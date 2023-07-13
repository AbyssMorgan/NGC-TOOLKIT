<?php

declare(strict_types=1);

namespace App\Services;

use Exception;

class IniFile {

	protected ?string $path;
	protected array $data;
	protected bool $valid;
	protected bool $sort;
	protected array $original;

	public int $version = 20400;

	function __construct(?string $path = null, bool $sort = false){
		$this->path = $path;
		$this->data = [];
		$this->original = [];
		$this->sort = $sort;
		if(is_null($this->path)){
			$this->valid = false;
		} else {
			$this->valid = $this->read();
		}
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

	public function open(string $path, bool $sort = false) : bool {
		$this->path = $path;
		$this->sort = $sort;
		$this->valid = $this->read();
		return $this->valid;
	}

	public function close() : void {
		$this->valid = false;
		$this->path = null;
		$this->data = [];
		$this->original = [];
	}

	public function read() : bool {
		if(!file_exists($this->path)){
			if(!$this->create()) return false;
		}
		$file = fopen($this->path, "r");
		if(!$file) return false;
		$this->data = [];
		while(($line = fgets($file)) !== false){
			if($this->parse_line($line, $key, $data)){
				$this->data[$key] = $data;
			}
		}
		$this->original = $this->data;
		fclose($file);
		return true;
	}

	public function parse_line(string $line, &$key, int|bool|string|array|float|null &$data, bool $escape = true) : bool {
		if($escape) $line = str_replace(["\n", "\r", "\xEF\xBB\xBF"], "", $line);
		if(strlen($line) == 0 || $line[0] == '#' || $line[0] == ';' || $line[0] == '[') return false;
		$option = explode("=", $line, 2);
		if(!empty(trim($option[0]))){
			$key = trim($option[0]);
			if(!isset($option[1])){
				$data = null;
			} else if(is_numeric($option[1])){
				if(strpos($option[1], ".") !== false){
					$data = floatval($option[1]);
				} else {
					$data = intval($option[1]);
				}
			} else if(empty($option[1])){
				$data = "";
			} else if($option[1] == 'false'){
				$data = false;
			} else if($option[1] == 'true'){
				$data = true;
			} else if($option[1] == 'null'){
				$data = null;
			} else {
				if(substr($option[1], 0, 1) == '"' && substr($option[1], -1, 1) == '"'){
					$data = substr($option[1], 1, -1);
				} else {
					$data = $option[1];
				}
				if(substr($data, 0, 5) == 'JSON:'){
					$data = json_decode(base64_decode(substr($data, 5)), true);
				}
			}
		}
		return true;
	}

	public function isChanged() : bool {
		return (json_encode($this->getOriginalAll()) != json_encode($this->getAll()));
	}

	public function isValueChanged(string $key) : bool {
		$value = $this->get($key);
		$original = $this->getOriginal($key);
		if(gettype($value) != gettype($original)) return false;
		if(gettype($value) == 'array'){
			return json_encode($value) != json_encode($original);
		} else {
			return $value != $original;
		}
	}

	public function getOriginalAll() : array {
		return $this->original;
	}

	public function getAll() : array {
		return $this->data;
	}

	public function getSorted() : array {
		$data = $this->data;
		ksort($data);
		return $data;
	}

	public function sort() : void {
		ksort($this->data);
	}

	public function setAll(array $data, bool $save = false) : void {
		$this->data = $data;
		if($save) $this->save();
	}

	public function update(array $data, bool $save = false) : void {
		foreach($data as $key => $value){
			$this->set($key, $value);
		}
		if($save) $this->save();
	}

	public function get(string $key, int|bool|string|array|float|null $default = null) : mixed {
		return $this->data[$key] ?? $default;
	}

	public function getString(string $key, int|bool|string|array|float|null $default = null) : string {
		return strval($this->data[$key] ?? $default);
	}

	public function getOriginal(string $key, int|bool|string|array|float|null $default = null) : mixed {
		return $this->original[$key] ?? $default;
	}

	public function set(string $key, int|bool|string|array|float|null $value) : void {
		$this->data[$key] = $this->cleanValue($value);
	}

	public function cleanValue(int|bool|string|array|float|null $value) : mixed {
		if(gettype($value) == 'string'){
			if(strtolower($value) == 'true'){
				return true;
			} else if(strtolower($value) == 'false'){
				return false;
			}
		}
		return $value;
	}

	public function rename(string $key1, string $key2) : void {
		$this->set($key2, $this->get($key1));
		$this->unset($key1);
	}

	public function unset(string|array $keys) : void {
		if(gettype($keys) == 'string') $keys = [$keys];
		foreach($keys as $key){
			if($this->isSet($key)){
				unset($this->data[$key]);
			}
		}
	}

	public function reset(string|array $keys, int|bool|string|array|float|null $value = null) : void {
		if(gettype($keys) == 'string') $keys = [$keys];
		foreach($keys as $key){
			if($this->isSet($key)){
				$this->set($key, $value);
			}
		}
	}

	public function save() : bool {
		if(!$this->isValid()) return false;
		if(file_exists($this->path)){
			try {
				chmod($this->path, 0755);
				unlink($this->path);
			}
			catch(Exception $e){

			}
		}
		$file = fopen($this->path, "w");
		if(!$file) return false;
		if($this->sort) ksort($this->data);
		foreach($this->data as $key => $value){
			if(is_numeric($value)){
				fwrite($file, "$key=$value\r\n");
			} else if(is_null($value)){
				fwrite($file, "$key=null\r\n");
			} else if(is_bool($value)){
				$value = $value ? 'true' : 'false';
				fwrite($file, "$key=$value\r\n");
			} else if(empty($value) && !is_array($value)){
				fwrite($file, "$key=\"\"\r\n");
			} else if(is_array($value)){
				$value = "JSON:".base64_encode(json_encode($value));
				fwrite($file, "$key=\"$value\"\r\n");
			} else {
				fwrite($file, "$key=\"$value\"\r\n");
			}
		}
		fclose($file);
		return true;
	}

	public function isValid() : bool {
		return $this->valid;
	}

	public function toggleSort(bool $sort) : void {
		$this->sort = $sort;
	}

	public function keys() : array {
		return array_keys($this->data);
	}

	public function isSet(string $key) : bool {
		return array_key_exists($key, $this->data);
	}

	public function getSize() : int {
		if(!$this->isValid()) return 0;
		$size = filesize($this->path);
		if(!$size) return 0;
		return $size;
	}

	public function getModificationDate() : string {
		if(!$this->isValid()) return '0000-00-00 00:00:00';
		return date("Y-m-d H:i:s", filemtime($this->path));
	}

	public function toJson() : string|false {
		return json_encode($this->data);
	}

	public function fromJson(string $json, bool $merge = false, bool $save = false) : void {
		if($merge){
			$this->update(json_decode($json, true), $save);
		} else {
			$this->setAll(json_decode($json, true), $save);
		}
	}

	public function fromAssoc(array $assoc, bool $merge = false, bool $save = false) : void {
		if(!$merge) $this->data = [];
		foreach($assoc as $key => $value){
			$this->set($key, $value);
		}
		if($save) $this->save();
	}

	public function search(string $search) : array {
		$results = [];
		$keys = $this->keys();
		foreach($keys as $key){
			if(strpos($key, $search) !== false){
				array_push($results, $key);
			}
		}
		return $results;
	}

	public function searchPrefix(string $search) : array {
		$results = [];
		$keys = $this->keys();
		foreach($keys as $key){
			if(strpos('#'.$key, '#'.$search) !== false){
				array_push($results, $key);
			}
		}
		return $results;
	}

	public function searchSuffix(string $search) : array {
		$results = [];
		$keys = $this->keys();
		foreach($keys as $key){
			if(strpos($key.'#', $search.'#') !== false){
				array_push($results, $key);
			}
		}
		return $results;
	}

	public function setChanged(string $key, int|bool|string|array|float|null $value, int|bool|string|array|float|null $default = null) : void {
		if($this->cleanValue($value) != $default){
			$this->set($key, $value);
		} else {
			$this->unset($key);
		}
	}

	public function only(string|array $keys) : array {
		if(gettype($keys) == 'string') $keys = [$keys];
		$data = [];
		foreach($keys as $key){
			$data[$key] = $this->get($key);
		}
		return $data;
	}

	public function allExcept(string|array $keys) : array {
		if(gettype($keys) == 'string') $keys = [$keys];
		$data = [];
		foreach($this->keys() as $key){
			if(!in_array($key, $keys)) $data[$key] = $this->get($key);
		}
		return $data;
	}

	public function extract_path(array &$data, string $key, string $delimiter = '/') : void {
		$this->set_nested_array_value($data, $key, $this->get($key), $delimiter);
	}

	public function set_nested_array_value(array &$array, string $path, array $value, string $delimiter = '/') : void {
		$pathParts = explode($delimiter, $path);
		$current = &$array;
		foreach($pathParts as $key) $current = &$current[$key];
		$current = $value;
	}

}

?>
