<?php

/**
 * NGC-TOOLKIT v2.9.0 – Component
 *
 * © 2026 Abyss Morgan
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
use ImagickKernel;
use NGC\Core\IniFile;
use NGC\Dictionaries\MediaDictionary;

/**
 * This class provides a set of functions for handling various media-related operations,
 * including MIME type detection, image manipulation, video analysis, and more.
 */
class MediaFunctions {

	use MediaDictionary;

	/**
	 * The core toolkit or script instance.
	 * @var Toolkit|Script
	 */
	private Toolkit|Script $core;

	/**
	 * Mime types data cache
	 * @var IniFile|null
	 */
	private ?IniFile $mime_types = null;

	/**
	 * File info data cache
	 * @var finfo|null
	 */
	private ?finfo $file_info = null;

	/**
	 * Constructor for MediaFunctions.
	 * Initializes the core toolkit/script and opens the fileinfo resource for MIME type detection.
	 * @param Toolkit|Script $core The core Toolkit or Script instance.
	 */
	public function __construct(Toolkit|Script $core){
		$this->core = $core;
		$this->file_info = \finfo_open(FILEINFO_MIME_TYPE, $this->core->get_resource("magic.text"));
	}

	/**
	 * Destructor for MediaFunctions.
	 * Closes the fileinfo resource.
	 */
	public function __destruct(){
		$this->file_info = null;
	}

	/**
	 * Retrieves the MIME type of a file from its path.
	 * @param string $path The path to the file.
	 * @return string|false The MIME type of the file, or false if the file does not exist or an error occurs.
	 */
	public function get_mime_type(string $path) : string|false {
		if(!\file_exists($path)) return false;
		try {
			$mime_type = @\finfo_file($this->file_info, $path);
		}
		catch(Exception $e){
			return false;
		}
		return \is_string($mime_type) ? \mb_strtolower($mime_type) : false;
	}

	/**
	 * Retrieves the MIME type from a string of content.
	 * @param string $content The string content.
	 * @return string|false The MIME type of the content, or false if an error occurs.
	 */
	public function get_string_mime_type(string $content) : string|false {
		try {
			$mime_type = @\finfo_buffer($this->file_info, $content);
		}
		catch(Exception $e){
			return false;
		}
		return \is_string($mime_type) ? \mb_strtolower($mime_type) : false;
	}

	/**
	 * Creates a GD image resource from a given file path.
	 * Supports various image formats. Animated images are not supported and will return null.
	 * @param string $path The path to the image file.
	 * @return GdImage|null A GdImage object on success, or null if the file does not exist, is not a supported image type, or is animated.
	 */
	public function get_image_from_path(string $path) : GdImage|null {
		if(!\file_exists($path)) return null;
		$mime = $this->get_mime_type($path);
		if($mime === false) return null;
		if($this->is_image_animated($path)) return null;
		$image = null;
		switch($mime){
			case 'image/bmp':{
				$image = @\imagecreatefrombmp($path);
				break;
			}
			case 'image/avif': {
				$image = @\imagecreatefromavif($path);
				break;
			}
			case 'image/gd2': {
				$image = @\imagecreatefromgd2($path);
				break;
			}
			case 'image/gd': {
				$image = @\imagecreatefromgd($path);
				break;
			}
			case 'image/gif': {
				$image = @\imagecreatefromgif($path);
				break;
			}
			case 'image/jpeg': {
				$image = @\imagecreatefromjpeg($path);
				break;
			}
			case 'image/png': {
				$image = @\imagecreatefrompng($path);
				break;
			}
			case 'image/x-tga': {
				$image = @\imagecreatefromtga($path);
				break;
			}
			case 'image/vnd.wap.wbmp': {
				$image = @\imagecreatefromwbmp($path);
				break;
			}
			case 'image/webp': {
				$image = @\imagecreatefromwebp($path);
				break;
			}
			case 'image/x-xbitmap': {
				$image = @\imagecreatefromxbm($path);
				break;
			}
			case 'image/x-xpixmap': {
				$image = @\imagecreatefromxpm($path);
				break;
			}
		}
		if($image === false) return null;
		return $image;
	}

	/**
	 * Retrieves the resolution of an image or video file.
	 * Attempts to use GD, then ImageMagick, and finally ffprobe if necessary.
	 * @param string $path The path to the image or video file.
	 * @return string The resolution in "WxH" format (e.g., "1920x1080"), or "0x0" if resolution cannot be determined.
	 */
	public function get_image_resolution(string $path) : string {
		$image = $this->get_image_from_path($path);
		if(!\is_null($image)){
			$w = \imagesx($image);
			$h = \imagesy($image);
			$image = null;
			return "{$w}x{$h}";
		}
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

	/**
	 * Checks if an image file is animated.
	 * Supports GIF, WebP, and APNG formats.
	 * @param string $path The path to the image file.
	 * @return bool True if the image is animated, false otherwise or if the file does not exist.
	 */
	public function is_image_animated(string $path) : bool {
		if(!\file_exists($path)) return false;
		$mime_type = $this->get_mime_type($path);
		if(\in_array($mime_type, $this->mime_types_video)) return true;
		switch($mime_type){
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
				if(!($fp = @\fopen($path, 'rb'))) return false;
				$header = \fread($fp, 1024);
				\fclose($fp);
				return \str_contains($header, 'ANMF');
			}
			case 'image/apng': {
				if(!($fp = @\fopen($path, 'rb'))) return false;
				$data = \fread($fp, 1024 * 100);
				\fclose($fp);
				return \str_contains($data, 'acTL');
			}
			default: return false;
		}
	}

	/**
	 * Retrieves the resolution of a media file using ffprobe.
	 * @param string $path The path to the media file.
	 * @return string The resolution in "WxH" format (e.g., "1920x1080"), or "0x0" if resolution cannot be determined.
	 */
	public function ffprobe_get_resolution(string $path) : string {
		$output = [];
		$this->core->exec("ffprobe", "-v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 \"$path\" 2>{$this->core->device_null}", $output);
		return \rtrim($output[0] ?? '0x0', 'x');
	}

	/**
	 * Retrieves the languages of audio streams in a video file using ffprobe.
	 * @param string $path The path to the video file.
	 * @return array<int, string> An array of language codes found in the audio streams.
	 */
	public function get_video_languages(string $path) : array {
		$output = [];
		$this->core->exec("ffprobe", "-i \"$path\" -show_entries stream=index:stream_tags=language -select_streams a -of compact=p=0:nk=1 2> {$this->core->device_null}", $output);
		$data = [];
		foreach($output as $language){
			$parts = \explode("|", $language);
			\array_push($data, $parts[1] ?? $parts[0]);
		}
		return $data;
	}

	/**
	 * Retrieves the file extension based on the MIME type of a file.
	 * @param string $path The path to the file.
	 * @return string|false The file extension (e.g., 'jpg', 'mp4'), or false if the file does not exist or MIME type cannot be determined.
	 */
	public function get_extension_by_mime_type(string $path) : string|false {
		if(!\file_exists($path)) return false;
		$mime_type = $this->get_mime_type($path);
		if($mime_type === false) return false;
		return $this->mime_type_to_extension($mime_type);
	}

	/**
	 * Generates a video thumbnail using 'mtn' (Motion Thumbnail) and converts it to WebP.
	 * Requires 'mtn' to be installed on non-Windows systems.
	 * @param string $path The path to the video file.
	 * @param string $output The output directory for the thumbnail.
	 * @param int $w The width of each thumbnail in the grid.
	 * @param int $r The number of rows in the thumbnail grid.
	 * @param int $c The number of columns in the thumbnail grid.
	 * @return bool True on success, false on failure (e.g., mtn not found, input file not found, output file not created).
	 */
	public function get_video_thumbnail(string $path, string $output, int $w, int $r, int $c) : bool {
		$out = [];
		if($this->core->get_system_type() != SYSTEM_TYPE_WINDOWS && !\file_exists("/usr/bin/mtn")) return false;
		$input_file = $this->core->get_path("$output/".\pathinfo($path, PATHINFO_FILENAME)."_s.jpg");
		$output_file = $this->core->get_path("$output/".\pathinfo($path, PATHINFO_FILENAME).".webp");
		if(\file_exists($output_file)) return true;
		if(!\file_exists($input_file)){
			$this->core->exec("mtn", "-w $w -r $r -c $c -P \"$path\" -O \"$output\" >{$this->core->device_null}"." 2>{$this->core->device_null}", $out);
			if(!\file_exists($input_file)) return false;
		}
		$image = new Imagick();
		$image->readImage($input_file);
		$image->writeImage($output_file);
		$image->clear();
		\unlink($input_file);
		return \file_exists($output_file);
	}

	/**
	 * Determines the orientation of media based on its width and height.
	 * @param int $width The width of the media.
	 * @param int $height The height of the media.
	 * @return int One of the MEDIA_ORIENTATION constants (HORIZONTAL, VERTICAL, SQUARE).
	 */
	public function get_media_orientation(int $width, int $height) : int {
		if($width > $height){
			return self::MEDIA_ORIENTATION_HORIZONTAL;
		} elseif($height > $width){
			return self::MEDIA_ORIENTATION_VERTICAL;
		} else {
			return self::MEDIA_ORIENTATION_SQUARE;
		}
	}

	/**
	 * Returns the human-readable name for a given media orientation.
	 * @param int $orientation The media orientation constant.
	 * @return string The name of the orientation (e.g., 'Horizontal', 'Vertical', 'Square'), or 'Unknown'.
	 */
	public function get_media_orientation_name(int $orientation) : string {
		return $this->media_orientation_name[$orientation] ?? 'Unknown';
	}

	/**
	 * Determines the quality (height-based resolution) of a media file.
	 * For VR media, it returns the minimum of width and height.
	 * @param int $width The width of the media.
	 * @param int $height The height of the media.
	 * @param bool $is_vr Optional. True if the media is VR, false otherwise. Defaults to false.
	 * @return int The quality value (e.g., 1080 for FullHD, 2160 for 4K).
	 */
	public function get_media_quality(int $width, int $height, bool $is_vr = false) : int {
		$w = \max($width, $height);
		$h = \min($width, $height);
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

	/**
	 * Retrieves the number of colors in an image.
	 * @param string $path The path to the image file.
	 * @return int|null The number of colors, -1 if the file does not exist, or null if the image is invalid.
	 */
	public function get_image_color_count(string $path) : ?int {
		if(!\file_exists($path)) return -1;
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

	/**
	 * Categorizes the number of colors into predefined groups.
	 * @param int $colors The number of colors in an image.
	 * @return string A string representing the color group (e.g., '000000 - 001000').
	 */
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

	/**
	 * Formats an episode number with leading zeros based on desired digits and a maximum value.
	 * The episode number will loop around if it exceeds the maximum.
	 * @param int $episode The current episode number.
	 * @param int $digits The desired number of digits for the formatted episode.
	 * @param int $max The maximum value for the episode number before it loops.
	 * @return string The formatted episode number.
	 */
	public function format_episode(int $episode, int $digits, int $max) : string {
		$episode = $episode % $max;
		if($episode < 0) $episode += $max;
		$ep = \strval($episode);
		return \str_repeat("0", $digits - \strlen($ep)).$ep;
	}

	/**
	 * Checks if a video file is a VR video based on its filename.
	 * It iterates through predefined VR tags to identify VR content.
	 * @param string $path The path to the video file.
	 * @return bool True if the video is identified as VR, false otherwise.
	 */
	public function is_vr_video(string $path) : bool {
		$name = \mb_strtoupper(\pathinfo($path, PATHINFO_FILENAME));
		foreach($this->vr_tags as $tag){
			if(\str_ends_with($name, $tag)) return true;
		}
		return false;
	}

	/**
	 * Checks if a video file is an AR (Augmented Reality) video based on its filename.
	 * It checks for VR tags appended with '_ALPHA'.
	 * @param string $path The path to the video file.
	 * @return bool True if the video is identified as AR, false otherwise.
	 */
	public function is_ar_video(string $path) : bool {
		$name = \mb_strtoupper(\pathinfo($path, PATHINFO_FILENAME));
		foreach($this->vr_tags as $tag){
			if(\str_ends_with("$name", "{$tag}_ALPHA")) return true;
		}
		return false;
	}

	/**
	 * Retrieves the VR quality string (e.g., '4K', '8K') based on the width of the video.
	 * @param int $width The width of the VR video.
	 * @return string|null The VR quality string, or null if no matching quality is found.
	 */
	public function get_vr_quality_string(int $width) : ?string {
		foreach($this->vr_quality_map as $min_width => $label){
			if($width >= $min_width){
				return $label;
			}
		}
		return null;
	}

	/**
	 * Determines the VR screen type and stereo mode based on the video filename.
	 * @param string $name The filename of the video.
	 * @return array{screen_type: string, stereo_mode: string, alpha: bool} An associative array containing 'screen_type', 'stereo_mode', and 'alpha' (boolean for AR).
	 */
	public function get_vr_mode(string $name) : array {
		if(\str_contains($name, '_LR_180')){
			$screen_type = "dome";
			$stereo_mode = "sbs";
		} elseif(\str_contains($name, '_FISHEYE190')){
			$screen_type = "fisheye";
			$stereo_mode = "sbs";
		} elseif(\str_contains($name, '_MKX200')){
			$screen_type = "mkx200";
			$stereo_mode = "sbs";
		} elseif(\str_contains($name, '_MKX220') || \str_contains($name, '_VRCA220')){
			$screen_type = "mkx220";
			$stereo_mode = "sbs";
		} elseif(\str_contains($name, '_TB_180')){
			$screen_type = "180";
			$stereo_mode = "tb";
		} elseif(\str_contains($name, '_360')){
			$screen_type = "360";
			$stereo_mode = (\str_contains($name, '_SBS_')) ? "sbs" : "off";
		} else {
			$screen_type = "flat";
			$stereo_mode = "off";
		}
		return [
			'screen_type' => $screen_type,
			'stereo_mode' => $stereo_mode,
			'alpha' => (\str_contains($name, '_ALPHA')),
		];
	}

	/**
	 * Returns a human-readable string for a VR screen type.
	 * @param string $screen_type The VR screen type (e.g., 'dome', '360').
	 * @return string The human-readable screen type (e.g., 'Dome 180°', '360°'), or 'Unknown'.
	 */
	public function vr_screen_type(string $screen_type) : string {
		return $this->vr_screen_types[$screen_type] ?? "Unknown";
	}

	/**
	 * Returns a human-readable string for a VR stereo mode.
	 * @param string $stereo_mode The VR stereo mode (e.g., 'sbs', 'tb', 'off').
	 * @return string The human-readable stereo mode (e.g., 'Side-by-Side (SBS)', 'Top-Bottom (TB)'), or 'Unknown'.
	 */
	public function vr_stereo_mode(string $stereo_mode) : string {
		return $this->vr_stereo_modes[$stereo_mode] ?? "Unknown";
	}

	/**
	 * Calculates the aspect ratio of media given its width and height.
	 * @param int $width The width of the media.
	 * @param int $height The height of the media.
	 * @return string The aspect ratio in "width:height" format (e.g., "16:9", "4:3").
	 */
	public function calculate_aspect_ratio(int $width, int $height) : string {
		$gcd = function(int $a, int $b) use (&$gcd) : int {
			return ($b == 0) ? $a : $gcd($b, $a % $b);
		};
		$divisor = $gcd($width, $height);
		$aspect_ratio_width = $width / $divisor;
		$aspect_ratio_height = $height / $divisor;
		return "$aspect_ratio_width:$aspect_ratio_height";
	}

	/**
	 * Returns a human-readable string for the number of audio channels.
	 * @param int $channels The number of audio channels.
	 * @return string The human-readable channel string (e.g., 'Stereo', '5.1', '7.1', 'Mono', 'None', 'Unknown', or 'Xch (Multi)').
	 */
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

	/**
	 * Retrieves detailed media information using ffprobe and returns it as a decoded JSON array.
	 * @param string $path The path to the media file.
	 * @return array<string, mixed> An associative array containing media format and stream information.
	 */
	public function get_media_info(string $path) : array {
		$output = [];
		$this->core->exec("ffprobe", "-v error -show_entries format -show_streams -of json \"$path\" 2>{$this->core->device_null}", $output);
		$info = \json_decode(\implode('', $output), true);
		return $info;
	}

	/**
	 * Retrieves simplified media information for a given file path.
	 * @param string $path The path to the media file.
	 * @return object|false An object containing simplified media metadata, or false if the file does not exist.
	 * @property string|null $video_resolution Resolution of the video (e.g., "1920x1080").
	 * @property int $video_quality Quality of the video (e.g., 1080 for FullHD).
	 * @property string $video_duration Human-readable duration of the video.
	 * @property int $video_duration_seconds Duration of the video in seconds.
	 * @property float|null $video_fps Frames per second of the video.
	 * @property int $video_bitrate Bitrate of the video.
	 * @property string|null $video_codec Codec name of the video stream.
	 * @property string|null $video_aspect_ratio Aspect ratio of the video (e.g., "16:9").
	 * @property string|null $video_orientation Orientation of the video ('Horizontal', 'Vertical', 'Square').
	 * @property string $audio_codec Codec name of the audio stream.
	 * @property int $audio_bitrate Bitrate of the audio.
	 * @property int $audio_channels Number of audio channels.
	 * @property int $file_size Size of the file in bytes.
	 * @property string $file_size_human Human-readable file size.
	 * @property string $file_creation_time Creation time of the file (Y-m-d H:i:s).
	 * @property string $file_modification_time Modification time of the file (Y-m-d H:i:s).
	 */
	public function get_media_info_simple(string $path) : object|false {
		if(!\file_exists($path)) return false;
		$media_info = $this->get_media_info($path);
		$video_duration_seconds = \intval(\round(\floatval($media_info['format']['duration'])));
		$file_size = \filesize($path);
		$meta = [
			'video_resolution' => null,
			'video_quality' => 0,
			'video_duration' => $this->core->seconds_to_time($video_duration_seconds, true),
			'video_duration_seconds' => $video_duration_seconds,
			'video_fps' => null,
			'video_bitrate' => \intval($media_info['format']['bit_rate'] ?? 0),
			'video_codec' => null,
			'video_aspect_ratio' => null,
			'video_orientation' => null,
			'audio_codec' => 'none',
			'audio_bitrate' => 0,
			'audio_channels' => 0,
			'file_size' => $file_size,
			'file_size_human' => $this->core->format_bytes($file_size, 2, false),
			'file_creation_time' => \date("Y-m-d H:i:s", \filectime($path)),
			'file_modification_time' => \date("Y-m-d H:i:s", \filemtime($path)),
		];
		$need_audio = true;
		foreach($media_info['streams'] as $stream){
			switch($stream['codec_type']){
				case 'video': {
					if(($stream['disposition']['attached_pic'] ?? 0) == 1) continue 2;
					$width = \intval($stream['width']);
					$height = \intval($stream['height']);
					$meta['video_resolution'] = "{$stream['width']}x{$stream['height']}";
					$is_vr = $this->is_vr_video($path);
					$is_ar = $this->is_ar_video($path);
					$meta['video_quality'] = $this->get_media_quality($width, $height, $is_vr || $is_ar);
					$orientation = $this->get_media_orientation($width, $height);
					$meta['video_orientation'] = $this->get_media_orientation_name($orientation);
					eval('$fps = '.\trim(\preg_replace('/[^0-9.\/]+/', "", $stream['r_frame_rate'])).';');
					$meta['video_fps'] = \round($fps, 4);
					$meta['video_codec'] = $stream['codec_name'] ?? null;
					$meta['video_aspect_ratio'] = $this->calculate_aspect_ratio($stream['width'], $stream['height']);
					break;
				}
				case 'audio': {
					if(!$need_audio) continue 2;
					$need_audio = false;
					$meta['audio_codec'] = $stream['codec_name'] ?? null;
					$meta['audio_bitrate'] = $stream['bit_rate'] ?? ($stream['tags']['BPS'] ?? null);
					if(!\is_null($meta['audio_bitrate'])) $meta['audio_bitrate'] = \intval($meta['audio_bitrate']);
					$meta['audio_channels'] = $stream['channels'];
					break;
				}
			}
		}
		return (object)$meta;
	}

	/**
	 * Converts a MIME type string to its corresponding file extension.
	 * Uses an INI file resource for the mapping.
	 * @param string $mime_type The MIME type string (e.g., 'image/jpeg').
	 * @return string|false The file extension (e.g., 'jpg'), or false if the MIME type is 'application/octet-stream' or no mapping is found.
	 */
	public function mime_type_to_extension(string $mime_type) : string|false {
		if($mime_type == 'application/octet-stream') return false;
		if(\is_null($this->mime_types)){
			$this->mime_types = new IniFile($this->core->get_resource("MimeTypes.ini"));
		}
		return $this->mime_types->get($mime_type, false);
	}

	/**
	 * Retrieves the common file extension for a given subtitle codec name.
	 * @param string $codec_name The subtitle codec name (e.g., 'subrip', 'ass').
	 * @return string|null The file extension (e.g., 'srt', 'ass'), or null if not found.
	 */
	public function get_subtitle_extension(string $codec_name) : ?string {
		$codec_name = \strtolower($codec_name);
		return $this->codec_extensions_subtitle[$codec_name] ?? null;
	}

	/**
	 * Retrieves the common file extension for a given audio codec name.
	 * @param string $codec_name The audio codec name (e.g., 'aac', 'mp3').
	 * @return string|null The file extension (e.g., 'aac', 'mp3'), or null if not found.
	 */
	public function get_audio_extension(string $codec_name) : ?string {
		$codec_name = \strtolower($codec_name);
		return $this->codec_extensions_audio[$codec_name] ?? null;
	}

	/**
	 * Retrieves the common file extension for a given video codec name.
	 * @param string $codec_name The video codec name (e.g., 'h264', 'hevc').
	 * @return string|null The file extension (e.g., 'h264', 'h265'), or null if not found.
	 */
	public function get_video_extension(string $codec_name) : ?string {
		$codec_name = \strtolower($codec_name);
		return $this->codec_extensions_video[$codec_name] ?? null;
	}

	/**
	 * Converts a timecode (hours, minutes, seconds, milliseconds) into total seconds.
	 * @param int $h Hours.
	 * @param int $m Minutes.
	 * @param int $s Seconds.
	 * @param int $ms Milliseconds.
	 * @return float The total duration in seconds.
	 */
	public function timecode_to_seconds(int $h, int $m, int $s, int $ms) : float {
		return $h * 3600 + $m * 60 + $s + $ms / 1000;
	}

	/**
	 * Scans a directory for files matching specified MIME types and name filters.
	 * @param string $path The directory path to scan.
	 * @param array<string>|null $include_mime_types Optional. An array of MIME types to include. If null, all MIME types are included.
	 * @param array<string>|null $exclude_mime_types Optional. An array of MIME types to exclude. If null, no MIME types are excluded.
	 * @param array<string>|null $name_filters Optional. An array of name filters (wildcards allowed, e.g., '*.jpg').
	 * @param bool $case_sensitive Optional. True for case-sensitive name filtering, false otherwise. Defaults to false.
	 * @param bool $recursive Optional. True to scan directories recursively, false for shallow scan. Defaults to true.
	 * @return array<int, string> An array of full paths to the matching files, sorted alphabetically.
	 * @deprecated Due to performance use process_files_mime_type
	 */
	public function get_files_mime_type(string $path, ?array $include_mime_types = null, ?array $exclude_mime_types = null, ?array $name_filters = null, bool $case_sensitive = false, bool $recursive = true) : array {
		if(!\file_exists($path)) return [];
		if(!$case_sensitive && !\is_null($name_filters)){
			$name_filters = $this->core->array_to_lower($name_filters);
		}
		$data = [];
		$this->scan_dir_safe_mime_type($path, $data, $include_mime_types, $exclude_mime_types, $name_filters, $case_sensitive, $recursive);
		\asort($data, SORT_STRING);
		return $data;
	}

	/**
	 * Do operations on a list of files from a given path, matching specified MIME types and name filters
	 *
	 * @param string|array $path The directory/direcories path to scan.
	 * @param callable $callback Callback called for every found files function(string $file, string $mime_type)
	 * @param array<string>|null $include_mime_types Optional. An array of MIME types to include. If null, all MIME types are included.
	 * @param array<string>|null $exclude_mime_types Optional. An array of MIME types to exclude. If null, no MIME types are excluded.
	 * @param ?array $name_filters An array of strings to filter file names by (case-sensitive or insensitive). Null for no name filter.
	 * @param bool $case_sensitive Whether name filtering should be case-sensitive.
	 * @param bool $recursive Whether to scan subdirectories recursively.
	 * @return int Count total processed files.
	 */
	public function process_files_mime_type(string|array $path, callable $callback, ?array $include_mime_types = null, ?array $exclude_mime_types = null, ?array $name_filters = null, bool $case_sensitive = false, bool $recursive = true) : int {
		if(\gettype($path) == 'string'){
			$paths = [$path];
		} else {
			$paths = $path;
		}
		if(!$case_sensitive && !\is_null($name_filters)){
			$name_filters = $this->core->array_to_lower($name_filters);
		}
		$counter = 0;
		foreach($paths as $path){
			if(!\file_exists($path)) continue;
			$this->scan_dir_safe_extension_process_files($path, $callback, $counter, $include_mime_types, $exclude_mime_types, $name_filters, $case_sensitive, $recursive);
		}
		return $counter;
	}

	/**
	 * Recursively scans a directory for files, applying MIME type and name filters.
	 * This is a private helper method for get_files_mime_type.
	 * @param string $dir The current directory to scan.
	 * @param array<string> &$data The array to populate with matching file paths.
	 * @param array<string>|null $include_mime_types MIME types to include.
	 * @param array<string>|null $exclude_mime_types MIME types to exclude.
	 * @param array<string>|null $name_filters Name filters to apply.
	 * @param bool $case_sensitive True for case-sensitive name filtering.
	 * @param bool $recursive True to scan recursively.
	 * @return bool True if an action was successfully performed, false otherwise.
	 */
	public function scan_dir_safe_mime_type(string $dir, array &$data, ?array $include_mime_types, ?array $exclude_mime_types, ?array $name_filters, bool $case_sensitive, bool $recursive) : bool {
		try {
			$items = @\scandir($dir);
		}
		catch(Exception $e){
			return false;
		}
		if($items === false) return false;
		foreach($items as $item){
			if($item === '.' || $item === '..') continue;
			$full_path = $dir.DIRECTORY_SEPARATOR.$item;
			if(\is_dir($full_path)){
				if(!$recursive) continue;
				$this->scan_dir_safe_mime_type($full_path, $data, $include_mime_types, $exclude_mime_types, $name_filters, $case_sensitive, $recursive);
				continue;
			}
			$mime_type = $this->get_mime_type($full_path);
			if($mime_type === false) continue;
			if(!\is_null($include_mime_types) && !\in_array($mime_type, $include_mime_types)) continue;
			if(!\is_null($exclude_mime_types) && \in_array($mime_type, $exclude_mime_types)) continue;
			$basename = \pathinfo($full_path, PATHINFO_BASENAME);
			if(!\is_null($name_filters)){
				$check_name = $case_sensitive ? $basename : \mb_strtolower($basename);
				if(!$this->core->filter($check_name, $name_filters)) continue;
			}
			$data[] = $full_path;
		}
		return true;
	}

	/**
	 * Recursively scans a directory for files, applying MIME type and name filters.
	 *
	 * @param string $dir The directory to scan.
	 * @param callable $callback Callback called for every found files function(string $file, string $mime_type)
	 * @param array<string>|null $include_mime_types MIME types to include.
	 * @param array<string>|null $exclude_mime_types MIME types to exclude.
	 * @param ?array $name_filters An array of strings to filter file names by.
	 * @param bool $case_sensitive Whether name filtering should be case-sensitive.
	 * @param bool $recursive Whether to scan subdirectories recursively.
	 * @return bool True if an action was successfully performed, false otherwise.
	 */
	public function scan_dir_safe_extension_process_files(string $dir, callable $callback, int &$counter, ?array $include_mime_types, ?array $exclude_mime_types, ?array $name_filters, bool $case_sensitive, bool $recursive) : bool {
		try {
			$items = @\scandir($dir);
		}
		catch(Exception $e){
			return false;
		}
		if($items === false) return false;
		foreach($items as $item){
			if($item === '.' || $item === '..') continue;
			$full_path = $dir.DIRECTORY_SEPARATOR.$item;
			if(\is_dir($full_path)){
				if(!$recursive) continue;
				$this->scan_dir_safe_extension_process_files($full_path, $callback, $counter, $include_mime_types, $exclude_mime_types, $name_filters, $case_sensitive, $recursive);
				continue;
			}
			$mime_type = $this->get_mime_type($full_path);
			if($mime_type === false) continue;
			if(!\is_null($include_mime_types) && !\in_array($mime_type, $include_mime_types)) continue;
			if(!\is_null($exclude_mime_types) && \in_array($mime_type, $exclude_mime_types)) continue;
			$basename = \pathinfo($full_path, PATHINFO_BASENAME);
			if(!\is_null($name_filters)){
				$check_name = $case_sensitive ? $basename : \mb_strtolower($basename);
				if(!$this->core->filter($check_name, $name_filters)) continue;
			}
			$counter++;
			$callback($full_path, $mime_type);
		}
		return true;
	}

	public function is_image_blurry(string $path, float $threshold = 10.0, int $max_size = 600) : bool {
		if(!\file_exists($path)) return false;
		try {
			$img = new Imagick($path);
		}
		catch(Exception $e){
			return false;
		}
		$geometry = $img->getImageGeometry();
		$w = $geometry['width'];
		$h = $geometry['height'];
		if(\max($w, $h) > $max_size){
			if($w >= $h){
				$img->resizeImage($max_size, 0, Imagick::FILTER_LANCZOS, 1);
			} else {
				$img->resizeImage(0, $max_size, Imagick::FILTER_LANCZOS, 1);
			}
		}

		$img->setImageColorspace(Imagick::COLORSPACE_GRAY);
		$img->setImageType(Imagick::IMGTYPE_GRAYSCALE);

		$laplaceMatrix = [
			[0, -1, 0],
			[-1, 4, -1],
			[0, -1, 0],
		];
		$kernel = ImagickKernel::fromMatrix($laplaceMatrix);
		$img->convolveImage($kernel);

		$it = $img->getPixelIterator();
		$sum = 0.0;
		$sum_sq = 0.0;
		$count = 0;

		foreach($it as $row){
			foreach($row as $pixel){
				$color = $pixel->getColor();
				$val = ($color['r'] + $color['g'] + $color['b']) / 3.0;
				$sum += $val;
				$sum_sq += $val * $val;
				$count++;
			}
			$it->syncIterator();
		}

		if($count === 0) return true;

		$mean = $sum / $count;
		$variance = ($sum_sq / $count) - ($mean * $mean);

		$img->destroy();

		return $variance < $threshold;
	}

}

?>