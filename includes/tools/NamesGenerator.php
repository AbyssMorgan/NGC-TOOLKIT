<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;

class NamesGenerator {

	private string $name = "NamesGenerator";

	private array $params = [];
	private string $action;
	private AVE $ave;

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
	}

	public function help(){
		$this->ave->print_help([
			' Actions:',
			' 0 - Generate names: CheckSum',
			' 1 - Generate names: Number (Video/Images)',
			' 2 - Generate video: CheckSum/Resolution/Thumbnail',
			' 3 - Generate series name: S01E01 etc.',
			' 4 - Escape file name (WWW)',
		]);
	}

	public function action(string $action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_checksum_help();
			case '1': return $this->tool_number_help();
			case '2': return $this->tool_videogenerator_help();
			case '3': return $this->tool_generateseriesname_action();
			case '4': return $this->tool_escapefilenamewww_action();
		}
		$this->ave->select_action();
	}

	public function tool_checksum_help(){
		$this->ave->clear();
		$this->ave->set_subtool("CheckSum");

		$this->ave->print_help([
			' Modes:',
			' 0   - Normal           "<HASH>"',
			' 1   - CurrentName      "name <HASH>"',
			' 2   - DirectoryName    "dir_name <HASH>"',
			' 3   - DirectoryNameEx  "dir_name DDDD <HASH>"',
			' 4   - DateName         "YYYY.MM.DD <HASH>"',
			' 5   - DateNameEx       "YYYY.MM.DD DDDD <HASH>"',
			' 6   - NumberFour       "DDDD <HASH>"',
			' 7   - NumberSix        "DDDDDD <HASH>"',
			' ?0  - md5 (default)',
			' ?1  - sha256',
			' ?2  - crc32',
			' ?3  - whirlpool',
			' ??l - List only',
		]);

		echo " Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'algo' => strtolower($line[1] ?? '0'),
			'list_only' => strtolower(($line[2] ?? '?')) == 'l',
		];

		if($this->params['algo'] == '?') $this->params['algo'] = '0';

		if(!in_array($this->params['mode'],['0','1','2','3','4','5','6','7'])) return $this->tool_checksum_help();
		if(!in_array($this->params['algo'],['0','1','2','3'])) return $this->tool_checksum_help();
		$this->ave->set_subtool("CheckSum > ".$this->tool_checksum_name($this->params['mode'])." > ".$this->tool_checksum_algo($this->params['algo']));
		return $this->tool_cheksum_action();
	}

	public function tool_checksum_name(string $mode) : string {
		switch($mode){
			case '0': return 'Normal';
			case '1': return 'CurrentName';
			case '2': return 'DirectoryName';
			case '3': return 'DirectoryNameEx';
			case '4': return 'DateName';
			case '5': return 'DateNameEx';
			case '6': return 'NumberFour';
			case '7': return 'NumberSix';
		}
		return 'Unknown';
	}

	public function tool_checksum_algo(string $mode) : string {
		switch($mode){
			case '0': return 'md5';
			case '1': return 'sha256';
			case '2': return 'crc32';
			case '3': return 'whirlpool';
		}
		return 'md5';
	}

	public function tool_checksum_get_pattern(string $mode, string $file, string $hash, int $file_id) : string {
		$folder = pathinfo($file, PATHINFO_DIRNAME);
		$foldername = pathinfo($folder, PATHINFO_FILENAME);
		$name = pathinfo($file, PATHINFO_FILENAME);
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		if($this->ave->config->get('AVE_EXTENSION_TO_LOWER')) $extension = strtolower($extension);
		switch($mode){
			case '0': return $folder.DIRECTORY_SEPARATOR."$hash.$extension";
			case '1': return $folder.DIRECTORY_SEPARATOR."$name $hash.$extension";
			case '2': return $folder.DIRECTORY_SEPARATOR."$foldername $hash.$extension";
			case '3': return $folder.DIRECTORY_SEPARATOR."$foldername ".sprintf("%04d",$file_id)." $hash.$extension";
			case '4': return $folder.DIRECTORY_SEPARATOR.date("Y-m-d",filemtime($file))." $hash.$extension";
			case '5': return $folder.DIRECTORY_SEPARATOR.date("Y-m-d",filemtime($file))." ".sprintf("%04d",$file_id)." $hash.$extension";
			case '6': return $folder.DIRECTORY_SEPARATOR.sprintf("%04d",$file_id)." $hash.$extension";
			case '7': return $folder.DIRECTORY_SEPARATOR.sprintf("%06d",$file_id)." $hash.$extension";
		}
		return '';
	}

	public function tool_cheksum_action(){
		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->tool_checksum_help();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$algo = $this->tool_checksum_algo($this->params['algo']);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$file_id = 1;
			$list = [];
			$files = $this->ave->getFiles($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$hash = hash_file($algo, $file, false);
				if($this->ave->config->get('AVE_HASH_TO_UPPER')) $hash = strtoupper($hash);
				$new_name = $this->tool_checksum_get_pattern($this->params['mode'], $file, $hash, $file_id++);
				if($this->params['list_only']){
					array_push($list,$new_name);
					$progress++;
				} else {
					if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
						$this->ave->log_error->write("DUPLICATE \"$file\" AS \"$new_name\"");
						$errors++;
						if($this->ave->config->get('AVE_ACTION_AFTER_DUPLICATE') == 'DELETE'){
							if(!$this->ave->unlink($file)) $errors++;
						} else {
							if(!$this->ave->rename($file, "$file.tmp")) $errors++;
						}
					} else {
						if($this->ave->rename($file, $new_name)){
							$progress++;
						} else {
							$errors++;
						}
					}
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			if($this->params['list_only']){
				$count = count($list);
				$this->ave->log_event->write("Write $count items from \"$folder\" to data file");
				$this->ave->log_data->write($list);
			}
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

	public function tool_number_help(){
		$this->ave->clear();
		$this->ave->set_subtool("Number");

		$this->ave->print_help([
			'           Group Single Format                Range',
			' Normal    g0    s0    "PPP_DDDDDD"           000001 - 999999',
			' Part      g1    s1    "III\PPP_DDDDDD"       000001 - 999999',
			' Merge     g2    s2    "PPP_DDDDDD"           000001 - 999999',
			' DirName   g3    s3    "PPP_dir_name_DDDDDD"  000001 - 999999',
			' DirNameEx g4    s4    "PPP_dir_name_DDDD"    0001 -   9999',
			' Revert    g5    s5    "PPP_DDDDDD"           999999 - 000001',
			' NoPref    g6    s6    "DDDDDD"               000001 - 999999',
		]);

		echo " Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$this->params = [
			'type' => strtolower($line[0] ?? '?'),
			'mode' => strtolower($line[1] ?? '?'),
		];

		if(!in_array($this->params['type'],['s','g'])) return $this->tool_number_help();
		if(!in_array($this->params['mode'],['0','1','2','3','4','5','6'])) return $this->tool_number_help();
		$this->ave->set_subtool("Number > ".$this->tool_number_name($this->params['mode']));
		switch($this->params['type']){
			case 's': return $this->tool_number_action_single();
			case 'g': return $this->tool_number_action_group();
		}

	}

	public function tool_number_name(string $mode){
		switch($mode){
			case '0': return 'Normal';
			case '1': return 'Part';
			case '2': return 'Merge';
			case '3': return 'DirName';
			case '4': return 'DirNameEx';
			case '5': return 'Revert';
			case '6': return 'NoPref';
		}
		return 'Unknown';
	}

	public function tool_number_get_prefix_id() : string {
		return sprintf("%03d", random_int(0, 999));
	}

	public function tool_number_get_pattern(string $mode, string $file, string $prefix, int $file_id, string $input, int $part_id){
		$folder = pathinfo($file, PATHINFO_DIRNAME);
		$foldername = pathinfo($folder, PATHINFO_FILENAME);
		$name = pathinfo($file, PATHINFO_FILENAME);
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		if($this->ave->config->get('AVE_EXTENSION_TO_LOWER')) $extension = strtolower($extension);
		switch($mode){
			case '0': return $folder.DIRECTORY_SEPARATOR."$prefix".sprintf("%06d",$file_id).".$extension";
			case '1': return $input.DIRECTORY_SEPARATOR.sprintf("%03d",$part_id).DIRECTORY_SEPARATOR."$prefix".sprintf("%06d",$file_id).".$extension";
			case '2': return $input.DIRECTORY_SEPARATOR."$prefix".sprintf("%06d",$file_id).".$extension";
			case '3': return $folder.DIRECTORY_SEPARATOR."$prefix$foldername"."_".sprintf("%06d",$file_id).".$extension";
			case '4': return $folder.DIRECTORY_SEPARATOR."$prefix$foldername"."_".sprintf("%04d",$file_id).".$extension";
			case '5': return $folder.DIRECTORY_SEPARATOR."$prefix".sprintf("%06d",$file_id).".$extension";
			case '6': return $folder.DIRECTORY_SEPARATOR.sprintf("%06d",$file_id).".$extension";
		}
	}

	public function tool_number_action(string $folder, int &$progress, int &$errors){
		if(!file_exists($folder)) return false;
		$file_id = ($this->params['mode'] == 5) ? 999999 : 1;
		$prefix_id = $this->tool_number_get_prefix_id();
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$image_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		$files = $this->ave->getFiles($folder, array_merge($image_extensions, $video_extensions));
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
			$part_id = (int) floor($file_id / intval($this->ave->config->get('AVE_PART_SIZE'))) + 1;
			if($this->params['mode'] == 1){
				$prefix_id = sprintf("%03d",$part_id);
			}
			if(in_array($extension, $image_extensions)){
				$prefix = $this->ave->config->get('AVE_PREFIX_PHOTO')."_$prefix_id"."_";
			} else {
				$prefix = $this->ave->config->get('AVE_PREFIX_VIDEO')."_$prefix_id"."_";
			}
			$new_name = $this->tool_number_get_pattern($this->params['mode'], $file, $prefix, $file_id, $folder, $part_id);
			$directory = pathinfo($new_name, PATHINFO_DIRNAME);
			if(!file_exists($directory)){
				if(!$this->ave->mkdir($directory)){
					$errors++;
					continue;
				}
			}
			if($this->params['mode'] == 5){
				$file_id--;
			} else {
				$file_id++;
			}
			if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
				$this->ave->log_error->write("DUPLICATE \"$file\" AS \"$new_name\"");
				$errors++;
			} else {
				if($this->ave->rename($file, $new_name)){
					$progress++;
				} else {
					$errors++;
				}
			}
			unset($files);
			$this->ave->progress($items, $total);
			$this->ave->set_progress($progress, $errors);
		}
		return true;
	}

	public function tool_number_action_single(){
		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->tool_number_help();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		foreach($folders as $folder){
			$this->tool_number_action($folder, $progress, $errors);
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

	public function tool_number_action_group(){
		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->tool_number_help();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$subfolders = scandir($folder);
			foreach($subfolders as $subfoolder){
				if($subfoolder == '.' || $subfoolder == '..') continue;
				if(is_dir($folder.DIRECTORY_SEPARATOR."$subfoolder")){
					$this->tool_number_action($folder.DIRECTORY_SEPARATOR."$subfoolder", $progress, $errors);
				}
			}
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

	public function tool_videogenerator_help(){
		$this->ave->clear();
		$this->ave->set_subtool("VideoGenerator");

		$this->ave->print_help([
			' Modes:',
			' 0  - CheckSum',
			' 1  - Resolution',
			' 2  - Thumbnail',
			' 3  - Full: CheckSum + Resolution + Thumbnail',
			' 4  - Lite: CheckSum + Resolution',
			' ?0 - md5 (default)',
			' ?1 - sha256',
			' ?2 - crc32',
			' ?3 - whirlpool',
		]);

		echo " Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'algo' => strtolower($line[1] ?? '?'),
		];

		if($this->params['algo'] == '?') $this->params['algo'] = '0';

		if(!in_array($this->params['mode'],['0','1','2','3','4'])) return $this->tool_videogenerator_help();
		if(!in_array($this->params['algo'],['0','1','2','3'])) return $this->tool_videogenerator_help();

		$this->ave->set_subtool("VideoGenerator > ".$this->tool_videogenerator_name($this->params['mode']));
		$this->params['checksum'] = in_array($this->params['mode'],['0','3','4']);
		$this->params['resolution'] = in_array($this->params['mode'],['1','3','4']);
		$this->params['thumbnail'] = in_array($this->params['mode'],['2','3']);
		$this->tool_videogenerator_action();
	}

	public function tool_videogenerator_name(string $mode) : string {
		switch($mode){
			case '0': return 'CheckSum';
			case '1': return 'Resolution';
			case '2': return 'Thumbnail';
			case '3': return 'Full';
			case '4': return 'Lite';
		}
		return 'Unknown';
	}

	public function tool_videogenerator_action(){
		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->tool_videogenerator_help();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$algo = $this->tool_checksum_algo($this->params['algo']);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$file_id = 1;
			$list = [];
			$files = $this->ave->getFiles($folder, $video_extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				$name = pathinfo($file, PATHINFO_FILENAME);
				$directory = pathinfo($file, PATHINFO_DIRNAME);
				if($this->params['checksum'] && !file_exists("$file.$algo")){
					$hash = hash_file($algo, $file, false);
					if($this->ave->config->get('AVE_HASH_TO_UPPER')) $hash = strtoupper($hash);
				}
				if($this->params['resolution']){
					$resolution = $this->ave->getVideoResolution($file);
					if($resolution == '0x0'){
						$this->ave->log_error->write("FAILED GET_MEDIA_RESOLUTION \"$file\"");
						$errors++;
					} else {
						if(strpos($name, " [$resolution]") === false){
							$name = "$name [$resolution]";
						}
					}
				}
				if($this->params['thumbnail']){
					$thumbnail = $this->ave->getVideoThumbnail($file);
				} else {
					$thumbnail = false;
				}
				$new_name = "$directory".DIRECTORY_SEPARATOR."$name.$extension";
				$renamed = false;
				if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
					$this->ave->log_error->write("DUPLICATE \"$file\" AS \"$new_name\"");
					$errors++;
				} else {
					if($this->ave->rename($file, $new_name)){
						$renamed = true;
					} else {
						$errors++;
					}
				}
				if(isset($hash)){
					if(file_put_contents("$new_name.$algo",$hash)){
						$this->ave->log_event->write("CREATE \"$new_name.$algo\"");
					} else {
						$this->ave->log_error->write("FAILED CREATE \"$new_name.$algo\"");
						$errors++;
					}
				} else if($renamed){
					foreach(['md5','sha256','crc32','whirlpool'] as $a){
						if(file_exists("$file.$a")){
							if(!$this->ave->rename("$file.$a","$new_name.$a")) $errors++;
						}
					}
				}

				$name_old = "$directory".DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_FILENAME)."_s.jpg";
				$name_new = "$directory".DIRECTORY_SEPARATOR."$name"."_s.jpg";
				if($renamed && file_exists($name_old)){
					if($this->ave->rename($name_old, $name_new)){
						$renamed = true;
					} else {
						$errors++;
					}
				}

				$name_old = "$directory".DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_FILENAME).".srt";
				$name_new = "$directory".DIRECTORY_SEPARATOR."$name.srt";
				if($renamed && file_exists($name_old)){
					if($this->ave->rename($name_old, $name_new)){
						$renamed = true;
					} else {
						$errors++;
					}
				}
				$progress++;
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

	public function tool_generateseriesname_action(){
		$this->ave->clear();
		$this->ave->set_subtool("GenerateSeriesName");
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder, $video_extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$file_name = str_replace(['SEASON','EPISODE',' '], ['S','E',''], strtoupper(pathinfo($file, PATHINFO_FILENAME)));
				if(preg_match("/S[0-9]{1,2}E[0-9]{1,3}(.*)E[0-9]{1,3}/", $file_name, $mathes) == 1){
					$escaped_name = preg_replace("/[^SE0-9]/i", "", $mathes[0]);
				} else if(preg_match("/S[0-9]{1,2}E[0-9]{1,3}/", $file_name, $mathes) == 1){
					$escaped_name = preg_replace("/[^SE0-9]/i", "", $mathes[0]);
				} else if(preg_match("/\[S[0-9]{2}\.E[0-9]{1,3}\]/", $file_name, $mathes) == 1){
					$escaped_name = preg_replace("/[^SE0-9]/i", "", $mathes[0]);
				} else if(preg_match("/(\[S0\.)(E[0-9]{1,3})\]/", $file_name, $mathes) == 1){
					$escaped_name = "S01".preg_replace("/[^E0-9]/i", "", $mathes[2]);
				} else {
					$this->ave->log_error->write("FAILED GET SERIES ID \"$file\"");
					$errors++;
				}

				if(isset($escaped_name)){
					$new_name = pathinfo($file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.$escaped_name.".".pathinfo($file, PATHINFO_EXTENSION);
					if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
						$this->ave->log_error->write("DUPLICATE \"$file\" AS \"$new_name\"");
						$errors++;
					} else {
						if($this->ave->rename($file, $new_name)){
							$progress++;
						} else {
							$errors++;
						}
					}
				}

				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->exit();
	}

	public function tool_escapefilenamewww_action(){
		$this->ave->clear();
		$this->ave->set_subtool("EscapeFileNameWWW");
		echo " Double spaces reduce\r\n";
		echo " Characters after escape: A-Z a-z 0-9 _ -\r\n";
		echo " Be careful to prevent use on Japanese, Chinese, Korean, etc. file names\r\n\r\n"
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$file_name = pathinfo($file, PATHINFO_FILENAME);
				while(strpos($file_name, '  ') !== false){
					$file_name = str_replace('  ', ' ', $file_name);
				}
				$escaped_name = preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $file_name));

				if(isset($escaped_name)){
					$new_name = pathinfo($file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.$escaped_name.".".pathinfo($file, PATHINFO_EXTENSION);
					if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
						$this->ave->log_error->write("DUPLICATE \"$file\" AS \"$new_name\"");
						$errors++;
					} else {
						if($this->ave->rename($file, $new_name)){
							$progress++;
						} else {
							$errors++;
						}
					}
				}

				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->exit();
	}

}

?>
