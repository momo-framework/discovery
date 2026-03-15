<?php

declare(strict_types=1);

$remoteHeaderUrl = 'https://raw.githubusercontent.com/momo-framework/.github/refs/heads/main/COPYRIGHT_HEADER';
$context         = stream_context_create(['http' => ['timeout' => 5]]);
$header          = @file_get_contents($remoteHeaderUrl, false, $context);

$rules = [
    '@PER-CS2.0'          => true,
    '@PHP84Migration'     => true,
    '@PhpCsFixer'         => true,

    'declare_strict_types'                          => true,
    'strict_param'                                  => true,
    'strict_comparison'                             => true,

    'ordered_imports'                               => ['sort_algorithm' => 'alpha'],
    'no_unused_imports'                             => true,
    'global_namespace_import'                       => [
        'import_classes'   => true,
        'import_constants' => true,
        'import_functions' => true,
    ],
    'fully_qualified_strict_types'                  => true,
    'no_leading_namespace_whitespace'                => true,

    'array_syntax'                                  => ['syntax' => 'short'],
    'array_indentation'                             => true,
    'trim_array_spaces'                             => true,
    'no_whitespace_before_comma_in_array'           => true,
    'whitespace_after_comma_in_array'               => ['ensure_single_space' => true],
    'normalize_index_brace'                         => true,

    'single_quote'                                  => true,
    'explicit_string_variable'                      => true,
    'heredoc_to_nowdoc'                             => true,
    'no_binary_string'                              => true,
    'string_implicit_backslashes'                   => true,

    'void_return'                                   => true,
    'return_type_declaration'                       => ['space_before' => 'none'],
    'nullable_type_declaration_for_default_null_value' => true,
    'nullable_type_declaration'                     => ['syntax' => 'union'],
    'phpdoc_to_param_type'                          => true,
    'phpdoc_to_return_type'                         => true,
    'phpdoc_to_property_type'                       => true,
    'no_useless_return'                             => true,
    'no_useless_else'                               => true,
    'simplified_null_return'                        => true,
    'static_lambda'                                 => true,
    'use_arrow_functions'                           => true,

    'final_class'                                   => true,
    'self_accessor'                                 => true,
    'self_static_accessor'                          => true,
    'no_null_property_initialization'               => true,

    'php_unit_method_casing'                        => ['case' => 'snake_case'],
    'php_unit_test_annotation'                      => ['style' => 'prefix'],

    'ordered_class_elements'                        => [
        'order' => [
            'use_trait',
            'case',
            'constant_public',
            'constant_protected',
            'constant_private',
            'property_public_static',
            'property_protected_static',
            'property_private_static',
            'property_public',
            'property_protected',
            'property_private',
            'construct',
            'destruct',
            'magic',
            'phpunit',
            'method_public_static',
            'method_protected_static',
            'method_private_static',
            'method_public',
            'method_protected',
            'method_private',
        ],
    ],
    'ordered_interfaces'                            => true,
    'protected_to_private'                          => true,

    'phpdoc_order'                                  => ['order' => ['param', 'return', 'throws']],
    'phpdoc_separation'                             => true,
    'phpdoc_trim'                                   => true,
    'phpdoc_trim_consecutive_blank_line_separation' => true,
    'phpdoc_var_without_name'                       => true,
    'no_superfluous_phpdoc_tags'                    => ['remove_inheritdoc' => true],
    'phpdoc_align'                                  => ['align' => 'vertical'],
    'align_multiline_comment'                       => true,

    'yoda_style'                                    => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
    'no_superfluous_elseif'                         => true,
    'no_alternative_syntax'                         => true,
    'simplified_if_return'                          => true,
    'ternary_to_null_coalescing'                    => true,
    'modernize_strpos'                              => true,
    'modernize_types_casting'                       => true,

    'operator_linebreak'                            => ['position' => 'beginning'],
    'concat_space'                                  => ['spacing' => 'one'],
    'not_operator_with_successor_space'             => true,
    'object_operator_without_whitespace'            => true,
    'standardize_not_equals'                        => true,
    'increment_style'                               => ['style' => 'post'],

    'blank_line_before_statement'                   => [
        'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try', 'yield'],
    ],
    'method_chaining_indentation'                   => true,
    'multiline_whitespace_before_semicolons'        => ['strategy' => 'no_multi_line'],
    'no_extra_blank_lines'                          => [
        'tokens' => ['extra', 'curly_brace_block', 'parenthesis_brace_block', 'square_brace_block', 'throw', 'use'],
    ],
    'types_spaces'                                  => ['space' => 'none'],

    'get_class_to_class_keyword'                    => true,
    'mb_str_functions'                              => true,
    'no_alias_functions'                            => true,
    'no_homoglyph_names'                            => true,
    'comment_to_phpdoc'                             => true,
    'multiline_comment_opening_closing'             => true,
];

if ($header !== false && trim($header) !== '') {
    $rules['header_comment'] = [
        'header'       => trim($header),
        'comment_type' => 'PHPDoc',
        'location'     => 'after_open',
        'separate'     => 'both',
    ];
}

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->notPath('bootstrap/cache');

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules($rules)
    ->setFinder($finder);