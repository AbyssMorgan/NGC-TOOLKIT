<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use App\Services\MediaFunctions;

class MediaSorter {

	private string $name = "MediaSorter";

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
			' 0 - Sort Files: Date',
			' 1 - Sort Files: Extension',
			' 2 - Sort Gif: Animated',
			' 3 - Sort Media: Quality',
			' 4 - Sort Images: Colors count',
			' 5 - Sort Videos: Auto detect series name',
			' 6 - Sort Media: Duration',
			' 7 - Sort Files: Size',
			' 8 - Sort Folders: Items quantity (First parent)',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolSortDate();
			case '1': return $this->ToolSortExtension();
			case '2': return $this->ToolSortGifAnimated();
			case '3': return $this->ToolSortMediaQuality();
			case '4': return $this->ToolSortImagesColor();
			case '5': return $this->ToolSortVideosAutoDetectSeriesName();
			case '6': return $this->ToolSortMediaDuration();
			case '7': return $this->ToolSortFilesSize();
			case '8': return $this->ToolSortFoldersQuantity();
		}
		return false;
	}

	public function ToolSortExtension() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SortExtension");
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);

		$this->ave->setup_folders($folders);

		$errors = 0;
		$this->ave->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->get_files($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				$new_name = $this->ave->get_file_path("$folder/$extension/".pathinfo($file, PATHINFO_BASENAME));
				if(!$this->ave->rename($file, $new_name)){
					$errors++;
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

	public function ToolSortMediaQuality() : bool {
		$this->ave->set_subtool("SortMediaQuality");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Modes:',
			' 0 - Orientation + Quality',
			' 1 - Orientation: Vertical / Horizontal / Square',
			' 2 - Quality: 17280p 8640p 4320p 2160p 1440p 1080p 720p 540p 480p 360p 240p 144p',
		]);

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params['mode'] = strtolower($line[0] ?? '?');
		if(!in_array($this->params['mode'], ['0', '1', '2'])) goto set_mode;
		$this->params['resolution'] = in_array($this->params['mode'], ['0', '1']);
		$this->params['quality'] = in_array($this->params['mode'], ['0', '2']);

		$this->ave->clear();
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		$this->ave->setup_folders($folders);
		$errors = 0;
		$this->ave->set_errors($errors);
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$image_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		$extensions = array_merge($image_extensions, $video_extensions);
		$media = new MediaFunctions($this->ave);
		foreach($folders as $folder){
			$files = $this->ave->get_files($folder, $extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				if(in_array($extension, $image_extensions)){
					$resolution = $media->getImageResolution($file);
				} else {
					$resolution = $media->getVideoResolution($file);
				}
				if($resolution == '0x0'){
					$this->ave->write_error("FAILED GET MEDIA RESOLUTION \"$file\"");
					$errors++;
					$this->ave->set_errors($errors);
					continue 1;
				}
				$size = explode("x", $resolution);
				$quality = $media->getMediaQuality(intval($size[0]), intval($size[1])).$this->ave->config->get('AVE_QUALITY_SUFFIX');
				$orientation_name = $media->getMediaOrientationName($media->getMediaOrientation(intval($size[0]), intval($size[1])));
				if($this->params['resolution'] && $this->params['quality']){
					$directory = "$folder/$orientation_name/$quality";
				} else if($this->params['resolution']){
					$directory = "$folder/$orientation_name";
				} else if($this->params['quality']){
					$directory = "$folder/$quality";
				}
				if(!$this->ave->rename($file, $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_BASENAME)))){
					$errors++;
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

	public function ToolSortGifAnimated() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SortGifAnimated");
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);

		$this->ave->setup_folders($folders);

		$errors = 0;
		$this->ave->set_errors($errors);

		$media = new MediaFunctions($this->ave);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->get_files($folder, ['gif']);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				if($media->isGifAnimated($file)){
					$directory = "$folder/Animated";
				} else {
					$directory = "$folder/NotAnimated";
				}
				$new_name = $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_BASENAME));
				if(!$this->ave->rename($file, $new_name)){
					$errors++;
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

	public function ToolSortDate() : bool {
		$this->ave->set_subtool("SortDate");

		set_mode:
		$this->ave->clear();
		$help = [' Modes:'];
		foreach($this->tool_sortdate_mode as $mode_key => $mode_name){
			array_push($help, " $mode_key $mode_name");
		}
		$this->ave->print_help($help);

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params['mode'] = strtolower($line[0] ?? '?');
		if(!in_array($this->params['mode'], ['0', '1', '2', '3', '4', '5', '6', '7'])) goto set_mode;

		set_separator:
		$this->ave->clear();
		$this->ave->print_help([
			' Separators:',
			' . - _ \ @',
		]);

		$separator = $this->ave->get_input(" Separator: ");
		if($separator == '#') return false;
		$this->params['separator'] = strtolower($separator[0] ?? '?');
		if(!in_array($this->params['separator'], ['.', '-', '_', '\\', '@'])) goto set_separator;
		if($this->params['separator'] == '\\') $this->params['separator'] = DIRECTORY_SEPARATOR;

		$this->ave->clear();
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);

		$this->ave->setup_folders($folders);

		$errors = 0;
		$this->ave->set_errors($errors);

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->get_files($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$new_name = $this->ToolSortDateGetPattern($folder, $this->params['mode'], $file, $this->params['separator']);
				if(!$this->ave->rename($file, $new_name)){
					$errors++;
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

	public function ToolSortDateGetPattern(string $folder, string $mode, string $file, string $separator) : string {
		return $this->ave->get_file_path("$folder/".str_replace("-", $separator, $this->ToolSortDateFormatDate($mode, filemtime($file)))."/".pathinfo($file, PATHINFO_BASENAME));
	}

	public function ToolSortDateFormatDate(string $mode, int $date) : string {
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

	public function ToolSortImagesColor() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SortImagesColor");
		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);

		$this->ave->setup_folders($folders);

		$errors = 0;
		$this->ave->set_errors($errors);
		$image_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		$media = new MediaFunctions($this->ave);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->get_files($folder, $image_extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$colors = $media->getImageColorCount($file);
				if(is_null($colors)){
					$group = 'Unknown';
				} else {
					$group = $media->getImageColorGroup($colors);
				}
				$new_name = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/$group/".pathinfo($file, PATHINFO_BASENAME));
				if(!$this->ave->rename($file, $new_name)){
					$errors++;
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

	public function ToolSortVideosAutoDetectSeriesName() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SortVideosAutoDetectSeriesName");

		set_input:
		$line = $this->ave->get_input(" Input: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->ave->echo(" Invalid input folder");
			goto set_input;
		}

		$errors = 0;
		$this->ave->set_errors($errors);
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$files = $this->ave->get_files($input, $video_extensions);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$file_name = str_replace(['SEASON', 'EPISODE'], ['S', 'E'], strtoupper(pathinfo($file, PATHINFO_FILENAME)));
			$file_name = str_replace([' ', '.', '[', ']'], '', $file_name);
			if(preg_match("/S[0-9]{1,2}E[0-9]{1,3}E[0-9]{1,3}/", $file_name, $mathes) == 1){
				$marker = $mathes[0];
			} else if(preg_match("/S[0-9]{1,2}E[0-9]{1,3}-E[0-9]{1,3}/", $file_name, $mathes) == 1){
				$marker = $mathes[0];
			} else if(preg_match("/S[0-9]{1,2}E[0-9]{1,3}-[0-9]{1,3}/", $file_name, $mathes) == 1){
				$marker = $mathes[0];
			} else if(preg_match("/(S[0-9]{1,2})(E[0-9]{1,3})/", $file_name, $mathes) == 1){
				if(strlen($mathes[1]) == 2) $mathes[1] = "S0".substr($mathes[1],1,1);
				if(strlen($mathes[2]) == 2) $mathes[2] = "E0".substr($mathes[2],1,1);
				$marker = $mathes[1].$mathes[2];
			} else if(preg_match("/(S0)(E[0-9]{1,3})/", $file_name, $mathes) == 1){
				$marker = "S01".preg_replace("/[^E0-9]/i", "", $mathes[2], 1);
			} else {
				$marker = '';
			}
			if(!empty($marker)){
				$end = strpos($file_name, $marker);
				if($end === false){
					$this->ave->write_error("FAILED GET MARKER \"$file\"");
					$errors++;
				} else {
					$folder_name = str_replace(['_', '.', "\u{00A0}"], ' ', substr(pathinfo($file, PATHINFO_FILENAME), 0, $end));
					$folder_name = str_replace([';', '@', '#', '~', '!', '$', '%', '^', '&'], '', $folder_name);
					while(strpos($folder_name, '  ') !== false){
						$folder_name = str_replace('  ', ' ', $folder_name);
					}
					$folder_name = trim($folder_name, ' ');
					if(empty($folder_name)){
						$this->ave->write_error("ESCAPED FOLDER NAME IS EMPTY \"$file\"");
						$errors++;
					} else {
						$new_name = $this->ave->get_file_path("$input/$folder_name/".pathinfo($file, PATHINFO_BASENAME));
						if(file_exists($new_name) && strtoupper($new_name) != strtoupper($file)){
							$this->ave->write_error("DUPLICATE \"$file\" AS \"$new_name\"");
							$errors++;
						} else {
							if(!$this->ave->rename($file, $new_name)){
								$errors++;
							}
						}
					}
				}
			}
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}
		$this->ave->progress($items, $total);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolSortMediaDuration() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SortMediaDuration");

		$interval = $this->ave->get_input_time_interval(" Interval: ");
		if(!$interval) return false;

		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		$this->ave->setup_folders($folders);
		$errors = 0;
		$this->ave->set_errors($errors);
		$extensions = array_merge(explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO')), explode(" ", $this->ave->config->get('AVE_EXTENSIONS_AUDIO')));
		$media = new MediaFunctions($this->ave);
		foreach($folders as $folder){
			$files = $this->ave->get_files($folder, $extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$duration = $media->getVideoDurationSeconds($file);
				$multiplier = floor($duration / $interval);
				if($multiplier == 0){
					$start = '00_00';
				} else {
					$start = str_replace(":", "_", $media->SecToTime(intval($interval * $multiplier) + 1));
				}
				$end = str_replace(":", "_", $media->SecToTime(intval($interval * ($multiplier + 1))));
				$directory = "$folder/$start - $end";
				$new_name = $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_BASENAME));
				if($this->ave->rename($file, $new_name)){
					$renamed = true;
				} else {
					$renamed = false;
					$errors++;
				}
				if($renamed){
					$name = pathinfo($file, PATHINFO_FILENAME);
					$follow_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO_FOLLOW'));
					foreach($follow_extensions as $a){
						if(file_exists("$file.$a")){
							if(!$this->ave->rename("$file.$a", "$new_name.$a")) $errors++;
						}
					}
					$name_old = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/$name.srt");
					$name_new = $this->ave->get_file_path("$directory/$name.srt");
					if(file_exists($name_old)){
						if(!$this->ave->rename($name_old, $name_new)){
							$errors++;
						}
					}
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

	public function ToolSortFilesSize() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SortFilesSize");

		$interval = $this->ave->get_input_bytes_size(" Size: ");
		if(!$interval) return false;

		$prefix = $this->ave->get_confirm(" Add numeric prefix for better sort folders (Y/N): ");

		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		$this->ave->setup_folders($folders);
		$errors = 0;
		$this->ave->set_errors($errors);
		foreach($folders as $folder){
			$files = $this->ave->get_files($folder);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$size = filesize($file);
				$multiplier = floor(($size-1) / $interval);
				if($size == 0) $multiplier = 0;
				$end = $this->ave->format_bytes(intval($interval * ($multiplier + 1)));
				if($prefix){
					$directory = "$folder/".sprintf("%06d", $multiplier)." $end";
				} else {
					$directory = "$folder/$end";
				}
				$new_name = $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_BASENAME));
				if(!$this->ave->rename($file, $new_name)){
					$errors++;
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

	public function ToolSortFoldersQuantity() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SortFoldersQuantity");

		$interval = $this->ave->get_input_integer(" Quantity interval: ");
		if(!$interval) return false;

		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		$this->ave->setup_folders($folders);
		$errors = 0;
		$this->ave->set_errors($errors);
		foreach($folders as $folder){
			$files = $this->ave->get_folders_ex($folder);
			foreach($files as $file){
				if(!file_exists($file)) continue 1;
				$quantity = count($this->ave->get_files($file));
				$multiplier = floor(($quantity-1) / $interval);
				if($quantity == 0) $multiplier = 0;
				$end = intval($interval * ($multiplier + 1));
				$new_name = $this->ave->get_file_path("$folder/$end/".pathinfo($file, PATHINFO_BASENAME));
				if(!$this->ave->rename($file, $new_name)){
					$errors++;
				}
				$this->ave->set_errors($errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

}

?>
