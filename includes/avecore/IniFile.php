<?php

declare(strict_types=1);

namespace AveCore;

use Exception;

class IniFile {

	protected ?string $path;
	protected array $data;
	protected bool $valid;
	protected bool $sort;
	protected array $original;
	protected bool $compressed;
	protected ?object $encoder = null;

	function __construct(?string $path = null, bool $sort = false, bool $compressed = false, ?object $encoder = null){
		$this->path = $path;
		$this->data = [];
		$this->original = [];
		$this->sort = $sort;
		$this->compressed = $compressed;
		if(!is_null($encoder)){
			if(method_exists($encoder, 'encrypt') && method_exists($encoder, 'decrypt') && method_exists($encoder, 'get_header')){
				$this->encoder = $encoder;
			}
		}
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

	public function open(string $path, bool $sort = false, bool $compressed = false) : bool {
		$this->path = $path;
		$this->sort = $sort;
		$this->compressed = $compressed;
		$this->valid = $this->read();
		return $this->valid;
	}

	public function close() : void {
		$this->valid = false;
		$this->path = null;
		$this->data = [];
		$this->original = [];
		$this->compressed = false;
	}

	public function read() : bool {
		if(!file_exists($this->path)){
			if(!$this->create()) return false;
		}
		$content = file_get_contents($this->path);
		if($content === false) return false;
		$this->data = [];
		if(strlen($content) > 0){
			if(!is_null($this->encoder)){
				$header_length = strlen($this->encoder->get_header());
				if(substr($content, 0, $header_length) == $this->encoder->get_header()){
					$content = $this->encoder->decrypt(substr($content, $header_length));
					if(is_null($content)) return false;
				}
			}
			if(substr($content, 0, 11) == 'ADM_GZ_INI:'){
				$content = str_replace(["\r\n", "\r"], "\n", gzuncompress(substr($content, 11)));
				$lines = explode("\n", $content);
				foreach($lines as $line){
					if($this->parse_line($line, $key, $data)){
						$this->data[$key] = $data;
					}
				}
			} else {
				unset($content);
				$file = fopen($this->path, "r");
				if(!$file) return false;
				while(($line = fgets($file)) !== false){
					if($this->parse_line($line, $key, $data)){
						$this->data[$key] = $data;
					}
				}
				fclose($file);
			}
		}
		$this->original = $this->data;
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

	public function is_changed() : bool {
		return (json_encode($this->get_original_all()) != json_encode($this->get_all()));
	}

	public function is_value_changed(string $key) : bool {
		$value = $this->get($key);
		$original = $this->get_original($key);
		if(gettype($value) != gettype($original)) return false;
		if(gettype($value) == 'array'){
			return json_encode($value) != json_encode($original);
		} else {
			return $value != $original;
		}
	}

	public function get_original_all() : array {
		return $this->original;
	}

	public function get_all() : array {
		return $this->data;
	}

	public function get_sorted() : array {
		$data = $this->data;
		ksort($data);
		return $data;
	}

	public function sort() : void {
		ksort($this->data);
	}

	public function set_all(array $data, bool $save = false) : void {
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

	public function get_string(string $key, int|bool|string|array|float|null $default = null) : string {
		return strval($this->data[$key] ?? $default);
	}

	public function get_original(string $key, int|bool|string|array|float|null $default = null) : mixed {
		return $this->original[$key] ?? $default;
	}

	public function set(string $key, int|bool|string|array|float|null $value) : void {
		$this->data[$key] = $this->clean_value($value);
	}

	public function clean_value(int|bool|string|array|float|null $value) : mixed {
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
			if($this->is_set($key)){
				unset($this->data[$key]);
			}
		}
	}

	public function reset(string|array $keys, int|bool|string|array|float|null $value = null) : void {
		if(gettype($keys) == 'string') $keys = [$keys];
		foreach($keys as $key){
			if($this->is_set($key)){
				$this->set($key, $value);
			}
		}
	}

	public function save() : bool {
		if(!$this->is_valid()) return false;
		if($this->sort) ksort($this->data);
		$content = "\xEF\xBB\xBF";
		foreach($this->data as $key => $value){
			if(is_numeric($value)){
				$content .= "$key=$value\r\n";
			} else if(is_null($value)){
				$content .= "$key=null\r\n";
			} else if(is_bool($value)){
				$value = $value ? 'true' : 'false';
				$content .= "$key=$value\r\n";
			} else if(empty($value) && !is_array($value)){
				$content .= "$key=\"\"\r\n";
			} else if(is_array($value)){
				$value = "JSON:".base64_encode(json_encode($value));
				$content .= "$key=\"$value\"\r\n";
			} else {
				$content .= "$key=\"$value\"\r\n";
			}
		}
		try {
			if($this->compressed) $content = "ADM_GZ_INI:".gzcompress($content, 9);
			if(!is_null($this->encoder)){
				$content = $this->encoder->encrypt($content);
				if(is_null($content)) return false;
				$content = $this->encoder->get_header().$content;
			}
			file_put_contents($this->path, $content);
		}
		catch(Exception $e){
			return false;
		}
		return true;
	}

	public function is_valid() : bool {
		return $this->valid;
	}

	public function toggle_sort(bool $sort) : void {
		$this->sort = $sort;
	}

	public function keys() : array {
		return array_keys($this->data);
	}

	public function is_set(string $key) : bool {
		return array_key_exists($key, $this->data);
	}

	public function get_size() : int {
		if(!$this->is_valid()) return 0;
		$size = filesize($this->path);
		if(!$size) return 0;
		return $size;
	}

	public function get_modification_date() : string {
		if(!$this->is_valid()) return '0000-00-00 00:00:00';
		return date("Y-m-d H:i:s", filemtime($this->path));
	}

	public function to_json() : string|false {
		return json_encode($this->data);
	}

	public function from_json(string $json, bool $merge = false, bool $save = false) : void {
		if($merge){
			$this->update(json_decode($json, true), $save);
		} else {
			$this->set_all(json_decode($json, true), $save);
		}
	}

	public function from_assoc(array $assoc, bool $merge = false, bool $save = false) : void {
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

	public function search_prefix(string $search) : array {
		$results = [];
		$keys = $this->keys();
		foreach($keys as $key){
			if(strpos('#'.$key, '#'.$search) !== false){
				array_push($results, $key);
			}
		}
		return $results;
	}

	public function search_suffix(string $search) : array {
		$results = [];
		$keys = $this->keys();
		foreach($keys as $key){
			if(strpos($key.'#', $search.'#') !== false){
				array_push($results, $key);
			}
		}
		return $results;
	}

	public function set_changed(string $key, int|bool|string|array|float|null $value, int|bool|string|array|float|null $default = null) : void {
		if($this->clean_value($value) != $default){
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

	public function all_except(string|array $keys) : array {
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