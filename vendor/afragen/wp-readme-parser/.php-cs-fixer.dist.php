<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php');

return (new Config())
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0'                         => true,
        'declare_strict_types'               => true,
        'strict_param'                       => true,
        'array_syntax'                       => ['syntax' => 'short'],
        'ordered_imports'                    => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'                  => true,
        'single_quote'                       => true,
        'trailing_comma_in_multiline'        => ['elements' => ['arrays', 'arguments', 'parameters']],
        'binary_operator_spaces'             => ['default' => 'align_single_space_minimal'],
        'blank_line_before_statement'        => ['statements' => ['return', 'throw', 'if', 'foreach', 'while']],
        'phpdoc_align'                       => ['align' => 'left'],
        'phpdoc_order'                       => true,
        'phpdoc_separation'                  => true,
        'phpdoc_trim'                        => true,
        'no_superfluous_phpdoc_tags'         => ['allow_unused_params' => false],
        'method_argument_space'              => ['on_multiline' => 'ensure_fully_multiline'],
        'class_attributes_separation'        => ['elements' => ['method' => 'one', 'property' => 'one']],
    ])
    ->setFinder($finder);
