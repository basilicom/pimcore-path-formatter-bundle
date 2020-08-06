<?php

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Basilicom\PathFormatterBundle\DependencyInjection\PathFormatter\Configuration;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

class BasilicomPathFormatterExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new ConfigDefinition();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $pathFormatterConfig = new Configuration(
            (bool) $config[ConfigDefinition::ENABLE_ASSET_PREVIEW],
            (array) $config[ConfigDefinition::PATTERN_LIST]
        );

        $container->getDefinition(BasilicomPathFormatter::class)->setArgument(1, $pathFormatterConfig);
    }
}
