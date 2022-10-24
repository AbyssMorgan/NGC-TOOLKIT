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
		]);
	}

	public function action(string $action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_antiduplicates_help();
			case '1': return $this->tool_extension_action();
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

}

?>
