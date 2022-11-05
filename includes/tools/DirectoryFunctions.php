<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use FilesystemIterator;

class DirectoryFunctions {

	private string $name = "DirectoryFunctions";

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
			' 0 - Delete empty dirs',
			' 1 - Force load icon (desktop.ini)',
			' 2 - Count files in every dirs',
			' 3 - Clone folder structure',
		]);
	}

	public function action(string $action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_deleteemptydirs_action();
			case '1': return $this->tool_forceloadicon_action();
			case '2': return $this->tool_countfiles_action();
			case '3': return $this->tool_clonefolderstructure_action();
		}
		$this->ave->select_action();
	}

	public function tool_deleteemptydirs_action(){
		$this->ave->clear();
		$this->ave->set_subtool("DeleteEmptyDirs");
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = array_reverse($this->ave->getFolders($folder));
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$count = iterator_count(new FilesystemIterator($file, FilesystemIterator::SKIP_DOTS));
				if($count == 0){
					if($this->ave->rmdir($file)){
						$progress++;
					} else {
						$errors++;
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

	public function tool_forceloadicon_action(){
		$this->ave->clear();
		$this->ave->set_subtool("ForceLoadIcon");
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFolders($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				if(!file_exists($file.DIRECTORY_SEPARATOR."desktop.ini")) continue 1;
				$a = $this->ave->get_file_attributes($file);
				$this->ave->set_file_attributes($file, true, $a['A'], $a['S'], $a['H']);
				$progress++;
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

	public function tool_countfiles_action(){
		$this->ave->clear();
		$this->ave->set_subtool("CountFiles");

		echo " Extensions (empty for all): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		if($line == '' || $line == '*'){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->select_action();
		$folders = $this->ave->get_folders($line);

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		$data = [];

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder, $extensions);
			$this->ave->log_event->write($files);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$progress++;
				$key = pathinfo($file, PATHINFO_DIRNAME);
				if(!isset($data[$key])) $data[$key] = 0;
				$data[$key]++;
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		foreach($data as $path => $count){
			if($this->ave->config->get('AVE_FILE_COUNT_FORMAT') == 'CSV'){
				$this->ave->log_data->write("$count;\"$path\"");
			} else {
				$this->ave->log_data->write("\"$count\" \"$path\"");
			}
		}

		unset($data);

		$this->ave->exit();
	}

	public function tool_clonefolderstructure_action(){
		$this->ave->clear();
		$this->ave->set_subtool("CloneFolderStructure");

		set_input:
		echo " Input (Folder): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder\r\n";
			goto set_input;
		}

		set_output:
		echo " Output (Folder): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if(file_exists($output) && !is_dir($output)){
			echo " Invalid output folder\r\n";
			goto set_output;
		}

		if(!file_exists($output)){
			if(!$this->ave->mkdir($output)){
				echo " Failed create output folder\r\n";
				goto set_output;
			}
		}

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		$folders = $this->ave->getFolders($input);
		$items = 0;
		$total = count($folders);
		foreach($folders as $folder){
			$items++;
			$directory = str_replace($input, $output, $folder);
			if(!file_exists($directory)){
				if($this->ave->mkdir($directory)){
					$progress++;
				} else {
					$errors++;
				}
			}
			$this->ave->progress($items, $total);
			$this->ave->set_progress($progress, $errors);
		}

		$this->ave->exit();
	}

}

?>
