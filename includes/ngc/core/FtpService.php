<?php

/**
 * NGC-TOOLKIT v2.7.0 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Core;

use FtpClient\FtpClient;

/**
 * This class provides a wrapper for FTP operations, simplifying common tasks
 * like file and folder listing, permission conversion, and file filtering.
 */
class FtpService {

	private FtpClient $ftp;

	/**
	 * FtpService constructor.
	 *
	 * @param FtpClient $ftp An instance of FtpClient.
	 */
	public function __construct(FtpClient $ftp){
		$this->ftp = $ftp;
	}

	/**
	 * Converts a Unix-style permission string (e.g., 'rwxr-xr--') to an octal representation.
	 *
	 * @param string $permission The Unix permission string.
	 * @return string The octal representation of the permission.
	 */
	public function unix_permission(string $permission) : string {
		$map = ['-' => 0, 'r' => 4, 'w' => 2, 'x' => 1];
		$num = '';
		for($p = 0; $p < 3; $p++){
			$n = $map[$permission[$p]] + $map[$permission[$p + 1]] + $map[$permission[$p + 2]];
			$num .= strval($n);
		}
		return "0$num";
	}

	/**
	 * Recursively retrieves a list of files from a given FTP path.
	 *
	 * @param string $path The starting path on the FTP server.
	 * @param array|null $include_extensions An optional array of allowed file extensions. If null, all extensions are allowed.
	 * @param array|null $exclude_extensions An optional array of file extensions to exclude.
	 * @param array|null $name_filters An optional array of strings to filter filenames by (case-sensitive containment).
	 * @return array An array of file paths.
	 */
	public function get_files(string $path, ?array $include_extensions = null, ?array $exclude_extensions = null, ?array $name_filters = null) : array {
		$data = [];
		$files = $this->ftp->rawlist($path);
		if($files === false) return [];
		foreach($files as $file){
			$chunks = preg_split("/\s+/", $file);
			if($chunks[8] == '.' || $chunks[8] == '..') continue;
			if(substr($chunks[0], 0, 1) == 'd'){
				$data = array_merge($data, $this->get_files("$path/{$chunks[8]}", $include_extensions, $exclude_extensions));
			} else {
				$ext = mb_strtolower(pathinfo($chunks[8], PATHINFO_EXTENSION));
				if(!is_null($include_extensions) && !in_array($ext, $include_extensions)) continue;
				if(!is_null($exclude_extensions) && in_array($ext, $exclude_extensions)) continue;
				if(!is_null($name_filters) && !$this->filter(pathinfo($chunks[8], PATHINFO_BASENAME), $name_filters)) continue;
				array_push($data, "$path/{$chunks[8]}");
			}
		}
		return $data;
	}

	/**
	 * Recursively retrieves a list of files with detailed metadata from a given FTP path.
	 *
	 * @param string $path The starting path on the FTP server.
	 * @param array|null $include_extensions An optional array of allowed file extensions. If null, all extensions are allowed.
	 * @param array|null $exclude_extensions An optional array of file extensions to exclude.
	 * @param array|null $name_filters An optional array of strings to filter filenames by (case-sensitive containment).
	 * @return array An array of file metadata arrays, each containing 'path', 'directory', 'name', 'date', 'permission', and 'size'.
	 */
	public function get_files_meta(string $path, ?array $include_extensions = null, ?array $exclude_extensions = null, ?array $name_filters = null) : array {
		$data = [];
		$files = $this->ftp->rawlist($path);
		foreach($files as $file){
			$chunks = preg_split("/\s+/", $file);
			if($chunks[8] == '.' || $chunks[8] == '..') continue;
			if(substr($chunks[0], 0, 1) == 'd'){
				$data = array_merge($data, $this->get_files_meta("$path/{$chunks[8]}", $include_extensions, $exclude_extensions));
			} else {
				$ext = mb_strtolower(pathinfo($chunks[8], PATHINFO_EXTENSION));
				if(!is_null($include_extensions) && !in_array($ext, $include_extensions)) continue;
				if(!is_null($exclude_extensions) && in_array($ext, $exclude_extensions)) continue;
				if(!is_null($name_filters) && !$this->filter(pathinfo($chunks[8], PATHINFO_BASENAME), $name_filters)) continue;
				array_push($data, [
					'path' => "$path/{$chunks[8]}",
					'directory' => $path,
					'name' => $chunks[8],
					'date' => date("Y-m-d H:i:s", $this->ftp->mdtm("$path/{$chunks[8]}")),
					'permission' => $this->unix_permission(substr($chunks[0], 1)),
					'size' => $this->ftp->size("$path/{$chunks[8]}"),
				]);
			}
		}
		return $data;
	}

	/**
	 * Recursively retrieves a list of folders from a given FTP path.
	 *
	 * @param string $path The starting path on the FTP server.
	 * @return array An array of folder paths.
	 */
	public function get_folders(string $path) : array {
		$data = [];
		$files = $this->ftp->rawlist($path);
		if($files === false) return [];
		array_push($data, $path);
		foreach($files as $file){
			$chunks = preg_split("/\s+/", $file);
			if($chunks[8] == '.' || $chunks[8] == '..') continue;
			if(substr($chunks[0], 0, 1) == 'd'){
				$data = array_merge($data, $this->get_folders("$path/{$chunks[8]}"));
			}
		}
		return $data;
	}

	/**
	 * Checks if a given FTP folder contains any files or subfolders (excluding '.' and '..').
	 *
	 * @param string $path The path of the folder to check.
	 * @return bool True if the folder contains files or subfolders, false otherwise.
	 */
	public function has_files(string $path) : bool {
		$files = $this->ftp->rawlist($path);
		foreach($files as $file){
			$chunks = preg_split("/\s+/", $file);
			if($chunks[8] == '.' || $chunks[8] == '..') continue;
			return true;
		}
		return false;
	}

	/**
	 * Checks if a given folder exists on the FTP server.
	 *
	 * @param string $path The path of the folder to check.
	 * @return bool True if the folder exists, false otherwise.
	 */
	public function folder_exists(string $path) : bool {
		$pwd = $this->ftp->pwd();
		if(!$this->ftp->chdir($path)) return false;
		$this->ftp->chdir($pwd);
		return true;
	}

	/**
	 * Checks if a search string contains any of the provided filter strings.
	 *
	 * @param string $search The string to search within.
	 * @param array $filters An array of strings to search for.
	 * @return bool True if the search string contains any filter string, false otherwise.
	 */
	public function filter(string $search, array $filters) : bool {
		foreach($filters as $filter){
			if(str_contains($search, $filter)){
				return true;
			}
		}
		return false;
	}

}

?>