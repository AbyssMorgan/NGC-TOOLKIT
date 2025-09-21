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
use NGC\Services\StringConverter;

class DirectoryNamesEditor {

	private string $name = "Directory Names Editor";
	private string $action;
	private Toolkit $core;

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
	}

	public function help() : void {
		$this->core->print_help([
			' Actions:',
			' 0 - Escape directory name (WWW)',
			' 1 - Pretty directory name',
			' 2 - Add directory name prefix/suffix',
			' 3 - Remove keywords from directory name',
			' 4 - Insert string into directory name',
			' 5 - Replace keywords in directory name',
		]);
	}

	public function action(string $action) : bool {
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_escape_directory_name_www();
			case '1': return $this->tool_pretty_directory_name();
			case '2': return $this->tool_add_directory_name_prefix_suffix();
			case '3': return $this->tool_remove_keywords_from_directory_name();
			case '4': return $this->tool_insert_string_into_directory_name();
			case '5': return $this->tool_replace_keywords_in_directory_name();
		}
		return false;
	}

	public function tool_escape_directory_name_www() : bool {
		$this->core->clear();
		$this->core->set_subtool("Escape directory name WWW");
		$this->core->print_help([
			" Double spaces reduce",
			" Characters after escape: A-Z a-z 0-9 _ - .",
			" Be careful to prevent use on Japanese, Chinese, Korean, etc. directory names",
		]);

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_folders($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$escaped_name = pathinfo($file, PATHINFO_BASENAME);
				while(str_contains($escaped_name, '  ')){
					$escaped_name = str_replace('  ', ' ', $escaped_name);
				}
				$escaped_name = trim(preg_replace('/[^A-Za-z0-9_\-.]/', '', str_replace(' ', '_', $escaped_name)), ' ');
				if(empty($escaped_name)){
					$this->core->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} else {
					$new_name = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/$escaped_name");
					if(file_exists($new_name) && mb_strtoupper($new_name) != mb_strtoupper($file)){
						$this->core->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
						$errors++;
					} else {
						if(!$this->core->move($file, $new_name)){
							$errors++;
						}
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

	public function tool_pretty_directory_name() : bool {
		$this->core->clear();
		$this->core->set_subtool("Pretty directory name");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Flags (type in one line, default BC):',
			' B - Basic replacement',
			' C - Basic remove',
			' L - Replace language characters',
			' P - Capitalize properly',
			' 0 - Chinese to PinYin',
			' 1 - Hiragama to Romaji',
			' 2 - Katakana to Romaji',
			' + - To upper case',
			' - - To lower case',
		]);

		$line = strtoupper($this->core->get_input(" Flags: "));
		if($line == '#') return false;
		if(empty($line)) $line = 'BC';
		if(str_replace(['B', 'C', 'L', 'P', '0', '1', '2', '+', '-'], '', $line) != '') goto set_mode;
		$flags = (object)[
			'basic_replace' => str_contains($line, 'B'),
			'basic_remove' => str_contains($line, 'C'),
			'language_replace' => str_contains($line, 'L'),
			'ChineseToPinYin' => str_contains($line, '0'),
			'HiragamaToRomaji' => str_contains($line, '1'),
			'KatakanaToRomaji' => str_contains($line, '2'),
			'UpperCase' => str_contains($line, '+'),
			'LowerCase' => str_contains($line, '-'),
			'CapitalizeProperly' => str_contains($line, 'P'),
		];
		$converter = new StringConverter();
		if($flags->language_replace){
			$converter->import_replacement($this->core->get_resource("LanguageReplacement.ini"));
		}
		if($flags->ChineseToPinYin){
			$converter->import_pin_yin($this->core->get_resource("PinYin.gz-ini"));
		}
		if($flags->HiragamaToRomaji){
			$converter->import_replacement($this->core->get_resource("Hiragama.gz-ini"));
		}
		if($flags->KatakanaToRomaji){
			$converter->import_replacement($this->core->get_resource("Katakana.gz-ini"));
		}
		$this->core->clear();

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_folders($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$escaped_name = pathinfo($file, PATHINFO_BASENAME);
				if($flags->basic_replace || $flags->language_replace || $flags->HiragamaToRomaji || $flags->KatakanaToRomaji){
					$escaped_name = $converter->convert($escaped_name);
				}
				if($flags->basic_remove){
					$escaped_name = $converter->clean($escaped_name);
				}
				if($flags->ChineseToPinYin){
					$escaped_name = $converter->string_to_pin_yin($escaped_name);
				}
				if($flags->UpperCase){
					$escaped_name = mb_strtoupper($escaped_name);
				} elseif($flags->LowerCase){
					$escaped_name = mb_strtolower($escaped_name);
				} elseif($flags->CapitalizeProperly){
					$escaped_name = ucwords(mb_strtolower($escaped_name));
					$escaped_name = preg_replace_callback('/(\d)([a-z])/', function(array $matches) : string {
						return $matches[1].mb_strtoupper($matches[2]);
					}, $escaped_name);
					$escaped_name = preg_replace_callback('/([\(\[])([a-z])/', function(array $matches) : string {
						return $matches[1].mb_strtoupper($matches[2]);
					}, $escaped_name);
				}
				$escaped_name = $converter->remove_double_spaces(str_replace(',', ', ', $escaped_name));
				if(empty($escaped_name)){
					$this->core->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} else {
					$new_name = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/$escaped_name");
					if(file_exists($new_name) && mb_strtoupper($new_name) != mb_strtoupper($file)){
						$this->core->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
						$errors++;
					} else {
						if(!$this->core->move_case($file, $new_name)){
							$errors++;
						}
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

	public function tool_add_directory_name_prefix_suffix() : bool {
		$this->core->clear();
		$this->core->set_subtool("Add directory name prefix/suffix");

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$prefix = $this->core->get_input(" Prefix (may be empty): ", false);
		if($prefix == '#') return false;
		$prefix = str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*'], '', $prefix);

		$suffix = $this->core->get_input(" Suffix (may be empty): ", false);
		if($suffix == '#') return false;
		$suffix = str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*'], '', $suffix);

		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_folders($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$new_name = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/$prefix".pathinfo($file, PATHINFO_BASENAME).$suffix);
				if(file_exists($new_name) && mb_strtoupper($new_name) != mb_strtoupper($file)){
					$this->core->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
					$errors++;
				} else {
					if(!$this->core->move($file, $new_name)){
						$errors++;
					}
				}
				$this->core->progress($items, $total);
				$this->core->set_errors($errors);
			}
			$this->core->progress($items, $total);
			$this->core->set_folder_done($folder);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_remove_keywords_from_directory_name() : bool {
		$this->core->clear();
		$this->core->set_subtool("Remove keywords from directory name");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Modes:',
			' 0 - Type keywords',
			' 1 - Load from file (new line every keyword)',
		]);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$params = [
			'mode' => $line[0] ?? '?',
		];

		if(!in_array($params['mode'], ['0', '1'])) goto set_mode;

		$this->core->clear();

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$keywords = [];
		if($params['mode'] == '0'){
			$this->core->echo(" Put numbers how much keywords you want remove");

			$quantity = $this->core->get_input_integer(" Quantity: ");
			if($quantity === false) return false;

			for($i = 0; $i < $quantity; $i++){
				$keywords[$i] = $this->core->get_input(" Keyword ".($i + 1).": ", false);
			}
		} elseif($params['mode'] == '1'){
			set_keyword_file:
			$input = $this->core->get_input_file(" Keywords file: ", true);
			if($input === false) return false;

			$fp = fopen($input, 'r');
			if(!$fp){
				$this->core->echo(" Failed open keywords file");
				goto set_keyword_file;
			}
			while(($line = fgets($fp)) !== false){
				$line = str_replace(["\n", "\r", $this->core->utf8_bom], "", $line);
				if(empty(trim($line))) continue;
				array_push($keywords, $line);
			}
			fclose($fp);
		}

		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_folders($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$name = trim(str_replace($keywords, '', pathinfo($file, PATHINFO_BASENAME)));
				$new_name = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/$name");
				if(empty($new_name)){
					$this->core->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} elseif(file_exists($new_name) && mb_strtoupper($new_name) != mb_strtoupper($file)){
					$this->core->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
					$errors++;
				} else {
					if(!$this->core->move($file, $new_name)){
						$errors++;
					}
				}
				$this->core->progress($items, $total);
				$this->core->set_errors($errors);
			}
			$this->core->progress($items, $total);
			$this->core->set_folder_done($folder);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_insert_string_into_directory_name() : bool {
		$this->core->set_subtool("Insert string into directory name");

		set_offset:
		$this->core->clear();
		$this->core->print_help([
			' Specify the string offset where you want insert into directory name',
			' Offset = 0 - means the beginning, i.e. the string will be inserted before the directory name (prefix)',
			' Offset > 0 - means that the string will be inserted after skipping N characters',
			' Offset < 0 - means that the string will be inserted after skipping N characters from the end',
		]);
		$line = $this->core->get_input(" Offset: ");
		if($line == '#') return false;
		$offset = preg_replace("/[^0-9\-]/", '', $line);
		if($offset == '') goto set_offset;
		$offset = intval($offset);

		$this->core->print_help([
			' Specify the string you want to inject the file name, may contain spaces',
		]);
		$insert_string = $this->core->get_input(" String: ", false);

		$this->core->clear();

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_folders($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$name = pathinfo($file, PATHINFO_BASENAME);
				if(abs($offset) > strlen($name)){
					$this->core->write_error("ILLEGAL OFFSET FOR FILE NAME \"$file\"");
					$errors++;
				} else {
					if($offset > 0){
						$name = substr($name, 0, $offset).$insert_string.substr($name, $offset);
					} elseif($offset < 0){
						$name = substr($name, 0, strlen($name) + $offset).$insert_string.substr($name, $offset);
					} else {
						$name = $insert_string.$name;
					}
					$new_name = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/$name");
					if(file_exists($new_name) && mb_strtoupper($new_name) != mb_strtoupper($file)){
						$this->core->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
						$errors++;
					} else {
						if(!$this->core->move($file, $new_name)){
							$errors++;
						}
					}
				}
				$this->core->progress($items, $total);
				$this->core->set_errors($errors);
			}
			$this->core->progress($items, $total);
			$this->core->set_folder_done($folder);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_replace_keywords_in_directory_name() : bool {
		$this->core->clear();
		$this->core->set_subtool("Replace keywords in directory name");

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		set_keyword_file:
		$input = $this->core->get_input_file(" Keywords file: ", true);
		if($input === false) return false;

		$fp = fopen($input, 'r');
		if(!$fp){
			$this->core->echo(" Failed open keywords file");
			goto set_keyword_file;
		}

		$replacements = [];
		$i = 0;
		$errors = 0;
		while(($line = fgets($fp)) !== false){
			$i++;
			$line = str_replace(["\n", "\r", $this->core->utf8_bom], "", $line);
			if(empty(trim($line))) continue;
			$replace = $this->core->parse_input_path($line, false);
			if(!isset($replace[0]) || !isset($replace[1]) || isset($replace[2])){
				$this->core->echo(" Failed parse replacement in line $i content: '$line'");
				$errors++;
			} else {
				$replacements[$replace[0]] = $replace[1];
			}
		}
		fclose($fp);

		if($errors > 0){
			if(!$this->core->get_confirm(" Errors detected, continue with valid replacement (Y/N): ")) goto set_keyword_file;
		}

		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_folders($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$name = trim(str_replace(array_keys($replacements), $replacements, pathinfo($file, PATHINFO_BASENAME)));
				$new_name = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/$name");
				if(empty($new_name)){
					$this->core->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} elseif(file_exists($new_name) && mb_strtoupper($new_name) != mb_strtoupper($file)){
					$this->core->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
					$errors++;
				} else {
					if(!$this->core->move($file, $new_name)){
						$errors++;
					}
				}
				$this->core->progress($items, $total);
				$this->core->set_errors($errors);
			}
			$this->core->progress($items, $total);
			$this->core->set_folder_done($folder);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

}

?>