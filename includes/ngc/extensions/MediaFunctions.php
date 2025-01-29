<?php

declare(strict_types=1);

namespace NGC\Extensions;

use GdImage;
use Imagick;
use Exception;

class MediaFunctions {

	public object $core;

	public array $vr_tags = ['_LR_180', '_FISHEYE190', '_MKX200', '_MKX220', '_VRCA220', '_TB_180', '_360'];

	public const MEDIA_ORIENTATION_HORIZONTAL = 0;
	public const MEDIA_ORIENTATION_VERTICAL = 1;
	public const MEDIA_ORIENTATION_SQUARE = 2;

	public function __construct(object $core){
		$this->core = $core;
	}

	public function get_image_from_path(string $path) : GdImage|bool|null {
		if(!file_exists($path)) return null;
		switch(strtolower(pathinfo($path, PATHINFO_EXTENSION))){
			case 'bmp': return @imagecreatefrombmp($path);
			case 'avif': return @imagecreatefromavif($path);
			case 'gd2': return @imagecreatefromgd2($path);
			case 'gd': return @imagecreatefromgd($path);
			case 'gif': return @imagecreatefromgif($path);
			case 'jpeg':
			case 'jpg': {
				return @imagecreatefromjpeg($path);
			}
			case 'png': return @imagecreatefrompng($path);
			case 'tga': return @imagecreatefromtga($path);
			case 'wbmp': return @imagecreatefromwbmp($path);
			case 'webp': return @imagecreatefromwebp($path);
			case 'xbm': return @imagecreatefromxbm($path);
			case 'xpm': return @imagecreatefromxpm($path);
		}
		return null;
	}

	public function get_image_resolution(string $path) : string {
		$image = $this->get_image_from_path($path);
		if(!$image){
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

	public function is_gif_animated(string $path) : bool {
		if(!($fh = @fopen($path, 'rb'))) return false;
		$count = 0;
		while(!feof($fh) && $count < 2){
			$chunk = fread($fh, 1024 * 100);
			$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
		}
		fclose($fh);
		return $count > 1;
	}

	public function ffprobe_get_resolution(string $path) : string {
		$output = [];
		$this->core->exec("ffprobe", "-v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 \"$path\" 2>".$this->core->get_output_null(), $output);
		return rtrim($output[0] ?? '0x0', 'x');
	}

	public function get_video_languages(string $path) : array {
		$output = [];
		$this->core->exec("ffprobe", "-i \"$path\" -show_entries stream=index:stream_tags=language -select_streams a -of compact=p=0:nk=1 2> ".$this->core->get_output_null(), $output);
		$data = [];
		foreach($output as $language){
			$parts = explode("|", $language);
			array_push($data, $parts[1] ?? $parts[0]);
		}
		return $data;
	}

	public function get_extension_by_mime_type(string $path) : string|false {
		if(!file_exists($path)) return false;
		$mime_type = mime_content_type($path);
		if(!$mime_type) return false;
		return $this->mime_type_to_extension($mime_type);
	}

	public function get_video_thumbnail(string $path, string $output, int $w, int $r, int $c) : bool {
		$out = [];
		if(!$this->core->windows && !file_exists("/usr/bin/mtn")) return false;
		$input_file = $this->core->get_path("$output/".pathinfo($path, PATHINFO_FILENAME)."_s.jpg");
		$output_file = $this->core->get_path("$output/".pathinfo($path, PATHINFO_FILENAME).".webp");
		if(file_exists($output_file)) return true;
		if(!file_exists($input_file)){
			$this->core->exec("mtn", "-w $w -r $r -c $c -P \"$path\" -O \"$output\" >".$this->core->get_output_null()." 2>".$this->core->get_output_null(), $out);
			if(!file_exists($input_file)) return false;
		}
		$image = new Imagick();
		$image->readImage($input_file);
		$image->writeImage($output_file);
		unlink($input_file);
		return file_exists($output_file);
	}

	public function get_media_orientation(int $width, int $height) : int {
		if($width > $height){
			return self::MEDIA_ORIENTATION_HORIZONTAL;
		} else if($height > $width){
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

	public function get_media_quality(int $width, int $height, bool $is_vr = false) : string {
		$w = max($width, $height);
		$h = min($width, $height);
		if($is_vr) return strval($h);
		if($w >= 61440 - 7680 || $h == 34560){
			return '34560';
		} else if($w >= 30720 - 3840 || $h == 17280){
			return '17280';
		} else if($w >= 15360 - 1920 || $h == 8640){
			return '8640';
		} else if($w >= 7680 - 960 || $h == 4320){
			return '4320';
		} else if($w >= 3840 - 320 || $h == 2160){
			return '2160';
		} else if($w >= 2560 - 160 || $h == 1440){
			return '1440';
		} else if($w >= 1920 - 160 || $h == 1080){
			return '1080';
		} else if($w >= 1280 - 80 || $h == 720){
			return '720';
		} else if($w >= 960 - 80 || $h == 540){
			return '540';
		} else if($w >= 640 - 40 || $h == 480){
			return '480';
		} else if($w >= 480 - 40 || $h == 360){
			return '360';
		} else if($w >= 320 - 32 || $h == 240){
			return '240';
		} else {
			return '144';
		}
	}

	public function get_image_color_count(string $path) : int|null {
		if(!file_exists($path)) return -1;
		try {
			$image = new Imagick($path);
			if(!$image->valid()) return null;
			return $image->getImageColors();
		}
		catch(Exception $e){
			return -1;
		}
	}

	public function get_image_color_group(int $colors) : string {
		if($colors > 500000){
			return '500001 - 999999';
		} else if($colors > 400000 && $colors <= 500000){
			return '400001 - 500000';
		} else if($colors > 300000 && $colors <= 400000){
			return '300001 - 400000';
		} else if($colors > 200000 && $colors <= 300000){
			return '200001 - 300000';
		} else if($colors > 100000 && $colors <= 200000){
			return '100001 - 200000';
		} else if($colors > 50000 && $colors <= 100000){
			return '050001 - 100000';
		} else if($colors > 40000 && $colors <= 50000){
			return '040001 - 050000';
		} else if($colors > 30000 && $colors <= 40000){
			return '030001 - 040000';
		} else if($colors > 20000 && $colors <= 30000){
			return '020001 - 030000';
		} else if($colors > 10000 && $colors <= 20000){
			return '010001 - 020000';
		} else if($colors > 5000 && $colors <= 10000){
			return '005001 - 010000';
		} else if($colors > 1000 && $colors <= 5000){
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
		$name = strtoupper(pathinfo($path, PATHINFO_FILENAME));
		foreach($this->vr_tags as $tag){
			if(strpos("$name#", "$tag#") !== false) return true;
		}
		return false;
	}

	public function is_ar_video(string $path) : bool {
		$name = strtoupper(pathinfo($path, PATHINFO_FILENAME));
		foreach($this->vr_tags as $tag){
			if(strpos("$name#", "{$tag}_ALPHA#") !== false) return true;
		}
		return false;
	}

	public function get_vr_mode(string $name) : array {
		if(strpos($name, '_LR_180') !== false){
			$screen_type = "dome";
			$stereo_mode = "sbs";
		} else if(strpos($name, '_FISHEYE190') !== false){
			$screen_type = "fisheye";
			$stereo_mode = "sbs";
		} else if(strpos($name, '_MKX200') !== false){
			$screen_type = "mkx200";
			$stereo_mode = "sbs";
		} else if(strpos($name, '_MKX220') !== false || strpos($name, '_VRCA220') !== false){
			$screen_type = "mkx220";
			$stereo_mode = "sbs";
		} else if(strpos($name, '_TB_180') !== false){
			$screen_type = "180";
			$stereo_mode = "tb";
		} else if(strpos($name, '_360') !== false){
			$screen_type = "360";
			$stereo_mode = (strpos($name, '_SBS_') !== false) ? "sbs" : "off";
		} else {
			$screen_type = "flat";
			$stereo_mode = "off";
		}
		return [
			'screen_type' => $screen_type,
			'stereo_mode' => $stereo_mode,
			'alpha' => (strpos($name, '_ALPHA') !== false),
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
		$gcd = function($a, $b) use (&$gcd) : mixed {
			return ($b == 0) ? $a : $gcd($b, $a % $b);
		};
		$divisor = $gcd($width, $height);
		$aspectRatioWidth = $width / $divisor;
		$aspectRatioHeight = $height / $divisor;
		return "$aspectRatioWidth:$aspectRatioHeight";
	}

	public function get_audio_channels_string(int $channels) : string {
		switch($channels){
			case 6: return '5.1';
			case 2: return 'Stereo';
			case 1: return 'Mono';
			case 0: return 'None';
		}
		return 'Unknown';
	}

	public function get_media_info(string $path): array {
		$output = [];
		$this->core->exec("ffprobe", "-v error -show_entries format -show_streams -of json \"$path\" 2>".$this->core->get_output_null(), $output);
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
					$is_vr = $this->core->media->is_vr_video($path);
					$is_ar = $this->core->media->is_ar_video($path);
					$meta['video_quality'] = $this->core->media->get_media_quality($width, $height, $is_vr || $is_ar);
					$orientation = $this->core->media->get_media_orientation($width, $height);
					$meta['video_orientation'] = $this->core->media->get_media_orientation_name($orientation);
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
		$mime_map = [
			'video/3gpp2' => '3g2',
			'video/3gpp' => '3gp',
			'application/x-7z-compressed' => '7z',
			'audio/x-aac' => 'aac',
			'application/vnd.android.package-archive' => 'apk',
			'audio/aac' => 'aac',
			'application/x-abiword' => 'abw',
			'application/x-freearc' => 'arc',
			'video/x-msvideo' => 'avi',
			'application/vnd.amazon.ebook' => 'azw',
			'image/bmp' => 'bmp',
			'application/x-bzip' => 'bz',
			'application/x-bzip2' => 'bz2',
			'application/x-csh' => 'csh',
			'text/css' => 'css',
			'text/csv' => 'csv',
			'application/msword' => 'doc',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
			'application/vnd.ms-fontobject' => 'eot',
			'application/epub+zip' => 'epub',
			'application/gzip' => 'gz',
			'image/gif' => 'gif',
			'text/html' => 'html',
			'image/vnd.microsoft.icon' => 'ico',
			'text/calendar' => 'ics',
			'application/java-archive' => 'jar',
			'image/jpeg' => 'jpg',
			'text/javascript' => 'js',
			'application/json' => 'json',
			'application/ld+json' => 'jsonld',
			'audio/midi' => 'midi',
			'audio/mpeg' => 'mp3',
			'video/mp4' => 'mp4',
			'video/mpeg' => 'mpeg',
			'application/vnd.apple.installer+xml' => 'mpkg',
			'application/vnd.oasis.opendocument.presentation' => 'odp',
			'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
			'application/vnd.oasis.opendocument.text' => 'odt',
			'audio/ogg' => 'oga',
			'video/ogg' => 'ogv',
			'application/ogg' => 'ogx',
			'audio/opus' => 'opus',
			'font/otf' => 'otf',
			'image/png' => 'png',
			'application/pdf' => 'pdf',
			'application/x-httpd-php' => 'php',
			'application/vnd.ms-powerpoint' => 'ppt',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
			'application/vnd.rar' => 'rar',
			'application/rtf' => 'rtf',
			'application/x-sh' => 'sh',
			'image/svg+xml' => 'svg',
			'application/x-tar' => 'tar',
			'image/tiff' => 'tiff',
			'video/mp2t' => 'ts',
			'font/ttf' => 'ttf',
			'text/plain' => 'txt',
			'application/vnd.visio' => 'vsd',
			'audio/wav' => 'wav',
			'audio/webm' => 'weba',
			'video/webm' => 'webm',
			'image/webp' => 'webp',
			'font/woff' => 'woff',
			'font/woff2' => 'woff2',
			'application/xhtml+xml' => 'xhtml',
			'application/vnd.ms-excel' => 'xls',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
			'application/xml' => 'xml',
			'text/xml' => 'xml',
			'application/zip' => 'zip',
			'audio/eac3' => 'eac3',
		];

		return isset($mime_map[$mime_type]) ? $mime_map[$mime_type] : false;
	}

}

?>
