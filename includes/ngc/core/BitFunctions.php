<?php

/* NGC-TOOLKIT v2.3.0 */

declare(strict_types=1);

namespace NGC\Core;

class BitFunctions {

	protected int $original = 0;

	protected int $max_bits;

	public function __construct(int $max_bits = 32){
		if($max_bits == 64){
			$this->max_bits = 64;
		} else {
			$this->max_bits = 32;
		}
	}

	public function get_max_bits() : int {
		return $this->max_bits;
	}

	public function get_bit_Value(int $value, int $bitid) : bool {
		return (bool)(($value >> $bitid) & 0x01);
	}

	public function set_bit_Value(int &$value, int $bitid, bool $state) : void {
		$value = (($value & ~(0x01 << $bitid)) | ((0x01 << $bitid)*($state ? 1 : 0)));
	}

	public function get_bit(int $bitid) : bool {
		return $this->get_bit_Value($this->original, $bitid);
	}

	public function set_bit(int $bitid, bool $state) : void {
		$this->set_bit_Value($this->original, $bitid, $state);
	}

	public function from_int(int $int) : void {
		$this->original = $int;
	}

	public function from_array(array $array) : void {
		$this->original = 0;
		for($bitid = 0; $bitid < $this->max_bits; $bitid++){
			$this->set_bit($bitid, ($array[$bitid] ?? false));
		}
	}

	public function from_assoc(array $assoc, array $keys) : void {
		$this->original = 0;
		foreach($keys as $bitid => $key){
			$this->set_bit($bitid, ($assoc[$key] ?? false));
		}
	}

	public function from_string(string $string) : void {
		$this->original = bindec($string);
	}

	public function from_json(string $json) : void {
		$this->from_array(json_decode($json, true));
	}

	public function to_int() : int {
		return $this->original;
	}

	public function to_array() : array {
		$data = [];
		for($bitid = 0; $bitid < $this->max_bits; $bitid++){
			$data[$bitid] = $this->get_bit($bitid);
		}
		return $data;
	}

	public function to_assoc(array $keys) : array {
		$data = [];
		for($bitid = 0; $bitid < $this->max_bits; $bitid++){
			if(isset($keys[$bitid])) $data[$keys[$bitid]] = $this->get_bit($bitid);
		}
		return $data;
	}

	public function to_string(bool $full_string = false) : string {
		$string = '';
		for($bitid = 0; $bitid < $this->max_bits; $bitid++){
			$string .= $this->get_bit($bitid) ? '1' : '0';
		}
		$string = strrev($string);
		if(!$full_string){
			$pos = strpos($string, "1", 0);
			if($pos === false){
				return "0";
			} else {
				return substr($string, $pos);
			}
		}
		return $string;
	}

	public function to_json() : string {
		return json_encode($this->to_array());
	}

	public function invert(bool $full_string = false) : void {
		$this->from_string(str_replace("X", "0", str_replace("0", "1", str_replace("1", "X", $this->to_string($full_string)))));
	}

	public function invert_full() : void {
		if($this->max_bits == 64){
			$this->original ^= 0x7FFFFFFFFFFFFFFF;
		} else {
			$this->original ^= 0xFFFFFFFF;
		}
		$this->set_bit($this->max_bits - 1, !$this->get_bit($this->max_bits - 1));
	}

	public function compare_value(int $value, int $mask) : bool {
		return ($value & $mask) == $mask;
	}

	public function compare(int $mask) : bool {
		return $this->compare_value($this->original, $mask);
	}

	public function extract_value(int $value, ?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4) : void {
		$int1 = (int)($value >> 24) & 0xFF;
		$int2 = (int)($value >> 16) & 0xFF;
		$int3 = (int)($value >> 8) & 0xFF;
		$int4 = (int)($value) & 0xFF;
	}

	public function merge_value(int $int1, int $int2, int $int3, int $int4) : int {
		return (int)((($int1 & 0xFF) << 24) | (($int2 & 0xFF) << 16) | (($int3 & 0xFF) << 8) | ($int4 & 0xFF));
	}

	public function extract_value_64(int $value, ?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4, ?int &$int5, ?int &$int6, ?int &$int7, ?int &$int8) : void {
		$int1 = (int)($value >> 56) & 0xFF;
		$int2 = (int)($value >> 48) & 0xFF;
		$int3 = (int)($value >> 40) & 0xFF;
		$int4 = (int)($value >> 32) & 0xFF;
		$int5 = (int)($value >> 24) & 0xFF;
		$int6 = (int)($value >> 16) & 0xFF;
		$int7 = (int)($value >> 8) & 0xFF;
		$int8 = (int)($value) & 0xFF;
	}

	public function merge_value_64(int $int1, int $int2, int $int3, int $int4, int $int5, int $int6, int $int7, int $int8) : int {
		return ((($int1 & 0xFF) << 56) | (($int2 & 0xFF) << 48) | (($int3 & 0xFF) << 40) | (($int4 & 0xFF) << 32) | (($int5 & 0xFF) << 24) | (($int6 & 0xFF) << 16) | (($int7 & 0xFF) << 8) | ($int8 & 0xFF));
	}

	public function extract(?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4) : void {
		$this->extract_value($this->original, $int1, $int2, $int3, $int4);
	}

	public function merge(int $int1, int $int2, int $int3, int $int4) : void {
		$this->original = $this->merge_value($int1, $int2, $int3, $int4);
	}

	public function extract_64(?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4, ?int &$int5, ?int &$int6, ?int &$int7, ?int &$int8) : void {
		$this->extract_value_64($this->original, $int1, $int2, $int3, $int4, $int5, $int6, $int7, $int8);
	}

	public function merge_64(int $int1, int $int2, int $int3, int $int4, int $int5, int $int6, int $int7, int $int8) : void {
		$this->original = $this->merge_value_64($int1, $int2, $int3, $int4, $int5, $int6, $int7, $int8);
	}

	public function bin64_to_float(int $value) : float {
		$sign = ($value >> 63) & 0x1;
		$exponent = ($value >> 52) & 0x7FF;
		$fraction = ($value & 0xFFFFFFFFFFFFF);
		$this->max_bits = 64;
		$this->from_int($fraction);
		$e = 1.0;
		for($i = 1; $i <= 52; $i++) $e += ($this->get_bit(52-$i) ? 1 : 0) * pow(2.0, -$i);
		return pow(-1.0, $sign) * ($e) * pow(2.0, ($exponent-1023));
	}

}

?>