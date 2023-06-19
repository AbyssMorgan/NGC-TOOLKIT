<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use App\Services\MediaFunctions;
use App\Services\StringConverter;

class FileNamesEditor {

	private string $name = "FileNamesEditor";

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
			' 0  - Generate names: CheckSum',
			' 1  - Generate names: Number (Video/Images)',
			' 2  - Generate video: CheckSum/Resolution/Thumbnail',
			' 3  - Generate series name: S00E00 etc.',
			' 4  - Escape file name (WWW)',
			' 5  - Pretty file name',
			' 6  - Remove YouTube quality tag',
			' 7  - Series episode editor',
			' 8  - Add file name prefix/suffix',
			' 9  - Remove keywords from file name',
			' 10 - Insert string into file name',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolCheckSum();
			case '1': return $this->ToolNumber();
			case '2': return $this->ToolVideoGenerator();
			case '3': return $this->ToolGenerateSeriesName();
			case '4': return $this->ToolEscapeFileNameWWW();
			case '5': return $this->ToolPrettyFileName();
			case '6': return $this->ToolRemoveYouTubeQualityTag();
			case '7': return $this->ToolSeriesEpisodeEditor();
			case '8': return $this->ToolAddFileNamePrefixSuffix();
			case '9': return $this->ToolRemoveKeywordsFromFileName();
			case '10': return $this->ToolInsertStringIntoFileName();
		}
		return false;
	}

	public function ToolCheckSum() : bool {
		$this->ave->set_subtool("CheckSum");

		set_mode:
		$this->ave->clear();
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

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'algo' => strtolower($line[1] ?? '0'),
			'list_only' => strtolower(($line[2] ?? '?')) == 'l',
		];

		if($this->params['algo'] == '?') $this->params['algo'] = '0';
		if(!in_array($this->params['mode'],['0','1','2','3','4','5','6','7'])) goto set_mode;
		if(!in_array($this->params['algo'],['0','1','2','3'])) goto set_mode;

		$this->ave->clear();
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
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
			$this->ave->progress($items, $total);
			unset($files);
			if($this->params['list_only']){
				$count = count($list);
				$this->ave->write_log("Write $count items from \"$folder\" to data file");
				$this->ave->write_data($list);
			}
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
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
			case '0': return $this->ave->get_file_path("$folder/$hash.$extension");
			case '1': return $this->ave->get_file_path("$folder/$name $hash.$extension");
			case '2': return $this->ave->get_file_path("$folder/$foldername $hash.$extension");
			case '3': return $this->ave->get_file_path("$folder/$foldername ".sprintf("%04d",$file_id)." $hash.$extension");
			case '4': return $this->ave->get_file_path("$folder/".date("Y-m-d",filemtime($file))." $hash.$extension");
			case '5': return $this->ave->get_file_path("$folder/".date("Y-m-d",filemtime($file))." ".sprintf("%04d",$file_id)." $hash.$extension");
			case '6': return $this->ave->get_file_path("$folder/".sprintf("%04d",$file_id)." $hash.$extension");
			case '7': return $this->ave->get_file_path("$folder/".sprintf("%06d",$file_id)." $hash.$extension");
		}
		return '';
	}

	public function ToolNumber() : bool {
		$this->ave->set_subtool("Number");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Group Single Format                   Range',
			' g0    s0    "PREFIX_DDDDDD"           000001 - 999999',
			' g1    s1    "PART\PREFIX_DDDDDD"      000001 - 999999',
			' g2    s2    "PREFIX_DDDDDD"           000001 - 999999',
			' g3    s3    "PREFIX_dir_name_DDDDDD"  000001 - 999999',
			' g4    s4    "PREFIX_dir_name_DDDD"    0001 -   9999',
			' g5    s5    "PREFIX_DDDDDD"           999999 - 000001',
			' g6    s6    "DDDDDD"                  000001 - 999999',
		]);

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'type' => strtolower($line[0] ?? '?'),
			'mode' => strtolower($line[1] ?? '?'),
		];

		if(!in_array($this->params['type'],['s','g'])) goto set_mode;
		if(!in_array($this->params['mode'],['0','1','2','3','4','5','6'])) goto set_mode;
		switch($this->params['type']){
			case 's': return $this->ToolNumberActionSingle();
			case 'g': return $this->ToolNumberActionGroup();
		}
		return false;
	}

	public function ToolNumberGetPrefixID() : string {
		return sprintf("%03d", random_int(0, 999));
	}

	public function ToolNumberGetPattern(string $mode, string $file, string $prefix, int $file_id, string $input, int $part_id) : ?string {
		$folder = pathinfo($file, PATHINFO_DIRNAME);
		$foldername = pathinfo($folder, PATHINFO_FILENAME);
		$name = pathinfo($file, PATHINFO_FILENAME);
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		if($this->ave->config->get('AVE_EXTENSION_TO_LOWER')) $extension = strtolower($extension);
		switch($mode){
			case '0': return $this->ave->get_file_path("$folder/$prefix".sprintf("%06d",$file_id).".$extension");
			case '1': return $this->ave->get_file_path("$input/".sprintf("%03d",$part_id)."/$prefix".sprintf("%06d",$file_id).".$extension");
			case '2': return $this->ave->get_file_path("$input/$prefix".sprintf("%06d",$file_id).".$extension");
			case '3': return $this->ave->get_file_path("$folder/$prefix$foldername"."_".sprintf("%06d",$file_id).".$extension");
			case '4': return $this->ave->get_file_path("$folder/$prefix$foldername"."_".sprintf("%04d",$file_id).".$extension");
			case '5': return $this->ave->get_file_path("$folder/$prefix".sprintf("%06d",$file_id).".$extension");
			case '6': return $this->ave->get_file_path("$folder/".sprintf("%06d",$file_id).".$extension");
		}
		return null;
	}

	public function ToolNumberAction(string $folder, int &$progress, int &$errors) : bool {
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
		$this->ave->progress($items, $total);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolNumberActionSingle() : bool {
		$this->ave->clear();
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		foreach($folders as $folder){
			$this->ToolNumberAction($folder, $progress, $errors);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolNumberActionGroup() : bool {
		$this->ave->clear();
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
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
				$dir = $this->ave->get_file_path("$folder/$subfoolder");
				if(is_dir($dir)){
					$this->ToolNumberAction($dir, $progress, $errors);
				}
			}
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolVideoGenerator() : bool {
		$this->ave->set_subtool("VideoGenerator");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Modes:',
			' 0  - CheckSum',
			' 1  - Resolution',
			' 2  - Thumbnail',
			' 3  - CheckSum + Resolution + Thumbnail',
			' 4  - CheckSum + Resolution',
			' ?0 - md5 (default)',
			' ?1 - sha256',
			' ?2 - crc32',
			' ?3 - whirlpool',
		]);

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'algo' => strtolower($line[1] ?? '?'),
		];

		if($this->params['algo'] == '?') $this->params['algo'] = '0';

		if(!in_array($this->params['mode'],['0','1','2','3','4'])) goto set_mode;
		if(!in_array($this->params['algo'],['0','1','2','3'])) goto set_mode;
		$this->params['checksum'] = in_array($this->params['mode'],['0','3','4']);
		$this->params['resolution'] = in_array($this->params['mode'],['1','3','4']);
		$this->params['thumbnail'] = in_array($this->params['mode'],['2','3']);

		$this->ave->clear();
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$algo = $this->ToolCheckSumAlgoName($this->params['algo']);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$media = new MediaFunctions();
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
					$resolution = $media->getVideoResolution($file);
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
					$thumbnail = $media->getVideoThumbnail($file, $directory, $this->ave->config->get('AVE_THUMBNAIL_WIDTH'), $this->ave->config->get('AVE_THUMBNAIL_ROWS'), $this->ave->config->get('AVE_THUMBNAIL_COLUMN'));
				} else {
					$thumbnail = false;
				}
				$new_name = $this->ave->get_file_path("$directory/$name.$extension");
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
					$follow_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO_FOLLOW'));
					foreach($follow_extensions as $a){
						if(file_exists("$file.$a")){
							if(!$this->ave->rename("$file.$a","$new_name.$a")) $errors++;
						}
					}
				}

				$name_old = $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_BASENAME).".webp");
				$name_new = $this->ave->get_file_path("$directory/$name.$extension.webp");
				if($renamed && file_exists($name_old)){
					if($this->ave->rename($name_old, $name_new)){
						$renamed = true;
					} else {
						$errors++;
					}
				}

				$name_old = $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_FILENAME).".srt");
				$name_new = $this->ave->get_file_path("$directory/$name.srt");
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
			$this->ave->progress($items, $total);
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolGenerateSeriesName() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("GenerateSeriesName");
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
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
					$new_name = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/$escaped_name.".pathinfo($file, PATHINFO_EXTENSION));
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
			$this->ave->progress($items, $total);
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolEscapeFileNameWWW() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("EscapeFileNameWWW");
		$this->ave->print_help([
			" Double spaces reduce",
			" Characters after escape: A-Z a-z 0-9 _ - .",
			" Be careful to prevent use on Japanese, Chinese, Korean, etc. file names",
		]);
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
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
				$escaped_name = trim(preg_replace('/[^A-Za-z0-9_\-.]/', '', str_replace(' ', '_', $escaped_name)), ' ');

				if(empty($escaped_name)){
					$this->ave->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} else {
					$new_name = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/$escaped_name.".pathinfo($file, PATHINFO_EXTENSION));
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
			$this->ave->progress($items, $total);
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolPrettyFileName() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("PrettyFileName");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Flags (type in one line, default BC):',
			' B   - Basic replacement',
			' C   - Basic remove',
			' L   - Replace language characters',
			' 0   - Chinese to PinYin',
			' 1   - Hiragama to Romaji',
			' 2   - Katakana to Romaji',
		]);

		$line = strtoupper($this->ave->get_input(" Flags: "));
		if($line == '#') return false;
		if(empty($line)) $line = 'BC';
		if(str_replace(['B', 'C', 'L', '0', '1', '2'], '', $line) != '') goto set_mode;
		$flags = (object)[
			'basic_replace' => (strpos($line, 'B') !== false),
			'basic_remove' => (strpos($line, 'C') !== false),
			'language_replace' => (strpos($line, 'L') !== false),
			'ChineseToPinYin' => (strpos($line, '0') !== false),
			'HiragamaToRomaji' => (strpos($line, '1') !== false),
			'KatakanaToRomaji' => (strpos($line, '2') !== false),
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
				if($flags->basic_replace || $flags->language_replace || $flags->HiragamaToRomaji || $flags->KatakanaToRomaji){
					$escaped_name = $converter->convert($escaped_name);
				}
				if($flags->basic_remove){
					$escaped_name = $converter->clean($escaped_name);
				}
				if($flags->ChineseToPinYin){
					$escaped_name = $converter->stringToPinYin($escaped_name);
				}
				$escaped_name = $converter->remove_double_spaces(str_replace(',', ', ', $escaped_name));
				if(empty($escaped_name)){
					$this->ave->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} else {
					$new_name = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/$escaped_name.".pathinfo($file, PATHINFO_EXTENSION));
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
			$this->ave->progress($items, $total);
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolRemoveYouTubeQualityTag() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("RemoveYouTubeQualityTag");
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
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
					$new_name = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/$escaped_name.".pathinfo($file, PATHINFO_EXTENSION));
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
			$this->ave->progress($items, $total);
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolSeriesEpisodeEditor() : bool {
		$this->ave->set_subtool("SeriesEpisodeEditor");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Modes:',
			' 0   - Change season number',
			' 1   - Change episode numbers',
		]);

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
		];

		if(!in_array($this->params['mode'],['0','1'])) goto set_mode;
		switch($this->params['mode']){
			case '0': return $this->ToolSeriesEpisodeEditorActionSeason();
			case '1': return $this->ToolSeriesEpisodeEditorActionEpisode();
		}
		return false;
	}

	public function ToolSeriesEpisodeEditorActionSeason() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SeriesEpisodeEditor > ChangeSeason");

		set_input:
		$this->ave->print_help([
			" Attention filename must begin with the season and episode number in the format:",
			" \"S00E00{whatever}.{extension}\"",
			" \"S00E000{whatever}.{extension}\"",
		]);
		$line = $this->ave->get_input(" Folder: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->ave->echo(" Invalid input folder");
			goto set_input;
		}

		$this->ave->echo(" Example: 1 or 01 (up to 99)");
		set_season_current:
		$line = $this->ave->get_input(" Current season: ");
		if($line == '#') return false;
		$current_season = substr(preg_replace('/\D/', '', $line), 0, 2);
		if($current_season == '') goto set_season_current;
		if(strlen($current_season) == 1) $current_season = "0$current_season";

		set_season_new:
		$line = $this->ave->get_input(" New season:     ");
		if($line == '#') return false;
		$new_season = substr(preg_replace('/\D/', '', $line), 0, 2);
		if($new_season == '') goto set_season_new;
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
					$new_name = $this->ave->get_file_path("$directory/$file_name.$extension");
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
		$this->ave->progress($items, $total);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolSeriesEpisodeEditorActionEpisode() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SeriesEpisodeEditor > ChangeEpisodeNumbers");

		set_input:
		$this->ave->print_help([
			" Attention filename must begin with the season and episode number in the format:",
			" \"S00E00{whatever}.{extension}\"",
			" \"S00E000{whatever}.{extension}\"",
		]);
		$line = $this->ave->get_input(" Folder: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->ave->echo(" Invalid input folder");
			goto set_input;
		}

		$this->ave->echo(" Choose episodes to edit (example 01 or 001)");

		set_start:
		$line = $this->ave->get_input(" Start: ");
		if($line == '#') return false;
		$episode_start = substr(preg_replace('/\D/', '', $line), 0, 3);
		if($episode_start == '') goto set_start;
		if($episode_start[0] == '0') $episode_start = substr($episode_start,1);
		$episode_start = intval($episode_start);

		set_end:
		$line = $this->ave->get_input(" End:   ");
		if($line == '#') return false;
		$episode_end = substr(preg_replace('/\D/', '', $line), 0, 3);
		if($episode_end == '') goto set_end;
		if($episode_end[0] == '0') $episode_end = substr($episode_end,1);
		$episode_end = intval($episode_end);

		$this->ave->echo(" Choose step as integer (example 5 or -5)");
		$line = $this->ave->get_input(" Step:  ");
		if($line == '#') return false;
		$episode_step = intval(substr(preg_replace("/[^0-9\-]/", '', $line), 0, 3));

		$progress = 0;
		$errors = 0;
		$list = [];
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$follow_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO_FOLLOW'));
		$files = $this->ave->getFiles($input, array_merge($video_extensions, $follow_extensions));
		$media = new MediaFunctions();
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
					$new_name = $this->ave->get_file_path("$directory/$season"."E".$media->format_episode($episode + $episode_step, $digits, $max)."$name.$extension");
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
		$this->ave->progress($items, $total);
		if($round == 0){
			$round++;
			goto change_names;
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolAddFileNamePrefixSuffix() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("AddFileNamePrefixSuffix");

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

		$prefix = $this->ave->get_input_no_trim(" Prefix (may be empty): ");
		if($prefix == '#') return false;
		$prefix = str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*'], '', $prefix);

		$suffix = $this->ave->get_input_no_trim(" Suffix (may be empty): ");
		if($suffix == '#') return false;
		$suffix = str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*'], '', $suffix);

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
				$new_name = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/$prefix".pathinfo($file, PATHINFO_FILENAME).$suffix.".".pathinfo($file, PATHINFO_EXTENSION));
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

	public function ToolRemoveKeywordsFromFileName() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("RemoveKeywordsFromFileName");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Modes:',
			' 0 - Type keywords',
			' 1 - Load from file (new line every keyword)',
		]);

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
		];

		if(!in_array($this->params['mode'],['0','1'])) goto set_mode;

		$this->ave->clear();
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

		$keywords = [];
		if($this->params['mode'] == '0'){
			$this->ave->echo(" Put numbers how much keywords you want remove");

			set_quantity:
			$line = $this->ave->get_input(" Quantity: ");
			if($line == '#') return false;
			$quantity = intval(preg_replace('/\D/', '', $line));
			if($quantity <= 0) goto set_quantity;

			for($i = 0; $i < $quantity; $i++){
				$keywords[$i] = $this->ave->get_input_no_trim(" Keyword ".($i+1).": ");
			}
		} else if($this->params['mode'] == '1'){
			set_keyword_file:
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
				if(!empty(trim($line))) continue;
				array_push($keywords, $line);
			}
			fclose($fp);
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
				$name = trim(str_replace($keywords, '', pathinfo($file, PATHINFO_FILENAME)));
				$new_name = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/$name.".pathinfo($file, PATHINFO_EXTENSION));
				if(empty($new_name)){
					$this->ave->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} else if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
					$this->ave->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
					$errors++;
				} else {
					if($this->ave->rename($file, $new_name)){
						$progress++;
					} else {
						$errors++;
					}
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

	public function ToolInsertStringIntoFileName() : bool {
		$this->ave->set_subtool("InsertStringIntoFileName");

		set_offset:
		$this->ave->clear();
		$this->ave->print_help([
			' Specify the string offset where you want insert into filename',
			' Offset = 0 - means the beginning, i.e. the string will be inserted before the file name (prefix)',
			' Offset > 0 - means that the string will be inserted after skipping N characters',
			' Offset < 0 - means that the string will be inserted after skipping N characters from the end',
		]);
		$line = $this->ave->get_input(" Offset: ");
		if($line == '#') return false;
		$offset = preg_replace("/[^0-9\-]/", '', $line);
		if($offset == '') goto set_offset;
		$offset = intval($offset);

		$this->ave->print_help([
			' Specify the string you want to inject the filename, may contain spaces',
		]);
		$insert_string = $this->ave->get_input_no_trim(" String: ");

		$this->ave->clear();
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
				$name = pathinfo($file, PATHINFO_FILENAME);
				if(abs($offset) > strlen($name)){
					$this->ave->write_error("ILLEGAL OFFSET FOR FILE NAME \"$file\"");
					$errors++;
				} else {
					if($offset > 0){
						$name = substr($name, 0, $offset).$insert_string.substr($name, $offset);
					} else if($offset < 0){
						$name = substr($name, 0, strlen($name) + $offset).$insert_string.substr($name, $offset);
					} else {
						$name = $insert_string.$name;
					}
	 				$new_name = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/$name.".pathinfo($file, PATHINFO_EXTENSION));
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
			$this->ave->progress($items, $total);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

}

?>
