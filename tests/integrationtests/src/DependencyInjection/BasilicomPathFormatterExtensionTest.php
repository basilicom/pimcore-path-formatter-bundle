<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Yaml\Yaml;

class BasilicomPathFormatterExtensionTest extends TestCase
{
    #[Test]
    public function load(): void
    {
        // prepare
        $configs = Yaml::parse(file_get_contents($this->getConfigPath()));

        $containerDefinitionMock = $this->createMock(Definition::class);

        $matcher = $this->exactly(3);
        $containerDefinitionMock
            ->expects($matcher)
            ->method('setArgument')
            ->willReturnCallback(function (int|string $index, mixed $value) use ($matcher, $containerDefinitionMock) {
                match ($matcher->numberOfInvocations()) {
                    1 => $this->assertEquals([1, true], [$index, $value]),
                    2 => $this->assertEquals([2, true], [$index, $value]),
                    3 => $this->assertEquals([
                        3,
                        [
                            'Pimcore\Model\DataObject\BasicProduct' => [
                                ConfigDefinition::PATTERN => 'Basic - {name}',
                            ],
                            'Pimcore\Model\DataObject\PremiumProduct' => [
                                ConfigDefinition::PATTERN => 'Premium - {name}',
                            ],
                            'Pimcore\Model\DataObject\ProductList' => [
                                ConfigDefinition::PATTERN => 'Product-list with {count} products',
                            ],
                            'Pimcore\Model\DataObject\ProductList::countryRelations' => [
                                ConfigDefinition::PATTERN_OVERWRITES => [
                                    'Pimcore\Model\DataObject\BasicProduct'   => '[{countryIso}] Basic - {name}',
                                    'Pimcore\Model\DataObject\PremiumProduct' => '[{countryIso}] Premium - {name}',
                                ],
                            ],
                        ],
                    ], [$index, $value]),
                };

                return $containerDefinitionMock;
            });

        $containerMock = $this->createMock(ContainerBuilder::class);
        $containerMock->method('getDefinition')->willReturn($containerDefinitionMock);

        $classUnderTest = new BasilicomPathFormatterExtension();

        // test
        $classUnderTest->load($configs, $containerMock);
        // verified by setup
    }

    /**  */
    private function getConfigPath(): string
    {
        return dirname(dirname(dirname(dirname(__DIR__)))) . '/src/Resources/config/pimcore/config.example.yaml';
    }
}
