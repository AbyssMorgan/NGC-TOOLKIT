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

namespace NGC\Core;

/**
 * JournalService class for reading and writing adm journal files.
 */
class JournalService {

	/**
	 * The path to the journal file.
	 * @var string|null
	 */
	protected ?string $path;

	/**
	 * An instance of the BitFunctions class for bit manipulation.
	 * @var BitFunctions
	 */
	protected BitFunctions $bits;

	/**
	 * The file permissions for the journal file.
	 * @var int
	 */
	protected int $permissions;

	/**
	 * The expected header data for the journal file.
	 * @const string
	 */
	public const FILE_HEADER_DATA = 'ADM-JOURNAL';

	/**
	 * Constructor for the JournalService.
	 *
	 * @param string|null $path The path to the journal file. Defaults to null.
	 * @param int $permissions The file permissions for the journal file. Defaults to 0755.
	 */
	public function __construct(?string $path = null, int $permissions = 0755){
		$this->path = $path;
		$this->permissions = $permissions;
		$this->bits = new BitFunctions(32);
	}

	/**
	 * Creates a new journal file with the predefined header.
	 *
	 * @return bool True if the file was created successfully, false otherwise.
	 */
	protected function create() : bool {
		$folder = \pathinfo($this->path, PATHINFO_DIRNAME);
		if(!\file_exists($folder)) \mkdir($folder, $this->permissions, true);
		$fp = \fopen($this->path, "w");
		if(!$fp) return false;
		\fwrite($fp, self::FILE_HEADER_DATA."\1");
		\fclose($fp);
		return \file_exists($this->path);
	}

	/**
	 * Calculates the length of the given data in bytes.
	 *
	 * @param string $data The data to calculate the length of.
	 * @return int The length of the data in bytes.
	 */
	protected function length(string $data) : int {
		return \strlen(\bin2hex($data)) / 2;
	}

	/**
	 * Writes a single string to the journal file.
	 * The string is compressed before writing.
	 *
	 * @param string $line The string to write.
	 * @return bool True if the string was written successfully, false otherwise.
	 */
	protected function write_string(string $line) : bool {
		$fp = \fopen($this->path, "a");
		if(!$fp) return false;
		$int1 = 0;
		$int2 = 0;
		$int3 = 0;
		$int4 = 0;
		$raw = \gzcompress($line, 9);
		$length = $this->length($raw);
		$this->bits->extract_value($length, $int1, $int2, $int3, $int4);
		\fwrite($fp, \chr($int1).\chr($int2).\chr($int3).\chr($int4).$raw);
		\fclose($fp);
		return true;
	}

	/**
	 * Writes an array of strings to the journal file.
	 * Each string is compressed before writing.
	 *
	 * @param array $lines An array of strings to write.
	 * @return bool True if the strings were written successfully, false otherwise.
	 */
	protected function write_array(array $lines) : bool {
		$fp = \fopen($this->path, "a");
		if(!$fp) return false;
		$int1 = 0;
		$int2 = 0;
		$int3 = 0;
		$int4 = 0;
		foreach($lines as $line){
			$raw = \gzcompress($line, 9);
			$length = $this->length($raw);
			$this->bits->extract_value($length, $int1, $int2, $int3, $int4);
			\fwrite($fp, \chr($int1).\chr($int2).\chr($int3).\chr($int4).$raw);
		}
		\fclose($fp);
		return true;
	}

	/**
	 * Writes content to the journal file.
	 * If the file does not exist, it will be created.
	 * The content can be a single string or an array of strings.
	 *
	 * @param string|array $content The content to write.
	 * @return bool True if the content was written successfully, false otherwise.
	 */
	public function write(string|array $content) : bool {
		if(\is_null($this->path)) return false;
		if(!\file_exists($this->path)){
			if(!$this->create()) return false;
		}
		if(\gettype($content) == "array") return $this->write_array($content);
		return $this->write_string($content);
	}

	/**
	 * Reads the content from the journal file.
	 *
	 * @param bool $json If true, attempts to decode each line as JSON. Defaults to false.
	 * @return array|null An array of strings (or decoded JSON objects) read from the file, or null if the file does not exist or an error occurs.
	 */
	public function read(bool $json = false) : ?array {
		if(!\file_exists($this->path)) return null;
		$data = [];
		$fp = \fopen($this->path, "rb");
		if(!$fp) return null;
		$header = \fread($fp, 11);
		if(!\in_array($header, [self::FILE_HEADER_DATA, 'EMU-JOURNAL'])) return null;
		\fseek($fp, 12);
		while(!\feof($fp)){
			$l = \fread($fp, 4);
			if(!isset($l[0])) break;
			$length = $this->bits->merge_value(\ord($l[0]), \ord($l[1]), \ord($l[2]), \ord($l[3]));
			$string = \gzuncompress(\fread($fp, $length));
			if($json) $string = \json_decode($string, true);
			\array_push($data, $string);
		}
		\fclose($fp);
		return $data;
	}

}

?>