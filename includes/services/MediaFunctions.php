<?php

declare(strict_types=1);

namespace App\Services;

use AVE;
use GdImage;
use Imagick;
use Exception;

class MediaFunctions {

	public AVE $ave;

	public array $vr_tags = ['_180', '_360', '_FISHEYE', '_FISHEYE190', '_RF52', '_MKX200', '_VRCA220'];

	const MEDIA_ORIENTATION_HORIZONTAL = 0;
	const MEDIA_ORIENTATION_VERTICAL = 1;
	const MEDIA_ORIENTATION_SQUARE = 2;

	public function __construct(AVE $ave){
		$this->ave = $ave;
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
				$image = new Imagick($path);
				$w = $image->getImageWidth();
				$h = $image->getImageHeight();
				$image->clear();
				return $w."x".$h;
			}
			catch(Exception $e){
				return $this->get_video_resolution($path);
			}
		}
		$w = imagesx($image);
		$h = imagesy($image);
		imagedestroy($image);
		return $w."x".$h;
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

	public function get_video_info(string $path): array {
		$this->ave->exec("ffprobe", "-v error -show_entries format -show_streams -of json \"$path\" 2>nul", $output);
		$info = json_decode(implode('', $output), true);
		return $info;
	}

	public function get_video_fps(string $path) : float {
		$this->ave->exec("ffprobe", "-v 0 -of csv=p=0 -select_streams v:0 -show_entries stream=r_frame_rate \"$path\" 2>nul", $output);
		eval('$fps = '.trim(preg_replace('/[^0-9.\/]+/', "", $output[0])).';');
		return $fps;
	}

	public function get_video_codec(string $path) : string {
		$this->ave->exec("ffprobe", "-v error -select_streams v:0 -show_entries stream=codec_name -of default=noprint_wrappers=1:nokey=1 \"$path\" 2>nul", $output);
		return trim($output[0]);
	}

	public function get_video_resolution(string $path) : string {
		$this->ave->exec("ffprobe", "-v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 \"$path\" 2>nul", $output);
		return rtrim($output[0] ?? '0x0', 'x');
	}

	public function get_video_color_primaries(string $path): string {
		$this->ave->exec("ffprobe","-v error -select_streams v:0 -show_entries stream=color_primaries -of default=noprint_wrappers=1:nokey=1 \"$path\" 2>nul", $output);
		return trim($output[0] ?? '');
	}

	public function get_video_duration(string $path) : string {
		$this->ave->exec("ffprobe", "-i \"$path\" -show_entries format=duration -v quiet -of csv=\"p=0\" -sexagesimal 2>nul", $output);
		$file_duration = trim($output[0]);
		$h = $m = $s = 0;
		sscanf($file_duration,"%d:%d:%d", $h, $m, $s);
		return sprintf("%02d:%02d:%02d", $h, $m, $s);
	}

	public function get_video_duration_seconds(string $path) : int {
		$this->ave->exec("ffprobe", "-i \"$path\" -show_entries format=duration -v quiet -of csv=\"p=0\" -sexagesimal 2>nul", $output);
		$file_duration = trim($output[0]);
		$h = $m = $s = 0;
		sscanf($file_duration,"%d:%d:%d", $h, $m, $s);
		return (intval($h) * 3600) + (intval($m) * 60) + intval($s);
	}

	public function get_video_languages(string $path) : array {
		$this->ave->exec("ffprobe", "-i \"$path\" -show_entries stream=index:stream_tags=language -select_streams a -of compact=p=0:nk=1 2> nul", $output);
		$data = [];
		foreach($output as $language){
			$parts = explode("|", $language);
			array_push($data, $parts[1] ?? $parts[0]);
		}
		return $data;
	}

	public function get_audio_channels(string $path) : int {
		$this->ave->exec("ffprobe", "-v error -select_streams a:0 -show_entries stream=channels -of default=noprint_wrappers=1:nokey=1 \"$path\" 2>nul", $output);
		return (int)trim($output[0] ?? '0');
	}

	public function get_extension_by_mime_type(string $path) : string|false {
		if(!file_exists($path)) return false;			
		switch(exif_imagetype($path)){
			case IMAGETYPE_GIF: return 'gif';
			case IMAGETYPE_JPEG: return 'jpg';
			case IMAGETYPE_PNG: return 'png';
			case IMAGETYPE_SWF: return 'swf';
			case IMAGETYPE_PSD: return 'psd';
			case IMAGETYPE_BMP: return 'bmp';
			case IMAGETYPE_TIFF_II: return 'tiff';
			case IMAGETYPE_TIFF_MM: return 'tiff';
			case IMAGETYPE_JPC: return 'jpc';
			case IMAGETYPE_JP2: return 'jp2';
			case IMAGETYPE_JPX: return 'jpx';
			case IMAGETYPE_JB2: return 'jb2';
			case IMAGETYPE_SWC: return 'swc';
			case IMAGETYPE_IFF: return 'iff';
			case IMAGETYPE_WBMP: return 'wbmp';
			case IMAGETYPE_XBM: return 'xbm';
			case IMAGETYPE_ICO: return 'ico';
			case IMAGETYPE_WEBP: return 'webp';
			case IMAGETYPE_AVIF: return 'avif';
			default: return false;
		}
	}

	public function sec_to_time(int $s) : string {
		$d = intval(floor($s / 86400));
		$s -= ($d * 86400);
		$h = intval(floor($s / 3600));
		$s -= ($h * 3600);
		$m = intval(floor($s / 60));
		$s -= ($m * 60);
		if($d > 0){
			return sprintf("%d:%02d:%02d:%02d", $d, $h, $m, $s);
		} else if($h > 0){
			return sprintf("%02d:%02d:%02d", $h, $m, $s);
		} else {
			return sprintf("%02d:%02d", $m, $s);
		}
	}

	public function get_video_thumbnail(string $path, string $output, int $w, int $r, int $c) : bool {
		if(!$this->ave->windows && !file_exists("/usr/bin/mtn")) return false;
		$input_file = $this->ave->get_file_path("$output/".pathinfo($path, PATHINFO_FILENAME)."_s.jpg");
		$output_file = $this->ave->get_file_path("$output/".pathinfo($path, PATHINFO_BASENAME).".webp");
		if(file_exists($output_file)) return true;
		if(!file_exists($input_file)){
			$this->ave->exec("mtn", "-w $w -r $r -c $c -P \"$path\" -O \"$output\" >nul 2>nul", $out);
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

	public function get_media_quality(int $width, int $height, bool $is_video = false) : string {
		$w = max($width, $height);
		$h = min($width, $height);
		if($is_video && $w / $h == 2) return strval($h);
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
		$image = new Imagick($path);
		if(!$image->valid()) return null;
		return $image->getImageColors();
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

}

?>
