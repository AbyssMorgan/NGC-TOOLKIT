<?php

/**
 * NGC-TOOLKIT v2.7.4 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Tools;

use Toolkit;
use Imagick;
use Exception;
use NGC\Core\Logs;
use NGC\Core\IniFile;
use NGC\Services\FaceDetector;
use NGC\Extensions\SubtitlesValidator;

class MediaTools {

	private string $name = "Media Tools";
	private string $action;
	private Toolkit $core;

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
	}

	public function help() : void {
		$this->core->print_help([
			' Actions:',
			' 0  - Merge: Video + Audio',
			' 1  - Merge: Video + SRT',
			' 2  - Avatar generator',
			' 3  - Fetch media info (Video)',
			' 4  - Image converter',
			' 5  - Ident mime type',
			' 6  - Extract video',
			' 7  - Extract audio',
			' 8  - Extract subtitles',
			' 9  - Validate subtitles (SRT)',
			' 10 - Compare subtitles (SRT)',
		]);
	}

	public function action(string $action) : bool {
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_merge_video_audio();
			case '1': return $this->tool_merge_video_subtitles();
			case '2': return $this->tool_avatar_generator();
			case '3': return $this->tool_fetch_media_info();
			case '4': return $this->tool_image_converter();
			case '5': return $this->tool_ident_mime_type();
			case '6': return $this->tool_extract_video();
			case '7': return $this->tool_extract_audio();
			case '8': return $this->tool_extract_subtitles();
			case '9': return $this->tool_validate_subtitles();
			case '10': return $this->tool_compare_subtitles();
		}
		return false;
	}

	public function tool_merge_video_audio() : bool {
		$this->core->clear();
		$this->core->set_subtool("Merge video audio");

		$video = $this->core->get_input_folder(" Video (Folder): ");
		if($video === false) return false;

		$audio = $this->core->get_input_folder(" Audio (Folder): ");
		if($audio === false) return false;

		set_output:
		$output = $this->core->get_input_folder(" Output (Folder): ", true);
		if($output === false) return false;

		if($audio == $output || $video == $output){
			$this->core->echo(" Output folder must be different than audio/video folder");
			goto set_output;
		}

		$errors = 0;
		$this->core->set_errors($errors);

		$files_video = [];
		$files_audio = [];

		$files = $this->core->get_files($video, $this->core->media->extensions_video);
		foreach($files as $file){
			$files_video[pathinfo($file, PATHINFO_FILENAME)] = $file;
		}

		$files = $this->core->get_files($audio, $this->core->media->extensions_audio);
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
			} elseif(!isset($files_audio[$key])){
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

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		set_output:
		$output = $this->core->get_input_folder(" Output (Folder): ", true);
		if($output === false) return false;

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		$errors = 0;
		$this->core->set_errors($errors);

		$lang = $this->core->config->get('SUBTITLES_LANGUAGE');
		$files = $this->core->get_files($input, $this->core->media->extensions_video);
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
			} elseif(!file_exists($srt)){
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

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		set_output:
		$output = $this->core->get_input_folder(" Output (Folder): ", true);
		if($output === false) return false;

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		$size = $this->core->get_input_integer(" Width (0 - no resize): ", 0);
		if($size === false) return false;

		$variants = explode(" ", $this->core->config->get('AVATAR_GENERATOR_VARIANTS'));
		$files = $this->core->get_files($input, $this->core->media->extensions_images);

		$errors = 0;

		$detector = new FaceDetector(gzuncompress($this->core->get_resource("FaceDetector.zz")));
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

	public function tool_fetch_media_info() : bool {
		$this->core->clear();
		$this->core->set_subtool("Fetch media info");

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		$output = $this->core->get_input_folder(" Output (Folder): ", true);
		if($output === false) return false;

		$file_name = 'MediaInfo';

		$line = $this->core->get_input(" File name (Empty, default): ");
		if($line == '#') return false;
		$fname = $this->core->clean_file_name($line);
		if(!empty($fname)) $file_name = $fname;

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
		$files = $this->core->get_files($input, $this->core->media->extensions_video);
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
			} elseif($generate_checksum){
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

		$params = [
			'mode' => strtolower($line[0] ?? '?'),
		];

		if(!in_array($params['mode'], ['0', '1', '2', '3'])) goto set_mode;
		$this->core->clear();

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		set_output:
		$output = $this->core->get_input_folder(" Output (Folder): ", true);
		if($output === false) return false;

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		$errors = 0;

		$files = $this->core->get_files($input);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			$this->core->set_errors($errors);
			if(!file_exists($file)) continue;
			if(!in_array($this->core->get_extension($file), $this->core->media->extensions_images)){
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
			switch(intval($params['mode'])){
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

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

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
					$mime_type = $this->core->media->get_mime_type($file);
					$this->core->write_error("FAILED DETECT EXTENSION \"$file\" MIME TYPE: $mime_type");
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

	public function tool_extract_video() : bool {
		$this->core->clear();
		$this->core->set_subtool("Extract video");

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		set_output:
		$output = $this->core->get_input_folder(" Output (Folder): ", true);
		if($output === false) return false;

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		$errors = 0;
		$this->core->set_errors($errors);
		$files = $this->core->get_files($input, $this->core->media->extensions_media_container);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue 1;
			$media_info = $this->core->media->get_media_info($file);
			foreach($media_info['streams'] as $stream){
				if($stream['codec_type'] == 'video'){
					$language = $stream['tags']['language'] ?? 'unk';
					$index = $stream['index'];
					$suffix = "-$index-$language";
					$codec = strtolower($stream['codec_name'] ?? 'unknown');
					$extension = $this->core->media->get_video_extension($codec);
					if(is_null($extension)){
						$this->core->write_error("UNSUPPORTED VIDEO CODEC \"$codec\" IN \"$file\"");
						$errors++;
					} else {
						$directory = pathinfo(str_ireplace($input, $output, $file), PATHINFO_DIRNAME);
						$this->core->mkdir($directory);
						$new_name = $this->core->get_path("$directory/".pathinfo($file, PATHINFO_FILENAME)."$suffix.$extension");
						if(!file_exists($new_name)){
							$this->core->write_log("EXTRACT \"$new_name\"");
							$this->core->exec("ffmpeg", "-i \"$file\" -map 0:$index -c copy -f $extension \"$new_name\" 2>{$this->core->device_null}");
							if(!file_exists($new_name)){
								$this->core->write_error("EXTRACT FAILED \"$new_name\"");
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
		unset($files);

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_extract_audio() : bool {
		$this->core->clear();
		$this->core->set_subtool("Extract audio");

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		set_output:
		$output = $this->core->get_input_folder(" Output (Folder): ", true);
		if($output === false) return false;

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		$errors = 0;
		$this->core->set_errors($errors);
		$files = $this->core->get_files($input, $this->core->media->extensions_media_container);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue 1;
			$media_info = $this->core->media->get_media_info($file);
			foreach($media_info['streams'] as $stream){
				if($stream['codec_type'] == 'audio'){
					$language = $stream['tags']['language'] ?? 'unk';
					$index = $stream['index'];
					$suffix = "-$index-$language";
					if(($stream['disposition']['comment'] ?? 0) == 1){
						$suffix .= "-commentary";
					}
					$extension = $this->core->media->get_audio_extension($stream['codec_name'] ?? 'aac');
					if(is_null($extension)){
						$this->core->write_error("UNSUPPORTED AUDIO CODEC \"{$stream['codec_name']}\" IN \"$file\"");
						$errors++;
					} else {
						$directory = pathinfo(str_ireplace($input, $output, $file), PATHINFO_DIRNAME);
						$this->core->mkdir($directory);
						$new_name = $this->core->get_path("$directory/".pathinfo($file, PATHINFO_FILENAME)."$suffix.$extension");
						if(!file_exists($new_name)){
							$this->core->write_log("EXTRACT \"$new_name\"");
							$this->core->exec("ffmpeg", "-i \"$file\" -map 0:$index -c copy \"$new_name\" 2>{$this->core->device_null}");
							if(!file_exists($new_name)){
								$this->core->write_error("EXTRACT FAILED \"$new_name\"");
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
		unset($files);

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_extract_subtitles() : bool {
		$this->core->clear();
		$this->core->set_subtool("Extract subtitles");

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		set_output:
		$output = $this->core->get_input_folder(" Output (Folder): ", true);
		if($output === false) return false;

		if($input == $output){
			$this->core->echo(" Output folder must be different than input folder");
			goto set_output;
		}

		$errors = 0;
		$this->core->set_errors($errors);
		$files = $this->core->get_files($input, $this->core->media->extensions_media_container);
		$items = 0;
		$total = count($files);
		foreach($files as $file){
			$items++;
			if(!file_exists($file)) continue 1;
			$media_info = $this->core->media->get_media_info($file);
			foreach($media_info['streams'] as $stream){
				if($stream['codec_type'] == 'subtitle'){
					$language = $stream['tags']['language'] ?? 'unk';
					$index = $stream['index'];
					$suffix = "-$index-$language";
					if(($stream['disposition']['forced'] ?? 0) == 1){
						$suffix .= "-forced";
					}
					$extension = $this->core->media->get_subtitle_extension($stream['codec_name'] ?? 'vtt');
					if(is_null($extension)){
						$this->core->write_error("UNSUPORTED SUBTITLES \"{$stream['codec_name']}\" IN \"$file\"");
						$errors++;
					} else {
						$directory = pathinfo(str_ireplace($input, $output, $file), PATHINFO_DIRNAME);
						$this->core->mkdir($directory);
						$new_name = $this->core->get_path("$directory/".pathinfo($file, PATHINFO_FILENAME)."$suffix.$extension");
						if(!file_exists($new_name)){
							$this->core->write_log("EXTRACT \"$new_name\"");
							$this->core->exec("ffmpeg", "-i \"$file\" -map 0:$index -c copy \"$new_name\" 2>{$this->core->device_null}");
							if(!file_exists($new_name)){
								$this->core->write_error("EXTRACT FAILED \"$new_name\"");
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
		unset($files);

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_validate_subtitles() : bool {
		$this->core->clear();
		$this->core->set_subtool("Validate subtitles");

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$subtitles_validator = new SubtitlesValidator($this->core);

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			if(!file_exists($folder)) continue;
			$files = $this->core->get_files($folder, ['srt']);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				$validation = $subtitles_validator->srt_validate($file);
				if($validation === false) continue 1;
				$errors_in_file = count($validation);
				$errors += $errors_in_file;
				if(!empty($validation)){
					$this->core->write_error("Validation \"$file\" total $errors_in_file errors");
					$this->core->write_error($validation);
					$this->core->write_error("");
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

	public function tool_compare_subtitles() : bool {
		$this->core->clear();
		$this->core->set_subtool("Compare subtitles");

		$input_a = $this->core->get_input_folder(" Input (Folder A): ");
		if($input_a === false) return false;

		set_input_b:
		$input_b = $this->core->get_input_folder(" Input (Folder B): ");
		if($input_b === false) return false;

		if($input_a == $input_b){
			$this->core->echo(" Input Folder A must be different than input Folder B");
			goto set_input_b;
		}

		$files_a = $this->core->get_files($input_a, ['srt']);
		$files_b = $this->core->get_files($input_b, ['srt']);

		$subtitles_validator = new SubtitlesValidator($this->core);

		$map = [];
		foreach($files_b as $file){
			$map[pathinfo($file, PATHINFO_BASENAME)] = $file;
		}

		$errors = 0;
		$this->core->set_errors($errors);
		$items = 0;
		$total = count($files_a);
		foreach($files_a as $file){
			$items++;
			$fname = pathinfo($file, PATHINFO_BASENAME);
			if(!isset($map[$fname])){
				$this->core->write_error("File \"$fname\" not found in folder B");
				$errors++;
				continue;
			}
			$validation = $subtitles_validator->srt_compare($file, $map[$fname]);
			$count = (object)[
				'global' => count($validation->global),
				'file_a' => count($validation->file_a),
				'file_b' => count($validation->file_b),
			];
			if($count->global > 0 || $count->file_a > 0 || $count->file_b > 0){
				$this->core->write_error("Comparsion \"$file\" to \"{$map[$fname]}\"");
				if($count->global > 0){
					$this->core->write_error("Global errors:");
					$this->core->write_error($validation->global);
					$errors += $count->global;
				}
				if($count->file_a > 0){
					$this->core->write_error("File left errors:");
					$this->core->write_error($validation->file_a);
					$errors += $count->file_a;
				}
				if($count->file_b > 0){
					$this->core->write_error("File right errors:");
					$this->core->write_error($validation->file_b);
					$errors += $count->file_b;
				}
				$this->core->write_error("");
			} else {
				$this->core->write_log("Comparsion \"$file\" to \"{$map[$fname]}\" SUCCESS");
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$this->core->progress($items, $total);

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
		} elseif($meta->audio_bitrate == 0){
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