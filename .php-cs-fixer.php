<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude([
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
    ])
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setUsingCache(true)
    ->setRules([
        '@PSR12' => true,

        // Arrays, strings e operadores
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['default' => 'single_space'],
        'concat_space' => ['spacing' => 'one'],
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters', 'match'],
        ],

        // Espaçamento e estrutura
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
        'function_declaration' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try', 'if', 'for', 'foreach', 'while'],
        ],
        'no_extra_blank_lines' => [
            'tokens' => ['extra'],
        ],
        'no_trailing_whitespace' => true,
        'single_blank_line_at_eof' => true,
        'line_ending' => true,
        'single_line_throw' => false,

        // Imports
        'no_unused_imports' => true,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'single_import_per_statement' => true,

        // PHPDoc
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_trim' => true,
        'phpdoc_scalar' => true,

        // Estilo moderno e previsível
        'nullable_type_declaration_for_default_null_value' => true,
        'declare_strict_types' => false,
    ])
    ->setFinder($finder);
