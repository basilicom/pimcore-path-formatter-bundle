<?php

declare(strict_types=1);

/**
 * This source file is available under the terms of the MIT License.
 * Full copyright and license information is available in
 * LICENSE which is distributed with this source code.
 *
 * @copyright Copyright (c) Basilicom GmbH (https://basilicom.de)
 * @license   MIT
 */

namespace Basilicom\PathFormatterBundle\Formatter;

use DateTimeImmutable;
use DateTimeInterface;
use NumberFormatter;
use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Model\Translation;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The formatting helpers available inside `{{ … }}` expressions.
 */
final class ExpressionFunctions implements ExpressionFunctionProviderInterface
{
    public const NAMES = ['number', 'date', 'upper', 'lower', 'trim', 'truncate', 'trans'];

    public function __construct(
        private readonly LocaleServiceInterface $localeService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /** @return ExpressionFunction[] */
    public function getFunctions(): array
    {
        return [
            $this->function('number', fn (mixed $value, int $decimals = 2): string => $this->number($value, $decimals)),
            $this->function('date', fn (mixed $value, string $format = 'd.m.Y'): string => $this->date($value, $format)),
            $this->function('upper', static fn (mixed $value): string => mb_strtoupper((string)$value)),
            $this->function('lower', static fn (mixed $value): string => mb_strtolower((string)$value)),
            $this->function('trim', static fn (mixed $value): string => trim((string)$value)),
            $this->function('truncate', fn (mixed $value, int $length, string $suffix = '…'): string => $this->truncate((string)$value, $length, $suffix)),
            $this->function('trans', fn (string $key, string $domain = Translation::DOMAIN_ADMIN): string => $this->translator->trans($key, [], $domain, $this->localeService->getLocale())),
        ];
    }

    /** Knows the function names but evaluates nothing, for linting patterns at build time. */
    public static function createLinter(): ExpressionLanguage
    {
        $language = new ExpressionLanguage();
        foreach (self::NAMES as $name) {
            $language->register($name, static fn (): string => "''", static fn (): string => '');
        }

        return $language;
    }

    private function function(string $name, callable $evaluator): ExpressionFunction
    {
        return new ExpressionFunction(
            $name,
            static fn (...$arguments): string => sprintf('%s(%s)', $name, implode(', ', $arguments)),
            static fn (array $variables, ...$arguments): mixed => $evaluator(...$arguments),
        );
    }

    private function number(mixed $value, int $decimals): string
    {
        if (!is_numeric($value)) {
            return '';
        }

        $formatter = new NumberFormatter($this->localeService->getLocale() ?? 'en', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);

        return (string)$formatter->format((float)$value);
    }

    private function date(mixed $value, string $format): string
    {
        $date = match (true) {
            $value instanceof DateTimeInterface      => $value,
            is_int($value)                           => (new DateTimeImmutable())->setTimestamp($value),
            is_string($value) && ctype_digit($value) => (new DateTimeImmutable())->setTimestamp((int)$value),
            default                                  => null,
        };

        return $date?->format($format) ?? '';
    }

    private function truncate(string $value, int $length, string $suffix): string
    {
        if ($length <= 0 || mb_strlen($value) <= $length) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $length)) . $suffix;
    }
}
