<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor');

return (new \PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_single_line',
            'keep_multiple_spaces_after_comma' => false,
        ],
        'line_ending' => true,
        'single_blank_line_at_eof' => true,
        'binary_operator_spaces' => [
            'default' => 'single_space', // Força apenas um espaço, sem quebras
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
    ])
    ->setFinder($finder);
