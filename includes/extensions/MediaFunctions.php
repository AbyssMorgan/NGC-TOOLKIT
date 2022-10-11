<?php

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

	public function getImageResolution(string $path){
		$image = $this->getImageFromPath($path);
		if(!$image){
			$image = new Imagick($path);
			$w = $image->getImageWidth();
			$h = $image->getImageHeight();
			return $w."x".$h;
		}
		$w = imagesx($image);
		$h = imagesy($image);
		imagedestroy($image);
		return $w."x".$h;
	}

	public function getVideoResolution(string $path){
		exec("ffprobe -v error -select_streams v:0 -show_entries stream^=width^,height -of csv^=s^=x:p^=0 \"$path\"", $output);
		return $output[0] ?? '0x0';
	}

	public function getVideoThumbnail(string $path){
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

	public function getMediaQuality(int $width, int $height){
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

}

?>
