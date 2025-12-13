<?php

/**
 * NGC-TOOLKIT v2.7.5 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Core;

/**
 * The AppBuffer class provides a simple file-based buffering mechanism for storing and retrieving various data types.
 * Data is stored as JSON files, with support for expiration and type handling.
 */
class AppBuffer {

	/**
	 * The base path for storing buffer files.
	 * @var string
	 */
	protected string $path;

	/**
	 * AppBuffer constructor.
	 *
	 * Initializes the AppBuffer with a specified path. If the directory does not exist, it will be created.
	 *
	 * @param string $path The directory path where buffer files will be stored.
	 * @param int $permissions The permissions to set for the created directory, default to 0755.
	 */
	public function __construct(string $path, int $permissions = 0755){
		$this->path = $path;
		if(!\file_exists($this->path)) \mkdir($this->path, $permissions, true);
	}

	/**
	 * Get the base path of the buffer directory.
	 *
	 * @return string The buffer directory path.
	 */
	public function get_path() : string {
		return $this->path;
	}

	/**
	 * Generates the full file path for a given key.
	 * The key is hashed using SHA256 to create a unique filename.
	 *
	 * @param string $key The key for which to generate the file path.
	 * @return string The full path to the buffer file.
	 */
	public function get_file(string $key) : string {
		$key = \hash('sha256', $key);
		return $this->path.DIRECTORY_SEPARATOR."$key.json";
	}

	/**
	 * Retrieves a value from the buffer associated with the given key.
	 *
	 * If the file does not exist, or if the buffer has expired, the default value is returned.
	 * Handles different data types (integer, boolean, string, array, float, null).
	 *
	 * @param string $key The key of the value to retrieve.
	 * @param int|bool|string|array|float|null $default The default value to return if the key is not found or expired.
	 * @return int|bool|string|array|float|null The retrieved value, or the default value if not found or expired.
	 */
	public function get(string $key, int|bool|string|array|float|null $default = null) : int|bool|string|array|float|null {
		$file = $this->get_file($key);
		if(!\file_exists($file)) return $default;
		$buffer = \json_decode(\file_get_contents($file), true);
		if(!isset($buffer['expire']) || !isset($buffer['type'])) return $default;
		if($buffer['expire'] != -1 && \time() > $buffer['expire']) return $default;
		switch(\strtolower($buffer['type'])){
			case 'integer': return (int)$buffer['value'];
			case 'boolean': return $buffer['value'] == 'true';
			case 'string': return $buffer['value'];
			case 'array': return \json_decode(\base64_decode($buffer['value']), true);
			case 'float': (float)$buffer['value'];
			case 'null': return null;
		}
		return null;
	}

	/**
	 * Stores a value in the buffer associated with the given key.
	 *
	 * The value can be set with an optional expiration time.
	 * Different data types are handled and converted for storage.
	 *
	 * @param string $key The key under which to store the value.
	 * @param int|bool|string|array|float|null $value The value to store.
	 * @param int $expire The expiration time in seconds. Use -1 for no expiration (default).
	 */
	public function set(string $key, int|bool|string|array|float|null $value, int $expire = -1) : void {
		$file = $this->get_file($key);
		if($expire > 0) $expire = \time() + $expire;
		$type = \strtolower(\gettype($value));
		switch($type){
			case 'float':
			case 'integer': {
				$value = \strval($value);
				break;
			}
			case 'boolean': {
				$value = $value ? 'true' : 'false';
				break;
			}
			case 'array': {
				$value = \base64_encode(\json_encode($value));
				break;
			}
			case 'null': {
				$value = 'null';
				break;
			}
		}
		\file_put_contents($file, \json_encode([
			'key' => $key,
			'type' => $type,
			'value' => $value,
			'expire' => $expire,
		]));
	}

	/**
	 * Removes a specific key-value pair from the buffer.
	 *
	 * @param string $key The key of the value to remove.
	 */
	public function forget(string $key) : void {
		$this->delete($this->get_file($key));
	}

	/**
	 * Clears all expired entries from the buffer.
	 * It iterates through all buffer files and deletes those that have passed their expiration time.
	 */
	public function clear_expired() : void {
		$files = \scandir($this->path);
		foreach($files as $file){
			if($file == '..' || $file == '.') continue;
			$buffer = \json_decode(\file_get_contents($this->path.DIRECTORY_SEPARATOR.$file), true);
			if($buffer['expire'] != -1 && \time() > $buffer['expire']){
				$this->delete($this->path.DIRECTORY_SEPARATOR.$file);
			}
		}
	}

	/**
	 * Clears all entries from the buffer, regardless of their expiration status.
	 * Deletes all files within the buffer directory.
	 */
	public function clear() : void {
		$files = \scandir($this->path);
		foreach($files as $file){
			$this->delete($this->path.DIRECTORY_SEPARATOR.$file);
		}
	}

	/**
	 * Deletes a file from the file system.
	 *
	 * @param string $path The full path to the file to delete.
	 * @return bool True on success, false on failure.
	 */
	private function delete(string $path) : bool {
		return @\unlink($path);
	}

}

?>