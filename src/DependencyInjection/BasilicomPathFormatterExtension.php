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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;

class BasilicomPathFormatterExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new ConfigDefinition();
        $config        = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $container
            ->getDefinition(PatternRenderer::class)
            ->setArgument('$enableAssetPreview', (bool)$config[ConfigDefinition::ENABLE_ASSET_PREVIEW]);

        $container
            ->getDefinition(BasilicomPathFormatter::class)
            ->setArgument('$enableInheritance', (bool)$config[ConfigDefinition::ENABLE_INHERITANCE])
            ->setArgument('$enableCache', (bool)$config[ConfigDefinition::ENABLE_CACHE])
            ->setArgument('$defaultPattern', $config[ConfigDefinition::DEFAULT_PATTERN])
            ->setArgument('$excludeContainerTypes', (array)$config[ConfigDefinition::EXCLUDE_CONTAINER_TYPES])
            ->setArgument('$patternConfiguration', (array)$config[ConfigDefinition::PATTERN]);
    }
}
