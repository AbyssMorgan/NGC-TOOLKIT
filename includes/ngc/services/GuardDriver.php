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

namespace NGC\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use Exception;
use NGC\Core\IniFile;

/**
 * GuardDriver Class
 *
 * This class provides functionality for scanning files and folders,
 * generating checksums, and validating file integrity against a stored manifest.
 */
class GuardDriver {

	/**
	 * An array of folders to be scanned.
	 * @var array
	 */
	private array $folders_to_scan = [];

	/**
	 * An array of individual files to be scanned.
	 * @var array
	 */
	private array $files_to_scan = [];

	/**
	 * A list of all files found during a scan.
	 * @var array
	 */
	public array $file_list = [];

	/**
	 * Stores file checksums and other relevant data, organized by directory hash.
	 * @var array
	 */
	public array $data = [];

	/**
	 * Stores a mapping of directory hashes to their paths.
	 * @var array
	 */
	public array $keys = [];

	/**
	 * Stores any validation errors found.
	 * @var array
	 */
	private array $errors = [];

	/**
	 * Configuration flags for validation, e.g., 'damaged', 'unknown', 'missing'.
	 * @var array
	 */
	private array $flags = [];

	/**
	 * The path to the INI file used for storing/loading guard data.
	 * @var string
	 */
	private string $file;

	/**
	 * Constructor for GuardDriver.
	 *
	 * @param string $file The path to the INI file to be used for guard data.
	 * @param array $folders_to_scan Optional array of initial folders to scan.
	 * @param array $files_to_scan Optional array of initial files to scan.
	 */
	public function __construct(string $file, array $folders_to_scan = [], array $files_to_scan = []){
		$this->file = $file;
		$this->folders_to_scan = $folders_to_scan;
		$this->files_to_scan = $files_to_scan;
	}

	/**
	 * Adds one or more folders to the list of folders to be scanned.
	 *
	 * @param array|string $folders A single folder path (string) or an array of folder paths.
	 */
	public function add_folders(array|string $folders) : void {
		if(\gettype($folders) == 'string') $folders = [$folders];
		$this->folders_to_scan = \array_unique(\array_merge($this->folders_to_scan, $folders));
	}

	/**
	 * Sets the list of folders to be scanned, replacing any existing list.
	 *
	 * @param array|string $folders A single folder path (string) or an array of folder paths.
	 */
	public function set_folders(array|string $folders) : void {
		if(\gettype($folders) == 'string') $folders = [$folders];
		$this->folders_to_scan = $folders;
	}

	/**
	 * Retrieves the current list of folders to be scanned.
	 *
	 * @return array An array of folder paths.
	 */
	public function get_folders() : array {
		return $this->folders_to_scan;
	}

	/**
	 * Adds one or more files to the list of files to be scanned.
	 *
	 * @param array|string $files A single file path (string) or an array of file paths.
	 */
	public function add_files(array|string $files) : void {
		if(\gettype($files) == 'string') $files = [$files];
		$this->files_to_scan = \array_unique(\array_merge($this->files_to_scan, $files));
	}

	/**
	 * Sets the list of files to be scanned, replacing any existing list.
	 *
	 * @param array|string $files A single file path (string) or an array of file paths.
	 */
	public function set_files(array|string $files) : void {
		if(\gettype($files) == 'string') $files = [$files];
		$this->files_to_scan = $files;
	}

	/**
	 * Retrieves the current list of files to be scanned.
	 *
	 * @return array An array of file paths.
	 */
	public function get_files() : array {
		return $this->files_to_scan;
	}

	/**
	 * Resets the internal cache, clearing file lists, data, and errors.
	 */
	public function reset_cache() : void {
		$this->file_list = [];
		$this->data = [];
		$this->keys = [];
		$this->errors = [];
	}

	/**
	 * Loads guard data from a provided IniFile object.
	 *
	 * @param IniFile $guard An instance of IniFile containing the guard data.
	 */
	public function load(IniFile $guard) : void {
		$this->data = $guard->all_except(['files', 'keys', 'files_to_scan', 'folders_to_scan']);
		$this->file_list = $guard->get('files');
		$this->keys = $guard->get('keys');
		$this->files_to_scan = $guard->get('files_to_scan');
		$this->folders_to_scan = $guard->get('folders_to_scan');
		$this->errors = [];
	}

	/**
	 * Scans a specified folder recursively to generate MD5 hashes for all files within it.
	 * The hashes and file paths are stored internally.
	 *
	 * @param string $folder The path to the folder to scan.
	 */
	public function scan_folder(string $folder) : void {
		$files = new RecursiveDirectoryIterator($folder, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS);
		foreach(new RecursiveIteratorIterator($files) as $file){
			if($file->isDir() || $file->isLink()) continue;
			$file = (string)$file;
			$key = \strtoupper(\hash('md5', \str_replace(["\\", "/"], ":", \pathinfo($file, PATHINFO_DIRNAME))));
			if(!isset($data[$key])) $data[$key] = [];
			$this->data[$key][\pathinfo($file, PATHINFO_BASENAME)] = \strtoupper(\hash_file('md5', $file));
			\array_push($this->file_list, \str_replace("\\", "/", $file));
			$this->keys[$key] = \str_replace("\\", "/", \pathinfo($file, PATHINFO_DIRNAME));
		}
	}

	/**
	 * Scans a single file to generate its MD5 hash.
	 * The hash and file path are stored internally.
	 *
	 * @param string $file The path to the file to scan.
	 * @param bool $update If true, the file will not be added to the file_list again if it already exists.
	 */
	public function scan_file(string $file, bool $update = false) : void {
		$key = \strtoupper(\hash('md5', \str_replace(["\\", "/"], ":", \pathinfo($file, PATHINFO_DIRNAME))));
		if(!isset($data[$key])) $data[$key] = [];
		$this->data[$key][\pathinfo($file, PATHINFO_BASENAME)] = \strtoupper(\hash_file('md5', $file));
		if(!$update) \array_push($this->file_list, \str_replace("\\", "/", $file));
		$this->keys[$key] = \str_replace("\\", "/", \pathinfo($file, PATHINFO_DIRNAME));
	}

	/**
	 * Validates the files within a specified folder against the loaded guard data.
	 * Checks for damaged or unknown files based on the 'flags' property.
	 *
	 * @param string $folder The path to the folder to validate.
	 * @return bool True if the folder exists, false otherwise.
	 */
	public function validate_folder(string $folder) : bool {
		if(!\file_exists($folder)) return false;
		$files = new RecursiveDirectoryIterator($folder, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS);
		foreach(new RecursiveIteratorIterator($files) as $file){
			if($file->isDir() || $file->isLink()) continue;
			$file = (string)$file;
			$key = \strtoupper(\hash('md5', \str_replace(["\\", "/"], ":", \pathinfo($file, PATHINFO_DIRNAME))));
			$file_name = \pathinfo($file, PATHINFO_BASENAME);
			$file = \str_replace("\\", "/", $file);
			if(isset($this->data[$key][$file_name])){
				if($this->flags['damaged']){
					try {
						$hash = \strtoupper(\hash_file('md5', $file));
					}
					catch (Exception $e){
						$hash = "#UNKNOWN";
					}
					if($this->data[$key][$file_name] != $hash){
						\array_push($this->errors, ['type' => 'damaged', 'file' => $file]);
					}
				}
			} else {
				if($this->flags['unknown']){
					\array_push($this->errors, ['type' => 'unknown', 'file' => $file]);
				}
			}
		}
		return true;
	}

	/**
	 * Initiates a full scan of all configured folders and files.
	 * Resets the cache before scanning.
	 */
	public function scan() : void {
		$this->reset_cache();
		foreach($this->folders_to_scan as $folder) $this->scan_folder($folder);
		foreach($this->files_to_scan as $file) $this->scan_file($file);
	}

	/**
	 * Retrieves the collected guard data, including file checksums, file lists,
	 * and folders/files that were scanned.
	 *
	 * @return array The complete guard data.
	 */
	public function get() : array {
		$this->data['keys'] = $this->keys;
		$this->data['files'] = $this->file_list;
		$this->data['files_to_scan'] = $this->files_to_scan;
		$this->data['folders_to_scan'] = $this->folders_to_scan;
		return $this->data;
	}

	/**
	 * Validates a single file against the loaded guard data.
	 * Checks for damaged or unknown files based on the 'flags' property.
	 *
	 * @param string $file The path to the file to validate.
	 * @return bool True if the file exists, false otherwise.
	 */
	public function validate_file(string $file) : bool {
		if(!\file_exists($file)) return false;
		$key = \strtoupper(\hash('md5', \str_replace(["\\", "/"], ":", \pathinfo($file, PATHINFO_DIRNAME))));
		$file_name = \pathinfo($file, PATHINFO_BASENAME);
		if(isset($this->data[$key][$file_name])){
			if($this->flags['damaged']){
				try {
					$hash = \strtoupper(\hash_file('md5', $file));
				}
				catch (Exception $e){
					$hash = "#UNKNOWN";
				}
				if($this->data[$key][$file_name] != $hash){
					\array_push($this->errors, ['type' => 'damaged', 'file' => \str_replace("\\", "/", $file)]);
				}
			}
		} else {
			if($this->flags['unknown']){
				\array_push($this->errors, ['type' => 'unknown', 'file' => \str_replace("\\", "/", $file)]);
			}
		}
		return true;
	}

	/**
	 * Validates the existence of all files recorded in the guard data.
	 * If a file is missing and the 'missing' flag is set, an error is recorded.
	 */
	public function validate_exists() : void {
		if(isset($this->data['files'])){
			foreach($this->data['files'] as $file){
				if(!\file_exists($file)){
					if($this->flags['missing']) \array_push($this->errors, ['type' => 'missing', 'file' => $file]);
				}
			}
		}
	}

	/**
	 * Performs a comprehensive validation of files and folders against the stored guard data.
	 *
	 * @param array $flags An associative array of flags to determine what types of validation to perform.
	 * Possible keys: 'damaged', 'unknown', 'missing'. Default is all true.
	 * @return array An array of detected errors. Each error is an associative array with 'type' and 'file' keys.
	 */
	public function validate(array $flags = ['damaged' => true, 'unknown' => true, 'missing' => true]) : array {
		$guard = new IniFile($this->file, true);
		$this->reset_cache();
		$this->data = $guard->get_all();
		$this->flags = $flags;
		foreach($this->folders_to_scan as $folder) $this->validate_folder($folder);
		foreach($this->files_to_scan as $file) $this->validate_file($file);
		$this->validate_exists();
		return $this->errors;
	}

	/**
	 * Generates new guard data by scanning all configured folders and files,
	 * and then saves this data to the INI file.
	 */
	public function generate() : void {
		$guard = new IniFile($this->file, true, true);
		$this->scan();
		$guard->set_all($this->get(), true);
	}

	/**
	 * Retrieves the guard data organized into a hierarchical tree structure,
	 * representing the file system.
	 *
	 * @return array The hierarchical representation of the guarded files.
	 */
	public function get_tree() : array {
		$guard = new IniFile($this->file, true);
		$data = [];
		foreach($guard->get('keys', []) as $key => $value){
			$guard->rename($key, $value);
			$guard->extract_path($data, $value);
		}
		$guard->set_all($data);
		foreach($guard->get('.', []) as $key => $value){
			$guard->set($key, $value);
		}
		$guard->unset('.');
		return $guard->get_all();
	}

}

?>