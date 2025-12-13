<?php

/**
 * NGC-TOOLKIT v2.7.5 – Component
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

class FileNamesEditor {

	private string $name = "File Names Editor";
	private string $action;
	private Toolkit $core;

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
	}

	public function help() : void {
		$this->core->print_help([
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
			' 11 - Replace keywords in file name',
			' 12 - Extension change',
		]);
	}

	public function action(string $action) : bool {
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_check_sum();
			case '1': return $this->tool_number();
			case '2': return $this->tool_video_generator();
			case '3': return $this->tool_generate_series_name();
			case '4': return $this->tool_escape_file_name_www();
			case '5': return $this->tool_pretty_file_name();
			case '6': return $this->tool_remove_youtube_quality_tag();
			case '7': return $this->tool_series_episode_editor();
			case '8': return $this->tool_add_file_name_prefix_suffix();
			case '9': return $this->tool_remove_keywords_from_file_name();
			case '10': return $this->tool_insert_string_into_file_name();
			case '11': return $this->tool_replace_keywords_in_file_name();
			case '12': return $this->tool_extension_change();
		}
		return false;
	}

	public function tool_check_sum() : bool {
		$this->core->set_subtool("Checksum");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
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

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$params = [
			'mode' => \strtolower($line[0] ?? '?'),
			'algo' => \strtolower($line[1] ?? '0'),
			'list_only' => \strtolower(($line[2] ?? '?')) == 'l',
		];

		if($params['algo'] == '?') $params['algo'] = '0';
		if(!\in_array($params['mode'], ['0', '1', '2', '3', '4', '5', '6', '7'])) goto set_mode;
		if(!\in_array($params['algo'], ['0', '1', '2', '3'])) goto set_mode;

		$this->core->clear();

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$algo = $this->core->get_hash_alghoritm(\intval($params['algo']))['name'];
		$errors = 0;
		$this->core->set_errors($errors);
		$except_files = \explode(";", $this->core->config->get('IGNORE_VALIDATE_FILES'));
		$except_extensions = \explode(" ", $this->core->config->get('IGNORE_VALIDATE_EXTENSIONS'));
		foreach($folders as $folder){
			if(!\file_exists($folder)) continue;
			$file_id = 1;
			$list = [];
			$files = $this->core->get_files($folder, null, $except_extensions);
			$items = 0;
			$total = \count($files);
			foreach($files as $file){
				$items++;
				if(!\file_exists($file)) continue 1;
				if(\in_array(\mb_strtolower(\pathinfo($file, PATHINFO_BASENAME)), $except_files)) continue;
				$hash = \strtoupper(\hash_file($algo, $file, false));
				$new_name = $this->tool_check_sum_get_pattern($params['mode'], $file, $hash, $file_id++);
				if($params['list_only']){
					\array_push($list, $new_name);
				} else {
					if(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
						$this->core->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
						$errors++;
						if($this->core->config->get('ACTION_AFTER_DUPLICATE') == 'DELETE'){
							if(!$this->core->delete($file)) $errors++;
						} else {
							if(!$this->core->move($file, "$file.tmp")) $errors++;
						}
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
			if($params['list_only']){
				$count = \count($list);
				$this->core->write_log("Write $count items from \"$folder\" to data file");
				$this->core->write_data($list);
			}
			$this->core->set_folder_done($folder);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_check_sum_get_pattern(string $mode, string $file, string $hash, int $file_id) : string {
		$folder = \pathinfo($file, PATHINFO_DIRNAME);
		$foldername = \pathinfo($folder, PATHINFO_FILENAME);
		$name = \pathinfo($file, PATHINFO_FILENAME);
		$extension = $this->core->get_extension($file);
		switch($mode){
			case '0': return $this->core->get_path("$folder/$hash.$extension");
			case '1': return $this->core->get_path("$folder/$name $hash.$extension");
			case '2': return $this->core->get_path("$folder/$foldername $hash.$extension");
			case '3': return $this->core->get_path("$folder/$foldername ".\sprintf("%04d", $file_id)." $hash.$extension");
			case '4': return $this->core->get_path("$folder/".\date("Y-m-d", \filemtime($file))." $hash.$extension");
			case '5': return $this->core->get_path("$folder/".\date("Y-m-d", \filemtime($file))." ".\sprintf("%04d", $file_id)." $hash.$extension");
			case '6': return $this->core->get_path("$folder/".\sprintf("%04d", $file_id)." $hash.$extension");
			case '7': return $this->core->get_path("$folder/".\sprintf("%06d", $file_id)." $hash.$extension");
		}
		return '';
	}

	public function tool_number() : bool {
		$this->core->set_subtool("Number");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Group | Single | Format                  | Range',
			' g0    | s0     | PREFIX_DDDDDD           | 000001 - 999999',
			' g1    | s1     | PART\PREFIX_DDDDDD      | 000001 - 999999',
			' g2    | s2     | PREFIX_DDDDDD           | 000001 - 999999',
			' g3    | s3     | PREFIX_dir_name_DDDDDD  | 000001 - 999999',
			' g4    | s4     | PREFIX_dir_name_DDDD    |   0001 -   9999',
			' g5    | s5     | PREFIX_DDDDDD           | 999999 - 000001',
			' g6    | s6     | DDDDDD                  | 000001 - 999999',
		]);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$params = [
			'type' => \strtolower($line[0] ?? '?'),
			'mode' => \strtolower($line[1] ?? '?'),
		];

		if(!\in_array($params['type'], ['s', 'g'])) goto set_mode;
		if(!\in_array($params['mode'], ['0', '1', '2', '3', '4', '5', '6'])) goto set_mode;
		switch($params['type']){
			case 's': return $this->tool_number_action_single($params['mode']);
			case 'g': return $this->tool_number_action_group($params['mode']);
		}
		return false;
	}

	public function tool_number_get_prefix_id() : string {
		return \sprintf("%03d", \random_int(0, 999));
	}

	public function tool_number_get_pattern(string $mode, string $file, string $prefix, int $file_id, string $input, int $part_id) : ?string {
		$folder = \pathinfo($file, PATHINFO_DIRNAME);
		$foldername = \pathinfo($folder, PATHINFO_FILENAME);
		$extension = $this->core->get_extension($file);
		switch($mode){
			case '0': return $this->core->get_path("$folder/$prefix".\sprintf("%06d", $file_id).".$extension");
			case '1': return $this->core->get_path("$input/".\sprintf("%03d", $part_id)."/$prefix".\sprintf("%06d", $file_id).".$extension");
			case '2': return $this->core->get_path("$input/$prefix".\sprintf("%06d", $file_id).".$extension");
			case '3': return $this->core->get_path("$folder/{$prefix}{$foldername}_".\sprintf("%06d", $file_id).".$extension");
			case '4': return $this->core->get_path("$folder/{$prefix}{$foldername}_".\sprintf("%04d", $file_id).".$extension");
			case '5': return $this->core->get_path("$folder/{$prefix}".\sprintf("%06d", $file_id).".$extension");
			case '6': return $this->core->get_path("$folder/".\sprintf("%06d", $file_id).".$extension");
		}
		return null;
	}

	public function tool_number_action(string $mode, string $folder, int &$errors) : bool {
		if(!\file_exists($folder)) return false;
		$file_id = ($mode == 5) ? 999999 : 1;
		$prefix_id = $this->tool_number_get_prefix_id();
		$files = $this->core->get_files($folder, \array_merge($this->core->media->extensions_images, $this->core->media->extensions_video));
		$items = 0;
		$total = \count($files);
		foreach($files as $file){
			$items++;
			if(!\file_exists($file)) continue;
			$extension = $this->core->get_extension($file);
			$part_id = (int) \floor($file_id / \intval($this->core->config->get('PART_SIZE'))) + 1;
			if($mode == 1){
				$prefix_id = \sprintf("%03d", $part_id);
			}
			if(\in_array($extension, $this->core->media->extensions_images)){
				$prefix = $this->core->config->get('PREFIX_PHOTO')."_$prefix_id"."_";
			} else {
				$prefix = $this->core->config->get('PREFIX_VIDEO')."_$prefix_id"."_";
			}
			$new_name = $this->tool_number_get_pattern($mode, $file, $prefix, $file_id, $folder, $part_id);
			$directory = \pathinfo($new_name, PATHINFO_DIRNAME);
			if(!\file_exists($directory)){
				if(!$this->core->mkdir($directory)){
					$errors++;
					$this->core->set_errors($errors);
					continue;
				}
			}
			if($mode == 5){
				$file_id--;
			} else {
				$file_id++;
			}
			if(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
				$this->core->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
				$errors++;
			} else {
				if(!$this->core->move($file, $new_name)){
					$errors++;
				}
			}
			unset($files);
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$this->core->progress($items, $total);
		return false;
	}

	public function tool_number_action_single(string $mode) : bool {
		$this->core->clear();

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			$this->tool_number_action($mode, $folder, $errors);
			$this->core->set_folder_done($folder);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_number_action_group(string $mode) : bool {
		$this->core->clear();

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!\file_exists($folder)) continue;
			$subfolders = \scandir($folder);
			foreach($subfolders as $subfoolder){
				if($subfoolder == '.' || $subfoolder == '..') continue;
				$dir = $this->core->get_path("$folder/$subfoolder");
				if(\is_dir($dir)){
					$this->tool_number_action($mode, $dir, $errors);
				}
			}
			$this->core->set_folder_done($folder);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_video_generator() : bool {
		$this->core->set_subtool("Video generator");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
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

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$params = [
			'mode' => \strtolower($line[0] ?? '?'),
			'algo' => \strtolower($line[1] ?? '?'),
		];

		if($params['algo'] == '?') $params['algo'] = '0';

		if(!\in_array($params['mode'], ['0', '1', '2', '3', '4'])) goto set_mode;
		if(!\in_array($params['algo'], ['0', '1', '2', '3'])) goto set_mode;
		$params['checksum'] = \in_array($params['mode'], ['0', '3', '4']);
		$params['resolution'] = \in_array($params['mode'], ['1', '3', '4']);
		$params['thumbnail'] = \in_array($params['mode'], ['2', '3']);

		$this->core->clear();

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$algo = $this->core->get_hash_alghoritm(\intval($params['algo']))['name'];
		$errors = 0;
		$this->core->set_errors($errors);
		$extensions = \array_merge($this->core->media->extensions_video, $this->core->media->extensions_audio, $this->core->media->extensions_images);
		foreach($folders as $folder){
			if(!\file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $extensions);
			$items = 0;
			$total = \count($files);
			foreach($files as $file){
				$items++;
				if(!\file_exists($file)) continue 1;
				$extension = $this->core->get_extension($file);
				$name = \pathinfo($file, PATHINFO_FILENAME);
				$directory = \pathinfo($file, PATHINFO_DIRNAME);
				if($params['checksum'] && !\file_exists("$file.$algo")){
					$hash = \strtoupper(\hash_file($algo, $file, false));
				} else {
					$hash = null;
				}
				if($params['resolution'] && \in_array($extension, $this->core->media->extensions_video) || \in_array($extension, $this->core->media->extensions_images)){
					$resolution = $this->core->media->ffprobe_get_resolution($file);
					if($resolution == '0x0'){
						$this->core->write_error("FAILED GET MEDIA RESOLUTION \"$file\"");
						$errors++;
					} else {
						if(\strpos($name, " [$resolution]") === false){
							$name = "$name [$resolution]";
						}
					}
				}
				if($params['thumbnail'] && \in_array($extension, $this->core->media->extensions_video)){
					$thumbnail = $this->core->media->get_video_thumbnail($file, $directory, $this->core->config->get('THUMBNAIL_WIDTH'), $this->core->config->get('THUMBNAIL_ROWS'), $this->core->config->get('THUMBNAIL_COLUMN'));
					if($thumbnail){
						$this->core->write_log("GENERATE THUMBNAIL \"$file.webp\"");
					} else {
						$this->core->write_error("FAILED GENERATE THUMBNAIL \"$file.webp\"");
						$errors++;
					}
				}
				$new_name = $this->core->get_path("$directory/$name.$extension");
				$renamed = false;
				if(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
					$this->core->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
					$errors++;
				} else {
					if($this->core->move($file, $new_name)){
						$renamed = true;
					} else {
						$errors++;
					}
				}
				if(isset($hash)){
					if(\file_put_contents("$new_name.$algo", $hash)){
						$this->core->write_log("CREATE \"$new_name.$algo\"");
					} else {
						$this->core->write_error("FAILED CREATE \"$new_name.$algo\"");
						$errors++;
					}
				} elseif($renamed){
					$follow_extensions = \explode(" ", $this->core->config->get('EXTENSIONS_VIDEO_FOLLOW'));
					foreach($follow_extensions as $a){
						if(\file_exists("$file.$a")){
							if(!$this->core->move("$file.$a", "$new_name.$a")) $errors++;
						}
						$name_old = $this->core->get_path(\pathinfo($file, PATHINFO_DIRNAME)."/$name.$a");
						$name_new = $this->core->get_path("$directory/$name.$a");
						if(\file_exists($name_old)){
							if(!$this->core->move($name_old, $name_new)) $errors++;
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

	public function tool_generate_series_name() : bool {
		$this->core->clear();
		$this->core->set_subtool("Generate series name");

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!\file_exists($folder)) continue;
			$files = $this->core->get_files($folder, \array_merge($this->core->media->extensions_video, $this->core->media->extensions_audio, $this->core->media->extensions_subtitle));
			$items = 0;
			$total = \count($files);
			foreach($files as $file){
				$items++;
				if(!\file_exists($file)) continue 1;
				$file_name = \str_replace(['SEASON', 'EPISODE'], ['S', 'E'], \mb_strtoupper(\pathinfo($file, PATHINFO_FILENAME)));
				$file_name = \str_replace(['[', ']'], '', $file_name);
				$pattern_multi_episodes = "/(S|SP)([0-9]{1,2})E([0-9]{1,3})(?:-E?([0-9]{1,3}))?/";
				$pattern_single_episode = "/(S|SP)([0-9]{1,2})E([0-9]{1,3})/";
				if(\preg_match($pattern_multi_episodes, $file_name, $matches)){
					$season = \str_pad($matches[2], 2, '0', STR_PAD_LEFT);
					$episode_start = \str_pad($matches[3], 2, '0', STR_PAD_LEFT);
					if(!empty($matches[4])){
						$episode_end = \str_pad($matches[4], 2, '0', STR_PAD_LEFT);
						$escaped_name = $matches[1].$season.'E'.$episode_start.'-E'.$episode_end;
					} else {
						$escaped_name = $matches[1].$season.'E'.$episode_start;
					}
				} elseif(\preg_match($pattern_single_episode, $file_name, $matches)){
					$season = \str_pad($matches[2], 2, '0', STR_PAD_LEFT);
					$episode = \str_pad($matches[3], 2, '0', STR_PAD_LEFT);
					$escaped_name = $matches[1].$season.'E'.$episode;
				} else {
					$escaped_name = '';
					$this->core->write_error("FAILED GET SERIES ID \"$file\"");
					$errors++;
				}

				if(!empty($escaped_name)){
					$new_name = $this->core->get_path(\pathinfo($file, PATHINFO_DIRNAME)."/$escaped_name.".$this->core->get_extension($file));
					if(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
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

	public function tool_escape_file_name_www() : bool {
		$this->core->clear();
		$this->core->set_subtool("Escape file name WWW");
		$this->core->print_help([
			" Double spaces reduce",
			" Characters after escape: A-Z a-z 0-9 _ - .",
			" Be careful to prevent use on Japanese, Chinese, Korean, etc. file names",
		]);

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
				$escaped_name = \pathinfo($file, PATHINFO_FILENAME);
				while(\str_contains($escaped_name, '  ')){
					$escaped_name = \str_replace('  ', ' ', $escaped_name);
				}
				$escaped_name = \trim(\preg_replace('/[^A-Za-z0-9_\-.]/', '', \str_replace(' ', '_', $escaped_name)), ' ');
				if(empty($escaped_name)){
					$this->core->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} else {
					$new_name = $this->core->get_path(\pathinfo($file, PATHINFO_DIRNAME)."/$escaped_name.".$this->core->get_extension($file));
					if(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
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

	public function tool_pretty_file_name() : bool {
		$this->core->clear();
		$this->core->set_subtool("Pretty file name");

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

		$line = \strtoupper($this->core->get_input(" Flags: "));
		if($line == '#') return false;
		if(empty($line)) $line = 'BC';
		if(\str_replace(['B', 'C', 'L', 'P', '0', '1', '2', '+', '-'], '', $line) != '') goto set_mode;
		$flags = (object)[
			'basic_replace' => \str_contains($line, 'B'),
			'basic_remove' => \str_contains($line, 'C'),
			'language_replace' => \str_contains($line, 'L'),
			'ChineseToPinYin' => \str_contains($line, '0'),
			'HiragamaToRomaji' => \str_contains($line, '1'),
			'KatakanaToRomaji' => \str_contains($line, '2'),
			'UpperCase' => \str_contains($line, '+'),
			'LowerCase' => \str_contains($line, '-'),
			'CapitalizeProperly' => \str_contains($line, 'P'),
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
			$filters = \explode(" ", $line);
		}

		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!\file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $extensions, null, $filters);
			$items = 0;
			$total = \count($files);
			foreach($files as $file){
				$items++;
				if(!\file_exists($file)) continue 1;
				$escaped_name = \pathinfo($file, PATHINFO_FILENAME);
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
					$escaped_name = ($escaped_name);
				} elseif($flags->LowerCase){
					$escaped_name = \mb_strtolower($escaped_name);
				} elseif($flags->CapitalizeProperly){
					$escaped_name = \ucwords(\mb_strtolower($escaped_name));
					$escaped_name = \preg_replace_callback('/(\d)([a-z])/', function(array $matches) : string {
						return $matches[1].\mb_strtoupper($matches[2]);
					}, $escaped_name);
					$escaped_name = \preg_replace_callback('/([\(\[])([a-z])/', function(array $matches) : string {
						return $matches[1].\mb_strtoupper($matches[2]);
					}, $escaped_name);
				}
				$escaped_name = $converter->remove_double_spaces(\str_replace(',', ', ', $escaped_name));
				if(empty($escaped_name)){
					$this->core->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} else {
					$new_name = $this->core->get_path(\pathinfo($file, PATHINFO_DIRNAME)."/$escaped_name.".$this->core->get_extension($file));
					if(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
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

	public function tool_remove_youtube_quality_tag() : bool {
		$this->core->clear();
		$this->core->set_subtool("Remove YouTube quality tag");

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!\file_exists($folder)) continue;
			$files = $this->core->get_files($folder, \array_merge($this->core->media->extensions_video, $this->core->media->extensions_audio, $this->core->media->extensions_subtitle));
			$items = 0;
			$total = \count($files);
			foreach($files as $file){
				$items++;
				if(!\file_exists($file)) continue 1;
				$file_name = \pathinfo($file, PATHINFO_FILENAME);
				$quality_tag = '';
				$start = \strrpos($file_name, '(');
				if($start !== false){
					$end = \strpos($file_name, ')', $start);
					if($end !== false){
						$quality_tag = \substr($file_name, $start, $end - $start + 1);
						if(\strpos($quality_tag, '_') === false){
							$quality_tag = '';
						}
					}
				}
				if(!empty($quality_tag)){
					$escaped_name = \trim(\str_replace($quality_tag, '', $file_name), ' ');
				} else {
					$escaped_name = '';
				}

				if(empty($quality_tag)){
					$this->core->write_error("FAILED GET YOUTUBE QUALITY TAG \"$file\"");
					$errors++;
				} elseif(empty($escaped_name)){
					$this->core->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} else {
					$new_name = $this->core->get_path(\pathinfo($file, PATHINFO_DIRNAME)."/$escaped_name.".$this->core->get_extension($file));
					if(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
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

	public function tool_series_episode_editor() : bool {
		$this->core->set_subtool("Series episode editor");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Modes:',
			' 0   - Change season number',
			' 1   - Change episode numbers',
		]);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$params = [
			'mode' => \strtolower($line[0] ?? '?'),
		];

		if(!\in_array($params['mode'], ['0', '1'])) goto set_mode;
		switch($params['mode']){
			case '0': return $this->tool_series_episode_editor_action_season();
			case '1': return $this->tool_series_episode_editor_action_episode();
		}
		return false;
	}

	public function tool_series_episode_editor_action_season() : bool {
		$this->core->clear();
		$this->core->set_subtool("Series episode editor > Change season");

		$this->core->print_help([
			" Attention file name must begin with the season and episode number in the format:",
			" \"S00E00{whatever}.{extension}\"",
			" \"S00E000{whatever}.{extension}\"",
		]);

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		$this->core->echo(" Example: 1 or 01 (up to 99)");
		set_season_current:
		$line = $this->core->get_input(" Current season: ");
		if($line == '#') return false;
		$current_season = \substr(\preg_replace('/\D/', '', $line), 0, 2);
		if($current_season == '') goto set_season_current;
		if(\strlen($current_season) == 1) $current_season = "0$current_season";

		set_season_new:
		$line = $this->core->get_input(" New season: ");
		if($line == '#') return false;
		$new_season = \substr(\preg_replace('/\D/', '', $line), 0, 2);
		if($new_season == '') goto set_season_new;
		if(\strlen($new_season) == 1) $new_season = "0$new_season";

		$follow_extensions = \explode(" ", $this->core->config->get('EXTENSIONS_VIDEO_FOLLOW'));
		$files = $this->core->get_files($input, \array_merge($this->core->media->extensions_video, $follow_extensions));
		$items = 0;
		$total = \count($files);

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($files as $file){
			$items++;
			if(!\file_exists($file)) continue;
			$file_name = \pathinfo($file, PATHINFO_FILENAME);
			if(\preg_match("/S[0-9]{2}E[0-9]{1,3}/", $file_name, $mathes) == 1){
				$serie_id = \substr($file_name, 1, 2);
				if($serie_id == $current_season){
					$directory = \pathinfo($file, PATHINFO_DIRNAME);
					$extension = $this->core->get_extension($file);
					$file_name = "S$new_season".\substr($file_name, 3);
					$new_name = $this->core->get_path("$directory/$file_name.$extension");
					if(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
						$this->core->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
						$errors++;
					} else {
						if(!$this->core->move($file, $new_name)){
							$errors++;
						}
					}
				}
			} else {
				$this->core->write_error("FAILED GET SERIES ID \"$file\"");
				$errors++;
			}

			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$this->core->progress($items, $total);

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_series_episode_editor_action_episode() : bool {
		$this->core->clear();
		$this->core->set_subtool("Series episode editor > Change episode");

		$this->core->print_help([
			" Attention file name must begin with the season and episode number in the format:",
			" \"S00E00{whatever}.{extension}\"",
			" \"S00E000{whatever}.{extension}\"",
		]);

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		$this->core->echo(" Choose episodes to edit (example 01 or 001)");

		set_start:
		$line = $this->core->get_input(" Start: ");
		if($line == '#') return false;
		$episode_start = \substr(\preg_replace('/\D/', '', $line), 0, 3);
		if($episode_start == '') goto set_start;
		if($episode_start[0] == '0') $episode_start = \substr($episode_start, 1);
		$episode_start = \intval($episode_start);

		set_end:
		$line = $this->core->get_input(" End: ");
		if($line == '#') return false;
		$episode_end = \substr(\preg_replace('/\D/', '', $line), 0, 3);
		if($episode_end == '') goto set_end;
		if($episode_end[0] == '0') $episode_end = \substr($episode_end, 1);
		$episode_end = \intval($episode_end);

		$this->core->echo(" Choose step as integer (example 5 or -5)");
		$line = $this->core->get_input(" Step: ");
		if($line == '#') return false;
		$episode_step = \intval(\substr(\preg_replace("/[^0-9\-]/", '', $line), 0, 3));

		$errors = 0;
		$list = [];
		$follow_extensions = \explode(" ", $this->core->config->get('EXTENSIONS_VIDEO_FOLLOW'));
		$files = $this->core->get_files($input, \array_merge($this->core->media->extensions_video, $follow_extensions));
		foreach($files as $file){
			if(!\file_exists($file)) continue 1;
			$file_name = \pathinfo($file, PATHINFO_FILENAME);
			$episode = null;
			if(\preg_match("/S[0-9]{2}E[0-9]{3}/", $file_name, $mathes) == 1){
				$digits = 3;
				$max = 999;
				$episode = \intval(\ltrim(\substr($file_name, 4, 3), "0"));
				$name = \substr($file_name, 7);
			} elseif(\preg_match("/S[0-9]{2}E[0-9]{2}/", $file_name, $mathes) == 1){
				$digits = 2;
				$max = 99;
				$episode = \intval(\ltrim(\substr($file_name, 4, 2), "0"));
				$name = \substr($file_name, 6);
			} elseif(\preg_match("/S[0-9]{2}E[0-9]{1}/", $file_name, $mathes) == 1){
				$digits = 2;
				$max = 99;
				$episode = \intval(\substr($file_name, 4, 1));
				$name = \substr($file_name, 5);
			}
			if(\is_null($episode)){
				$this->core->write_error("FAILED GET EPISODE ID \"$file\"");
				$errors++;
			} else {
				$season = \substr($file_name, 0, 3);
				if($episode <= $episode_end && $episode >= $episode_start){
					$directory = \pathinfo($file, PATHINFO_DIRNAME);
					$extension = $this->core->get_extension($file);
					$new_name = $this->core->get_path("$directory/$season"."E".$this->core->media->format_episode($episode + $episode_step, $digits, $max)."$name.$extension");
					\array_push($list, [
						'input' => $file,
						'output' => $new_name,
					]);
				}
			}
		}

		if($episode_step > 0) $list = \array_reverse($list);

		$items = 0;
		$total = \count($list);
		$round = 0;
		change_names:
		foreach($list as $key => $item){
			if(\file_exists($item['output']) && $round == 0) continue;
			$items++;
			if(\file_exists($item['output'])){
				$this->core->write_error("UNABLE CHANGE NAME \"{$item['input']}\" TO \"{$item['output']}\" FILE ALREADY EXIST");
				$errors++;
			} else {
				if(!$this->core->move($item['input'], $item['output'])) $errors++;
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
			unset($list[$key]);
		}
		$this->core->progress($items, $total);
		if($round == 0){
			$round++;
			goto change_names;
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_add_file_name_prefix_suffix() : bool {
		$this->core->clear();
		$this->core->set_subtool("Add file name prefix/suffix");

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$extensions = $this->core->get_input_extensions(" Extensions: ");
		if($extensions === false) return false;

		$prefix = $this->core->get_input(" Prefix (may be empty): ", false);
		if($prefix == '#') return false;
		$prefix = \str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*'], '', $prefix);

		$suffix = $this->core->get_input(" Suffix (may be empty): ", false);
		if($suffix == '#') return false;
		$suffix = \str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*'], '', $suffix);

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
				$new_name = $this->core->get_path(\pathinfo($file, PATHINFO_DIRNAME)."/$prefix".\pathinfo($file, PATHINFO_FILENAME).$suffix.".".$this->core->get_extension($file));
				if(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
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

	public function tool_remove_keywords_from_file_name() : bool {
		$this->core->clear();
		$this->core->set_subtool("Remove keywords from file name");

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
			'mode' => \strtolower($line[0] ?? '?'),
		];

		if(!\in_array($params['mode'], ['0', '1'])) goto set_mode;

		$this->core->clear();

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$extensions = $this->core->get_input_extensions(" Extensions: ");
		if($extensions === false) return false;

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

			$fp = \fopen($input, 'r');
			if(!$fp){
				$this->core->echo(" Failed open keywords file");
				goto set_keyword_file;
			}
			while(($line = \fgets($fp)) !== false){
				$line = \str_replace(["\n", "\r", $this->core->utf8_bom], "", $line);
				if(empty(\trim($line))) continue;
				\array_push($keywords, $line);
			}
			\fclose($fp);
		}

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
				$name = \trim(\str_replace($keywords, '', \pathinfo($file, PATHINFO_FILENAME)));
				$new_name = $this->core->get_path(\pathinfo($file, PATHINFO_DIRNAME)."/$name.".$this->core->get_extension($file));
				if(empty($new_name)){
					$this->core->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} elseif(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
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

	public function tool_insert_string_into_file_name() : bool {
		$this->core->set_subtool("Insert string into file name");

		set_offset:
		$this->core->clear();
		$this->core->print_help([
			' Specify the string offset where you want insert into file name',
			' Offset = 0 - means the beginning, i.e. the string will be inserted before the file name (prefix)',
			' Offset > 0 - means that the string will be inserted after skipping N characters',
			' Offset < 0 - means that the string will be inserted after skipping N characters from the end',
		]);
		$line = $this->core->get_input(" Offset: ");
		if($line == '#') return false;
		$offset = \preg_replace("/[^0-9\-]/", '', $line);
		if($offset == '') goto set_offset;
		$offset = \intval($offset);

		$this->core->print_help([
			' Specify the string you want to inject the file name, may contain spaces',
		]);
		$insert_string = $this->core->get_input(" String: ", false);

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
				$name = \pathinfo($file, PATHINFO_FILENAME);
				if(\abs($offset) > \strlen($name)){
					$this->core->write_error("ILLEGAL OFFSET FOR FILE NAME \"$file\"");
					$errors++;
				} else {
					if($offset > 0){
						$name = \substr($name, 0, $offset).$insert_string.\substr($name, $offset);
					} elseif($offset < 0){
						$name = \substr($name, 0, \strlen($name) + $offset).$insert_string.\substr($name, $offset);
					} else {
						$name = $insert_string.$name;
					}
					$new_name = $this->core->get_path(\pathinfo($file, PATHINFO_DIRNAME)."/$name.".$this->core->get_extension($file));
					if(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
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

	public function tool_replace_keywords_in_file_name() : bool {
		$this->core->clear();
		$this->core->set_subtool("Replace keywords in file name");

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$extensions = $this->core->get_input_extensions(" Extensions: ");
		if($extensions === false) return false;

		set_keyword_file:
		$input = $this->core->get_input_file(" Keywords file: ", true);
		if($input === false) return false;

		$fp = \fopen($input, 'r');
		if(!$fp){
			$this->core->echo(" Failed open keywords file");
			goto set_keyword_file;
		}

		$replacements = [];
		$i = 0;
		$errors = 0;
		while(($line = \fgets($fp)) !== false){
			$i++;
			$line = \str_replace(["\n", "\r", $this->core->utf8_bom], "", $line);
			if(empty(\trim($line))) continue;
			$replace = $this->core->parse_input_path($line, false);
			if(!isset($replace[0]) || !isset($replace[1]) || isset($replace[2])){
				$this->core->echo(" Failed parse replacement in line $i content: '$line'");
				$errors++;
			} else {
				$replacements[$replace[0]] = $replace[1];
			}
		}
		\fclose($fp);

		if($errors > 0){
			if(!$this->core->get_confirm(" Errors detected, continue with valid replacement (Y/N): ")) goto set_keyword_file;
		}

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
				$name = \trim(\str_replace(\array_keys($replacements), $replacements, \pathinfo($file, PATHINFO_FILENAME)));
				$new_name = $this->core->get_path(\pathinfo($file, PATHINFO_DIRNAME)."/$name.".$this->core->get_extension($file));
				if(empty($new_name)){
					$this->core->write_error("ESCAPED NAME IS EMPTY \"$file\"");
					$errors++;
				} elseif(\file_exists($new_name) && \mb_strtoupper($new_name) != \mb_strtoupper($file)){
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

	public function tool_extension_change() : bool {
		$this->core->clear();
		$this->core->set_subtool("Extension change");

		$folders = $this->core->get_input_multiple_folders(" Folders: ", false);
		if($folders === false) return false;

		$extension_old = \mb_strtolower($this->core->get_input(" Extension old: "));
		if($extension_old == '#') return false;

		$extension_new = $this->core->get_input(" Extension new: ");
		if($extension_new == '#') return false;

		$this->core->setup_folders($folders);

		$errors = 0;
		$this->core->set_errors($errors);

		foreach($folders as $folder){
			if(!\file_exists($folder)) continue;
			$files = $this->core->get_files($folder, [$extension_old]);
			$items = 0;
			$total = \count($files);
			foreach($files as $file){
				$items++;
				if(!\file_exists($file)) continue 1;
				$new_name = $this->core->get_path(\pathinfo($file, PATHINFO_DIRNAME)."/".\pathinfo($file, PATHINFO_FILENAME));
				if(!empty($extension_new)) $new_name .= ".$extension_new";
				if(!$this->core->move($file, $new_name)){
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