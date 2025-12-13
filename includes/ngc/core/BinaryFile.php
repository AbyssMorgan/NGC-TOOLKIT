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
 * The BinaryFile class provides a functions for handling binary files.
 * This class provides methods to create, open, read, write, and manage binary files.
 */
class BinaryFile {

	/**
	 * The file pointer resource.
	 * @var resource|null
	 */
	private mixed $file = null;

	/**
	 * The path to the binary file.
	 * @var string|null
	 */
	private ?string $path = null;

	/**
	 * BinaryFile constructor.
	 *
	 * Initializes a new BinaryFile instance. If a path is provided, it attempts to open the file.
	 *
	 * @param string|null $path The path to the binary file.
	 * @param int|null $allocate The initial size to allocate for the file in bytes.
	 */
	public function __construct(?string $path = null, ?int $allocate = null){
		$this->path = $path;
		if(!\is_null($path)) $this->open($path, $allocate);
	}

	/**
	 * Creates a new binary file.
	 *
	 * If the file already exists, it returns false. It attempts to create the directory if it doesn't exist.
	 *
	 * @param string $path The path to the new binary file.
	 * @param int|null $allocate The initial size to allocate for the file in bytes.
	 * @param int $permissions The permissions for the newly created directory (if applicable). Default is 0755.
	 * @return bool True on success, false on failure.
	 */
	public function create(string $path, ?int $allocate = null, int $permissions = 0755) : bool {
		if(\file_exists($path)) return false;
		$folder = \pathinfo($path, PATHINFO_DIRNAME);
		if(!\file_exists($folder) && !@\mkdir($folder, $permissions, true)) return false;
		$file = @\fopen($path, "wb");
		if(!$file) return false;
		if(!\is_null($allocate) && $allocate > 0){
			\fseek($file, $allocate - 1);
			\fwrite($file, "\0");
		} else {
			\fwrite($file, "");
		}
		\fclose($file);
		return \file_exists($path);
	}

	/**
	 * Opens an existing binary file or creates it if it doesn't exist.
	 *
	 * If a file is already open, it returns false.
	 *
	 * @param string $path The path to the binary file to open.
	 * @param int|null $allocate The initial size to allocate for the file in bytes if it needs to be created.
	 * @param int $permissions The permissions for the newly created directory (if applicable). Default is 0755.
	 * @return bool True on success, false on failure.
	 */
	public function open(string $path, ?int $allocate = null, int $permissions = 0755) : bool {
		if(!\is_null($this->file)) return false;
		if(!\file_exists($path) && !$this->create($path, $allocate, $permissions)) return false;
		$this->file = \fopen($path, "r+b");
		if(!$this->file) return false;
		$this->path = $path;
		return true;
	}

	/**
	 * Closes the currently open binary file.
	 *
	 * @return bool True on success, false if no file is open.
	 */
	public function close() : bool {
		if(\is_null($this->file)) return false;
		\fclose($this->file);
		$this->file = null;
		$this->path = null;
		return true;
	}

	/**
	 * Reads data from the binary file.
	 *
	 * @param int $offset The offset from the beginning of the file to start reading. Default is 0.
	 * @param int|null $length The number of bytes to read. If null, it reads until the end of the file.
	 * @return string|false The read data as a string, or false on failure or if no file is open.
	 */
	public function read(int $offset = 0, ?int $length = null) : string|false {
		if(\is_null($this->file)) return false;
		\clearstatcache(true, $this->path);
		\fseek($this->file, $offset);
		if(\is_null($length)) $length = \filesize($this->path) - $offset;
		if($length <= 0) return "";
		return \fread($this->file, $length);
	}

	/**
	 * Writes data to the binary file.
	 *
	 * @param string $data The data to write.
	 * @param int $offset The offset from the beginning of the file to start writing. Default is 0.
	 * @param int|null $length The maximum number of bytes to write. If null, the entire data string is written.
	 * @return int|false The number of bytes written, or false on failure or if no file is open.
	 */
	public function write(string $data, int $offset = 0, ?int $length = null) : int|false {
		if(\is_null($this->file)) return false;
		\fseek($this->file, $offset);
		return \fwrite($this->file, $data, $length);
	}

	/**
	 * Returns the size of the binary file in bytes.
	 *
	 * @return int|false The size of the file in bytes, or false on failure or if no file is open.
	 */
	public function size() : int|false {
		if(\is_null($this->file)) return false;
		return \filesize($this->path);
	}

	/**
	 * Truncates the file to a specified size.
	 *
	 * @param int $size The desired size of the file in bytes.
	 * @return bool True on success, false on failure or if no file is open.
	 */
	public function truncate(int $size) : bool {
		if(\is_null($this->file)) return false;
		return \ftruncate($this->file, $size);
	}

}

?>