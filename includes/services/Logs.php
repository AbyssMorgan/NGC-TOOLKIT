<?php

declare(strict_types=1);

namespace App\Services;

class Logs {

	private string $path;
	private bool $timestamp;
	private bool $hold_open;
	private $file;

	public int $version = 10100;

	function __construct(string $path, bool $timestamp = true, bool $hold_open = false){
		$this->path = $path;
		$this->timestamp = $timestamp;
		$this->hold_open = $hold_open;
		$this->file = false;
	}

	protected function create() : bool {
		$folder = pathinfo($this->path, PATHINFO_DIRNAME);
		if(!file_exists($folder)) mkdir($folder, 0755, true);
		$file = fopen($this->path, "w");
		if(!$file) return false;
		fwrite($file, "");
		fclose($file);
		return file_exists($this->path);
	}

	protected function writeString(string $line) : bool {
		if(!$this->file) $this->file = fopen($this->path, "a");
		if(!$this->file) return false;
		if($this->timestamp) fwrite($this->file, "[".date("Y-m-d H:i:s")."] ");
		fwrite($this->file, $line."\r\n");
		if(!$this->hold_open) $this->close();
		return true;
	}

	protected function writeArray(array $lines) : bool {
		if(!$this->file) $this->file = fopen($this->path, "a");
		if(!$this->file) return false;
		foreach($lines as $line){
			if($this->timestamp) fwrite($this->file, "[".date("Y-m-d H:i:s")."] ");
			fwrite($this->file, $line."\r\n");
		}
		if(!$this->hold_open) $this->close();
		return true;
	}

	public function write(string|array $content) : bool {
		if(is_null($this->path)) return false;
		if(!file_exists($this->path)){
			if(!$this->create()) return false;
		}
		if(gettype($content) == "array") return $this->writeArray($content);
		return $this->writeString($content);
	}

	public function getPath() : string {
		return $this->path;
	}

	public function close() : bool {
		if(!$this->file) return false;
		fclose($this->file);
		$this->file = false;
		return true;
	}

}

?>
