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

/**
 * Rendered values are parked in slots so a later pass cannot read user content as pattern
 * syntax — a product named "{name}" must stay text.
 *
 * @internal
 */
final class RenderState
{
    /** @var array<string, string> */
    public array $tags = [];

    /** @var array<string, string> */
    public array $trace = [];

    /** @var array<string, string> */
    public array $memoized = [];

    /** @var list<array{value: string, empty: bool}> */
    private array $slots = [];

    public function slot(string $value): string
    {
        $this->slots[] = ['value' => $value, 'empty' => $value === ''];

        return "\x00" . (count($this->slots) - 1) . "\x00";
    }

    /** @return array{used: int, filled: int} */
    public function inspect(string $text): array
    {
        $matches = [];
        preg_match_all("~\x00(\d+)\x00~", $text, $matches);

        $used   = 0;
        $filled = 0;
        foreach ($matches[1] as $index) {
            $slot = $this->slots[(int)$index] ?? null;
            if ($slot === null) {
                continue;
            }

            $used++;
            $filled += $slot['empty'] ? 0 : 1;
        }

        return ['used' => $used, 'filled' => $filled];
    }

    /** @return array<string, string> */
    public function slotValues(): array
    {
        $values = [];
        foreach ($this->slots as $index => $slot) {
            $values["\x00" . $index . "\x00"] = $slot['value'];
        }

        return $values;
    }
}
