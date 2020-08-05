<?php

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Pimcore\Model\Asset;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\DataObject\ClassDefinition\PathFormatterInterface;

class BasilicomPathFormatter implements PathFormatterInterface
{
    private $pattern;

    private $pimcoreAdapter;

    public function __construct(PimcoreAdapter $pimcoreAdapter, string $pattern = '')
    {
        $this->pimcoreAdapter = $pimcoreAdapter;
        $this->pattern = $pattern;
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
        if (!empty($this->pattern)) {
            foreach ($targets as $key => $item) {
                if ($item['type'] === 'object') {
                    $result[$key] = $this->formatDataObjectPath($item);
                }
            }
        }

        return $result;
    }

    private function formatDataObjectPath(array $item): string
    {
        $targetObject = $this->pimcoreAdapter->getConcreteById($item['id']);
        $propertyList = $this->getPropertyListFromPattern();

        $formattedPath = $this->pattern;
        foreach ($propertyList as $property) {
            $propertyGetter = 'get' . ucfirst(trim($property));
            if (method_exists($targetObject, $propertyGetter)) {
                $propertyValue = call_user_func([$targetObject, $propertyGetter]);
                if ($propertyValue instanceof Asset\Image) {
                    $replacement = '<img src="' . $propertyValue->getFullPath() . '" style="height: 18px; margin-right: 5px;" />';
                } else {
                    $replacement = $propertyValue;
                }

                $formattedPath = str_replace('{' . $property . '}', $replacement, $formattedPath);
            }
        }

        return $formattedPath;
    }

    /**
     * @return array
     */
    private function getPropertyListFromPattern(): array
    {
        $matches = [];
        preg_match_all('~{(.*?)}~', $this->pattern, $matches);

        return !empty($matches) ? $matches[1] : [];
    }
}
