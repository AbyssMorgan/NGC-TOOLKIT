<?php

/**
 * NGC-TOOLKIT v2.9.0 – Component
 *
 * © 2026 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Services;

use NGC\Core\IniFile;

/**
 * A utility class for converting and cleaning strings, including Pinyin conversion.
 */
class StringConverter {

	/**
	 * An associative array of characters to be replaced, where keys are characters to find and values are their replacements.
	 * @var array
	 */
	public array $replace = [];

	/**
	 * An associative array of characters to be removed, where keys are characters to find and values are empty strings.
	 * @var array
	 */
	public array $removal = [];

	/**
	 * An associative array for Pinyin conversion, typically loaded from an INI file.
	 * @var array
	 */
	public array $pinyin = [];

	/**
	 * Constructor that initializes the default replacement and removal character sets.
	 */
	public function __construct(){
		$this->replace = [
			"\u{3002}" => '.', // '。'
			"\u{3001}" => ',', // '、'
			"\u{FF01}" => '!', // '！'
			"\u{30FC}" => '-', // 'ー'
			"\u{FF08}" => '(', // '（'
			"\u{FF09}" => ')', // '）'
			"\u{300A}" => '(', // '《'
			"\u{300B}" => ')', // '》'
			"\u{3010}" => '[', // '【'
			"\u{3011}" => ']', // '】'
			"\u{30FB}" => ' - ', // '・'
			"\u{FF0C}" => ',', // '，'
			"\u{00A0}" => "\x20",
			"_" => "\x20",
			"." => "\x20",
		];
		$this->removal = [
			';' => '',
			'@' => '',
			'#' => '',
			'~' => '',
			'!' => '',
			'$' => '',
			'%' => '',
			'^' => '',
			'&' => '',
			"\u{FF1F}" => '', // '？'
			"\u{300C}" => '', // '「'
			"\u{300D}" => '', // '」'
			"\u{300E}" => '', // '『'
			"\u{300F}" => '', // '』'
			"\u{FF1A}" => '', // '：'
		];
	}

	/**
	 * Imports Pinyin mapping from a specified INI file.
	 * The Pinyin data is loaded only if the $pinyin array is empty.
	 *
	 * @param string $path The path to the INI file containing Pinyin mappings.
	 */
	public function import_pin_yin(string $path) : void {
		if(empty($this->pinyin)){
			$ini = new IniFile($path);
			$this->pinyin = $ini->get_all();
		}
	}

	/**
	 * Imports additional replacement rules from a specified INI file.
	 * These rules are merged with the existing replacement rules.
	 *
	 * @param string $path The path to the INI file containing replacement rules.
	 */
	public function import_replacement(string $path) : void {
		$ini = new IniFile($path);
		foreach($ini->get_all() as $key => $value){
			$this->replace[$key] = $value;
		}
	}

	/**
	 * Converts a string by replacing characters based on the defined `$replace` array.
	 *
	 * @param string $string The input string to convert.
	 * @return string The converted string.
	 */
	public function convert(string $string) : string {
		return \str_replace(\array_keys($this->replace), \array_values($this->replace), $string);
	}

	/**
	 * Cleans a string by removing characters based on the defined `$removal` array.
	 *
	 * @param string $string The input string to clean.
	 * @return string The cleaned string.
	 */
	public function clean(string $string) : string {
		return \str_replace(\array_keys($this->removal), \array_values($this->removal), $string);
	}

	/**
	 * Removes all double spaces from a string and trims leading/trailing spaces.
	 *
	 * @param string $string The input string.
	 * @return string The string with double spaces removed and trimmed.
	 */
	public function remove_double_spaces(string $string) : string {
		while(\str_contains($string, "\x20\x20")){
			$string = \str_replace("\x20\x20", "\x20", $string);
		}
		return \trim($string, "\x20");
	}

	/**
	 * Converts a string containing Chinese characters to Pinyin.
	 * This method uses `iconv` for character encoding conversion and a custom mapping for Pinyin.
	 *
	 * @param string $string The input string containing characters to convert to Pinyin.
	 * @return string The string converted to Pinyin.
	 */
	public function string_to_pin_yin(string $string) : string {
		$string = \preg_replace("/\s/is", "_", $string);
		$pinyin = "";
		$string = \iconv('UTF-8', 'GBK//TRANSLIT', $string);
		for($i = 0; $i < \strlen($string); $i++){
			if(\ord($string[$i]) > 128){
				$char = $this->asc2_to_pin_yin(\ord($string[$i]) + \ord($string[$i + 1]) * 256);
				if(!\is_null($char)){
					$pinyin .= $char;
				} else {
					$pinyin .= $string[$i];
				}
				$i++;
			} else {
				$pinyin .= $string[$i];
			}
		}
		return \str_replace("_", "\x20", $pinyin);
	}

	/**
	 * Converts a 2-byte ASCII code (representing a Chinese character) to its Pinyin equivalent.
	 * This is a private helper method used by `string_to_pin_yin`.
	 *
	 * @param int $asc2 The 2-byte ASCII code of the character.
	 * @return string|null The Pinyin string if found, otherwise null.
	 */
	private function asc2_to_pin_yin(int $asc2) : ?string {
		foreach($this->pinyin as $key => $value){
			if(\array_search($asc2, $value) !== false){
				return $key;
			}
		}
		return null;
	}

}

?>