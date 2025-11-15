<?php

/**
 * NGC-TOOLKIT v2.7.4 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Core;

/**
 * The BitFunctions class provides a set of utility methods for performing bitwise operations
 * on integers, including setting, getting, and converting bit values, and extracting/merging byte values.
 */
class BitFunctions {

	/**
	 * The integer on which bitwise operations are performed.
	 * @var int
	 */
	protected int $original = 0;

	/**
	 * The maximum number of bits to operate on (either 32 or 64).
	 * @var int
	 */
	protected int $max_bits;

	/**
	 * Constructor for the BitFunctions class.
	 *
	 * @param int $max_bits The maximum number of bits to use for operations. Can be 32 or 64. Defaults to 32.
	 */
	public function __construct(int $max_bits = 32){
		if($max_bits == 64){
			$this->max_bits = 64;
		} else {
			$this->max_bits = 32;
		}
	}

	/**
	 * Returns the maximum number of bits currently configured for operations.
	 *
	 * @return int The maximum number of bits (32 or 64).
	 */
	public function get_max_bits() : int {
		return $this->max_bits;
	}

	/**
	 * Gets the boolean value of a specific bit within a given integer.
	 *
	 * @param int $value The integer to read the bit from.
	 * @param int $bit_id The zero-based index of the bit to retrieve (e.g., 0 for the least significant bit).
	 * @return bool True if the bit is set (1), false if it's not set (0).
	 */
	public function get_bit_value(int $value, int $bit_id) : bool {
		return (bool)(($value >> $bit_id) & 1);
	}

	/**
	 * Sets or unsets a specific bit within a given integer by reference.
	 *
	 * @param int $value The integer whose bit will be modified (passed by reference).
	 * @param int $bit_id The zero-based index of the bit to set or unset.
	 * @param bool $state True to set the bit to 1, false to set it to 0.
	 */
	public function set_bit_value(int &$value, int $bit_id, bool $state) : void {
		$value = ($value & ~(1 << $bit_id)) | ((int)$state << $bit_id);
	}

	/**
	 * Gets the boolean value of a specific bit in the internal 'original' integer.
	 *
	 * @param int $bit_id The zero-based index of the bit to retrieve.
	 * @return bool True if the bit is set (1), false if it's not set (0).
	 */
	public function get_bit(int $bit_id) : bool {
		return $this->get_bit_value($this->original, $bit_id);
	}

	/**
	 * Sets or unsets a specific bit in the internal 'original' integer.
	 *
	 * @param int $bit_id The zero-based index of the bit to set or unset.
	 * @param bool $state True to set the bit to 1, false to set it to 0.
	 */
	public function set_bit(int $bit_id, bool $state) : void {
		$this->set_bit_value($this->original, $bit_id, $state);
	}

	/**
	 * Sets the internal 'original' integer to the provided integer value.
	 *
	 * @param int $int The integer value to set.
	 */
	public function from_int(int $int) : void {
		$this->original = $int;
	}

	/**
	 * Sets the internal 'original' integer based on a boolean array representing individual bits.
	 * The array index corresponds to the bit ID.
	 *
	 * @param array $array An array of booleans, where array[bit_id] determines the state of that bit.
	 */
	public function from_array(array $array) : void {
		$this->original = 0;
		for($bit_id = 0; $bit_id < $this->max_bits; $bit_id++){
			$this->set_bit($bit_id, $array[$bit_id] ?? false);
		}
	}

	/**
	 * Sets the internal 'original' integer based on an associative array and a mapping of bit IDs to keys.
	 *
	 * @param array $assoc An associative array containing boolean values.
	 * @param array $keys An array where keys are bit IDs and values are the corresponding keys in $assoc.
	 */
	public function from_assoc(array $assoc, array $keys) : void {
		$this->original = 0;
		foreach($keys as $bit_id => $key){
			$this->set_bit($bit_id, $assoc[$key] ?? false);
		}
	}

	/**
	 * Sets the internal 'original' integer by converting a binary string to an integer.
	 *
	 * @param string $string The binary string (e.g., "10110").
	 */
	public function from_string(string $string) : void {
		$this->original = (int)bindec($string);
	}

	/**
	 * Sets the internal 'original' integer by decoding a JSON string into an array of booleans,
	 * then using that array to set the bits.
	 *
	 * @param string $json The JSON string representing an array of boolean bit values.
	 */
	public function from_json(string $json) : void {
		$this->from_array(\json_decode($json ?? '[]', true) ?? []);
	}

	/**
	 * Returns the current value of the internal 'original' integer.
	 *
	 * @return int The integer representation of the bits.
	 */
	public function to_int() : int {
		return $this->original;
	}

	/**
	 * Converts the internal 'original' integer into an array of boolean bit values.
	 * The array index corresponds to the bit ID.
	 *
	 * @return array An array of booleans, where each element represents the state of a bit.
	 */
	public function to_array() : array {
		$data = [];
		for($bit_id = 0; $bit_id < $this->max_bits; $bit_id++){
			$data[$bit_id] = $this->get_bit($bit_id);
		}
		return $data;
	}

	/**
	 * Converts the internal 'original' integer into an associative array of boolean bit values,
	 * using provided keys for the array elements.
	 *
	 * @param array $keys An array where keys are bit IDs and values are the desired keys in the output associative array.
	 * @return array An associative array of booleans.
	 */
	public function to_assoc(array $keys) : array {
		$data = [];
		for($bit_id = 0; $bit_id < $this->max_bits; $bit_id++){
			if(isset($keys[$bit_id])) $data[$keys[$bit_id]] = $this->get_bit($bit_id);
		}
		return $data;
	}

	/**
	 * Converts the internal 'original' integer into its binary string representation.
	 *
	 * @param bool $full_string Optional. If true, the string will be padded with leading zeros up to max_bits.
	 * If false, leading zeros will be trimmed. Defaults to false.
	 * @return string The binary string representation.
	 */
	public function to_string(bool $full_string = false) : string {
		$string = '';
		for($bit_id = 0; $bit_id < $this->max_bits; $bit_id++){
			$string .= $this->get_bit($bit_id) ? '1' : '0';
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

	/**
	 * Converts the internal 'original' integer's bit representation to a JSON string.
	 *
	 * @return string The JSON string representing an array of boolean bit values.
	 */
	public function to_json() : string {
		return \json_encode($this->to_array());
	}

	/**
	 * Inverts the bits of the value.
	 * - If $full_string is false, only the used bits (from first '1') are inverted.
	 * - If $full_string is true, all bits (including leading zeros) are inverted.
	 *
	 * @param bool $full_string Optional. Passed to to_string() to determine if the string should be full length.
	 */
	public function invert(bool $full_string = false) : void {
		$this->from_string(strtr($this->to_string($full_string), ['0' => '1', '1' => '0']));
	}

	/**
	 * Performs a full bitwise inversion (NOT) on the internal 'original' integer.
	 * It inverts all bits up to $max_bits.
	 */
	public function invert_full() : void {
		$this->original ^= (1 << $this->max_bits) - 1;
	}

	/**
	 * Compares a given integer value against a bitmask.
	 * Returns true if all bits set in the mask are also set in the value.
	 *
	 * @param int $value The integer value to compare.
	 * @param int $mask The bitmask to compare against.
	 * @return bool True if the masked bits match, false otherwise.
	 */
	public function compare_value(int $value, int $mask) : bool {
		return ($value & $mask) == $mask;
	}

	/**
	 * Compares the internal 'original' integer against a bitmask.
	 * Returns true if all bits set in the mask are also set in the 'original' integer.
	 *
	 * @param int $mask The bitmask to compare against.
	 * @return bool True if the masked bits match, false otherwise.
	 */
	public function compare(int $mask) : bool {
		return $this->compare_value($this->original, $mask);
	}

	/**
	 * Extracts four 8-bit integer values from a 32-bit integer.
	 *
	 * @param int $value The 32-bit integer from which to extract values.
	 * @param int|null $int1 Output parameter for the most significant 8 bits.
	 * @param int|null $int2 Output parameter for the next 8 bits.
	 * @param int|null $int3 Output parameter for the next 8 bits.
	 * @param int|null $int4 Output parameter for the least significant 8 bits.
	 */
	public function extract_value(int $value, ?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4) : void {
		$int1 = ($value >> 24) & 0xFF;
		$int2 = ($value >> 16) & 0xFF;
		$int3 = ($value >> 8) & 0xFF;
		$int4 = $value & 0xFF;
	}

	/**
	 * Merges four 8-bit integer values into a single 32-bit integer.
	 *
	 * @param int $int1 The most significant 8-bit integer.
	 * @param int $int2 The next 8-bit integer.
	 * @param int $int3 The next 8-bit integer.
	 * @param int $int4 The least significant 8-bit integer.
	 * @return int The resulting 32-bit integer.
	 */
	public function merge_value(int $int1, int $int2, int $int3, int $int4) : int {
		return (int)((($int1 & 0xFF) << 24) | (($int2 & 0xFF) << 16) | (($int3 & 0xFF) << 8) | ($int4 & 0xFF));
	}

	/**
	 * Extracts eight 8-bit integer values from a 64-bit integer.
	 *
	 * @param int $value The 64-bit integer from which to extract values.
	 * @param int|null $int1 Output parameter for the most significant 8 bits.
	 * @param int|null $int2 Output parameter for the next 8 bits.
	 * @param int|null $int3 Output parameter for the next 8 bits.
	 * @param int|null $int4 Output parameter for the next 8 bits.
	 * @param int|null $int5 Output parameter for the next 8 bits.
	 * @param int|null $int6 Output parameter for the next 8 bits.
	 * @param int|null $int7 Output parameter for the next 8 bits.
	 * @param int|null $int8 Output parameter for the least significant 8 bits.
	 */
	public function extract_value_64(int $value, ?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4, ?int &$int5, ?int &$int6, ?int &$int7, ?int &$int8) : void {
		$int1 = ($value >> 56) & 0xFF;
		$int2 = ($value >> 48) & 0xFF;
		$int3 = ($value >> 40) & 0xFF;
		$int4 = ($value >> 32) & 0xFF;
		$int5 = ($value >> 24) & 0xFF;
		$int6 = ($value >> 16) & 0xFF;
		$int7 = ($value >> 8) & 0xFF;
		$int8 = $value & 0xFF;
	}

	/**
	 * Merges eight 8-bit integer values into a single 64-bit integer.
	 *
	 * @param int $int1 The most significant 8-bit integer.
	 * @param int $int2 The next 8-bit integer.
	 * @param int $int3 The next 8-bit integer.
	 * @param int $int4 The next 8-bit integer.
	 * @param int $int5 The next 8-bit integer.
	 * @param int $int6 The next 8-bit integer.
	 * @param int $int7 The next 8-bit integer.
	 * @param int $int8 The least significant 8-bit integer.
	 * @return int The resulting 64-bit integer.
	 */
	public function merge_value_64(int $int1, int $int2, int $int3, int $int4, int $int5, int $int6, int $int7, int $int8) : int {
		return (($int1 & 0xFF) << 56) | (($int2 & 0xFF) << 48) | (($int3 & 0xFF) << 40) | (($int4 & 0xFF) << 32) | (($int5 & 0xFF) << 24) | (($int6 & 0xFF) << 16) | (($int7 & 0xFF) << 8) | ($int8 & 0xFF);
	}

	/**
	 * Extracts four 8-bit integer values from the internal 'original' 32-bit integer.
	 *
	 * @param int|null $int1 Output parameter for the most significant 8 bits.
	 * @param int|null $int2 Output parameter for the next 8 bits.
	 * @param int|null $int3 Output parameter for the next 8 bits.
	 * @param int|null $int4 Output parameter for the least significant 8 bits.
	 */
	public function extract(?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4) : void {
		$this->extract_value($this->original, $int1, $int2, $int3, $int4);
	}

	/**
	 * Merges four 8-bit integer values into the internal 'original' 32-bit integer.
	 *
	 * @param int $int1 The most significant 8-bit integer.
	 * @param int $int2 The next 8-bit integer.
	 * @param int $int3 The next 8-bit integer.
	 * @param int $int4 The least significant 8-bit integer.
	 */
	public function merge(int $int1, int $int2, int $int3, int $int4) : void {
		$this->original = $this->merge_value($int1, $int2, $int3, $int4);
	}

	/**
	 * Extracts eight 8-bit integer values from the internal 'original' 64-bit integer.
	 *
	 * @param int|null $int1 Output parameter for the most significant 8 bits.
	 * @param int|null $int2 Output parameter for the next 8 bits.
	 * @param int|null $int3 Output parameter for the next 8 bits.
	 * @param int|null $int4 Output parameter for the next 8 bits.
	 * @param int|null $int5 Output parameter for the next 8 bits.
	 * @param int|null $int6 Output parameter for the next 8 bits.
	 * @param int|null $int7 Output parameter for the next 8 bits.
	 * @param int|null $int8 Output parameter for the least significant 8 bits.
	 */
	public function extract_64(?int &$int1, ?int &$int2, ?int &$int3, ?int &$int4, ?int &$int5, ?int &$int6, ?int &$int7, ?int &$int8) : void {
		$this->extract_value_64($this->original, $int1, $int2, $int3, $int4, $int5, $int6, $int7, $int8);
	}

	/**
	 * Merges eight 8-bit integer values into the internal 'original' 64-bit integer.
	 *
	 * @param int $int1 The most significant 8-bit integer.
	 * @param int $int2 The next 8-bit integer.
	 * @param int $int3 The next 8-bit integer.
	 * @param int $int4 The next 8-bit integer.
	 * @param int $int5 The next 8-bit integer.
	 * @param int $int6 The next 8-bit integer.
	 * @param int $int7 The next 8-bit integer.
	 * @param int $int8 The least significant 8-bit integer.
	 */
	public function merge_64(int $int1, int $int2, int $int3, int $int4, int $int5, int $int6, int $int7, int $int8) : void {
		$this->original = $this->merge_value_64($int1, $int2, $int3, $int4, $int5, $int6, $int7, $int8);
	}

	/**
	 * Converts a 64-bit integer representing an IEEE 754 double-precision floating-point number
	 * into its corresponding float value.
	 *
	 * @param int $value The 64-bit integer representation of a float.
	 * @return float The converted double-precision floating-point number.
	 */
	public function bin64_to_float(int $value) : float {
		$max_bits = $this->max_bits;
		$sign = ($value >> 63) & 0x1;
		$exponent = ($value >> 52) & 0x7FF;
		$fraction = $value & 0xFFFFFFFFFFFFF;
		$this->max_bits = 64; // Temporarily set max_bits to 64 for fraction processing
		$this->from_int($fraction);
		$e = 1.0;
		for($i = 1; $i <= 52; $i++) $e += ($this->get_bit(52 - $i) ? 1 : 0) * pow(2.0, -$i);
		$this->max_bits = $max_bits;
		return pow(-1.0, $sign) * $e * pow(2.0, $exponent - 1023);
	}

}

?>