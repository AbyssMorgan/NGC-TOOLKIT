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

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use Exception;
use NGC\Core\IniFile;

class GuardDriver {

	private array $folders_to_scan = [];
	private array $files_to_scan = [];
	public array $file_list = [];
	public array $data = [];
	public array $keys = [];
	private array $errors = [];
	private array $flags = [];
	private int $size = 0;
	private string $file;

	public function __construct(string $file, array $folders_to_scan = [], array $files_to_scan = []){
		$this->file = $file;
		$this->folders_to_scan = $folders_to_scan;
		$this->files_to_scan = $files_to_scan;
	}

	public function add_folders(array|string $folders) : void {
		if(gettype($folders) == 'string') $folders = [$folders];
		$this->folders_to_scan = array_unique(array_merge($this->folders_to_scan, $folders));
	}

	public function set_folders(array|string $folders) : void {
		if(gettype($folders) == 'string') $folders = [$folders];
		$this->folders_to_scan = $folders;
	}

	public function get_folders() : array {
		return $this->folders_to_scan;
	}

	public function add_files(array|string $files) : void {
		if(gettype($files) == 'string') $files = [$files];
		$this->files_to_scan = array_unique(array_merge($this->files_to_scan, $files));
	}

	public function set_files(array|string $files) : void {
		if(gettype($files) == 'string') $files = [$files];
		$this->files_to_scan = $files;
	}

	public function get_files() : array {
		return $this->files_to_scan;
	}

	public function reset_cache() : void {
		$this->file_list = [];
		$this->data = [];
		$this->keys = [];
		$this->errors = [];
	}

	public function load(IniFile $guard) : void {
		$this->data = $guard->all_except(['files', 'keys', 'files_to_scan', 'folders_to_scan']);
		$this->file_list = $guard->get('files');
		$this->keys = $guard->get('keys');
		$this->files_to_scan = $guard->get('files_to_scan');
		$this->folders_to_scan = $guard->get('folders_to_scan');
		$this->errors = [];
	}

	public function scan_folder(string $folder) : void {
		$files = new RecursiveDirectoryIterator($folder, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS);
		foreach(new RecursiveIteratorIterator($files) as $file){
			if($file->isDir() || $file->isLink()) continue;
			$file = (string)$file;
			$key = strtoupper(hash('md5', str_replace(["\\", "/"], ":", pathinfo($file, PATHINFO_DIRNAME))));
			if(!isset($data[$key])) $data[$key] = [];
			$this->data[$key][pathinfo($file, PATHINFO_BASENAME)] = strtoupper(hash_file('md5', $file));
			array_push($this->file_list, str_replace("\\", "/", $file));
			$this->keys[$key] = str_replace("\\", "/", pathinfo($file, PATHINFO_DIRNAME));
		}
	}

	public function scan_file(string $file, bool $update = false) : void {
		$key = strtoupper(hash('md5', str_replace(["\\", "/"], ":", pathinfo($file, PATHINFO_DIRNAME))));
		if(!isset($data[$key])) $data[$key] = [];
		$this->data[$key][pathinfo($file, PATHINFO_BASENAME)] = strtoupper(hash_file('md5', $file));
		if(!$update) array_push($this->file_list, str_replace("\\", "/", $file));
		$this->keys[$key] = str_replace("\\", "/", pathinfo($file, PATHINFO_DIRNAME));
	}

	public function validate_folder(string $folder) : bool {
		if(!file_exists($folder)) return false;
		$files = new RecursiveDirectoryIterator($folder, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS);
		foreach(new RecursiveIteratorIterator($files) as $file){
			if($file->isDir() || $file->isLink()) continue;
			$file = (string)$file;
			$key = strtoupper(hash('md5', str_replace(["\\", "/"], ":", pathinfo($file, PATHINFO_DIRNAME))));
			$file_name = pathinfo($file, PATHINFO_BASENAME);
			$file = str_replace("\\", "/", $file);
			if(isset($this->data[$key][$file_name])){
				if($this->flags['damaged']){
					try {
						$hash = strtoupper(hash_file('md5', $file));
					}
					catch (Exception $e){
						$hash = "#UNKNOWN";
					}
					if($this->data[$key][$file_name] != $hash){
						array_push($this->errors, ['type' => 'damaged', 'file' => $file]);
					}
				}
			} else {
				if($this->flags['unknown']){
					array_push($this->errors, ['type' => 'unknown', 'file' => $file]);
				}
			}
		}
		return true;
	}

	public function scan() : void {
		$this->reset_cache();
		foreach($this->folders_to_scan as $folder) $this->scan_folder($folder);
		foreach($this->files_to_scan as $file) $this->scan_file($file);
	}

	public function get() : array {
		$this->data['keys'] = $this->keys;
		$this->data['files'] = $this->file_list;
		$this->data['files_to_scan'] = $this->files_to_scan;
		$this->data['folders_to_scan'] = $this->folders_to_scan;
		return $this->data;
	}

	public function validate_file(string $file) : bool {
		if(!file_exists($file)) return false;
		$key = strtoupper(hash('md5', str_replace(["\\", "/"], ":", pathinfo($file, PATHINFO_DIRNAME))));
		$file_name = pathinfo($file, PATHINFO_BASENAME);
		if(isset($this->data[$key][$file_name])){
			if($this->flags['damaged']){
				try {
					$hash = strtoupper(hash_file('md5', $file));
				}
				catch (Exception $e){
					$hash = "#UNKNOWN";
				}
				if($this->data[$key][$file_name] != $hash){
					array_push($this->errors, ['type' => 'damaged', 'file' => str_replace("\\", "/", $file)]);
				}
			}
		} else {
			if($this->flags['unknown']){
				array_push($this->errors, ['type' => 'unknown', 'file' => str_replace("\\", "/", $file)]);
			}
		}
		return true;
	}

	public function validate_exists() : void {
		if(isset($this->data['files'])){
			foreach($this->data['files'] as $file){
				if(!file_exists($file)){
					if($this->flags['missing']) array_push($this->errors, ['type' => 'missing', 'file' => $file]);
				}
			}
		}
	}

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

	public function generate() : void {
		$guard = new IniFile($this->file, true, true);
		$this->scan();
		$guard->set_all($this->get(), true);
	}

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