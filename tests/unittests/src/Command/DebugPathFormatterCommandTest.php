<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle\Command;

use Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter;
use Basilicom\PathFormatterBundle\DependencyInjection\PimcoreAdapter;
use Basilicom\PathFormatterBundle\Formatter\ExpressionFunctions;
use Basilicom\PathFormatterBundle\Formatter\PatternRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Model\DataObject\Product;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;

class DebugPathFormatterCommandTest extends TestCase
{
    #[Test]
    public function execute_showsThePatternAndEveryPlaceholder(): void
    {
        // prepare
        $tester = $this->createTester(['Pimcore\Model\DataObject\Product' => ['pattern' => '{key} {emptyValue}']]);

        // test
        $exitCode = $tester->execute(['target' => '1']);

        // verify
        $output = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('{key} {emptyValue}', $output);
        $this->assertStringContainsString('sneakers', $output);
        $this->assertStringContainsString('{emptyValue}', $output);
        $this->assertStringContainsString('(empty)', $output);
        $this->assertStringContainsString('object_1', $output);
    }

    #[Test]
    public function execute_reportsWhenNoPatternMatches(): void
    {
        // prepare
        $tester = $this->createTester([]);

        // test
        $exitCode = $tester->execute(['target' => '1']);

        // verify
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('No pattern matches', $tester->getDisplay());
    }

    #[Test]
    public function execute_failsForAnUnknownTarget(): void
    {
        // prepare
        $tester = $this->createTester([], targetExists: false);

        // test
        $exitCode = $tester->execute(['target' => '404']);

        // verify
        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('No object found with ID 404', $tester->getDisplay());
    }

    #[Test]
    public function execute_appliesTheFieldContext(): void
    {
        // prepare
        $tester = $this->createTester([
            'Pimcore\Model\DataObject\Product' => ['pattern' => 'global {key}'],
            'Pimcore\Model\DataObject\Product::relations' => [
                'patternOverwrites' => ['Pimcore\Model\DataObject\Product' => 'in context {key}'],
            ],
        ]);

        // test
        $tester->execute(['target' => '1', '--source' => '1', '--field' => 'relations']);

        // verify
        $this->assertStringContainsString('in context {key}', $tester->getDisplay());
    }

    private function createTester(array $patternConfiguration, bool $targetExists = true): CommandTester
    {
        $localeService = $this->createMock(LocaleServiceInterface::class);
        $localeService->method('getLocale')->willReturn('de');

        $translator = $this->createMock(TranslatorInterface::class);

        $product = new Product();
        $product->setId(1);
        $product->setKey('sneakers');
        $product->setPath('/dataObjects/');

        $adapter = $this->createMock(PimcoreAdapter::class);
        $adapter->method('getElementById')->willReturn($targetExists ? $product : null);

        $formatter = new BasilicomPathFormatter(
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
            false,
            null,
            [],
            $patternConfiguration
        );

        return new CommandTester(new DebugPathFormatterCommand($formatter, $adapter));
    }
}
