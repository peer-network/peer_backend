<?php

declare(strict_types=1);

use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('*.php')
    ->ignoreVCS(true)
    ->exclude(['vendor', 'var', 'cache']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setIndent("    ")
    ->setLineEnding("\n")
    ->setRules([
        '@PSR12' => true,

        // --- PHPStorm-like rules ---
        'array_syntax' => ['syntax' => 'short'],

        'no_unused_imports' => true,

        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
        ],

        'binary_operator_spaces' => [
            'default' => 'align_single_space_minimal',
        ],

        'blank_line_before_statement' => [
            'statements' => [
                'return',
            ],
        ],

        // Strict but safe:
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'single_quote' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => [
            'tokens' => ['curly_brace_block', 'extra'],
        ],
    ])
    ->setFinder($finder);
