<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/Classes')
    ->in(__DIR__ . '/Tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'yoda_style' => false,
        'concat_space' => ['spacing' => 'one'],
        'cast_spaces' => ['space' => 'none'],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_functions' => false,
            'import_constants' => false,
        ],
        'phpdoc_line_span' => [
            'const' => 'multi',
            'method' => 'multi',
            'property' => 'multi',
        ],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true);
