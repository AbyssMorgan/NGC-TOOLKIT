<?php

/**
 * NGC-TOOLKIT v2.6.1 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Services;

class GuardPattern {

	private array $folders = [];
	private array $files = [];
	private string $file;
	private string $input;

	public function __construct(array $folders = [], array $files = []){
		$this->folders = $folders;
		$this->files = $files;
		$this->input = '';
	}

	public function add_folders(array|string $folders) : void {
		if(gettype($folders) == 'string') $folders = [$folders];
		$this->folders = array_unique(array_merge($this->folders, $folders));
	}

	public function set_folders(array|string $folders) : void {
		if(gettype($folders) == 'string') $folders = [$folders];
		$this->folders = $folders;
	}

	public function get_folders() : array {
		return $this->folders;
	}

	public function add_files(array|string $files) : void {
		if(gettype($files) == 'string') $files = [$files];
		$this->files = array_unique(array_merge($this->files, $files));
	}

	public function set_files(array|string $files) : void {
		if(gettype($files) == 'string') $files = [$files];
		$this->files = $files;
	}

	public function get_files() : array {
		return $this->files;
	}

	public function get_input() : string {
		return rtrim($this->input, "\\/");
	}

	public function set_input(string $input) : void {
		$this->input = $input;
	}

	public function get() : string {
		$data = ['input:'.$this->get_input()];
		foreach($this->get_folders() as $folder){
			array_push($data, "folder:$folder");
		}
		foreach($this->get_files() as $file){
			array_push($data, "file:$file");
		}
		return implode("\r\n", $data);
	}

	public function load(string $pattern) : void {
		$data = explode("\n", str_replace(["\r\n", "\r"], "\n", $pattern));
		$folders = [];
		$files = [];
		foreach($data as $pat){
			if(substr($pat, 0, 7) == 'folder:'){
				array_push($folders, substr($pat, 7));
			} elseif(substr($pat, 0, 5) == 'file:'){
				array_push($files, substr($pat, 5));
			} elseif(substr($pat, 0, 6) == 'input:'){
				$this->set_input(substr($pat, 6));
			}
		}
		$this->set_folders($folders);
		$this->set_files($files);
	}

}

?>