<?php

declare(strict_types=1);

namespace Basilicom\PathFormatterBundle;

use Basilicom\PathFormatterBundle\Command\DebugPathFormatterCommand;
use Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatter;
use Basilicom\PathFormatterBundle\DependencyInjection\BasilicomPathFormatterExtension;
use Basilicom\PathFormatterBundle\Formatter\PatternRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pimcore\Localization\LocaleService;
use Pimcore\Localization\LocaleServiceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Guards the autowiring: every constructor argument is either type hinted or set by the
 * extension. A missing scalar only shows up when a real project builds its container.
 */
class ServiceWiringTest extends TestCase
{
    #[Test]
    public function theBundleServicesCompileAndCanBeInstantiated(): void
    {
        // prepare
        $container = new ContainerBuilder();
        $container->setDefinition(LocaleServiceInterface::class, new Definition(LocaleService::class));
        $container->setDefinition(TranslatorInterface::class, new Definition(Translator::class, ['de']));
        $container->setDefinition(LoggerInterface::class, new Definition(NullLogger::class));

        (new BasilicomPathFormatterExtension())->load([[]], $container);

        foreach ([BasilicomPathFormatter::class, PatternRenderer::class, DebugPathFormatterCommand::class] as $id) {
            $container->getDefinition($id)->setPublic(true);
        }

        // test
        $container->compile();

        // verify
        $this->assertInstanceOf(BasilicomPathFormatter::class, $container->get(BasilicomPathFormatter::class));
        $this->assertInstanceOf(PatternRenderer::class, $container->get(PatternRenderer::class));
        $this->assertInstanceOf(DebugPathFormatterCommand::class, $container->get(DebugPathFormatterCommand::class));
    }
}
