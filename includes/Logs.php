<?php

class Logs {

	protected $path;
	protected $timestamp;

	function __construct($path = null, $timestamp = true){
		$this->path = $path;
		$this->timestamp = $timestamp;
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
		$file = fopen($this->path,"a");
		if(!$file) return false;
		if($this->timestamp) fwrite($file,"[".date("Y-m-d H:i:s")."] ");
		fwrite($file,$line."\r\n");
		fclose($file);
		return true;
	}

	protected function writeArray($lines){
		$file = fopen($this->path,"a");
		if(!$file) return false;
		foreach($lines as $line){
			if($this->timestamp) fwrite($file,"[".date("Y-m-d H:i:s")."] ");
			fwrite($file,$line."\r\n");
		}
		fclose($file);
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

}

?>
