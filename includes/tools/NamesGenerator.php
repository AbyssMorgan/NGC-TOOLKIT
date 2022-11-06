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
			' 3 - Generate series name: S00E00 etc.',
			' 4 - Escape file name (WWW)',
			' 5 - Pretty file name',
			' 6 - Remove YouTube quality tag',
			' 7 - Series episode editor',
		]);
	}

	public function action(string $action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolCheckSumHelp();
			case '1': return $this->ToolNumberHelp();
			case '2': return $this->ToolVideoGeneratorHelp();
			case '3': return $this->ToolGenerateSeriesNameAction();
			case '4': return $this->ToolEscapeFileNameWWWAction();
			case '5': return $this->ToolPrettyFileNameAction();
			case '6': return $this->ToolRemoveYouTubeQualityTagAction();
			case '7': return $this->ToolSeriesEpisodeEditorHelp();
		}
		$this->ave->select_action();
	}

	public function ToolCheckSumHelp(){
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

		if(!in_array($this->params['mode'],['0','1','2','3','4','5','6','7'])) return $this->ToolCheckSumHelp();
		if(!in_array($this->params['algo'],['0','1','2','3'])) return $this->ToolCheckSumHelp();
		$this->ave->set_subtool("CheckSum > ".$this->ToolCheckSumModeName($this->params['mode'])." > ".$this->ToolCheckSumAlgoName($this->params['algo']));
		return $this->ToolCheckSumAction();
	}

	public function ToolCheckSumModeName(string $mode) : string {
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

	public function ToolCheckSumAlgoName(string $mode) : string {
		switch($mode){
			case '0': return 'md5';
			case '1': return 'sha256';
			case '2': return 'crc32';
			case '3': return 'whirlpool';
		}
		return 'md5';
	}

	public function ToolCheckSumGetPattern(string $mode, string $file, string $hash, int $file_id) : string {
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

	public function ToolCheckSumAction(){
		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ToolCheckSumHelp();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$algo = $this->ToolCheckSumAlgoName($this->params['algo']);
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
				$new_name = $this->ToolCheckSumGetPattern($this->params['mode'], $file, $hash, $file_id++);
				if($this->params['list_only']){
					array_push($list,$new_name);
					$progress++;
				} else {
					if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
						$this->ave->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
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
				$this->ave->write_log("Write $count items from \"$folder\" to data file");
				$this->ave->write_data($list);
			}
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

	public function ToolNumberHelp(){
		$this->ave->clear();
		$this->ave->set_subtool("Number");

		$this->ave->print_help([
			' Group Single Format                Range',
			' g0    s0    "PPP_DDDDDD"           000001 - 999999',
			' g1    s1    "III\PPP_DDDDDD"       000001 - 999999',
			' g2    s2    "PPP_DDDDDD"           000001 - 999999',
			' g3    s3    "PPP_dir_name_DDDDDD"  000001 - 999999',
			' g4    s4    "PPP_dir_name_DDDD"    0001 -   9999',
			' g5    s5    "PPP_DDDDDD"           999999 - 000001',
			' g6    s6    "DDDDDD"               000001 - 999999',
		]);

		echo " Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$this->params = [
			'type' => strtolower($line[0] ?? '?'),
			'mode' => strtolower($line[1] ?? '?'),
		];

		if(!in_array($this->params['type'],['s','g'])) return $this->ToolNumberHelp();
		if(!in_array($this->params['mode'],['0','1','2','3','4','5','6'])) return $this->ToolNumberHelp();
		switch($this->params['type']){
			case 's': return $this->ToolNumberActionSingle();
			case 'g': return $this->ToolNumberActionGroup();
		}
	}

	public function ToolNumberGetPrefixID() : string {
		return sprintf("%03d", random_int(0, 999));
	}

	public function ToolNumberGetPattern(string $mode, string $file, string $prefix, int $file_id, string $input, int $part_id){
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

	public function ToolNumberAction(string $folder, int &$progress, int &$errors){
		if(!file_exists($folder)) return false;
		$file_id = ($this->params['mode'] == 5) ? 999999 : 1;
		$prefix_id = $this->ToolNumberGetPrefixID();
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
			$new_name = $this->ToolNumberGetPattern($this->params['mode'], $file, $prefix, $file_id, $folder, $part_id);
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
				$this->ave->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
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

	public function ToolNumberActionSingle(){
		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ToolNumberHelp();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		foreach($folders as $folder){
			$this->ToolNumberAction($folder, $progress, $errors);
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

	public function ToolNumberActionGroup(){
		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ToolNumberHelp();
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
					$this->ToolNumberAction($folder.DIRECTORY_SEPARATOR."$subfoolder", $progress, $errors);
				}
			}
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

	public function ToolVideoGeneratorHelp(){
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

		if(!in_array($this->params['mode'],['0','1','2','3','4'])) return $this->ToolVideoGeneratorHelp();
		if(!in_array($this->params['algo'],['0','1','2','3'])) return $this->ToolVideoGeneratorHelp();

		$this->ave->set_subtool("VideoGenerator > ".$this->ToolVideoGeneratorModeName($this->params['mode']));
		$this->params['checksum'] = in_array($this->params['mode'],['0','3','4']);
		$this->params['resolution'] = in_array($this->params['mode'],['1','3','4']);
		$this->params['thumbnail'] = in_array($this->params['mode'],['2','3']);
		$this->ToolVideoGeneratorAction();
	}

	public function ToolVideoGeneratorModeName(string $mode) : string {
		switch($mode){
			case '0': return 'CheckSum';
			case '1': return 'Resolution';
			case '2': return 'Thumbnail';
			case '3': return 'Full';
			case '4': return 'Lite';
		}
		return 'Unknown';
	}

	public function ToolVideoGeneratorAction(){
		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ToolVideoGeneratorHelp();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$algo = $this->ToolCheckSumAlgoName($this->params['algo']);
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
						$this->ave->write_error("FAILED GET_MEDIA_RESOLUTION \"$file\"");
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
				$new_name = $directory.DIRECTORY_SEPARATOR."$name.$extension";
				$renamed = false;
				if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
					$this->ave->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
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
						$this->ave->write_log("CREATE \"$new_name.$algo\"");
					} else {
						$this->ave->write_error("FAILED CREATE \"$new_name.$algo\"");
						$errors++;
					}
				} else if($renamed){
					foreach(['md5','sha256','crc32','whirlpool'] as $a){
						if(file_exists("$file.$a")){
							if(!$this->ave->rename("$file.$a","$new_name.$a")) $errors++;
						}
					}
				}

				$name_old = $directory.DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_FILENAME)."_s.jpg";
				$name_new = $directory.DIRECTORY_SEPARATOR."$name"."_s.jpg";
				if($renamed && file_exists($name_old)){
					if($this->ave->rename($name_old, $name_new)){
						$renamed = true;
					} else {
						$errors++;
					}
				}

				$name_old = $directory.DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_FILENAME).".srt";
				$name_new = $directory.DIRECTORY_SEPARATOR."$name.srt";
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

	public function ToolGenerateSeriesNameAction(){
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
					$escaped_name = '';
					$this->ave->write_error("FAILED GET SERIES ID \"$file\"");
					$errors++;
				}

				if(!empty($escaped_name)){
					$new_name = pathinfo($file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.$escaped_name.".".pathinfo($file, PATHINFO_EXTENSION);
					if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
						$this->ave->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
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

	public function ToolEscapeFileNameWWWAction(){
		$this->ave->clear();
		$this->ave->set_subtool("EscapeFileNameWWW");
		echo " Double spaces reduce\r\n";
		echo " Characters after escape: A-Z a-z 0-9 _ -\r\n";
		echo " Be careful to prevent use on Japanese, Chinese, Korean, etc. file names\r\n\r\n";
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
				$escaped_name = pathinfo($file, PATHINFO_FILENAME);
				while(strpos($escaped_name, '  ') !== false){
					$escaped_name = str_replace('  ', ' ', $escaped_name);
				}
				$escaped_name = trim(preg_replace('/[^A-Za-z0-9_\-]/', '', str_replace(' ', '_', $escaped_name)), ' ');

				if(empty($escaped_name)){
					$this->ave->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} else {
					$new_name = pathinfo($file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.$escaped_name.".".pathinfo($file, PATHINFO_EXTENSION);
					if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
						$this->ave->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
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

	public function ToolPrettyFileNameAction(){
		$this->ave->clear();
		$this->ave->set_subtool("PrettyFileName");
		echo " Double spaces reduce\r\n";
		echo " Replace nbsp into space\r\n";
		echo " Replace _ and . into space\r\n";
		echo " Remove characters: ; @ # ~ ! $ % ^ &\r\n\r\n";
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
				$escaped_name = str_replace(['_', '.', "\u{00A0}"], ' ', pathinfo($file, PATHINFO_FILENAME));
				$escaped_name = str_replace([';', '@', '#', '~', '!', '$', '%', '^', '&'], '', $escaped_name);
				while(strpos($escaped_name, '  ') !== false){
					$escaped_name = str_replace('  ', ' ', $escaped_name);
				}
				$escaped_name = trim($escaped_name, ' ');

				if(empty($escaped_name)){
					$this->ave->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} else {
					$new_name = pathinfo($file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.$escaped_name.".".pathinfo($file, PATHINFO_EXTENSION);
					if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
						$this->ave->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
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

	public function ToolRemoveYouTubeQualityTagAction(){
		$this->ave->clear();
		$this->ave->set_subtool("RemoveYouTubeQualityTag");
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$audio_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_AUDIO'));
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder, array_merge($video_extensions, $audio_extensions));
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$file_name = pathinfo($file, PATHINFO_FILENAME);
				$quality_tag = '';
				$start = strrpos($file_name, '(');
				if($start !== false){
					$end = strpos($file_name, ')', $start);
					if($end !== false){
						$quality_tag = substr($file_name, $start, $end - $start + 1);
						if(strpos($quality_tag, '_') === false){
							$quality_tag = '';
						}
					}
				}
				if(!empty($quality_tag)){
					$escaped_name = trim(str_replace($quality_tag, '', $file_name), ' ');
				} else {
					$escaped_name = '';
				}

				if(empty($quality_tag)){
					$this->ave->write_error("FAILED GET YOUTUBE QUALITY TAG \"$file\"");
					$errors++;
				} else if(empty($escaped_name)){
					$this->ave->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} else {
					$new_name = pathinfo($file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.$escaped_name.".".pathinfo($file, PATHINFO_EXTENSION);
					if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
						$this->ave->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
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

	public function ToolSeriesEpisodeEditorHelp(){
		$this->ave->clear();
		$this->ave->set_subtool("SeriesEpisodeEditor");

		$this->ave->print_help([
			' Modes:',
			' 0   - Change season',
			' 1   - Change episode numbers',
		]);

		echo " Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
		];

		if($this->params['algo'] == '?') $this->params['algo'] = '0';

		if(!in_array($this->params['mode'],['0','1'])) return $this->ToolSeriesEpisodeEditorHelp();
		switch($this->params['mode']){
			case '0': {
				$this->ToolSeriesEpisodeEditorActionSeason();
				break;
			}
			case '1': {
				$this->ToolSeriesEpisodeEditorActionEpisode();
				break;
			}
		}
	}

	public function ToolSeriesEpisodeEditorActionSeason(){
		$this->ave->clear();
		$this->ave->set_subtool("SeriesEpisodeEditor > ChangeSeason");

		set_input:
		echo " Attention filename must begin with the season and episode number in the format:\r\n";
		echo " \"S00E00<whatever>.<extension>\"\r\n";
		echo " \"S00E000<whatever>.<extension>\"\r\n\r\n";
		echo " Folder: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->ToolSeriesEpisodeEditorHelp();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder\r\n";
			goto set_input;
		}

		echo " Example: 1 or 01 (up to 99)\r\n";
		set_season_current:
		echo " Current season: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->ToolSeriesEpisodeEditorHelp();
		$current_season = substr(preg_replace('/\D/', '', $line), 0, 2);
		if(empty($current_season)) goto set_season_current;
		if(strlen($current_season) == 1) $current_season = "0$current_season";

		set_season_new:
		echo " New season:     ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->ToolSeriesEpisodeEditorHelp();
		$new_season = substr(preg_replace('/\D/', '', $line), 0, 2);
		if(empty($new_season)) goto set_season_new;
		if(strlen($new_season) == 1) $new_season = "0$new_season";

		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$follow_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO_FOLLOW'));
		$files = $this->ave->getFiles($input, array_merge($video_extensions, $follow_extensions));
		$items = 0;
		$total = count($files);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$file_name = pathinfo($file, PATHINFO_FILENAME);
			if(preg_match("/S[0-9]{2}E[0-9]{1,3}/", $file_name, $mathes) == 1){
				$serie_id = substr($file_name, 1, 2);
				if($serie_id == $current_season){
					$directory = pathinfo($file, PATHINFO_DIRNAME);
					$extension = pathinfo($file, PATHINFO_EXTENSION);
					$file_name = "S$new_season".substr($file_name, 3);
					$new_name = $directory.DIRECTORY_SEPARATOR."$file_name.$extension";
					if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
						$this->ave->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
						$errors++;
					} else {
						if($this->ave->rename($file, $new_name)){
							$progress++;
						} else {
							$errors++;
						}
					}
				}
			} else {
				$this->ave->write_error("FAILED GET SERIES ID \"$file\"");
				$errors++;
			}

			$this->ave->progress($items, $total);
			$this->ave->set_progress($progress, $errors);
		}

		$this->ave->exit();
	}

	public function ToolSeriesEpisodeEditorActionEpisode(){
		$this->ave->clear();
		$this->ave->set_subtool("SeriesEpisodeEditor > ChangeEpisodeNumbers");

		set_input:
		echo " Attention filename must begin with the season and episode number in the format:\r\n";
		echo " \"S00E00<whatever>.<extension>\"\r\n";
		echo " \"S00E000<whatever>.<extension>\"\r\n\r\n";
		echo " Folder: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->ToolSeriesEpisodeEditorHelp();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder\r\n";
			goto set_input;
		}

		echo " Choose episodes to edit (example 01 or 001)\r\n";

		set_start:
		echo " Start: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->ToolSeriesEpisodeEditorHelp();
		$episode_start = substr(preg_replace('/\D/', '', $line), 0, 3);
		if(empty($episode_start)) goto set_start;
		if($episode_start[0] == '0') $episode_start = substr($episode_start,1);
		$episode_start = intval($episode_start);

		set_end:
		echo " End:   ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->ToolSeriesEpisodeEditorHelp();
		$episode_end = substr(preg_replace('/\D/', '', $line), 0, 3);
		if(empty($episode_end)) goto set_end;
		if($episode_end[0] == '0') $episode_end = substr($episode_end,1);
		$episode_end = intval($episode_end);

		echo " Choose step as integer (example 5 or -5)\r\n";
		echo " Step:  ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->ToolSeriesEpisodeEditorHelp();
		$episode_step = intval(substr(preg_replace("/[^0-9\-]/", '', $line), 0, 3));

		$progress = 0;
		$errors = 0;
		$list = [];
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$follow_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO_FOLLOW'));
		$files = $this->ave->getFiles($input, array_merge($video_extensions, $follow_extensions));
		foreach($files as $file){
			if(!file_exists($file)) continue 1;
			$file_name = pathinfo($file, PATHINFO_FILENAME);
			$episode = null;
			if(preg_match("/S[0-9]{2}E[0-9]{3}/", $file_name, $mathes) == 1){
				$digits = 3;
				$max = 999;
				$episode = intval(ltrim(substr($file_name, 4, 3), "0"));
				$name = substr($file_name, 7);
			} else if(preg_match("/S[0-9]{2}E[0-9]{2}/", $file_name, $mathes) == 1){
				$digits = 2;
				$max = 99;
				$episode = intval(ltrim(substr($file_name, 4, 2), "0"));
				$name = substr($file_name, 6);
			} else if(preg_match("/S[0-9]{2}E[0-9]{1}/", $file_name, $mathes) == 1){
				$digits = 2;
				$max = 99;
				$episode = intval(substr($file_name, 4, 1));
				$name = substr($file_name, 5);
			}
			if(is_null($episode)){
				$this->ave->write_error("FAILED GET EPISODE ID \"$file\"");
				$errors++;
			} else {
				$season = substr($file_name, 0, 3);
				if($episode <= $episode_end && $episode >= $episode_start){
					$directory = pathinfo($file, PATHINFO_DIRNAME);
					$extension = pathinfo($file, PATHINFO_EXTENSION);
					$new_name = $directory.DIRECTORY_SEPARATOR.$season.'E'.$this->ave->format_episode($episode + $episode_step, $digits, $max)."$name.$extension";
					array_push($list,[
						'input' => $file,
						'output' => $new_name,
					]);
				}
			}
		}

		if($episode_step > 0) $list = array_reverse($list);

		$items = 0;
		$total = count($list);
		$round = 0;
		change_names:
		foreach($list as $key => $item){
			$progress++;
			if(file_exists($item['output']) && $round == 0) continue;
			$items++;
			if(file_exists($item['output'])){
				$this->ave->write_error("UNABLE CHANGE NAME \"".$item['input']."\" TO \"".$item['output']."\" FILE ALREADY EXIST");
				$errors++;
			} else {
				if(!$this->ave->rename($item['input'], $item['output'])) $errors++;
			}
			$this->ave->progress($items, $total);
			$this->ave->set_progress($progress, $errors);
			unset($list[$key]);
		}
		if($round == 0){
			$round++;
			goto change_names;
		}

		$this->ave->exit();
	}

}

?>
