<?php

/**
 * This source file is available under the terms of the MIT License.
 * Full copyright and license information is available in
 * LICENSE which is distributed with this source code.
 *
 * @copyright Copyright (c) Basilicom GmbH (https://basilicom.de)
 * @license   MIT
 */

$header = <<<'HEADER'
This source file is available under the terms of the MIT License.
Full copyright and license information is available in
LICENSE which is distributed with this source code.

@copyright Copyright (c) Basilicom GmbH (https://basilicom.de)
@license   MIT
HEADER;

return (new PhpCsFixer\Config())
    ->setFinder(
        PhpCsFixer\Finder::create()
        ->in(['src'])
        ->append([
            '.php-cs-fixer.dist.php',
            'phpstan-bootstrap.php'
        ])
    )
    ->setRules(
        [
            '@PSR12'         => true,
            'header_comment' => [
                'header'       => $header,
                'comment_type' => 'PHPDoc',
                'location'     => 'after_declare_strict',
                'separate'     => 'both',
            ],
            'array_indentation'      => true,
            'binary_operator_spaces' => ['operators' => [
                '=>' => 'align_single_space_minimal',
                '='  => 'align_single_space_minimal'
            ]],
            'single_quote'               => true,
            'ordered_imports'            => true,
            'no_superfluous_phpdoc_tags' => true,
            'phpdoc_line_span'           => ['const' => 'single','method' => 'single','property' => 'single'],
            'no_unused_imports'          => true,
        ]
    );
