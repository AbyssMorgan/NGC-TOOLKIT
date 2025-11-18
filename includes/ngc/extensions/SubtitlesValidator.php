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

namespace NGC\Extensions;

use Script;
use Toolkit;

/**
 * SubtitlesValidator class for validating and comparing SRT subtitle files.
 */
class SubtitlesValidator {

	/**
	 * The core toolkit or script instance.
	 * @var Toolkit|Script
	 */
	private Toolkit|Script $core;

	/**
	 * Constructor for the SubtitlesValidator.
	 *
	 * @param Toolkit|Script $core The core toolkit or script instance.
	 */
	public function __construct(Toolkit|Script $core){
		$this->core = $core;
	}

	/**
	 * Validates an SRT subtitle file for common errors.
	 *
	 * Checks for:
	 * - File existence.
	 * - Valid timecode format (HH:MM:SS,ms).
	 * - Timecode values within valid ranges (hours >= 0, minutes 0-59, seconds 0-59, milliseconds 0-999).
	 * - Overlapping subtitles.
	 * - Out-of-order subtitle timings.
	 *
	 * @param string $path The path to the SRT file.
	 * @return array|false An array of error messages, or false if the file does not exist.
	 */
	public function srt_validate(string $path) : array|false {
		if(!\file_exists($path)) return false;
		$file_content = \file($path, FILE_IGNORE_NEW_LINES);
		$timestamps = [];
		$errors = [];
		foreach($file_content as $line_index => $line){
			if(\str_contains($line, '-->')){
				$line_number = $line_index + 1;
				$pattern = '/^(\d{2}):(\d{2}):(\d{2}),(\d{3})\s+-->\s+(\d{2}):(\d{2}):(\d{2}),(\d{3})$/';
				if(!\preg_match($pattern, $line, $m)){
					$errors[] = "Line $line_number: invalid timecode format: \"$line\"";
					continue;
				}
				[$_, $h1, $mi1, $s1, $ms1, $h2, $mi2, $s2, $ms2] = $m;
				$parts = [
					['h' => $h1, 'mi' => $mi1, 's' => $s1, 'ms' => $ms1, 'pos' => 'start', 'ln' => $line_number],
					['h' => $h2, 'mi' => $mi2, 's' => $s2, 'ms' => $ms2, 'pos' => 'end', 'ln' => $line_number],
				];
				foreach($parts as $p){
					if((int)$p['h'] < 0){
						$errors[] = "Line {$p['ln']}: hours < 0 in {$p['pos']} timecode";
					}
					if((int)$p['mi'] < 0 || (int)$p['mi'] > 59){
						$errors[] = "Line {$p['ln']}: minutes out of range (0-59) in {$p['pos']} timecode";
					}
					if((int)$p['s'] < 0 || (int)$p['s'] > 59){
						$errors[] = "Line {$p['ln']}: seconds out of range (0-59) in {$p['pos']} timecode";
					}
					if((int)$p['ms'] < 0 || (int)$p['ms'] > 999){
						$errors[] = "Line {$p['ln']}: milliseconds out of range (0-999) in {$p['pos']} timecode";
					}
				}
				if(empty($errors) || \end($errors)[-1] !== $line_number){
					$timestamps[] = [
						'start' => $this->core->media->timecode_to_seconds((int)$h1, (int)$mi1, (int)$s1, (int)$ms1),
						'end' => $this->core->media->timecode_to_seconds((int)$h2, (int)$mi2, (int)$s2, (int)$ms2),
						'line' => $line_number,
					];
				}
			}
		}
		for($i = 0; $i < \count($timestamps) - 1; $i++){
			if($timestamps[$i]['end'] > $timestamps[$i + 1]['start']){
				$errors[] = \sprintf(
					"Time in line %d (%s --> %s) overlaps with line %d (%s --> %s)",
					$timestamps[$i]['line'],
					$this->core->seconds_to_time($timestamps[$i]['start'], true, false, true),
					$this->core->seconds_to_time($timestamps[$i]['end'], true, false, true),
					$timestamps[$i + 1]['line'],
					$this->core->seconds_to_time($timestamps[$i + 1]['start'], true, false, true),
					$this->core->seconds_to_time($timestamps[$i + 1]['end'], true, false, true)
				);
			}
			if($timestamps[$i]['start'] > $timestamps[$i + 1]['start']){
				$errors[] = \sprintf(
					"Subtitle timing out of order: line %d starts after line %d",
					$timestamps[$i]['line'],
					$timestamps[$i + 1]['line']
				);
			}
		}
		return $errors;
	}

	/**
	 * Compares two SRT subtitle files and identifies missing subtitle blocks.
	 *
	 * Comparison is based on the timecode range (start --> end) and the text content.
	 *
	 * @param string $path_a The path to the first SRT file.
	 * @param string $path_b The path to the second SRT file.
	 * @return object|false An object containing arrays of missing subtitles in each file and global errors, or false if both files do not exist.
	 * The returned object has properties: 'global', 'file_a', 'file_b'.
	 * 'file_a' will contain errors for subtitles missing in file_b but present in file_a.
	 * 'file_b' will contain errors for subtitles missing in file_a but present in file_b.
	 */
	public function srt_compare(string $path_a, string $path_b) : object|false {
		$errors = (object)[
			'global' => [],
			'file_a' => [],
			'file_b' => [],
		];
		$nea = !\file_exists($path_a);
		$neb = !\file_exists($path_b);
		if($nea && $neb) return false;
		if($nea){
			\array_push($errors->global, "File \"$path_a\" not exists");
			return $errors;
		} elseif($neb){
			\array_push($errors->global, "File \"$path_b\" not exists");
			return $errors;
		}

		$lines = $this->srt_extract($path_a);
		$srt_content_a = [];
		foreach($lines as $line){
			$key = "{$line['start']} --> {$line['end']}";
			$srt_content_a[$key] = [
				'index' => $line['index'],
				'text' => $line['text'],
			];
		}
		unset($lines, $line);

		$lines = $this->srt_extract($path_b);
		$srt_content_b = [];
		foreach($lines as $line){
			$key = "{$line['start']} --> {$line['end']}";
			$srt_content_b[$key] = [
				'index' => $line['index'],
				'text' => $line['text'],
			];
		}
		unset($lines, $line);

		$keys_a = \array_keys($srt_content_a);
		$keys_b = \array_keys($srt_content_b);

		$diff_keys_a = \array_diff($keys_a, $keys_b);
		$diff_keys_b = \array_diff($keys_b, $keys_a);

		if(!empty($diff_keys_a)){
			foreach($diff_keys_a as $error_key){
				$line = $srt_content_a[$error_key];
				\array_push($errors->file_b, "Missing \"$error_key\" with \"{$line['text']}\"");
			}
		}

		if(!empty($diff_keys_b)){
			foreach($diff_keys_b as $error_key){
				$line = $srt_content_b[$error_key];
				\array_push($errors->file_a, "Missing \"$error_key\" with \"{$line['text']}\"");
			}
		}

		return $errors;
	}

	/**
	 * Extracts subtitle blocks from an SRT file.
	 *
	 * Each subtitle block is parsed into an associative array containing:
	 * - 'index': The subtitle index number.
	 * - 'start': The start timecode string (HH:MM:SS,ms).
	 * - 'end': The end timecode string (HH:MM:SS,ms).
	 * - 'start_seconds': The start time in total seconds.
	 * - 'end_seconds': The end time in total seconds.
	 * - 'text': The subtitle text.
	 *
	 * @param string $path The path to the SRT file.
	 * @return array|false An array of subtitle blocks, or false if the file does not exist.
	 */
	public function srt_extract(string $path) : array|false {
		if(!\file_exists($path)) return false;
		$content = \file_get_contents($path);
		$blocks = \preg_split("/\R{2,}/", $content);
		$subtitles = [];
		foreach($blocks as $block){
			$lines = \preg_split("/\R/", \trim($block));
			if(\count($lines) < 3) continue;
			$index = (int)\array_shift($lines);
			$timestamp_line = \array_shift($lines);
			$pattern = '/^(\d{2}):(\d{2}):(\d{2}),(\d{3})\s+-->\s+(\d{2}):(\d{2}):(\d{2}),(\d{3})$/';
			if(!\preg_match($pattern, $timestamp_line, $m)) continue;
			$start = $this->core->media->timecode_to_seconds((int)$m[1], (int)$m[2], (int)$m[3], (int)$m[4]);
			$end = $this->core->media->timecode_to_seconds((int)$m[5], (int)$m[6], (int)$m[7], (int)$m[8]);
			$text = \implode("\n", $lines);
			$subtitles[] = [
				'index' => $index,
				'start' => "{$m[1]}:{$m[2]}:{$m[3]},{$m[4]}",
				'end' => "{$m[5]}:{$m[6]}:{$m[7]},{$m[8]}",
				'start_seconds' => $start,
				'end_seconds' => $end,
				'text' => $text,
			];
		}
		return $subtitles;
	}

}

?>