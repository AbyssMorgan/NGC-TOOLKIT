<?php

namespace App\Extensions;

trait VideoFunctions {

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

}

?>
