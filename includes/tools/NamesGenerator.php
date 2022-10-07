<?php

class NamesGenerator {

	private string $name = "NamesGenerator";

	private array $params = [];
	private string $action;
	private AVE $ave;

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
	}

	public function help(){
		echo " Actions:\r\n";
		echo " 0 - Generate Names CheckSum\r\n";
		echo " 1 - Generate Names Number\r\n";
		echo " 2 - Extension Change\r\n";
		echo " 3 - Video CheckSum/Resolution Generator\r\n";
	}

	public function action($action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': {
				return $this->tool_checksum_help();
			}
		}
		$this->ave->select_action();
	}

	public function tool_checksum_help(){
		$this->ave->clear();
		$this->ave->set_tool("$this->name > CheckSum");

		echo implode("\r\n",[
			' Modes:',
			' 0   - Normal           "<HASH>"',
			' 1   - CurrentName      "name <HASH>"',
			' 2   - DirectoryName    "dir_name <HASH>"',
			' 3   - DirectoryNameEx  "dir_name DDDD <HASH>"',
			' 4   - DateName         "YYYY.MM.DD <HASH>"',
			' 5   - DateNameEx       "YYYY.MM.DD DDDD <HASH>"',
			' 6   - NumberFour       "DDDD <HASH>"',
			' 7   - NumberSix        "DDDDDD <HASH>"',
			' ?0  - md5 (default)',
			' ?1  - sha256',
			' ?2  - crc32',
			' ?3  - whirlpool',
			' ??l - List only',
		]);

		echo "\r\n Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'algo' => strtolower($line[1] ?? '0'),
			'list_only' => strtolower(($line[2] ?? '?')) == 'l',
		];

		if(!in_array($this->params['mode'],['0','1','2','3','4','5','6','7'])) return $this->tool_checksum_help();
		if(!in_array($this->params['algo'],['0','1','2','3'])) return $this->tool_checksum_help();
		$this->ave->set_tool("$this->name > CheckSum > ".$this->tool_checksum_name($this->params['mode'])." > ".$this->tool_checksum_algo($this->params['algo']));
		return $this->tool_cheksum_action();
	}

	public function tool_checksum_name(string $mode){
		switch($mode){
			case '0': return 'Normal';
			case '1': return 'CurrentName';
			case '2': return 'DirectoryName';
			case '3': return 'DirectoryNameEx';
			case '4': return 'DateName';
			case '5': return 'DateNameEx';
			case '6': return 'NumberFour';
			case '7': return 'NumberSix';
		}
		return 'Unknown';
	}

	public function tool_checksum_algo(string $mode){
		switch($mode){
			case '0': return 'md5';
			case '1': return 'sha256';
			case '2': return 'crc32';
			case '3': return 'whirlpool';
		}
		return 'md5';
	}

	public function tool_checksum_get_pattern(string $mode, string $file, string $hash, int $file_id){
		$folder = pathinfo($file, PATHINFO_DIRNAME);
		$foldername = pathinfo($folder, PATHINFO_FILENAME);
		$name = pathinfo($file, PATHINFO_FILENAME);
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		if($this->ave->config->get('AVE_EXTENSION_TO_LOWER')) $extension = strtolower($extension);
		switch($mode){
			case '0': return "$folder\\$hash.$extension";
			case '1': return "$folder\\$name $hash.$extension";
			case '2': return "$folder\\$foldername $hash.$extension";
			case '3': return "$folder\\$foldername ".sprintf("%04d",$file_id)." $hash.$extension";
			case '4': return "$folder\\".date("Y-m-d",filemtime($file))." $hash.$extension";
			case '5': return "$folder\\".date("Y-m-d",filemtime($file))." ".sprintf("%04d",$file_id)." $hash.$extension";
			case '6': return "$folder\\".sprintf("%04d",$file_id)." $hash.$extension";
			case '7': return "$folder\\".sprintf("%06d",$file_id)." $hash.$extension";
		}
	}

	public function tool_cheksum_action(){
		$this->ave->clear();
		echo "\r\n Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->tool_checksum_help();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$algo = $this->tool_checksum_algo($this->params['algo']);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		foreach($folders as $folder){
			$file_id = 1;
			if(!file_exists($folder)) continue;
			$files = new RecursiveDirectoryIterator($folder,FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS);
			foreach(new RecursiveIteratorIterator($files) as $file){
				if(is_dir($file) || is_link($file)) continue 2;
				$hash = hash_file($algo, $file, false);
				if($this->ave->config->get('AVE_HASH_TO_UPPER')) $hash = strtoupper($hash);
				$new_name = $this->tool_checksum_get_pattern($this->params['mode'], $file, $hash, $file_id++);
				$progress++;
				if($this->params['list_only']){
					echo "lista $file $new_name\r\n";
				} else {
					if(file_exists($new_name) && $new_name != $file){
						$errors++;
						if($this->ave->config('AVE_ACTION_AFTER_DUPLICATE') == 'DELETE'){
							unlink($file);
						} else {
							rename($file,"$file.tmp");
						}
					} else {
						rename($file,$new_name);
					}
				}
				$this->ave->set_progress($progress, $errors);
			}
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

}

?>
