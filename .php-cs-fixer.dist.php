<?php
$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->exclude('Lang')
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => [
                '|' => 'no_space',
            ],
        ],
        'array_syntax' => [
            'syntax' => 'short',
        ],
        'blank_line_before_statement' => [
            'statements' => [
                'return'
            ],
        ],
        'single_quote' => [
            'strings_containing_single_quote_chars' => false,
        ],
    ])
    ->setUsingCache(true)
    ->setFinder($finder);
