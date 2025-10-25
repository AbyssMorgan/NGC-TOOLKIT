<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()->in(__DIR__)->name('*.php')->name('*.ngcs');

$config = [
	'array_indentation' => true,
	'array_syntax' => ['syntax' => 'short'],
	'assign_null_coalescing_to_coalesce_equal' => true,
	'binary_operator_spaces' => ['default' => 'single_space'],
	'blank_line_after_opening_tag' => false,
	'compact_nullable_type_declaration' => true,
	'elseif' => true,
	'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
	'new_with_parentheses' => true,
	'no_singleline_whitespace_before_semicolons' => true,
	'no_space_around_double_colon' => true,
	'no_spaces_after_function_name' => true,
	'no_spaces_around_offset' => true,
	'no_trailing_whitespace' => true,
	'object_operator_without_whitespace' => true,
	'spaces_inside_parentheses' => false,
	'ternary_operator_spaces' => true,
	'trailing_comma_in_multiline' => ['elements' => ['arrays']],
	'whitespace_after_comma_in_array' => true,
];

return (new Config())->setRules($config)->setIndent("\t")->setLineEnding("\n")->setFinder($finder);
?>