<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Pimcore\Model\Asset;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\PathFormatterInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Document;
use Pimcore\Model\Element\AbstractElement;
use Pimcore\Model\Element\ElementInterface;

class BasilicomPathFormatter implements PathFormatterInterface
{
    private PimcoreAdapter $pimcoreAdapter;

    private bool $enableAssetPreview;

    private array $patternConfiguration;

    private bool $enableInheritance;

    public function __construct(
        PimcoreAdapter $pimcoreAdapter,
        bool $enableInheritance,
        bool $enableAssetPreview,
        array $patternConfiguration
    ) {
        $this->pimcoreAdapter       = $pimcoreAdapter;
        $this->enableAssetPreview   = $enableAssetPreview;
        $this->patternConfiguration = $patternConfiguration;
        $this->enableInheritance    = $enableInheritance;
    }

    public function formatPath(array $result, ElementInterface $source, array $targets, array $params): array
    {
        if (empty($this->patternConfiguration)) {
            return empty($result) ? [null] : $result;
        }

        foreach ($targets as $key => $item) {
            $targetElement = $this->getTargetElement($item);
            if (!$targetElement) {
                continue;
            }

            foreach (array_reverse($this->patternConfiguration) as $patternKey => $patternConfig) {
                if (strrpos($patternKey, '::') !== false) {
                    $formattedPath = $this->getFormattedPathWithContext(
                        $patternKey,
                        $patternConfig[ConfigDefinition::PATTERN_OVERWRITES],
                        $params['context'],
                        $source,
                        $targetElement
                    );
                } else {
                    $formattedPath = $this->getFormattedPath(
                        $patternKey,
                        $patternConfig[ConfigDefinition::PATTERN],
                        $targetElement
                    );
                }

                if (!empty($formattedPath)) {
                    $result[$key] = $formattedPath;
                    break;
                }
            }
        }

        return empty($result) ? [null] : $result;
    }

    private function getTargetElement(array $item): DataObject|Asset|Document|null
    {
        $targetElement = null;
        if ($item['type'] === 'object') {
            $targetElement = $this->pimcoreAdapter->getConcreteById($item['id']);
        } elseif ($item['type'] === 'asset') {
            $targetElement = $this->pimcoreAdapter->getAssetById($item['id']);
        } elseif ($item['type'] === 'document') {
            $targetElement = $this->pimcoreAdapter->getDocumentById($item['id']);
        }

        return $targetElement;
    }

    private function getFormattedPathWithContext(
        string $patternKey,
        array $patternOverwrites,
        array $context,
        ElementInterface $source,
        ?AbstractElement $targetElement
    ): string {
        $formattedPath = '';

        $contextClassName = substr($patternKey, 0, strpos($patternKey, '::'));
        $contextFieldName = substr($patternKey, strpos($patternKey, '::') + 2);

        if (class_exists($contextClassName)
            && $source instanceof $contextClassName
            && $context['fieldname'] === $contextFieldName
        ) {
            foreach ($patternOverwrites as $className => $pattern) {
                if (class_exists($className) && $targetElement instanceof $className) {
                    $formattedPath = $this->getFormattedPath($className, $pattern, $targetElement);
                    break;
                }
            }
        }

        return $formattedPath;
    }

    private function getFormattedPath(string $className, string $pattern, ?AbstractElement $targetElement): string
    {
        if (empty($pattern) || !class_exists($className) || !($targetElement instanceof $className)) {
            return '';
        }

        $wasInheritanceEnabled = Concrete::getGetInheritedValues();
        if ($this->enableInheritance) {
            Concrete::setGetInheritedValues(true);
        }

        $formattedPath = $this->format($pattern, $targetElement);

        if ($this->enableInheritance) {
            Concrete::setGetInheritedValues($wasInheritanceEnabled);
        }

        return $formattedPath;
    }

    private function format(string $pattern, AbstractElement $targetElement): string
    {
        $formattedPath = $pattern;
        if ($targetElement instanceof Asset\Image && $this->enableAssetPreview) {
            $formattedPath = '<img src="' . $targetElement->getFullPath() . '" style="height: 18px; margin-right: 5px;" /> ' . $formattedPath;
        }

        $propertyList = $this->getPropertyListFromPattern($pattern);
        foreach ($propertyList as $property) {
            $propertyValue = $this->resolvePropertyValue($targetElement, $property);
            $replacement   = '';

            if ($propertyValue !== null) {
                if ($propertyValue instanceof Asset\Image) {
                    $imagePath   = $propertyValue->getFullPath();
                    $replacement = $this->enableAssetPreview
                        ? '<img src="' . $imagePath . '" style="height: 18px; margin-right: 5px;" />'
                        : $imagePath;
                } else {
                    $replacement = (string)$propertyValue;
                }
            }

            $formattedPath = str_replace('{' . $property . '}', $replacement, $formattedPath);
        }

        return $formattedPath;
    }

    private function resolvePropertyValue(?object $element, string $propertyPath): mixed
    {
        $properties   = explode('.', $propertyPath);
        $currentValue = $element;

        foreach ($properties as $property) {
            if (!is_object($currentValue)) {
                return null;
            }

            $getter = 'get' . ucfirst(trim($property));
            if (method_exists($currentValue, $getter)) {
                $currentValue = call_user_func([$currentValue, $getter]);
            } else {
                return null;
            }
        }

        return $currentValue;
    }

    private function getPropertyListFromPattern(string $pattern): array
    {
        $matches = [];
        preg_match_all('~{(.*?)}~', $pattern, $matches);

        return $matches[1];
    }
}
