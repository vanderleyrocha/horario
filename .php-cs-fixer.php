<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor');

return (new \PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
        'line_ending' => true,
        'function_declaration' => true,
        'single_blank_line_at_eof' => true,
        'single_line_throw' => false,
        'binary_operator_spaces' => [
            'default' => 'single_space', // Força apenas um espaço, sem quebras
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
    ])
    ->setFinder($finder);
