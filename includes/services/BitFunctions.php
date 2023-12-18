<?php

declare(strict_types=1);

namespace App\Services;

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

	public function getMaxBits() : int {
		return $this->max_bits;
	}

	public function getBitValue(int $value, int $bitid) : bool {
		return (bool)(($value >> $bitid) & 0x01);
	}

	public function setBitValue(int &$value, int $bitid, bool $state) : void {
		$value = (($value & ~(0x01 << $bitid)) | ((0x01 << $bitid)*($state ? 1 : 0)));
	}

	public function getBit(int $bitid) : bool {
		return $this->getBitValue($this->original, $bitid);
	}

	public function setBit(int $bitid, bool $state) : void {
		$this->setBitValue($this->original, $bitid, $state);
	}

	public function fromInt(int $int) : void {
		$this->original = $int;
	}

	public function fromArray(array $array) : void {
		$this->original = 0;
		for($bitid = 0; $bitid < $this->max_bits; $bitid++){
			$this->setBit($bitid, ($array[$bitid] ?? false));
		}
	}

	public function fromAssoc(array $assoc, array $keys) : void {
		$this->original = 0;
		foreach($keys as $bitid => $key){
			$this->setBit($bitid, ($assoc[$key] ?? false));
		}
	}

	public function fromString(string $string) : void {
		$this->original = bindec($string);
	}

	public function fromJson(string $json) : void {
		$this->fromArray(json_decode($json, true));
	}

	public function toInt() : int {
		return $this->original;
	}

	public function toArray() : array {
		$data = [];
		for($bitid = 0; $bitid < $this->max_bits; $bitid++){
			$data[$bitid] = $this->getBit($bitid);
		}
		return $data;
	}

	public function toAssoc(array $keys) : array {
		$data = [];
		for($bitid = 0; $bitid < $this->max_bits; $bitid++){
			if(isset($keys[$bitid])) $data[$keys[$bitid]] = $this->getBit($bitid);
		}
		return $data;
	}

	public function toString(bool $full_string = false) : string {
		$string = '';
		for($bitid = 0; $bitid < $this->max_bits; $bitid++){
			$string .= $this->getBit($bitid) ? '1' : '0';
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

	public function toJson() : string {
		return json_encode($this->toArray());
	}

	public function invert(bool $full_string = false) : void {
		$this->fromString(str_replace("X", "0", str_replace("0", "1", str_replace("1", "X", $this->toString($full_string)))));
	}

	public function invertFull() : void {
		if($this->max_bits == 64){
			$this->original ^= 0x7FFFFFFFFFFFFFFF;
		} else {
			$this->original ^= 0xFFFFFFFF;
		}
		$this->setBit($this->max_bits - 1, !$this->getBit($this->max_bits - 1));
	}

	public function compareValue(int $value, int $mask) : bool {
		return ($value & $mask) == $mask;
	}

	public function compare(int $mask) : bool {
		return $this->compareValue($this->original, $mask);
	}

	public function extractValue(int $value, ?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4) : void {
		$int1 = (int)($value >> 24) & 0xFF;
		$int2 = (int)($value >> 16) & 0xFF;
		$int3 = (int)($value >> 8) & 0xFF;
		$int4 = (int)($value) & 0xFF;
	}

	public function mergeValue(int $int1, int $int2, int $int3, int $int4) : int {
		return (int)((($int1 & 0xFF) << 24) | (($int2 & 0xFF) << 16) | (($int3 & 0xFF) << 8) | ($int4 & 0xFF));
	}

	public function extractValue64(int $value, ?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4, ?int &$int5, ?int &$int6, ?int &$int7, ?int &$int8) : void {
		$int1 = (int)($value >> 56) & 0xFF;
		$int2 = (int)($value >> 48) & 0xFF;
		$int3 = (int)($value >> 40) & 0xFF;
		$int4 = (int)($value >> 32) & 0xFF;
		$int5 = (int)($value >> 24) & 0xFF;
		$int6 = (int)($value >> 16) & 0xFF;
		$int7 = (int)($value >> 8) & 0xFF;
		$int8 = (int)($value) & 0xFF;
	}

	public function mergeValue64(int $int1, int $int2, int $int3, int $int4, int $int5, int $int6, int $int7, int $int8) : int {
		return ((($int1 & 0xFF) << 56) | (($int2 & 0xFF) << 48) | (($int3 & 0xFF) << 40) | (($int4 & 0xFF) << 32) | (($int5 & 0xFF) << 24) | (($int6 & 0xFF) << 16) | (($int7 & 0xFF) << 8) | ($int8 & 0xFF));
	}

	public function extract(?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4) : void {
		$this->extractValue($this->original, $int1, $int2, $int3, $int4);
	}

	public function merge(int $int1, int $int2, int $int3, int $int4) : void {
		$this->original = $this->mergeValue($int1, $int2, $int3, $int4);
	}

	public function extract64(?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4, ?int &$int5, ?int &$int6, ?int &$int7, ?int &$int8) : void {
		$this->extractValue64($this->original, $int1, $int2, $int3, $int4, $int5, $int6, $int7, $int8);
	}

	public function merge64(int $int1, int $int2, int $int3, int $int4, int $int5, int $int6, int $int7, int $int8) : void {
		$this->original = $this->mergeValue64($int1, $int2, $int3, $int4, $int5, $int6, $int7, $int8);
	}

	public function Bin64ToFloat(int $value) : float {
		$sign = ($value >> 63) & 0x1;
		$exponent = ($value >> 52) & 0x7FF;
		$fraction = ($value & 0xFFFFFFFFFFFFF);
		$this->max_bits = 64;
		$this->fromInt($fraction);
		$e = 1.0;
		for($i = 1; $i <= 52; $i++) $e += ($this->getBit(52-$i) ? 1 : 0) * pow(2.0, -$i);
		return pow(-1.0, $sign) * ($e) * pow(2.0, ($exponent-1023));
	}

}

?>
