<?php

declare(strict_types=1);

namespace NGC\Tools;

use Exception;
use Toolkit;
use Imagick;

class MediaSorter {

	private string $name = "Media Sorter";
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
			' 0 - Sort Files: Date',
			' 1 - Sort Files: Extension',
			' 2 - Sort GIF/WEBP: Animated/NotAnimated',
			' 3 - Sort Media: Quality',
			' 4 - Sort Images: Colors count',
			' 5 - Sort Videos: Auto detect series name',
			' 6 - Sort Media: Duration',
			' 7 - Sort Files: Size',
			' 8 - Sort Folders: Items quantity (First parent)',
			' 9 - Sort Images: Monochrome',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_sort_date();
			case '1': return $this->tool_sort_extension();
			case '2': return $this->tool_sort_gif_animated();
			case '3': return $this->tool_sort_media_quality();
			case '4': return $this->tool_sort_images_color();
			case '5': return $this->tool_sort_videos_auto_detect_series_name();
			case '6': return $this->tool_sort_media_duration();
			case '7': return $this->tool_sort_files_size();
			case '8': return $this->tool_sort_folders_quantity();
			case '9': return $this->tool_sort_images_monochrome();
		}
		return false;
	}

	public function tool_sort_extension() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort extension");
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
				$extension = $this->core->get_extension($file);
				$new_name = $this->core->get_path("$folder/$extension/".pathinfo($file, PATHINFO_BASENAME));
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

	public function tool_sort_media_quality() : bool {
		$this->core->set_subtool("Sort media quality");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Modes:',
			' 0 - Orientation + Quality',
			' 1 - Orientation: Vertical / Horizontal / Square',
			' 2 - Quality: 17280p 8640p 4320p 2160p 1440p 1080p 720p 540p 480p 360p 240p 144p',
		]);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params['mode'] = strtolower($line[0] ?? '?');
		if(!in_array($this->params['mode'], ['0', '1', '2'])) goto set_mode;
		$this->params['resolution'] = in_array($this->params['mode'], ['0', '1']);
		$this->params['quality'] = in_array($this->params['mode'], ['0', '2']);

		$this->core->clear();
		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		$video_extensions = explode(" ", $this->core->config->get('EXTENSIONS_VIDEO'));
		$image_extensions = explode(" ", $this->core->config->get('EXTENSIONS_PHOTO'));
		$extensions = array_merge($image_extensions, $video_extensions);
		foreach($folders as $folder){
			$files = $this->core->get_files($folder, $extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$extension = $this->core->get_extension($file);
				if(in_array($extension, $image_extensions)){
					$resolution = $this->core->media->get_image_resolution($file);
				} else {
					$resolution = $this->core->media->ffprobe_get_resolution($file);
				}
				if($resolution == '0x0'){
					$this->core->write_error("FAILED GET MEDIA RESOLUTION \"$file\"");
					$errors++;
					$this->core->set_errors($errors);
					continue 1;
				}
				$size = explode("x", $resolution);
				$is_vr = $this->core->media->is_vr_video($file);
				$is_ar = $this->core->media->is_ar_video($file);
				$quality = $this->core->media->get_media_quality(intval($size[0]), intval($size[1]), $is_vr || $is_ar).$this->core->config->get('QUALITY_SUFFIX');
				$orientation_name = $this->core->media->get_media_orientation_name($this->core->media->get_media_orientation(intval($size[0]), intval($size[1])));
				if($this->params['resolution'] && $this->params['quality']){
					$directory = "$folder/$orientation_name/$quality";
				} else if($this->params['resolution']){
					$directory = "$folder/$orientation_name";
				} else if($this->params['quality']){
					$directory = "$folder/$quality";
				}
				if(!$this->core->move($file, $this->core->get_path("$directory/".pathinfo($file, PATHINFO_BASENAME)))){
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

	public function tool_sort_gif_animated() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort gif animated");
		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);

		$this->core->setup_folders($folders);

		$errors = 0;
		$this->core->set_errors($errors);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, ['gif', 'webp']);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				if($this->core->media->is_gif_animated($file)){
					$directory = "$folder/Animated";
				} else {
					$directory = "$folder/NotAnimated";
				}
				$new_name = $this->core->get_path("$directory/".pathinfo($file, PATHINFO_BASENAME));
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

	public array $tool_sortdate_mode = [
		'0' => 'YYYYxMMxDD',
		'1' => 'YYYYxMM',
		'2' => 'YYYY',
		'3' => 'YYxMMxDD',
		'4' => 'DDxMMxYY',
		'5' => 'DDxMMxYYYY',
		'6' => 'YYYYxMMxDDxhh',
		'7' => 'YYYYxMMxDDxhhxmm',
	];

	public function tool_sort_date() : bool {
		$this->core->set_subtool("Sort date");

		set_mode:
		$this->core->clear();
		$help = [' Modes:'];
		foreach($this->tool_sortdate_mode as $mode_key => $mode_name){
			array_push($help, " $mode_key $mode_name");
		}
		$this->core->print_help($help);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params['mode'] = strtolower($line[0] ?? '?');
		if(!in_array($this->params['mode'], ['0', '1', '2', '3', '4', '5', '6', '7'])) goto set_mode;

		set_separator:
		$this->core->clear();
		$this->core->print_help([
			' Separators:',
			' . - _ \ @',
		]);

		$separator = $this->core->get_input(" Separator: ");
		if($separator == '#') return false;
		$this->params['separator'] = strtolower($separator[0] ?? '?');
		if(!in_array($this->params['separator'], ['.', '-', '_', '\\', '@'])) goto set_separator;
		if($this->params['separator'] == '\\') $this->params['separator'] = DIRECTORY_SEPARATOR;

		$this->core->clear();
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
				$new_name = $this->tool_sort_date_get_pattern($folder, $this->params['mode'], $file, $this->params['separator']);
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

	public function tool_sort_date_get_pattern(string $folder, string $mode, string $file, string $separator) : string {
		return $this->core->get_path("$folder/".str_replace("-", $separator, $this->tool_sort_date_format_date($mode, filemtime($file)))."/".pathinfo($file, PATHINFO_BASENAME));
	}

	public function tool_sort_date_format_date(string $mode, int $date) : string {
		switch($mode){
			case '0': return date('Y-m-d', $date);
			case '1': return date('Y-m', $date);
			case '2': return date('Y', $date);
			case '3': return date('y-m-d', $date);
			case '4': return date('d-m-y', $date);
			case '5': return date('d-m-Y', $date);
			case '6': return date('Y-m-d-h', $date);
			case '7': return date('Y-m-d-h-i', $date);
		}
		return '';
	}

	public function tool_sort_images_color() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort images color");
		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);

		$this->core->setup_folders($folders);

		$errors = 0;
		$this->core->set_errors($errors);
		$image_extensions = explode(" ", $this->core->config->get('EXTENSIONS_PHOTO'));
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $image_extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$colors = $this->core->media->get_image_color_count($file);
				if(is_null($colors)){
					$group = 'Unknown';
				} else {
					$group = $this->core->media->get_image_color_group($colors);
				}
				$new_name = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/$group/".pathinfo($file, PATHINFO_BASENAME));
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

	public function tool_sort_videos_auto_detect_series_name() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort videos auto detect series name");

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

		$errors = 0;
		$this->core->set_errors($errors);
		$video_extensions = explode(" ", $this->core->config->get('EXTENSIONS_VIDEO'));
		$files = $this->core->get_files($input, $video_extensions);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$file_name = str_replace(['SEASON', 'EPISODE'], ['S', 'E'], mb_strtoupper(pathinfo($file, PATHINFO_FILENAME)));
			$file_name = str_replace([' ', '.', '[', ']'], '', $file_name);
			if(preg_match("/S[0-9]{1,2}E[0-9]{1,3}E[0-9]{1,3}/", $file_name, $mathes) == 1){
				$marker = $mathes[0];
			} else if(preg_match("/S[0-9]{1,2}E[0-9]{1,3}-E[0-9]{1,3}/", $file_name, $mathes) == 1){
				$marker = $mathes[0];
			} else if(preg_match("/S[0-9]{1,2}E[0-9]{1,3}-[0-9]{1,3}/", $file_name, $mathes) == 1){
				$marker = $mathes[0];
			} else if(preg_match("/(S[0-9]{1,2})(E[0-9]{1,3})/", $file_name, $mathes) == 1){
				if(strlen($mathes[1]) == 2) $mathes[1] = "S0".substr($mathes[1], 1, 1);
				if(strlen($mathes[2]) == 2) $mathes[2] = "E0".substr($mathes[2], 1, 1);
				$marker = $mathes[1].$mathes[2];
			} else if(preg_match("/(S0)(E[0-9]{1,3})/", $file_name, $mathes) == 1){
				$marker = "S01".preg_replace("/[^E0-9]/i", "", $mathes[2], 1);
			} else {
				$marker = '';
			}
			if(!empty($marker)){
				$end = strpos($file_name, $marker);
				if($end === false){
					$this->core->write_error("FAILED GET MARKER \"$file\"");
					$errors++;
				} else {
					$folder_name = str_replace(['_', '.', "\u{00A0}"], ' ', substr(pathinfo($file, PATHINFO_FILENAME), 0, $end));
					$folder_name = str_replace([';', '@', '#', '~', '!', '$', '%', '^', '&'], '', $folder_name);
					while(str_contains($folder_name, '  ')){
						$folder_name = str_replace('  ', ' ', $folder_name);
					}
					$folder_name = trim($folder_name, ' ');
					if(empty($folder_name)){
						$this->core->write_error("ESCAPED FOLDER NAME IS EMPTY \"$file\"");
						$errors++;
					} else {
						$new_name = $this->core->get_path("$input/$folder_name/".pathinfo($file, PATHINFO_BASENAME));
						if(file_exists($new_name) && mb_strtoupper($new_name) != mb_strtoupper($file)){
							$this->core->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
							$errors++;
						} else {
							if(!$this->core->move($file, $new_name)){
								$errors++;
							}
						}
					}
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

	public function tool_sort_media_duration() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort media duration");

		$interval = $this->core->get_input_time_interval(" Interval: ");
		if(!$interval) return false;

		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		$extensions = array_merge(explode(" ", $this->core->config->get('EXTENSIONS_VIDEO')), explode(" ", $this->core->config->get('EXTENSIONS_AUDIO')));
		foreach($folders as $folder){
			$files = $this->core->get_files($folder, $extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$meta = $this->core->media->get_media_info_simple($file);
				if(!$meta){
					$this->core->write_error("FAILED GET MEDIA INFO \"$file\"");
					$errors++;
					continue;
				}
				$multiplier = max(floor(($meta->video_duration_seconds - 1) / $interval), 0);
				if($multiplier == 0){
					$start = '00_00';
				} else {
					$start = str_replace(":", "_", $this->core->seconds_to_time(intval($interval * $multiplier) + 1));
				}
				$end = str_replace(":", "_", $this->core->seconds_to_time(intval($interval * ($multiplier + 1))));
				$directory = "$folder/$start - $end";
				$new_name = $this->core->get_path("$directory/".pathinfo($file, PATHINFO_BASENAME));
				if($this->core->move($file, $new_name)){
					$renamed = true;
				} else {
					$renamed = false;
					$errors++;
				}
				if($renamed){
					$name = pathinfo($file, PATHINFO_FILENAME);
					$follow_extensions = explode(" ", $this->core->config->get('EXTENSIONS_VIDEO_FOLLOW'));
					foreach($follow_extensions as $a){
						if(file_exists("$file.$a")){
							if(!$this->core->move("$file.$a", "$new_name.$a")) $errors++;
						}
						$name_old = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/$name.$a");
						$name_new = $this->core->get_path("$directory/$name.$a");
						if(file_exists($name_old)){
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

	public function tool_sort_files_size() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort files size");

		$interval = $this->core->get_input_bytes_size(" Size: ");
		if(!$interval) return false;

		$prefix = $this->core->get_confirm(" Add numeric prefix for better sort folders (Y/N): ");

		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			$files = $this->core->get_files($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$size = filesize($file);
				$multiplier = floor(($size-1) / $interval);
				if($size == 0) $multiplier = 0;
				$end = $this->core->format_bytes(intval($interval * ($multiplier + 1)));
				if($prefix){
					$directory = "$folder/".sprintf("%06d", $multiplier)." $end";
				} else {
					$directory = "$folder/$end";
				}
				$new_name = $this->core->get_path("$directory/".pathinfo($file, PATHINFO_BASENAME));
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

	public function tool_sort_folders_quantity() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort folders quantity");

		$interval = $this->core->get_input_integer(" Quantity interval: ");
		if(!$interval) return false;

		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		$this->core->setup_folders($folders);
		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			$files = $this->core->get_folders_ex($folder);
			foreach($files as $file){
				if(!file_exists($file)) continue 1;
				$quantity = count($this->core->get_files($file));
				$multiplier = floor(($quantity-1) / $interval);
				if($quantity == 0) $multiplier = 0;
				$end = intval($interval * ($multiplier + 1));
				$new_name = $this->core->get_path("$folder/$end/".pathinfo($file, PATHINFO_BASENAME));
				if(!$this->core->move($file, $new_name)){
					$errors++;
				}
				$this->core->set_errors($errors);
			}
			unset($files);
			$this->core->set_folder_done($folder);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_sort_images_monochrome() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort images monochrome");
		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);

		$this->core->setup_folders($folders);

		$errors = 0;
		$this->core->set_errors($errors);
		$image_extensions = explode(" ", $this->core->config->get('EXTENSIONS_PHOTO'));
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $image_extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				try {
					$image = new Imagick($file);
				}
				catch(Exception $e){
					$this->core->write_error(" Failed open image \"$file\" ".$e->getMessage());
					$errors++;
					$this->core->set_errors($errors);
					continue 1;
				}
				$image->setImageColorspace(Imagick::COLORSPACE_RGB);
				$histogram = $image->getImageHistogram();
				$is_monochrome = true;
				$tolerance = 50;
				foreach($histogram as $pixel){
					$color = $pixel->getColor();
					if(abs($color['r'] - $color['g']) > $tolerance || abs($color['g'] - $color['b']) > $tolerance || abs($color['b'] - $color['r']) > $tolerance){
						$is_monochrome = false;
						break;
					}
				}
				$image->clear();
				if($is_monochrome && $this->core->get_extension($file) != 'gif'){
					$group = 'Monochrome';
				} else {
					$group = 'Normal';
				}
				$new_name = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/$group/".pathinfo($file, PATHINFO_BASENAME));
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