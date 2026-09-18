<?php

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
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigDefinition implements ConfigurationInterface
{
    public const ENABLE_ASSET_PREVIEW    = 'enable_asset_preview';
    public const ENABLE_INHERITANCE      = 'enable_inheritance';
    public const ENABLE_CACHE            = 'enable_cache';
    public const DEFAULT_PATTERN         = 'default_pattern';
    public const EXCLUDE_CONTAINER_TYPES = 'exclude_container_types';
    public const PATTERN                 = 'pattern';
    public const PATTERN_OVERWRITES      = 'patternOverwrites';

    public const CONTAINER_TYPES = ['object', 'localizedfield', 'objectbrick', 'fieldcollection', 'block'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('basilicom_path_formatter');

        $treeBuilder
            ->getRootNode()
            ->children()
                ->booleanNode(self::ENABLE_ASSET_PREVIEW)
                    ->defaultTrue()
                ->end()
                ->booleanNode(self::ENABLE_INHERITANCE)
                    ->defaultTrue()
                ->end()
                ->booleanNode(self::ENABLE_CACHE)
                    ->defaultTrue()
                ->end()
                ->scalarNode(self::DEFAULT_PATTERN)
                    ->defaultNull()
                ->end()
                ->arrayNode(self::EXCLUDE_CONTAINER_TYPES)
                    ->scalarPrototype()->end()
                    ->validate()
                        ->always(function (array $types): array {
                            foreach ($types as $type) {
                                if (!in_array($type, self::CONTAINER_TYPES, true)) {
                                    throw new InvalidConfigurationException(sprintf(
                                        'Unknown container type "%s", expected one of: %s.',
                                        $type,
                                        implode(', ', self::CONTAINER_TYPES)
                                    ));
                                }
                            }

                            return $types;
                        })
                    ->end()
                ->end()
                ->arrayNode(self::PATTERN)
                    ->useAttributeAsKey('patternClass')
                    ->arrayPrototype()
                        ->validate()
                            ->always(function ($v) {
                                if (empty($v[ConfigDefinition::PATTERN_OVERWRITES])) {
                                    unset($v[ConfigDefinition::PATTERN_OVERWRITES]);
                                }

                                return $v;
                            })
                        ->end()
                        ->beforeNormalization()
                            ->ifString()
                            ->then(function ($value) {
                                return [ConfigDefinition::PATTERN => $value];
                            })
                        ->end()
                        ->children()
                            ->scalarNode(self::PATTERN)->end()
                            ->arrayNode(self::PATTERN_OVERWRITES)
                                ->useAttributeAsKey('patternClass')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->always(function (array $patterns): array {
                            foreach ($patterns as $key => $config) {
                                $this->assertPatternShape((string)$key, $config);
                            }

                            return $patterns;
                        })
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->always(function (array $config): array {
                    foreach ($this->collectPatterns($config) as $origin => $pattern) {
                        $this->assertPatternSyntax($origin, $pattern);
                    }

                    return $config;
                })
            ->end();

        return $treeBuilder;
    }

    /** @return array<string, string> */
    private function collectPatterns(array $config): array
    {
        $patterns = [];

        if (is_string($config[self::DEFAULT_PATTERN] ?? null)) {
            $patterns[self::DEFAULT_PATTERN] = $config[self::DEFAULT_PATTERN];
        }

        foreach ($config[self::PATTERN] ?? [] as $key => $entry) {
            if (isset($entry[self::PATTERN])) {
                $patterns[$key] = $entry[self::PATTERN];
            }

            foreach ($entry[self::PATTERN_OVERWRITES] ?? [] as $targetClass => $pattern) {
                $patterns[$key . ' → ' . $targetClass] = $pattern;
            }
        }

        return $patterns;
    }

    private function assertPatternShape(string $key, array $config): void
    {
        $isFieldContext = str_contains($key, '::');

        if ($isFieldContext && empty($config[self::PATTERN_OVERWRITES])) {
            throw new InvalidConfigurationException(
                sprintf('"%s" addresses a field and therefore needs "%s".', $key, self::PATTERN_OVERWRITES)
            );
        }

        if (!$isFieldContext && empty($config[self::PATTERN])) {
            throw new InvalidConfigurationException(
                sprintf('"%s" addresses a class and therefore needs a "%s".', $key, self::PATTERN)
            );
        }
    }

    private function assertPatternSyntax(string $origin, string $pattern): void
    {
        $errors = PatternRenderer::lint($pattern);

        if ($errors !== []) {
            throw new InvalidConfigurationException(
                sprintf('Invalid pattern for "%s": %s', $origin, implode('; ', $errors))
            );
        }
    }
}
