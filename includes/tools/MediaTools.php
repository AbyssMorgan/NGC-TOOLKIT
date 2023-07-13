<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use Imagick;
use Exception;

use App\Dictionaries\MediaOrientation;
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
			' 0 - Merge: Video + Audio',
			' 1 - Merge: Video + SRT',
			' 2 - Avatar generator',
			' 3 - Video: Fetch media info',
			' 4 - Image converter'
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolMergeVideoAudio();
			case '1': return $this->ToolMergeVideoSubtitles();
			case '2': return $this->ToolAvatarGenerator();
			case '3': return $this->ToolVideoFetchMediaInfo();
			case '4': return $this->ToolImageConverter();
		}
		return false;
	}

	public function ToolMergeVideoAudio() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("MergeVideoAudio");

		set_video:
		$line = $this->ave->get_input(" Video: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_video;
		$video = $folders[0];

		if(!file_exists($video) || !is_dir($video)){
			$this->ave->echo(" Invalid video folder");
			goto set_video;
		}

		set_audio:
		$line = $this->ave->get_input(" Audio: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_audio;
		$audio = $folders[0];

		if(!file_exists($audio) || !is_dir($audio)){
			$this->ave->echo(" Invalid audio folder");
			goto set_audio;
		}

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if($audio == $output || $video == $output){
			$this->ave->echo(" Output folder must be different than audio/video folder");
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			$this->ave->echo(" Invalid output folder");
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
					$this->ave->exec("mkvmerge", "-o \"$out\" --no-audio --no-subtitles \"$file\" --no-video \"$audio\"");
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
		$this->ave->progress($items, $total);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolMergeVideoSubtitles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("MergeVideoSubtitles");

		set_input:
		$line = $this->ave->get_input(" Input: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->ave->echo(" Invalid input folder");
			goto set_input;
		}

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if($input == $output){
			$this->ave->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			$this->ave->echo(" Invalid output folder");
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
				$this->ave->exec("mkvmerge", "-o \"$out\" --default-track 0 --sub-charset 0:UTF-8 --language 0:$lang \"$srt\" \"$file\"");
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
		$this->ave->progress($items, $total);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolAvatarGenerator() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("AvatarGenerator");

		set_input:
		$line = $this->ave->get_input(" Input: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->ave->echo(" Invalid input folder");
			goto set_input;
		}

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		set_size:
		$line = $this->ave->get_input(" Width (0 - no resize): ");
		if($line == '#') return false;
		$size = preg_replace('/\D/', '', $line);
		if($size == '') goto set_size;
		$size = intval($size);
		if($size < 0) goto set_size;

		if($input == $output){
			$this->ave->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			$this->ave->echo(" Invalid output folder");
			goto set_output;
		}

		$media = new MediaFunctions($this->ave);

		$image_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		$variants = explode(" ", $this->ave->config->get('AVE_AVATAR_GENERATOR_VARIANTS'));
		$files = $this->ave->getFiles($input, $image_extensions);

		$progress = 0;
		$errors = 0;

		$detector = new FaceDetector($this->ave->get_file_path($this->ave->path."/includes/data/FaceDetector.dat"));
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
		$this->ave->progress($items, $total);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolVideoFetchMediaInfo() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("VideoFetchMediaInfo");

		set_input:
		$line = $this->ave->get_input(" Input: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->ave->echo(" Invalid input folder");
			goto set_input;
		}

		$media = new MediaFunctions($this->ave);

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		$this->ave->write_data('"File path";"Dir name";"File name";"Extension";"Resolution";"Quality";"Duration";"Size";"Orientation"');

		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$files = $this->ave->getFiles($input, $video_extensions);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$resolution = $media->getVideoResolution($file);
			if($resolution == '0x0'){
				$this->ave->write_error("FAILED GET_MEDIA_RESOLUTION \"$file\"");
				$errors++;
				continue;
			}
			$size = explode('x', $resolution);
			$orientation = $media->getMediaOrientation(intval($size[0]), intval($size[1]));
			$quality = $media->getMediaQuality(intval($size[0]), intval($size[1]));
			switch($orientation){
				case MediaOrientation::MEDIA_ORIENTATION_HORIZONTAL: {
					$quality .= $this->ave->config->get('AVE_QUALITY_SUFFIX_HORIZONTAL');
					break;
				}
				case MediaOrientation::MEDIA_ORIENTATION_VERTICAL: {
					$quality .= $this->ave->config->get('AVE_QUALITY_SUFFIX_VERTICAL');
					break;
				}
				case MediaOrientation::MEDIA_ORIENTATION_SQUARE: {
					$quality .= $this->ave->config->get('AVE_QUALITY_SUFFIX_SQUARE');
					break;
				}
			}
			$meta = [
				'"'.str_replace("\\\\", "\\", addslashes($file)).'"',
				'"'.str_replace("\\\\", "\\", addslashes(pathinfo(pathinfo($file, PATHINFO_DIRNAME), PATHINFO_BASENAME))).'"',
				'"'.str_replace("\\\\", "\\", addslashes(pathinfo($file, PATHINFO_FILENAME))).'"',
				'"'.str_replace("\\\\", "\\", addslashes(pathinfo($file, PATHINFO_EXTENSION))).'"',
				'"'.$resolution.'"',
				'"'.$quality.'"',
				'"'.$media->getVideoDuration($file).'"',
				'"'.$this->ave->formatBytes(filesize($file)).'"',
				'"'.$media->getMediaOrientationName($orientation).'"',
			];
			$this->ave->write_data(implode($this->ave->config->get('AVE_CSV_SEPARATOR'), $meta));
			$progress++;
			$this->ave->progress($items, $total);
			$this->ave->set_progress($progress, $errors);
		}
		$this->ave->progress($items, $total);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

	public function ToolImageConverter() : bool {
		$this->ave->set_subtool("ImageConverter");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Modes:',
			' 0 - Image > WEBP',
			' 1 - Image > JPG',
			' 2 - Image > PNG',
			' 3 - Image > GIF',
		]);

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
		];

		if(!in_array($this->params['mode'],['0','1','2','3'])) goto set_mode;
		$this->ave->clear();

		set_input:
		$line = $this->ave->get_input(" Input: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->ave->echo(" Invalid input folder");
			goto set_input;
		}

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if($input == $output){
			$this->ave->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			$this->ave->echo(" Invalid output folder");
			goto set_output;
		}

		$progress = 0;
		$errors = 0;

		$extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		$files = $this->ave->getFiles($input);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			$this->ave->set_progress($progress, $errors);
			if(!file_exists($file)) continue;
			if(!in_array(pathinfo($file, PATHINFO_EXTENSION), $extensions)){
				$this->ave->write_error("FILE FORMAT NOT SUPORTED \"$file\"");
				$errors++;
				continue;
			}
			$folder = pathinfo($file, PATHINFO_DIRNAME);
			$directory = str_replace($input, $output, $folder);
			if(!file_exists($directory)){
				if($this->ave->mkdir($directory)){
					$progress++;
				} else {
					$errors++;
					continue;
				}
			}
			$new_name = $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_FILENAME));
			if(file_exists($new_name)){
				$this->ave->write_error("FILE ALREADY EXISTS \"$new_name\"");
				$errors++;
				continue;
			}
			$image = new Imagick($file);
			if(!$image->valid()){
				$this->ave->write_error("FAILED READ IMAGE \"$file\" BY IMAGICK");
				$errors++;
				continue;
			}
			switch(intval($this->params['mode'])){
				case 0: {
					$image->setImageFormat('webp');
					$image->setOption('webp:lossless', 'true');
					$new_name .= ".webp";
					break;
				}
				case 1: {
					$image->setImageFormat('jpeg');
					$new_name .= ".jpg";
					break;
				}
				case 2: {
					$image->setImageFormat('png');
					$new_name .= ".png";
					break;
				}
				case 3: {
					$image->setImageFormat('gif');
					$new_name .= ".gif";
					break;
				}
			}
			$image->setImageCompressionQuality(100);
			try {
				$image->writeImage($new_name);
			}
			catch(Exception $e){
				$this->ave->write_error($e->getMessage());
			}
			$image->destroy();
			if(!file_exists($new_name)){
				$this->ave->write_error("FAILED SAVE FILE \"$new_name\"");
				$errors++;
				continue;
			} else {
				$this->ave->write_log("CONVERT \"$file\" TO \"$new_name\"");
				$progress++;
			}
			$this->ave->progress($items, $total);
			$this->ave->set_progress($progress, $errors);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press enter to back to menu");
		return false;
	}

}

?>
