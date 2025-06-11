<?php

/**
 * NGC-TOOLKIT v2.6.1 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Extensions;

use Script;
use Toolkit;
use finfo;
use GdImage;
use Imagick;
use Exception;
use NGC\Core\IniFile;

class MediaFunctions {

	private Toolkit|Script $core;
	private ?IniFile $mime_types = null;
	private ?finfo $file_info = null;

	public array $vr_tags = ['_LR_180', '_FISHEYE190', '_MKX200', '_MKX220', '_VRCA220', '_TB_180', '_360'];
	public array $extensions_images = ['bmp', 'dpx', 'exr', 'gif', 'jpeg', 'jpg', 'jpe', 'pam', 'pbm', 'pcx', 'pgm', 'png', 'ppm', 'sgi', 'tga', 'tif', 'tiff', 'webp', 'xwd', 'jfif'];
	public array $extensions_video = ['mp4', 'mkv', 'mov', 'avi', 'flv', 'webm', 'wmv', 'mpeg', 'mpg', 'm4v', 'ts', 'm2ts', '3gp', '3g2', 'ogv', 'rm', 'rmvb', 'vob', 'asf', 'f4v', 'divx', 'dv', 'mts', 'yuv', 'mxf', 'nut', 'h264', 'hevc', 'av1', 'prores', 'mpv', 'nsv', 'amv', 'drc', 'm1v', 'm2v', 'roq'];
	public array $extensions_audio = ['aac', 'ac3', 'amr', 'ape', 'dts', 'eac3', 'flac', 'm4a', 'mlp', 'mp2', 'mp3', 'ogg', 'opus', 'ra', 'thd', 'tta', 'vqf', 'wav', 'wma', 'wv'];
	public array $extensions_subtitle = ['srt', 'ass', 'ssa', 'vtt', 'sub', 'mpl2', 'jss', 'smi', 'rt', 'txt'];
	public array $extensions_media_container = ['mp4', 'mkv', 'mov', 'avi', 'flv', 'webm', 'wmv', 'mpeg', 'mpg', 'm4v', 'ts', 'm2ts', '3gp', '3g2', 'ogv', 'vob', 'asf', 'f4v', 'mxf', 'nut', 'rm', 'rmvb', 'drc', 'nsv', 'amv', 'mks', 'mka'];

	public array $codec_extensions_video = [
		'h264' => 'h264',
		'hevc' => 'h265',
		'mpeg2video' => 'm2v',
		'mpeg1video' => 'm1v',
		'vp8' => 'ivf',
		'vp9' => 'ivf',
		'av1' => 'ivf',
		'prores' => 'mov',
		'dvvideo' => 'dv',
		'mjpeg' => 'mjpeg',
		'png' => 'png',
		'gif' => 'gif',
		'ffv1' => 'nut',
		'theora' => 'ogv',
		'vc1' => 'vc1',
		'msmpeg4v2' => 'avi',
		'msmpeg4v3' => 'avi',
		'huffyuv' => 'avi',
		'snow' => 'nut',
	];

	public array $codec_extensions_audio = [
		'aac' => 'aac',
		'mp3' => 'mp3',
		'ac3' => 'ac3',
		'eac3' => 'eac3',
		'mp2' => 'mp2',
		'vorbis' => 'ogg',
		'opus' => 'opus',
		'amr_nb' => 'amr',
		'amr_wb' => 'amr',
		'flac' => 'flac',
		'alac' => 'm4a',
		'pcm_s16le' => 'wav',
		'pcm_s24le' => 'wav',
		'pcm_s32le' => 'wav',
		'pcm_u8' => 'wav',
		'pcm_mulaw' => 'wav',
		'pcm_alaw' => 'wav',
		'ape' => 'ape',
		'tta' => 'tta',
		'wavpack' => 'wv',
		'mlp' => 'mlp',
		'dts' => 'dts',
		'dts_hd' => 'dts',
		'truehd' => 'thd',
		'ra' => 'ra',
		'wma' => 'wma',
		'vqf' => 'vqf',
		'adpcm_ima_wav' => 'wav',
	];

	public array $codec_extensions_subtitle = [
		'subrip' => 'srt',
		'ass' => 'ass',
		'ssa' => 'ssa',
		'mov_text' => 'srt',
		'webvtt' => 'vtt',
		'microdvd' => 'sub',
		'mpl2' => 'mpl2',
		'jacosub' => 'jss',
		'sami' => 'smi',
		'realtext' => 'rt',
		'subviewer' => 'sub',
		'subviewer1' => 'sub',
		'vplayer' => 'txt',
	];

	public array $vr_quality_map = [
		7680 => '8K',
		5760 => '6K',
		3840 => '4K',
		1920 => '2K',
		1600 => 'FullHD',
	];

	public const MEDIA_ORIENTATION_HORIZONTAL = 0;
	public const MEDIA_ORIENTATION_VERTICAL = 1;
	public const MEDIA_ORIENTATION_SQUARE = 2;

	public function __construct(Toolkit|Script $core){
		$this->core = $core;
		$this->file_info = finfo_open(FILEINFO_MIME_TYPE, $this->core->get_resource("magic.mgc"));
	}

	public function __destruct(){
		finfo_close($this->file_info);
		$this->file_info = null;
	}

	public function get_mime_type(string $path) : string|false {
		if(!file_exists($path)) return false;
		try {
			$mime_type = @finfo_file($this->file_info, $path);
		}
		catch(Exception $e){
			return false;
		}
		return is_string($mime_type) ? mb_strtolower($mime_type) : false;
	}

	public function get_string_mime_type(string $content) : string|false {
		try {
			$mime_type = @finfo_buffer($this->file_info, $content);
		}
		catch(Exception $e){
			return false;
		}
		return is_string($mime_type) ? mb_strtolower($mime_type) : false;
	}

	public function get_image_from_path(string $path) : GdImage|null {
		if(!file_exists($path)) return null;
		$mime = $this->get_mime_type($path);
		if($mime === false) return null;
		if($this->is_image_animated($path)) return null;
		$image = null;
		switch($mime){
			case 'image/bmp':{
				$image = @imagecreatefrombmp($path);
				break;
			}
			case 'image/avif': {
				$image = @imagecreatefromavif($path);
				break;
			}
			case 'image/gd2': {
				$image = @imagecreatefromgd2($path);
				break;
			}
			case 'image/gd': {
				$image = @imagecreatefromgd($path);
				break;
			}
			case 'image/gif': {
				$image = @imagecreatefromgif($path);
				break;
			}
			case 'image/jpeg': {
				$image = @imagecreatefromjpeg($path);
				break;
			}
			case 'image/png': {
				$image = @imagecreatefrompng($path);
				break;
			}
			case 'image/x-tga': {
				$image = @imagecreatefromtga($path);
				break;
			}
			case 'image/vnd.wap.wbmp': {
				$image = @imagecreatefromwbmp($path);
				break;
			}
			case 'image/webp': {
				$image = @imagecreatefromwebp($path);
				break;
			}
			case 'image/x-xbitmap': {
				$image = @imagecreatefromxbm($path);
				break;
			}
			case 'image/x-xpixmap': {
				$image = @imagecreatefromxpm($path);
				break;
			}
		}
		if($image === false) return null;
		return $image;
	}

	public function get_image_resolution(string $path) : string {
		$image = $this->get_image_from_path($path);
		if(is_null($image)){
			try {
				@$image = new Imagick($path);
				$w = @$image->getImageWidth();
				$h = @$image->getImageHeight();
				@$image->clear();
				return "{$w}x{$h}";
			}
			catch(Exception $e){
				return $this->ffprobe_get_resolution($path);
			}
		}
		$w = imagesx($image);
		$h = imagesy($image);
		imagedestroy($image);
		return "{$w}x{$h}";
	}

	public function is_image_animated(string $path) : bool {
		if(!file_exists($path)) return false;
		switch($this->get_mime_type($path)){
			case 'image/gif': {
				try {
					$image = new Imagick($path);
					$images_number = $image->getNumberImages();
					$image->clear();
					return $images_number > 1;
				}
				catch(Exception $e){
					return false;
				}
			}
			case 'image/webp': {
				if(!($fp = @fopen($path, 'rb'))) return false;
				$header = fread($fp, 1024);
				fclose($fp);
				return str_contains($header, 'ANMF');
			}
			case 'image/apng': {
				if(!($fp = @fopen($path, 'rb'))) return false;
				$data = fread($fp, 1024 * 100);
				fclose($fp);
				return strpos($data, 'acTL') !== false;
			}
			default: return false;
		}
	}

	public function ffprobe_get_resolution(string $path) : string {
		$output = [];
		$this->core->exec("ffprobe", "-v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 \"$path\" 2>{$this->core->device_null}", $output);
		return rtrim($output[0] ?? '0x0', 'x');
	}

	public function get_video_languages(string $path) : array {
		$output = [];
		$this->core->exec("ffprobe", "-i \"$path\" -show_entries stream=index:stream_tags=language -select_streams a -of compact=p=0:nk=1 2> {$this->core->device_null}", $output);
		$data = [];
		foreach($output as $language){
			$parts = explode("|", $language);
			array_push($data, $parts[1] ?? $parts[0]);
		}
		return $data;
	}

	public function get_extension_by_mime_type(string $path) : string|false {
		if(!file_exists($path)) return false;
		$mime_type = $this->get_mime_type($path);
		if($mime_type === false) return false;
		return $this->mime_type_to_extension($mime_type);
	}

	public function get_video_thumbnail(string $path, string $output, int $w, int $r, int $c) : bool {
		$out = [];
		if($this->core->get_system_type() != SYSTEM_TYPE_WINDOWS && !file_exists("/usr/bin/mtn")) return false;
		$input_file = $this->core->get_path("$output/".pathinfo($path, PATHINFO_FILENAME)."_s.jpg");
		$output_file = $this->core->get_path("$output/".pathinfo($path, PATHINFO_FILENAME).".webp");
		if(file_exists($output_file)) return true;
		if(!file_exists($input_file)){
			$this->core->exec("mtn", "-w $w -r $r -c $c -P \"$path\" -O \"$output\" >{$this->core->device_null}"." 2>{$this->core->device_null}", $out);
			if(!file_exists($input_file)) return false;
		}
		$image = new Imagick();
		$image->readImage($input_file);
		$image->writeImage($output_file);
		$image->clear();
		unlink($input_file);
		return file_exists($output_file);
	}

	public function get_media_orientation(int $width, int $height) : int {
		if($width > $height){
			return self::MEDIA_ORIENTATION_HORIZONTAL;
		} elseif($height > $width){
			return self::MEDIA_ORIENTATION_VERTICAL;
		} else {
			return self::MEDIA_ORIENTATION_SQUARE;
		}
	}

	public function get_media_orientation_name(int $orientation) : string {
		switch($orientation){
			case self::MEDIA_ORIENTATION_HORIZONTAL: return 'Horizontal';
			case self::MEDIA_ORIENTATION_VERTICAL: return 'Vertical';
			case self::MEDIA_ORIENTATION_SQUARE: return 'Square';
		}
		return 'Unknown';
	}

	public function get_media_quality(int $width, int $height, bool $is_vr = false) : int {
		$w = max($width, $height);
		$h = min($width, $height);
		if($is_vr) return $h;
		if($w >= 61440 - 7680 || $h == 34560){
			return 34560;
		} elseif($w >= 30720 - 3840 || $h == 17280){
			return 17280;
		} elseif($w >= 15360 - 1920 || $h == 8640){
			return 8640;
		} elseif($w >= 7680 - 960 || $h == 4320){
			return 4320;
		} elseif($w >= 3840 - 320 || $h == 2160){
			return 2160;
		} elseif($w >= 2560 - 160 || $h == 1440){
			return 1440;
		} elseif($w >= 1920 - 160 || $h == 1080){
			return 1080;
		} elseif($w >= 1280 - 80 || $h == 720){
			return 720;
		} elseif($w >= 960 - 80 || $h == 540){
			return 540;
		} elseif($w >= 640 - 40 || $h == 480){
			return 480;
		} elseif($w >= 480 - 40 || $h == 360){
			return 360;
		} elseif($w >= 320 - 32 || $h == 240){
			return 240;
		} else {
			return 144;
		}
	}

	public function get_image_color_count(string $path) : ?int {
		if(!file_exists($path)) return -1;
		try {
			$image = new Imagick($path);
			if(!$image->valid()) return null;
			$count = $image->getImageColors();
			$image->clear();
			return $count;
		}
		catch(Exception $e){
			return -1;
		}
	}

	public function get_image_color_group(int $colors) : string {
		if($colors > 500000){
			return '500001 - 999999';
		} elseif($colors > 400000 && $colors <= 500000){
			return '400001 - 500000';
		} elseif($colors > 300000 && $colors <= 400000){
			return '300001 - 400000';
		} elseif($colors > 200000 && $colors <= 300000){
			return '200001 - 300000';
		} elseif($colors > 100000 && $colors <= 200000){
			return '100001 - 200000';
		} elseif($colors > 50000 && $colors <= 100000){
			return '050001 - 100000';
		} elseif($colors > 40000 && $colors <= 50000){
			return '040001 - 050000';
		} elseif($colors > 30000 && $colors <= 40000){
			return '030001 - 040000';
		} elseif($colors > 20000 && $colors <= 30000){
			return '020001 - 030000';
		} elseif($colors > 10000 && $colors <= 20000){
			return '010001 - 020000';
		} elseif($colors > 5000 && $colors <= 10000){
			return '005001 - 010000';
		} elseif($colors > 1000 && $colors <= 5000){
			return '001001 - 005000';
		} else {
			return '000000 - 001000';
		}
	}

	public function format_episode(int $episode, int $digits, int $max) : string {
		$episode = $episode % $max;
		if($episode < 0) $episode += $max;
		$ep = strval($episode);
		return str_repeat("0", $digits - strlen($ep)).$ep;
	}

	public function is_vr_video(string $path) : bool {
		$name = mb_strtoupper(pathinfo($path, PATHINFO_FILENAME));
		foreach($this->vr_tags as $tag){
			if(str_contains("$name#", "$tag#")) return true;
		}
		return false;
	}

	public function is_ar_video(string $path) : bool {
		$name = mb_strtoupper(pathinfo($path, PATHINFO_FILENAME));
		foreach($this->vr_tags as $tag){
			if(str_contains("$name#", "{$tag}_ALPHA#")) return true;
		}
		return false;
	}

	public function get_vr_quality_string(int $width) : ?string {
		foreach($this->vr_quality_map as $min_width => $label){
			if($width >= $min_width){
				return $label;
			}
		}
		return null;
	}

	public function get_vr_mode(string $name) : array {
		if(str_contains($name, '_LR_180')){
			$screen_type = "dome";
			$stereo_mode = "sbs";
		} elseif(str_contains($name, '_FISHEYE190')){
			$screen_type = "fisheye";
			$stereo_mode = "sbs";
		} elseif(str_contains($name, '_MKX200')){
			$screen_type = "mkx200";
			$stereo_mode = "sbs";
		} elseif(str_contains($name, '_MKX220') || str_contains($name, '_VRCA220')){
			$screen_type = "mkx220";
			$stereo_mode = "sbs";
		} elseif(str_contains($name, '_TB_180')){
			$screen_type = "180";
			$stereo_mode = "tb";
		} elseif(str_contains($name, '_360')){
			$screen_type = "360";
			$stereo_mode = (str_contains($name, '_SBS_')) ? "sbs" : "off";
		} else {
			$screen_type = "flat";
			$stereo_mode = "off";
		}
		return [
			'screen_type' => $screen_type,
			'stereo_mode' => $stereo_mode,
			'alpha' => (str_contains($name, '_ALPHA')),
		];
	}

	public function vr_screen_type(string $screen_type) : string {
		switch($screen_type){
			case "dome": return "Dome 180°";
			case "fisheye": return "Fisheye 190°";
			case "mkx200": return "MKX 200°";
			case "mkx220": return "MKX 220°";
			case "180": return "180°";
			case "360": return "360°";
			case "flat": return "Flat";
			default: return "Unknown";
		}
	}

	public function vr_stereo_mode(string $stereo_mode) : string {
		switch($stereo_mode){
			case "sbs": return "Side-by-Side (SBS)";
			case "tb": return "Top-Bottom (TB)";
			case "off": return "No Stereo";
			default: return "Unknown";
		}
	}

	public function calculate_aspect_ratio(int $width, int $height) : string {
		$gcd = function(int $a, int $b) use (&$gcd) : int {
			return ($b == 0) ? $a : $gcd($b, $a % $b);
		};
		$divisor = $gcd($width, $height);
		$aspectRatioWidth = $width / $divisor;
		$aspectRatioHeight = $height / $divisor;
		return "$aspectRatioWidth:$aspectRatioHeight";
	}

	public function get_audio_channels_string(int $channels) : string {
		if($channels > 8) return "{$channels}ch (Multi)";
		switch($channels){
			case 8: return '7.1';
			case 7: return '6.1';
			case 6: return '5.1';
			case 2: return 'Stereo';
			case 1: return 'Mono';
			case 0: return 'None';
		}
		return 'Unknown';
	}

	public function get_media_info(string $path) : array {
		$output = [];
		$this->core->exec("ffprobe", "-v error -show_entries format -show_streams -of json \"$path\" 2>{$this->core->device_null}", $output);
		$info = json_decode(implode('', $output), true);
		return $info;
	}

	public function get_media_info_simple(string $path) : object|false {
		if(!file_exists($path)) return false;
		$media_info = $this->get_media_info($path);
		$video_duration_seconds = intval(round(floatval($media_info['format']['duration'])));
		$file_size = filesize($path);
		$meta = [
			'video_resolution' => null,
			'video_quality' => 0,
			'video_duration' => $this->core->seconds_to_time($video_duration_seconds, true),
			'video_duration_seconds' => $video_duration_seconds,
			'video_fps' => null,
			'video_bitrate' => intval($media_info['format']['bit_rate'] ?? 0),
			'video_codec' => null,
			'video_aspect_ratio' => null,
			'video_orientation' => null,
			'audio_codec' => 'none',
			'audio_bitrate' => 0,
			'audio_channels' => 0,
			'file_size' => $file_size,
			'file_size_human' => $this->core->format_bytes($file_size, 2, false),
			'file_creation_time' => date("Y-m-d H:i:s", filectime($path)),
			'file_modification_time' => date("Y-m-d H:i:s", filemtime($path)),
		];
		$need_audio = true;
		foreach($media_info['streams'] as $stream){
			switch($stream['codec_type']){
				case 'video': {
					if(($stream['disposition']['attached_pic'] ?? 0) == 1) continue 2;
					$width = intval($stream['width']);
					$height = intval($stream['height']);
					$meta['video_resolution'] = "{$stream['width']}x{$stream['height']}";
					$is_vr = $this->is_vr_video($path);
					$is_ar = $this->is_ar_video($path);
					$meta['video_quality'] = $this->get_media_quality($width, $height, $is_vr || $is_ar);
					$orientation = $this->get_media_orientation($width, $height);
					$meta['video_orientation'] = $this->get_media_orientation_name($orientation);
					eval('$fps = '.trim(preg_replace('/[^0-9.\/]+/', "", $stream['r_frame_rate'])).';');
					$meta['video_fps'] = round($fps, 4);
					$meta['video_codec'] = $stream['codec_name'] ?? null;
					$meta['video_aspect_ratio'] = $this->calculate_aspect_ratio($stream['width'], $stream['height']);
					break;
				}
				case 'audio': {
					if(!$need_audio) continue 2;
					$need_audio = false;
					$meta['audio_codec'] = $stream['codec_name'] ?? null;
					$meta['audio_bitrate'] = $stream['bit_rate'] ?? ($stream['tags']['BPS'] ?? null);
					if(!is_null($meta['audio_bitrate'])) $meta['audio_bitrate'] = intval($meta['audio_bitrate']);
					$meta['audio_channels'] = $stream['channels'];
					break;
				}
			}
		}
		return (object)$meta;
	}

	public function mime_type_to_extension(string $mime_type) : string|false {
		if($mime_type == 'application/octet-stream') return false;
		if(is_null($this->mime_types)){
			$this->mime_types = new IniFile($this->core->get_resource("MimeTypes.ini"));
		}
		return $this->mime_types->get($mime_type, false);
	}

	public function get_subtitle_extension(string $codec_name) : ?string {
		$codec_name = strtolower($codec_name);
		return $this->codec_extensions_subtitle[$codec_name] ?? null;
	}

	public function get_audio_extension(string $codec_name) : ?string {
		$codec_name = strtolower($codec_name);
		return $this->codec_extensions_audio[$codec_name] ?? null;
	}

	public function get_video_extension(string $codec_name) : ?string {
		$codec_name = strtolower($codec_name);
		return $this->codec_extensions_video[$codec_name] ?? null;
	}

	public function timecode_to_seconds(int $h, int $m, int $s, int $ms) : float {
		return $h * 3600 + $m * 60 + $s + $ms / 1000;
	}

	public function get_files_mime_type(string $path, ?array $include_mime_types = null, ?array $exclude_mime_types = null, ?array $name_filters = null, bool $case_sensitive = false, bool $recursive = true) : array {
		if(!file_exists($path) || !is_readable($path)) return [];
		if(!$case_sensitive && !is_null($name_filters)){
			$name_filters = $this->core->array_to_lower($name_filters);
		}
		$data = [];
		$this->scan_dir_safe_mime_type($path, $data, $include_mime_types, $exclude_mime_types, $name_filters, $case_sensitive, $recursive);
		asort($data, SORT_STRING);
		return $data;
	}

	private function scan_dir_safe_mime_type(string $dir, array &$data, ?array $include_mime_types, ?array $exclude_mime_types, ?array $name_filters, bool $case_sensitive, bool $recursive) : void {
		$items = @scandir($dir);
		if($items === false) return;
		foreach($items as $item){
			if($item === '.' || $item === '..') continue;
			$full_path = $dir.DIRECTORY_SEPARATOR.$item;
			if(is_dir($full_path)){
				if(!$recursive) continue;
				if(!is_readable($full_path)) continue;
				$this->scan_dir_safe_mime_type($full_path, $data, $include_mime_types, $exclude_mime_types, $name_filters, $case_sensitive, $recursive);
				continue;
			}
			if(!is_file($full_path) || !is_readable($full_path)) continue;
			$mime_type = $this->get_mime_type($full_path);
			if($mime_type === false) continue;
			if(!is_null($include_mime_types) && !in_array($mime_type, $include_mime_types)) continue;
			if(!is_null($exclude_mime_types) && in_array($mime_type, $exclude_mime_types)) continue;
			$basename = basename($full_path);
			if(!is_null($name_filters)){
				$check_name = $case_sensitive ? $basename : mb_strtolower($basename);
				if(!$this->core->filter($check_name, $name_filters)) continue;
			}
			$full_path = realpath($full_path);
			if($full_path !== false) $data[] = $full_path;
		}
	}
}

?>