<?php

/* NGC-TOOLKIT v2.5.1 */

declare(strict_types=1);

namespace NGC\Core;

class JournalService {

	protected ?string $path;
	protected BitFunctions $bits;

	public const FILE_HEADER_DATA = 'ADM-JOURNAL';

	public function __construct(?string $path = null){
		$this->path = $path;
		$this->bits = new BitFunctions(32);
	}

	protected function create() : bool {
		$folder = pathinfo($this->path, PATHINFO_DIRNAME);
		if(!file_exists($folder)) mkdir($folder, 0755, true);
		$fp = fopen($this->path, "w");
		if(!$fp) return false;
		fwrite($fp, self::FILE_HEADER_DATA."\1");
		fclose($fp);
		return file_exists($this->path);
	}

	protected function length(string $data) : int {
		return strlen(bin2hex($data)) / 2;
	}

	protected function write_string(string $line) : bool {
		$fp = fopen($this->path, "a");
		if(!$fp) return false;
		$int1 = 0;
		$int2 = 0;
		$int3 = 0;
		$int4 = 0;
		$raw = gzcompress($line, 9);
		$length = $this->length($raw);
		$this->bits->extract_value($length, $int1, $int2, $int3, $int4);
		fwrite($fp, chr($int1).chr($int2).chr($int3).chr($int4).$raw);
		fclose($fp);
		return true;
	}

	protected function write_array(array $lines) : bool {
		$fp = fopen($this->path, "a");
		if(!$fp) return false;
		$int1 = 0;
		$int2 = 0;
		$int3 = 0;
		$int4 = 0;
		foreach($lines as $line){
			$raw = gzcompress($line, 9);
			$length = $this->length($raw);
			$this->bits->extract_value($length, $int1, $int2, $int3, $int4);
			fwrite($fp, chr($int1).chr($int2).chr($int3).chr($int4).$raw);
		}
		fclose($fp);
		return true;
	}

	public function write(string|array $content) : bool {
		if(is_null($this->path)) return false;
		if(!file_exists($this->path)){
			if(!$this->create()) return false;
		}
		if(gettype($content) == "array") return $this->write_array($content);
		return $this->write_string($content);
	}

	public function read(bool $json = false) : ?array {
		if(!file_exists($this->path)) return null;
		$data = [];
		$fp = fopen($this->path, "rb");
		if(!$fp) return null;
		$header = fread($fp, 11);
		if(!in_array($header, [self::FILE_HEADER_DATA, 'EMU-JOURNAL'])) return null;
		fseek($fp, 12);
		while(!feof($fp)){
			$l = fread($fp, 4);
			if(!isset($l[0])) break;
			$length = $this->bits->merge_value(ord($l[0]), ord($l[1]), ord($l[2]), ord($l[3]));
			$string = gzuncompress(fread($fp, $length));
			if($json) $string = json_decode($string, true);
			array_push($data, $string);
		}
		fclose($fp);
		return $data;
	}

}

?>