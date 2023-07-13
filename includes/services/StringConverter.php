<?php

declare(strict_types=1);

namespace App\Services;

class StringConverter {

	public array $replace = [];
	public array $removal = [];
	public array $pinyin = [];

	public function __construct(){
		$this->replace = [
			'。' => '.', '、' => ',', '！' => '!', 'ー' => '-', '（' => '(', '）' => ')', '《' => '(', '》' => ')',
			'【' => '[', '】' => ']', '・' => ' - ', '，' => ',', '_' => ' ', '.' => ' ', "\u{00A0}" => ' '
		];
		$this->removal = [
			';' => '', '@' => '', '#' => '', '~' => '', '!' => '', '$' => '', '%' => '', '^' => '', '&' => '', '？' => '',
			'「' => '', '」' => '', '『' => '', '』' => '', '：' => '',
		];
	}

	public function importPinYin(string $path) : void {
		if(empty($this->pinyin)){
			$ini = new IniFile($path);
			$this->pinyin = $ini->getAll();
		}
	}

	public function importReplacement(string $path) : void {
		$ini = new IniFile($path);
		foreach($ini->getAll() as $key => $value){
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

	public function stringToPinYin(string $string) : string {
		$string = preg_replace("/\s/is", "_", $string);
		$pinyin = "";
		$string = iconv('UTF-8', 'GBK//TRANSLIT', $string);
		for($i = 0; $i < strlen($string); $i++){
			if(ord($string[$i]) > 128){
				$char = $this->asc2ToPinYin(ord($string[$i]) + ord($string[$i+1]) * 256);
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

	private function asc2ToPinYin(int $asc2) : ?string {
		foreach($this->pinyin as $key => $value){
			if(array_search($asc2, $value) !== false){
				return $key;
			}
		}
		return null;
	}

}

?>