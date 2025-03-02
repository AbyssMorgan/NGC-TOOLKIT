<?php

declare(strict_types=1);

namespace NGC\Tools;

use Toolkit;
use FilesystemIterator;

class DirectoryFunctions {

	private string $name = "Directory Functions";
	private array $params = [];
	private string $action;
	private Toolkit $core;

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
	}

	public function help() : void {
		$this->core->print_help([
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
			case '0': return $this->tool_delete_empty_folders();
			case '1': return $this->tool_force_load_icon();
			case '2': return $this->tool_count_files();
			case '3': return $this->tool_clone_folder_structure();
		}
		return false;
	}

	public function tool_delete_empty_folders() : bool {
		$this->core->clear();
		$this->core->set_subtool("Delete empty folders");
		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);

		$this->core->setup_folders($folders);

		$errors = 0;
		$this->core->set_errors($errors);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = array_reverse($this->core->get_folders($folder));
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$count = iterator_count(new FilesystemIterator($file, FilesystemIterator::SKIP_DOTS));
				if($count == 0){
					if(!$this->core->rmdir($file)){
						$errors++;
					}
				}
				$this->core->progress($items, $total);
				$this->core->set_errors($errors);
			}
			$this->core->progress($items, $total);
			unset($files);
			$this->core->set_folder_done($folder);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_force_load_icon() : bool {
		$this->core->clear();
		$this->core->set_subtool("Force load icon");
		if($this->core->get_system_type() != SYSTEM_TYPE_WINDOWS) return $this->core->windows_only();

		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		$this->core->setup_folders($folders);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_folders($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$ini = $this->core->get_path("$file/desktop.ini");
				if(!file_exists($ini)) continue 1;
				$a = $this->core->get_file_attributes($file);
				$this->core->set_file_attributes($file, true, $a['A'], $a['S'], $a['H']);
				$a = $this->core->get_file_attributes($ini);
				$this->core->set_file_attributes($ini, $a['R'], $a['A'], $a['S'], true);
				$this->core->progress($items, $total);
			}
			$this->core->progress($items, $total);
			unset($files);
			$this->core->set_folder_done($folder);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_count_files() : bool {
		$this->core->clear();
		$this->core->set_subtool("Count files");

		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		$this->core->setup_folders($folders);

		$this->core->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->core->get_input(" Extensions: ");
		if($line == '#') return false;
		if($line == ''){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$data = [];

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $extensions);
			$this->core->write_log($files);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$key = pathinfo($file, PATHINFO_DIRNAME);
				if(!isset($data[$key])) $data[$key] = 0;
				$data[$key]++;
				$this->core->progress($items, $total);
			}
			$this->core->progress($items, $total);
			unset($files);
			$this->core->set_folder_done($folder);
		}

		$separator = $this->core->config->get('CSV_SEPARATOR');
		foreach($data as $path => $count){
			$this->core->write_data("{$count}{$separator}\"$path\"");
		}

		unset($data);

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_clone_folder_structure() : bool {
		$this->core->clear();
		$this->core->set_subtool("Clone folder structure");

		set_input:
		$line = $this->core->get_input(" Input (Folder): ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->core->echo(" Invalid input folder");
			goto set_input;
		}

		set_output:
		$line = $this->core->get_input(" Output (Folder): ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		$errors = 0;
		$this->core->set_errors($errors);

		$folders = $this->core->get_folders($input);
		$items = 0;
		$total = count($folders);
		foreach($folders as $folder){
			$items++;
			$directory = str_ireplace($input, $output, $folder);
			if(!file_exists($directory)){
				if(!$this->core->mkdir($directory)){
					$errors++;
				}
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$this->core->progress($items, $total);

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

}

?>