<?php

/**
 * NGC-TOOLKIT v2.7.2 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Core;

/**
 * The BitArray class provides a way to manage a dynamic array of bits,
 * storing them efficiently as integers. It allows setting and getting individual bits
 * as well as converting between different data representations.
 */
class BitArray {

	/**
	 * An array of integers where each integer stores a block of bits.
	 * @var array
	 */
	protected array $original = [];

	/**
	 * The number of bits stored in a single integer (e.g., 32 or 64).
	 * @var int
	 */
	protected int $max_bits;

	/**
	 * An instance of the BitFunctions class for bitwise operations.
	 * @var BitFunctions
	 */
	protected BitFunctions $bits;

	/**
	 * Constructor for the BitArray class.
	 * Initializes a BitFunctions instance and sets the maximum bits per integer.
	 */
	public function __construct(){
		$this->bits = new BitFunctions(32);
		$this->max_bits = $this->bits->get_max_bits();
	}

	/**
	 * Calculates the array address (index) where a given bit ID is stored.
	 *
	 * @param int $id The zero-based global ID of the bit.
	 * @return int The integer index in the internal array.
	 */
	public function get_config_address(int $id) : int {
		return (int)floor(intval($id) / $this->max_bits);
	}

	/**
	 * Calculates the bit position within the integer at its corresponding array address.
	 *
	 * @param int $id The zero-based global ID of the bit.
	 * @return int The zero-based bit position within the integer.
	 */
	public function get_config_bit(int $id) : int {
		return (int)intval($id) % $this->max_bits;
	}

	/**
	 * Calculates the minimum required size of the internal array to store a given number of items (bits).
	 *
	 * @param int $max_items The maximum number of bits to be stored.
	 * @return int The required size of the internal integer array.
	 */
	public function get_config_size(int $max_items) : int {
		return (int)floor(intval($max_items) / $this->max_bits) + 1;
	}

	/**
	 * Returns the effective size of the internal array based on the highest used index.
	 *
	 * @return int The number of integers currently used in the internal array.
	 */
	public function get_config_used_size() : int {
		$size = 0;
		foreach($this->original as $key => $value) $size = max($size, $key);
		return (int)($size + 1);
	}

	/**
	 * Gets the boolean value of a specific bit by its global ID.
	 *
	 * @param int $id The zero-based global ID of the bit to retrieve.
	 * @return bool True if the bit is set (1), false if it's not set (0).
	 */
	public function get_config(int $id) : bool {
		$address = $this->get_config_address($id);
		return $this->bits->get_bit_value($this->original[$address] ?? 0, $this->get_config_bit($id));
	}

	/**
	 * Sets or unsets a specific bit by its global ID.
	 *
	 * @param int $id The zero-based global ID of the bit to set or unset.
	 * @param bool $state True to set the bit to 1, false to set it to 0.
	 */
	public function set_config(int $id, bool $state) : void {
		$address = $this->get_config_address($id);
		if(!isset($this->original[$address])) $this->original[$address] = 0;
		$this->bits->set_bit_value($this->original[$address], $this->get_config_bit($id), $state);
	}

	/**
	 * Sets the internal bit array directly from a provided array of integers.
	 *
	 * @param array $array An array of integers, where each integer represents a block of bits.
	 */
	public function from_array(array $array) : void {
		$this->original = $array;
	}

	/**
	 * Populates the bit array from an associative array using a key mapping.
	 * Each boolean value in the associative array, mapped by `keys`, sets the corresponding bit.
	 *
	 * @param array $assoc An associative array containing boolean values.
	 * @param array $keys An array where keys are global bit IDs and values are the corresponding keys in $assoc.
	 */
	public function from_assoc(array $assoc, array $keys) : void {
		$this->original = [];
		foreach($keys as $id => $key){
			$this->set_config($id, ($assoc[$key] ?? false));
		}
	}

	/**
	 * Populates the bit array by decoding a JSON string into an array of integers.
	 *
	 * @param string $json The JSON string representing an array of integers.
	 */
	public function from_json(string $json) : void {
		$this->from_array(json_decode($json, true));
	}

	/**
	 * Populates the bit array from a binary string. Each 4 bytes are merged into an integer.
	 *
	 * @param string $binary The binary string.
	 * @param int $length The length of the binary string to process.
	 */
	public function from_binary(string $binary, int $length) : void {
		$this->original = [];
		$address = 0;
		for($offset = 0; $offset < $length; $offset += 4){
			$this->original[$address] = $this->bits->merge_value(ord($binary[$offset] ?? 0), ord($binary[$offset + 1] ?? 0), ord($binary[$offset + 2] ?? 0), ord($binary[$offset + 3] ?? 0));
			$address++;
		}
	}

	/**
	 * Populates the bit array from a hexadecimal string by converting it to binary first.
	 *
	 * @param string $hex The hexadecimal string.
	 */
	public function from_hex(string $hex) : void {
		$this->from_binary(hex2bin($hex), strlen($hex) * 2);
	}

	/**
	 * Populates the bit array from the contents of a binary file.
	 *
	 * @param string $path The path to the binary file.
	 * @return bool True on success, false on failure (e.g., file not found or not readable).
	 */
	public function from_file(string $path) : bool {
		if(!file_exists($path)) return false;
		$file = fopen($path, "rb");
		if(!$file) return false;
		$length = filesize($path);
		$this->from_binary(fread($file, $length), $length);
		fclose($file);
		return true;
	}

	/**
	 * Returns the internal array of integers representing the bit array.
	 *
	 * @return array The array of integers.
	 */
	public function to_array() : array {
		return $this->original;
	}

	/**
	 * Converts the bit array into an associative array of boolean values, using a key mapping.
	 *
	 * @param array $keys An array where keys are global bit IDs and values are the desired keys in the output associative array.
	 * @return array An associative array of booleans.
	 */
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

	/**
	 * Converts the internal bit array into a JSON string.
	 *
	 * @return string The JSON string representing the array of integers.
	 */
	public function to_json() : string {
		return json_encode($this->to_array());
	}

	/**
	 * Converts the internal bit array into a binary string.
	 * Each integer from the internal array is broken down into 4 bytes.
	 *
	 * @param int $length Output parameter: The length of the generated binary string in bytes.
	 * @return string The binary string representation of the bit array.
	 */
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
			$data .= chr($int1).chr($int2).chr($int3).chr($int4);
			$length += 4;
		}
		return $data;
	}

	/**
	 * Converts the internal bit array into a hexadecimal string.
	 *
	 * @return string The hexadecimal string representation of the bit array.
	 */
	public function to_hex() : string {
		return bin2hex($this->to_binary());
	}

	/**
	 * Writes the binary representation of the bit array to a file.
	 *
	 * @param string $path The path to the file where the data will be written.
	 * @return bool True on success, false on failure (e.g., file cannot be created or written).
	 */
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