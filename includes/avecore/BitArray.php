<?php

/* AVE-PHP v2.2.0 */

declare(strict_types=1);

namespace AveCore;

class BitArray {

	protected array $original = [];

	protected int $max_bits;

	protected BitFunctions $bits;

	public function __construct(){
		$this->bits = new BitFunctions();
		$this->max_bits = $this->bits->get_max_bits();
	}

	public function get_config_address(int $id) : int {
		return (int)floor(intval($id) / $this->max_bits);
	}

	public function get_config_bit(int $id) : int {
		return (int)intval($id) % $this->max_bits;
	}

	public function get_config_size(int $max_items) : int {
		return (int)floor(intval($max_items) / $this->max_bits) + 1;
	}

	public function get_config_used_size() : int {
		$size = 0;
		foreach($this->original as $key => $value) $size = max($size, $key);
		return (int)($size + 1);
	}

	public function get_config(int $id) : bool {
		$address = $this->get_config_address($id);
		return $this->bits->get_bit_Value($this->original[$address] ?? 0, $this->get_config_bit($id));
	}

	public function set_config(int $id, bool $state) : void {
		$address = $this->get_config_address($id);
		if(!isset($this->original[$address])) $this->original[$address] = 0;
		$this->bits->set_bit_Value($this->original[$address], $this->get_config_bit($id), $state);
	}

	public function from_array(array $array) : void {
		$this->original = $array;
	}

	public function from_assoc(array $assoc, array $keys) : void {
		$this->original = [];
		foreach($keys as $id => $key){
			$this->set_config($id, ($assoc[$key] ?? false));
		}
	}

	public function from_json(string $json) : void {
		$this->from_array(json_decode($json, true));
	}

	public function from_binary(string $binary, int $length) : void {
		$this->original = [];
		$address = 0;
		for($offset = 0; $offset < $length; $offset += 4){
			$this->original[$address] = $this->bits->merge_value(ord($binary[$offset] ?? 0), ord($binary[$offset+1] ?? 0), ord($binary[$offset+2] ?? 0), ord($binary[$offset+3] ?? 0));
			$address++;
		}
	}

	public function from_hex(string $hex) : void {
		$this->from_binary(hex2bin($hex), strlen($hex) * 2);
	}

	public function from_file(string $path) : bool {
		if(!file_exists($path)) return false;
		$file = fopen($path, "rb");
		if(!$file) return false;
		$length = filesize($path);
		$this->from_binary(fread($file, $length), $length);
		fclose($file);
		return true;
	}

	public function to_array() : array {
		return $this->original;
	}

	public function to_assoc(array $keys) : array {
		$data = [];
		$id = 0;
		$max_address = $this->get_config_used_size();
		for($address = 0; $address < $max_address; $address++){
			for($bitid = 0; $bitid < $this->max_bits; $bitid++){
				if(isset($keys[$id])){
					$data[$keys[$id]] = $this->get_config($id);
				}
				$id++;
			}
		}
		return $data;
	}

	public function to_json() : string {
		return json_encode($this->to_array());
	}

	public function to_binary(int &$length = 0) : string {
		$data = '';
		$length = 0;
		$int1 = 0;
		$int2 = 0;
		$int3 = 0;
		$int4 = 0;
		$max_address = $this->get_config_used_size();
		for($address = 0; $address < $max_address; $address++){
			$this->bits->extract_value($this->original[$address] ?? 0, $int1, $int2, $int3, $int4);
			$data .= chr($int1);
			$data .= chr($int2);
			$data .= chr($int3);
			$data .= chr($int4);
			$length += 4;
		}
		return $data;
	}

	public function to_hex() : string {
		return bin2hex($this->to_binary());
	}

	public function to_file(string $path) : bool {
		if(file_exists($path)){
			unlink($path);
			if(file_exists($path)) return false;
		}
		$file = fopen($path, "wb");
		if(!$file) return false;
		$length = 0;
		$data = $this->to_binary($length);
		fwrite($file, $data, $length);
		fclose($file);
		return true;
	}

}

?>