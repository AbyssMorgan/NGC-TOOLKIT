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

namespace NGC\Services;

/**
 * GuardPattern Class
 *
 * This class is designed to manage and represent a "guard pattern,"
 * which consists of a set of folders and files, and an optional input path.
 * It allows for adding, setting, getting, and serializing/deserializing
 * these patterns.
 */
class GuardPattern {

	/**
	 * An array of folder paths included in the pattern.
	 * @var array
	 */
	private array $folders = [];

	/**
	 * An array of file paths included in the pattern.
	 * @var array
	 */
	private array $files = [];

	/**
	 * An optional input path associated with the pattern.
	 * @var string
	 */
	private string $input;

	/**
	 * Constructor for GuardPattern.
	 *
	 * @param array $folders Optional array of initial folder paths.
	 * @param array $files Optional array of initial file paths.
	 */
	public function __construct(array $folders = [], array $files = []){
		$this->folders = $folders;
		$this->files = $files;
		$this->input = '';
	}

	/**
	 * Adds one or more folders to the existing list of folders in the pattern.
	 * Duplicate folders will be automatically removed.
	 *
	 * @param array|string $folders A single folder path (string) or an array of folder paths.
	 */
	public function add_folders(array|string $folders) : void {
		if(\gettype($folders) == 'string') $folders = [$folders];
		$this->folders = \array_unique(\array_merge($this->folders, $folders));
	}

	/**
	 * Sets the list of folders for the pattern, replacing any previously added folders.
	 *
	 * @param array|string $folders A single folder path (string) or an array of folder paths.
	 */
	public function set_folders(array|string $folders) : void {
		if(\gettype($folders) == 'string') $folders = [$folders];
		$this->folders = $folders;
	}

	/**
	 * Retrieves the current list of folders in the pattern.
	 *
	 * @return array An array of folder paths.
	 */
	public function get_folders() : array {
		return $this->folders;
	}

	/**
	 * Adds one or more files to the existing list of files in the pattern.
	 * Duplicate files will be automatically removed.
	 *
	 * @param array|string $files A single file path (string) or an array of file paths.
	 */
	public function add_files(array|string $files) : void {
		if(\gettype($files) == 'string') $files = [$files];
		$this->files = \array_unique(\array_merge($this->files, $files));
	}

	/**
	 * Sets the list of files for the pattern, replacing any previously added files.
	 *
	 * @param array|string $files A single file path (string) or an array of file paths.
	 */
	public function set_files(array|string $files) : void {
		if(\gettype($files) == 'string') $files = [$files];
		$this->files = $files;
	}

	/**
	 * Retrieves the current list of files in the pattern.
	 *
	 * @return array An array of file paths.
	 */
	public function get_files() : array {
		return $this->files;
	}

	/**
	 * Retrieves the current input path, with trailing slashes removed.
	 *
	 * @return string The input path.
	 */
	public function get_input() : string {
		return \rtrim($this->input, "\\/");
	}

	/**
	 * Sets the input path for the pattern.
	 *
	 * @param string $input The input path to set.
	 */
	public function set_input(string $input) : void {
		$this->input = $input;
	}

	/**
	 * Generates a string representation of the guard pattern.
	 * The format includes "input:PATH", "folder:PATH", and "file:PATH" lines,
	 * separated by CRLF.
	 *
	 * @return string The serialized guard pattern.
	 */
	public function get() : string {
		$data = ['input:'.$this->get_input()];
		foreach($this->get_folders() as $folder){
			\array_push($data, "folder:$folder");
		}
		foreach($this->get_files() as $file){
			\array_push($data, "file:$file");
		}
		return \implode("\r\n", $data);
	}

	/**
	 * Loads a guard pattern from a string representation.
	 * The string is parsed to extract the input path, folders, and files.
	 *
	 * @param string $pattern The string representation of the guard pattern to load.
	 */
	public function load(string $pattern) : void {
		$data = \explode("\n", \str_replace(["\r\n", "\r"], "\n", $pattern));
		$folders = [];
		$files = [];
		foreach($data as $pat){
			if(\substr($pat, 0, 7) == 'folder:'){
				\array_push($folders, \substr($pat, 7));
			} elseif(\substr($pat, 0, 5) == 'file:'){
				\array_push($files, \substr($pat, 5));
			} elseif(\substr($pat, 0, 6) == 'input:'){
				$this->set_input(\substr($pat, 6));
			}
		}
		$this->set_folders($folders);
		$this->set_files($files);
	}

}

?>