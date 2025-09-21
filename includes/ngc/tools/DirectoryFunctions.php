<?php

/**
 * NGC-TOOLKIT v2.7.3 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Tools;

use Toolkit;
use FilesystemIterator;

class DirectoryFunctions {

	private string $name = "Directory Functions";
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

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

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

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

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

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$extensions = $this->core->get_input_extensions(" Extensions: ");
		if($extensions === false) return false;

		$this->core->setup_folders($folders);
		
		$data = [];
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $extensions);
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

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		$output = $this->core->get_input_folder(" Output (Folder): ", true);
		if($output === false) return false;

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