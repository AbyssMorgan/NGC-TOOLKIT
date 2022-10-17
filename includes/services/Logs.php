<?php

declare(strict_types=1);

namespace App\Services;

class Logs {

	private $path;
	private $timestamp;
	private $hold_open;
	private $file;

	function __construct($path = null, $timestamp = true, $hold_open = false){
		$this->path = $path;
		$this->timestamp = $timestamp;
		$this->hold_open = $hold_open;
		$this->file = false;
	}

	protected function create(){
		$folder = pathinfo($this->path,PATHINFO_DIRNAME);
		if(!file_exists($folder)) mkdir($folder, 0777, true);
		$file = fopen($this->path, "w");
		if(!$file) return false;
		fwrite($file,"");
		fclose($file);
		return file_exists($this->path);
	}

	protected function writeString($line){
		if(!$this->file) $this->file = fopen($this->path,"a");
		if(!$this->file) return false;
		if($this->timestamp) fwrite($this->file,"[".date("Y-m-d H:i:s")."] ");
		fwrite($this->file,$line."\r\n");
		if(!$this->hold_open) $this->close();
		return true;
	}

	protected function writeArray($lines){
		if(!$this->file) $this->file = fopen($this->path,"a");
		if(!$this->file) return false;
		foreach($lines as $line){
			if($this->timestamp) fwrite($this->file,"[".date("Y-m-d H:i:s")."] ");
			fwrite($this->file,$line."\r\n");
		}
		if(!$this->hold_open) $this->close();
		return true;
	}

	public function write($content){
		if(is_null($this->path)) return false;
		if(!file_exists($this->path)){
			if(!$this->create()) return false;
		}
		if(gettype($content) == "array") return $this->writeArray($content);
		return $this->writeString($content);
	}

	public function getPath(){
		return $this->path;
	}

	public function close(){
		if(!$this->file) return false;
		fclose($this->file);
		$this->file = false;
		return true;
	}

}

?>
