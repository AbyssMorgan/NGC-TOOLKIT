<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;

class FileFunctions {

	private string $name = "FileFunctions";

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
			' 0 - Anti Duplicates',
			' 1 - Extension Change',
			' 2 - Validate CheckSum',
			' 3 - Random file generator',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolAntiDuplicates();
			case '1': return $this->ToolExtensionChange();
			case '2': return $this->ToolValidateCheckSum();
			case '3': return $this->ToolRandomFileGenerator();
		}
		return false;
	}

	public function ToolAntiDuplicates() : bool {
		$this->ave->set_subtool("AntiDuplicates");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Modes:',
			' CheckSum Name   Action',
			' a1       b1     Rename',
			' a2       b2     Delete',
		]);

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'action' => strtolower($line[1] ?? '?'),
		];

		if(!in_array($this->params['mode'], ['a', 'b'])) goto set_mode;
		if(!in_array($this->params['action'], ['1', '2'])) goto set_mode;

		$this->ave->clear();
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		$keys = [];
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$extension = strtolower(pathinfo($folder, PATHINFO_EXTENSION));
			if(is_file($folder)){
				$progress += $this->ave->getHashFromIDX($folder, $keys, true);
				$this->ave->set_progress($progress, $errors);
				$this->ave->set_folder_done($folder);
				continue;
			}
			$files = $this->ave->getFiles($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				if($extension == 'idx' && $this->ave->config->get('AVE_LOAD_IDX_CHECKSUM')){
					$progress += $this->ave->getHashFromIDX($file, $keys, false);
					$this->ave->set_progress($progress, $errors);
					continue 1;
				}
				if($this->params['mode'] == 'a'){
					$key = hash_file('md5', $file, false);
				} else {
					$key = pathinfo($file, PATHINFO_FILENAME);
				}
				$progress++;
				if(isset($keys[$key])){
					$duplicate = $keys[$key];
					$this->ave->write_error("DUPLICATE \"$file\" OF \"$duplicate\"");
					$errors++;
					if($this->params['action'] == '2'){
						if(!$this->ave->unlink($file)) $errors++;
					} else {
						if(!$this->ave->rename($file, "$file.tmp")) $errors++;
					}
				} else {
					$keys[$key] = $file;
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			$this->ave->progress($items, $total);
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		unset($keys);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolExtensionChange() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ExtensionChange");
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);

		$extension_old = strtolower($this->ave->get_input(" Extension old: "));
		if($extension_old == '#') return false;

		$extension_new = $this->ave->get_input(" Extension new: ");
		if($extension_new == '#') return false;

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder, [$extension_old]);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$new_name = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/".pathinfo($file, PATHINFO_FILENAME).".$extension_new");
				if($this->ave->rename($file, $new_name)){
					$progress++;
				} else {
					$errors++;
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			$this->ave->progress($items, $total);
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolValidateCheckSum() : bool {
		$this->ave->set_subtool("ValidateCheckSum");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Modes:',
			' 0   - From file',
			' 1   - From name',
			' ?0  - md5 (default)',
			' ?1  - sha256',
			' ?2  - crc32',
			' ?3  - whirlpool',
		]);

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'algo' => strtolower($line[1] ?? '0'),
		];

		if($this->params['algo'] == '?') $this->params['algo'] = '0';

		if(!in_array($this->params['mode'], ['0', '1'])) goto set_mode;
		if(!in_array($this->params['algo'], ['0', '1', '2', '3'])) goto set_mode;

		$this->ave->clear();
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$algo = $this->ToolValidateCheckSumAlgoName($this->params['algo']);
		$algo_length = $this->ToolValidateCheckSumAlgoLength($this->params['algo']);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		$except_files = explode(";", $this->ave->config->get('AVE_IGNORE_VALIDATE_FILES'));
		$except_extensions = explode(" ", $this->ave->config->get('AVE_IGNORE_VALIDATE_EXTENSIONS'));
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$file_id = 1;
			$list = [];
			$files = $this->ave->getFiles($folder, null, $except_extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$file_name = strtolower(pathinfo($file, PATHINFO_FILENAME));
				if(in_array(strtolower(pathinfo($file, PATHINFO_BASENAME)), $except_files)) continue;
				$hash = hash_file($algo, $file, false);
				$progress++;
				if($this->params['mode'] == '0'){
					$checksum_file = "$file.$algo";
					if(!file_exists($checksum_file)){
						$this->ave->write_error("FILE NOT FOUND \"$checksum_file\"");
						$errors++;
					} else {
						$hash_current = strtolower(trim(file_get_contents($checksum_file)));
						if($hash_current != $hash){
							$this->ave->write_error("INVALID FILE CHECKSUM \"$file\" current: $hash expected: $hash_current");
							$errors++;
						} else {
							$this->ave->write_log("FILE \"$file\" checksum: $hash");
						}
					}
				} else {
					$len = strlen($file_name);
					if($len < $algo_length){
						$this->ave->write_error("INVALID FILE NAME \"$file\"");
						$errors++;
					} else {
						if($len > $algo_length){
							$start = strpos($file_name, '[');
							if($start !== false){
								$end = strpos($file_name, ']', $start);
								$file_name = str_replace(' '.substr($file_name, $start, $end - $start + 1), '', $file_name);
							}
							$file_name = substr($file_name, strlen($file_name) - $algo_length, $algo_length);
						}
						if($file_name != $hash){
							$this->ave->write_error("INVALID FILE CHECKSUM \"$file\" current: $hash expected: $file_name");
							$errors++;
						} else {
							$this->ave->write_log("FILE \"$file\" checksum: $hash");
						}
					}
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			$this->ave->progress($items, $total);
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolValidateCheckSumAlgoName(string $mode) : string {
		switch($mode){
			case '0': return 'md5';
			case '1': return 'sha256';
			case '2': return 'crc32';
			case '3': return 'whirlpool';
		}
		return 'md5';
	}

	public function ToolValidateCheckSumAlgoLength(string $mode) : int {
		switch($mode){
			case '0': return 32;
			case '1': return 64;
			case '2': return 8;
			case '3': return 128;
		}
		return 32;
	}

	public function ToolRandomFileGenerator() : bool {
		$this->ave->set_subtool("RandomFileGenerator");

		$size = explode(' ', $this->ave->config->get('AVE_WRITE_BUFFER_SIZE'));
		$write_buffer = $this->ave->unitToBytes(intval($size[0]), $size[1] ?? '?');
		if($write_buffer <= 0){
			$this->ave->clear();
			$this->ave->pause(" Operation aborted: invalid config value for AVE_WRITE_BUFFER_SIZE=\"".$this->ave->config->get('AVE_WRITE_BUFFER_SIZE')."\", press enter to back to menu.");
			return false;
		}

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Modes:',
			' 0 - Single file',
			' 1 - Multiple files (size for one)',
			' 2 - Multiple files (size for all)',
		]);

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
		];

		if(!in_array($this->params['mode'], ['0', '1', '2'])) goto set_mode;

		set_size:
		$this->ave->clear();
		$this->ave->print_help([
			' Type integer and unit separate by space, example: 1 GB',
			' Size units: B, KB, MB, GB, TB',
		]);

		$line = $this->ave->get_input(" Size: ");
		if($line == '#') return false;
		$size = explode(' ', $line);
		if(!isset($size[1])) goto set_size;
		$size[0] = preg_replace('/\D/', '', $size[0]);
		if(empty($size[0])) goto set_size;
		if(!in_array(strtoupper($size[1]), ['KB', 'MB', 'GB', 'TB'])) goto set_size;
		$bytes = $this->ave->unitToBytes(intval($size[0]), $size[1]);
		if($bytes <= 0) goto set_size;

		if(in_array($this->params['mode'], ['1', '2'])){
			set_quantity:
			$line = $this->ave->get_input(" Quantity: ");
			if($line == '#') return false;
			$quantity = preg_replace('/\D/', '', $line);
			if(empty($quantity)) goto set_quantity;
			$quantity = intval($quantity);
			if($quantity < 1) goto set_quantity;
		} else {
			$quantity = 1;
		}

		set_output:
		$this->ave->clear();
		$line = $this->ave->get_input(" Folder: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			$this->ave->echo(" Invalid output folder");
			goto set_output;
		}

		switch($this->params['mode']){
			case '0': {
				$this->ave->print_help([" Creating single file of size ".$this->ave->formatBytes($bytes, 0)]);
				$per_file_size = $bytes;
				break;
			}
			case '1': {
				$this->ave->print_help([" Creating $quantity files of size ".$this->ave->formatBytes($bytes, 0)." in total ".$this->ave->formatBytes($bytes * $quantity, 0)]);
				$per_file_size = $bytes;
				break;
			}
			case '2': {
				$this->ave->print_help([" Creating $quantity files of size ".$this->ave->formatBytes(intval(floor($bytes / $quantity)), 0)." in total ".$this->ave->formatBytes($bytes, 0)]);
				$per_file_size = intval(floor($bytes / $quantity));
				break;
			}
		}

		$small_mode = $per_file_size < $write_buffer;
		$size_text = $this->ave->formatBytes($per_file_size);
		for($i = 1; $i <= $quantity; $i++){
			$file_path = $this->ave->get_file_path($output."/AVE-RANDOM-".hash('md5', uniqid().$i).".tmp");
			if(file_exists($file_path)) $this->ave->unlink($file_path);
			$fp = fopen($file_path, "w");
			if($small_mode){
				echo " Files: $i / $quantity                                       \r";
			} else {
				echo " Files: $i / $quantity Progress: 0.00 %                      \r";
			}
			if($fp){
				$this->ave->write_log("FILE CREATE WITH DISK ALLOCATION \"$file_path\" Size: $size_text");
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
				$this->ave->write_log("FILE CREATION FINISH \"$file_path\"");
			} else {
				$this->ave->write_error("FAILED CREATE FILE \"$file_path\"");
			}
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

}

?>
