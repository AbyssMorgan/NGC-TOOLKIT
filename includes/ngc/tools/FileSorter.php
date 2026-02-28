<?php

/**
 * NGC-TOOLKIT v2.9.0 – Component
 *
 * © 2026 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Tools;

use Toolkit;

class FileSorter {

	private string $name = "File Sorter";
	private string $action;
	private Toolkit $core;

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
	}

	public function help() : void {
		$this->core->print_help([
			' Actions:',
			' 0 - Sort by date',
			' 1 - Sort by extension',
			' 2 - Sort by size',
			' 3 - Sort by name prefix',
			' 4 - Sort by mime type',
		]);
	}

	public function action(string $action) : bool {
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_sort_files_date();
			case '1': return $this->tool_sort_files_extension();
			case '2': return $this->tool_sort_files_size();
			case '3': return $this->tool_sort_files_name_prefix();
			case '4': return $this->tool_sort_mime_type();
		}
		return false;
	}

	public function tool_sort_files_date() : bool {
		$this->core->set_subtool("Sort by date");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Modes:',
			' 0 - YYYYxMMxDD',
			' 1 - YYYYxMM',
			' 2 - YYYY',
			' 3 - YYxMMxDD',
			' 4 - DDxMMxYY',
			' 5 - DDxMMxYYYY',
			' 6 - YYYYxMMxDDxhh',
			' 7 - YYYYxMMxDDxhhxmm',
		]);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$params['mode'] = \strtolower($line[0] ?? '?');
		if(!\in_array($params['mode'], ['0', '1', '2', '3', '4', '5', '6', '7'])) goto set_mode;

		set_separator:
		$this->core->clear();
		$this->core->print_help([
			' Separators:',
			' . - _ \ @',
		]);

		$separator = $this->core->get_input(" Separator: ");
		if($separator == '#') return false;
		$params['separator'] = \strtolower($separator[0] ?? '?');
		if(!\in_array($params['separator'], ['.', '-', '_', '\\', '@'])) goto set_separator;
		if($params['separator'] == '\\') $params['separator'] = DIRECTORY_SEPARATOR;

		$this->core->clear();

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$extensions = $this->core->get_input_extensions(" Extensions: ");
		if($extensions === false) return false;

		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!\file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $extensions);
			$items = 0;
			$total = \count($files);
			foreach($files as $file){
				$items++;
				if(!\file_exists($file)) continue 1;
				if(!$this->core->move($file, $this->core->put_folder_to_path($file, \str_replace("-", $params['separator'], $this->tool_sort_date_format_date($params['mode'], \filemtime($file)))))){
					$errors++;
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

	public function tool_sort_files_extension() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort by extension");

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!\file_exists($folder)) continue;
			$files = $this->core->get_files($folder);
			$items = 0;
			$total = \count($files);
			foreach($files as $file){
				$items++;
				if(!\file_exists($file)) continue 1;
				if(!$this->core->move($file, $this->core->put_folder_to_path($file, $this->core->get_extension($file)))){
					$errors++;
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

	public function tool_sort_date_format_date(string $mode, int $date) : string {
		switch($mode){
			case '0': return \date('Y-m-d', $date);
			case '1': return \date('Y-m', $date);
			case '2': return \date('Y', $date);
			case '3': return \date('y-m-d', $date);
			case '4': return \date('d-m-y', $date);
			case '5': return \date('d-m-Y', $date);
			case '6': return \date('Y-m-d-h', $date);
			case '7': return \date('Y-m-d-h-i', $date);
		}
		return '';
	}

	public function tool_sort_files_size() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort by size");

		$interval = $this->core->get_input_bytes_size(" Size: ");
		if($interval === false) return false;

		$prefix = $this->core->get_confirm(" Add numeric prefix for better sort folders (Y/N): ");

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$extensions = $this->core->get_input_extensions(" Extensions: ");
		if($extensions === false) return false;

		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			$files = $this->core->get_files($folder, $extensions);
			$items = 0;
			$total = \count($files);
			foreach($files as $file){
				$items++;
				if(!\file_exists($file)) continue 1;
				$size = \filesize($file);
				$multiplier = \floor(($size - 1) / $interval);
				if($size == 0) $multiplier = 0;
				$end = $this->core->format_bytes(\intval($interval * ($multiplier + 1)));
				if($prefix){
					$directory = \sprintf("%06d", $multiplier)." $end";
				} else {
					$directory = $end;
				}
				if(!$this->core->move($file, $this->core->put_folder_to_path($file, $directory))){
					$errors++;
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

	public function tool_sort_files_name_prefix() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort by name prefix");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Modes:',
			' 0 - Delimiter',
			' 1 - Word length',
		]);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$params['mode'] = \strtolower($line[0] ?? '?');
		if(!\in_array($params['mode'], ['0', '1'])) goto set_mode;

		if($params['mode'] == '0'){
			set_delimiter:
			$delimiter = $this->core->get_input(" Delimiter: ");
			if(\strlen($delimiter) == 0) goto set_delimiter;
		} elseif($params['mode'] == '1'){
			$length = $this->core->get_input_integer(" Word length: ");
		}

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$extensions = $this->core->get_input_extensions(" Extensions: ");
		if($extensions === false) return false;

		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!\file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $extensions);
			$items = 0;
			$total = \count($files);
			foreach($files as $file){
				$items++;
				if(!\file_exists($file)) continue 1;
				$name = \pathinfo($file, PATHINFO_BASENAME);
				if($params['mode'] == '0'){
					$end = \strpos($name, $delimiter);
					if($end === false){
						$prefix = null;
					} else {
						$prefix = \trim(\substr($name, 0, $end));
					}
				} elseif($params['mode'] == '1'){
					$prefix = \trim(\substr($name, 0, $length));
				}
				if(!\is_null($prefix)){
					if(!$this->core->move($file, $this->core->put_folder_to_path($file, $prefix))){
						$errors++;
					}
				} else {
					$this->core->write_error("FAILED GET PREFIX \"$file\"");
					$errors++;
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

	public function tool_sort_mime_type() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort by mime type");

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!\file_exists($folder)) continue;
			$files = $this->core->get_files($folder);
			$items = 0;
			$total = \count($files);
			foreach($files as $file){
				$items++;
				if(!\file_exists($file)) continue 1;
				$mime_type = $this->core->media->get_mime_type($file);
				if($mime_type === false){
					$this->core->write_error("FAILED GET MIME TYPE \"$file\"");
					$errors++;
					continue 1;
				}
				if(!$this->core->move($file, $this->core->put_folder_to_path($file, \str_replace("/", "_", $mime_type)))){
					$errors++;
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

}

?>