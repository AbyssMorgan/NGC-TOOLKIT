<?php

declare(strict_types=1);

namespace App\Services;

use FtpClient\FtpClient;

class AveFtp {

	private FtpClient $ftp;

	public function __construct(FtpClient $ftp){
		$this->ftp = $ftp;
	}

	public function get_files(string $path, array|null $extensions = null, array|null $except = null, array|null $filters = null) : array {
		$data = [];
		$files = $this->ftp->mlsd($path);
		if($files === false) return [];
		foreach($files as $file){
			if($file['name'] == '.' || $file['name'] == '..') continue;
			if($file['type'] == 'dir'){
				$data = array_merge($data, $this->get_files($path.'/'.$file['name'], $extensions, $except));
			} else if($file['type'] == 'file'){
				$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
				if(!is_null($extensions) && !in_array($ext, $extensions)) continue;
				if(!is_null($except) && in_array($ext, $except)) continue;
				if(!is_null($filters) && !$this->filter(pathinfo($file['name'], PATHINFO_BASENAME), $filters)) continue;
				array_push($data, $path.'/'.$file['name']);
			}
		}
		return $data;
	}

	public function get_files_meta(string $path, array|null $extensions = null, array|null $except = null, array|null $filters = null) : array {
		$data = [];
		$files = $this->ftp->mlsd($path);
		foreach($files as $file){
			if($file['name'] == '.' || $file['name'] == '..') continue;
			if($file['type'] == 'dir'){
				$data = array_merge($data, $this->get_files_meta($path.'/'.$file['name'], $extensions, $except));
			} else if($file['type'] == 'file'){
				$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
				if(!is_null($extensions) && !in_array($ext, $extensions)) continue;
				if(!is_null($except) && in_array($ext, $except)) continue;
				if(!is_null($filters) && !$this->filter(pathinfo($file['name'], PATHINFO_BASENAME), $filters)) continue;
				array_push($data, [
					'path' => $path.'/'.$file['name'],
					'directory' => $path,
					'name' => $file['name'],
					'date' => substr($file['modify'], 0, 4).'-'.substr($file['modify'], 4, 2).'-'.substr($file['modify'], 6, 2).' '.substr($file['modify'], 8, 2).':'.substr($file['modify'], 10, 2).':'.substr($file['modify'], 12, 2),
					'permission' => $file['UNIX.mode'] ?? '',
					'size' => $this->ftp->size($path.'/'.$file['name']),
				]);
			}
		}
		return $data;
	}

	public function get_folders(string $path) : array {
		$data = [];
		$files = $this->ftp->mlsd($path);
		if($files === false) return [];
		array_push($data, $path);
		foreach($files as $file){
			if($file['name'] == '.' || $file['name'] == '..') continue;
			if($file['type'] == 'dir'){
				$data = array_merge($data, $this->get_folders($path.'/'.$file['name']));
			}
		}
		return $data;
	}

	public function hasFiles(string $path) : bool {
		$files = $this->ftp->mlsd($path);
		foreach($files as $file){
			if($file['name'] == '.' || $file['name'] == '..') continue;
			return true;
		}
		return false;
	}

	public function folder_exists(string $path) : bool {
		$pwd = $this->ftp->pwd();
		if(!$this->ftp->chdir($path)) return false;
		$this->ftp->chdir($pwd);
		return true;
	}

	public function filter(string $search, array $filters) : bool {
		foreach($filters as $filter){
			if(strpos($search, $filter) !== false){
				return true;
			}
		}
		return false;
	}

}

?>
