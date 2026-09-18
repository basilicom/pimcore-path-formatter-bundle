<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Basilicom\PathFormatterBundle\Formatter\ExpressionFunctions;
use Basilicom\PathFormatterBundle\Formatter\PatternRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\ProductList;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;

class BasilicomPathFormatterCacheTest extends TestCase
{
    private const TARGET  = [['id' => 1, 'type' => 'object']];
    private const PATTERN = ['Pimcore\Model\DataObject\Product' => ['pattern' => '{key} of {parent.key}']];

    #[Test]
    public function formatPath_usesACachedValueInsteadOfRendering(): void
    {
        // prepare
        $adapter = $this->createAdapter();
        $adapter->method('loadFromCache')->willReturn('from cache');
        $adapter->expects($this->never())->method('saveToCache');

        // test
        $result = $this->createFormatter($adapter)->formatPath([], $this->createSource(), self::TARGET, []);

        // verify
        $this->assertSame(['from cache'], $result);
    }

    #[Test]
    public function formatPath_cachesWithTheTagsOfEveryTouchedElement(): void
    {
        // prepare
        $adapter = $this->createAdapter();
        $adapter->method('loadFromCache')->willReturn(false);

        $savedTags = null;
        $adapter->method('saveToCache')->willReturnCallback(
            function (string $key, string $value, array $tags) use (&$savedTags): void {
                $savedTags = $tags;
            }
        );

        // test
        $result = $this->createFormatter($adapter)->formatPath([], $this->createSource(), self::TARGET, []);

        // verify: the target plus the folder reached through {parent.key}
        $this->assertSame(['sneakers of root'], $result);
        $this->assertEqualsCanonicalizing(['object_7', 'object_1'], $savedTags);
    }

    #[Test]
    public function formatPath_cachesWithTheAncestorChainWhenInheritanceIsOn(): void
    {
        // prepare: an inherited value belongs to an ancestor, so that ancestor has to invalidate
        $grandParent = new Product();
        $grandParent->setId(20);
        $grandParent->setKey('grandparent');

        $parent = new Product();
        $parent->setId(10);
        $parent->setKey('parent');
        $parent->setParent($grandParent);

        $product = new Product();
        $product->setId(1);
        $product->setKey('sneakers');
        $product->setPath('/dataObjects/');
        $product->setParent($parent);

        $adapter = $this->createMock(PimcoreAdapter::class);
        $adapter->method('getConcreteById')->willReturn($product);
        $adapter->method('loadFromCache')->willReturn(false);

        $savedTags = null;
        $adapter->method('saveToCache')->willReturnCallback(
            function (string $key, string $value, array $tags) use (&$savedTags): void {
                $savedTags = $tags;
            }
        );

        // test
        $this->createFormatter($adapter)->formatPath([], $this->createSource(), self::TARGET, []);

        // verify
        $this->assertEqualsCanonicalizing(['object_1', 'object_10', 'object_20'], $savedTags);
    }

    #[Test]
    public function formatPath_rendersOnlyOnceForRepeatedTargets(): void
    {
        // prepare
        $adapter = $this->createAdapter();
        $adapter->expects($this->once())->method('loadFromCache')->willReturn(false);
        $adapter->expects($this->once())->method('saveToCache');

        // test
        $result = $this->createFormatter($adapter)->formatPath(
            [],
            $this->createSource(),
            [self::TARGET[0], self::TARGET[0], self::TARGET[0]],
            []
        );

        // verify
        $this->assertSame(array_fill(0, 3, 'sneakers of root'), $result);
    }

    #[Test]
    public function formatPath_doesNotTouchTheCacheWhenItIsDisabled(): void
    {
        // prepare
        $adapter = $this->createAdapter();
        $adapter->expects($this->never())->method('loadFromCache');
        $adapter->expects($this->never())->method('saveToCache');

        // test
        $result = $this->createFormatter($adapter, enableCache: false)
            ->formatPath([], $this->createSource(), self::TARGET, []);

        // verify
        $this->assertSame(['sneakers of root'], $result);
    }

    private function createAdapter(): PimcoreAdapter
    {
        $adapter = $this->createMock(PimcoreAdapter::class);
        $adapter->method('getConcreteById')->willReturn($this->createProduct());

        return $adapter;
    }

    private function createFormatter(PimcoreAdapter $adapter, bool $enableCache = true): BasilicomPathFormatter
    {
        $localeService = $this->createMock(LocaleServiceInterface::class);
        $localeService->method('getLocale')->willReturn('de');

        $translator = $this->createMock(TranslatorInterface::class);

        return new BasilicomPathFormatter(
            $adapter,
            new PatternRenderer(
                new ExpressionFunctions($localeService, $translator),
                $localeService,
                $translator,
                new NullLogger(),
                false
            ),
            $localeService,
            true,
            $enableCache,
            null,
            [],
            self::PATTERN
        );
    }

    private function createProduct(): Product
    {
        $parent = new DataObject\Folder();
        $parent->setId(7);
        $parent->setKey('root');

        $product = new Product();
        $product->setId(1);
        $product->setKey('sneakers');
        $product->setPath('/dataObjects/');
        $product->setParent($parent);

        return $product;
    }

    private function createSource(): ProductList
    {
        return $this->createMock(ProductList::class);
    }
}
