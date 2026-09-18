<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle\DependencyInjection;

use Basilicom\PathFormatterBundle\Formatter\ExpressionFunctions;
use Basilicom\PathFormatterBundle\Formatter\PatternRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\DataObject\ProductList;
use Pimcore\Model\Element\ElementInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;

class BasilicomPathFormatterTest extends TestCase
{
    private const TARGET = [['id' => 1, 'type' => 'object']];

    #[Test]
    public function formatPath_appliesTheConfiguredPattern(): void
    {
        // prepare
        $formatter = $this->createFormatter(['Pimcore\Model\DataObject\Product' => ['pattern' => '{key} {price}{unit}']]);

        // test
        $result = $formatter->formatPath([], $this->createSource(), self::TARGET, []);

        // verify
        $this->assertSame(['sneakers 10€'], $result);
    }

    #[Test]
    public function formatPath_fallsBackToTheElementPathWithoutAMatchingPattern(): void
    {
        // prepare
        $formatter = $this->createFormatter(['Pimcore\Model\DataObject\DoesNotExist' => ['pattern' => '{key}']]);

        // test
        $result = $formatter->formatPath([], $this->createSource(), self::TARGET, []);

        // verify
        $this->assertSame(['/dataObjects/sneakers'], $result);
    }

    #[Test]
    public function formatPath_fallsBackToTheElementPathWhenThePatternResolvesToNothing(): void
    {
        // prepare
        $formatter = $this->createFormatter(['Pimcore\Model\DataObject\Product' => ['pattern' => '{emptyValue}']]);

        // test
        $result = $formatter->formatPath([], $this->createSource(), self::TARGET, []);

        // verify
        $this->assertSame(['/dataObjects/sneakers'], $result);
    }

    #[Test]
    public function formatPath_prefersTheMostSpecificClass(): void
    {
        // prepare: the less specific class is configured last on purpose
        $formatter = $this->createFormatter([
            'Pimcore\Model\DataObject\Product'  => ['pattern' => 'specific {key}'],
            'Pimcore\Model\DataObject\Concrete' => ['pattern' => 'generic {key}'],
        ]);

        // test
        $result = $formatter->formatPath([], $this->createSource(), self::TARGET, []);

        // verify
        $this->assertSame(['specific sneakers'], $result);
    }

    #[Test]
    public function formatPath_prefersTheFieldContextOverTheGlobalPattern(): void
    {
        // prepare: the global pattern is configured last on purpose
        $formatter = $this->createFormatter([
            'Pimcore\Model\DataObject\ProductList::countryRelations' => [
                ConfigDefinition::PATTERN_OVERWRITES => ['Pimcore\Model\DataObject\Product' => '[{countryIso}] {key}'],
            ],
            'Pimcore\Model\DataObject\Product' => [ConfigDefinition::PATTERN => 'global {key}'],
        ]);

        // test
        $result = $formatter->formatPath(
            [],
            $this->createSource(),
            self::TARGET,
            ['context' => ['containerType' => 'object', 'fieldname' => 'countryRelations']]
        );

        // verify
        $this->assertSame(['[de] sneakers'], $result);
    }

    #[Test]
    public function formatPath_ignoresTheFieldContextOfAnotherField(): void
    {
        // prepare
        $formatter = $this->createFormatter([
            'Pimcore\Model\DataObject\Product' => [ConfigDefinition::PATTERN => 'global {key}'],
            'Pimcore\Model\DataObject\ProductList::countryRelations' => [
                ConfigDefinition::PATTERN_OVERWRITES => ['Pimcore\Model\DataObject\Product' => '[{countryIso}] {key}'],
            ],
        ]);

        // test
        $result = $formatter->formatPath(
            [],
            $this->createSource(),
            self::TARGET,
            ['context' => ['containerType' => 'object', 'fieldname' => 'otherField']]
        );

        // verify
        $this->assertSame(['global sneakers'], $result);
    }

    #[Test]
    public function formatPath_survivesParamsWithoutAContext(): void
    {
        // prepare: object grids call the formatter without a context
        $formatter = $this->createFormatter([
            'Pimcore\Model\DataObject\ProductList::countryRelations' => [
                ConfigDefinition::PATTERN_OVERWRITES => ['Pimcore\Model\DataObject\Product' => '{key}'],
            ],
        ]);

        // test
        $result = $formatter->formatPath([], $this->createSource(), self::TARGET, []);

        // verify
        $this->assertSame(['/dataObjects/sneakers'], $result);
    }

    #[Test]
    public function formatPath_usesTheDefaultPatternAsLastResort(): void
    {
        // prepare
        $formatter = $this->createFormatter([], defaultPattern: 'fallback {key}');

        // test
        $result = $formatter->formatPath([], $this->createSource(), self::TARGET, []);

        // verify
        $this->assertSame(['fallback sneakers'], $result);
    }

    #[Test]
    public function formatPath_skipsExcludedContainerTypes(): void
    {
        // prepare
        $formatter = $this->createFormatter(
            ['Pimcore\Model\DataObject\Product' => ['pattern' => '{key}']],
            excludeContainerTypes: ['fieldcollection']
        );

        // test
        $result = $formatter->formatPath(
            [],
            $this->createSource(),
            [['id' => 1, 'type' => 'object', 'fullPath' => '/raw/path']],
            ['context' => ['containerType' => 'fieldcollection', 'fieldname' => 'relation']]
        );

        // verify: the payload path is returned without loading or formatting the element
        $this->assertSame(['/raw/path'], $result);
    }

    #[Test]
    public function formatPath_keepsTheOwnPathOfAnUnresolvableTarget(): void
    {
        // prepare: Studio's FormatedPath DTO would reject a null here
        $formatter = $this->createFormatter(
            ['Pimcore\Model\DataObject\Product' => ['pattern' => '{key}']],
            targetExists: false
        );

        // test
        $result = $formatter->formatPath(
            [],
            $this->createSource(),
            [['id' => 999, 'type' => 'object', 'fullPath' => '/deleted/object']],
            []
        );

        // verify
        $this->assertSame(['/deleted/object'], $result);
    }

    #[Test]
    public function formatPath_returnsNoEntryForEmptyTargets(): void
    {
        // test
        $result = $this->createFormatter([])->formatPath([], $this->createSource(), [], []);

        // verify
        $this->assertSame([], $result);
    }

    #[Test]
    public function formatPath_keepsTheTargetKeysOfTheRequest(): void
    {
        // prepare: Studio keys its targets by "object_<id>"
        $formatter = $this->createFormatter(['Pimcore\Model\DataObject\Product' => ['pattern' => '{key}']]);

        // test
        $result = $formatter->formatPath([], $this->createSource(), ['object_1' => self::TARGET[0]], []);

        // verify
        $this->assertSame(['object_1' => 'sneakers'], $result);
    }

    #[Test]
    public function formatPath_restoresTheGlobalInheritanceState(): void
    {
        // prepare
        Concrete::setGetInheritedValues(true);
        $formatter = $this->createFormatter(
            ['Pimcore\Model\DataObject\Product' => ['pattern' => '{key}']],
            enableInheritance: false
        );

        // test
        $formatter->formatPath([], $this->createSource(), self::TARGET, []);

        // verify
        $this->assertTrue(Concrete::getGetInheritedValues());

        Concrete::setGetInheritedValues(false);
    }

    #[Test]
    public function explain_reportsThePatternAndItsPlaceholders(): void
    {
        // prepare
        $formatter = $this->createFormatter(['Pimcore\Model\DataObject\Product' => ['pattern' => '{key} {emptyValue}']]);

        // test
        $explanation = $formatter->explain($this->createProduct(), $this->createSource(), []);

        // verify
        $this->assertSame('{key} {emptyValue}', $explanation['pattern']);
        $this->assertSame('sneakers ', $explanation['value']);
        $this->assertSame(['{key}' => 'sneakers', '{emptyValue}' => ''], $explanation['trace']);
    }

    #[Test]
    public function explain_reportsNoPatternWhenNothingMatches(): void
    {
        // test
        $explanation = $this->createFormatter([])->explain($this->createProduct(), $this->createSource(), []);

        // verify
        $this->assertNull($explanation['pattern']);
        $this->assertNull($explanation['value']);
    }

    private function createFormatter(
        array $patternConfiguration,
        bool $targetExists = true,
        bool $enableInheritance = true,
        ?string $defaultPattern = null,
        array $excludeContainerTypes = [],
    ): BasilicomPathFormatter {
        $localeService = $this->createMock(LocaleServiceInterface::class);
        $localeService->method('getLocale')->willReturn('de');

        $translator = $this->createMock(TranslatorInterface::class);

        $pimcoreAdapter = $this->createMock(PimcoreAdapter::class);
        $pimcoreAdapter->method('getConcreteById')->willReturn($targetExists ? $this->createProduct() : null);

        return new BasilicomPathFormatter(
            $pimcoreAdapter,
            new PatternRenderer(
                new ExpressionFunctions($localeService, $translator),
                $localeService,
                $translator,
                new NullLogger(),
                false
            ),
            $localeService,
            $enableInheritance,
            false,
            $defaultPattern,
            $excludeContainerTypes,
            $patternConfiguration
        );
    }

    private function createProduct(): Product
    {
        $product = new Product();
        $product->setId(1);
        $product->setKey('sneakers');
        $product->setPath('/dataObjects/');

        return $product;
    }

    private function createSource(): ElementInterface
    {
        return $this->createMock(ProductList::class);
    }
}
