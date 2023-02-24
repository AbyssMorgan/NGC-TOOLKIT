<?php

declare(strict_types=1);

namespace App\Services;

class CommandLine {

	public function __construct(){

	}

	public function cmd_escape(string $text) : string {
		return str_replace([">", "<"], ["^>", "^<"], $text);
	}

	public function title(string $title) : void {
		system("TITLE ".$this->cmd_escape($title));
	}

	public function cls() : void {
		popen('cls', 'w');
	}

	public function get_input() : string {
		return trim(readline());
	}

	public function get_input_no_trim() : string {
		return readline();
	}

	public function pause(?string $message = null) : void {
		if(!is_null($message)) echo $message;
		system("PAUSE > nul");
	}

	public function get_folders(string $string) : array {
		$string = trim($string);
		$folders = [];

		$length = strlen($string);
		$offset = 0;

		while($offset < $length){
			if(substr($string, $offset, 1) == '"'){
				$end = strpos($string, '"', $offset+1);
				array_push($folders, substr($string, $offset+1, $end - $offset-1));
				$offset = $end + 1;
			} else if(substr($string, $offset, 1) == ' '){
				$offset++;
			} else {
				$end = strpos($string, ' ', $offset);
				if($end !== false){
					array_push($folders, substr($string, $offset, $end - $offset));
					$offset = $end + 1;
				} else {
					array_push($folders, substr($string, $offset));
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
		exec("START /MIN \"\" \"$path\"");
	}

	public function get_file_attributes(string $path) : array {
		exec("ATTRIB \"$path\"", $var);
		$attributes = str_replace($path, '', $var[0]);
		return [
			'R' => (strpos($attributes, "R") !== false),
			'A' => (strpos($attributes, "A") !== false),
			'S' => (strpos($attributes, "S") !== false),
			'H' => (strpos($attributes, "H") !== false),
			'I' => (strpos($attributes, "I") !== false),
		];
	}

	public function set_file_attributes(string $path, bool|null $r = null, bool|null $a = null, bool|null $s = null, bool|null $h = null, bool|null $i = null) : void {
		$attributes = '';
		if(!is_null($r)) $attributes .= ($r ? '+' : '-').'R ';
		if(!is_null($a)) $attributes .= ($a ? '+' : '-').'A ';
		if(!is_null($s)) $attributes .= ($s ? '+' : '-').'S ';
		if(!is_null($h)) $attributes .= ($h ? '+' : '-').'H ';
		if(!is_null($i)) $attributes .= ($i ? '+' : '-').'I ';
		exec("ATTRIB $attributes \"$path\"");
	}

	public function is_valid_device(string $path) : bool {
		if(substr($path, 1, 1) == ':'){
			return file_exists(substr($path, 0, 3));
		} else if(substr($path, 0, 2) == "\\\\"){
			$device = substr($path, 2);
			if(strpos($device, "\\") !== false){
				$parts = explode("\\", $device);
				return file_exists("\\\\".$parts[0]."\\".$parts[1]);
			} else {
				return false;
			}
		}
	}

	public function get_file_path(string $path) : string {
		return str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
	}

}

?>
