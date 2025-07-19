<?php

/**
 * NGC-TOOLKIT v2.7.1 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Tools;

use Toolkit;
use Exception;
use NGC\Services\StringConverter;

class FileEditor {

	private string $name = "File Editor";
	private string $action;
	private Toolkit $core;

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
	}

	public function help() : void {
		$this->core->print_help([
			' Actions:',
			' 0 - Replace keywords in files',
			' 1 - Remove keywords in files',
			' 2 - Remove duplicate lines in file',
			' 3 - Split file by lines count',
			' 4 - Split file by size (Binary)',
			' 5 - Reverse text file lines',
			' 6 - Pretty file content',
		]);
	}

	public function action(string $action) : bool {
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_replace_keywords_in_files();
			case '1': return $this->tool_remove_keywords_in_files();
			case '2': return $this->tool_remove_duplicate_lines_in_file();
			case '3': return $this->tool_split_file_by_lines_count();
			case '4': return $this->tool_split_file_by_size();
			case '5': return $this->tool_reverse_file_lines();
			case '6': return $this->tool_pretty_file_content();
		}
		return false;
	}

	public function tool_replace_keywords_in_files() : bool {
		$this->core->clear();
		$this->core->set_subtool("Replace keywords in files");

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$extensions = $this->core->get_input_extensions(" Extensions: ");
		if($extensions === false) return false;

		set_keyword_file:
		$keywords_file = $this->core->get_input_file(" Keywords file: ", true);
		if($keywords_file === false) return false;

		$replacements = [];
		$fp = fopen($keywords_file, 'r');
		if(!$fp){
			$this->core->echo(" Failed open keywords file");
			goto set_keyword_file;
		}
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
			$files = $this->core->get_files($folder, $extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				try {
					$content = file_get_contents($file);
					$new_content = str_replace(array_keys($replacements), $replacements, $content);
					$changed = $content != $new_content;
					unset($content);
					if($changed){
						file_put_contents($file, $new_content);
						$this->core->write_log("EDIT FILE \"$file\"");
					}
				}
				catch(Exception $e){
					$this->core->write_error("FAILED EDIT FILE \"$file\" ERROR:".$e->getMessage());
					$errors++;
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

	public function tool_remove_keywords_in_files() : bool {
		$this->core->clear();
		$this->core->set_subtool("Remove keywords in files");

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$extensions = $this->core->get_input_extensions(" Extensions: ");
		if($extensions === false) return false;

		set_keyword_file:
		$keywords_file = $this->core->get_input_file(" Keywords file: ", true);
		if($keywords_file === false) return false;

		$keywords = [];
		$fp = fopen($keywords_file, 'r');
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

		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				try {
					$content = file_get_contents($file);
					$new_content = str_replace($keywords, '', $content);
					$changed = ($content != $new_content);
					unset($content);
					if($changed){
						file_put_contents($file, $new_content);
						$this->core->write_log("EDIT FILE \"$file\"");
					}
				}
				catch(Exception $e){
					$this->core->write_error("FAILED EDIT FILE \"$file\" ERROR:".$e->getMessage());
					$errors++;
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

	public function tool_remove_duplicate_lines_in_file() : bool {
		$this->core->clear();
		$this->core->set_subtool("Remove duplicate lines in file");

		set_input:
		$file = $this->core->get_input_file(" File: ", true);
		if($file === false) return false;

		if(!$this->core->is_text_file($file)){
			if(!$this->core->get_confirm(" The file does not appear to be a text file, continue (Y/N): ")) goto set_input;
		}

		$ignore_empty_lines = $this->core->get_confirm(" Ignore empty lines comparison (Y/N): ");

		$duplicates = 0;

		try {
			$content = file_get_contents($file);
			$bom = $this->core->has_utf8_bom($content) ? $this->core->utf8_bom : "";
			if(!empty($bom)){
				$content = str_replace($bom, "", $content);
			}
			$eol = $this->core->detect_eol($content);
			$data = [];
			$lines = explode($eol, $content);
			foreach($lines as $line){
				if(in_array($line, $data)){
					if(empty(trim($line))){
						if($ignore_empty_lines){
							array_push($data, $line);
						} else {
							$duplicates++;
						}
					} else {
						$duplicates++;
					}
				} else {
					array_push($data, $line);
				}
			}
			unset($lines);
			$new_content = $bom.implode($eol, $data);
			$changed = ($content != $new_content);
			unset($content);
			if($changed){
				file_put_contents($file, $new_content);
				$this->core->echo(" Removed $duplicates lines in \"$file\"");
			}
		}
		catch(Exception $e){
			$this->core->echo(" Failed edit \"$file\" Error:".$e->getMessage());
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_split_file_by_lines_count() : bool {
		$this->core->clear();
		$this->core->set_subtool("Split file by lines count");

		$lines_limit = $this->core->get_input_integer(" Lines limit: ");
		if($lines_limit === false) return false;

		set_input:
		$file = $this->core->get_input_file(" File: ", true);
		if($file === false) return false;

		if(!$this->core->is_text_file($file)){
			if(!$this->core->get_confirm(" The file does not appear to be a text file, continue (Y/N): ")) goto set_input;
		}

		$fp = fopen($file, 'r');
		if(!$fp){
			$this->core->echo(" Failed open input file");
			goto set_input;
		}

		$first_line = fgets($fp);
		fseek($fp, 0);

		$utf8_bom = $this->core->has_utf8_bom($first_line);
		$eol = $this->core->detect_eol($first_line);

		$count = 0;
		$part_id = 1;
		$out = false;
		while(($line = fgets($fp)) !== false){
			$line = str_replace(["\n", "\r", $this->core->utf8_bom], "", $line);
			if($count % $lines_limit == 0){
				if($out){
					fclose($out);
					$out = false;
				}
				$output_file = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/".pathinfo($file, PATHINFO_FILENAME)."_".sprintf("%06d", $part_id).".".$this->core->get_extension($file));
				if(file_exists($output_file)) $this->core->delete($output_file);
				$out = fopen($output_file, 'w');
				if(!$out){
					$this->core->write_error("FAILED OPEN FILE \"$output_file\"");
					break;
				} else {
					$this->core->write_log("CREATE FILE \"$output_file\"");
				}
				if($utf8_bom) fwrite($out, $this->core->utf8_bom);
				$part_id++;
			}
			fwrite($out, "{$line}{$eol}");
			$count++;
		}
		if($out){
			fclose($out);
			$out = false;
		}
		fclose($fp);

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_split_file_by_size() : bool {
		$this->core->clear();
		$this->core->set_subtool("Split file by size");

		$bytes = $this->core->get_input_bytes_size(" Size: ");
		if($bytes === false) return false;

		set_input:
		$file = $this->core->get_input_file(" File: ", true);
		if($file === false) return false;

		$fp = fopen($file, 'r');
		if(!$fp){
			$this->core->echo(" Failed open input file");
			goto set_input;
		}

		$part_id = 1;
		while(!feof($fp)){
			$buffer = fread($fp, $bytes);
			$output_file = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/".pathinfo($file, PATHINFO_FILENAME)."_".sprintf("%06d", $part_id).".".$this->core->get_extension($file));
			if(file_exists($output_file)) $this->core->delete($output_file);
			$out = fopen($output_file, 'w');
			if(!$out){
				$this->core->write_error("FAILED OPEN FILE \"$output_file\"");
				break;
			} else {
				$this->core->write_log("CREATE FILE \"$output_file\"");
			}
			fwrite($out, $buffer, strlen(bin2hex($buffer)) / 2);
			fclose($out);
			$part_id++;
		}
		fclose($fp);

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_reverse_file_lines() : bool {
		$this->core->clear();
		$this->core->set_subtool("Reverse file lines");

		set_input:
		$file = $this->core->get_input_file(" File: ", true);
		if($file === false) return false;

		if(!$this->core->is_text_file($file)){
			if(!$this->core->get_confirm(" The file does not appear to be a text file, continue (Y/N): ")) goto set_input;
		}

		try {
			$content = file_get_contents($file);
			$bom = $this->core->has_utf8_bom($content) ? $this->core->utf8_bom : "";
			if(!empty($bom)){
				$content = str_replace($bom, "", $content);
			}
			$eol = $this->core->detect_eol($content);
			$lines = array_reverse(explode($eol, $content));
			$new_content = $bom.implode($eol, $lines);
			$changed = ($content != $new_content);
			unset($content);
			if($changed){
				file_put_contents($file, $new_content);
				$this->core->echo(" Reversed file lines in \"$file\"");
			}
		}
		catch(Exception $e){
			$this->core->echo(" Failed edit \"$file\" Error:".$e->getMessage());
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_pretty_file_content() : bool {
		$this->core->clear();
		$this->core->set_subtool("Pretty file content");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Flags (type in one line, default BC):',
			' B - Basic replacement',
			' C - Basic remove',
			' L - Replace language characters',
			' S - Remove double spaces',
			' W - Remove whitespace characters on EOL',
			' F - Remove whitespace characters on EOF',
			' 0 - Chinese to PinYin',
			' 1 - Hiragama to Romaji',
			' 2 - Katakana to Romaji',
			' + - To upper case',
			' - - To lower case',
		]);

		$line = strtoupper($this->core->get_input(" Flags: "));
		if($line == '#') return false;
		if(empty($line)) $line = 'BC';
		if(str_replace(['B', 'C', 'L', 'S', 'W', 'F', '0', '1', '2', '+', '-'], '', $line) != '') goto set_mode;
		$flags = (object)[
			'basic_replace' => str_contains($line, 'B'),
			'basic_remove' => str_contains($line, 'C'),
			'language_replace' => str_contains($line, 'L'),
			'RemoveDoubleSpace' => str_contains($line, 'S'),
			'RemoveWhitespaceEOL' => str_contains($line, 'W'),
			'RemoveWhitespaceEOF' => str_contains($line, 'F'),
			'ChineseToPinYin' => str_contains($line, '0'),
			'HiragamaToRomaji' => str_contains($line, '1'),
			'KatakanaToRomaji' => str_contains($line, '2'),
			'UpperCase' => str_contains($line, '+'),
			'LowerCase' => str_contains($line, '-'),
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

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$extensions = $this->core->get_input_extensions(" Extensions: ");
		if($extensions === false) return false;

		$this->core->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->core->get_input(" Name filter: ");
		if($line == '#') return false;
		if(empty($line)){
			$filters = null;
		} else {
			$filters = explode(" ", $line);
		}

		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $extensions, null, $filters);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$content = file_get_contents($file);
				$original = $content;
				if($flags->basic_replace || $flags->language_replace || $flags->HiragamaToRomaji || $flags->KatakanaToRomaji){
					$content = $converter->convert($content);
				}
				if($flags->basic_remove){
					$content = $converter->clean($content);
				}
				if($flags->ChineseToPinYin){
					$content = $converter->string_to_pin_yin($content);
				}
				if($flags->UpperCase){
					$content = mb_strtoupper($content);
				} elseif($flags->LowerCase){
					$content = mb_strtolower($content);
				}
				if($flags->basic_replace){
					$content = str_replace(',', ', ', $content);
				}
				if($flags->RemoveDoubleSpace){
					$content = $converter->remove_double_spaces($content);
				}
				if($flags->RemoveWhitespaceEOL){
					$bom = $this->core->has_utf8_bom($content) ? $this->core->utf8_bom : "";
					if(!empty($bom)){
						$content = str_replace($bom, "", $content);
					}
					$eol = $this->core->detect_eol($content);
					$lines = explode($eol, $content);
					foreach($lines as &$line){
						$line = rtrim($line);
					}
					$content = $bom.implode($eol, $lines);
				}
				if($flags->RemoveWhitespaceEOF){
					$content = rtrim($content);
				}
				if($content != $original){
					file_put_contents($file, $content);
					$this->core->write_log("EDIT FILE \"$file\"");
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