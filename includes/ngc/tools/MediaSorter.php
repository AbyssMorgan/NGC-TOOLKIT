<?php

/**
 * NGC-TOOLKIT v2.7.0 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Tools;

use Exception;
use Toolkit;
use Imagick;

class MediaSorter {

	private string $name = "Media Sorter";
	private string $action;
	private Toolkit $core;

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
	}

	public function help() : void {
		$this->core->print_help([
			' Actions:',
			' 0 - Sort by quality (Video/Images)',
			' 1 - Sort by colors count (Images)',
			' 2 - Sort by duration (Video/Audio)',
			' 3 - Sort by animated (Images)',
			' 4 - Sort by monochrome (Images)',
			' 5 - Sort by auto detect series name (Video)',
		]);
	}

	public function action(string $action) : bool {
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_sort_media_quality();
			case '1': return $this->tool_sort_media_colors_count();
			case '2': return $this->tool_sort_media_duration();
			case '3': return $this->tool_sort_media_animated();
			case '4': return $this->tool_sort_media_monochrome();
			case '5': return $this->tool_sort_media_auto_detect_series_name();
		}
		return false;
	}

	public function tool_sort_media_quality() : bool {
		$this->core->set_subtool("Sort by quality");

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

		$params['mode'] = strtolower($line[0] ?? '?');
		if(!in_array($params['mode'], ['0', '1', '2'])) goto set_mode;
		$params['resolution'] = in_array($params['mode'], ['0', '1']);
		$params['quality'] = in_array($params['mode'], ['0', '2']);

		$this->core->clear();

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		$extensions = array_merge($this->core->media->extensions_images, $this->core->media->extensions_video);
		foreach($folders as $folder){
			$files = $this->core->get_files($folder, $extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$extension = $this->core->get_extension($file);
				if(in_array($extension, $this->core->media->extensions_images)){
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
				$quality = $this->core->media->get_media_quality(intval($size[0]), intval($size[1]), $is_vr || $is_ar)."p";
				$orientation_name = $this->core->media->get_media_orientation_name($this->core->media->get_media_orientation(intval($size[0]), intval($size[1])));
				if($params['resolution'] && $params['quality']){
					$directory = "$folder/$orientation_name/$quality";
				} elseif($params['resolution']){
					$directory = "$folder/$orientation_name";
				} elseif($params['quality']){
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

	public function tool_sort_media_colors_count() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort by colors count");

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $this->core->media->extensions_images);
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
				if(!$this->core->move($file, $this->core->put_folder_to_path($file, $group))){
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

	public function tool_sort_media_duration() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort by duration");

		$interval = $this->core->get_input_time_interval(" Interval: ");
		if($interval === false) return false;

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		$extensions = array_merge($this->core->media->extensions_video, $this->core->media->extensions_audio);
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
				$new_name = $this->core->put_folder_to_path($file, "$start - $end");
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
						if(file_exists($name_old)){
							if(!$this->core->move($name_old, $this->core->put_folder_to_path($name_old, "$start - $end"))) $errors++;
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

	public function tool_sort_media_animated() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort by animated");

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, ['gif', 'webp', 'apng']);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				if($this->core->media->is_image_animated($file)){
					$directory = "Animated";
				} else {
					$directory = "NotAnimated";
				}
				if(!$this->core->move($file, $this->core->put_folder_to_path($file, $directory))){
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

	public function tool_sort_media_monochrome() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort by monochrome");

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, $this->core->media->extensions_images);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				try {
					$image = new Imagick($file);
				}
				catch(Exception $e){
					$this->core->write_error("FAILED OPEN IMAGE \"$file\" ".$e->getMessage());
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
				if(!$this->core->move($file, $this->core->put_folder_to_path($file, $group))){
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

	public function tool_sort_media_auto_detect_series_name() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort by auto detect series name");

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		$files = $this->core->get_files($input, $this->core->media->extensions_video);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$file_name = str_replace(['SEASON', 'EPISODE'], ['S', 'E'], mb_strtoupper(pathinfo($file, PATHINFO_FILENAME)));
			$file_name = str_replace([' ', '.', '[', ']'], ' ', $file_name);
			$mathes = [];
			if(preg_match("/S[0-9]{1,2}E[0-9]{1,3}E[0-9]{1,3}/", $file_name, $mathes) == 1){
				$marker = $mathes[0];
			} elseif(preg_match("/S[0-9]{1,2}E[0-9]{1,3}-E[0-9]{1,3}/", $file_name, $mathes) == 1){
				$marker = $mathes[0];
			} elseif(preg_match("/S[0-9]{1,2}E[0-9]{1,3}-[0-9]{1,3}/", $file_name, $mathes) == 1){
				$marker = $mathes[0];
			} elseif(preg_match("/(S[0-9]{1,2})(E[0-9]{1,3})/", $file_name, $mathes) == 1){
				if(strlen($mathes[1]) == 2) $mathes[1] = "S0".substr($mathes[1], 1, 1);
				if(strlen($mathes[2]) == 2) $mathes[2] = "E0".substr($mathes[2], 1, 1);
				$marker = $mathes[1].$mathes[2];
			} elseif(preg_match("/(S0)(E[0-9]{1,3})/", $file_name, $mathes) == 1){
				$marker = "S01".preg_replace("/[^E0-9]/i", "", $mathes[2], 1);
			} else {
				$marker = '';
			}
			if(!empty($marker)){
				$end = strpos(mb_strtoupper(pathinfo($file, PATHINFO_FILENAME)), $marker);
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
						$new_name = $this->core->put_folder_to_path($file, $folder_name);
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

}

?>