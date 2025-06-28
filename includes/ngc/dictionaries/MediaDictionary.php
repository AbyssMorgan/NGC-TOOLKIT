<?php

/**
 * NGC-TOOLKIT v2.7.0 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Dictionaries;

/**
 * Trait MediaDictionary
 *
 * This trait provides a collection of constants and arrays related to media types,
 * orientations, extensions, MIME types, VR tags, and codec extensions.
 */
trait MediaDictionary {

	/**
	 * Media orientation constant for horizontal media.
	 * @const int
	 */
	public const MEDIA_ORIENTATION_HORIZONTAL = 0;

	/**
	 * Media orientation constant for vertical media.
	 * @const int
	 */
	public const MEDIA_ORIENTATION_VERTICAL = 1;

	/**
	 * Media orientation constant for square media.
	 * @const int
	 */
	public const MEDIA_ORIENTATION_SQUARE = 2;

	/**
	 * An associative array mapping media orientation constants to their human-readable names.
	 * @var array<int, string>
	 */
	public array $media_orientation_name = [
		self::MEDIA_ORIENTATION_HORIZONTAL => 'Horizontal',
		self::MEDIA_ORIENTATION_VERTICAL => 'Vertical',
		self::MEDIA_ORIENTATION_SQUARE => 'Square',
	];

	/**
	 * An array of Virtual Reality (VR) related tags.
	 * @var array<int, string>
	 */
	public array $vr_tags = [
		'_LR_180',
		'_FISHEYE190',
		'_MKX200',
		'_MKX220',
		'_VRCA220',
		'_TB_180',
		'_360'
	];

	/**
	 * An array of common image file extensions.
	 * @var array<int, string>
	 */
	public array $extensions_images = [
		'avif',
		'bmp',
		'dpx',
		'emf',
		'exr',
		'gif',
		'hdr',
		'heic',
		'heif',
		'ico',
		'jfif',
		'jpe',
		'jpeg',
		'jpg',
		'miff',
		'pam',
		'pbm',
		'pcx',
		'pdf',
		'pgm',
		'png',
		'ppm',
		'psd',
		'ras',
		'sgi',
		'svg',
		'tga',
		'tif',
		'tiff',
		'webp',
		'wmf',
		'xpm',
		'xwd'
	];

	/**
	 * An array of common video file extensions.
	 * @var array<int, string>
	 */
	public array $extensions_video = [
		'264',
		'265',
		'3g2',
		'3gp',
		'amv',
		'asf',
		'av1',
		'avc',
		'avi',
		'divx',
		'drc',
		'dv',
		'evo',
		'evob',
		'f4v',
		'flv',
		'h264',
		'h265',
		'hevc',
		'ivf',
		'm1v',
		'm2ts',
		'm2v',
		'm4v',
		'mkv',
		'mov',
		'mp4',
		'mpeg',
		'mpg',
		'mpv',
		'mts',
		'mxf',
		'nsv',
		'nut',
		'obu',
		'ogv',
		'prores',
		'rm',
		'rmvb',
		'roq',
		'rv',
		'ts',
		'vc1',
		'vob',
		'webm',
		'webmv',
		'wmv',
		'x264',
		'x265',
		'yuv'
	];

	/**
	 * An array of common audio file extensions.
	 * @var array<int, string>
	 */
	public array $extensions_audio = [
		'aac',
		'ac3',
		'amr',
		'ape',
		'caf',
		'dts',
		'dtshd',
		'dtsma',
		'eac3',
		'eb3',
		'ec3',
		'flac',
		'm4a',
		'mlp',
		'mp2',
		'mp3',
		'ogg',
		'opus',
		'ra',
		'thd',
		'truehd',
		'tta',
		'vqf',
		'wav',
		'weba',
		'wma',
		'wv'
	];

	/**
	 * An array of common subtitle file extensions.
	 * @var array<int, string>
	 */
	public array $extensions_subtitle = [
		'ass',
		'idx',
		'jss',
		'mpl2',
		'rt',
		'smi',
		'srt',
		'ssa',
		'sub',
		'sup',
		'textst',
		'txt',
		'usf',
		'vtt',
		'webvtt'
	];
	
	/**
	 * An array of common media container file extensions.
	 * These extensions can contain video, audio, and/or subtitle streams.
	 * @var array<int, string>
	 */
	public array $extensions_media_container = [
		'3g2',
		'3gp',
		'amv',
		'asf',
		'avi',
		'drc',
		'f4v',
		'flv',
		'm2ts',
		'm4v',
		'mk3d',
		'mka',
		'mks',
		'mkv',
		'mov',
		'mp4',
		'mpeg',
		'mpg',
		'mxf',
		'nsv',
		'nut',
		'ogm',
		'ogv',
		'rm',
		'rmvb',
		'ts',
		'vob',
		'webm',
		'webma',
		'webmv',
		'wmv',
	];

	/**
	 * An array of common image MIME types.
	 * @var array<int, string>
	 */
	public array $mime_types_images = [
		'image/bmp',
		'image/gif',
		'image/jpeg',
		'image/png',
		'image/tiff',
		'image/webp',
		'image/x-dpx',
		'image/x-exr',
		'image/x-pam',
		'image/x-pcx',
		'image/x-portable-bitmap',
		'image/x-portable-graymap',
		'image/x-portable-pixmap',
		'image/x-rgb',
		'image/x-tga',
		'image/x-xbitmap',
		'image/x-xwindowdump',
	];

	/**
	 * An array of common video MIME types.
	 * @var array<int, string>
	 */
	public array $mime_types_video = [
		'video/3gpp',
		'video/3gpp2',
		'video/av1',
		'video/h265',
		'video/mp4',
		'video/mpeg',
		'video/ogg',
		'video/quicktime',
		'video/vnd.dlna.mpeg-tts',
		'video/webm',
		'video/x-amv',
		'video/x-dv',
		'video/x-evo',
		'video/x-f4v',
		'video/x-flv',
		'video/x-h264',
		'video/x-ivf',
		'video/x-m4v',
		'video/x-matroska',
		'video/x-mjpeg',
		'video/x-ms-asf',
		'video/x-ms-vob',
		'video/x-ms-wmv',
		'video/x-msvideo',
		'video/x-nsv',
		'video/x-obu',
		'video/x-prores',
		'video/x-roq',
		'video/x-rv',
		'video/x-vc1',
		'video/x-webmv',
		'video/x-yuv',
	];

	/**
	 * An array of common audio MIME types.
	 * @var array<int, string>
	 */
	public array $mime_types_audio = [
		'audio/aac',
		'audio/ac3',
		'audio/amr',
		'audio/ape',
		'audio/eac3',
		'audio/flac',
		'audio/mp4',
		'audio/mpeg',
		'audio/ogg',
		'audio/opus',
		'audio/true-hd',
		'audio/vnd.dts',
		'audio/vnd.dts.hd',
		'audio/wav',
		'audio/webm',
		'audio/x-caf',
		'audio/x-dtsma',
		'audio/x-ms-wma',
		'audio/x-pn-realaudio',
		'audio/x-tta',
		'audio/x-vqf',
		'audio/x-wavpack',
	];

	/**
	 * An array of common subtitle MIME types.
	 * @var array<int, string>
	 */
	public array $mime_types_subtitle = [
		'application/ttml+xml',
		'application/x-idx',
		'application/x-jss',
		'application/x-mpl2',
		'application/x-realtext',
		'application/x-sami',
		'application/x-subrip',
		'application/x-usf',
		'application/x-webvtt',
		'image/vnd.dvb.subtitle',
		'text/plain',
		'text/srt',
		'text/vtt',
		'text/x-ass',
		'text/x-ssa',
	];

	/**
	 * An array of common media container MIME types.
	 * @var array<int, string>
	 */
	public array $mime_types_media_container = [
		'audio/webm',
		'audio/x-webma',
		'video/3gpp',
		'video/3gpp2',
		'video/mp4',
		'video/mpeg',
		'video/ogg',
		'video/quicktime',
		'video/vnd.dlna.mpeg-tts',
		'video/webm',
		'video/x-amv',
		'video/x-drc',
		'video/x-f4v',
		'video/x-flv',
		'video/x-matroska',
		'video/x-mk3d',
		'video/x-ms-asf',
		'video/x-ms-vob',
		'video/x-ms-wmv',
		'video/x-msvideo',
		'video/x-mxf',
		'video/x-nsv',
		'video/x-nut',
		'video/x-ogm',
		'video/x-rmvb',
	];

	/**
	 * An associative array mapping video codec names to their common file extensions.
	 * @var array<string, string>
	 */
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

	/**
	 * An associative array mapping audio codec names to their common file extensions.
	 * @var array<string, string>
	 */
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

	/**
	 * An associative array mapping subtitle codec names to their common file extensions.
	 * @var array<string, string>
	 */
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

	/**
	 * An associative array mapping VR quality resolutions (width) to their common names.
	 * @var array<int, string>
	 */
	public array $vr_quality_map = [
		7680 => '8K',
		5760 => '6K',
		3840 => '4K',
		1920 => '2K',
		1600 => 'FullHD',
	];

}