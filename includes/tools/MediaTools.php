<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;

use App\Services\MediaFunctions;
use App\Services\FaceDetector;

class MediaTools {

	private string $name = "MediaTools";

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
			' 0 - Merge:  Video + Audio',
			' 1 - Merge:  Video + SRT',
			' 2 - Avatar generator',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolMergeVideoAudio();
			case '1': return $this->ToolMergeVideoSubtitles();
			case '2': return $this->ToolAvatarGenerator();
		}
		return false;
	}

	public function ToolMergeVideoAudio() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("MergeVideoAudio");

		set_video:
		echo " Video:  ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_video;
		$video = $folders[0];

		if(!file_exists($video) || !is_dir($video)){
			echo " Invalid video folder\r\n";
			goto set_video;
		}

		set_audio:
		echo " Audio:  ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_audio;
		$audio = $folders[0];

		if(!file_exists($audio) || !is_dir($audio)){
			echo " Invalid audio folder\r\n";
			goto set_audio;
		}

		set_output:
		echo " Output: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if($audio == $output || $video == $output){
			echo " Output folder must be different than audio/video folder\r\n";
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			echo " Invalid output folder\r\n";
			goto set_output;
		}

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		$files_video = [];
		$files_audio = [];

		$files = $this->ave->getFiles($video, $this->ave->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
		foreach($files as $file){
			$files_video[pathinfo($file, PATHINFO_FILENAME)] = $file;
		}

		$files = $this->ave->getFiles($audio, $this->ave->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
		foreach($files as $file){
			$files_audio[pathinfo($file, PATHINFO_FILENAME)] = $file;
		}

		$items = 0;
		$total = count($files_video);
		foreach($files_video as $key => $file){
			$items++;
			if(!file_exists($file)){
				$this->ave->write_error("FILE NOT FOUND \"$file\"");
				$errors++;
			} else if(!isset($files_audio[$key])){
				$this->ave->write_error("AUDIO FILE NOT FOUND FOR \"$file\"");
				$errors++;
			} else {
				$audio = $files_audio[$key];
				$out = $this->ave->get_file_path("$output/$key.mkv");
				if(file_exists($out)){
					$this->ave->write_error("FILE ALREADY EXISTS \"$out\"");
					$errors++;
				} else {
					exec("mkvmerge -o \"$out\" --no-audio --no-subtitles \"$file\" --no-video \"$audio\"");
					if(!file_exists($out)){
						$this->ave->write_error("FAILED MERGE \"$file\" + \"$audio\" INTO \"$out\"");
						$errors++;
					} else {
						$this->ave->write_log("MERGE \"$file\" + \"$audio\" INTO \"$out\"");
						$progress++;
					}
				}
			}
			$this->ave->progress($items, $total);
			$this->ave->set_progress($progress, $errors);
		}

		return true;
	}

	public function ToolMergeVideoSubtitles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("MergeVideoSubtitles");

		set_input:
		echo " Input:  ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder\r\n";
			goto set_input;
		}

		set_output:
		echo " Output: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if($input == $output){
			echo " Output folder must be different than input folder\r\n";
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			echo " Invalid output folder\r\n";
			goto set_output;
		}

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		$lang = $this->ave->config->get('AVE_SUBTITLES_LANGUAGE');
		$files = $this->ave->getFiles($input, $this->ave->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$srt = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/".pathinfo($file, PATHINFO_FILENAME).".srt");
			$out = $this->ave->get_file_path("$output/".pathinfo($file, PATHINFO_BASENAME));
			if(file_exists($out)){
				$this->ave->write_error("FILE ALREADY EXISTS \"$out\"");
				$errors++;
			} else if(!file_exists($srt)){
				$this->ave->write_error("FILE NOT EXISTS \"$srt\"");
				$errors++;
			} else {
				exec("mkvmerge -o \"$out\" --default-track 0 --sub-charset 0:UTF-8 --language 0:$lang \"$srt\" \"$file\"");
				if(!file_exists($out)){
					$this->ave->write_error("FAILED MERGE \"$file\" + \"$srt\" INTO \"$out\"");
					$errors++;
				} else {
					$this->ave->write_log("MERGE \"$file\" + \"$srt\" INTO \"$out\"");
					$progress++;
				}
			}
			$this->ave->progress($items, $total);
			$this->ave->set_progress($progress, $errors);
		}

		return true;
	}

	public function ToolAvatarGenerator() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("AvatarGenerator");

		set_input:
		echo " Input:  ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder\r\n";
			goto set_input;
		}

		set_output:
		echo " Output: ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		set_size:
		echo " Width (0 - no resize): ";
		$line = $this->ave->get_input();
		if($line == '#') return false;
		$size = preg_replace('/\D/', '', $line);
		if($size == '') goto set_size;
		$size = intval($size);
		if($size < 0) goto set_size;

		if($input == $output){
			echo " Output folder must be different than input folder\r\n";
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			echo " Invalid output folder\r\n";
			goto set_output;
		}

		$media = new MediaFunctions();

		$image_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		$variants = explode(" ", $this->ave->config->get('AVE_AVATAR_GENERATOR_VARIANTS'));
		$files = $this->ave->getFiles($input, $image_extensions);

		$progress = 0;
		$errors = 0;

		$detector = new FaceDetector($this->ave->get_file_path($this->ave->path."/bin/data/FaceDetector.dat"));
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$folder = pathinfo($file, PATHINFO_DIRNAME);
			$directory = str_replace($input, $output, $folder);
			if(!file_exists($directory)){
				if($this->ave->mkdir($directory)){
					$progress++;
				} else {
					$errors++;
				}
			}
			if(file_exists($directory)){
				$image = $media->getImageFromPath($file);
				if(is_null($image)){
					$this->ave->write_error("FAILED LOAD IMAGE \"$file\"");
					$errors++;
				} else {
					$face = $detector->faceDetect($image);
					if(!$face){
						$this->ave->write_error("FAILED GET FACE \"$file\"");
						$errors++;
					} else {
						foreach($variants as $variant){
							$new_name = $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_FILENAME)."@$variant.".pathinfo($file, PATHINFO_EXTENSION));
							if($detector->saveVariantImage(floatval($variant), $file, $new_name, $size)){
								$this->ave->write_log("WRITE VARIANT $variant FOR \"$file\"");
							}
						}
						$progress++;
					}
				}
			}
			$this->ave->progress($items, $total);
			$this->ave->set_progress($progress, $errors);
		}

		return true;
	}

}

?>
