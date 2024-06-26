<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        'src',
        'config',
    ]);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'                     => true,
        '@PHP74Migration'            => true,
        'normalize_index_brace'      => true,
        'global_namespace_import'    => ['import_classes' => true, 'import_constants' => true, 'import_functions' => true],
        'operator_linebreak'         => ['only_booleans' => true, 'position' => 'beginning'],
        'standardize_not_equals'     => true,
        'unary_operator_spaces'      => true,
        // risky
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'all', 'strict' => true],
        'function_to_constant'       => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
