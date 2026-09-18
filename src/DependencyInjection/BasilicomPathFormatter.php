<?php

declare(strict_types=1);

/**
 * This source file is available under the terms of the MIT License.
 * Full copyright and license information is available in
 * LICENSE which is distributed with this source code.
 *
 * @copyright Copyright (c) Basilicom GmbH (https://basilicom.de)
 * @license   MIT
 */

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Basilicom\PathFormatterBundle\Formatter\PatternRenderer;
use Basilicom\PathFormatterBundle\Formatter\RenderResult;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Model\DataObject\ClassDefinition\PathFormatterInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Element\ElementInterface;

class BasilicomPathFormatter implements PathFormatterInterface
{
    private const CACHE_KEY_PREFIX = 'basilicom_path_formatter_';

    /** @var array<string, string> */
    private array $globalPatterns = [];

    /** @var array<string, array<string, string>> */
    private array $contextPatterns = [];

    /** @var array<string, string> */
    private array $memoized = [];

    /** @param string[] $excludeContainerTypes */
    public function __construct(
        private readonly PimcoreAdapter $pimcoreAdapter,
        private readonly PatternRenderer $renderer,
        private readonly LocaleServiceInterface $localeService,
        private readonly bool $enableInheritance,
        private readonly bool $enableCache,
        private readonly ?string $defaultPattern,
        private readonly array $excludeContainerTypes,
        array $patternConfiguration,
    ) {
        // Reversed once so that later config entries win ties, as in previous versions.
        foreach (array_reverse($patternConfiguration, true) as $key => $config) {
            if (str_contains($key, '::')) {
                $this->contextPatterns[$key] = array_reverse($config[ConfigDefinition::PATTERN_OVERWRITES] ?? [], true);
            } elseif (!empty($config[ConfigDefinition::PATTERN])) {
                $this->globalPatterns[$key] = $config[ConfigDefinition::PATTERN];
            }
        }
    }

    public function formatPath(array $result, ElementInterface $source, array $targets, array $params): array
    {
        $context = is_array($params['context'] ?? null) ? $params['context'] : [];

        if (in_array($context['containerType'] ?? null, $this->excludeContainerTypes, true)) {
            return $this->fallbackForAll($result, $targets);
        }

        $previousInheritance = Concrete::getGetInheritedValues();
        Concrete::setGetInheritedValues($this->enableInheritance);

        try {
            foreach ($targets as $key => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $target    = $this->getTargetElement($item);
                $pattern   = $target === null ? null : $this->resolvePattern($target, $source, $context);
                $formatted = $pattern === null ? null : $this->format($pattern, $target);

                // A pattern that resolves to nothing at all is less useful than the plain path.
                $formatted = $formatted === '' ? null : $formatted;

                // Studio's FormatedPath DTO rejects null, so every target keeps at least its own path.
                $result[$key] = $formatted
                    ?? ($result[$key] ?? null)
                    ?? $target?->getFullPath()
                    ?? $this->rawPath($item);
            }
        } finally {
            Concrete::setGetInheritedValues($previousInheritance);
        }

        return $result;
    }

    /** @return array{pattern: ?string, value: ?string, trace: array<string, string>, tags: string[]} */
    public function explain(ElementInterface $target, ElementInterface $source, array $context): array
    {
        $pattern = $this->resolvePattern($target, $source, $context);
        if ($pattern === null) {
            return ['pattern' => null, 'value' => null, 'trace' => [], 'tags' => []];
        }

        $previousInheritance = Concrete::getGetInheritedValues();
        Concrete::setGetInheritedValues($this->enableInheritance);

        try {
            $rendered = $this->renderer->render($pattern, $target);
        } finally {
            Concrete::setGetInheritedValues($previousInheritance);
        }

        return [
            'pattern' => $pattern,
            'value'   => $rendered->value,
            'trace'   => $rendered->trace,
            'tags'    => $this->collectTags($rendered, $target),
        ];
    }

    /** @param array<array-key, mixed> $targets */
    private function fallbackForAll(array $result, array $targets): array
    {
        foreach ($targets as $key => $item) {
            if (is_array($item)) {
                $result[$key] ??= $this->rawPath($item);
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $item */
    private function rawPath(array $item): string
    {
        return (string)($item['path'] ?? $item['fullPath'] ?? '');
    }

    /** @param array<string, mixed> $item */
    private function getTargetElement(array $item): ?ElementInterface
    {
        $id = (int)($item['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return match ($item['type'] ?? null) {
            'object'   => $this->pimcoreAdapter->getConcreteById($id),
            'asset'    => $this->pimcoreAdapter->getAssetById($id),
            'document' => $this->pimcoreAdapter->getDocumentById($id),
            default    => null,
        };
    }

    /** @param array<string, mixed> $context */
    private function resolvePattern(ElementInterface $target, ElementInterface $source, array $context): ?string
    {
        $fieldName = $context['fieldname'] ?? null;

        if (is_string($fieldName)) {
            foreach ($this->contextPatterns as $contextKey => $overwrites) {
                [$contextClass, $contextField] = explode('::', $contextKey, 2);

                if ($contextField === $fieldName && $source instanceof $contextClass) {
                    $pattern = $this->matchMostSpecific($overwrites, $target);
                    if ($pattern !== null) {
                        return $pattern;
                    }
                }
            }
        }

        return $this->matchMostSpecific($this->globalPatterns, $target) ?? $this->defaultPattern;
    }

    /** @param array<string, string> $patterns */
    private function matchMostSpecific(array $patterns, ElementInterface $target): ?string
    {
        $bestClass   = null;
        $bestPattern = null;

        foreach ($patterns as $className => $pattern) {
            if (!$target instanceof $className) {
                continue;
            }

            if ($bestClass === null || is_subclass_of($className, $bestClass)) {
                $bestClass   = $className;
                $bestPattern = $pattern;
            }
        }

        return $bestPattern;
    }

    private function format(string $pattern, ElementInterface $target): string
    {
        $cacheKey = $this->getCacheKey($pattern, $target);

        if (isset($this->memoized[$cacheKey])) {
            return $this->memoized[$cacheKey];
        }

        if ($this->enableCache) {
            $cached = $this->pimcoreAdapter->loadFromCache($cacheKey);
            if (is_string($cached)) {
                return $this->memoized[$cacheKey] = $cached;
            }
        }

        $rendered = $this->renderer->render($pattern, $target);

        if ($this->enableCache) {
            $this->pimcoreAdapter->saveToCache($cacheKey, $rendered->value, $this->collectTags($rendered, $target));
        }

        return $this->memoized[$cacheKey] = $rendered->value;
    }

    /** @return string[] */
    private function collectTags(RenderResult $rendered, ElementInterface $target): array
    {
        $tags                         = $rendered->tags;
        $tags[$target->getCacheTag()] = $target->getCacheTag();

        // An inherited value belongs to an ancestor, so the whole chain has to invalidate the entry.
        if ($this->enableInheritance && $target instanceof Concrete) {
            for ($parent = $target->getParent(); $parent instanceof Concrete; $parent = $parent->getParent()) {
                $tags[$parent->getCacheTag()] = $parent->getCacheTag();
            }
        }

        return array_values($tags);
    }

    private function getCacheKey(string $pattern, ElementInterface $target): string
    {
        return self::CACHE_KEY_PREFIX . sha1(implode('|', [
            $pattern,
            $target->getCacheTag(),
            (string)$target->getModificationDate(),
            (string)$this->localeService->getLocale(),
            (string)$this->enableInheritance,
            $this->renderer->cacheDiscriminator(),
        ]));
    }
}
