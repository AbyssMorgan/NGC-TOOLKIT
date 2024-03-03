<?php

declare(strict_types=1);

namespace AveCore;

use Exception;

class AppBuffer {

	protected string $path;

	function __construct(string $path){
		$this->path = $path;
		if(!file_exists($this->path)) mkdir($this->path, 0755, true);
	}

	public function get_path() : string {
		return $this->path;
	}

	public function get_file(string $key) : string {
		$key = hash('sha256', $key);
		return $this->path.DIRECTORY_SEPARATOR."$key.json";
	}

	public function get(string $key, int|bool|string|array|float|null $default = null) : mixed {
		$file = $this->get_file($key);
		if(!file_exists($file)) return $default;
		$buffer = json_decode(file_get_contents($file), true);
		if(!isset($buffer['expire']) || !isset($buffer['type'])) return $default;
		if($buffer['expire'] != -1 && time() > $buffer['expire']) return $default;
		switch(strtolower($buffer['type'])){
			case 'integer': return (int)$buffer['value'];
			case 'boolean': return ($buffer['value'] == 'true');
			case 'string': return $buffer['value'];
			case 'array': return json_decode(base64_decode($buffer['value']), true);
			case 'float': (float)$buffer['value'];
			case 'null': return null;
		}
		return null;
	}

	public function set(string $key, int|bool|string|array|float|null $value, int $expire = -1) : void {
		$file = $this->get_file($key);
		if($expire > 0) $expire = time() + $expire;
		$type = strtolower(gettype($value));
		switch($type){
			case 'float':
			case 'integer': {
				$value = strval($value);
				break;
			}
			case 'boolean': {
				$value = $value ? 'true' : 'false';
				break;
			}
			case 'array': {
				$value = base64_encode(json_encode($value));
				break;
			}
			case 'null': {
				$value = 'null';
				break;
			}
		}
		file_put_contents($file, json_encode([
			'key' => $key, 
			'type' => $type, 
			'value' => $value, 
			'expire' => $expire, 
		]));
	}

	public function forget(string $key) : void {
		$this->delete($this->get_file($key));
	}

	public function clear_expired() : void {
		$files = scandir($this->path);
		foreach($files as $file){
			$buffer = json_decode(file_get_contents($this->path.DIRECTORY_SEPARATOR.$file), true);
			if($buffer['expire'] != -1 && time() > $buffer['expire']){
				$this->delete($this->path.DIRECTORY_SEPARATOR.$file);
			}
		}
	}

	public function clear() : void {
		$files = scandir($this->path);
		foreach($files as $file){
			$this->delete($this->path.DIRECTORY_SEPARATOR.$file);
		}
	}

	private function delete(string $path) : void {
		try {
			if(file_exists($path)) unlink($path);
		}
		catch(Exception $e){

		}
	}

}

?>
