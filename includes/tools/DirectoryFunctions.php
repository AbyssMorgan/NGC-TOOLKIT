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

	public function help() : void {
		$this->ave->print_help([
			' Actions:',
			' 0 - Delete empty folders',
			' 1 - Force load icon (desktop.ini)',
			' 2 - Count files in every folder',
			' 3 - Clone folder structure',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolDeleteEmptyFolders();
			case '1': return $this->ToolForceLoadIcon();
			case '2': return $this->ToolCountFiles();
			case '3': return $this->ToolCloneFolderStructure();
		}
		return false;
	}

	public function ToolDeleteEmptyFolders() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("DeleteEmptyFolders");
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);

		$this->ave->setup_folders($folders);

		$errors = 0;
		$this->ave->set_errors($errors);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = array_reverse($this->ave->get_folders($folder));
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$count = iterator_count(new FilesystemIterator($file, FilesystemIterator::SKIP_DOTS));
				if($count == 0){
					if(!$this->ave->rmdir($file)){
						$errors++;
					}
				}
				$this->ave->progress($items, $total);
				$this->ave->set_errors($errors);
			}
			$this->ave->progress($items, $total);
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolForceLoadIcon() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ForceLoadIcon");
		if(!$this->ave->windows) return $this->ave->windows_only();
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);

		$this->ave->setup_folders($folders);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->get_folders($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$ini = $this->ave->get_file_path("$file/desktop.ini");
				if(!file_exists($ini)) continue 1;
				$a = $this->ave->get_file_attributes($file);
				$this->ave->set_file_attributes($file, true, $a['A'], $a['S'], $a['H']);
				$a = $this->ave->get_file_attributes($ini);
				$this->ave->set_file_attributes($ini, $a['R'], $a['A'], $a['S'], true);
				$this->ave->progress($items, $total);
			}
			$this->ave->progress($items, $total);
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolCountFiles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("CountFiles");

		$this->ave->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->ave->get_input(" Extensions: ");
		if($line == '#') return false;

		if($line == ''){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);

		$this->ave->setup_folders($folders);

		$data = [];

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->get_files($folder, $extensions);
			$this->ave->write_log($files);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$key = pathinfo($file, PATHINFO_DIRNAME);
				if(!isset($data[$key])) $data[$key] = 0;
				$data[$key]++;
				$this->ave->progress($items, $total);
			}
			$this->ave->progress($items, $total);
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$separator = $this->ave->config->get('AVE_CSV_SEPARATOR');
		foreach($data as $path => $count){
			$this->ave->write_data($count.$separator."\"$path\"");
		}

		unset($data);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolCloneFolderStructure() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("CloneFolderStructure");

		set_input:
		$line = $this->ave->get_input(" Input (Folder): ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->ave->echo(" Invalid input folder");
			goto set_input;
		}

		set_output:
		$line = $this->ave->get_input(" Output (Folder): ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			$this->ave->echo(" Invalid output folder");
			goto set_output;
		}

		$errors = 0;
		$this->ave->set_errors($errors);

		$folders = $this->ave->get_folders($input);
		$items = 0;
		$total = count($folders);
		foreach($folders as $folder){
			$items++;
			$directory = str_ireplace($input, $output, $folder);
			if(!file_exists($directory)){
				if(!$this->ave->mkdir($directory)){
					$errors++;
				}
			}
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}
		$this->ave->progress($items, $total);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

}

?>
