<?php

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\DataObject\ClassDefinition\PathFormatterInterface;

class BasilicomPathFormatter implements PathFormatterInterface
{
    private $pimcoreAdapter;

    private $enableAssetPreview;

    private $patternConfiguration;

    public function __construct(PimcoreAdapter $pimcoreAdapter, bool $enableAssetPreview, array $patternConfiguration)
    {
        $this->pimcoreAdapter = $pimcoreAdapter;
        $this->enableAssetPreview = $enableAssetPreview;
        $this->patternConfiguration = $patternConfiguration;
    }

    /**
     * @param array            $result  containing the nice path info. Modify it or leave it as it is. Pass it out
     *                                  afterwards!
     * @param ElementInterface $source  the source object
     * @param array            $targets list of nodes describing the target elements
     * @param array            $params  optional parameters. may contain additional context information in the future.
     *                                  to be defined.
     *
     * @return array list of display names.
     */
    public function formatPath(array $result, ElementInterface $source, array $targets, array $params): array
    {
        if (!empty($this->patternConfiguration)) {
            foreach ($targets as $key => $item) {
                if ($item['type'] === 'object') {
                    $targetObject = $this->pimcoreAdapter->getConcreteById($item['id']);

                    foreach (array_reverse($this->patternConfiguration) as $patternKey => $patternConfig) {
                        if (strrpos($patternKey, '::') !== false) {
                            $formattedPath = $this->getFormattedPathWithContext(
                                $patternKey,
                                $patternConfig['patternOverwrites'],
                                $params['context'],
                                $source,
                                $targetObject
                            );
                        } else {
                            $formattedPath = $this->getFormattedPath(
                                $patternKey,
                                $patternConfig['pattern'],
                                $targetObject
                            );
                        }

                        if (!empty($formattedPath)) {
                            $result[$key] = $formattedPath;
                            break;
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function getFormattedPathWithContext(
        string $patternKey,
        array $patternOverwrites,
        array $context,
        ElementInterface $source,
        ?Concrete $targetObject
    ): string {
        $formattedPath = '';

        $contextClassName = substr($patternKey, 0, strpos($patternKey, '::'));
        $contextFieldName = substr($patternKey, strpos($patternKey, '::') + 2);

        $pattern = '';
        if (class_exists($contextClassName)
            && $source instanceof $contextClassName
            && $context['fieldname'] === $contextFieldName
        ) {
            foreach ($patternOverwrites as $className => $pattern) {
                if (class_exists($className) && $targetObject instanceof $className) {
                    break;
                }
            }

            $formattedPath = $this->getFormattedPath($className, $pattern, $targetObject);
        }

        return $formattedPath;
    }


    private function getFormattedPath(string $className, string $pattern, ?Concrete $targetObject): string
    {
        if (empty($pattern) || !class_exists($className) || !($targetObject instanceof $className)) {
            return '';
        }

        $propertyList = $this->getPropertyListFromPattern($pattern);

        $formattedPath = $pattern;
        foreach ($propertyList as $property) {
            $propertyGetter = 'get' . ucfirst(trim($property));
            if (method_exists($targetObject, $propertyGetter)) {
                $propertyValue = call_user_func([$targetObject, $propertyGetter]);
                if ($propertyValue instanceof Asset\Image) {
                    $imagePath = $propertyValue->getFullPath();
                    $replacement = $this->enableAssetPreview
                        ? '<img src="' . $imagePath . '" style="height: 18px; margin-right: 5px;" />'
                        : $imagePath;
                } else {
                    $replacement = $propertyValue;
                }

                $formattedPath = str_replace('{' . $property . '}', $replacement, $formattedPath);
            }
        }

        return $formattedPath;
    }

    private function getPropertyListFromPattern(string $pattern): array
    {
        $matches = [];
        preg_match_all('~{(.*?)}~', $pattern, $matches);

        return !empty($matches) ? $matches[1] : [];
    }

}
