<?php

declare(strict_types=1);

namespace NGC\Tools;

use Toolkit;
use Imagick;
use Exception;
use NGC\Core\Logs;
use NGC\Core\IniFile;
use NGC\Services\FaceDetector;

class MediaTools {

	private string $name = "Media Tools";
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
			' 0 - Merge: Video + Audio',
			' 1 - Merge: Video + SRT',
			' 2 - Avatar generator',
			' 3 - Video: Fetch media info',
			' 4 - Image converter',
			' 5 - Ident mime type',
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
		$this->core->clear();
		$this->core->set_subtool("Merge video audio");

		set_video:
		$line = $this->core->get_input(" Video: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_video;
		$video = $folders[0];

		if(!file_exists($video) || !is_dir($video)){
			$this->core->echo(" Invalid video folder");
			goto set_video;
		}

		set_audio:
		$line = $this->core->get_input(" Audio: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_audio;
		$audio = $folders[0];

		if(!file_exists($audio) || !is_dir($audio)){
			$this->core->echo(" Invalid audio folder");
			goto set_audio;
		}

		set_output:
		$line = $this->core->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if($audio == $output || $video == $output){
			$this->core->echo(" Output folder must be different than audio/video folder");
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		$errors = 0;
		$this->core->set_errors($errors);

		$files_video = [];
		$files_audio = [];

		$files = $this->core->get_files($video, $this->core->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
		foreach($files as $file){
			$files_video[pathinfo($file, PATHINFO_FILENAME)] = $file;
		}

		$files = $this->core->get_files($audio, $this->core->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
		foreach($files as $file){
			$files_audio[pathinfo($file, PATHINFO_FILENAME)] = $file;
		}

		$items = 0;
		$total = count($files_video);
		foreach($files_video as $key => $file){
			$items++;
			if(!file_exists($file)){
				$this->core->write_error("FILE NOT FOUND \"$file\"");
				$errors++;
			} else if(!isset($files_audio[$key])){
				$this->core->write_error("AUDIO FILE NOT FOUND FOR \"$file\"");
				$errors++;
			} else {
				$audio = $files_audio[$key];
				$out = $this->core->get_path("$output/$key.mkv");
				if(file_exists($out)){
					$this->core->write_error("FILE ALREADY EXISTS \"$out\"");
					$errors++;
				} else {
					$this->core->exec("mkvmerge", "-o \"$out\" --no-audio --no-subtitles \"$file\" --no-video \"$audio\"");
					if(!file_exists($out)){
						$this->core->write_error("FAILED MERGE \"$file\" + \"$audio\" INTO \"$out\"");
						$errors++;
					} else {
						$this->core->write_log("MERGE \"$file\" + \"$audio\" INTO \"$out\"");
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

	public function tool_merge_video_subtitles() : bool {
		$this->core->clear();
		$this->core->set_subtool("Merge video subtitles");

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

		set_output:
		$line = $this->core->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		$errors = 0;
		$this->core->set_errors($errors);

		$lang = $this->core->config->get('SUBTITLES_LANGUAGE');
		$files = $this->core->get_files($input, $this->core->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$srt = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/".pathinfo($file, PATHINFO_FILENAME).".srt");
			$out = $this->core->get_path("$output/".pathinfo($file, PATHINFO_BASENAME));
			if(file_exists($out)){
				$this->core->write_error("FILE ALREADY EXISTS \"$out\"");
				$errors++;
			} else if(!file_exists($srt)){
				$this->core->write_error("FILE NOT EXISTS \"$srt\"");
				$errors++;
			} else {
				$this->core->exec("mkvmerge", "-o \"$out\" --default-track 0 --sub-charset 0:UTF-8 --language 0:$lang \"$srt\" \"$file\"");
				if(!file_exists($out)){
					$this->core->write_error("FAILED MERGE \"$file\" + \"$srt\" INTO \"$out\"");
					$errors++;
				} else {
					$this->core->write_log("MERGE \"$file\" + \"$srt\" INTO \"$out\"");
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

	public function tool_avatar_generator() : bool {
		$this->core->clear();
		$this->core->set_subtool("Avatar generator");

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

		set_output:
		$line = $this->core->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		$size = $this->core->get_input_integer(" Width (0 - no resize): ", 0);
		if(!$size) return false;

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		$image_extensions = explode(" ", $this->core->config->get('EXTENSIONS_PHOTO'));
		$variants = explode(" ", $this->core->config->get('AVATAR_GENERATOR_VARIANTS'));
		$files = $this->core->get_files($input, $image_extensions);

		$errors = 0;

		$detector = new FaceDetector($this->core->get_path($this->core->path."/includes/data/FaceDetector.dat"));
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue;
			$folder = pathinfo($file, PATHINFO_DIRNAME);
			$directory = str_ireplace($input, $output, $folder);
			if(!file_exists($directory)){
				if(!$this->core->mkdir($directory)){
					$errors++;
				}
			}
			if(file_exists($directory)){
				$image = $this->core->media->get_image_from_path($file);
				if(is_null($image)){
					$this->core->write_error("FAILED LOAD IMAGE \"$file\"");
					$errors++;
				} else {
					$face = $detector->face_detect($image);
					if(!$face){
						$this->core->write_error("FAILED GET FACE \"$file\"");
						$errors++;
					} else {
						foreach($variants as $variant){
							$new_name = $this->core->get_path("$directory/".pathinfo($file, PATHINFO_FILENAME)."@$variant.".$this->core->get_extension($file));
							if($detector->save_variant_image(floatval($variant), $file, $new_name, $size)){
								$this->core->write_log("WRITE VARIANT $variant FOR \"$file\"");
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

	public function tool_video_fetch_media_info() : bool {
		$this->core->clear();
		$this->core->set_subtool("Video fetch media info");

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
		$output = $input;

		$file_name = 'MediaInfo';

		set_output:
		$line = $this->core->get_input(" Output (Empty, same as input): ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(isset($folders[0])){
			$output = $folders[0];
			if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
				$this->core->echo(" Invalid output folder");
				goto set_output;
			}

			$line = $this->core->get_input(" File name (Empty, default): ");
			if($line == '#') return false;
			$fname = $this->core->clean_file_name($line);
			if(!empty($fname)) $file_name = $fname;
		}

		$generate_checksum = $this->core->get_confirm(" Generate checksum if .md5 file not found (Y/N): ");

		$errors = 0;
		$this->core->set_errors($errors);

		$ini_old = $this->core->get_path("$input/$file_name.ini");
		$ini_new = $this->core->get_path("$output/$file_name.gz-ini");
		if(file_exists($ini_old) && !file_exists($ini_new)){
			$this->core->move($ini_old, $ini_new);
		}
		$cache = new IniFile($ini_new, true, true);
		$this->core->echo(" Read file: $ini_new");
		$this->core->echo(" Last update: ".$cache->get('.LAST_UPDATE', 'None'));

		$csv_file = $this->core->get_path("$output/$file_name.csv");
		$this->core->delete($csv_file);
		$csv = new Logs($csv_file, false, true);
		$separator = $this->core->config->get('CSV_SEPARATOR');

		$labels = [
			"\"File path\"",
			"\"Dir name\"",
			"\"File name\"",
			"\"Extension\"",
			"\"Checksum (MD5)\"",
			"\"Size\"",
			"\"Modification date\"",
			"\"Resolution\"",
			"\"Quality\"",
			"\"Duration\"",
			"\"FPS\"",
			"\"Video bitrate\"",
			"\"Video codec\"",
			"\"Video aspect ratio\"",
			"\"Video orientation\"",
			"\"Audio codec\"",
			"\"Audio bitrate\"",
			"\"Audio channels\"",
			"\"Display type (VR)\"",
			"\"Stereo mode (VR)\"",
			"\"Passthrough (AR)\"",
		];

		$csv->write(implode($separator, $labels));

		$keys = [];
		$video_extensions = explode(" ", $this->core->config->get('EXTENSIONS_VIDEO'));
		$files = $this->core->get_files($input, $video_extensions);
		$items = 0;
		$new = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			$this->core->set_errors($errors);
			if(!file_exists($file)) continue;
			$key = hash('md5', str_ireplace($input, '', $file));

			$file_info = (object)[
				'path' => str_replace("\\\\", "\\", addslashes($file)),
				'directory' => str_replace("\\\\", "\\", addslashes(pathinfo(pathinfo($file, PATHINFO_DIRNAME), PATHINFO_BASENAME))),
				'filename' => str_replace("\\\\", "\\", addslashes(pathinfo($file, PATHINFO_FILENAME))),
				'extension' => str_replace("\\\\", "\\", addslashes($this->core->get_extension($file))),
			];

			$media_cache = $cache->get($key, []);
			if(($media_cache['version'] ?? 0) < 1){
				$meta = $this->core->media->get_media_info_simple($file);
				if(!$meta){
					$this->core->write_error("FAILED GET MEDIA INFO \"$file\"");
					$errors++;
					continue;
				}
				$new++;
			} else {
				$meta = (object)$media_cache;
			}

			if(file_exists("$file.md5")){
				$meta->checksum = file_get_contents("$file.md5");
			} else if($generate_checksum){
				$meta->checksum = strtoupper(hash_file('md5', $file));
			} else {
				$meta->checksum = null;
			}

			$media_cache = array_merge((array)$meta, ['version' => 1]);
			$cache->set($key, $media_cache);

			if($new > 0 && $new % 25 == 0) $cache->save();
			$this->core->write_log("FETCH MEDIA INFO \"$file\"");

			$meta->name = $file_info->filename;
			$this->translate_media_info($meta);
			$data = [
				"\"$file_info->path\"",
				"\"$file_info->directory\"",
				"\"$file_info->filename\"",
				"\"$file_info->extension\"",
				"\"$meta->checksum\"",
				"\"$meta->file_size_human\"",
				"\"$meta->file_modification_time\"",
				"\"$meta->video_resolution\"",
				"\"$meta->video_quality\"",
				"\"$meta->video_duration\"",
				"\"$meta->video_fps\"",
				"\"$meta->video_bitrate\"",
				"\"$meta->video_codec\"",
				"\"$meta->video_aspect_ratio\"",
				"\"$meta->video_orientation\"",
				"\"$meta->audio_codec\"",
				"\"$meta->audio_bitrate\"",
				"\"$meta->audio_channels\"",
				"\"$meta->vr_screen_type\"",
				"\"$meta->vr_stereo_mode\"",
				"\"$meta->vr_alpha\"",
			];
			array_push($keys, $key);
			$csv->write(implode($separator, $data));
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$this->core->progress($items, $total);
		$this->core->set_errors($errors);
		$this->core->echo(" Saved results into ".$csv->get_path());
		$csv->close();
		$cache->set_all($cache->only($keys));
		$cache->update(['.LAST_UPDATE' => date('Y-m-d H:i:s')], true);
		$cache->close();

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_image_converter() : bool {
		$this->core->set_subtool("Image converter");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Modes:',
			' 0 - Image > WEBP',
			' 1 - Image > JPG',
			' 2 - Image > PNG',
			' 3 - Image > GIF',
		]);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
		];

		if(!in_array($this->params['mode'], ['0', '1', '2', '3'])) goto set_mode;
		$this->core->clear();

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

		set_output:
		$line = $this->core->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		$errors = 0;

		$extensions = explode(" ", $this->core->config->get('EXTENSIONS_PHOTO'));
		$files = $this->core->get_files($input);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			$this->core->set_errors($errors);
			if(!file_exists($file)) continue;
			if(!in_array($this->core->get_extension($file), $extensions)){
				$this->core->write_error("FILE FORMAT NOT SUPORTED \"$file\"");
				$errors++;
				continue;
			}
			$folder = pathinfo($file, PATHINFO_DIRNAME);
			$directory = str_ireplace($input, $output, $folder);
			if(!file_exists($directory)){
				if(!$this->core->mkdir($directory)){
					$errors++;
					continue;
				}
			}
			$new_name = $this->core->get_path("$directory/".pathinfo($file, PATHINFO_FILENAME));
			$image = new Imagick($file);
			if(!$image->valid()){
				$this->core->write_error("FAILED READ IMAGE \"$file\" BY IMAGICK");
				$errors++;
				continue;
			}
			switch(intval($this->params['mode'])){
				case 0: {
					$image->setImageFormat('webp');
					if($image->getImageFormat() == 'PNG'){
						$image->setOption('webp:lossless', 'true');
					}
					$image->setImageCompressionQuality($this->core->config->get('COMPRESS_LEVEL_WEBP'));
					$new_name .= ".webp";
					break;
				}
				case 1: {
					$image->setImageFormat('jpeg');
					$image->setImageCompressionQuality($this->core->config->get('COMPRESS_LEVEL_JPEG'));
					$new_name .= ".jpg";
					break;
				}
				case 2: {
					$image->setImageFormat('png');
					$image->setImageCompressionQuality($this->core->config->get('COMPRESS_LEVEL_PNG'));
					$new_name .= ".png";
					break;
				}
				case 3: {
					$image->setImageFormat('gif');
					$image->setImageCompressionQuality($this->core->config->get('COMPRESS_LEVEL_GIF'));
					$new_name .= ".gif";
					break;
				}
			}
			if(file_exists($new_name)){
				$image->clear();
				$this->core->write_error("FILE ALREADY EXISTS \"$new_name\"");
				$errors++;
				continue;
			}
			try {
				$image->writeImage($new_name);
			}
			catch(Exception $e){
				$this->core->write_error($e->getMessage());
			}
			$image->clear();
			if(!file_exists($new_name)){
				$this->core->write_error("FAILED SAVE FILE \"$new_name\"");
				$errors++;
				continue;
			} else {
				$this->core->write_log("CONVERT \"$file\" TO \"$new_name\"");
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$this->core->progress($items, $total);
		$this->core->set_errors($errors);

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_ident_mime_type() : bool {
		$this->core->clear();
		$this->core->set_subtool("Ident mime type");

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
				$extension_current = $this->core->get_extension($file);
				$extension_detected = $this->core->media->get_extension_by_mime_type($file);
				if(!$extension_detected){
					$this->core->write_error("FAILED DETECT TYPE \"$file\"");
					$errors++;
					continue 1;
				}
				if($extension_current != $extension_detected){
					$new_name = $this->core->get_path(pathinfo($file, PATHINFO_DIRNAME)."/".pathinfo($file, PATHINFO_FILENAME).".".$extension_detected);
					if(!$this->core->move($file, $new_name)){
						$errors++;
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

	private function translate_media_info(object &$meta) : void {
		$vr_mode = $this->core->media->get_vr_mode($meta->name);
		$meta->video_bitrate = is_null($meta->video_bitrate) ? 'N/A' : $this->core->format_bits($meta->video_bitrate, 2, false).'/s';
		$meta->video_quality = "{$meta->video_quality}p";
		switch($meta->video_codec){
			case 'h264': {
				$meta->video_codec = 'AVC';
				break;
			}
			default: {
				$meta->video_codec = mb_strtoupper($meta->video_codec);
				break;
			}
		}
		$meta->audio_codec = $meta->audio_codec == 'none' ? 'None' : mb_strtoupper($meta->audio_codec);
		if(is_null($meta->audio_bitrate)){
			$meta->audio_bitrate = 'N/A';
		} else if($meta->audio_bitrate == 0){
			$meta->audio_bitrate = 'None';
		} else {
			$meta->audio_bitrate = $this->core->format_bits($meta->audio_bitrate, 2, false).'/s';
		}
		$meta->audio_channels = $this->core->media->get_audio_channels_string($meta->audio_channels);
		$meta->vr_screen_type = $this->core->media->vr_screen_type($vr_mode['screen_type']);
		$meta->vr_stereo_mode = $this->core->media->vr_stereo_mode($vr_mode['stereo_mode']);
		$meta->vr_alpha = $vr_mode['alpha'] ? 'YES' : 'NO';
		$meta->checksum ??= 'None';
	}

}

?>