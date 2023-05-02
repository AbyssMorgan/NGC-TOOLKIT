<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use App\Dictionaries\MediaOrientation;
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
			' 0 - Sort Files:  Date',
			' 1 - Sort Files:  Extension',
			' 2 - Sort Gif:    Animated',
			' 3 - Sort Media:  Quality',
			' 4 - Sort Images: Colors count',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolSortDate();
			case '1': return $this->ToolSortExtension();
			case '2': return $this->ToolSortGifAnimated();
			case '3': return $this->ToolSortMedia();
			case '4': return $this->ToolSortImagesColor();
		}
		return false;
	}

	public function ToolSortExtension() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SortExtension");
		echo " Folders: ";
		$line = $this->ave->get_input();
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
				$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				$directory = $this->ave->get_file_path("$folder/$extension");
				if(!file_exists($directory)){
					if(!$this->ave->mkdir($directory)){
						$errors++;
						$this->ave->set_progress($progress, $errors);
						continue 1;
					}
				}
				$new_name = $this->ave->get_file_path("$folder/$extension".pathinfo($file, PATHINFO_BASENAME));
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

	public function ToolSortMedia() : bool {
		$this->ave->set_subtool("SortMedia");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Modes:',
			' 0 - Orientation + Quality',
			' 1 - Orientation: Vertical / Horizontal / Square',
			' 2 - Quality:    17280p 8640p 4320p 2160p 1440p 1080p 720p 540p 480p 360p 240p 144p',
		]);

		echo " Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;

		$this->params['mode'] = strtolower($line[0] ?? '?');
		if(!in_array($this->params['mode'],['0','1','2'])) goto set_mode;
		$this->params['resolution'] = in_array($this->params['mode'],['0','1']);
		$this->params['quality'] = in_array($this->params['mode'],['0','2']);

		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$image_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		$extensions = array_merge($image_extensions, $video_extensions);
		$media = new MediaFunctions();
		foreach($folders as $folder){
			$files = $this->ave->getFiles($folder, $extensions);
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
					$this->ave->write_error("FAILED GET_MEDIA_RESOLUTION \"$file\"");
					$errors++;
					$this->ave->set_progress($progress, $errors);
					continue 1;
				}
				$size = explode("x",$resolution);
				$quality = $media->getMediaQuality(intval($size[0]), intval($size[1]));

				switch($media->getMediaOrientation(intval($size[0]), intval($size[1]))){
					case MediaOrientation::MEDIA_ORIENTATION_HORIZONTAL: {
						$quality .= $this->ave->config->get('AVE_QUALITY_SUFFIX_HORIZONTAL');
						$orientation = "Horizontal";
						break;
					}
					case MediaOrientation::MEDIA_ORIENTATION_VERTICAL: {
						$quality .= $this->ave->config->get('AVE_QUALITY_SUFFIX_VERTICAL');
						$orientation = "Vertical";
						break;
					}
					case MediaOrientation::MEDIA_ORIENTATION_SQUARE: {
						$quality .= $this->ave->config->get('AVE_QUALITY_SUFFIX_SQUARE');
						$orientation = "Square";
						break;
					}
				}
				if($this->params['resolution'] && $this->params['quality']){
					$directory = $this->ave->get_file_path("$folder/$orientation/$quality");
				} else if($this->params['resolution']){
					$directory = $this->ave->get_file_path("$folder/$orientation");
				} else if($this->params['quality']){
					$directory = $this->ave->get_file_path("$folder/$quality");
				}
				if(!file_exists($directory)){
					if(!$this->ave->mkdir($directory)){
						$errors++;
						continue;
					}
				}
				if($this->ave->rename($file, $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_BASENAME)))){
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

	public function ToolSortGifAnimated() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("SortGifAnimated");
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		$media = new MediaFunctions();

		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder, ['gif']);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				if($media->isGifAnimated($file)){
					$directory = $this->ave->get_file_path("$folder/Animated");
				} else {
					$directory = $this->ave->get_file_path("$folder/NotAnimated");
				}
				if(!file_exists($directory)){
					if(!$this->ave->mkdir($directory)){
						$errors++;
						$this->ave->set_progress($progress, $errors);
						continue 1;
					}
				}
				$new_name = $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_BASENAME));
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

		echo " Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;

		$this->params['mode'] = strtolower($line[0] ?? '?');
		if(!in_array($this->params['mode'],['0','1','2','3','4','5','6','7'])) goto set_mode;

		set_separator:
		$this->ave->clear();
		$this->ave->print_help([
			' Separators:',
			' . - _ \ @',
		]);

		echo " Separator: ";
		$separator = $this->ave->get_input();
		if($separator == '#') return false;
		$this->params['separator'] = strtolower($separator[0] ?? '?');
		if(!in_array($this->params['separator'],['.','-','_','\\','@'])) goto set_separator;
		if($this->params['separator'] == '\\') $this->params['separator'] = DIRECTORY_SEPARATOR;

		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
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
				$new_name = $this->ToolSortDateGetPattern($folder, $this->params['mode'], $file, $this->params['separator']);
				$directory = pathinfo($new_name, PATHINFO_DIRNAME);
				if(!file_exists($directory)){
					if(!$this->ave->mkdir($directory)){
						$errors++;
						$this->ave->set_progress($progress, $errors);
						continue 1;
					}
				}
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
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		$image_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		$media = new MediaFunctions();
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder, $image_extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$colors = $media->getImageColorCount($file);
				$group = $media->getImageColorGroup($colors);
				$directory = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/$group");
				if(!file_exists($directory)){
					if(!$this->ave->mkdir($directory)){
						$errors++;
						$this->ave->set_progress($progress, $errors);
						continue 1;
					}
				}
				$new_name = $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_BASENAME));
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

}

?>
