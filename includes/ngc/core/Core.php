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

use Exception;
use FilesystemIterator;
use IntlTimeZone;

\define('SYSTEM_TYPE_UNKNOWN', 0);
\define('SYSTEM_TYPE_WINDOWS', 1);
\define('SYSTEM_TYPE_LINUX', 2);
\define('SYSTEM_TYPE_MACOS', 3);

/**
 * The Core class provides a set of utility functions for CLI applications.
 * It handles configuration, logging, file system operations, and console interactions.
 */
class Core {

	/**
	 * Configuration object.
	 * @var IniFile
	 */
	public IniFile $config;

	/**
	 * Event log object.
	 * @var Logs
	 */
	public Logs $log_event;

	/**
	 * Error log object.
	 * @var Logs
	 */
	public Logs $log_error;

	/**
	 * Data log object.
	 * @var Logs
	 */
	public Logs $log_data;

	/**
	 * Application name.
	 * @var string
	 */
	public string $app_name = "";

	/**
	 * Application version.
	 * @var string
	 */
	public string $version = "0.0.0";

	/**
	 * The command executed by the application.
	 * @var string|null
	 */
	public ?string $command = null;

	/**
	 * Array of arguments passed to the application.
	 * @var array
	 */
	public array $arguments = [];

	/**
	 * Application logo.
	 * @var string
	 */
	public string $logo = '';

	/**
	 * Base path of the application.
	 * @var string
	 */
	public string $path = '';

	/**
	 * Current tool name.
	 * @var string
	 */
	public string $tool_name = '';

	/**
	 * Current subtool name.
	 * @var string
	 */
	public string $subtool_name = '';

	/**
	 * State of monitored folders.
	 * @var array
	 */
	public array $folders_state = [];

	/**
	 * Current tool object.
	 * @var object|null
	 */
	public ?object $tool = null;

	/**
	 * Units for byte formatting.
	 * @var array
	 */
	public array $units_bytes = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];

	/**
	 * Units for bit formatting.
	 * @var array
	 */
	public array $units_bits = ['bit', 'Kbit', 'Mbit', 'Gbit', 'Tbit', 'Pbit', 'Ebit', 'Zbit', 'Ybit'];

	/**
	 * Toggle for event logging.
	 * @var bool
	 */
	public bool $toggle_log_event = true;

	/**
	 * Toggle for error logging.
	 * @var bool
	 */
	public bool $toggle_log_error = true;

	/**
	 * Path to utilities.
	 * @var string
	 */
	public string $utilities_path = '';

	/**
	 * Core path.
	 * @var string|null
	 */
	public ?string $core_path = null;

	/**
	 * Version of utilities.
	 * @var string
	 */
	public string $utilities_version = "1.3.0";

	/**
	 * Current console title.
	 * @var string
	 */
	public string $current_title = '';

	/**
	 * Array of drive letters (Windows).
	 * @var array
	 */
	public array $drives = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

	/**
	 * Null device path for the current system.
	 * @var string
	 */
	public string $device_null;

	/**
	 * UTF-8 Byte Order Mark.
	 * @var string
	 */
	public string $utf8_bom = "\xEF\xBB\xBF";

	/**
	 * Path to resources folder.
	 * @var string
	 */
	public string $resources_folder;

	/**
	 * Abort flag.
	 * @var bool
	 */
	public bool $abort = false;
	
	/**
	 * Map of console color codes.
	 * @var array
	 */
	public array $console_color_map = [
		'0' => ['30', '40'],
		'1' => ['34', '44'],
		'2' => ['32', '42'],
		'3' => ['36', '46'],
		'4' => ['31', '41'],
		'5' => ['35', '45'],
		'6' => ['33', '43'],
		'7' => ['37', '47'],
		'8' => ['90', '100'],
		'9' => ['94', '104'],
		'A' => ['92', '102'],
		'B' => ['96', '106'],
		'C' => ['91', '101'],
		'D' => ['95', '105'],
		'E' => ['93', '103'],
		'F' => ['97', '107'],
	];

	/**
	 * Constructor for the Core class.
	 * Initializes timezone, internal encoding, command, arguments, and base path.
	 *
	 * @param array $arguments Command line arguments.
	 */
	public function __construct(array $arguments){
		\date_default_timezone_set(IntlTimeZone::createDefault()->getID());
		\mb_internal_encoding('UTF-8');
		unset($arguments[0]);
		$this->command = $arguments[1] ?? null;
		if(isset($arguments[1])) unset($arguments[1]);
		$this->arguments = \array_values($arguments);
		$this->path = \realpath($this->get_path(__DIR__."/../../.."));
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			$this->device_null = 'nul';
		} else {
			$this->device_null = '/dev/null';
		}
	}

	/**
	 * Sets the path to the resources folder.
	 *
	 * @param string $path The path to the resources folder.
	 * @return bool True if the folder exists and was set, false otherwise.
	 */
	public function set_resources_folder(string $path) : bool {
		if(!\file_exists($path)) return false;
		$this->resources_folder = $path;
		return true;
	}

	/**
	 * Gets the full path to a resource within the resources folder.
	 *
	 * @param string $name The name of the resource.
	 * @return string The full path to the resource.
	 */
	public function get_resource(string $name) : string {
		return $this->get_path("$this->resources_folder/$name");
	}

	/**
	 * Requires and checks for necessary utilities based on the operating system.
	 * For Windows, it checks for NGC-UTILITIES. For Linux/macOS, it checks for
	 * ffprobe, mkvmerge, and the Imagick extension.
	 */
	public function require_utilities() : void {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			$this->utilities_path = $this->get_path($this->get_variable("%PROGRAMFILES%")."/NGC-UTILITIES");
			$utilities = false;
			if(\file_exists($this->utilities_path)){
				$utilities_main = new IniFile($this->get_path("$this->utilities_path/main.ini"));
				$utilities_imagick = new IniFile($this->get_path("$this->utilities_path/imagick.ini"));
				$utilities_version = $utilities_main->get('APP_VERSION');
				if($utilities_version == $this->utilities_version && $utilities_imagick->get('APP_VERSION') == $this->utilities_version){
					$utilities = true;
					$this->core_path = $this->get_path("$this->utilities_path/core/$utilities_version");
				}
			}

			if(!$utilities){
				$this->echo();
				$this->echo(" Invalid NGC-UTILITIES version detected: v{$utilities_version} required: v$this->utilities_version");
				$this->echo();
				$this->pause();
				die("");
			}
		} else {
			$programs = [
				'ffprobe' => 'ffmpeg',
				'mkvmerge' => 'mkvtoolnix',
			];
			$errors = 0;
			foreach($programs as $program_name => $install_name){
				if(!\file_exists("/usr/bin/$program_name") && !\file_exists("/opt/homebrew/bin/$program_name") && !\file_exists("/usr/local/bin/$program_name")){
					$this->echo("[ERROR] Required $program_name not found, please install $install_name");
					$errors++;
				}
			}
			if(!\extension_loaded('imagick')){
				$this->echo("[ERROR] Imagick is not installed");
				$errors++;
			}
			if($errors > 0){
				$this->abort = true;
				return;
			}
		}
	}

	/**
	 * Allows the user to select an action for the current tool.
	 *
	 * @param ?string $trigger_action An optional action to trigger directly, bypassing user input.
	 * @return bool True if an action was successfully performed, false otherwise.
	 */
	public function select_action(?string $trigger_action = null) : bool {
		if(\is_null($this->tool)) return false;
		do {
			$this->clear();
			$this->title("$this->app_name v$this->version > $this->tool_name");
			if(\is_null($trigger_action)){
				$this->tool->help();
				$line = $this->get_input(" Action: ");
				if($line == '#') return false;
				$response = $this->tool->action($line);
			} else {
				$response = $this->tool->action($trigger_action);
			}
			$trigger_action = null;
		}
		while(!$response);
		return true;
	}

	/**
	 * Sets up and scans the initial state of specified folders.
	 *
	 * @param array $folders An array of folder paths to set up.
	 */
	public function setup_folders(array $folders) : void {
		$this->folders_state = [];
		foreach($folders as $folder){
			$this->folders_state[$folder] = \file_exists($folder) ? '' : '[NOT EXISTS]';
			$this->write_log("Scan: $folder");
		}
		$this->print_folders_state();
	}

	/**
	 * Sets the state of a specific folder to '[DONE]'.
	 *
	 * @param string $folder The path of the folder to mark as done.
	 */
	public function set_folder_done(string $folder) : void {
		$this->folders_state[$folder] = \file_exists($folder) ? '[DONE]' : '[NOT EXISTS]';
		$this->print_folders_state();
	}

	/**
	 * Prints the current state of all monitored folders to the console.
	 */
	public function print_folders_state() : void {
		$this->clear();
		foreach($this->folders_state as $folder_name => $state){
			$this->echo(" Scan: \"$folder_name\" $state");
		}
	}

	/**
	 * Gets the formatted title for the current tool, including app name, version, and subtool if applicable.
	 *
	 * @return string The formatted tool title.
	 */
	public function get_tool_name() : string {
		$title = "$this->app_name v$this->version > $this->tool_name";
		if(!empty($this->subtool_name)) $title .= " > $this->subtool_name";
		return $title;
	}

	/**
	 * Sets the name of the current tool and updates the console title.
	 *
	 * @param string $name The name of the tool.
	 */
	public function set_tool(string $name) : void {
		$this->tool_name = $name;
		$this->subtool_name = '';
		$this->title($this->get_tool_name());
	}

	/**
	 * Sets the name of the current subtool and updates the console title.
	 *
	 * @param string $name The name of the subtool.
	 */
	public function set_subtool(string $name) : void {
		$this->subtool_name = $name;
		$this->title($this->get_tool_name());
	}

	/**
	 * Updates the console title to display the current number of errors.
	 *
	 * @param int $errors The number of errors.
	 */
	public function set_errors(int $errors) : void {
		$this->title($this->get_tool_name()." > Errors: $errors");
	}

	/**
	 * Updates the console title to display extended progress information.
	 *
	 * @param string $label A label for the progress.
	 * @param int $progress The current progress count.
	 * @param int $total The total count for the progress.
	 */
	public function set_progress_ex(string $label, int $progress, int $total) : void {
		$this->title($this->get_tool_name()." > $label: $progress / $total");
	}

	/**
	 * Clears the console screen and displays the application logo if available.
	 */
	public function clear() : void {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			\popen('cls', 'w');
		} else {
			\system('clear');
		}
		if(!empty($this->logo)){
			$this->echo($this->logo);
		} else {
			$this->echo();
		}
	}

	/**
	 * Converts a version string (e.g., "1.2.3") into an integer for comparison.
	 *
	 * @param string $version The version string.
	 * @return int The integer representation of the version.
	 */
	public function get_version_number(string $version) : int {
		$ver = \explode(".", $version);
		return 10000 * \intval($ver[0]) + 100 * \intval($ver[1]) + \intval($ver[2]);
	}

	/**
	 * Prints a help message to the console.
	 *
	 * @param array $help An array of strings representing the help message.
	 */
	public function print_help(array $help) : void {
		$this->echo(\implode("\r\n", $help));
		$this->echo();
	}

	/**
	 * Displays progress percentage on the current console line.
	 *
	 * @param int|float $count The current count.
	 * @param int|float $total The total count.
	 */
	public function progress(int|float $count, int|float $total) : void {
		if($total > 0){
			$percent = \sprintf("%.02f", ($count / $total) * 100.0);
			$this->current_line(" Progress: $percent %");
		}
	}

	/**
	 * Checks if a given label is valid (alphanumeric, underscores, dot, hyphens, spaces, 3-48 characters).
	 *
	 * @param string $label The label to validate.
	 * @return bool True if the label is valid, false otherwise.
	 */
	public function is_valid_label(string $label) : bool {
		return \preg_match('/(?=[a-zA-Z0-9_\-. ]{3,48}$)/i', $label) == 1;
	}

	/**
	 * Displays extended progress with a label on the current console line.
	 *
	 * @param string $label A label for the progress.
	 * @param int|float $count The current count.
	 * @param int|float $total The total count.
	 */
	public function progress_ex(string $label, int|float $count, int|float $total) : void {
		if($total > 0){
			$percent = \sprintf("%.02f", ($count / $total) * 100.0);
			$this->current_line(" $label Progress: $percent %");
		}
	}

	/**
	 * Reads hashes and their corresponding lines from an index file.
	 *
	 * @param string $path The path to the index file.
	 * @param array $keys Reference to an array to store the hashes and lines.
	 * @param bool $progress Whether to display progress while reading.
	 * @return int The number of lines read from the file.
	 */
	public function get_hash_from_idx(string $path, array &$keys, bool $progress) : int {
		if(!\file_exists($path)) return 0;
		$cnt = 0;
		$size = \filesize($path);
		$fp = @\fopen($path, "r");
		if($fp){
			while(($line = \fgets($fp)) !== false){
				$line = \trim($line);
				$hash = \strtoupper(\pathinfo(\str_replace("\\", "/", $line), PATHINFO_FILENAME));
				$keys[$hash] = $line;
				$cnt++;
				if($progress) $this->progress(\ftell($fp), $size);
			}
			\fclose($fp);
		}
		return $cnt;
	}

	/**
	 * Formats a given number of bytes into a human-readable string (e.g., "1.23 GiB").
	 *
	 * @param float|int $bytes The number of bytes.
	 * @param int $precision The number of decimal places to use.
	 * @param bool $dot Whether to use a dot (.) or comma (,) as the decimal separator.
	 * @return string The formatted byte string.
	 */
	public function format_bytes(float|int $bytes, int $precision = 2, bool $dot = true) : string {
		if($bytes > 0){
			$i = \floor(\log($bytes) / \log(1024));
			$res = \sprintf("%.{$precision}f", $bytes / \pow(1024, $i)).' '.$this->units_bytes[$i];
		} else {
			$res = \sprintf("%.{$precision}f", 0).' B';
		}
		if(!$dot) $res = \str_replace(".", ",", $res);
		return $res;
	}

	/**
	 * Formats a given number of bits into a human-readable string (e.g., "1.23 Mbit").
	 *
	 * @param float|int $bits The number of bits.
	 * @param int $precision The number of decimal places to use.
	 * @param bool $dot Whether to use a dot (.) or comma (,) as the decimal separator.
	 * @return string The formatted bit string.
	 */
	public function format_bits(float|int $bits, int $precision = 2, bool $dot = true) : string {
		if($bits > 0){
			$i = \floor(\log($bits) / \log(1000));
			$res = \sprintf("%.{$precision}f", $bits / \pow(1000, $i)).' '.$this->units_bits[$i];
		} else {
			$res = \sprintf("%.{$precision}f", 0).' bit';
		}
		if(!$dot) $res = \str_replace(".", ",", $res);
		return $res;
	}

	/**
	 * Converts a size value from a specified unit to bytes.
	 *
	 * @param int $value The numeric value of the size.
	 * @param string $unit The unit of the size (e.g., 'KiB', 'MiB').
	 * @return int The size in bytes, or -1 if the unit is invalid.
	 */
	public function size_unit_to_bytes(int $value, string $unit) : int {
		$index = \array_search(\strtolower($unit), $this->array_to_lower($this->units_bytes));
		if($index === false) return -1;
		return \intval($value * \pow(1024, $index));
	}

	/**
	 * Converts a time value from a specified unit to seconds.
	 *
	 * @param int $value The numeric value of the time.
	 * @param string $unit The unit of the time (e.g., 'sec', 'min', 'hour', 'day').
	 * @return int The time in seconds.
	 */
	public function time_unit_to_seconds(int $value, string $unit) : int {
		switch(\strtolower($unit)){
			case 'sec': return $value;
			case 'min': return $value * 60;
			case 'hour': return $value * 3600;
			case 'day': return $value * 86400;
		}
		return 0;
	}

	/**
	 * Converts a number of seconds into a formatted time string.
	 *
	 * @param float $seconds The total number of seconds.
	 * @param bool $force_hours Whether to always display hours, even if zero.
	 * @param bool $with_days Whether to include days in the output.
	 * @param bool $with_ms Whether to include milliseconds in the output.
	 * @return string The formatted time string.
	 */
	public function seconds_to_time(float $seconds, bool $force_hours = false, bool $with_days = false, bool $with_ms = false) : string {
		$output = "";
		if($with_days){
			$days = \intval(\floor($seconds / 86400));
			$seconds -= ($days * 86400);
		} else {
			$days = 0;
		}
		$h = \intval(\floor($seconds / 3600));
		$seconds -= $h * 3600;
		$m = \intval(\floor($seconds / 60));
		$seconds -= $m * 60;
		$s = \floor($seconds);
		$seconds -= $s;
		$ms = \round($seconds * 1000);
		if($days > 0){
			$output = "$days:";
		}
		if($h > 0 || $force_hours){
			$output .= \sprintf("%02d:%02d:%02d", $h, $m, $s);
		} else {
			$output .= \sprintf("%02d:%02d", $m, $s);
		}
		if($with_ms){
			$output .= \sprintf(",%03d", $ms);
		}
		return $output;
	}

	/**
	 * Converts a formatted time string into total seconds.
	 *
	 * @param string $time The time string (e.g., "HH:MM:SS", "MM:SS", "D:HH:MM:SS").
	 * @return int The total number of seconds.
	 */
	public function time_to_seconds(string $time) : int {
		$parts = \explode(':', $time);
		$count = \count($parts);
		if($count == 4){
			[$days, $hours, $minutes, $seconds] = $parts;
			return $days * 86400 + $hours * 3600 + $minutes * 60 + $seconds;
		}
		if($count == 3){
			[$hours, $minutes, $seconds] = $parts;
			return $hours * 3600 + $minutes * 60 + $seconds;
		}
		if($count == 2){
			[$minutes, $seconds] = $parts;
			return $minutes * 60 + $seconds;
		}
		return 0;
	}

	/**
	 * Checks if a given folder is empty.
	 *
	 * @param string $path The path to the folder.
	 * @return bool True if the folder is empty or does not exist, false otherwise.
	 */
	public function is_folder_empty(string $path) : bool {
		if(!\file_exists($path)) return true;
		$files = \scandir($path);
		foreach($files as $file){
			if($file == "." || $file == "..") continue;
			return false;
		}
		return true;
	}

	/**
	 * Retrieves a list of files from a given path, with optional filtering.
	 *
	 * @param string $path The directory path to scan.
	 * @param ?array $include_extensions An array of extensions to include (e.g., ['jpg', 'png']). Null for all.
	 * @param ?array $exclude_extensions An array of extensions to exclude. Null for none.
	 * @param ?array $name_filters An array of strings to filter file names by (case-sensitive or insensitive). Null for no name filter.
	 * @param bool $case_sensitive Whether name filtering should be case-sensitive.
	 * @param bool $recursive Whether to scan subdirectories recursively.
	 * @return array An array of full paths to the found files.
	 */
	public function get_files(string $path, ?array $include_extensions = null, ?array $exclude_extensions = null, ?array $name_filters = null, bool $case_sensitive = false, bool $recursive = true) : array {
		if(!\file_exists($path)) return [];
		if(!$case_sensitive && !\is_null($name_filters)){
			$name_filters = $this->array_to_lower($name_filters);
		}
		$data = [];
		$this->scan_dir_safe_extension($path, $data, $include_extensions, $exclude_extensions, $name_filters, $case_sensitive, $recursive);
		\asort($data, SORT_STRING);
		return \array_values($data);
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
	 * @return int Count total processed files.
	 */
	public function process_files(string|array $path, callable $callback, ?array $include_extensions = null, ?array $exclude_extensions = null, ?array $name_filters = null, bool $case_sensitive = false, bool $recursive = true, bool $follow_symlinks = true) : int {
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
			if(!\file_exists($path)) continue;
			$this->scan_dir_safe_extension_process_files($path, $callback, $counter, $include_extensions, $exclude_extensions, $name_filters, $case_sensitive, $recursive, $follow_symlinks);
		}
		return $counter;
	}

	/**
	 * Retrieves a list of folders from a given path, with optional recursion and parent inclusion.
	 *
	 * @param string $path The directory path to scan.
	 * @param bool $with_parent Whether to include the parent directory in the result.
	 * @param bool $recursive Whether to scan subdirectories recursively.
	 * @return array An array of full paths to the found folders.
	 */
	public function get_folders(string $path, bool $with_parent = false, bool $recursive = true) : array {
		if(!\file_exists($path) || !\is_dir($path)) return [];
		$data = [];
		if($with_parent){
			$data[] = $path;
		}
		try {
			$files = @\scandir($path);
		}
		catch(Exception $e){
			return [];
		}
		if($files === false) return $data;
		foreach($files as $file){
			if($file === '.' || $file === '..'){
				continue;
			}
			$full_path = $path.DIRECTORY_SEPARATOR.$file;
			if(\is_dir($full_path) && !\is_link($full_path)){
				$data[] = $full_path;
				if($recursive){
					$data = \array_merge($data, $this->get_folders($full_path, false, $recursive));
				}
			}
		}
		\asort($data, SORT_STRING);
		return \array_values($data);
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

	/**
	 * Closes the application, optionally opening the event log.
	 *
	 * @param bool $open_log Whether to open the event log file on exit.
	 */
	public function close(bool $open_log = false) : void {
		$this->open_logs($open_log, false);
		exit(0);
	}

	/**
	 * Initializes the log file objects (event, error, and data logs).
	 */
	public function init_logs() : void {
		$timestamp = \date("Y-m-d/Y-m-d His");
		$this->log_event = new Logs($this->get_path($this->config->get('LOG_FOLDER')."/$timestamp-Event.txt"), true, true);
		$this->log_error = new Logs($this->get_path($this->config->get('LOG_FOLDER')."/$timestamp-Error.txt"), true, true);
		$this->log_data = new Logs($this->get_path($this->config->get('DATA_FOLDER')."/$timestamp.txt"), false, true);
	}

	/**
	 * Closes current log files and optionally opens them in an external viewer.
	 *
	 * @param bool $open_event Whether to open the event log.
	 * @param bool $init Whether to re-initialize the logs after closing.
	 */
	public function open_logs(bool $open_event = false, bool $init = true) : void {
		$this->log_event->close();
		$this->log_error->close();
		$this->log_data->close();
		if($this->config->get('OPEN_LOG_EVENT', true) && $open_event && \file_exists($this->log_event->get_path())){
			$this->open_file($this->log_event->get_path());
		}
		if(\file_exists($this->log_data->get_path())){
			$this->open_file($this->log_data->get_path());
		}
		if(\file_exists($this->log_error->get_path())){
			$this->open_file($this->log_error->get_path());
		}
		if($init) $this->init_logs();
	}

	/**
	 * Writes data to the event log.
	 *
	 * @param string|array $data The data to write.
	 */
	public function write_log(string|array $data) : void {
		if($this->config->get('LOG_EVENT', true) && $this->toggle_log_event){
			$this->log_event->write($data);
		}
	}

	/**
	 * Writes data to the error log.
	 *
	 * @param string|array $data The data to write.
	 */
	public function write_error(string|array $data) : void {
		if($this->config->get('LOG_ERROR', true) && $this->toggle_log_error){
			$this->log_error->write($data);
		}
	}

	/**
	 * Writes data to the data log.
	 *
	 * @param string|array $data The data to write.
	 */
	public function write_data(string|array $data) : void {
		$this->log_data->write($data);
	}

	/**
	 * Recursively removes a directory and its contents.
	 *
	 * @param string $dir The path to the directory to remove.
	 * @param bool $log Whether to log the operations.
	 * @return bool True on success, false if the directory does not exist.
	 */
	public function rrmdir(string $dir, bool $log = true) : bool {
		if(!\file_exists($dir)) return false;
		if(\is_dir($dir)){
			$items = \scandir($dir);
			foreach($items as $item){
				if($item == "." || $item == "..") continue;
				$subdir = $this->get_path("$dir/$item");
				if(\is_dir($subdir) && !\is_link($subdir)){
					$this->rrmdir($subdir, $log);
				} else {
					$this->delete($subdir, $log);
				}
			}
			$this->rmdir($dir, false);
		}
		return true;
	}

	/**
	 * Removes an empty directory.
	 *
	 * @param string $path The path to the directory to remove.
	 * @param bool $log Whether to log the operation.
	 * @return bool True on success, false on failure or if the directory does not exist/is not a directory.
	 */
	public function rmdir(string $path, bool $log = true) : bool {
		if(!\file_exists($path) || !\is_dir($path)) return false;
		if(@\rmdir($path)){
			if($log) $this->write_log("DELETE \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED RMDIR \"$path\"");
			return false;
		}
	}

	/**
	 * Removes empty directories recursively from a given path.
	 *
	 * @param string $path The starting path to clean.
	 * @param bool $log Whether to log the operations.
	 * @return bool True if the starting path is removed (if it becomes empty), false otherwise.
	 */
	public function rmdir_empty(string $path, bool $log = true) : bool {
		if(!\file_exists($path) || !\is_dir($path)) return false;
		$files = \array_reverse($this->get_folders($path, true));
		foreach($files as $file){
			if(!\file_exists($file)) continue;
			$count = \iterator_count(new FilesystemIterator($file, FilesystemIterator::SKIP_DOTS));
			if($count == 0){
				$this->rmdir($file, $log);
			}
		}
		return !\file_exists($path);
	}

	/**
	 * Deletes a file.
	 *
	 * @param string $path The path to the file to delete.
	 * @param bool $log Whether to log the operation.
	 * @return bool True on success, false on failure or if the file does not exist/is a directory.
	 */
	public function delete(string $path, bool $log = true) : bool {
		if(!\file_exists($path) || \is_dir($path)) return false;
		if(@\unlink($path)){
			if($log) $this->write_log("DELETE \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED DELETE \"$path\"");
			return false;
		}
	}

	/**
	 * Creates a directory.
	 *
	 * @param string $path The path of the directory to create.
	 * @param bool $log Whether to log the operation.
	 * @param int $permissions The permissions for the new directory (default 0755).
	 * @return bool True on success, false on failure or if the directory already exists and is not a directory.
	 */
	public function mkdir(string $path, bool $log = true, int $permissions = 0755) : bool {
		if(\file_exists($path) && \is_dir($path)) return true;
		if(@\mkdir($path, $permissions, true)){
			if($log) $this->write_log("MKDIR \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED MKDIR \"$path\"");
			return false;
		}
	}

	/**
	 * Clones the folder structure from an input path to an output path.
	 *
	 * @param string $input The input directory path.
	 * @param string $output The output directory path.
	 * @return int|false The number of errors encountered, or false on input path error.
	 */
	public function clone_folder_structure(string $input, string $output) : int|false {
		if(!\file_exists($input) || !\is_dir($input)) return false;
		$errors = 0;
		$folders = $this->get_folders($input);
		foreach($folders as $folder){
			$directory = \str_ireplace($input, $output, $folder);
			if(!\file_exists($directory)){
				if(!$this->mkdir($directory)){
					$errors++;
				}
			}
		}
		return $errors;
	}

	/**
	 * Moves a file or directory from one location to another.
	 *
	 * @param string $from The source path.
	 * @param string $to The destination path.
	 * @param bool $log Whether to log the operation.
	 * @return bool True on success, false on failure.
	 */
	public function move(string $from, string $to, bool $log = true) : bool {
		if(!\file_exists($from)) return false;
		if(\file_exists($to) && \pathinfo($from, PATHINFO_DIRNAME) != \pathinfo($to, PATHINFO_DIRNAME)){
			if($log) $this->write_error("FAILED RENAME \"$from\" \"$to\" FILE EXIST");
			return false;
		}
		$dir = \pathinfo($to, PATHINFO_DIRNAME);
		if(!\file_exists($dir)) $this->mkdir($dir);
		$modification_date = \filemtime($from);
		if(@\rename($from, $to)){
			\touch($to, $modification_date);
			if($log) $this->write_log("RENAME \"$from\" \"$to\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED MOVE \"$from\" \"$to\"");
			return false;
		}
	}

	/**
	 * Moves a file or directory, handling case-sensitive renaming on case-insensitive file systems.
	 *
	 * @param string $from The source path.
	 * @param string $to The destination path.
	 * @param bool $log Whether to log the operation.
	 * @return bool True on success, false on failure.
	 */
	public function move_case(string $from, string $to, bool $log = true) : bool {
		if(!\file_exists($from)) return false;
		if(\strcmp($from, $to) == 0) return true;
		$dir = \pathinfo($to, PATHINFO_DIRNAME);
		if(!\file_exists($dir)) $this->mkdir($dir);
		$modification_date = \filemtime($from);
		if(@\rename($from, $to)){
			\touch($to, $modification_date);
			if($log) $this->write_log("RENAME \"$from\" \"$to\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED RENAME \"$from\" \"$to\"");
			return false;
		}
	}

	/**
	 * Copies a file from one location to another.
	 *
	 * @param string $from The source file path.
	 * @param string $to The destination file path.
	 * @param bool $log Whether to log the operation.
	 * @return bool True on success, false on failure.
	 */
	public function copy(string $from, string $to, bool $log = true) : bool {
		if(!\file_exists($from)) return false;
		if($this->same_path($from, $to)) return true;
		if(\file_exists($to) && \pathinfo($from, PATHINFO_DIRNAME) != \pathinfo($to, PATHINFO_DIRNAME)){
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" FILE EXIST");
			return false;
		}
		$dir = \pathinfo($to, PATHINFO_DIRNAME);
		if(!\file_exists($dir)) $this->mkdir($dir);
		$modification_date = \filemtime($from);
		if(@\copy($from, $to)){
			\touch($to, $modification_date);
			if($log) $this->write_log("COPY \"$from\" \"$to\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\"");
			return false;
		}
	}

	/**
	 * Copies a file from one location to another using a buffer and file allocation.
	 *
	 * @param string $from The source file path.
	 * @param string $to The destination file path.
	 * @param bool $log Whether to log the operation.
	 * @return bool True on success, false on failure.
	 */
	public function acopy(string $from, string $to, bool $log = true) : bool {
		if(!\file_exists($from)) return false;
		if($this->same_path($from, $to)) return true;
		$write_buffer = $this->get_write_buffer();
		if(!$write_buffer) return false;
		if(\file_exists($to) && \pathinfo($from, PATHINFO_DIRNAME) != \pathinfo($to, PATHINFO_DIRNAME)){
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" FILE EXISTS");
			return false;
		}
		$dir = \pathinfo($to, PATHINFO_DIRNAME);
		if(!\file_exists($dir)) $this->mkdir($dir);
		$modification_date = \filemtime($from);
		$filesize = \filesize($from);
		$source = @\fopen($from, 'rb');
		$destination = @\fopen($to, 'wb');
		if(!$source || !$destination){
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" (cannot open files)");
			return false;
		}
		try {
			@\ftruncate($destination, $filesize);
			while(!\feof($source)){
				$buffer = @\fread($source, $write_buffer);
				if($buffer === false) throw new Exception("Failed read block");
				$state = @\fwrite($destination, $buffer);
				if($state === false) throw new Exception("Failed write block");
			}
		}
		catch(Exception $e){
			\fclose($source);
			\fclose($destination);
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" ".$e->getMessage());
			return false;
		}
		\fclose($source);
		\fclose($destination);
		if(!\file_exists($to)){
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" (output not exists)");
			return false;
		}
		@\touch($to, $modification_date);
		if($log) $this->write_log("COPY \"$from\" \"$to\"");
		return true;
	}

	/**
	 * Copies a file from one location to another using a buffer, file allocation and destination comparsion for reduce writing in SSD drives.
	 *
	 * @param string $from The source file path.
	 * @param string $to The destination file path.
	 * @param int $block_size Block size for read/write operations
	 * @param bool $log Whether to log the operation.
	 * @return bool True on success, false on failure.
	 */
	public function acopy_ssd(string $from, string $to, int $block_size = 4096, bool $log = true) : bool {
		if(!\file_exists($from)) return false;
		if(!\file_exists($to)) return $this->acopy($from, $to, $log);
		if($this->same_path($from, $to)) return true;
		$dir = \pathinfo($to, PATHINFO_DIRNAME);
		if(!\file_exists($dir)) $this->mkdir($dir);
		$modification_date = \filemtime($from);
		$filesize = \filesize($from);
		$source = @\fopen($from, 'rb');
		$destination = @\fopen($to, 'r+b');
		if(!$source || !$destination){
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" (cannot open files)");
			return false;
		}
		try {
			@\ftruncate($destination, $filesize);
			$offset = 0;
			while(!\feof($source)){
				\fseek($destination, $offset);
				$data_source = @\fread($source, $block_size);
				if($data_source === false) throw new Exception("Failed read block on source");
				$data_destination = @\fread($destination, $block_size);
				if($data_destination === false) throw new Exception("Failed read block on destination");
				$hash_source = \hash('md5', $data_source);
				$hash_destination = \hash('md5', $data_destination);
				if($hash_source !== $hash_destination){
					\fseek($destination, $offset);
					$state = @\fwrite($destination, $data_source);
					if($state === false) throw new Exception("Failed write block");
				}
				$offset += $block_size;
			}
		}
		catch(Exception $e){
			\fclose($source);
			\fclose($destination);
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" ".$e->getMessage());
			return false;
		}
		\fclose($source);
		\fclose($destination);
		@\touch($to, $modification_date);
		if($log) $this->write_log("COPY \"$from\" \"$to\"");
		return true;
	}

	/**
	 * Compare two path are identical
	 * @param string $from The source file path.
	 * @param string $to The destination file path.
	 * @return bool True when path are identical, false if not.
	 */
	public function same_path(string $from, string $to) : bool {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			if(\mb_strtolower($from) == \mb_strtolower($to)) return true;
		}
		if($from == $to) return true;
		return false;
	}

	/**
	 * Deletes files within a directory, with optional extension filters.
	 *
	 * @param string $path The directory path to scan for files.
	 * @param ?array $include_extensions An array of extensions to include. Null for all.
	 * @param ?array $exclude_extensions An array of extensions to exclude. Null for none.
	 */
	public function delete_files(string $path, ?array $include_extensions = null, ?array $exclude_extensions = null) : void {
		$files = $this->get_files($path, $include_extensions, $exclude_extensions);
		foreach($files as $file){
			$this->delete($file);
		}
	}

	/**
	 * Escapes special characters for command line usage (specifically for `cmd.exe`).
	 *
	 * @param string $text The text to escape.
	 * @return string The escaped text.
	 */
	public function cmd_escape(string $text) : string {
		return \str_replace([">", "<"], ["^>", "^<"], $text);
	}

	/**
	 * Sets the console window title (Windows only).
	 *
	 * @param string $title The title to set.
	 */
	public function title(string $title) : void {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			$title = $this->cmd_escape($title);
			if($this->current_title != $title){
				$this->current_title = $title;
				\system("TITLE $title");
			}
		}
	}

	/**
	 * Prompts the user for a yes/no confirmation.
	 *
	 * @param string $question The question to ask.
	 * @return bool True if the user confirms with 'Y', false with 'N'.
	 */
	public function get_confirm(string $question) : bool {
		ask_confirm:
		$answer = \strtoupper($this->get_input($question));
		if(!\in_array($answer, ['Y', 'N'])) goto ask_confirm;
		return $answer == 'Y';
	}

	/**
	 * Gets input from the user via the command line.
	 *
	 * @param ?string $message The message to display as a prompt.
	 * @param bool $trim Whether to trim whitespace from the input.
	 * @param bool $history Whether to add the input to readline history.
	 * @return string The user's input.
	 */
	public function get_input(?string $message = null, bool $trim = true, bool $history = true) : string {
		$line = \readline($message);
		if($line === false){
			$this->write_error("Failed readline from prompt");
			$this->close();
			return '';
		}
		if($trim) $line = \trim($line);
		if($history) \readline_add_history($line);
		return $line;
	}

	/**
	 * Gets a password input from the user (input is masked).
	 *
	 * @param string $message The message to display as a prompt.
	 * @return string The user's password.
	 */
	public function get_input_password(string $message) : string {
		echo $message;
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			$color = $this->config->get('COLOR');
			$this->set_console_color(\substr($color, 0, 1).\substr($color, 0, 1));
			$password = \readline();
			$this->set_console_color($color);
			echo "\033[1A$message".\str_repeat("*", \strlen($password))."\r\n";
		} else {
			\system('stty -echo');
			$password = \fgets(STDIN);
			\system('stty echo');
			echo "\r\n";
		}
		return \rtrim($password, "\r\n");
	}

	/**
	 * Prompts the user for multiple folder paths.
	 *
	 * @param string $title The message to display as a prompt.
	 * @param bool $setup Whether to call `setup_folders` with the parsed folders.
	 * @return array|false An array of folder paths, or false if the user cancels.
	 */
	public function get_input_multiple_folders(string $title, bool $setup = true) : array|false {
		$line = $this->get_input($title);
		if($line == '#') return false;
		$folders = $this->parse_input_path($line);
		if($setup) $this->setup_folders($folders);
		return $folders;
	}

	/**
	 * Prompts the user for a single folder path.
	 *
	 * @param string $title The message to display as a prompt.
	 * @param bool $as_output Whether the folder is intended as an output directory (will attempt to create it).
	 * @return string|false The path to the selected folder, or false if the user cancels.
	 */
	public function get_input_folder(string $title, bool $as_output = false) : string|false {
		set_path:
		$line = $this->get_input($title);
		if($line == '#') return false;
		$folders = $this->parse_input_path($line);
		if(!isset($folders[0])) goto set_path;
		$path = $folders[0];
		if(\file_exists($path) && !\is_dir($path)){
			$this->echo(" Invalid folder path");
			goto set_path;
		}
		if($as_output && !$this->mkdir($path)){
			$this->echo(" Failed create folder");
			goto set_path;
		}
		if(!\file_exists($path)){
			$this->echo(" Folder not exists");
			goto set_path;
		}
		return $path;
	}

	/**
	 * Prompts the user for a single file path.
	 *
	 * @param string $title The message to display as a prompt.
	 * @param bool $required Whether the file must exist.
	 * @param bool $create_directory Whether to create the parent directory if it doesn't exist.
	 * @return string|false The path to the selected file, or false if the user cancels.
	 */
	public function get_input_file(string $title, bool $required = true, bool $create_directory = false) : string|false {
		set_path:
		$line = $this->get_input($title);
		if($line == '#') return false;
		$files = $this->parse_input_path($line);
		if(!isset($files[0])) goto set_path;
		$path = $files[0];
		if(\file_exists($path) && \is_dir($path)){
			$this->echo(" Invalid file path");
			goto set_path;
		}
		if($required && !\file_exists($path)){
			$this->echo(" Input file not exists");
			goto set_path;
		}
		if($create_directory){
			$directory = \pathinfo($path, PATHINFO_DIRNAME);
			if(!\file_exists($directory) && !$this->mkdir($directory)){
				$this->echo(" Failed create destination directory \"$directory\"");
				goto set_path;
			}
		}
		return $path;
	}

	/**
	 * Prompts the user for file extensions, separated by spaces.
	 *
	 * @param string $title The message to display as a prompt.
	 * @param ?string $help_message An optional help message to display before the prompt.
	 * @return array|null|false An array of extensions, null if empty input, or false if the user cancels.
	 */
	public function get_input_extensions(string $title, ?string $help_message = " Empty for all, separate with spaces for multiple") : array|null|false {
		if(!\is_null($help_message)) $this->echo($help_message);
		$line = $this->get_input($title);
		if($line == '#') return false;
		if(empty($line)) return null;
		return \explode(" ", $line);
	}

	/**
	 * Pauses the execution and waits for user input (e.g., "Press any key to continue...").
	 *
	 * @param ?string $message An optional message to display before pausing.
	 */
	public function pause(?string $message = null) : void {
		if(!\is_null($message)) echo $message;
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			\system("PAUSE > nul");
		} else {
			$this->get_input();
		}
	}

	/**
	 * Prints a string to the console, with optional color.
	 *
	 * @param string $string The string to print.
	 * @param ?string $color_code The color code (e.g., '0F' for black background, white foreground). Null for default.
	 */
	public function echo(string $string = '', ?string $color_code = null) : void {
		if(!\is_null($color_code)) $this->set_console_color($color_code);
		echo "$string\r\n";
		if(!\is_null($color_code)) $this->set_console_color("XX");
	}

	/**
	 * Prints a string to the console with embedded color codes.
	 * Color codes are in the format {XY} where X is background and Y is foreground.
	 * Example: "This is {4F}red on blue{XX}."
	 *
	 * @param string $string The string to print with embedded color codes.
	 */
	public function cecho(string $string = '') : void {
		$output = '';
		$offset = 0;
		while(\preg_match('/\{([0-9A-Fa-f]{2}|XX)\}/', $string, $match, PREG_OFFSET_CAPTURE, $offset)){
			$output .= \substr($string, $offset, (int)$match[0][1] - (int)$offset);
			$output .= $this->convert_color_to_ansi($match[1][0]);
			$offset = $match[0][1] + 4;
		}
		$output .= \substr($string, $offset);
		echo "$output\r\n";
		$this->set_console_color("XX");
	}

	/**
	 * Prints a string to the current console line, overwriting previous content.
	 * Useful for progress updates.
	 *
	 * @param string $string The string to print.
	 */
	public function current_line(string $string = '') : void {
		echo "$string".\str_repeat(" ", (int)\max(62 - \strlen($string), 0))."\r";
	}

	/**
	 * Prints a variable's contents in a human-readable format (similar to `var_dump` but formatted).
	 *
	 * @param mixed $var The variable to print.
	 * @param bool $add_space Whether to add a leading space to each line.
	 */
	public function print(mixed $var, bool $add_space = false) : void {
		echo $this->get_print($var, 0, $add_space);
	}

	/**
	 * Returns a string representation of a variable's contents in a human-readable format.
	 *
	 * @param mixed $var The variable.
	 * @param int $indent The current indentation level.
	 * @param bool $add_space Whether to add a leading space to each line.
	 * @return string The formatted string representation.
	 */
	public function get_print(mixed $var, int $indent = 0, bool $add_space = false) : string {
		$output = '';
		$prefix = \str_repeat("\t", $indent);
		if($add_space) $prefix = " $prefix";
		if(\is_array($var)){
			if(empty($var)){
				$output .= "{$prefix}(array) []\n";
			} else {
				$output .= "{$prefix}(array) [\n";
				foreach($var as $key => $value){
					if(!\is_numeric($key)) $key = "'$key'";
					$output .= "$prefix\t$key => ".\ltrim($this->get_print($value, $indent + 1, $add_space));
				}
				$output .= "$prefix]\n";
			}
		} elseif(\is_object($var)){
			$class = \get_class($var);
			if(empty($var)){
				$output .= "{$prefix}($class){}\n";
			} else {
				$output .= "{$prefix}($class){\n";
				foreach(\get_object_vars($var) as $key => $value){
					if(!\is_numeric($key)) $key = "'$key'";
					$output .= "$prefix\t$key => ".\ltrim($this->get_print($value, $indent + 1, $add_space));
				}
				$output .= "$prefix}\n";
			}
		} else {
			$type = \strtolower(\gettype($var));
			switch($type){
				case 'integer': {
					$type = 'int';
					break;
				}
				case 'boolean': {
					$type = 'bool';
					break;
				}
			}
			if($indent > 0){
				$output .= "$prefix\t($type) ".\var_export($var, true)."\n";
			} else {
				$output .= "($type) ".\var_export($var, true)."\n";
			}
		}
		return $output;
	}

	/**
	 * Gets the value of an environment variable (Windows only).
	 *
	 * @param string $string The name of the environment variable (e.g., "%PROGRAMFILES%").
	 * @return string The value of the environment variable, or an empty string if not found.
	 */
	public function get_variable(string $string) : string {
		\exec("echo $string", $var);
		return $var[0] ?? '';
	}

	/**
	 * Opens a file using the default system application (Windows) or a configured binary.
	 *
	 * @param string $path The path to the file.
	 * @param string $params Additional parameters for the open command (Windows only, e.g., '/MIN').
	 */
	public function open_file(string $path, string $params = '/MIN') : void {
		if(\file_exists($path)){
			if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
				\exec("START $params \"\" \"$path\"");
			} elseif(!\is_null($this->config->get('OPEN_FILE_BINARY'))){
				\exec($this->config->get('OPEN_FILE_BINARY')." \"$path\"");
			} else {
				$this->write_error("Failed open file OPEN_FILE_BINARY is not configured");
			}
		}
	}

	/**
	 * Opens a URL in the default web browser.
	 *
	 * @param string $url The URL to open.
	 */
	public function open_url(string $url) : void {
		if(\str_contains($url, "https://") || \str_contains($url, "http://")){
			if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
				\exec("START \"\" \"$url\"");
			} elseif(!\is_null($this->config->get('OPEN_FILE_BINARY'))){
				\exec($this->config->get('OPEN_FILE_BINARY')." \"$url\"");
			} else {
				$this->write_error("Failed open url OPEN_FILE_BINARY is not configured");
			}
		}
	}

	/**
	 * Gets the file attributes of a given path (Windows only).
	 *
	 * @param string $path The path to the file or directory.
	 * @return array An associative array of attributes ('R', 'A', 'S', 'H', 'I') and their boolean states.
	 */
	public function get_file_attributes(string $path) : array {
		$path = $this->get_path($path);
		if($this->get_system_type() != SYSTEM_TYPE_WINDOWS || !\file_exists($path)) return ['R' => false, 'A' => false, 'S' => false, 'H' => false, 'I' => false];
		$attributes = \substr(\shell_exec("attrib \"$path\""), 0, 21);
		return [
			'R' => \str_contains($attributes, "R"),
			'A' => \str_contains($attributes, "A"),
			'S' => \str_contains($attributes, "S"),
			'H' => \str_contains($attributes, "H"),
			'I' => \str_contains($attributes, "I"),
		];
	}

	/**
	 * Sets the file attributes of a given path (Windows only).
	 *
	 * @param string $path The path to the file or directory.
	 * @param ?bool $r Read-only attribute. Null to not change.
	 * @param ?bool $a Archive attribute. Null to not change.
	 * @param ?bool $s System attribute. Null to not change.
	 * @param ?bool $h Hidden attribute. Null to not change.
	 * @param ?bool $i Not-content-indexed attribute. Null to not change.
	 * @return bool True on success, false on failure or if not on Windows or file does not exist.
	 */
	public function set_file_attributes(string $path, ?bool $r = null, ?bool $a = null, ?bool $s = null, ?bool $h = null, ?bool $i = null) : bool {
		if($this->get_system_type() != SYSTEM_TYPE_WINDOWS || !\file_exists($path)) return false;
		$attributes = '';
		$params = '';
		if(!\is_null($r)) $attributes .= ($r ? '+' : '-').'R ';
		if(!\is_null($a)) $attributes .= ($a ? '+' : '-').'A ';
		if(!\is_null($s)) $attributes .= ($s ? '+' : '-').'S ';
		if(!\is_null($h)) $attributes .= ($h ? '+' : '-').'H ';
		if(!\is_null($i)) $attributes .= ($i ? '+' : '-').'I ';
		if(\is_link($path)) $params .= '/L ';
		\shell_exec("attrib {$params}{$attributes} \"$path\"");
		return true;
	}

	/**
	 * Checks if a given path is a valid Windows path (drive letter or UNC path).
	 *
	 * @param string $path The path to validate.
	 * @return bool True if the path is valid, false otherwise. Returns true for non-Windows systems.
	 */
	public function is_valid_path(string $path) : bool {
		if($this->get_system_type() != SYSTEM_TYPE_WINDOWS) return true;
		if(\strlen($path) >= 2 && $path[1] === ':' && \ctype_alpha($path[0])){
			return \file_exists(\substr($path, 0, 3));
		} elseif(\substr($path, 0, 2) == "\\\\"){
			$device = \substr($path, 2);
			if(\str_contains($device, "\\")){
				$parts = \explode("\\", $device);
				if(\count($parts) >= 2){
					return \is_dir("\\\\{$parts[0]}\\{$parts[1]}");
				}
			}
		}
		return false;
	}

	/**
	 * Converts a path to use the correct directory separator for the current operating system.
	 *
	 * @param string $path The path to convert.
	 * @return string The converted path.
	 */
	public function get_path(string $path) : string {
		return \str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
	}

	/**
	 * Gets the lowercase file extension from a given path.
	 *
	 * @param string $path The file path.
	 * @return string The lowercase file extension.
	 */
	public function get_extension(string $path) : string {
		return \mb_strtolower(\pathinfo($path, PATHINFO_EXTENSION));
	}

	/**
	 * Inserts a subfolder into a given file path.
	 * Example: `put_folder_to_path("/path/to/file.txt", "sub")` returns `/path/to/sub/file.txt`.
	 *
	 * @param string $path The original file path.
	 * @param string $subfolder The name of the subfolder to insert.
	 * @return string The modified file path.
	 */
	public function put_folder_to_path(string $path, string $subfolder) : string {
		return $this->get_path(\pathinfo($path, PATHINFO_DIRNAME)."/$subfolder/".\pathinfo($path, PATHINFO_BASENAME));
	}

	/**
	 * Gets the computer name of the current system.
	 *
	 * @return string The computer name.
	 */
	public function get_computer_name() : string {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			return \trim($this->get_variable("%COMPUTERNAME%"));
		} else {
			return \trim(\shell_exec('hostname'));
		}
	}

	/**
	 * Formats an array of paths for command-line arguments, enclosing them in double quotes if necessary.
	 *
	 * @param array $arguments An array of paths.
	 * @return string A space-separated string of quoted paths.
	 */
	public function get_arguments_folders(array $arguments) : string {
		$output = '';
		foreach($arguments as $argument){
			$argument = $this->get_path($argument);
			if(\substr($argument, 0, 1) == '"'){
				$output .= " $argument";
			} else {
				$output .= " \"$argument\"";
			}
		}
		return $output;
	}

	/**
	 * Returns information about a hashing algorithm based on its ID.
	 *
	 * @param int $id The ID of the hash algorithm (0: md5, 1: sha256, 2: crc32, 3: whirlpool).
	 * @return array An associative array with 'name' and 'length' of the hash. Defaults to md5.
	 */
	public function get_hash_alghoritm(int $id) : array {
		switch($id){
			case 0: return ['name' => 'md5', 'length' => 32];
			case 1: return ['name' => 'sha256', 'length' => 64];
			case 2: return ['name' => 'crc32', 'length' => 8];
			case 3: return ['name' => 'whirlpool', 'length' => 128];
		}
		return ['name' => 'md5', 'length' => 32];
	}

	/**
	 * Checks if a file is a text file based on its MIME type.
	 *
	 * @param string $path The path to the file.
	 * @return bool True if the file is a text file, false otherwise or if it doesn't exist.
	 */
	public function is_text_file(string $path) : bool {
		if(!\file_exists($path)) return false;
		$finfo = \finfo_open(FILEINFO_MIME);
		return \substr(\finfo_file($finfo, $path), 0, 4) == 'text';
	}

	/**
	 * Executes an external program. For Windows, it attempts to use a program within `core_path`.
	 *
	 * @param string $program The name of the program to execute.
	 * @param string $command The command-line arguments for the program.
	 * @param ?array $output Reference to an array to capture the output lines.
	 * @param ?int $result_code Reference to an integer to capture the exit code.
	 * @return string|false The last line of the command output on success, or false on failure.
	 */
	public function exec(string $program, string $command, ?array &$output = null, ?int &$result_code = null) : string|false {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			if(\is_null($this->core_path)) return false;
			$program = $this->get_path("$this->core_path/$program.exe");
		}
		return \exec("\"$program\" $command", $output, $result_code);
	}

	/**
	 * Checks if the current script is running with administrator/root privileges (Windows only).
	 *
	 * @return bool True if running as admin, false otherwise. Returns false for non-Windows systems.
	 */
	public function is_admin() : bool {
		if($this->get_system_type() != SYSTEM_TYPE_WINDOWS) return false;
		return \exec('net session 1>NUL 2>NUL || (ECHO NO_ADMIN)') != 'NO_ADMIN';
	}

	/**
	 * Prompts the user to input a byte size with a unit (e.g., "1 GiB").
	 *
	 * @param string $name The message to display as a prompt.
	 * @return int|false The size in bytes, or false if the user cancels or input is invalid.
	 */
	public function get_input_bytes_size(string $name) : int|false {
		set_size:
		$this->print_help([
			' Type integer and unit separate by space, example: 1 GiB',
			' Size units: B, KiB, MiB, GiB, TiB',
		]);

		$line = $this->get_input($name);
		if($line == '#') return false;
		$size = \explode(' ', $line);
		if(!isset($size[1])) goto set_size;
		$size[0] = \preg_replace('/\D/', '', $size[0]);
		if(empty($size[0])) goto set_size;
		$bytes = $this->size_unit_to_bytes(\intval($size[0]), $size[1]);
		if($bytes <= 0) goto set_size;
		return $bytes;
	}

	/**
	 * Prompts the user to input a time interval with a unit (e.g., "30 sec").
	 *
	 * @param string $name The message to display as a prompt.
	 * @return int|false The interval in seconds, or false if the user cancels or input is invalid.
	 */
	public function get_input_time_interval(string $name) : int|false {
		set_interval:
		$this->print_help([
			' Type integer and unit separate by space, example: 30 sec',
			' Interval units: sec, min, hour, day',
		]);

		$line = $this->get_input($name);
		if($line == '#') return false;
		$size = \explode(' ', $line);
		if(!isset($size[1])) goto set_interval;
		$size[0] = \preg_replace('/\D/', '', $size[0]);
		if(empty($size[0])) goto set_interval;
		$interval = $this->time_unit_to_seconds(\intval($size[0]), $size[1]);
		if($interval <= 0) goto set_interval;
		return $interval;
	}

	/**
	 * Prompts the user to input an integer within a specified range.
	 *
	 * @param string $name The message to display as a prompt.
	 * @param int $min The minimum allowed integer value (default 1).
	 * @param int $max The maximum allowed integer value (default 2147483647).
	 * @return int|false The validated integer, or false if the user cancels or input is invalid.
	 */
	public function get_input_integer(string $name, int $min = 1, int $max = 2147483647) : int|false {
		set_number:
		$line = $this->get_input($name);
		if($line == '#') return false;
		$line = \preg_replace('/\D/', '', $line);
		if($line == ''){
			$this->echo(" Type valid integer number");
			goto set_number;
		}
		$number = \intval($line);
		if($number < $min){
			$this->echo(" Number must be have greater than or equal $min");
			goto set_number;
		} elseif($number > $max){
			$this->echo(" Number must be have less than or equal $max");
			goto set_number;
		}
		return $number;
	}

	/**
	 * Gets the configured write buffer size in bytes.
	 *
	 * @return int|bool The write buffer size in bytes, or false if the configuration value is invalid.
	 */
	public function get_write_buffer() : int|bool {
		$size = \explode(' ', $this->config->get('WRITE_BUFFER_SIZE'));
		$write_buffer = $this->size_unit_to_bytes(\intval($size[0]), $size[1] ?? '?');
		if($write_buffer <= 0){
			$this->clear();
			$write_buffer_size = $this->config->get('WRITE_BUFFER_SIZE');
			$this->pause(" Operation aborted: invalid config value for WRITE_BUFFER_SIZE=\"$write_buffer_size\", press any key to back to menu.");
			return false;
		}
		return $write_buffer;
	}

	/**
	 * Moves a file or folder to the system's trash/recycle bin.
	 *
	 * @param string $path The path to the file or folder to trash.
	 * @param ?string $trash_folder The path to the trash folder
	 * @return bool True on success, false on failure.
	 */
	public function trash(string $path, ?string $trash_folder = null) : bool {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			if(\substr($path, 1, 1) == ':'){
				$relative_path = \substr($path, 3);
				if(\is_null($trash_folder)){
					$new_name = $this->get_path(\substr($path, 0, 2)."/.Trash/$relative_path");
				} else {
					$new_name = $this->get_path("$trash_folder/$relative_path");
				}
				if(\file_exists($new_name) && !$this->delete($new_name)) return false;
				return $this->move($path, $new_name);
			} elseif(\substr($path, 0, 2) == "\\\\"){
				$device = \substr($path, 2);
				if(\str_contains($device, "\\")){
					$relative_path = \str_replace("\\\\$device", "", $path);
					if(\is_null($trash_folder)){
						$new_name = $this->get_path("$device/.Trash/$relative_path");
					} else {
						$new_name = $this->get_path("$trash_folder/$relative_path");
					}
					if(\file_exists($new_name) && !$this->delete($new_name)) return false;
					return $this->move($path, $new_name);
				}
			}
		} else {
			$output = [];
			$return_var = 0;
			\exec("gio trash ".\escapeshellarg($path), $output, $return_var);
			return $return_var === 0;
		}
		$this->write_error("FAILED TRASH \"$path\"");
		return false;
	}

	/**
	 * Displays a message indicating that a tool is Windows-only and pauses.
	 *
	 * @return bool Always returns false.
	 */
	public function windows_only() : bool {
		$this->echo(" This tool is only available on windows operating system");
		$this->pause(" Press any key to back to menu");
		return false;
	}

	/**
	 * Calculates the original length of a base64 encoded string.
	 *
	 * @param string $string The base64 encoded string.
	 * @return ?int The original length in bytes, or null if the string is not a valid base64 representation.
	 */
	public function base64_length(string $string) : ?int {
		$string = \trim(\str_replace(["\r", "\n"], "", $string));
		$base64_length = \strlen($string);
		$padding_length = \substr_count($string, '=');
		$original_length = ($base64_length / 4) * 3 - $padding_length;
		if($original_length != (int)$original_length) return null;
		return $original_length;
	}

	/**
	 * Cleans a file name by replacing invalid characters with underscores.
	 *
	 * @param string $name The file name to clean.
	 * @return string The cleaned file name.
	 */
	public function clean_file_name(string $name) : string {
		return \str_replace(["\\", '/', ':', '*', '?', '"', '<', '>', '|'], '_', $name);
	}

	/**
	 * Cleans and converts a file extension to lowercase.
	 *
	 * @param string $extension The file extension to clean.
	 * @return string The cleaned and lowercase file extension.
	 */
	public function clean_file_extension(string $extension) : string {
		return \mb_strtolower(\preg_replace("/\s/is", "", $extension));
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
	 * Converts all string items in an array to uppercase.
	 *
	 * @param array $items The input array.
	 * @return array The array with all string items converted to uppercase.
	 */
	public function array_to_upper(array $items) : array {
		$data = [];
		foreach($items as $item){
			$data[] = \mb_strtoupper($item);
		}
		return $data;
	}

	/**
	 * Detects the current operating system type.
	 *
	 * @return int An integer representing the system type (SYSTEM_TYPE_WINDOWS, SYSTEM_TYPE_LINUX, SYSTEM_TYPE_MACOS, SYSTEM_TYPE_UNKNOWN).
	 */
	public function get_system_type() : int {
		switch(PHP_OS_FAMILY){
			case 'Windows': return SYSTEM_TYPE_WINDOWS;
			case 'Linux': return SYSTEM_TYPE_LINUX;
			case 'Darwin': return SYSTEM_TYPE_MACOS;
			default: return SYSTEM_TYPE_UNKNOWN;
		}
	}

	/**
	 * Detects the End-Of-Line (EOL) sequence used in a given content string.
	 *
	 * @param string $content The string content to analyze.
	 * @return string The detected EOL sequence (e.g., "\r\n", "\n", "\r"), defaults to "\r\n".
	 */
	public function detect_eol(string $content) : string {
		if(\str_contains($content, "\r\n")){
			return "\r\n";
		} elseif(\str_contains($content, "\n")){
			return "\n";
		} elseif(\str_contains($content, "\r")){
			return "\r";
		} else {
			return "\r\n";
		}
	}

	/**
	 * Checks if a string content starts with a UTF-8 Byte Order Mark (BOM).
	 *
	 * @param string $content The string content to check.
	 * @return bool True if the content has a UTF-8 BOM, false otherwise.
	 */
	public function has_utf8_bom(string $content) : bool {
		return \str_contains($content, $this->utf8_bom);
	}

	/**
	 * Converts a two-character hexadecimal color code (e.g., "0F") to an ANSI escape code for console coloring.
	 * The first character represents background color, the second represents foreground color.
	 *
	 * @param string $color_code The two-character hexadecimal color code.
	 * @return string The ANSI escape code. Returns reset code if input is invalid.
	 */
	public function convert_color_to_ansi(string $color_code) : string {
		$code = \strtoupper($color_code);
		if(\strlen($code) !== 2 || !isset($this->console_color_map[$code[0]]) || !isset($this->console_color_map[$code[1]])){
			return "\033[0m";
		}
		[$fg, $bg] = [$this->console_color_map[$code[1]][0], $this->console_color_map[$code[0]][1]];
		return "\033[{$fg};{$bg}m";
	}

	/**
	 * Sets the console foreground and background color using a two-character hexadecimal color code.
	 * 'XX' will reset the color to the configured default.
	 *
	 * @param string $color_code The two-character hexadecimal color code (e.g., '0F').
	 * @return bool True on success, false if the color code is invalid.
	 */
	public function set_console_color(string $color_code) : bool {
		if($color_code == "XX") $color_code = $this->config->get('COLOR');
		if(!\preg_match('/^[0-9A-Fa-f]{2}$/', $color_code)) return false;
		echo $this->convert_color_to_ansi($color_code);
		return true;
	}

	/**
	 * Parses an input string containing one or more paths (potentially quoted) into an array of unique, resolved paths.
	 *
	 * @param string $string The input string containing paths.
	 * @param bool $unique Whether to return only unique paths (default true).
	 * @return array An array of parsed and resolved paths.
	 */
	public function parse_input_path(string $string, bool $unique = true) : array {
		$string = \trim($string);
		\preg_match_all('/"([^"]+)"|\'([^\']+)\'|(\S+)/', $string, $matches);
		$folders = [];
		foreach($matches[0] as $match){
			$match = \trim($match, '"\'');
			$folders[] = $this->get_path($match);
		}
		if(!$unique) return $folders;
		return \array_unique($folders);
	}

	/**
	 * Checks if a given path matches any of the provided wildcard filters.
	 *
	 * This method supports two types of wildcard filters:
	 * 1. **Global filters:** These patterns can include directory separators (`/`) or start with an asterisk (`*`).
	 * They are matched against the entire normalized path.
	 * 2. **Filename-only filters:** These patterns do not contain directory separators and do not start with an asterisk.
	 * They are matched only against the filename part of the path.
	 *
	 * Both types of filters support `*` (matches zero or more characters) and `?` (matches exactly one character)
	 * wildcards. The matching is case-insensitive.
	 *
	 * @param string $path The path to check. Backslashes (`\`) are converted to forward slashes (`/`) for normalization.
	 * @param array<string> $wildcard_filters An array of wildcard patterns to match against the path.
	 * @return bool True if the path matches any of the wildcard filters, false otherwise.
	 */
	public function matches_path_wildcard_filters(string $path, array $wildcard_filters) : bool {
		$normalized_path = \str_replace('\\', '/', $path);
		foreach($wildcard_filters as $pattern){
			$normalized_pattern = \str_replace('\\', '/', $pattern);
			$is_global = \str_starts_with($normalized_pattern, '*') || \str_contains($normalized_pattern, '/');
			$regex = \preg_quote($normalized_pattern, '#');
			$regex = \str_replace(['\*', '\?'], ['.*', '.'], $regex);
			if($is_global){
				if(\preg_match("#^{$regex}$#i", $normalized_path)){
					return true;
				}
			} else {
				$file_name = \basename($normalized_path);
				if(\preg_match("#^{$regex}$#i", $file_name)){
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Recursively scans a directory for files, applying include/exclude extension filters and name filters.
	 *
	 * @param string $dir The directory to scan.
	 * @param array $data Reference to an array to store the found file paths.
	 * @param ?array $include_extensions An array of extensions to include.
	 * @param ?array $exclude_extensions An array of extensions to exclude.
	 * @param ?array $name_filters An array of strings to filter file names by.
	 * @param bool $case_sensitive Whether name filtering should be case-sensitive.
	 * @param bool $recursive Whether to scan subdirectories recursively.
	 * @return bool True if an action was successfully performed, false otherwise.
	 */
	public function scan_dir_safe_extension(string $dir, array &$data, ?array $include_extensions, ?array $exclude_extensions, ?array $name_filters, bool $case_sensitive, bool $recursive) : bool {
		try {
			$items = @\scandir($dir);
		}
		catch(Exception $e){
			return false;
		}
		if($items === false) return false;
		foreach($items as $item){
			if($item === '.' || $item === '..') continue;
			$full_path = $dir.DIRECTORY_SEPARATOR.$item;
			if(\is_dir($full_path)){
				if(!$recursive) continue;
				$this->scan_dir_safe_extension($full_path, $data, $include_extensions, $exclude_extensions, $name_filters, $case_sensitive, $recursive);
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
			$data[] = $full_path;
		}
		return true;
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
	 * @return bool True if an action was successfully performed, false otherwise.
	 */
	public function scan_dir_safe_extension_process_files(string $dir, callable $callback, int &$counter, ?array $include_extensions, ?array $exclude_extensions, ?array $name_filters, bool $case_sensitive, bool $recursive, bool $follow_symlinks) : bool {
		try {
			$items = @\scandir($dir);
		}
		catch(Exception $e){
			return false;
		}
		if($items === false) return false;
		foreach($items as $item){
			if($item === '.' || $item === '..') continue;
			$full_path = $dir.DIRECTORY_SEPARATOR.$item;
			if(!$follow_symlinks && \is_link($full_path)) continue;
			if(\is_dir($full_path)){
				if(!$recursive) continue;
				$this->scan_dir_safe_extension_process_files($full_path, $callback, $counter, $include_extensions, $exclude_extensions, $name_filters, $case_sensitive, $recursive, $follow_symlinks);
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
			$callback($full_path);
		}
		return true;
	}

	/**
	 * Create symlink for directory
	 *
	 * @param string $target_path The destination folder path
	 * @param string $link_path The symlink path to create
	 * @return bool
	 */
	public function create_directory_symlink(string $target_path, string $link_path) : bool {
		if(\is_link($link_path) || \file_exists($link_path)) return true;
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			$cmd = \sprintf('cmd /c mklink /D %s %s', \escapeshellarg($link_path), \escapeshellarg($target_path));
			\exec($cmd, $output, $code);
			return $code === 0;
		}
		return \symlink($target_path, $link_path);
	}

}

?>