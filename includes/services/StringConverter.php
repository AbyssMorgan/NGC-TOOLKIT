<?php

declare(strict_types=1);

namespace App\Services;

use AveCore\IniFile;

class StringConverter {

	public array $replace = [];
	public array $removal = [];
	public array $pinyin = [];

	public function __construct(){
		$this->replace = [
			'。' => '.', '、' => ',', "\u{FF01}" => '!', 'ー' => '-', "\u{FF08}" => '(', "\u{FF09}" => ')', '《' => '(', '》' => ')',
			'【' => '[', '】' => ']', '・' => ' - ', "\u{FF0C}" => ',', '_' => ' ', '.' => ' ', "\u{00A0}" => ' '
		];
		$this->removal = [
			';' => '', '@' => '', '#' => '', '~' => '', '!' => '', '$' => '', '%' => '', '^' => '', '&' => '', "\u{FF1F}" => '',
			'「' => '', '」' => '', '『' => '', '』' => '', "\u{FF1A}" => '',
		];
	}

	public function import_pin_yin(string $path) : void {
		if(empty($this->pinyin)){
			$ini = new IniFile($path);
			$this->pinyin = $ini->get_all();
		}
	}

	public function import_replacement(string $path) : void {
		$ini = new IniFile($path);
		foreach($ini->get_all() as $key => $value){
			$this->replace[$key] = $value;
		}
	}

	public function convert(string $string) : string {
		return str_replace(array_keys($this->replace), $this->replace, $string);
	}

	public function clean(string $string) : string {
		return str_replace(array_keys($this->removal), $this->removal, $string);
	}

	public function remove_double_spaces(string $string) : string {
		while(strpos($string, '  ') !== false){
			$string = str_replace('  ', ' ', $string);
		}
		return trim($string, ' ');
	}

	public function string_to_pin_yin(string $string) : string {
		$string = preg_replace("/\s/is", "_", $string);
		$pinyin = "";
		$string = iconv('UTF-8', 'GBK//TRANSLIT', $string);
		for($i = 0; $i < strlen($string); $i++){
			if(ord($string[$i]) > 128){
				$char = $this->asc2_to_pin_yin(ord($string[$i]) + ord($string[$i+1]) * 256);
				if(!is_null($char)){
					$pinyin .= $char;
				} else {
					$pinyin .= $string[$i];
				}
				$i++;
			} else {
				$pinyin .= $string[$i];
			}
		}
		return str_replace('_', ' ', $pinyin);
	}

	private function asc2_to_pin_yin(int $asc2) : ?string {
		foreach($this->pinyin as $key => $value){
			if(array_search($asc2, $value) !== false){
				return $key;
			}
		}
		return null;
	}

}

?>
