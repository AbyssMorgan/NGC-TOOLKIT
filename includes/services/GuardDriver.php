<?php

declare(strict_types=1);

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class GuardDriver {

	private array $folders_to_scan = [];
	private array $files_to_scan = [];
	private array $file_list = [];
	private array $folder_list = [];
	private array $data = [];
	private array $keys = [];
	private array $errors = [];
	private array $flags = [];
	private int $size = 0;
	private string $file;

	public function __construct(string $file, array $folders_to_scan = [], array $files_to_scan = []){
		$this->file = $file;
		$this->folders_to_scan = $folders_to_scan;
		$this->files_to_scan = $files_to_scan;
	}

	public function addFolders(array|string $folders) : void {
		if(gettype($folders) == 'string') $folders = [$folders];
		$this->folders_to_scan = array_unique(array_merge($this->folders_to_scan, $folders));
	}

	public function setFolders(array|string $folders) : void {
		if(gettype($folders) == 'string') $folders = [$folders];
		$this->folders_to_scan = $folders;
	}

	public function getFolders() : array {
		return $this->folders_to_scan;
	}

	public function addFiles(array|string $files) : void {
		if(gettype($files) == 'string') $files = [$files];
		$this->files_to_scan = array_unique(array_merge($this->files_to_scan, $files));
	}

	public function setFiles(array|string $files) : void {
		if(gettype($files) == 'string') $files = [$files];
		$this->files_to_scan = $files;
	}

	public function getFiles() : array {
		return $this->files_to_scan;
	}

	public function resetCache() : void {
		$this->file_list = [];
		$this->folder_list = [];
		$this->data = [];
		$this->keys = [];
		$this->errors = [];
		$this->size = 0;
	}

	public function getFolderList(string $folder) : bool {
		if(!file_exists($folder)) return false;
		array_push($this->folder_list, str_replace("\\", "/", $folder));
		$files = scandir($folder);
		foreach($files as $file){
			if($file == '.' || $file == '..') continue;
			if(is_dir($folder.DIRECTORY_SEPARATOR.$file) && !is_link($folder.DIRECTORY_SEPARATOR.$file)) $this->getFolderList($folder.DIRECTORY_SEPARATOR.$file);
		}
		return true;
	}

	public function scanFolder(string $folder) : void {
		$files = new RecursiveDirectoryIterator($folder, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS);
		foreach(new RecursiveIteratorIterator($files) as $file){
			if($file->isDir() || $file->isLink()) continue;
			$file = (string)$file;
			$this->size += filesize($file);
			$key = strtoupper(hash('md5', str_replace(["\\", "/"], ":", pathinfo($file, PATHINFO_DIRNAME))));
			if(!isset($data[$key])) $data[$key] = [];
			$this->data[$key][pathinfo($file, PATHINFO_BASENAME)] = strtoupper(hash_file('md5', $file));
			array_push($this->file_list, str_replace("\\", "/", $file));
			$this->keys[$key] = str_replace("\\", "/", pathinfo($file, PATHINFO_DIRNAME));
		}
	}

	public function scanFile(string $file) : void {
		$this->size += filesize($file);
		$key = strtoupper(hash('md5', str_replace(["\\", "/"], ":", pathinfo($file, PATHINFO_DIRNAME))));
		if(!isset($data[$key])) $data[$key] = [];
		$this->data[$key][pathinfo($file, PATHINFO_BASENAME)] = strtoupper(hash_file('md5', $file));
		array_push($this->file_list, str_replace("\\", "/", $file));
		$this->keys[$key] = str_replace("\\", "/", pathinfo($file, PATHINFO_DIRNAME));
	}

	public function validateFolder(string $folder) : bool {
		if(!file_exists($folder)) return false;
		$files = new RecursiveDirectoryIterator($folder, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS);
		foreach(new RecursiveIteratorIterator($files) as $file){
			if($file->isDir() || $file->isLink()) continue;
			$file = (string)$file;
			$key = strtoupper(hash('md5', str_replace(["\\", "/"], ":", pathinfo($file, PATHINFO_DIRNAME))));
			$file_name = pathinfo($file, PATHINFO_BASENAME);
			try {
				$hash = strtoupper(hash_file('md5', $file));
			}
			catch (\Exception $e){
				$hash = "#UNKNOWN";
			}
			$file = str_replace("\\", "/", $file);
			if(isset($this->data[$key][$file_name])){
				if($this->data[$key][$file_name] != $hash){
					if($this->flags['damaged']){
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

	public function get() : array {
		$this->resetCache();
		foreach($this->folders_to_scan as $folder) $this->scanFolder($folder);
		foreach($this->files_to_scan as $file) $this->scanFile($file);
		foreach($this->folders_to_scan as $folder) $this->getFolderList($folder);
		$this->data['keys'] = $this->keys;
		$this->data['files'] = $this->file_list;
		$this->data['file_size'] = $this->size;
		$this->data['file_count'] = count($this->file_list);
		$this->data['folders'] = $this->folder_list;
		$this->data['folders_count'] = count($this->folder_list);
		return $this->data;
	}

	public function validateFile(string $file) : bool {
		if(!file_exists($file)) return false;
		$key = strtoupper(hash('md5', str_replace(["\\", "/"], ":", pathinfo($file, PATHINFO_DIRNAME))));
		$file_name = pathinfo($file, PATHINFO_BASENAME);
		try {
			$hash = strtoupper(hash_file('md5', $file));
		}
		catch (\Exception $e){
			$hash = "#UNKNOWN";
		}
		if(isset($this->data[$key][$file_name])){
			if($this->data[$key][$file_name] != $hash){
				if($this->flags['damaged']){
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

	public function validateExist() : void {
		if(isset($this->data['files'])){
			foreach($this->data['files'] as $file){
				if(!file_exists($file)){
					if($this->flags['missing']) array_push($this->errors, ['type' => 'missing', 'file' => $file]);
				}
			}
		}
	}

	public function getUnusedFolders() : array {
		$guard = new IniFile($this->file, true);
		$folders = $guard->get('folders', []);
		$errors = [];
		$this->folder_list = [];
		foreach($this->folders_to_scan as $folder) $this->getFolderList($folder);
		foreach($this->folder_list as $folder){
			if(!in_array($folder, $folders)) array_push($errors, $folder);
		}
		return array_reverse($errors);
	}

	public function validate(array $flags = ['damaged' => true, 'unknown' => true, 'missing' => true]) : array {
		$guard = new IniFile($this->file, true);
		$this->resetCache();
		$this->data = $guard->getAll();
		$this->flags = $flags;
		foreach($this->folders_to_scan as $folder) $this->validateFolder($folder);
		foreach($this->files_to_scan as $file) $this->validateFile($file);
		$this->validateExist();
		return $this->errors;
	}

	public function generate() : void {
		$guard = new IniFile($this->file, true);
		$guard->setAll($this->get(), true);
	}

	public function getTree() : array {
		$guard = new IniFile($this->file, true);
		$data = [];
		foreach($guard->get('keys',[]) as $key => $value){
			$guard->rename($key, $value);
			$guard->extract_path($data, $value);
		}
		$guard->setAll($data);
		foreach($guard->get('.',[]) as $key => $value){
			$guard->set($key, $value);
		}
		$guard->unset('.');
		return $guard->getAll();
	}

}

?>
