<?php

declare(strict_types=1);

namespace NGC\Tools;

use Toolkit;

class FileFunctions {

	private string $name = "File Functions";
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
			' 0 - Anti Duplicates',
			' 1 - Validate CheckSum',
			' 2 - Random file generator',
			' 3 - Overwrite folders content',
			' 4 - Move files with structure',
			' 5 - Copy files with structure',
			' 6 - Clone files with structure (Mirror)',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_anti_duplicates();
			case '1': return $this->tool_validate_check_sum();
			case '2': return $this->tool_random_file_generator();
			case '3': return $this->tool_overwrite_folders_content();
			case '4': return $this->tool_move_files_with_structure();
			case '5': return $this->tool_copy_files_with_structure();
			case '6': return $this->tool_clone_files_with_structure();
		}
		return false;
	}

	public function tool_anti_duplicates() : bool {
		$this->core->set_subtool("Anti duplicates");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Modes:',
			' CheckSum Name   Action',
			' a1       b1     Rename',
			' a2       b2     Delete',
			' a3       b3     List',
		]);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'action' => strtolower($line[1] ?? '?'),
		];

		if(!in_array($this->params['mode'], ['a', 'b'])) goto set_mode;
		if(!in_array($this->params['action'], ['1', '2', '3'])) goto set_mode;

		$this->core->clear();
		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);

		$this->core->setup_folders($folders);

		$errors = 0;
		$this->core->set_errors($errors);

		$keys = [];
		$except_files = explode(";", $this->core->config->get('IGNORE_VALIDATE_FILES'));
		$except_extensions = explode(" ", $this->core->config->get('IGNORE_VALIDATE_EXTENSIONS'));
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$extension = strtolower(pathinfo($folder, PATHINFO_EXTENSION));
			if(is_file($folder)){
				$this->core->get_hash_from_idx($folder, $keys, true);
				$this->core->set_folder_done($folder);
				continue;
			}
			$files = $this->core->get_files($folder, null, $except_extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				if(in_array(strtolower(pathinfo($file, PATHINFO_BASENAME)), $except_files)) continue;
				$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				if($extension == 'idx' && $this->core->config->get('LOAD_IDX_CHECKSUM')){
					$this->core->get_hash_from_idx($file, $keys, false);
					continue 1;
				}
				if($this->params['mode'] == 'a'){
					$key = hash_file('md5', $file, false);
				} else {
					$key = pathinfo($file, PATHINFO_FILENAME);
				}
				if(isset($keys[$key])){
					$duplicate = $keys[$key];
					$this->core->write_error("DUPLICATE \"$file\" OF \"$duplicate\"");
					$errors++;
					if($this->params['action'] == '1'){
						if(!$this->core->rename($file, "$file.tmp")) $errors++;
					} else if($this->params['action'] == '2'){
						if(!$this->core->delete($file)) $errors++;
					}
				} else {
					$keys[$key] = $file;
				}
				$this->core->progress($items, $total);
				$this->core->set_errors($errors);
			}
			$this->core->progress($items, $total);
			unset($files);
			$this->core->set_folder_done($folder);
		}

		unset($keys);

		$this->core->open_logs($this->params['action'] != '3');
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_validate_check_sum() : bool {
		$this->core->set_subtool("Validate checksum");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Modes:',
			' 0   - From file',
			' 1   - From name',
			' ?0  - md5 (default)',
			' ?1  - sha256',
			' ?2  - crc32',
			' ?3  - whirlpool',
		]);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'algo' => strtolower($line[1] ?? '0'),
		];

		if($this->params['algo'] == '?') $this->params['algo'] = '0';

		if(!in_array($this->params['mode'], ['0', '1'])) goto set_mode;
		if(!in_array($this->params['algo'], ['0', '1', '2', '3'])) goto set_mode;

		$this->core->clear();
		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		$this->core->setup_folders($folders);

		$algo = $this->core->get_hash_alghoritm(intval($this->params['algo']));

		$errors = 0;
		$this->core->set_errors($errors);
		$except_files = explode(";", $this->core->config->get('IGNORE_VALIDATE_FILES'));
		$except_extensions = explode(" ", $this->core->config->get('IGNORE_VALIDATE_EXTENSIONS'));
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, null, $except_extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				if(in_array(strtolower(pathinfo($file, PATHINFO_BASENAME)), $except_files)) continue;
				$file_name = strtolower(pathinfo($file, PATHINFO_FILENAME));
				$hash = hash_file($algo['name'], $file, false);
				if($this->params['mode'] == '0'){
					$checksum_file = "$file.".$algo['name'];
					if(!file_exists($checksum_file)){
						$this->core->write_error("FILE NOT FOUND \"$checksum_file\"");
						$errors++;
					} else {
						$hash_current = strtolower(trim(file_get_contents($checksum_file)));
						if($hash_current != $hash){
							$this->core->write_error("INVALID FILE CHECKSUM \"$file\" current: $hash expected: $hash_current");
							$errors++;
						} else {
							$this->core->write_log("FILE \"$file\" checksum: $hash");
						}
					}
				} else {
					$len = strlen($file_name);
					if($len < $algo['length']){
						$this->core->write_error("INVALID FILE NAME \"$file\"");
						$errors++;
					} else {
						if($len > $algo['length']){
							$start = strpos($file_name, '[');
							if($start !== false){
								$end = strpos($file_name, ']', $start);
								$file_name = str_replace(' '.substr($file_name, $start, $end - $start + 1), '', $file_name);
							}
							$file_name = substr($file_name, intval(strlen($file_name) - $algo['length']), $algo['length']);
						}
						if($file_name != $hash){
							$this->core->write_error("INVALID FILE CHECKSUM \"$file\" current: $hash expected: $file_name");
							$errors++;
						} else {
							$this->core->write_log("FILE \"$file\" checksum: $hash");
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

	public function tool_random_file_generator() : bool {
		$this->core->set_subtool("Random file generator");

		$write_buffer = $this->core->get_write_buffer();
		if(!$write_buffer) return false;

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Modes:',
			' 0 - Single file',
			' 1 - Multiple files (size for one)',
			' 2 - Multiple files (size for all)',
		]);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
		];

		if(!in_array($this->params['mode'], ['0', '1', '2'])) goto set_mode;

		$bytes = $this->core->get_input_bytes_size(" Size: ");
		if(!$bytes) return false;

		if(in_array($this->params['mode'], ['1', '2'])){
			$quantity = $this->core->get_input_integer(" Quantity: ");
			if(!$quantity) return false;
		} else {
			$quantity = 1;
		}

		set_output:
		$this->core->clear();
		$line = $this->core->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		switch($this->params['mode']){
			case '0': {
				$this->core->print_help([" Creating single file of size ".$this->core->format_bytes($bytes, 0)]);
				$per_file_size = $bytes;
				break;
			}
			case '1': {
				$this->core->print_help([" Creating $quantity files of size ".$this->core->format_bytes($bytes, 0)." in total ".$this->core->format_bytes($bytes * $quantity, 0)]);
				$per_file_size = $bytes;
				break;
			}
			case '2': {
				$this->core->print_help([" Creating $quantity files of size ".$this->core->format_bytes(intval(floor($bytes / $quantity)), 0)." in total ".$this->core->format_bytes($bytes, 0)]);
				$per_file_size = intval(floor($bytes / $quantity));
				break;
			}
		}

		$small_mode = $per_file_size < $write_buffer;
		$size_text = $this->core->format_bytes($per_file_size);
		for($i = 1; $i <= $quantity; $i++){
			$file_path = $this->core->get_path($output."/NGC-TOOLKIT-".hash('md5', uniqid().$i).".tmp");
			if(file_exists($file_path)) $this->core->delete($file_path);
			$fp = fopen($file_path, "w");
			if($small_mode){
				echo " Files: $i / $quantity                                       \r";
			} else {
				echo " Files: $i / $quantity Progress: 0.00 %                      \r";
			}
			if($fp){
				$this->core->write_log("FILE CREATE WITH DISK ALLOCATION \"$file_path\" Size: $size_text");
				fseek($fp, $per_file_size - 1);
				fwrite($fp, "\0");
				fclose($fp);
				$fp = fopen($file_path, "r+w");
				fseek($fp, 0);
				$bytes_needle = $per_file_size;
				$current_size = 0;
				while($bytes_needle > 0){
					$percent = sprintf("%.02f", ($current_size / $per_file_size) * 100.0);
					if($small_mode){
						echo " Files: $i / $quantity                                           \r";
					} else {
						echo " Files: $i / $quantity Progress: $percent %                      \r";
					}
					if($bytes_needle > $write_buffer){
						$current_size += $write_buffer;
						$buffer = '';
						for($si = 0; $si < $write_buffer; $si++){
							$buffer .= chr(rand(0, 255));
						}
						fwrite($fp, $buffer, $write_buffer);
						$bytes_needle -= $write_buffer;
					} else {
						$current_size += $bytes_needle;
						$buffer = '';
						for($si = 0; $si < $bytes_needle; $si++){
							$buffer .= chr(rand(0, 255));
						}
						fwrite($fp, $buffer, $bytes_needle);
						$bytes_needle = 0;
					}
				}
				if($small_mode){
					echo " Files: $i / $quantity                                           \r";
				} else {
					echo " Files: $i / $quantity Progress: 100.00 %                        \r";
				}
				fclose($fp);
				$this->core->write_log("FILE CREATION FINISH \"$file_path\"");
			} else {
				$this->core->write_error("FAILED CREATE FILE \"$file_path\"");
			}
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_overwrite_folders_content() : bool {
		$this->core->clear();
		$this->core->set_subtool("Overwrite folders content");

		$write_buffer = $this->core->get_write_buffer();
		if(!$write_buffer) return false;

		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$bytes_needle = filesize($file);
				$current_size = 0;
				$fp = fopen($file, "r+w");
				if(!$fp){
					$this->core->write_error("FILE OVERWRITE FAILED \"$file\"");
					$errors++;
				} else {
					$this->core->write_log("FILE OVERWRITE START \"$file\"");
					fseek($fp, 0);
					while($bytes_needle > 0){
						if($bytes_needle > $write_buffer){
							$current_size += $write_buffer;
							$buffer = '';
							for($si = 0; $si < $write_buffer; $si++){
								$buffer .= chr(rand(0, 255));
							}
							fwrite($fp, $buffer, $write_buffer);
							$bytes_needle -= $write_buffer;
						} else {
							$current_size += $bytes_needle;
							$buffer = '';
							for($si = 0; $si < $bytes_needle; $si++){
								$buffer .= chr(rand(0, 255));
							}
							fwrite($fp, $buffer, $bytes_needle);
							$bytes_needle = 0;
						}
					}
					fclose($fp);
					$this->core->write_log("FILE OVERWRITE END \"$file\"");
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

	public function tool_move_files_with_structure() : bool {
		$this->core->clear();
		$this->core->set_subtool("Move files with structure");

		set_input:
		$line = $this->core->get_input(" Input: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->core->echo(" Invalid input folder");
			goto set_input;
		}

		set_output:
		$line = $this->core->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		$this->core->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->core->get_input(" Extensions: ");
		if($line == '#') return false;
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->core->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->core->get_input(" Name filter: ");
		if($line == '#') return false;
		if(empty($line)){
			$filters = null;
		} else {
			$filters = explode(" ", $line);
		}

		$errors = 0;
		$this->core->set_errors($errors);

		$files = $this->core->get_files($input, $extensions, null, $filters);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$new_name = str_ireplace($input, $output, $file);
			if(file_exists($new_name)){
				$this->core->write_error("FILE ALREADY EXISTS \"$new_name\"");
				$errors++;
			} else {
				if(!$this->core->rename($file, $new_name)){
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

	public function tool_copy_files_with_structure() : bool {
		$this->core->clear();
		$this->core->set_subtool("Copy files with structure");

		set_input:
		$line = $this->core->get_input(" Input: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->core->echo(" Invalid input folder");
			goto set_input;
		}

		set_output:
		$line = $this->core->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		$this->core->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->core->get_input(" Extensions: ");
		if($line == '#') return false;
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->core->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->core->get_input(" Name filter: ");
		if($line == '#') return false;
		if(empty($line)){
			$filters = null;
		} else {
			$filters = explode(" ", $line);
		}

		$errors = 0;
		$this->core->set_errors($errors);

		$files = $this->core->get_files($input, $extensions, null, $filters);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$new_name = str_ireplace($input, $output, $file);
			if(file_exists($new_name)){
				$this->core->write_error("FILE ALREADY EXISTS \"$new_name\"");
				$errors++;
			} else {
				if(!$this->core->copy($file, $new_name)){
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

	public function tool_clone_files_with_structure() : bool {
		$this->core->set_subtool("Clone files with structure");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Checksum algorithm:',
			' 0  - md5 (default)',
			' 1  - sha256',
			' 2  - crc32',
			' 3  - whirlpool',
		]);

		$line = $this->core->get_input(" Algorithm: ");
		if($line == '#') return false;

		$this->params = [
			'algo' => strtolower($line[0] ?? '0'),
		];

		if(!in_array($this->params['algo'], ['0', '1', '2', '3'])) goto set_mode;

		$this->core->clear();

		set_input:
		$line = $this->core->get_input(" Input: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->core->echo(" Invalid input folder");
			goto set_input;
		}

		set_output:
		$line = $this->core->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		$errors = 0;
		$this->core->set_errors($errors);

		$algo = $this->core->get_hash_alghoritm(intval($this->params['algo']))['name'];

		$this->core->echo(" Delete not existing files on output");
		$files = $this->core->get_files($output);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			$new_name = str_ireplace($output, $input, $file);
			if(!file_exists($new_name)){
				if(!$this->core->delete($file)){
					$errors++;
				}
			}
		}
		$this->core->progress($items, $total);

		$this->core->echo(" Delete not existing folders on output");
		$files = $this->core->get_folders($output);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			$new_name = str_ireplace($output, $input, $file);
			if(!file_exists($new_name)){
				if(!$this->core->rmdir($file)){
					$errors++;
				}
			}
		}
		$this->core->progress($items, $total);

		$this->core->echo(" Clone folder structure");
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

		$this->core->echo(" Clone new/changed files");
		$files = $this->core->get_files($input);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$new_name = str_ireplace($input, $output, $file);
			if(file_exists($new_name)){
				if(file_exists("$file.$algo")){
					$hash_input = file_get_contents("$file.$algo");
				} else {
					$hash_input = hash_file($algo, $file);
				}
				if(file_exists("$new_name.$algo")){
					$hash_output = file_get_contents("$new_name.$algo");
				} else {
					$hash_output = hash_file($algo, $new_name);
				}
				if($hash_input != $hash_output){
					if(!$this->core->delete($new_name)){
						$errors++;
					} else if(!$this->core->copy($file, $new_name)){
						$errors++;
					}
				}
			} else {
				if(!$this->core->copy($file, $new_name)){
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