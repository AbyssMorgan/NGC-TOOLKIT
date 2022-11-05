<?php

declare(strict_types=1);

namespace App\Extensions;

use Imagick;
use App\Dictionaries\MediaOrientation;

trait MediaFunctions {

	public function getImageFromPath(string $path){
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

	public function getImageResolution(string $path) : string {
		$image = $this->getImageFromPath($path);
		if(!$image){
			try {
				$image = new Imagick($path);
				$w = $image->getImageWidth();
				$h = $image->getImageHeight();
				$image->clear();
				return $w."x".$h;
			}
			catch(\Exception $e){
				return $this->getVideoResolution($path);
			}
		}
		$w = imagesx($image);
		$h = imagesy($image);
		imagedestroy($image);
		return $w."x".$h;
	}

	public function isGifAnimated(string $path) : bool {
		if(!($fh = @fopen($path, 'rb'))) return false;
		$count = 0;
		while(!feof($fh) && $count < 2){
			$chunk = fread($fh, 1024 * 100);
			$count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
		}
		fclose($fh);
		return $count > 1;
	}

	public function getVideoResolution(string $path) : string {
		exec("ffprobe -v error -select_streams v:0 -show_entries stream^=width^,height -of csv^=s^=x:p^=0 \"$path\" 2>nul", $output);
		return rtrim($output[0] ?? '0x0', 'x');
	}

	public function getVideoThumbnail(string $path) : bool {
		$folder = pathinfo($path, PATHINFO_DIRNAME);
		$w = $this->config->get('AVE_THUMBNAIL_WIDTH');
		$r = $this->config->get('AVE_THUMBNAIL_ROWS');
		$c = $this->config->get('AVE_THUMBNAIL_COLUMN');
		exec("mtn -w $w -r $r -c $c -P \"$path\" -O \"$folder\" >nul 2>nul", $output);
		return file_exists("$path"."_s.jpg");
	}

	public function getMediaOrientation(int $width, int $height){
		if($width > $height){
			return MediaOrientation::MEDIA_ORIENTATION_HORIZONTAL;
		} else if($height > $width){
			return MediaOrientation::MEDIA_ORIENTATION_VERTICAL;
		} else {
			return MediaOrientation::MEDIA_ORIENTATION_SQUARE;
		}
	}

	public function getMediaQuality(int $width, int $height) : string {
		$v = max($width, $height);
		if($v >= 30720){
			return '17280';
		} else if($v >= 15360){
			return '8640';
		} else if($v >= 7680){
			return '4320';
		} else if($v >= 3840){
			return '2160';
		} else if($v >= 2560){
			return '1440';
		} else if($v >= 1920){
			return '1080';
		} else if($v >= 1280){
			return '720';
		} else if($v >= 1024){
			return '540';
		} else if($v >= 850){
			return '480';
		} else if($v >= 640){
			return '360';
		} else if($v >= 320){
			return '240';
		} else {
			return '144';
		}
	}

	public function getImageColorCount(string $path) : int {
		$imagick = new Imagick($path);
		return $imagick->getImageColors();
	}

	public function getImageColorGroup(int $colors) : string {
		if($colors >= 100000){
			return 'Very-High';
		} else if($colors >= 30000){
			return 'High';
		} else if($colors >= 10000){
			return 'Medium';
		} else if($colors >= 1000){
			return 'Low';
		} else {
			return 'Very-Low';
		}
	}

	public function format_episode(int $episode, int $digits, int $max) : string {
		$episode = $episode % $max;
		if($episode < 0) $episode += $max;
		$ep = strval($episode);
		return str_repeat("0", $digits - strlen($ep)).$ep;
	}

}

?>
