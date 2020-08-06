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

    private $patternList;

    public function __construct(PimcoreAdapter $pimcoreAdapter, bool $enableAssetPreview, array $patternList)
    {
        $this->pimcoreAdapter = $pimcoreAdapter;
        $this->enableAssetPreview = $enableAssetPreview;
        $this->patternList = $patternList;
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
        if (!empty($this->patternList)) {
            foreach ($targets as $key => $item) {
                if ($item['type'] === 'object') {
                    $targetObject = $this->pimcoreAdapter->getConcreteById($item['id']);

                    foreach ($this->patternList as $className => $pattern) {
                        if (class_exists($className) && $targetObject instanceof $className) {
                            $result[$key] = $this->formatDataObjectPath($targetObject, $pattern);
                            break;
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function formatDataObjectPath(Concrete $targetObject, string $pattern): string
    {
        $propertyList = $this->getPropertyListFromPattern($pattern);

        $formattedPath = $pattern;
        foreach ($propertyList as $property) {
            $propertyGetter = 'get' . ucfirst(trim($property));
            if (method_exists($targetObject, $propertyGetter)) {
                $propertyValue = call_user_func([$targetObject, $propertyGetter]);
                if ($propertyValue instanceof Asset\Image) {
                    $replacement = $this->getFormattedAssetValue($propertyValue);
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

    private function getFormattedAssetValue(Asset\Image $propertyValue): string
    {
        $imagePath = $propertyValue->getFullPath();

        return $this->enableAssetPreview
            ? '<img src="' . $imagePath . '" style="height: 18px; margin-right: 5px;" />'
            : $imagePath;
    }
}
