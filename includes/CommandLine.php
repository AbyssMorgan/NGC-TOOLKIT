<?php

class CommandLine {

	public function __construct(){

	}

	public function cmd_escape(string $text){
		return str_replace([">","<"],["^>","^<"],$text);
	}

	public function title(string $title){
		system("TITLE ".$this->cmd_escape($title));
	}

	public function cls(){
		echo chr(27).chr(91).'H'.chr(27).chr(91).'J';
	}

	public function get_input(){
		return trim(fgets(STDIN));
	}

	public function get_folders(string $string){
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

}

?>
