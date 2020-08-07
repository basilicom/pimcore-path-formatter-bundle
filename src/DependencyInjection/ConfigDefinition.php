<?php

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ConfigDefinition implements ConfigurationInterface
{
    public const ENABLE_ASSET_PREVIEW = 'enable_asset_preview';
    public const PATTERN = 'pattern';
    public const PATTERN_OVERWRITES = 'patternOverwrites';

    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('basilicom_path_formatter');
        $treeBuilder
            ->getRootNode()
            ->children()
                ->booleanNode(self::ENABLE_ASSET_PREVIEW)->defaultTrue()->end()
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
                            ->ifString()->then(function ($value) { return ['pattern' => $value]; })
                        ->end()
                        ->children()
                            ->scalarNode('pattern')->end()
                            ->arrayNode(self::PATTERN_OVERWRITES)
                                ->useAttributeAsKey('patternClass')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
