<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use Imagick;
use Exception;
use AveCore\Logs;
use AveCore\IniFile;
use App\Services\MediaFunctions;
use App\Services\FaceDetector;

class MediaTools {

	private string $name = "Media Tools";
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
			' 4 - Image converter',
			' 5 - Ident mime type: Images',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_merge_video_audio();
			case '1': return $this->tool_merge_video_subtitles();
			case '2': return $this->tool_avatar_generator();
			case '3': return $this->tool_video_fetch_media_info();
			case '4': return $this->tool_image_converter();
			case '5': return $this->tool_ident_mime_type();
		}
		return false;
	}

	public function tool_merge_video_audio() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Merge video audio");

		set_video:
		$line = $this->ave->get_input(" Video: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_video;
		$video = $folders[0];

		if(!file_exists($video) || !is_dir($video)){
			$this->ave->echo(" Invalid video folder");
			goto set_video;
		}

		set_audio:
		$line = $this->ave->get_input(" Audio: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_audio;
		$audio = $folders[0];

		if(!file_exists($audio) || !is_dir($audio)){
			$this->ave->echo(" Invalid audio folder");
			goto set_audio;
		}

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
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

		$errors = 0;
		$this->ave->set_errors($errors);

		$files_video = [];
		$files_audio = [];

		$files = $this->ave->get_files($video, $this->ave->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
		foreach($files as $file){
			$files_video[pathinfo($file, PATHINFO_FILENAME)] = $file;
		}

		$files = $this->ave->get_files($audio, $this->ave->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
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

	public function tool_merge_video_subtitles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Merge video subtitles");

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

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
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

		$errors = 0;
		$this->ave->set_errors($errors);

		$lang = $this->ave->config->get('AVE_SUBTITLES_LANGUAGE');
		$files = $this->ave->get_files($input, $this->ave->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
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

	public function tool_avatar_generator() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Avatar generator");

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

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		$size = $this->ave->get_input_integer(" Width (0 - no resize): ", 0);
		if(!$size) return false;

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
		$files = $this->ave->get_files($input, $image_extensions);

		$errors = 0;

		$detector = new FaceDetector($this->ave->get_file_path($this->ave->path."/includes/data/FaceDetector.dat"));
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$folder = pathinfo($file, PATHINFO_DIRNAME);
			$directory = str_ireplace($input, $output, $folder);
			if(!file_exists($directory)){
				if(!$this->ave->mkdir($directory)){
					$errors++;
				}
			}
			if(file_exists($directory)){
				$image = $media->get_image_from_path($file);
				if(is_null($image)){
					$this->ave->write_error("FAILED LOAD IMAGE \"$file\"");
					$errors++;
				} else {
					$face = $detector->face_detect($image);
					if(!$face){
						$this->ave->write_error("FAILED GET FACE \"$file\"");
						$errors++;
					} else {
						foreach($variants as $variant){
							$new_name = $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_FILENAME)."@$variant.".pathinfo($file, PATHINFO_EXTENSION));
							if($detector->save_variant_image(floatval($variant), $file, $new_name, $size)){
								$this->ave->write_log("WRITE VARIANT $variant FOR \"$file\"");
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

	public function tool_video_fetch_media_info() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Video fetch media info");

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
		$output = $input;

		set_output:
		$line = $this->ave->get_input(" Output (Empty, same as input): ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(isset($folders[0])){
			$output = $folders[0];
			if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
				$this->ave->echo(" Invalid output folder");
				goto set_output;
			}
			$file_name = 'AveMediaInfo';

			$line = $this->ave->get_input(" File name (Empty, default): ");
			if($line == '#') return false;
			$fname = $this->ave->clean_file_name($line);
			if(!empty($fname)) $file_name = $fname;
		}

		$generate_checksum = $this->ave->get_confirm(" Generate checksum if .md5 file not found (Y/N): ");

		$media = new MediaFunctions($this->ave);

		$errors = 0;
		$this->ave->set_errors($errors);

		$ini_old = $this->ave->get_file_path("$input/$file_name.ini");
		$ini_new = $this->ave->get_file_path("$output/$file_name.gz-ini");
		if(file_exists($ini_old) && !file_exists($ini_new)){
			$this->ave->rename($ini_old, $ini_new);
		}
		$cache = new IniFile($ini_new, true, true);
		$this->ave->echo(" Read file: $ini_new");
		$this->ave->echo(" Last update: ".$cache->get('.LAST_UPDATE', 'None'));

		$csv_file = $this->ave->get_file_path("$output/$file_name.csv");
		$this->ave->delete($csv_file);
		$csv = new Logs($csv_file, false, true);
		$s = $this->ave->config->get('AVE_CSV_SEPARATOR');
		$csv->write('"File path"'.$s.'"Dir name"'.$s.'"File name"'.$s.'"Extension"'.$s.'"Resolution"'.$s.'"Quality"'.$s.'"Duration"'.$s.'"Size"'.$s.'"Orientation"'.$s.'"Checksum (MD5)"'.$s.'"FPS"'.$s.'"Codec"');

		$keys = [];
		$video_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_VIDEO'));
		$files = $this->ave->get_files($input, $video_extensions);
		$items = 0;
		$new = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			$this->ave->set_errors($errors);
			if(!file_exists($file)) continue;
			$key = hash('md5', str_ireplace($input, '', $file));
			if($cache->is_set($key)){
				$media_info = $cache->get($key);
				$resolution = $media_info['resolution'];
				$quality = $media_info['quality'];
				$duration = $media_info['duration'];
				$file_size = $media_info['file_size'];
				$orientation_name = $media_info['orientation_name'];
				$checksum = $media_info['checksum'];
				$fps = $media_info['fps'];
				$codec = $media_info['codec'];
				if(is_null($checksum) && $generate_checksum){
					$checksum = strtoupper(hash_file('md5', $file));
					$new++;
				}
			} else {
				$new++;
				$resolution = $media->get_video_resolution($file);
				if($resolution == '0x0'){
					$this->ave->write_error("FAILED GET MEDIA RESOLUTION \"$file\"");
					$errors++;
					continue;
				}
				$size = explode('x', $resolution);
				$orientation = $media->get_media_orientation(intval($size[0]), intval($size[1]));
				$quality = $media->get_media_quality(intval($size[0]), intval($size[1]), true).$this->ave->config->get('AVE_QUALITY_SUFFIX');
				$duration = $media->get_video_duration($file);
				$file_size = $this->ave->format_bytes(filesize($file));
				$orientation_name = $media->get_media_orientation_name($orientation);
				$fps = $media->get_video_fps($file);
				$codec = $media->get_video_codec($file);
				if(file_exists("$file.md5")){
					$checksum = file_get_contents("$file.md5");
				} else if($generate_checksum){
					$checksum = strtoupper(hash_file('md5', $file));
				} else {
					$checksum = null;
				}
				$cache->set($key, [
					'resolution' => $resolution,
					'quality' => $quality,
					'duration' => $duration,
					'file_size' => $file_size,
					'orientation_name' => $orientation_name,
					'checksum' => $checksum,
					'fps' => $fps,
					'codec' => $codec,
				]);
				if($new > 0 && $new % 25 == 0) $cache->save();
				$this->ave->write_log("FETCH MEDIA INFO \"$file\"");
			}
			$meta = [
				'"'.str_replace("\\\\", "\\", addslashes($file)).'"',
				'"'.str_replace("\\\\", "\\", addslashes(pathinfo(pathinfo($file, PATHINFO_DIRNAME), PATHINFO_BASENAME))).'"',
				'"'.str_replace("\\\\", "\\", addslashes(pathinfo($file, PATHINFO_FILENAME))).'"',
				'"'.str_replace("\\\\", "\\", addslashes(pathinfo($file, PATHINFO_EXTENSION))).'"',
				'"'.$resolution.'"',
				'"'.$quality.'"',
				'"'.$duration.'"',
				'"'.$file_size.'"',
				'"'.$orientation_name.'"',
				'"'.($checksum ?? 'None').'"',
				'"'.$fps.'"',
				'"'.$codec.'"',
			];
			array_push($keys, $key);
			$csv->write(implode($this->ave->config->get('AVE_CSV_SEPARATOR'), $meta));
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}
		$this->ave->progress($items, $total);
		$this->ave->set_errors($errors);
		$this->ave->echo(" Saved results into ".$csv->get_path());
		$csv->close();
		$cache->set_all($cache->only($keys));
		$cache->update(['.LAST_UPDATE' => date('Y-m-d H:i:s')], true);
		$cache->close();

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_image_converter() : bool {
		$this->ave->set_subtool("Image converter");

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
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->ave->echo(" Invalid input folder");
			goto set_input;
		}

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
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

		$errors = 0;

		$extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		$files = $this->ave->get_files($input);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			$this->ave->set_errors($errors);
			if(!file_exists($file)) continue;
			if(!in_array(pathinfo($file, PATHINFO_EXTENSION), $extensions)){
				$this->ave->write_error("FILE FORMAT NOT SUPORTED \"$file\"");
				$errors++;
				continue;
			}
			$folder = pathinfo($file, PATHINFO_DIRNAME);
			$directory = str_ireplace($input, $output, $folder);
			if(!file_exists($directory)){
				if(!$this->ave->mkdir($directory)){
					$errors++;
					continue;
				}
			}
			$new_name = $this->ave->get_file_path("$directory/".pathinfo($file, PATHINFO_FILENAME));
			$image = new Imagick($file);
			if(!$image->valid()){
				$this->ave->write_error("FAILED READ IMAGE \"$file\" BY IMAGICK");
				$errors++;
				continue;
			}
			switch(intval($this->params['mode'])){
				case 0: {
					$image->setImageFormat('webp');
					if($image->getImageFormat() == 'PNG'){
						$image->setOption('webp:lossless', 'true');
					}
					$image->setImageCompressionQuality($this->ave->config->get('AVE_COMPRESS_LEVEL_WEBP'));
					$new_name .= ".webp";
					break;
				}
				case 1: {
					$image->setImageFormat('jpeg');
					$image->setImageCompressionQuality($this->ave->config->get('AVE_COMPRESS_LEVEL_JPEG'));
					$new_name .= ".jpg";
					break;
				}
				case 2: {
					$image->setImageFormat('png');
					$image->setImageCompressionQuality($this->ave->config->get('AVE_COMPRESS_LEVEL_PNG'));
					$new_name .= ".png";
					break;
				}
				case 3: {
					$image->setImageFormat('gif');
					$image->setImageCompressionQuality($this->ave->config->get('AVE_COMPRESS_LEVEL_GIF'));
					$new_name .= ".gif";
					break;
				}
			}
			if(file_exists($new_name)){
				$image->destroy();
				$this->ave->write_error("FILE ALREADY EXISTS \"$new_name\"");
				$errors++;
				continue;
			}
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
			}
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}
		$this->ave->progress($items, $total);
		$this->ave->set_errors($errors);

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_ident_mime_type() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("Ident mime type");
		if(!$this->ave->windows) return $this->ave->windows_only();

		$line = $this->ave->get_input(" Folders: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		$this->ave->setup_folders($folders);

		$errors = 0;
		$this->ave->set_errors($errors);
		$image_extensions = explode(" ", $this->ave->config->get('AVE_EXTENSIONS_PHOTO'));
		array_push($image_extensions, '');
		$media = new MediaFunctions($this->ave);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->ave->get_files($folder, $image_extensions);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue 1;
				$extension_current = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				$extension_detected = $media->get_extension_by_mime_type($file);
				if(!$extension_detected){
					$this->ave->write_error("FAILED DETECT IMAGE TYPE \"$new_name\"");
					$errors++;
					continue 1;
				}
				if($extension_current != $extension_detected){
					$new_name = $this->ave->get_file_path(pathinfo($file, PATHINFO_DIRNAME)."/".pathinfo($file, PATHINFO_FILENAME).".".$extension_detected);
					if(!$this->ave->rename($file, $new_name)){
						$errors++;
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

}

?>
