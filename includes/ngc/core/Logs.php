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
 * The Logs class provides a simple and flexible way to write log messages to a file.
 * It supports automatic timestamping, custom date formats, and control over file handling (e.g., keeping file open).
 */
class Logs {

	/**
	 * The full path to the log file.
	 * @var string
	 */
	private string $path;

	/**
	 * Flag to determine if a timestamp should be prepended to each log entry.
	 * @var bool
	 */
	private bool $timestamp;

	/**
	 * Flag to determine if the log file handle should be kept open between writes.
	 * @var bool
	 */
	private bool $hold_open;

	/**
	 * The format string for the timestamp (e.g., 'Y-m-d H:i:s').
	 * @var string
	 */
	private string $date_format;

	/**
	 * The file permissions (octal) for newly created log files and directories.
	 * @var int
	 */
	private int $permissions;

	/**
	 * The file pointer resource for the log file, or false if not open.
	 * @var resource|false
	 */
	private mixed $file;

	/**
	 * Constructor for the Logs class.
	 *
	 * @param string $path The full path to the log file.
	 * @param bool $timestamp Optional. Whether to prepend a timestamp to each log entry. Defaults to true.
	 * @param bool $hold_open Optional. Whether to keep the file handle open between writes. Defaults to false.
	 * @param string $date_format Optional. The date format string for timestamps. Defaults to 'Y-m-d H:i:s'.
	 * @param int $permissions Optional. The file permissions (octal) for log files and directories. Defaults to 0755.
	 */
	public function __construct(string $path, bool $timestamp = true, bool $hold_open = false, string $date_format = 'Y-m-d H:i:s', int $permissions = 0755){
		$this->path = $path;
		$this->timestamp = $timestamp;
		$this->hold_open = $hold_open;
		$this->date_format = $date_format;
		$this->permissions = $permissions;
		$this->file = false;
	}

	/**
	 * Creates the log file and its parent directories if they do not exist.
	 *
	 * @return bool True if the file and directory structure were successfully created or already exist, false otherwise.
	 */
	protected function create() : bool {
		$folder = \pathinfo($this->path, PATHINFO_DIRNAME);
		if(!\file_exists($folder)) \mkdir($folder, $this->permissions, true);
		$file = \fopen($this->path, "w");
		if(!$file) return false;
		\fwrite($file, "\xEF\xBB\xBF");
		\fclose($file);
		return \file_exists($this->path);
	}

	/**
	 * Writes a single string line to the log file.
	 *
	 * @param string $line The string content to write.
	 * @return bool True on successful write, false on failure.
	 */
	protected function write_string(string $line) : bool {
		if(!$this->file) $this->file = \fopen($this->path, "a");
		if(!$this->file) return false;
		if($this->timestamp){
			\fwrite($this->file, "[".$this->get_timestamp()."] ");
		}
		\fwrite($this->file, \mb_convert_encoding("$line\r\n", 'UTF-8', 'auto'));
		if(!$this->hold_open) $this->close();
		return true;
	}

	/**
	 * Writes multiple string lines from an array to the log file.
	 *
	 * @param array $lines An array of strings, where each string is a log line.
	 * @return bool True on successful write, false on failure.
	 */
	protected function write_array(array $lines) : bool {
		if(!$this->file) $this->file = \fopen($this->path, "a");
		if(!$this->file) return false;
		foreach($lines as $line){
			if($this->timestamp){
				\fwrite($this->file, "[".$this->get_timestamp()."] ");
			}
			\fwrite($this->file, \mb_convert_encoding("$line\r\n", 'UTF-8', 'auto'));
		}
		if(!$this->hold_open) $this->close();
		return true;
	}

	/**
	 * Sets the date format for timestamps in log entries.
	 *
	 * @param string $date_format The date format string (e.g., 'Y-m-d H:i:s').
	 */
	public function set_date_format(string $date_format) : void {
		$this->date_format = $date_format;
	}

	/**
	 * Generates a formatted timestamp based on the configured date format.
	 *
	 * @return string The formatted timestamp string.
	 */
	public function get_timestamp() : string {
		return \date($this->date_format);
	}

	/**
	 * Writes content to the log file. The content can be a single string or an array of strings.
	 * If the log file does not exist, it attempts to create it.
	 *
	 * @param string|array $content The content to write. Can be a string or an array of strings.
	 * @return bool True on successful write, false on failure.
	 */
	public function write(string|array $content) : bool {
		if(\is_null($this->path)) return false;
		if(!\file_exists($this->path)){
			if(!$this->create()) return false;
		}
		if(\gettype($content) == "array") return $this->write_array($content);
		return $this->write_string($content);
	}

	/**
	 * Returns the path to the log file.
	 *
	 * @return string The log file path.
	 */
	public function get_path() : string {
		return $this->path;
	}

	/**
	 * Closes the currently open log file handle.
	 *
	 * @return bool True if the file was successfully closed or if it was not open, false on error during close.
	 */
	public function close() : bool {
		if(!$this->file) return false;
		\fclose($this->file);
		$this->file = false;
		return true;
	}

}

?>