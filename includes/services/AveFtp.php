<?php

declare(strict_types=1);

namespace App\Services;

use FtpClient\FtpClient;

class AveFtp {

	private FtpClient $ftp;

	public function __construct(FtpClient $ftp){
		$this->ftp = $ftp;
	}

	public function unix_permission(string $permission) : string {
		$map = ['-' => 0, 'r' => 4, 'w' => 2, 'x' => 1];
		$num = '';
		for($p = 0; $p < 3; $p++){
			$n = $map[$permission[$p]] + $map[$permission[$p+1]] + $map[$permission[$p+2]];
			$num .= strval($n);
		}
		return "0$num";
	}

	public function get_files(string $path, array|null $extensions = null, array|null $except = null, array|null $filters = null) : array {
		$data = [];
		$files = $this->ftp->rawlist($path);
		if($files === false) return [];
		foreach($files as $file){
			$chunks = preg_split("/\s+/", $file);
			if($chunks[8] == '.' || $chunks[8] == '..') continue;
			if(substr($chunks[0], 0, 1) == 'd'){
				$data = array_merge($data, $this->get_files($path.'/'.$chunks[8], $extensions, $except));
			} else {
				$ext = strtolower(pathinfo($chunks[8], PATHINFO_EXTENSION));
				if(!is_null($extensions) && !in_array($ext, $extensions)) continue;
				if(!is_null($except) && in_array($ext, $except)) continue;
				if(!is_null($filters) && !$this->filter(pathinfo($chunks[8], PATHINFO_BASENAME), $filters)) continue;
				array_push($data, $path.'/'.$chunks[8]);
			}
		}
		return $data;
	}

	public function get_files_meta(string $path, array|null $extensions = null, array|null $except = null, array|null $filters = null) : array {
		$data = [];
		$files = $this->ftp->rawlist($path);
		foreach($files as $file){
			$chunks = preg_split("/\s+/", $file);
			if($chunks[8] == '.' || $chunks[8] == '..') continue;
			if(substr($chunks[0], 0, 1) == 'd'){
				$data = array_merge($data, $this->get_files_meta($path.'/'.$chunks[8], $extensions, $except));
			} else {
				$ext = strtolower(pathinfo($chunks[8], PATHINFO_EXTENSION));
				if(!is_null($extensions) && !in_array($ext, $extensions)) continue;
				if(!is_null($except) && in_array($ext, $except)) continue;
				if(!is_null($filters) && !$this->filter(pathinfo($chunks[8], PATHINFO_BASENAME), $filters)) continue;
				array_push($data, [
					'path' => $path.'/'.$chunks[8],
					'directory' => $path,
					'name' => $chunks[8],
					'date' => date("Y-m-d H:i:s", $this->ftp->mdtm($path.'/'.$chunks[8])),
					'permission' => $this->unix_permission(substr($chunks[0], 1)),
					'size' => $this->ftp->size($path.'/'.$chunks[8]),
				]);
			}
		}
		return $data;
	}

	public function get_folders(string $path) : array {
		$data = [];
		$files = $this->ftp->rawlist($path);
		if($files === false) return [];
		array_push($data, $path);
		foreach($files as $file){
			$chunks = preg_split("/\s+/", $file);
			if($chunks[8] == '.' || $chunks[8] == '..') continue;
			if(substr($chunks[0], 0, 1) == 'd'){
				$data = array_merge($data, $this->get_folders($path.'/'.$chunks[8]));
			}
		}
		return $data;
	}

	public function hasFiles(string $path) : bool {
		$files = $this->ftp->rawlist($path);
		foreach($files as $file){
			$chunks = preg_split("/\s+/", $file);
			if($chunks[8] == '.' || $chunks[8] == '..') continue;
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
