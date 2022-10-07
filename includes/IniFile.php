<?php

class IniFile {

	protected $path;

	protected $data;

	protected $valid;

	protected $sort;

	protected $original;

	public $version = 10500;

	function __construct($path = null, $sort = false){
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

	protected function create(){
		$folder = pathinfo($this->path,PATHINFO_DIRNAME);
		if(!file_exists($folder)) mkdir($folder, 0777, true);
		$file = fopen($this->path, "w");
		if(!$file) return false;
		fwrite($file,"");
		fclose($file);
		return file_exists($this->path);
	}

	public function open($path, $sort = false){
		$this->path = $path;
		$this->sort = $sort;
		$this->valid = $this->read();
		return $this->valid;
	}

	public function close(){
		$this->valid = false;
		$this->path = null;
		$this->data = [];
		$this->original = [];
	}

	public function read(){
		if(!file_exists($this->path)){
			if(!$this->create()) return false;
		}
		$file = fopen($this->path,"r");
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

	public function parse_line($line, &$key, &$data, $escape = true){
		if($escape) $line = str_replace("\n","",str_replace("\r","",str_replace("\xEF\xBB\xBF","",$line)));
		if(strlen($line) == 0 || $line[0] == '#' || $line[0] == ';' || $line[0] == '[') return false;
		$option = explode("=",$line,2);
		if(!empty($option[0])){
			$key = $option[0];
			if(!isset($option[1])){
				$data = null;
			} else if(is_numeric($option[1])){
				if(strpos($option[1],".") !== false){
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
				if(substr($option[1],0,1) == '"' && substr($option[1],-1,1) == '"'){
					$data = substr($option[1],1,-1);
				} else {
					$data = $option[1];
				}
				if(substr($data,0,5) == 'JSON:'){
					$data = json_decode(base64_decode(substr($data,5)),true);
				}
			}
		}
		return true;
	}

	public function isChanged(){
		return (json_encode($this->getOriginalAll()) != json_encode($this->getAll()));
	}

	public function isValueChanged($key){
		$value = $this->get($key);
		$original = $this->getOriginal($key);
		if(gettype($value) != gettype($original)) return false;
		if(gettype($value) == 'array'){
			return json_encode($value) != json_encode($original);
		} else {
			return $value != $original;
		}
	}

	public function getOriginalAll(){
		return $this->original;
	}

	public function getAll(){
		return $this->data;
	}

	public function getSorted(){
		$data = $this->data;
		ksort($data);
		return $data;
	}

	public function sort(){
		ksort($this->data);
	}

	public function setAll($data, $save = false){
		if(gettype($data) == 'array'){
			$this->data = $data;
			if($save) return $this->save();
			return true;
		}
		return false;
	}

	public function update($data, $save = false){
		if(gettype($data) == 'array'){
			foreach($data as $key => $value){
				$this->set($key,$value);
			}
			if($save) return $this->save();
			return true;
		}
		return false;
	}

	public function get($key, $default = null){
		return $this->data[$key] ?? $default;
	}

	public function getOriginal($key, $default = null){
		return $this->original[$key] ?? $default;
	}

	public function set($key, $value){
		$this->data[$key] = $this->cleanValue($value);
	}

	public function cleanValue($value){
		if(gettype($value) == 'string'){
			if(strtolower($value) == 'true'){
				return true;
			} else if(strtolower($value) == 'false'){
				return false;
			}
		}
		return $value;
	}

	public function rename($key1, $key2){
		$this->set($key2, $this->get($key1));
		$this->unset($key1);
	}

	public function unset($keys){
		if(gettype($keys) == 'string') $keys = [$keys];
		foreach($keys as $key){
			if($this->isSet($key)){
				unset($this->data[$key]);
			}
		}
	}

	public function reset($keys, $value = null){
		if(gettype($keys) == 'string') $keys = [$keys];
		foreach($keys as $key){
			if($this->isSet($key)){
				$this->set($key,$value);
			}
		}
	}

	public function save(){
		if(!$this->isValid()) return false;
		if(file_exists($this->path)){
			chmod($this->path, 0777);
			unlink($this->path);
		}
		$file = fopen($this->path,"w");
		if(!$file) return false;
		if($this->sort) ksort($this->data);
		foreach($this->data as $key => $value){
			if(is_numeric($value)){
				fwrite($file,"$key=$value\r\n");
			} else if(is_null($value)){
				fwrite($file,"$key=null\r\n");
			} else if(is_bool($value)){
				$value = $value ? 'true' : 'false';
				fwrite($file,"$key=$value\r\n");
			} else if(empty($value) && !is_array($value)){
				fwrite($file,"$key=\"\"\r\n");
			} else if(is_array($value)){
				$value = "JSON:".base64_encode(json_encode($value));
				fwrite($file,"$key=\"$value\"\r\n");
			} else {
				fwrite($file,"$key=\"$value\"\r\n");
			}
		}
		fclose($file);
		return true;
	}

	public function isValid(){
		return $this->valid;
	}

	public function toggleSort($sort){
		$this->sort = $sort;
	}

	public function keys(){
		return array_keys($this->data);
	}

	public function isSet($key){
		return array_key_exists($key, $this->data);
	}

	public function getSize(){
		if(!$this->isValid()) return 0;
		return filesize($this->path);
	}

	public function getModificationDate(){
		if(!$this->isValid()) return '0000-00-00 00:00:00';
		return date("Y-m-d H:i:s",filemtime($this->path));
	}

	public function toJson(){
		return json_encode($this->data);
	}

	public function fromJson($json, $merge = false, $save = false){
		if($merge){
			$this->update(json_decode($json, true), $save);
		} else {
			$this->setAll(json_decode($json, true), $save);
		}
	}

	public function fromAssoc($assoc, $merge = false, $save = false){
		if(!$merge) $this->data = [];
		foreach($assoc as $key => $value){
			$this->set($key, $value);
		}
		if($save) $this->save();
	}

	public function search($search){
		$results = [];
		$keys = $this->keys();
		foreach($keys as $key){
			if(strpos($key,$search) !== false){
				array_push($results,$key);
			}
		}
		return $results;
	}

	public function searchPrefix($search){
		$results = [];
		$keys = $this->keys();
		foreach($keys as $key){
			if(strpos('#'.$key,'#'.$search) !== false){
				array_push($results,$key);
			}
		}
		return $results;
	}

	public function searchSuffix($search){
		$results = [];
		$keys = $this->keys();
		foreach($keys as $key){
			if(strpos($key.'#',$search.'#') !== false){
				array_push($results,$key);
			}
		}
		return $results;
	}

	public function setChanged($key, $value, $default = null){
		if($this->cleanValue($value) != $default){
			$this->set($key, $value);
		} else {
			$this->unset($key);
		}
	}

	public function only($keys){
		if(gettype($keys) == 'string') $keys = [$keys];
		$data = [];
		foreach($keys as $key){
			$data[$key] = $this->get($key);
		}
		return $data;
	}

	public function allExcept($keys){
		if(gettype($keys) == 'string') $keys = [$keys];
		$data = [];
		foreach($this->keys() as $key){
			if(!in_array($key,$keys)) $data[$key] = $this->get($key);
		}
		return $data;
	}

	public function extract_path(&$data, $key, $delimiter = '/'){
		$this->set_nested_array_value($data, $key, $this->get($key), $delimiter);
	}

	public function set_nested_array_value(&$array, $path, $value, $delimiter = '/'){
		$pathParts = explode($delimiter, $path);
		$current = &$array;
		foreach($pathParts as $key) $current = &$current[$key];
		$backup = $current;
		$current = $value;
		return $backup;
	}

}

?>
