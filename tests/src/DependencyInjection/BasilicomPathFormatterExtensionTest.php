<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Basilicom\PathFormatterBundle\DependencyInjection\PathFormatter\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Yaml\Yaml;

class BasilicomPathFormatterExtensionTest extends TestCase
{
    /**
     * @test
     */
    public function load(): void
    {
        // prepare
        $configs = Yaml::parse(file_get_contents(dirname(dirname(dirname(__DIR__))) . '/src/Resources/config/pimcore/config.yml'));

        $containerDefinitionMock = $this->createMock(Definition::class);
        $containerDefinitionMock->expects($this->once())
            ->method('setArgument')
            ->with(1, $this->isInstanceOf(Configuration::class));

        $containerMock = $this->createMock(ContainerBuilder::class);
        $containerMock->method('getDefinition')->willReturn($containerDefinitionMock);

        $classUnderTest = new BasilicomPathFormatterExtension();

        // test
        $classUnderTest->load($configs, $containerMock);
        // verified by setup
    }
}
