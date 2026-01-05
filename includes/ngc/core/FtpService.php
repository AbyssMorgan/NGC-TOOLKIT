<?php

/**
 * NGC-TOOLKIT v2.8.0 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Core;

use FtpClient\FtpClient;
use FtpClient\FtpException;

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
			$num .= \strval($n);
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
			$chunks = \preg_split("/\s+/", $file);
			if($chunks[8] == '.' || $chunks[8] == '..') continue;
			if(\substr($chunks[0], 0, 1) == 'd'){
				$data = \array_merge($data, $this->get_files("$path/{$chunks[8]}", $include_extensions, $exclude_extensions));
			} else {
				$ext = \mb_strtolower(\pathinfo($chunks[8], PATHINFO_EXTENSION));
				if(!\is_null($include_extensions) && !\in_array($ext, $include_extensions)) continue;
				if(!\is_null($exclude_extensions) && \in_array($ext, $exclude_extensions)) continue;
				if(!\is_null($name_filters) && !$this->filter(\pathinfo($chunks[8], PATHINFO_BASENAME), $name_filters)) continue;
				\array_push($data, "$path/{$chunks[8]}");
			}
		}
		return $data;
	}

	/**
	 * Do operations on a list of files from a given path, with optional filtering.
	 *
	 * @param string|array $path The directory/direcories path to scan.
	 * @param callable $callback Callback called for every found files function(string $file)
	 * @param ?array $include_extensions An array of extensions to include (e.g., ['jpg', 'png']). Null for all.
	 * @param ?array $exclude_extensions An array of extensions to exclude. Null for none.
	 * @param ?array $name_filters An array of strings to filter file names by (case-sensitive or insensitive). Null for no name filter.
	 * @param bool $case_sensitive Whether name filtering should be case-sensitive.
	 * @param bool $recursive Whether to scan subdirectories recursively.
	 * @param bool $with_folders Determine if include folders before files.
	 * @return int Count total processed files.
	 */
	public function process_files(string|array $path, callable $callback, ?array $include_extensions = null, ?array $exclude_extensions = null, ?array $name_filters = null, bool $case_sensitive = false, bool $recursive = true, bool $with_folders = true) : int {
		if(\gettype($path) == 'string'){
			$paths = [$path];
		} else {
			$paths = $path;
		}
		if(!$case_sensitive && !\is_null($name_filters)){
			$name_filters = $this->array_to_lower($name_filters);
		}
		$counter = 0;
		foreach($paths as $path){
			if(!$this->folder_exists($path)) continue;
			$this->scan_dir_safe_extension_process_files($path, $callback, $counter, $include_extensions, $exclude_extensions, $name_filters, $case_sensitive, $recursive, $with_folders);
		}
		return $counter;
	}

	/**
	 * Recursively scans a directory for files, applying include/exclude extension filters and name filters.
	 *
	 * @param string $dir The directory to scan.
	 * @param callable $callback Callback called for every found files function(string $file)
	 * @param ?array $include_extensions An array of extensions to include.
	 * @param ?array $exclude_extensions An array of extensions to exclude.
	 * @param ?array $name_filters An array of strings to filter file names by.
	 * @param bool $case_sensitive Whether name filtering should be case-sensitive.
	 * @param bool $recursive Whether to scan subdirectories recursively.
	 * @param bool $with_folders Determine if include folders before files.
	 * @return bool True if an action was successfully performed, false otherwise.
	 */
	public function scan_dir_safe_extension_process_files(string $dir, callable $callback, int &$counter, ?array $include_extensions, ?array $exclude_extensions, ?array $name_filters, bool $case_sensitive, bool $recursive, bool $with_folders) : bool {
		try {
			$items = $this->ftp->scan_dir($dir);
		}
		catch(FtpException $e){
			return false;
		}
		foreach($items as $item){
			if($item['name'] === '.' || $item['name'] === '..') continue;
			$full_path = "$dir/{$item['name']}";
			if($item['type'] == 'directory'){
				if($with_folders){
					$callback($full_path, 'directory');
				}
				if(!$recursive) continue;
				$this->scan_dir_safe_extension_process_files($full_path, $callback, $counter, $include_extensions, $exclude_extensions, $name_filters, $case_sensitive, $recursive, $with_folders);
				continue;
			}
			$ext = \mb_strtolower(\pathinfo($full_path, PATHINFO_EXTENSION));
			if(!\is_null($include_extensions) && !\in_array($ext, $include_extensions)) continue;
			if(!\is_null($exclude_extensions) && \in_array($ext, $exclude_extensions)) continue;
			$basename = \pathinfo($full_path, PATHINFO_BASENAME);
			if(!\is_null($name_filters)){
				$check_name = $case_sensitive ? $basename : \mb_strtolower($basename);
				if(!$this->filter($check_name, $name_filters)) continue;
			}
			$counter++;
			$callback($full_path, 'file');
		}
		return true;
	}

	/**
	 * Converts all string items in an array to lowercase.
	 *
	 * @param array $items The input array.
	 * @return array The array with all string items converted to lowercase.
	 */
	public function array_to_lower(array $items) : array {
		$data = [];
		foreach($items as $item){
			$data[] = \mb_strtolower($item);
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
			$chunks = \preg_split("/\s+/", $file);
			if($chunks[8] == '.' || $chunks[8] == '..') continue;
			if(\substr($chunks[0], 0, 1) == 'd'){
				$data = \array_merge($data, $this->get_files_meta("$path/{$chunks[8]}", $include_extensions, $exclude_extensions));
			} else {
				$ext = \mb_strtolower(\pathinfo($chunks[8], PATHINFO_EXTENSION));
				if(!\is_null($include_extensions) && !\in_array($ext, $include_extensions)) continue;
				if(!\is_null($exclude_extensions) && \in_array($ext, $exclude_extensions)) continue;
				if(!\is_null($name_filters) && !$this->filter(\pathinfo($chunks[8], PATHINFO_BASENAME), $name_filters)) continue;
				\array_push($data, [
					'path' => "$path/{$chunks[8]}",
					'directory' => $path,
					'name' => $chunks[8],
					'date' => \date("Y-m-d H:i:s", $this->ftp->mdtm("$path/{$chunks[8]}")),
					'permission' => $this->unix_permission(\substr($chunks[0], 1)),
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
		\array_push($data, $path);
		foreach($files as $file){
			$chunks = \preg_split("/\s+/", $file);
			if($chunks[8] == '.' || $chunks[8] == '..') continue;
			if(\substr($chunks[0], 0, 1) == 'd'){
				$data = \array_merge($data, $this->get_folders("$path/{$chunks[8]}"));
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
			$chunks = \preg_split("/\s+/", $file);
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
	 * Filters a search string against a list of filters.
	 *
	 * @param string $search The string to search within.
	 * @param array $filters An array of strings to filter by.
	 * @param bool $case_sensitive Whether the search should be case-sensitive.
	 * @return bool True if any filter is found in the search string, false otherwise.
	 */
	public function filter(string $search, array $filters, bool $case_sensitive = false) : bool {
		if(!$case_sensitive) $search = \mb_strtolower($search);
		foreach($filters as $filter){
			if(\str_contains($search, $filter)){
				return true;
			}
		}
		return false;
	}

}

?>