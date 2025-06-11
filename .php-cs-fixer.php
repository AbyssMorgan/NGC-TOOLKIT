<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()->in(__DIR__)->name('*.php')->name('*.ngcs')->exclude('vendor');

$config = [
	'blank_line_after_opening_tag' => true,
	'compact_nullable_type_declaration' => true,
	'no_singleline_whitespace_before_semicolons' => true,
	'no_space_around_double_colon' => true,
	'no_spaces_after_function_name' => true,
	'no_spaces_around_offset' => true,
	'spaces_inside_parentheses' => false,
	'object_operator_without_whitespace' => true,
	'ternary_operator_spaces' => true,
	'elseif' => true,
	'new_with_parentheses' => true,
	'array_indentation' => true,
	'assign_null_coalescing_to_coalesce_equal' => true,
	'binary_operator_spaces' => ['default' => 'single_space'],
	'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
	'no_trailing_whitespace' => true,
	'whitespace_after_comma_in_array' => true,
];

return (new Config())->setRules($config)->setIndent("\t")->setLineEnding("\n")->setFinder($finder);
?>