<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\ProductList;
use Pimcore\Model\Element\ElementInterface;

class BasilicomPathFormatterTest extends TestCase
{
    /**
     * @return array
     */
    public function formatPathDataProvider(): array
    {
        $assetMock = $this->createMock(Image::class);
        $assetMock->method('getFullPath')
            ->willReturn('/images/some-file.png');

        $productMock = new Product();
        $productMock->setImage($assetMock);

        $rawPaths = [
            [
                'id' => 1,
                'type' => 'object',
            ],
        ];

        return [
            'only class property' => [
                $productMock,
                $patternConfig = [
                    'Pimcore\Model\DataObject\Concrete' =>
                        [
                            'pattern' => '{price}{unit}',
                        ],
                ],
                $rawPaths,
                $expectedResult = ['10€'],
            ],
            'pimcore concrete properties' => [
                $productMock,
                $patternConfig = [
                    'Pimcore\Model\DataObject\Concrete' =>
                        [
                            'pattern' => '{fullPath} - {price}{unit}',
                        ],
                ],
                $rawPaths,
                $expectedResult = ['/dataObjects/product - 10€'],
            ],
            'config for non-existing class' => [
                $productMock,
                $patternConfig = [
                    'Pimcore\Model\DataObject\Concreteeee' =>
                        [
                            'pattern' => '{fullPath} - {price}{unit}',
                        ],
                ],
                $rawPaths,
                $expectedResult = [],
            ],
            'use first true class check' => [
                $productMock,
                $patternConfig = [
                    'Pimcore\Model\DataObject\Concrete' => [
                        'pattern' => '{price}{unit}',
                    ],
                    'Basilicom\PathFormatterBundle\Fixtures\Product' => [
                        'pattern' => 'Product price: {price}{unit}',
                    ],
                ],
                $rawPaths,
                $expectedResult = ['10€'],
            ],
            'image rendering active' => [
                $productMock,
                $patternConfig = [
                    'Pimcore\Model\DataObject\Concrete' => [
                        'pattern' => '{image} {price}{unit}',
                    ],
                ],
                $rawPaths,
                $expectedResult = ['<img src="/images/some-file.png" style="height: 18px; margin-right: 5px;" /> 10€'],
                $imagePreviewRenderingEnabled = true,
            ],
            'image rendering inactive' => [
                $productMock,
                $patternConfig = [
                    'Pimcore\Model\DataObject\Concrete' => [
                        'pattern' => '{image} {price}{unit}',
                    ],
                ],
                $rawPaths,
                $expectedResult = ['/images/some-file.png 10€'],
                $imagePreviewRenderingEnabled = false,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider formatPathDataProvider
     *
     * @param Product $productMock
     * @param array   $patternConfig
     * @param array   $rawPaths
     * @param array   $expectedResult
     * @param bool    $imagePreviewRenderingEnabled
     */
    public function formatPath(
        Product $productMock,
        array $patternConfig,
        array $rawPaths,
        array $expectedResult,
        bool $imagePreviewRenderingEnabled = false
    ): void {
        // prepare
        $sourceMock = $this->createMock(ElementInterface::class);

        $pimcoreAdapterMock = $this->createMock(PimcoreAdapter::class);
        $pimcoreAdapterMock->method('getConcreteById')->willReturn($productMock);

        $classUnderTest = new BasilicomPathFormatter(
            $pimcoreAdapterMock,
            $imagePreviewRenderingEnabled,
            $patternConfig
        );

        // test
        $result = $classUnderTest->formatPath([], $sourceMock, $rawPaths, []);

        // verify
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @test
     */
    public function formatPath_patternOverwrites(): void
    {
        // prepare
        $rawPaths = [
            [
                'id' => 1,
                'type' => 'object',
            ],
        ];

        $params = [
            'context' => [
                'containerType' => 'object',
                'fieldname' => 'countryRelations',
                'objectId' => '1',
            ],
        ];
        $productMock = new Product();
        $expectedResult = ['[de] Product: Sneakers'];

        $sourceMock = $this->createMock(ProductList::class);

        $patternConfig = [
            'Pimcore\Model\DataObject\Product' => [
                ConfigDefinition::PATTERN => 'Product: {name}',
            ],
            'Pimcore\Model\DataObject\ProductList::countryRelations' => [
                ConfigDefinition::PATTERN_OVERWRITES => [
                    'Pimcore\Model\DataObject\Product' => '[{countryIso}] Product: {name}',
                ],
            ],
        ];

        $pimcoreAdapterMock = $this->createMock(PimcoreAdapter::class);
        $pimcoreAdapterMock->method('getConcreteById')->willReturn($productMock);

        $classUnderTest = new BasilicomPathFormatter($pimcoreAdapterMock, false, $patternConfig);

        // test
        $result = $classUnderTest->formatPath([], $sourceMock, $rawPaths, $params);

        // verify
        $this->assertEquals($expectedResult, $result);
    }
}
