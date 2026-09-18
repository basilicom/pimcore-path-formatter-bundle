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

use Pimcore\Localization\LocaleServiceInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Translation;
use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class PatternRenderer
{
    private const PREVIEW_THUMBNAIL = 'pimcore-system-treepreview';

    private const TRANSLATION_PREFIX = '@';

    private ?ExpressionLanguage $expressionLanguage = null;

    public function __construct(
        private readonly ExpressionFunctions $expressionFunctions,
        private readonly LocaleServiceInterface $localeService,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly bool $enableAssetPreview,
    ) {
    }

    public function render(string $pattern, ElementInterface $element): RenderResult
    {
        $state = new RenderState();

        if (str_contains($pattern, '{' . self::TRANSLATION_PREFIX) || str_contains($pattern, 'trans(')) {
            $state->tags['translate'] = 'translate';
        }

        $text = $this->renderExpressions($pattern, $element, $state);
        $text = $this->renderGroups($text, $element, $state);
        $text = $this->renderPlaceholders($text, $element, $state);
        $text = strtr($text, $state->slotValues());

        if ($element instanceof Asset\Image && $this->enableAssetPreview) {
            $text = $this->renderPreview($element) . ' ' . $text;
        }

        return new RenderResult($text, $state->tags, $state->trace);
    }

    /** Settings that change the rendered output and therefore have to be part of a cache key. */
    public function cacheDiscriminator(): string
    {
        return $this->enableAssetPreview ? 'preview' : 'no-preview';
    }

    /** @return string[] syntax errors, empty when the pattern is sound */
    public static function lint(string $pattern): array
    {
        $errors  = [];
        $matches = [];
        preg_match_all('~\{\{(.*?)\}\}~s', $pattern, $matches);

        $linter = ExpressionFunctions::createLinter();
        foreach ($matches[1] as $expression) {
            try {
                $linter->lint(trim($expression), ['element', 'locale']);
            } catch (SyntaxError $error) {
                $errors[] = sprintf('{{%s}}: %s', $expression, $error->getMessage());
            }
        }

        return $errors;
    }

    private function renderExpressions(string $text, ElementInterface $element, RenderState $state): string
    {
        return preg_replace_callback(
            '~\{\{(.*?)\}\}~s',
            fn (array $matches): string => $this->renderOnce(
                '{{' . trim($matches[1]) . '}}',
                $state,
                fn (): string => $this->escape($this->toText($this->evaluate(trim($matches[1]), $element)))
            ),
            $text
        ) ?? $text;
    }

    private function renderGroups(string $text, ElementInterface $element, RenderState $state): string
    {
        return preg_replace_callback(
            '~\[\[(.*?)\]\]~s',
            function (array $matches) use ($element, $state): string {
                $rendered = $this->renderPlaceholders($matches[1], $element, $state);
                $slots    = $state->inspect($rendered);

                return $slots['used'] > 0 && $slots['filled'] === 0 ? '' : $rendered;
            },
            $text
        ) ?? $text;
    }

    private function renderPlaceholders(string $text, ElementInterface $element, RenderState $state): string
    {
        return preg_replace_callback(
            '~\{([^{}]*)\}~',
            fn (array $matches): string => $this->renderOnce(
                '{' . trim($matches[1]) . '}',
                $state,
                fn (): string => $this->renderPlaceholder(trim($matches[1]), $element, $state)
            ),
            $text
        ) ?? $text;
    }

    private function renderOnce(string $key, RenderState $state, callable $render): string
    {
        if (isset($state->memoized[$key])) {
            return $state->memoized[$key];
        }

        $rendered           = $render();
        $state->trace[$key] = $rendered;

        return $state->memoized[$key] = $state->slot($rendered);
    }

    private function renderPlaceholder(string $path, ElementInterface $element, RenderState $state): string
    {
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, self::TRANSLATION_PREFIX)) {
            return $this->escape($this->translate(substr($path, 1)));
        }

        $value = $this->resolveProperty($element, $path, $state);

        if ($value instanceof Asset\Image) {
            return $this->enableAssetPreview
                ? $this->renderPreview($value)
                : $this->escape($value->getFullPath());
        }

        return $this->escape($this->toText($value));
    }

    private function evaluate(string $expression, ElementInterface $element): mixed
    {
        $this->expressionLanguage ??= $this->createExpressionLanguage();

        try {
            return $this->expressionLanguage->evaluate($expression, [
                'element' => $element,
                'locale'  => $this->localeService->getLocale(),
            ]);
        } catch (Throwable $error) {
            $this->logger->warning('Path formatter expression failed: {expression} ({message})', [
                'expression' => $expression,
                'message'    => $error->getMessage(),
                'element'    => $element->getId(),
            ]);

            return null;
        }
    }

    private function createExpressionLanguage(): ExpressionLanguage
    {
        $language = new ExpressionLanguage();
        $language->registerProvider($this->expressionFunctions);

        return $language;
    }

    private function translate(string $key): string
    {
        return $this->translator->trans($key, [], Translation::DOMAIN_ADMIN, $this->localeService->getLocale());
    }

    private function resolveProperty(ElementInterface $element, string $propertyPath, RenderState $state): mixed
    {
        $currentValue = $element;

        foreach (explode('.', $propertyPath) as $property) {
            if (!is_object($currentValue)) {
                return null;
            }

            $getter = 'get' . ucfirst(trim($property));
            if (!method_exists($currentValue, $getter)) {
                return null;
            }

            $currentValue = $currentValue->$getter();

            if ($currentValue instanceof ElementInterface) {
                $state->tags[$currentValue->getCacheTag()] = $currentValue->getCacheTag();
            }
        }

        return $currentValue;
    }

    private function renderPreview(Asset\Image $image): string
    {
        $source = $this->escape($image->getThumbnail(self::PREVIEW_THUMBNAIL)->getPath());

        return '<img src="' . $source . '" style="height: 18px; margin-right: 5px;" />';
    }

    private function toText(mixed $value): string
    {
        if ($value === null || is_array($value) || (is_object($value) && !method_exists($value, '__toString'))) {
            return '';
        }

        return (string)$value;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
