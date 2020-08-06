<?php

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class ConfigDefinition implements ConfigurationInterface
{
    const ENABLE_ASSET_PREVIEW = 'enable_asset_preview';
    const PATTERN_LIST = 'pattern';

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
                ->arrayNode(self::PATTERN_LIST)
                    ->useAttributeAsKey('patternClass')
                    ->arrayPrototype()
                        ->validate()
                            ->always(function ($v) {
                                if (empty($v['patternOverwrites'])) {
                                    unset($v['patternOverwrites']);
                                }

                                return $v;
                            })
                        ->end()
                        ->beforeNormalization()
                            ->ifString()->then(function ($value) { return ['pattern' => $value]; })
                        ->end()
                        ->children()
                            ->scalarNode('pattern')->end()
                            ->arrayNode('patternOverwrites')
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
