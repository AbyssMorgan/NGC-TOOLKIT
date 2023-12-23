<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use Exception;
use App\Services\StringConverter;

class FileEditor {

	private string $name = "File Editor";

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
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolReplaceKeywordsInFiles();
			case '1': return $this->ToolRemoveKeywordsInFiles();
			case '2': return $this->ToolRemoveDuplicateLinesInFile();
			case '3': return $this->ToolSplitFileByLinesCount();
			case '4': return $this->ToolSplitFileBySize();
			case '5': return $this->ToolReverseFileLines();
			case '6': return $this->ToolPrettyFileContent();
		}
		return false;
	}

	public function ToolReplaceKeywordsInFiles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Replace keywords in files");

		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);

		$this->ave->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->ave->get_input(" Extensions: ");
		if($line == '#') return false;
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		set_keyword_file:
		$replacements = [];
		$line = $this->ave->get_input(" Keywords file: ");
		if($line == '#') return false;
		$line = $this->ave->get_input_folders($line);
		if(!isset($line[0])) goto set_keyword_file;
		$input = $line[0];

		if(!file_exists($input) || is_dir($input)){
			$this->ave->echo(" Invalid keywords file");
			goto set_keyword_file;
		}

		$fp = fopen($input, 'r');
		if(!$fp){
			$this->ave->echo(" Failed open keywords file");
			goto set_keyword_file;
		}
		$i = 0;
		$errors = 0;
		while(($line = fgets($fp)) !== false){
			$i++;
			$line = str_replace(["\n", "\r", "\xEF\xBB\xBF"], "", $line);
			if(empty(trim($line))) continue;
			$replace = $this->ave->get_input_folders($line, false);
			if(!isset($replace[0]) || !isset($replace[1]) || isset($replace[2])){
				$this->ave->echo(" Failed parse replacement in line $i content: '$line'");
				$errors++;
			} else {
				$replacements[$replace[0]] = $replace[1];
			}
		}
		fclose($fp);

		if($errors > 0){
			if(!$this->ave->get_confirm(" Errors detected, continue with valid replacement (Y/N): ")) goto set_keyword_file;
		}

		$this->ave->setup_folders($folders);
		$errors = 0;
		$this->ave->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->get_files($folder, $extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				try {
					$content = file_get_contents($file);
					$new_content = str_replace(array_keys($replacements), $replacements, $content);
					$changed = ($content != $new_content);
					unset($content);
					if($changed){
						file_put_contents($file, $new_content);
						$this->ave->write_log("EDIT FILE \"$file\"");
					}
				}
				catch(Exception $e){
					$this->ave->write_error("FAILED EDIT FILE \"$file\" ERROR:".$e->getMessage());
					$errors++;
				}
				$this->ave->progress($items, $total);
				$this->ave->set_errors($errors);
			}
			$this->ave->progress($items, $total);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolRemoveKeywordsInFiles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Remove keywords in files");

		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);

		$this->ave->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->ave->get_input(" Extensions: ");
		if($line == '#') return false;
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		set_keyword_file:
		$keywords = [];
		$line = $this->ave->get_input(" Keywords file: ");
		if($line == '#') return false;
		$line = $this->ave->get_input_folders($line);
		if(!isset($line[0])) goto set_keyword_file;
		$input = $line[0];

		if(!file_exists($input) || is_dir($input)){
			$this->ave->echo(" Invalid keywords file");
			goto set_keyword_file;
		}

		$fp = fopen($input, 'r');
		if(!$fp){
			$this->ave->echo(" Failed open keywords file");
			goto set_keyword_file;
		}
		while(($line = fgets($fp)) !== false){
			$line = str_replace(["\n", "\r", "\xEF\xBB\xBF"], "", $line);
			if(empty(trim($line))) continue;
			array_push($keywords, $line);
		}
		fclose($fp);

		$this->ave->setup_folders($folders);
		$errors = 0;
		$this->ave->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->get_files($folder, $extensions);
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
						$this->ave->write_log("EDIT FILE \"$file\"");
					}
				}
				catch(Exception $e){
					$this->ave->write_error("FAILED EDIT FILE \"$file\" ERROR:".$e->getMessage());
					$errors++;
				}
				$this->ave->progress($items, $total);
				$this->ave->set_errors($errors);
			}
			$this->ave->progress($items, $total);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolRemoveDuplicateLinesInFile() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Remove duplicate lines in file");

		set_input:
		$line = $this->ave->get_input(" File: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$file = $folders[0];

		if(!file_exists($file) || is_dir($file)){
			$this->ave->echo(" Invalid input file");
			goto set_input;
		}

		if(!$this->ave->is_text_file($file)){
			if(!$this->ave->get_confirm(" The file does not appear to be a text file, continue (Y/N): ")) goto set_input;
		}

		$ignore_empty_lines = $this->ave->get_confirm(" Ignore empty lines comparison (Y/N): ");

		$duplicates = 0;

		try {
			$content = file_get_contents($file);
			$bom = (strpos($content, "\xEF\xBB\xBF") !== false) ? "\xEF\xBB\xBF" : "";
			if(!empty($bom)){
				$content = str_replace($bom, "" ,$content);
			}
			if(strpos($content, "\r\n") !== false){
				$eol = "\r\n";
			} else if(strpos($content, "\n") !== false){
				$eol = "\n";
			} else {
				$eol = "\r";
			}
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
				$this->ave->echo(" Removed $duplicates lines in \"$file\"");
			}
		}
		catch(Exception $e){
			$this->ave->echo(" Failed edit \"$file\" Error:".$e->getMessage());
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolSplitFileByLinesCount() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Split file by lines count");

		$lines_limit = $this->ave->get_input_integer(" Lines limit: ");
		if(!$lines_limit) return false;

		set_input:
		$line = $this->ave->get_input(" File: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$file = $folders[0];

		if(!file_exists($file) || is_dir($file)){
			$this->ave->echo(" Invalid input file");
			goto set_input;
		}

		if(!$this->ave->is_text_file($file)){
			if(!$this->ave->get_confirm(" The file does not appear to be a text file, continue (Y/N): ")) goto set_input;
		}

		$fp = fopen($file, 'r');
		if(!$fp){
			$this->ave->echo(" Failed open input file");
			goto set_input;
		}

		$first_line = fgets($fp);
		fseek($fp, 0);

		$utf8_bom = (strpos($first_line, "\xEF\xBB\xBF") !== false);
		if(strpos($first_line, "\r\n") !== false){
			$eol = "\r\n";
		} else if(strpos($first_line, "\n") !== false){
			$eol = "\n";
		} else if(strpos($first_line, "\r") !== false){
			$eol = "\r";
		} else {
			fclose($fp);
			$this->ave->echo(" The selected file has no newlines");
			goto set_input;
		}

		$count = 0;
		$part_id = 1;
		$out = false;
		while(($line = fgets($fp)) !== false){
			$line = str_replace(["\n", "\r", "\xEF\xBB\xBF"], "", $line);
			if($count % $lines_limit == 0){
				if($out){
					fclose($out);
					$out = false;
				}
				$output_file = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/".pathinfo($file, PATHINFO_FILENAME)."_".sprintf("%06d", $part_id).".".pathinfo($file, PATHINFO_EXTENSION));
				if(file_exists($output_file)) $this->ave->delete($output_file);
				$out = fopen($output_file, 'w');
				if(!$out){
					$this->ave->write_error("FAILED OPEN FILE \"$output_file\"");
					break;
				} else {
					$this->ave->write_log("CREATE FILE \"$output_file\"");
				}
				if($utf8_bom) fwrite($out, "\xEF\xBB\xBF");
				$part_id++;
			}
			fwrite($out, $line.$eol);
			$count++;
		}
		if($out){
			fclose($out);
			$out = false;
		}
		fclose($fp);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolSplitFileBySize() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Split file by size");

		$bytes = $this->ave->get_input_bytes_size(" Size: ");
		if(!$bytes) return false;

		set_input:
		$line = $this->ave->get_input(" File: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$file = $folders[0];

		if(!file_exists($file) || is_dir($file)){
			$this->ave->echo(" Invalid input file");
			goto set_input;
		}

		$fp = fopen($file, 'r');
		if(!$fp){
			$this->ave->echo(" Failed open input file");
			goto set_input;
		}

		$part_id = 1;
		while(!feof($fp)){
			$buffer = fread($fp, $bytes);
			$output_file = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/".pathinfo($file, PATHINFO_FILENAME)."_".sprintf("%06d", $part_id).".".pathinfo($file, PATHINFO_EXTENSION));
			if(file_exists($output_file)) $this->ave->delete($output_file);
			$out = fopen($output_file, 'w');
			if(!$out){
				$this->ave->write_error("FAILED OPEN FILE \"$output_file\"");
				break;
			} else {
				$this->ave->write_log("CREATE FILE \"$output_file\"");
			}
			fwrite($out, $buffer, strlen(bin2hex($buffer)) / 2);
			fclose($out);
			$part_id++;
		}
		fclose($fp);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolReverseFileLines() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Reverse file lines");

		set_input:
		$line = $this->ave->get_input(" File: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$file = $folders[0];

		if(!file_exists($file) || is_dir($file)){
			$this->ave->echo(" Invalid input file");
			goto set_input;
		}

		if(!$this->ave->is_text_file($file)){
			if(!$this->ave->get_confirm(" The file does not appear to be a text file, continue (Y/N): ")) goto set_input;
		}

		try {
			$content = file_get_contents($file);
			$bom = (strpos($content, "\xEF\xBB\xBF") !== false) ? "\xEF\xBB\xBF" : "";
			if(!empty($bom)){
				$content = str_replace($bom, "" ,$content);
			}
			if(strpos($content, "\r\n") !== false){
				$eol = "\r\n";
			} else if(strpos($content, "\n") !== false){
				$eol = "\n";
			} else {
				$eol = "\r";
			}
			$lines = array_reverse(explode($eol, $content));
			$new_content = $bom.implode($eol, $lines);
			$changed = ($content != $new_content);
			unset($content);
			if($changed){
				file_put_contents($file, $new_content);
				$this->ave->echo(" Reversed file lines in \"$file\"");
			}
		}
		catch(Exception $e){
			$this->ave->echo(" Failed edit \"$file\" Error:".$e->getMessage());
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolPrettyFileContent() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Pretty file content");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Flags (type in one line, default BC):',
			' B   - Basic replacement',
			' C   - Basic remove',
			' L   - Replace language characters',
			' S   - Remove double spaces',
			' W   - Remove whitespace characters on EOL',
			' 0   - Chinese to PinYin',
			' 1   - Hiragama to Romaji',
			' 2   - Katakana to Romaji',
			' +   - To upper case',
			' -   - To lower case',
		]);

		$line = strtoupper($this->ave->get_input(" Flags: "));
		if($line == '#') return false;
		if(empty($line)) $line = 'BC';
		if(str_replace(['B', 'C', 'L', 'S', 'W', '0', '1', '2', '+', '-'], '', $line) != '') goto set_mode;
		$flags = (object)[
			'basic_replace' => (strpos($line, 'B') !== false),
			'basic_remove' => (strpos($line, 'C') !== false),
			'language_replace' => (strpos($line, 'L') !== false),
			'RemoveDoubleSpace' => (strpos($line, 'S') !== false),
			'RemoveWhitespaceEOL' => (strpos($line, 'W') !== false),
			'ChineseToPinYin' => (strpos($line, '0') !== false),
			'HiragamaToRomaji' => (strpos($line, '1') !== false),
			'KatakanaToRomaji' => (strpos($line, '2') !== false),
			'UpperCase' => (strpos($line, '+') !== false),
			'LowerCase' => (strpos($line, '-') !== false),
		];
		$converter = new StringConverter();
		if($flags->language_replace){
			$converter->importReplacement($this->ave->get_file_path($this->ave->path."/includes/data/LanguageReplacement.ini"));
		}
		if($flags->ChineseToPinYin){
			$converter->importPinYin($this->ave->get_file_path($this->ave->path."/includes/data/PinYin.ini"));
		}
		if($flags->HiragamaToRomaji){
			$converter->importReplacement($this->ave->get_file_path($this->ave->path."/includes/data/Hiragama.ini"));
		}
		if($flags->KatakanaToRomaji){
			$converter->importReplacement($this->ave->get_file_path($this->ave->path."/includes/data/Katakana.ini"));
		}
		$this->ave->clear();
		
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		$this->ave->setup_folders($folders);

		$this->ave->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->ave->get_input(" Extensions: ");
		if($line == '#') return false;
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->ave->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->ave->get_input(" Name filter: ");
		if($line == '#') return false;
		if(empty($line)){
			$filters = null;
		} else {
			$filters = explode(" ", $line);
		}

		$errors = 0;
		$this->ave->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->get_files($folder, $extensions, null, $filters);
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
					$content = $converter->stringToPinYin($content);
				}
				if($flags->UpperCase){
					$content = mb_strtoupper($content);
				} else if($flags->LowerCase){
					$content = mb_strtolower($content);
				}
				if($flags->basic_replace){
					$content = str_replace(',', ', ', $content);
				}
				if($flags->RemoveDoubleSpace){
					$content = $converter->remove_double_spaces($content);
				}
				if($flags->RemoveWhitespaceEOL){
					$bom = (strpos($content, "\xEF\xBB\xBF") !== false) ? "\xEF\xBB\xBF" : "";
					if(!empty($bom)){
						$content = str_replace($bom, "" ,$content);
					}
					if(strpos($content, "\r\n") !== false){
						$eol = "\r\n";
					} else if(strpos($content, "\n") !== false){
						$eol = "\n";
					} else {
						$eol = "\r";
					}
					$lines = explode($eol, $content);
					foreach($lines as &$line){
						$line = rtrim($line);
					}
					$content = $bom.implode($eol, $lines);
				}
				if($content != $original){
					file_put_contents($file, $content);
					$this->ave->write_log("EDIT FILE \"$file\"");
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

}

?>
