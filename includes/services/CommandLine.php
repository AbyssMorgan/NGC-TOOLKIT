<?php

declare(strict_types=1);

namespace App\Services;

class CommandLine {

	public function __construct(){

	}

	public function cmd_escape(string $text) : string {
		return str_replace([">","<"],["^>","^<"], $text);
	}

	public function title(string $title) : void {
		system("TITLE ".$this->cmd_escape($title));
	}

	public function cls() : void {
		echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
	}

	public function get_input() : string {
		return trim(readline());
	}

	public function get_folders(string $string) : array {
		$string = trim($string);
		$folders = [];

		$length = strlen($string);
		$offset = 0;

		while($offset < $length){
			if(substr($string,$offset,1) == '"'){
				$end = strpos($string,'"',$offset+1);
				array_push($folders,substr($string,$offset+1,$end - $offset-1));
				$offset = $end + 1;
			} else if(substr($string,$offset,1) == ' '){
				$offset++;
			} else {
				$end = strpos($string,' ',$offset);
				if($end !== false){
					array_push($folders,substr($string,$offset,$end - $offset));
					$offset = $end + 1;
				} else {
					array_push($folders,substr($string,$offset));
					$offset = $length;
				}
			}
		}

		return array_unique($folders);
	}

	public function get_variable(string $string) : string {
		exec("echo $string", $var);
		return $var[0] ?? '';
	}

	public function open_file(string $path) : void {
		exec("START \"\" \"$path\"");
	}

}

?>
