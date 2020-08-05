<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle\DependencyInjection;

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
        $assetMock->method('getFullPath')->willReturn(ProductMock::PATH . ProductMock::FILENAME);

        $productMock = new ProductMock();
        $productMock->setImage($assetMock);

        return [
            [
                $productMock,
                $pattern = '{price}{unit}',
                $rawPaths = [
                    [
                        'id' => 1,
                        'type' => 'object',
                    ],
                ],
                $expectedResult = ['10€'],
            ],
            [
                $productMock,
                $pattern = '{image} {price}{unit}',
                $rawPaths = [
                    [
                        'id' => 1,
                        'type' => 'object',
                    ],
                ],
                $expectedResult = ['<img src="' . ProductMock::PATH . ProductMock::FILENAME . '" style="height: 18px; margin-right: 5px;" /> 10€'],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider formatPathDataProvider
     *
     * @param ProductMock $productMock
     * @param string      $pattern
     * @param array       $rawPaths
     * @param array       $expectedResult
     *
     * @throws Exception
     */
    public function formatPath(ProductMock $productMock, string $pattern, array $rawPaths, array $expectedResult): void
    {
        // prepare
        $sourceMock = $this->createMock(ElementInterface::class);

        $pimcoreAdapterMock = $this->createMock(PimcoreAdapter::class);
        $pimcoreAdapterMock->method('getConcreteById')->willReturn($productMock);

        $classUnderTest = new BasilicomPathFormatter($pimcoreAdapterMock, $pattern);

        // test
        $result = $classUnderTest->formatPath([], $sourceMock, $rawPaths, []);

        // verify
        $this->assertEquals($expectedResult, $result);
    }
}
