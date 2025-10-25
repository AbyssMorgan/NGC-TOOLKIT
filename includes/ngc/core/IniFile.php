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

use Exception;

/**
 * A class for reading, writing, and managing INI files.
 */
class IniFile {

	/**
	 * The path to the INI file.
	 * @var string|null
	 */
	protected ?string $path = null;

	/**
	 * The current data loaded from the INI file.
	 * @var array
	 */
	protected array $data = [];

	/**
	 * The original data loaded from the INI file (before any modifications).
	 * @var array
	 */
	protected array $original = [];

	/**
	 * Indicates whether the INI file is valid and successfully loaded.
	 * @var bool
	 */
	protected bool $valid = false;

	/**
	 * Indicates whether the data should be sorted when saving.
	 * @var bool
	 */
	protected bool $sort = false;

	/**
	 * Indicates whether the INI file content is compressed.
	 * @var bool
	 */
	protected bool $compressed = false;

	/**
	 * An optional encoder object for encryption/decryption.
	 * @var object|null
	 */
	protected ?object $encoder = null;

	/**
	 * The permissions for creating new directories.
	 * @var int
	 */
	protected int $permissions = 0755;

	/**
	 * IniFile constructor.
	 *
	 * @param string|null $path The path to the INI file.
	 * @param bool $sort Whether to sort the data when saving.
	 * @param bool $compressed Whether the INI file content is compressed.
	 * @param object|null $encoder An optional encoder object with encrypt, decrypt, and get_header methods.
	 * @param int $permissions The permissions for creating new directories.
	 */
	public function __construct(?string $path = null, bool $sort = false, bool $compressed = false, ?object $encoder = null, int $permissions = 0755){
		if(!is_null($path)){
			$this->open($path, $sort, $compressed, $encoder, $permissions);
		}
	}

	/**
	 * Creates the INI file and its parent directories if they don't exist.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function create() : bool {
		$folder = pathinfo($this->path, PATHINFO_DIRNAME);
		if(!file_exists($folder)) mkdir($folder, $this->permissions, true);
		$file = fopen($this->path, "w");
		if(!$file) return false;
		fwrite($file, "");
		fclose($file);
		return file_exists($this->path);
	}

	/**
	 * Opens and reads the INI file.
	 *
	 * @param string $path The path to the INI file.
	 * @param bool $sort Whether to sort the data when saving.
	 * @param bool $compressed Whether the INI file content is compressed.
	 * @param object|null $encoder An optional encoder object with encrypt, decrypt, and get_header methods.
	 * @param int $permissions The permissions for creating new directories.
	 * @return bool True on success, false on failure.
	 */
	public function open(string $path, bool $sort = false, bool $compressed = false, ?object $encoder = null, int $permissions = 0755) : bool {
		$this->path = $path;
		$this->sort = $sort;
		$this->compressed = $compressed;
		$this->permissions = $permissions;
		if(!is_null($encoder)){
			if(method_exists($encoder, 'encrypt') && method_exists($encoder, 'decrypt') && method_exists($encoder, 'get_header')){
				$this->encoder = $encoder;
			}
		}
		$this->valid = $this->read();
		return $this->valid;
	}

	/**
	 * Closes the INI file, clearing its internal state.
	 *
	 * @return void
	 */
	public function close() : void {
		$this->valid = false;
		$this->path = null;
		$this->data = [];
		$this->original = [];
		$this->compressed = false;
	}

	/**
	 * Reads the content of the INI file and populates the internal data array.
	 *
	 * @return bool True on success, false on failure.
	 */
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

	/**
	 * Parses a single line from the INI file.
	 *
	 * @param string $line The line to parse.
	 * @param string $key The extracted key (passed by reference).
	 * @param int|bool|string|array|float|null $data The extracted data (passed by reference).
	 * @param bool $escape Whether to remove newline characters and BOM.
	 * @return bool True if the line was successfully parsed, false otherwise.
	 */
	public function parse_line(string $line, &$key, int|bool|string|array|float|null &$data, bool $escape = true) : bool {
		if($escape) $line = str_replace(["\n", "\r", "\xEF\xBB\xBF"], "", $line);
		if(strlen($line) == 0 || $line[0] == '#' || $line[0] == ';' || $line[0] == '[') return false;
		$option = explode("=", $line, 2);
		if(!empty(trim($option[0]))){
			$key = trim($option[0]);
			if(!isset($option[1])){
				$data = null;
			} elseif(is_numeric($option[1])){
				if(strpos($option[1], ".") !== false){
					$data = floatval($option[1]);
				} else {
					$data = intval($option[1]);
				}
			} elseif(empty($option[1])){
				$data = "";
			} elseif($option[1] == 'false'){
				$data = false;
			} elseif($option[1] == 'true'){
				$data = true;
			} elseif($option[1] == 'null'){
				$data = null;
			} else {
				if(substr($option[1], 0, 1) == '"' && substr($option[1], -1, 1) == '"'){
					$data = stripslashes(substr($option[1], 1, -1));
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

	/**
	 * Checks if any data in the INI file has been changed compared to the original loaded data.
	 *
	 * @return bool True if changes exist, false otherwise.
	 */
	public function is_changed() : bool {
		return (json_encode($this->get_original_all()) != json_encode($this->get_all()));
	}

	/**
	 * Checks if a specific value has been changed compared to its original loaded value.
	 *
	 * @param string $key The key of the value to check.
	 * @return bool True if the value has changed, false otherwise.
	 */
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

	/**
	 * Returns all original data loaded from the INI file.
	 *
	 * @return array The original data.
	 */
	public function get_original_all() : array {
		return $this->original;
	}

	/**
	 * Returns all current data.
	 *
	 * @return array The current data.
	 */
	public function get_all() : array {
		return $this->data;
	}

	/**
	 * Returns all data sorted by key.
	 *
	 * @return array The sorted data.
	 */
	public function get_sorted() : array {
		$data = $this->data;
		ksort($data);
		return $data;
	}

	/**
	 * Sorts the internal data array by key.
	 *
	 * @return void
	 */
	public function sort() : void {
		ksort($this->data);
	}

	/**
	 * Sets all data in the INI file.
	 *
	 * @param array $data The data to set.
	 * @param bool $save Whether to save the changes immediately.
	 * @return void
	 */
	public function set_all(array $data, bool $save = false) : void {
		$this->data = $data;
		if($save) $this->save();
	}

	/**
	 * Updates the INI file with new data, merging with existing data.
	 *
	 * @param array $data The data to update.
	 * @param bool $save Whether to save the changes immediately.
	 * @return void
	 */
	public function update(array $data, bool $save = false) : void {
		foreach($data as $key => $value){
			$this->set($key, $value);
		}
		if($save) $this->save();
	}

	/**
	 * Retrieves a value from the INI file by its key.
	 *
	 * @param string $key The key of the value to retrieve.
	 * @param int|bool|string|array|float|null $default The default value to return if the key does not exist.
	 * @return int|bool|string|array|float|null The retrieved value or the default value.
	 */
	public function get(string $key, int|bool|string|array|float|null $default = null) : int|bool|string|array|float|null {
		return $this->data[$key] ?? $default;
	}

	/**
	 * Retrieves a value from the INI file as a string.
	 *
	 * @param string $key The key of the value to retrieve.
	 * @param int|bool|string|array|float|null $default The default value to return if the key does not exist.
	 * @return string The retrieved value as a string.
	 */
	public function get_string(string $key, int|bool|string|array|float|null $default = null) : string {
		return strval($this->data[$key] ?? $default);
	}

	/**
	 * Retrieves the original value from the INI file by its key (before any modifications).
	 *
	 * @param string $key The key of the value to retrieve.
	 * @param int|bool|string|array|float|null $default The default value to return if the key does not exist.
	 * @return int|bool|string|array|float|null The retrieved original value or the default value.
	 */
	public function get_original(string $key, int|bool|string|array|float|null $default = null) : int|bool|string|array|float|null {
		return $this->original[$key] ?? $default;
	}

	/**
	 * Sets a value in the INI file.
	 *
	 * @param string $key The key to set.
	 * @param int|bool|string|array|float|null $value The value to set.
	 * @return void
	 */
	public function set(string $key, int|bool|string|array|float|null $value) : void {
		$this->data[$key] = $this->clean_value($value);
	}

	/**
	 * Cleans and normalizes a value before setting it.
	 * Converts string "true" to boolean true, and string "false" to boolean false.
	 *
	 * @param int|bool|string|array|float|null $value The value to clean.
	 * @return int|bool|string|array|float|null The cleaned value.
	 */
	public function clean_value(int|bool|string|array|float|null $value) : int|bool|string|array|float|null {
		if(gettype($value) == 'string'){
			$lvalue = mb_strtolower($value);
			if($lvalue == 'true'){
				return true;
			} elseif($lvalue == 'false'){
				return false;
			}
		}
		return $value;
	}

	/**
	 * Renames a key in the INI file.
	 *
	 * @param string $key1 The old key name.
	 * @param string $key2 The new key name.
	 * @return void
	 */
	public function rename(string $key1, string $key2) : void {
		$this->set($key2, $this->get($key1));
		$this->unset($key1);
	}

	/**
	 * Unsets (removes) one or more keys from the INI file.
	 *
	 * @param string|array $keys The key or an array of keys to unset.
	 * @return void
	 */
	public function unset(string|array $keys) : void {
		if(gettype($keys) == 'string') $keys = [$keys];
		foreach($keys as $key){
			if($this->is_set($key)){
				unset($this->data[$key]);
			}
		}
	}

	/**
	 * Resets the value of one or more keys to a specified default value (or null if not provided).
	 *
	 * @param string|array $keys The key or an array of keys to reset.
	 * @param int|bool|string|array|float|null $value The value to reset to. Defaults to null.
	 * @return void
	 */
	public function reset(string|array $keys, int|bool|string|array|float|null $value = null) : void {
		if(gettype($keys) == 'string') $keys = [$keys];
		foreach($keys as $key){
			if($this->is_set($key)){
				$this->set($key, $value);
			}
		}
	}

	/**
	 * Saves the current data to the INI file.
	 *
	 * @param bool $as_output Whether to return the content as a string instead of saving to file.
	 * @return string|bool The content as a string if $as_output is true, otherwise true on success or false on failure.
	 */
	public function save(bool $as_output = false) : string|bool {
		if(!$this->is_valid() && !$as_output) return false;
		if($this->sort) ksort($this->data);
		$content = "\xEF\xBB\xBF"; // BOM for UTF-8
		foreach($this->data as $key => $value){
			if(is_numeric($value)){
				$content .= "$key=$value\r\n";
			} elseif(is_null($value)){
				$content .= "$key=null\r\n";
			} elseif(is_bool($value)){
				$value = $value ? 'true' : 'false';
				$content .= "$key=$value\r\n";
			} elseif(empty($value) && !is_array($value)){
				$content .= "$key=\"\"\r\n";
			} elseif(is_array($value)){
				$value = "JSON:".base64_encode(json_encode($value));
				$content .= "$key=\"$value\"\r\n";
			} else {
				$value = addslashes($value);
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
			if($as_output){
				return $content;
			} else {
				file_put_contents($this->path, $content);
			}
		}
		catch(Exception $e){
			return false;
		}
		return true;
	}

	/**
	 * Returns the current INI file content as a string.
	 *
	 * @return string|bool The content as a string, or false on failure.
	 */
	public function get_output() : string|bool {
		return $this->save(true);
	}

	/**
	 * Checks if the INI file is currently valid (successfully opened and read).
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid() : bool {
		return $this->valid;
	}

	/**
	 * Toggles the sorting behavior when saving the INI file.
	 *
	 * @param bool $sort True to enable sorting, false to disable.
	 * @return void
	 */
	public function toggle_sort(bool $sort) : void {
		$this->sort = $sort;
	}

	/**
	 * Returns an array of all keys in the INI file.
	 *
	 * @return array An array of keys.
	 */
	public function keys() : array {
		return array_keys($this->data);
	}

	/**
	 * Checks if a specific key exists in the INI file.
	 *
	 * @param string $key The key to check.
	 * @return bool True if the key exists, false otherwise.
	 */
	public function is_set(string $key) : bool {
		return array_key_exists($key, $this->data);
	}

	/**
	 * Returns the size of the INI file in bytes.
	 *
	 * @return int The file size, or 0 if the file is not valid or doesn't exist.
	 */
	public function get_size() : int {
		if(!$this->is_valid()) return 0;
		$size = filesize($this->path);
		if(!$size) return 0;
		return $size;
	}

	/**
	 * Returns the last modification date of the INI file.
	 *
	 * @return string The modification date in "Y-m-d H:i:s" format, or '0000-00-00 00:00:00' if not valid.
	 */
	public function get_modification_date() : string {
		if(!$this->is_valid()) return '0000-00-00 00:00:00';
		return date("Y-m-d H:i:s", filemtime($this->path));
	}

	/**
	 * Converts the INI file data to a JSON string.
	 *
	 * @return string|false The JSON string, or false on failure.
	 */
	public function to_json() : string|false {
		return json_encode($this->data);
	}

	/**
	 * Loads data into the INI file from a JSON string.
	 *
	 * @param string $json The JSON string.
	 * @param bool $merge Whether to merge with existing data or overwrite.
	 * @param bool $save Whether to save the changes immediately.
	 * @return void
	 */
	public function from_json(string $json, bool $merge = false, bool $save = false) : void {
		if($merge){
			$this->update(json_decode($json, true), $save);
		} else {
			$this->set_all(json_decode($json, true), $save);
		}
	}

	/**
	 * Loads data into the INI file from an associative array.
	 *
	 * @param array $assoc The associative array.
	 * @param bool $merge Whether to merge with existing data or overwrite.
	 * @param bool $save Whether to save the changes immediately.
	 * @return void
	 */
	public function from_assoc(array $assoc, bool $merge = false, bool $save = false) : void {
		if(!$merge) $this->data = [];
		foreach($assoc as $key => $value){
			$this->set($key, $value);
		}
		if($save) $this->save();
	}

	/**
	 * Searches for keys containing a specific substring.
	 *
	 * @param string $search The substring to search for.
	 * @return array An array of keys that contain the search string.
	 */
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

	/**
	 * Searches for keys that start with a specific prefix.
	 *
	 * @param string $search The prefix to search for.
	 * @return array An array of keys that start with the search string.
	 */
	public function search_prefix(string $search) : array {
		$results = [];
		$keys = $this->keys();
		foreach($keys as $key){
			if(str_starts_with($key, $search) !== false){
				array_push($results, $key);
			}
		}
		return $results;
	}

	/**
	 * Searches for keys that end with a specific suffix.
	 *
	 * @param string $search The suffix to search for.
	 * @return array An array of keys that end with the search string.
	 */
	public function search_suffix(string $search) : array {
		$results = [];
		$keys = $this->keys();
		foreach($keys as $key){
			if(str_ends_with($key, $search) !== false){
				array_push($results, $key);
			}
		}
		return $results;
	}

	/**
	 * Sets a value if it's different from the default, otherwise unsets the key.
	 * This is useful for saving only changed values.
	 *
	 * @param string $key The key to set or unset.
	 * @param int|bool|string|array|float|null $value The value to set.
	 * @param int|bool|string|array|float|null $default The default value to compare against.
	 * @return void
	 */
	public function set_changed(string $key, int|bool|string|array|float|null $value, int|bool|string|array|float|null $default = null) : void {
		if($this->clean_value($value) != $default){
			$this->set($key, $value);
		} else {
			$this->unset($key);
		}
	}

	/**
	 * Returns an associative array containing only the specified keys and their values.
	 *
	 * @param string|array $keys The key or an array of keys to include.
	 * @return array An associative array of the selected keys and their values.
	 */
	public function only(string|array $keys) : array {
		if(gettype($keys) == 'string') $keys = [$keys];
		$data = [];
		foreach($keys as $key){
			$data[$key] = $this->get($key);
		}
		return $data;
	}

	/**
	 * Returns an associative array containing all keys and their values except for the specified ones.
	 *
	 * @param string|array $keys The key or an array of keys to exclude.
	 * @return array An associative array of all keys and their values except the excluded ones.
	 */
	public function all_except(string|array $keys) : array {
		if(gettype($keys) == 'string') $keys = [$keys];
		$data = [];
		foreach($this->keys() as $key){
			if(!in_array($key, $keys)) $data[$key] = $this->get($key);
		}
		return $data;
	}

	/**
	 * Extracts a value from the INI file based on a path-like key and sets it into a nested array structure.
	 *
	 * @param array $data The array to populate with the nested value (passed by reference).
	 * @param string $key The key from the INI file (can contain delimiters for nested structure).
	 * @param string $delimiter The delimiter used in the key to represent nesting.
	 * @return void
	 */
	public function extract_path(array &$data, string $key, string $delimiter = '/') : void {
		$this->set_nested_array_value($data, $key, $this->get($key), $delimiter);
	}

	/**
	 * Sets a value in a nested array structure based on a path and delimiter.
	 *
	 * @param array $array The array to modify (passed by reference).
	 * @param string $path The path to the desired element in the array (e.g., "level1/level2/key").
	 * @param mixed $value The value to set.
	 * @param string $delimiter The delimiter used in the path to represent nesting.
	 * @return void
	 */
	public function set_nested_array_value(array &$array, string $path, array $value, string $delimiter = '/') : void {
		$path_parts = explode($delimiter, $path);
		$current = &$array;
		foreach($path_parts as $key) $current = &$current[$key];
		$current = $value;
	}

}

?>