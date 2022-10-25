<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;

class FileFunctions {

	private string $name = "FileFunctions";

	private array $params = [];
	private string $action;
	private AVE $ave;

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
	}

	public function help(){
		$this->ave->print_help([
			' Actions:',
			' 0 - Anti Duplicates',
			' 1 - Extension Change',
			' 2 - Validate CheckSum',
		]);
	}

	public function action(string $action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_antiduplicates_help();
			case '1': return $this->tool_extension_action();
			case '2': return $this->tool_validatechecksum_help();
		}
		$this->ave->select_action();
	}

	public function tool_antiduplicates_help(){
		$this->ave->clear();
		$this->ave->set_subtool("AntiDuplicates");

		$this->ave->print_help([
			' CheckSum Name   Action',
			' a1       b1     Rename',
			' a2       b2     Delete',
		]);

		echo " Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'action' => strtolower($line[1] ?? '?'),
		];

		if(!in_array($this->params['mode'],['a','b'])) return $this->tool_antiduplicates_help();
		if(!in_array($this->params['action'],['1','2'])) return $this->tool_antiduplicates_help();
		$this->ave->set_subtool("AntiDuplicates > ".$this->tool_antiduplicates_name($this->params['mode'])." > ".$this->tool_antiduplicates_actionname($this->params['action']));
		return $this->tool_antiduplicates_action();
	}

	public function tool_antiduplicates_name(string $mode) : string {
		switch($mode){
			case 'a': return 'CheckSum';
			case 'b': return 'Name';
		}
		return 'Unknown';
	}

	public function tool_antiduplicates_actionname(string $mode) : string {
		switch($mode){
			case '1': return 'Rename';
			case '2': return 'Delete';
		}
		return 'Unknown';
	}

	public function tool_antiduplicates_action(){
		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->tool_antiduplicates_help();
		$folders = $this->ave->get_folders($line);

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		$keys = [];
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$extension = strtolower(pathinfo($folder, PATHINFO_EXTENSION));
			if(is_file($folder)){
				$progress += $this->ave->getHashFromIDX($folder, $keys, true);
				$this->ave->set_progress($progress, $errors);
				$this->ave->set_folder_done($folder);
				continue;
			}
			$files = $this->ave->getFiles($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				if($extension == 'idx' && $this->ave->config->get('AVE_LOAD_IDX_CHECKSUM')){
					$progress += $this->ave->getHashFromIDX($file, $keys, false);
					$this->ave->set_progress($progress, $errors);
					continue 1;
				}
				if($this->params['mode'] == 'a'){
					$key = hash_file('md5', $file, false);
				} else {
					$key = pathinfo($file, PATHINFO_FILENAME);
				}
				$progress++;
				if(isset($keys[$key])){
					$duplicate = $keys[$key];
					$this->ave->log_error->write("DUPLICATE \"$file\" OF \"$duplicate\"");
					$errors++;
					if($this->params['action'] == '2'){
						if(!$this->ave->unlink($file)) $errors++;
					} else {
						if(!$this->ave->rename($file, "$file.tmp")) $errors++;
					}
				} else {
					$keys[$key] = $file;
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		unset($keys);
		$this->ave->exit();
	}

	public function tool_extension_action(){
		$this->ave->clear();
		$this->ave->set_subtool("ExtensionChange");
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);

		echo " Extension old: ";
		$extension_old = strtolower($this->ave->get_input());
		if($extension_old == '#') return $this->ave->select_action();

		echo " Extension new: ";
		$extension_new = $this->ave->get_input();
		if($extension_new == '#') return $this->ave->select_action();

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder, [$extension_old]);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$new_name = pathinfo($file,PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.pathinfo($file,PATHINFO_FILENAME).".".$extension_new;
				if($this->ave->rename($file, $new_name)){
					$progress++;
				} else {
					$errors++;
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

	public function tool_validatechecksum_help(){
		$this->ave->clear();
		$this->ave->set_subtool("ValidateCheckSum");

		$this->ave->print_help([
			' Modes:',
			' 0   - From file',
			' 1   - From name',
			' ?0  - md5 (default)',
			' ?1  - sha256',
			' ?2  - crc32',
			' ?3  - whirlpool',
		]);

		echo " Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'algo' => strtolower($line[1] ?? '0'),
		];

		if($this->params['algo'] == '?') $this->params['algo'] = '0';

		if(!in_array($this->params['mode'],['0','1'])) return $this->tool_validatechecksum_help();
		if(!in_array($this->params['algo'],['0','1','2','3'])) return $this->tool_validatechecksum_help();

		$this->ave->set_subtool("ValidateCheckSum > ".$this->tool_validatechecksum_name($this->params['mode'])." > ".$this->tool_validatechecksum_algo($this->params['algo']));
		return $this->tool_validatechecksum_action();
	}

	public function tool_validatechecksum_name(string $mode) : string {
		switch($mode){
			case '0': return 'File';
			case '1': return 'Name';
		}
		return 'Unknown';
	}

	public function tool_validatechecksum_algo(string $mode) : string {
		switch($mode){
			case '0': return 'md5';
			case '1': return 'sha256';
			case '2': return 'crc32';
			case '3': return 'whirlpool';
		}
		return 'md5';
	}

	public function tool_validatechecksum_algo_length(string $mode) : int {
		switch($mode){
			case '0': return 32;
			case '1': return 64;
			case '2': return 8;
			case '3': return 128;
		}
		return 32;
	}

	public function tool_validatechecksum_action(){
		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->tool_validatechecksum_help();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$algo = $this->tool_validatechecksum_algo($this->params['algo']);
		$algo_length = $this->tool_validatechecksum_algo_length($this->params['algo']);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		$except_files = explode(";", $this->ave->config->get('AVE_IGNORE_SCAN'));
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$file_id = 1;
			$list = [];
			$files = $this->ave->getFiles($folder, null, ['md5','sha256','crc32','whirlpool','srt']);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$file_name = strtolower(pathinfo($file, PATHINFO_FILENAME));
				if(in_array($file_name, $except_files)) continue;
				$hash = hash_file($algo, $file, false);
				$progress++;
				if($this->params['mode'] == '0'){
					$checksum_file = "$file.$algo";
					if(!file_exists($checksum_file)){
						$this->ave->log_error->write("FILE NOT FOUND \"$checksum_file\"");
						$errors++;
					} else {
						$hash_current = strtolower(trim(file_get_contents($checksum_file)));
						if($hash_current != $hash){
							$this->ave->log_error->write("INVALID FILE CHECKSUM \"$file\" current: $hash expected: $hash_current");
							$errors++;
						} else {
							$this->ave->log_event->write("FILE \"$file\" checksum: $hash");
						}
					}
				} else {
					$len = strlen($file_name);
					if($len < $algo_length){
						$this->ave->log_error->write("INVALID FILE NAME \"$file\"");
						$errors++;
					} else {
						if($len > $algo_length){
							$start = strpos($file_name, '[');
							if($start !== false){
								$end = strpos($file_name, ']', $start);
								$file_name = str_replace(' '.substr($file_name, $start, $end - $start + 1), '', $file_name);
							}
							$file_name = substr($file_name, strlen($file_name) - $algo_length, $algo_length);
						}
						if($file_name != $hash){
							$this->ave->log_error->write("INVALID FILE CHECKSUM \"$file\" current: $hash expected: $file_name");
							$errors++;
						} else {
							$this->ave->log_event->write("FILE \"$file\" checksum: $hash");
						}
					}
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

}

?>
