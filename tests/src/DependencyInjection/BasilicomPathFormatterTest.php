<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Basilicom\PathFormatterBundle\DependencyInjection\PathFormatter\Configuration;
use Basilicom\PathFormatterBundle\Fixtures\ProductMock;
use Exception;
use PHPUnit\Framework\TestCase;
use Pimcore\Model\Asset\Image;
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

        $productMock = new ProductMock();
        $productMock->setImage($assetMock);

        return [
            'only class property' => [
                $productMock,
                $patternConfig = ['Pimcore\Model\DataObject\Concrete' => '{price}{unit}'],
                $rawPaths = [
                    [
                        'id' => 1,
                        'type' => 'object',
                    ],
                ],
                $expectedResult = ['10€'],
            ],
            'pimcore concrete properties' => [
                $productMock,
                $patternConfig = ['Pimcore\Model\DataObject\Concrete' => '{fullPath} - {price}{unit}'],
                $rawPaths = [
                    [
                        'id' => 1,
                        'type' => 'object',
                    ],
                ],
                $expectedResult = ['/dataObjects/product - 10€'],
            ],
            'use first true class check' => [
                $productMock,
                $patternConfig = [
                    'Pimcore\Model\DataObject\Concrete' => '{price}{unit}',
                    'Basilicom\PathFormatterBundle\Fixtures\ProductMock' => 'Product price: {price}{unit}',
                ],
                $rawPaths = [
                    [
                        'id' => 1,
                        'type' => 'object',
                    ],
                ],
                $expectedResult = ['10€'],
            ],
            'image rendering active' => [
                $productMock,
                $patternConfig = ['Pimcore\Model\DataObject\Concrete' => '{image} {price}{unit}'],
                $rawPaths = [
                    [
                        'id' => 1,
                        'type' => 'object',
                    ],
                ],
                $expectedResult = ['<img src="/images/some-file.png" style="height: 18px; margin-right: 5px;" /> 10€'],
                $imagePreviewRenderingEnabled = true,
            ],
            'image rendering inactive' => [
                $productMock,
                $patternConfig = ['Pimcore\Model\DataObject\Concrete' => '{image} {price}{unit}'],
                $rawPaths = [
                    [
                        'id' => 1,
                        'type' => 'object',
                    ],
                ],
                $expectedResult = ['/images/some-file.png 10€'],
                $imagePreviewRenderingEnabled = false,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider formatPathDataProvider
     *
     * @param ProductMock $productMock
     * @param array       $patternConfig
     * @param array       $rawPaths
     * @param array       $expectedResult
     * @param bool        $imagePreviewRenderingEnabled
     */
    public function formatPath(
        ProductMock $productMock,
        array $patternConfig,
        array $rawPaths,
        array $expectedResult,
        bool $imagePreviewRenderingEnabled = false
    ): void {
        // prepare
        $sourceMock = $this->createMock(ElementInterface::class);

        $pimcoreAdapterMock = $this->createMock(PimcoreAdapter::class);
        $pimcoreAdapterMock->method('getConcreteById')->willReturn($productMock);

        $configMock = $this->createMock(Configuration::class);
        $configMock->method('getPatternList')->willReturn($patternConfig);
        $configMock->method('isAssetPreviewEnabled')->willReturn($imagePreviewRenderingEnabled);

        $classUnderTest = new BasilicomPathFormatter($pimcoreAdapterMock, $configMock);

        // test
        $result = $classUnderTest->formatPath([], $sourceMock, $rawPaths, []);

        // verify
        $this->assertEquals($expectedResult, $result);
    }
}
