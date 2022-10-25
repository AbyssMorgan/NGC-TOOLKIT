<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use App\Dictionaries\MediaOrientation;

class MediaSorter {

	private string $name = "MediaSorter";

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
			' 0 - Sort Files:  Date',
			' 1 - Sort Files:  Extension',
			' 2 - Sort Gif:    Animated',
			' 3 - Sort Media:  Quality',
			' 4 - Sort Images: Colors count',
		]);
	}

	public function action(string $action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_sortdate_help();
			case '1': return $this->tool_sortextension_action();
			case '2': return $this->tool_gifanimated_action();
			case '3': return $this->tool_sortmedia_help();
			case '4': return $this->tool_sortimagescolor_help();
		}
		$this->ave->select_action();
	}

	public function tool_sortextension_action(){
		$this->ave->clear();
		$this->ave->set_subtool("SortExtension");
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
				$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				$directory = $folder.DIRECTORY_SEPARATOR."$extension";
				if(!file_exists($directory)){
					if(!$this->ave->mkdir($directory)){
						$errors++;
						$this->ave->set_progress($progress, $errors);
						continue 1;
					}
				}
				$new_name = $folder.DIRECTORY_SEPARATOR."$extension".DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_BASENAME);
				if($this->ave->rename($file, $new_name)){
					$progress++;
				} else {
					$errors++;
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->exit();
	}

	public function tool_sortmedia_help(){
		$this->ave->clear();
		$this->ave->set_subtool("SortMedia");

		$this->ave->print_help([
			' Modes:',
			' 0 - Resolution + Quality',
			' 1 - Resolution: Vertical / Horizontal / Square',
			' 2 - Quality:    17280p 8640p 4320p 2160p 1440p 1080p 720p 540p 480p 360p 240p 144p',
		]);

		echo " Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
		];

		if(!in_array($this->params['mode'],['0','1','2'])) return $this->tool_sortmedia_help();
		$this->params['resolution'] = in_array($this->params['mode'],['0','1']);
		$this->params['quality'] = in_array($this->params['mode'],['0','2']);
		$this->ave->set_subtool("SortMedia > ".$this->tool_sortmedia_name($this->params['mode']));
		return $this->tool_sortmedia_action();
	}

	public function tool_sortmedia_name(string $mode) : string {
		switch($mode){
			case '0': return 'Resolution + Quality';
			case '1': return 'Resolution';
			case '2': return 'Quality';
		}
		return 'Unknown';
	}

	public function tool_sortmedia_action(){
		$this->ave->clear();
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->tool_sortmedia_help();
		$folders = $this->ave->get_folders($line);
		$this->ave->setup_folders($folders);
		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$image_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		$extensions = array_merge($image_extensions, $video_extensions);
		foreach($folders as $folder){
			$files = $this->ave->getFiles($folder, $extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				if(in_array($extension, $image_extensions)){
					$resolution = $this->ave->getImageResolution($file);
				} else {
					$resolution = $this->ave->getVideoResolution($file);
				}
				if($resolution == '0x0'){
					$this->ave->log_error->write("FAILED GET_MEDIA_RESOLUTION \"$file\"");
					$errors++;
					$this->ave->set_progress($progress, $errors);
					continue 1;
				}
				$size = explode("x",$resolution);
				$quality = $this->ave->getMediaQuality(intval($size[0]), intval($size[1]));

				switch($this->ave->getMediaOrientation(intval($size[0]), intval($size[1]))){
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
					$directory = $folder.DIRECTORY_SEPARATOR.$orientation.DIRECTORY_SEPARATOR.$quality;
				} else if($this->params['resolution']){
					$directory = $folder.DIRECTORY_SEPARATOR.$orientation;
				} else if($this->params['quality']){
					$directory = $folder.DIRECTORY_SEPARATOR.$quality;
				}
				if(!file_exists($directory)){
					if(!$this->ave->mkdir($directory)){
						$errors++;
						continue;
					}
				}
				if($this->ave->rename($file, $directory.DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_BASENAME))){
					$progress++;
				} else {
					$errors++;
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
	}

	public function tool_gifanimated_action(){
		$this->ave->clear();
		$this->ave->set_subtool("SortGifAnimated");
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
			$files = $this->ave->getFiles($folder, ['gif']);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				if($this->ave->isGifAnimated($file)){
					$directory = $folder.DIRECTORY_SEPARATOR."Animated";
				} else {
					$directory = $folder.DIRECTORY_SEPARATOR."NotAnimated";
				}
				if(!file_exists($directory)){
					if(!$this->ave->mkdir($directory)){
						$errors++;
						$this->ave->set_progress($progress, $errors);
						continue 1;
					}
				}
				$new_name = $directory.DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_BASENAME);
				if($this->ave->rename($file, $new_name)){
					$progress++;
				} else {
					$errors++;
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
			unset($files);
			$this->ave->set_folder_done($folder);
		}
		$this->ave->exit();
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

	public function tool_sortdate_help(){
		$this->ave->clear();
		$this->ave->set_subtool("SortDate");

		$help = [' Modes:'];
		foreach($this->tool_sortdate_mode as $mode_key => $mode_name){
			array_push($help, " $mode_key $mode_name");
		}
		$this->ave->print_help($help);

		echo " Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$this->ave->clear();

		$this->ave->print_help([
			' Separators:',
			' . - _ \ @',
		]);

		echo " Separator: ";
		$separator = $this->ave->get_input();
		if($separator == '#') return $this->ave->select_action();

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'separator' => strtolower($separator[0] ?? '?'),
		];

		if(!in_array($this->params['mode'],['0','1','2','3','4','5','6','7'])) return $this->tool_sortdate_help();
		if(!in_array($this->params['separator'],['.','-','_','\\','@'])) return $this->tool_sortdate_help();
		if($this->params['separator'] == '\\') $this->params['separator'] = DIRECTORY_SEPARATOR;
		$this->ave->set_subtool("SortDate > ".$this->tool_sortdate_mode[$this->params['mode'] ?? '?']);
		return $this->tool_sortdate_action();
	}

	public function tool_sortdate_action(){
		$this->ave->clear();
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
				$new_name = $this->tool_sortdate_get_pattern($folder, $this->params['mode'], $file, $this->params['separator']);
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
			unset($files);
			$this->ave->set_folder_done($folder);
		}

		$this->ave->exit();
	}

	public function tool_sortdate_get_pattern(string $folder, string $mode, string $file, string $separator) : string {
		return $folder.DIRECTORY_SEPARATOR.str_replace("-", $separator, $this->tool_sortdate_format_date($mode, filemtime($file))).DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_BASENAME);
	}

	public function tool_sortdate_format_date(string $mode, int $date) : string {
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

	public function tool_sortimagescolor_help(){
		$this->ave->clear();
		$this->ave->set_subtool("SortImagesColor");
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);

		$this->ave->setup_folders($folders);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);
		$image_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->getFiles($folder, $image_extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$colors = $this->ave->getImageColorCount($file);
				$group = $this->ave->getImageColorGroup($colors);
				$directory = pathinfo($file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.$group;
				if(!file_exists($directory)){
					if(!$this->ave->mkdir($directory)){
						$errors++;
						$this->ave->set_progress($progress, $errors);
						continue 1;
					}
				}
				$new_name = $directory.DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_BASENAME);
				if($this->ave->rename($file, $new_name)){
					$progress++;
				} else {
					$errors++;
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
