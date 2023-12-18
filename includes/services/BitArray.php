<?php

declare(strict_types=1);

namespace App\Services;

class BitArray {

	protected array $original = [];

	protected int $max_bits;

	protected BitFunctions $bits;

	public function __construct(){
		$this->bits = new BitFunctions();
		$this->max_bits = $this->bits->getMaxBits();
	}

	public function getConfigAddress(int $id) : int {
		return (int)floor(intval($id) / $this->max_bits);
	}

	public function getConfigBit(int $id) : int {
		return (int)intval($id) % $this->max_bits;
	}

	public function getConfigSize(int $max_items) : int {
		return (int)floor(intval($max_items) / $this->max_bits) + 1;
	}

	public function getConfigUsedSize() : int {
		$size = 0;
		foreach($this->original as $key => $value) $size = max($size,$key);
		return (int)($size + 1);
	}

	public function getConfig(int $id) : bool {
		$address = $this->getConfigAddress($id);
		return $this->bits->getBitValue($this->original[$address] ?? 0, $this->getConfigBit($id));
	}

	public function setConfig(int $id, bool $state) : void {
		$address = $this->getConfigAddress($id);
		if(!isset($this->original[$address])) $this->original[$address] = 0;
		$this->bits->setBitValue($this->original[$address], $this->getConfigBit($id), $state);
	}

	public function fromArray(array $array) : void {
		$this->original = $array;
	}

	public function fromAssoc(array $assoc, array $keys) : void {
		$this->original = [];
		foreach($keys as $id => $key){
			$this->setConfig($id,($assoc[$key] ?? false));
		}
	}

	public function fromJson(string $json) : void {
		$this->fromArray(json_decode($json,true));
	}

	public function fromBinary(string $binary, int $length) : void {
		$this->original = [];
		$address = 0;
		for($offset = 0; $offset < $length; $offset += 4){
			$this->original[$address] = $this->bits->mergeValue(ord($binary[$offset] ?? 0),ord($binary[$offset+1] ?? 0),ord($binary[$offset+2] ?? 0),ord($binary[$offset+3] ?? 0));
			$address++;
		}
	}

	public function fromHex(string $hex) : void {
		$this->fromBinary(hex2bin($hex), strlen($hex) * 2);
	}

	public function fromFile(string $path) : bool {
		if(!file_exists($path)) return false;
		$file = fopen($path,"rb");
		if(!$file) return false;
		$length = filesize($path);
		$this->fromBinary(fread($file,$length),$length);
		fclose($file);
		return true;
	}

	public function toArray() : array {
		return $this->original;
	}

	public function toAssoc(array $keys) : array {
		$data = [];
		$id = 0;
		$max_address = $this->getConfigUsedSize();
		for($address = 0; $address < $max_address; $address++){
			for($bitid = 0; $bitid < $this->max_bits; $bitid++){
				if(isset($keys[$id])){
					$data[$keys[$id]] = $this->getConfig($id);
				}
				$id++;
			}
		}
		return $data;
	}

	public function toJson() : string {
		return json_encode($this->toArray());
	}

	public function toBinary(int &$length = 0) : string {
		$data = '';
		$length = 0;
		$int1 = 0;
		$int2 = 0;
		$int3 = 0;
		$int4 = 0;
		$max_address = $this->getConfigUsedSize();
		for($address = 0; $address < $max_address; $address++){
			$this->bits->extractValue($this->original[$address] ?? 0, $int1, $int2, $int3, $int4);
			$data .= chr($int1);
			$data .= chr($int2);
			$data .= chr($int3);
			$data .= chr($int4);
			$length += 4;
		}
		return $data;
	}

	public function toHex() : string {
		return bin2hex($this->toBinary());
	}

	public function toFile(string $path) : bool {
		if(file_exists($path)){
			unlink($path);
			if(file_exists($path)) return false;
		}
		$file = fopen($path,"wb");
		if(!$file) return false;
		$length = 0;
		$data = $this->toBinary($length);
		fwrite($file,$data,$length);
		fclose($file);
		return true;
	}

}

?>
