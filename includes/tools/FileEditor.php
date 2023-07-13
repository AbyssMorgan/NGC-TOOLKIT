<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use Exception;

class FileEditor {

	private string $name = "FileEditor";

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
		}
		return false;
	}

	public function ToolReplaceKeywordsInFiles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ReplaceKeywordsInFiles");

		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);

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
		$line = $this->ave->get_folders($line);
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
			$replace = $this->ave->get_folders($line, false);
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
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder, $extensions);
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
						$progress++;
					}
				}
				catch(Exception $e){
					$this->ave->write_error("FAILED EDIT FILE \"$file\" ERROR:".$e->getMessage());
					$errors++;
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			$this->ave->progress($items, $total);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolRemoveKeywordsInFiles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("RemoveKeywordsInFiles");

		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);

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
		$line = $this->ave->get_folders($line);
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
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder, $extensions);
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
						$progress++;
					}
				}
				catch(Exception $e){
					$this->ave->write_error("FAILED EDIT FILE \"$file\" ERROR:".$e->getMessage());
					$errors++;
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			$this->ave->progress($items, $total);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolRemoveDuplicateLinesInFile() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("RemoveDuplicateLinesInFile");

		set_input:
		$line = $this->ave->get_input(" File: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$file = $folders[0];

		if(!file_exists($file) || is_dir($file)){
			$this->ave->echo(" Invalid input file");
			goto set_input;
		}

		if(!$this->ave->isTextFile($file)){
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
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolSplitFileByLinesCount() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SplitFileByLinesCount");

		set_lines_limit:
		$line = $this->ave->get_input(" Lines limit: ");
		if($line == '#') return false;
		$lines_limit = intval(preg_replace('/\D/', '', $line));
		if($lines_limit <= 0) goto set_lines_limit;

		set_input:
		$line = $this->ave->get_input(" File: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$file = $folders[0];

		if(!file_exists($file) || is_dir($file)){
			$this->ave->echo(" Invalid input file");
			goto set_input;
		}

		if(!$this->ave->isTextFile($file)){
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
				if(file_exists($output_file)) $this->ave->unlink($output_file);
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
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolSplitFileBySize() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SplitFileBySize");

		$bytes = $this->ave->get_size(" Size: ");
		if(!$bytes) return false;

		set_input:
		$line = $this->ave->get_input(" File: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
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
			if(file_exists($output_file)) $this->ave->unlink($output_file);
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
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolReverseFileLines() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ReverseFileLines");

		set_input:
		$line = $this->ave->get_input(" File: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$file = $folders[0];

		if(!file_exists($file) || is_dir($file)){
			$this->ave->echo(" Invalid input file");
			goto set_input;
		}

		if(!$this->ave->isTextFile($file)){
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
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

}

?>
