<?php

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
            '@PSR12'                 => true,
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
