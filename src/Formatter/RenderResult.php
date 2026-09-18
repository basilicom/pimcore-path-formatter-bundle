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

final readonly class RenderResult
{
    /**
     * @param array<string, string> $tags  cache tags of every touched element
     * @param array<string, string> $trace placeholder => rendered value
     */
    public function __construct(
        public string $value,
        public array $tags = [],
        public array $trace = [],
    ) {
    }
}
