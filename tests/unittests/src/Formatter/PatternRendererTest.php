<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle\Formatter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Model\Asset\Image;
use Pimcore\Model\Asset\Image\Thumbnail;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\Element\ElementInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;

class PatternRendererTest extends TestCase
{
    private static ?Image $imageMock = null;

    public static function renderDataProvider(): array
    {
        return [
            'plain property'                  => ['{key} #{id}', 'sneakers #42'],
            'nested property'                 => ['{key} of {parent.key}', 'sneakers of root'],
            'unknown property renders empty'  => ['a{doesNotExist}b', 'ab'],
            'values are html escaped'         => ['{unsafeName}', '&lt;script&gt;alert(1)&lt;/script&gt;'],
            'values are never re-interpreted' => ['{bracedName}', '{key} wins'],
            'unstringifiable value is empty'  => ['list:{unstringifiable}', 'list:'],
            'repeated placeholder'            => ['{key}-{key}', 'sneakers-sneakers'],

            'group is dropped when empty'     => ['Name[[ ({emptyValue})]]', 'Name'],
            'group is kept when filled'       => ['Name[[ ({price}{unit})]]', 'Name (10€)'],
            'group is kept when partly filled' => ['{key}[[ - {emptyValue}{price}]]', 'sneakers - 10'],
            'group without placeholders stays' => ['{key}[[ - fixed]]', 'sneakers - fixed'],
            'single brackets stay literal'    => ['[{countryIso}] {key}', '[de] sneakers'],

            'expression formats a number'     => ['{{ number(element.getPrice(), 2) }}', '10,00'],
            'expression formats a date'       => ['{{ date(1700000000, "Y-m-d") }}', '2023-11-14'],
            'expression uppercases'           => ['{{ upper(element.getKey()) }}', 'SNEAKERS'],
            'expression truncates'            => ['{{ truncate(element.getName(), 4, "...") }}', 'Snea...'],
            'expression translates'           => ['{{ trans("some.key") }}', '[some.key|admin|de]'],
            'failing expression renders empty' => ['a{{ element.getNope().deeper }}b', 'ab'],
            'expression counts for groups'    => ['X[[ ({{ element.getEmptyValue() }})]]', 'X'],
            'filled expression keeps group'   => ['X[[ ({{ element.getPrice() }})]]', 'X (10)'],

            'translation key'                 => ['{@my.key}: {key}', '[my.key|admin|de]: sneakers'],
            'expression knows the locale'     => ['{{ upper(locale) }}', 'DE'],
        ];
    }

    #[Test]
    #[DataProvider('renderDataProvider')]
    public function render(string $pattern, string $expected): void
    {
        // prepare
        $renderer = $this->createRenderer();

        // test
        $result = $renderer->render($pattern, $this->createProduct());

        // verify
        $this->assertSame($expected, $result->value);
    }

    #[Test]
    public function render_collectsCacheTagsOfEveryTouchedElement(): void
    {
        // prepare
        $product = $this->createProduct();

        // test
        $result = $this->createRenderer()->render('{key} of {parent.key}', $product);

        // verify
        $this->assertSame(['object_7'], array_values($result->tags));
    }

    #[Test]
    public function render_tagsTranslationsSoThatEditedLabelsInvalidate(): void
    {
        // test
        $result = $this->createRenderer()->render('{@some.key}', $this->createProduct());

        // verify
        $this->assertSame(['translate'], array_values($result->tags));
    }

    #[Test]
    public function render_tracesEveryPlaceholder(): void
    {
        // test
        $result = $this->createRenderer()->render('{key} {emptyValue}', $this->createProduct());

        // verify
        $this->assertSame(['{key}' => 'sneakers', '{emptyValue}' => ''], $result->trace);
    }

    #[Test]
    public function render_prependsAPreviewWhenTheElementItselfIsAnImage(): void
    {
        // prepare
        $image = $this->createImageMock();

        // test
        $result = $this->createRenderer(enableAssetPreview: true)->render('Logo', $image);

        // verify
        $this->assertSame(
            '<img src="/thumbnail/some-file.png" style="height: 18px; margin-right: 5px;" /> Logo',
            $result->value
        );
    }

    #[Test]
    public function render_rendersAnImageProperyAsPreview(): void
    {
        // prepare
        $product = $this->createProduct();
        $product->setImage($this->createImageMock());

        // test
        $result = $this->createRenderer(enableAssetPreview: true)->render('{image} {key}', $product);

        // verify
        $this->assertSame(
            '<img src="/thumbnail/some-file.png" style="height: 18px; margin-right: 5px;" /> sneakers',
            $result->value
        );
    }

    #[Test]
    public function render_rendersAnImagePropertyAsPathWhenPreviewsAreDisabled(): void
    {
        // prepare
        $product = $this->createProduct();
        $product->setImage($this->createImageMock());

        // test
        $result = $this->createRenderer(enableAssetPreview: false)->render('{image} {key}', $product);

        // verify
        $this->assertSame('/images/some-file.png sneakers', $result->value);
    }

    #[Test]
    public function lint_acceptsAValidExpression(): void
    {
        $this->assertSame([], PatternRenderer::lint('{key} {{ upper(element.getKey()) }}'));
    }

    #[Test]
    public function lint_reportsBrokenExpressions(): void
    {
        // test
        $errors = PatternRenderer::lint('{{ upper( }}');

        // verify
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Unclosed "("', $errors[0]);
    }

    #[Test]
    public function lint_reportsUnknownFunctions(): void
    {
        // test
        $errors = PatternRenderer::lint('{{ shout(element) }}');

        // verify
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('shout', $errors[0]);
    }

    private function createRenderer(bool $enableAssetPreview = false): PatternRenderer
    {
        $localeService = $this->createMock(LocaleServiceInterface::class);
        $localeService->method('getLocale')->willReturn('de');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters, ?string $domain, ?string $locale): string
                => sprintf('[%s|%s|%s]', $id, $domain, $locale)
        );

        return new PatternRenderer(
            new ExpressionFunctions($localeService, $translator),
            $localeService,
            $translator,
            new NullLogger(),
            $enableAssetPreview
        );
    }

    private function createProduct(): Product
    {
        $parent = new DataObject\Folder();
        $parent->setId(7);
        $parent->setKey('root');

        $product = new Product();
        $product->setId(42);
        $product->setKey('sneakers');
        $product->setPath('/dataObjects/');
        $product->setParent($parent);

        return $product;
    }

    /** Overload mocks are process global, so the alias may only be defined once. */
    private function createImageMock(): Image
    {
        if (self::$imageMock !== null) {
            return self::$imageMock;
        }

        $thumbnail = \Mockery::mock('overload:' . Thumbnail::class);
        $thumbnail->shouldReceive('getPath')->andReturn('/thumbnail/some-file.png');

        $image = \Mockery::mock('overload:' . Image::class, ElementInterface::class);
        $image->shouldReceive('getFullPath')->andReturn('/images/some-file.png');
        $image->shouldReceive('getThumbnail')->andReturn($thumbnail);
        $image->shouldReceive('getCacheTag')->andReturn('asset_3');

        return self::$imageMock = $image;
    }
}
