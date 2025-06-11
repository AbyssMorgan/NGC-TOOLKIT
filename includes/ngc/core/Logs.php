<?php

/**
 * NGC-TOOLKIT v2.6.1 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Core;

class Logs {

	private string $path;
	private bool $timestamp;
	private bool $hold_open;
	private string $date_format;
	private int $permissions;
	private $file;

	public function __construct(string $path, bool $timestamp = true, bool $hold_open = false, string $date_format = 'Y-m-d H:i:s', int $permissions = 0755){
		$this->path = $path;
		$this->timestamp = $timestamp;
		$this->hold_open = $hold_open;
		$this->date_format = $date_format;
		$this->permissions = $permissions;
		$this->file = false;
	}

	protected function create() : bool {
		$folder = pathinfo($this->path, PATHINFO_DIRNAME);
		if(!file_exists($folder)) mkdir($folder, $this->permissions, true);
		$file = fopen($this->path, "w");
		if(!$file) return false;
		fwrite($file, "");
		fclose($file);
		return file_exists($this->path);
	}

	protected function write_string(string $line) : bool {
		if(!$this->file) $this->file = fopen($this->path, "a");
		if(!$this->file) return false;
		if($this->timestamp){
			fwrite($this->file, "[".$this->get_timestamp()."] ");
		}
		fwrite($this->file, mb_convert_encoding("$line\r\n", 'UTF-8', 'auto'));
		if(!$this->hold_open) $this->close();
		return true;
	}

	protected function write_array(array $lines) : bool {
		if(!$this->file) $this->file = fopen($this->path, "a");
		if(!$this->file) return false;
		foreach($lines as $line){
			if($this->timestamp){
				fwrite($this->file, "[".$this->get_timestamp()."] ");
			}
			fwrite($this->file, mb_convert_encoding("$line\r\n", 'UTF-8', 'auto'));
		}
		if(!$this->hold_open) $this->close();
		return true;
	}

	public function set_date_format(string $date_format) : void {
		$this->date_format = $date_format;
	}

	public function get_timestamp() : string {
		return date($this->date_format);
	}

	public function write(string|array $content) : bool {
		if(is_null($this->path)) return false;
		if(!file_exists($this->path)){
			if(!$this->create()) return false;
		}
		if(gettype($content) == "array") return $this->write_array($content);
		return $this->write_string($content);
	}

	public function get_path() : string {
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