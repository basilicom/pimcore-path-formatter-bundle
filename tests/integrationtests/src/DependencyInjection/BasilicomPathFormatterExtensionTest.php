<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Basilicom\PathFormatterBundle\Formatter\PatternRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Yaml\Yaml;

class BasilicomPathFormatterExtensionTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private array $arguments = [];

    #[Test]
    public function load_passesTheExampleConfigurationToTheServices(): void
    {
        // prepare
        $configs = Yaml::parse((string)file_get_contents($this->getConfigPath()));

        // test
        $this->load($configs);

        // verify
        $this->assertSame(true, $this->arguments[PatternRenderer::class]['$enableAssetPreview']);
        $this->assertSame(true, $this->arguments[BasilicomPathFormatter::class]['$enableInheritance']);
        $this->assertSame(true, $this->arguments[BasilicomPathFormatter::class]['$enableCache']);
        $this->assertSame([], $this->arguments[BasilicomPathFormatter::class]['$excludeContainerTypes']);
        $this->assertSame(
            '{key}',
            $this->arguments[BasilicomPathFormatter::class]['$defaultPattern']
        );
        $this->assertEquals(
            [
                'Pimcore\Model\DataObject\BasicProduct' => [
                    ConfigDefinition::PATTERN => 'Basic - {name}',
                ],
                'Pimcore\Model\DataObject\PremiumProduct' => [
                    ConfigDefinition::PATTERN => 'Premium - {name}[[ ({{ number(element.getPrice(), 2) }} {currency})]]',
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
            $this->arguments[BasilicomPathFormatter::class]['$patternConfiguration']
        );
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidConfigurationProvider(): array
    {
        return [
            'field context without overwrites' => [
                [ConfigDefinition::PATTERN => ['Pimcore\Model\DataObject\ProductList::relations' => '{name}']],
                'patternOverwrites',
            ],
            'class without a pattern' => [
                [ConfigDefinition::PATTERN => [
                    'Pimcore\Model\DataObject\Product' => [ConfigDefinition::PATTERN_OVERWRITES => ['A' => 'b']],
                ]],
                'needs a "pattern"',
            ],
            'unknown container type' => [
                [ConfigDefinition::EXCLUDE_CONTAINER_TYPES => ['fieldcollections']],
                'Unknown container type',
            ],
            'broken expression in a pattern' => [
                [ConfigDefinition::PATTERN => ['Pimcore\Model\DataObject\Product' => '{{ upper( }}']],
                'Invalid pattern',
            ],
            'broken expression in an overwrite' => [
                [ConfigDefinition::PATTERN => [
                    'Pimcore\Model\DataObject\ProductList::relations' => [
                        ConfigDefinition::PATTERN_OVERWRITES => ['Pimcore\Model\DataObject\Product' => '{{ 1 + }}'],
                    ],
                ]],
                'Invalid pattern',
            ],
            'broken expression in the default pattern' => [
                [ConfigDefinition::DEFAULT_PATTERN => '{{ unknownFunction() }}'],
                'Invalid pattern',
            ],
        ];
    }

    #[Test]
    #[DataProvider('invalidConfigurationProvider')]
    public function load_rejectsInvalidConfiguration(array $config, string $expectedMessage): void
    {
        // verify
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('~' . preg_quote($expectedMessage, '~') . '~');

        // test
        $this->load([$config]);
    }

    private function load(array $configs): void
    {
        $container = $this->createMock(ContainerBuilder::class);
        $container->method('getDefinition')->willReturnCallback(
            fn (string $id): Definition => $this->createRecordingDefinition($id)
        );

        (new BasilicomPathFormatterExtension())->load($configs, $container);
    }

    private function createRecordingDefinition(string $id): Definition
    {
        $definition = $this->createMock(Definition::class);
        $definition->method('setArgument')->willReturnCallback(
            function (int|string $key, mixed $value) use ($id, $definition): Definition {
                $this->arguments[$id][$key] = $value;

                return $definition;
            }
        );

        return $definition;
    }

    private function getConfigPath(): string
    {
        return dirname(__DIR__, 4) . '/src/Resources/config/pimcore/config.example.yaml';
    }
}
