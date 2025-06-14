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

namespace NGC\Core;

use IntlTimeZone;
use FilesystemIterator;

define('SYSTEM_TYPE_UNKNOWN', 0);
define('SYSTEM_TYPE_WINDOWS', 1);
define('SYSTEM_TYPE_LINUX', 2);
define('SYSTEM_TYPE_MACOS', 3);

class Core {

	public IniFile $config;
	public Logs $log_event;
	public Logs $log_error;
	public Logs $log_data;
	public string $app_name = "";
	public string $version = "0.0.0";
	public ?string $command = null;
	public array $arguments = [];
	public string $logo = '';
	public string $path = '';
	public string $tool_name = '';
	public string $subtool_name = '';
	public array $folders_state = [];
	public ?object $tool = null;
	public array $units_bytes = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
	public array $units_bits = ['bit', 'Kbit', 'Mbit', 'Gbit', 'Tbit', 'Pbit', 'Ebit', 'Zbit', 'Ybit'];
	public bool $toggle_log_event = true;
	public bool $toggle_log_error = true;
	public string $utilities_path = '';
	public ?string $core_path = null;
	public string $utilities_version = "1.2.0";
	public string $current_title = '';
	public array $drives = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
	public string $device_null;
	public string $utf8_bom = "\xEF\xBB\xBF";
	public string $resources_folder;

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

	public function __construct(array $arguments){
		date_default_timezone_set(IntlTimeZone::createDefault()->getID());
		mb_internal_encoding('UTF-8');
		unset($arguments[0]);
		$this->command = $arguments[1] ?? null;
		if(isset($arguments[1])) unset($arguments[1]);
		$this->arguments = array_values($arguments);
		$this->path = realpath($this->get_path(__DIR__."/../../.."));
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			$this->device_null = 'nul';
		} else {
			$this->device_null = '/dev/null';
		}
	}

	public function set_resources_folder(string $path) : bool {
		if(!file_exists($path)) return false;
		$this->resources_folder = $path;
		return true;
	}

	public function get_resource(string $name) : string {
		return $this->get_path("$this->resources_folder/$name");
	}

	public function require_utilities() : void {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			$this->utilities_path = $this->get_path($this->get_variable("%PROGRAMFILES%")."/NGC-UTILITIES");
			$utilities = false;
			if(file_exists($this->utilities_path)){
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
				if(!file_exists("/usr/bin/$program_name") && !file_exists("/opt/homebrew/bin/$program_name") && !file_exists("/usr/local/bin/$program_name")){
					$this->echo("[ERROR] Required $program_name not found, please install $install_name");
					$errors++;
				}
			}
			if(!extension_loaded('imagick')){
				$this->echo("[ERROR] Imagick is not installed");
				$errors++;
			}
			if($errors > 0){
				$this->abort = true;
				return;
			}
		}
	}

	public function select_action(?string $trigger_action = null) : bool {
		if(is_null($this->tool)) return false;
		do {
			$this->clear();
			$this->title("$this->app_name v$this->version > $this->tool_name");
			if(is_null($trigger_action)){
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

	public function setup_folders(array $folders) : void {
		$this->folders_state = [];
		foreach($folders as $folder){
			$this->folders_state[$folder] = file_exists($folder) ? '' : '[NOT EXISTS]';
			$this->write_log("Scan: $folder");
		}
		$this->print_folders_state();
	}

	public function set_folder_done(string $folder) : void {
		$this->folders_state[$folder] = file_exists($folder) ? '[DONE]' : '[NOT EXISTS]';
		$this->print_folders_state();
	}

	public function print_folders_state() : void {
		$this->clear();
		foreach($this->folders_state as $folder_name => $state){
			$this->echo(" Scan: \"$folder_name\" $state");
		}
	}

	public function get_tool_name() : string {
		$title = "$this->app_name v$this->version > $this->tool_name";
		if(!empty($this->subtool_name)) $title .= " > $this->subtool_name";
		return $title;
	}

	public function set_tool(string $name) : void {
		$this->tool_name = $name;
		$this->subtool_name = '';
		$this->title($this->get_tool_name());
	}

	public function set_subtool(string $name) : void {
		$this->subtool_name = $name;
		$this->title($this->get_tool_name());
	}

	public function set_errors(int $errors) : void {
		$this->title($this->get_tool_name()." > Errors: $errors");
	}

	public function set_progress_ex(string $label, int $progress, int $total) : void {
		$this->title($this->get_tool_name()." > $label: $progress / $total");
	}

	public function clear() : void {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			popen('cls', 'w');
		} else {
			system('clear');
		}
		if(!empty($this->logo)){
			$this->echo($this->logo);
		} else {
			$this->echo();
		}
	}

	public function get_version_number(string $version) : int {
		$ver = explode(".", $version);
		return 10000 * intval($ver[0]) + 100 * intval($ver[1]) + intval($ver[2]);
	}

	public function print_help(array $help) : void {
		$this->echo(implode("\r\n", $help));
		$this->echo();
	}

	public function progress(int|float $count, int|float $total) : void {
		if($total > 0){
			$percent = sprintf("%.02f", ($count / $total) * 100.0);
			$this->current_line(" Progress: $percent %");
		}
	}

	public function is_valid_label(string $label) : bool {
		return preg_match('/(?=[a-zA-Z0-9_\- ]{3,48}$)/i', $label) == 1;
	}

	public function progress_ex(string $label, int|float $count, int|float $total) : void {
		if($total > 0){
			$percent = sprintf("%.02f", ($count / $total) * 100.0);
			$this->current_line(" $label Progress: $percent %");
		}
	}

	public function get_hash_from_idx(string $path, array &$keys, bool $progress) : int {
		if(!file_exists($path)) return 0;
		$cnt = 0;
		$size = filesize($path);
		$fp = @fopen($path, "r");
		if($fp){
			while(($line = fgets($fp)) !== false){
				$line = trim($line);
				$hash = strtoupper(pathinfo(str_replace("\\", "/", $line), PATHINFO_FILENAME));
				$keys[$hash] = $line;
				$cnt++;
				if($progress) $this->progress(ftell($fp), $size);
			}
			fclose($fp);
		}
		return $cnt;
	}

	public function format_bytes(float|int $bytes, int $precision = 2, bool $dot = true) : string {
		if($bytes > 0){
			$i = floor(log($bytes) / log(1024));
			$res = sprintf("%.{$precision}f", $bytes / pow(1024, $i)).' '.$this->units_bytes[$i];
		} else {
			$res = sprintf("%.{$precision}f", 0).' B';
		}
		if(!$dot) $res = str_replace(".", ",", $res);
		return $res;
	}

	public function format_bits(float|int $bits, int $precision = 2, bool $dot = true) : string {
		if($bits > 0){
			$i = floor(log($bits) / log(1000));
			$res = sprintf("%.{$precision}f", $bits / pow(1000, $i)).' '.$this->units_bits[$i];
		} else {
			$res = sprintf("%.{$precision}f", 0).' bit';
		}
		if(!$dot) $res = str_replace(".", ",", $res);
		return $res;
	}

	public function size_unit_to_bytes(int $value, string $unit) : int {
		$index = array_search(strtolower($unit), $this->array_to_lower($this->units_bytes));
		if($index === false) return -1;
		return intval($value * pow(1024, $index));
	}

	public function time_unit_to_seconds(int $value, string $unit) : int {
		switch(strtolower($unit)){
			case 'sec': return $value;
			case 'min': return $value * 60;
			case 'hour': return $value * 3600;
			case 'day': return $value * 86400;
		}
		return 0;
	}

	public function seconds_to_time(float $seconds, bool $force_hours = false, bool $with_days = false, bool $with_ms = false) : string {
		$output = "";
		if($with_days){
			$days = intval(floor($seconds / 86400));
			$seconds -= ($days * 86400);
		} else {
			$days = 0;
		}
		$h = intval(floor($seconds / 3600));
		$seconds -= $h * 3600;
		$m = intval(floor($seconds / 60));
		$seconds -= $m * 60;
		$s = floor($seconds);
		$seconds -= $s;
		$ms = round($seconds * 1000);
		if($days > 0){
			$output = "$days:";
		}
		if($h > 0 || $force_hours){
			$output .= sprintf("%02d:%02d:%02d", $h, $m, $s);
		} else {
			$output .= sprintf("%02d:%02d", $m, $s);
		}
		if($with_ms){
			$output .= sprintf(",%03d", $ms);
		}
		return $output;
	}

	public function time_to_seconds(string $time) : int {
		$parts = explode(':', $time);
		$count = count($parts);
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

	public function is_folder_empty(string $path) : bool {
		if(!file_exists($path)) return true;
		$files = scandir($path);
		foreach($files as $file){
			if($file == "." || $file == "..") continue;
			return false;
		}
		return true;
	}

	public function get_files(string $path, ?array $include_extensions = null, ?array $exclude_extensions = null, ?array $name_filters = null, bool $case_sensitive = false, bool $recursive = true) : array {
		if(!file_exists($path) || !is_readable($path)) return [];
		if(!$case_sensitive && !is_null($name_filters)){
			$name_filters = $this->array_to_lower($name_filters);
		}
		$data = [];
		$this->scan_dir_safe_extension($path, $data, $include_extensions, $exclude_extensions, $name_filters, $case_sensitive, $recursive);
		asort($data, SORT_STRING);
		return array_values($data);
	}

	public function get_folders(string $path, bool $with_parent = false, bool $recursive = true) : array {
		if(!file_exists($path) || !is_dir($path)) return [];
		$data = [];
		if($with_parent){
			$data[] = realpath($path);
		}
		if(!is_readable($path)) return $data;
		$files = scandir($path);
		if($files === false) return $data;
		foreach($files as $file){
			if($file === '.' || $file === '..'){
				continue;
			}
			$full_path = $path.DIRECTORY_SEPARATOR.$file;
			if(is_dir($full_path) && !is_link($full_path)){
				$data[] = realpath($full_path);
				if($recursive){
					$data = array_merge($data, $this->get_folders($full_path, false, $recursive));
				}
			}
		}
		asort($data, SORT_STRING);
		return array_values($data);
	}

	public function filter(string $search, array $filters, bool $case_sensitive = false) : bool {
		if(!$case_sensitive) $search = mb_strtolower($search);
		foreach($filters as $filter){
			if(str_contains($search, $filter)){
				return true;
			}
		}
		return false;
	}

	/**
	 * @deprecated Use get_files with recursive = false
	 */
	public function get_files_ex(string $path, ?array $include_extensions = null, ?array $exclude_extensions = null, ?array $name_filters = null, bool $case_sensitive = false) : array {
		return $this->get_files($path, $include_extensions, $exclude_extensions, $name_filters, $case_sensitive, false);
	}

	/**
	 * @deprecated Use get_folders with recursive = false
	 */
	public function get_folders_ex(string $path) : array {
		return $this->get_folders($path, false, false);
	}

	public function close(bool $open_log = false) : void {
		$this->open_logs($open_log, false);
		exit(0);
	}

	public function init_logs() : void {
		$timestamp = date("Y-m-d/Y-m-d His");
		$this->log_event = new Logs($this->get_path($this->config->get('LOG_FOLDER')."/$timestamp-Event.txt"), true, true);
		$this->log_error = new Logs($this->get_path($this->config->get('LOG_FOLDER')."/$timestamp-Error.txt"), true, true);
		$this->log_data = new Logs($this->get_path($this->config->get('DATA_FOLDER')."/$timestamp.txt"), false, true);
	}

	public function open_logs(bool $open_event = false, bool $init = true) : void {
		$this->log_event->close();
		$this->log_error->close();
		$this->log_data->close();
		if($this->config->get('OPEN_LOG_EVENT', true) && $open_event && file_exists($this->log_event->get_path())){
			$this->open_file($this->log_event->get_path());
		}
		if(file_exists($this->log_data->get_path())){
			$this->open_file($this->log_data->get_path());
		}
		if(file_exists($this->log_error->get_path())){
			$this->open_file($this->log_error->get_path());
		}
		if($init) $this->init_logs();
	}

	public function write_log(string|array $data) : void {
		if($this->config->get('LOG_EVENT', true) && $this->toggle_log_event){
			$this->log_event->write($data);
		}
	}

	public function write_error(string|array $data) : void {
		if($this->config->get('LOG_ERROR', true) && $this->toggle_log_error){
			$this->log_error->write($data);
		}
	}

	public function write_data(string|array $data) : void {
		$this->log_data->write($data);
	}

	public function rrmdir(string $dir, bool $log = true) : bool {
		if(!file_exists($dir)) return false;
		if(is_dir($dir)){
			$items = scandir($dir);
			foreach($items as $item){
				if($item == "." || $item == "..") continue;
				$subdir = $this->get_path("$dir/$item");
				if(is_dir($subdir) && !is_link($subdir)){
					$this->rrmdir($subdir, $log);
				} else {
					$this->delete($subdir, $log);
				}
			}
			$this->rmdir($dir, false);
		}
		return true;
	}

	public function rmdir(string $path, bool $log = true) : bool {
		if(!file_exists($path) || !is_dir($path)) return false;
		if(@rmdir($path)){
			if($log) $this->write_log("DELETE \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED RMDIR \"$path\"");
			return false;
		}
	}

	public function rmdir_empty(string $path, bool $log = true) : bool {
		if(!file_exists($path) || !is_dir($path)) return false;
		$files = array_reverse($this->get_folders($path, true));
		foreach($files as $file){
			if(!file_exists($file)) continue;
			$count = iterator_count(new FilesystemIterator($file, FilesystemIterator::SKIP_DOTS));
			if($count == 0){
				$this->rmdir($file, $log);
			}
		}
		return !file_exists($path);
	}

	public function delete(string $path, bool $log = true) : bool {
		if(!file_exists($path) || is_dir($path)) return false;
		if(@unlink($path)){
			if($log) $this->write_log("DELETE \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED DELETE \"$path\"");
			return false;
		}
	}

	public function mkdir(string $path, bool $log = true, int $permissions = 0755) : bool {
		if(file_exists($path) && is_dir($path)) return true;
		if(@mkdir($path, $permissions, true)){
			if($log) $this->write_log("MKDIR \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED MKDIR \"$path\"");
			return false;
		}
	}

	public function clone_folder_structure(string $input, string $output) : int|false {
		if(!file_exists($input) || !is_dir($input)) return false;
		$errors = 0;
		$folders = $this->get_folders($input);
		foreach($folders as $folder){
			$directory = str_ireplace($input, $output, $folder);
			if(!file_exists($directory)){
				if(!$this->mkdir($directory)){
					$errors++;
				}
			}
		}
		return $errors;
	}

	public function move(string $from, string $to, bool $log = true) : bool {
		if(!file_exists($from)) return false;
		if($from == $to) return true;
		if(file_exists($to) && pathinfo($from, PATHINFO_DIRNAME) != pathinfo($to, PATHINFO_DIRNAME)){
			if($log) $this->write_error("FAILED RENAME \"$from\" \"$to\" FILE EXIST");
			return false;
		}
		$dir = pathinfo($to, PATHINFO_DIRNAME);
		if(!file_exists($dir)) $this->mkdir($dir);
		$modification_date = filemtime($from);
		if(@rename($from, $to)){
			touch($to, $modification_date);
			if($log) $this->write_log("RENAME \"$from\" \"$to\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED RENAME \"$from\" \"$to\"");
			return false;
		}
	}

	public function move_case(string $from, string $to, bool $log = true) : bool {
		if(!file_exists($from)) return false;
		if(strcmp($from, $to) == 0) return true;
		$dir = pathinfo($to, PATHINFO_DIRNAME);
		if(!file_exists($dir)) $this->mkdir($dir);
		$modification_date = filemtime($from);
		if(@rename($from, $to)){
			touch($to, $modification_date);
			if($log) $this->write_log("RENAME \"$from\" \"$to\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED RENAME \"$from\" \"$to\"");
			return false;
		}
	}

	public function copy(string $from, string $to, bool $log = true) : bool {
		if(!file_exists($from)) return false;
		if($from == $to) return true;
		if(file_exists($to) && pathinfo($from, PATHINFO_DIRNAME) != pathinfo($to, PATHINFO_DIRNAME)){
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" FILE EXIST");
			return false;
		}
		$dir = pathinfo($to, PATHINFO_DIRNAME);
		if(!file_exists($dir)) $this->mkdir($dir);
		$modification_date = filemtime($from);
		if(@copy($from, $to)){
			touch($to, $modification_date);
			if($log) $this->write_log("COPY \"$from\" \"$to\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\"");
			return false;
		}
	}

	public function acopy(string $from, string $to, bool $log = true) : bool {
		if(!file_exists($from)) return false;
		if($from === $to) return true;
		$write_buffer = $this->get_write_buffer();
		if(!$write_buffer) return false;
		if(file_exists($to) && pathinfo($from, PATHINFO_DIRNAME) !== pathinfo($to, PATHINFO_DIRNAME)){
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" FILE EXISTS");
			return false;
		}
		$dir = pathinfo($to, PATHINFO_DIRNAME);
		if(!file_exists($dir)) $this->mkdir($dir);
		$modification_date = filemtime($from);
		$filesize = filesize($from);
		$source = fopen($from, 'rb');
		$destination = fopen($to, 'wb');
		if(!$source || !$destination){
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" (cannot open files)");
			return false;
		}
		if(function_exists('ftruncate')){
			ftruncate($destination, $filesize);
		}
		while(!feof($source)){
			$buffer = fread($source, $write_buffer);
			fwrite($destination, $buffer);
		}
		fclose($source);
		fclose($destination);
		touch($to, $modification_date);
		if($log) $this->write_log("COPY \"$from\" \"$to\"");
		return true;
	}

	public function delete_files(string $path, ?array $include_extensions = null, ?array $exclude_extensions = null) : void {
		$files = $this->get_files($path, $include_extensions, $exclude_extensions);
		foreach($files as $file){
			$this->delete($file);
		}
	}

	public function cmd_escape(string $text) : string {
		return str_replace([">", "<"], ["^>", "^<"], $text);
	}

	public function title(string $title) : void {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			$title = $this->cmd_escape($title);
			if($this->current_title != $title){
				$this->current_title = $title;
				system("TITLE $title");
			}
		}
	}

	public function get_confirm(string $question) : bool {
		ask_confirm:
		$answer = strtoupper($this->get_input($question));
		if(!in_array($answer, ['Y', 'N'])) goto ask_confirm;
		return $answer == 'Y';
	}

	public function get_input(?string $message = null, bool $trim = true, bool $history = true) : string {
		$line = readline($message);
		if($line === false){
			$this->write_error("Failed readline from prompt");
			$this->close();
			return '';
		}
		if($trim) $line = trim($line);
		if($history) readline_add_history($line);
		return $line;
	}

	public function get_input_password(string $message) : string {
		echo $message;
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			$color = $this->config->get('COLOR');
			$this->set_console_color(substr($color, 0, 1).substr($color, 0, 1));
			$password = readline();
			$this->set_console_color($color);
			echo "\033[1A$message".str_repeat("*", strlen($password))."\r\n";
		} else {
			system('stty -echo');
			$password = fgets(STDIN);
			system('stty echo');
			echo "\r\n";
		}
		return rtrim($password, "\r\n");
	}

	public function get_input_multiple_folders(string $title, bool $setup = true) : array|false {
		$line = $this->get_input($title);
		if($line == '#') return false;
		$folders = $this->parse_input_path($line);
		if($setup) $this->setup_folders($folders);
		return $folders;
	}

	public function get_input_folder(string $title, bool $as_output = false) : string|false {
		set_path:
		$line = $this->get_input($title);
		if($line == '#') return false;
		$folders = $this->parse_input_path($line);
		if(!isset($folders[0])) goto set_path;
		$path = $folders[0];
		if(file_exists($path) && !is_dir($path)){
			$this->echo(" Invalid folder path");
			goto set_path;
		}
		if($as_output && !$this->mkdir($path)){
			$this->echo(" Failed create folder");
			goto set_path;
		}
		if(!file_exists($path)){
			$this->echo(" Folder not exists");
			goto set_path;
		}
		return $path;
	}

	public function get_input_file(string $title, bool $required = true, bool $create_directory = false) : string|false {
		set_path:
		$line = $this->get_input($title);
		if($line == '#') return false;
		$files = $this->parse_input_path($line);
		if(!isset($files[0])) goto set_path;
		$path = $files[0];
		if(file_exists($path) && is_dir($path)){
			$this->echo(" Invalid file path");
			goto set_path;
		}
		if($required && !file_exists($path)){
			$this->echo(" Input file not exists");
			goto set_path;
		}
		if($create_directory){
			$directory = pathinfo($$path, PATHINFO_DIRNAME);
			if(!file_exists($directory) && !$this->mkdir($directory)){
				$this->echo(" Failed create destination directory \"$directory\"");
				goto set_path;
			}
		}
		return $path;
	}

	public function get_input_extensions(string $title, ?string $help_message = " Empty for all, separate with spaces for multiple") : array|null|false {
		if(!is_null($help_message)) $this->echo($help_message);
		$line = $this->get_input($title);
		if($line == '#') return false;
		if(empty($line)) return null;
		return explode(" ", $line);
	}

	public function pause(?string $message = null) : void {
		if(!is_null($message)) echo $message;
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			system("PAUSE > nul");
		} else {
			$this->get_input();
		}
	}

	public function echo(string $string = '', ?string $color_code = null) : void {
		if(!is_null($color_code)) $this->set_console_color($color_code);
		echo "$string\r\n";
		if(!is_null($color_code)) $this->set_console_color("XX");
	}

	public function cecho(string $string = '') : void {
		$output = '';
		$offset = 0;
		while(preg_match('/\{([0-9A-Fa-f]{2}|XX)\}/', $string, $match, PREG_OFFSET_CAPTURE, $offset)){
			$output .= substr($string, $offset, (int)$match[0][1] - (int)$offset);
			$output .= $this->convert_color_to_ansi($match[1][0]);
			$offset = $match[0][1] + 4;
		}
		$output .= substr($string, $offset);
		echo "$output\r\n";
		$this->set_console_color("XX");
	}

	public function current_line(string $string = '') : void {
		echo "$string".str_repeat(" ", (int)max(62 - strlen($string), 0))."\r";
	}

	public function print(mixed $var, bool $add_space = false) : void {
		echo $this->get_print($var, 0, $add_space);
	}

	public function get_print(mixed $var, int $indent = 0, bool $add_space = false) : string {
		$output = '';
		$prefix = str_repeat("\t", $indent);
		if($add_space) $prefix = " $prefix";
		if(is_array($var)){
			if(empty($var)){
				$output .= "{$prefix}(array) []\n";
			} else {
				$output .= "{$prefix}(array) [\n";
				foreach($var as $key => $value){
					if(!is_numeric($key)) $key = "'$key'";
					$output .= "$prefix\t$key => ".ltrim($this->get_print($value, $indent + 1, $add_space));
				}
				$output .= "$prefix]\n";
			}
		} elseif(is_object($var)){
			$class = get_class($var);
			if(empty($var)){
				$output .= "{$prefix}($class){}\n";
			} else {
				$output .= "{$prefix}($class){\n";
				foreach(get_object_vars($var) as $key => $value){
					if(!is_numeric($key)) $key = "'$key'";
					$output .= "$prefix\t$key => ".ltrim($this->get_print($value, $indent + 1, $add_space));
				}
				$output .= "$prefix}\n";
			}
		} else {
			$type = strtolower(gettype($var));
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
				$output .= "$prefix\t($type) ".var_export($var, true)."\n";
			} else {
				$output .= "($type) ".var_export($var, true)."\n";
			}
		}
		return $output;
	}

	public function get_variable(string $string) : string {
		exec("echo $string", $var);
		return $var[0] ?? '';
	}

	public function open_file(string $path, string $params = '/MIN') : void {
		if(file_exists($path)){
			if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
				exec("START $params \"\" \"$path\"");
			} elseif(!is_null($this->config->get('OPEN_FILE_BINARY'))){
				exec($this->config->get('OPEN_FILE_BINARY')." \"$path\"");
			} else {
				$this->write_error("Failed open file OPEN_FILE_BINARY is not configured");
			}
		}
	}

	public function open_url(string $url) : void {
		if(str_contains($url, "https://") || str_contains($url, "http://")){
			if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
				exec("START \"\" \"$url\"");
			} elseif(!is_null($this->config->get('OPEN_FILE_BINARY'))){
				exec($this->config->get('OPEN_FILE_BINARY')." \"$url\"");
			} else {
				$this->write_error("Failed open url OPEN_FILE_BINARY is not configured");
			}
		}
	}

	public function get_file_attributes(string $path) : array {
		$path = $this->get_path($path);
		if($this->get_system_type() != SYSTEM_TYPE_WINDOWS || !file_exists($path)) return ['R' => false, 'A' => false, 'S' => false, 'H' => false, 'I' => false];
		$attributes = substr(shell_exec("attrib ".escapeshellarg($path)), 0, 21);
		return [
			'R' => str_contains($attributes, "R"),
			'A' => str_contains($attributes, "A"),
			'S' => str_contains($attributes, "S"),
			'H' => str_contains($attributes, "H"),
			'I' => str_contains($attributes, "I"),
		];
	}

	public function set_file_attributes(string $path, ?bool $r = null, ?bool $a = null, ?bool $s = null, ?bool $h = null, ?bool $i = null) : bool {
		if($this->get_system_type() != SYSTEM_TYPE_WINDOWS || !file_exists($path)) return false;
		$attributes = '';
		if(!is_null($r)) $attributes .= ($r ? '+' : '-').'R ';
		if(!is_null($a)) $attributes .= ($a ? '+' : '-').'A ';
		if(!is_null($s)) $attributes .= ($s ? '+' : '-').'S ';
		if(!is_null($h)) $attributes .= ($h ? '+' : '-').'H ';
		if(!is_null($i)) $attributes .= ($i ? '+' : '-').'I ';
		shell_exec("attrib $attributes ".escapeshellarg($path));
		return true;
	}

	public function is_valid_path(string $path) : bool {
		if($this->get_system_type() != SYSTEM_TYPE_WINDOWS) return true;
		if(strlen($path) >= 2 && $path[1] === ':' && ctype_alpha($path[0])){
			return file_exists(substr($path, 0, 3));
		} elseif(substr($path, 0, 2) == "\\\\"){
			$device = substr($path, 2);
			if(str_contains($device, "\\")){
				$parts = explode("\\", $device);
				if(count($parts) >= 2){
					return is_dir("\\\\{$parts[0]}\\{$parts[1]}");
				}
			}
		}
		return false;
	}

	public function get_path(string $path) : string {
		return str_replace(["/", "\\"], DIRECTORY_SEPARATOR, $path);
	}

	public function get_extension(string $path) : string {
		return mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
	}

	public function put_folder_to_path(string $path, string $subfolder) : string {
		return $this->get_path(pathinfo($path, PATHINFO_DIRNAME)."/$subfolder/".pathinfo($path, PATHINFO_BASENAME));
	}

	public function get_computer_name() : string {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			return $this->get_variable("%COMPUTERNAME%");
		} else {
			return shell_exec('hostname');
		}
	}

	public function get_arguments_folders(array $arguments) : string {
		$output = '';
		foreach($arguments as $argument){
			$argument = $this->get_path($argument);
			if(substr($argument, 0, 1) == '"'){
				$output .= " $argument";
			} else {
				$output .= " \"$argument\"";
			}
		}
		return $output;
	}

	public function get_hash_alghoritm(int $id) : array {
		switch($id){
			case 0: return ['name' => 'md5', 'length' => 32];
			case 1: return ['name' => 'sha256', 'length' => 64];
			case 2: return ['name' => 'crc32', 'length' => 8];
			case 3: return ['name' => 'whirlpool', 'length' => 128];
		}
		return ['name' => 'md5', 'length' => 32];
	}

	public function is_text_file(string $path) : bool {
		if(!file_exists($path)) return false;
		$finfo = finfo_open(FILEINFO_MIME);
		return substr(finfo_file($finfo, $path), 0, 4) == 'text';
	}

	public function exec(string $program, string $command, ?array &$output = null, ?int &$result_code = null) : string|false {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			if(is_null($this->core_path)) return false;
			$program = $this->get_path("$this->core_path/$program.exe");
		}
		return exec("\"$program\" $command", $output, $result_code);
	}

	public function is_admin() : bool {
		if($this->get_system_type() != SYSTEM_TYPE_WINDOWS) return false;
		return exec('net session 1>NUL 2>NUL || (ECHO NO_ADMIN)') != 'NO_ADMIN';
	}

	public function get_input_bytes_size(string $name) : int|false {
		set_size:
		$this->clear();
		$this->print_help([
			' Type integer and unit separate by space, example: 1 GiB',
			' Size units: B, KiB, MiB, GiB, TiB',
		]);

		$line = $this->get_input($name);
		if($line == '#') return false;
		$size = explode(' ', $line);
		if(!isset($size[1])) goto set_size;
		$size[0] = preg_replace('/\D/', '', $size[0]);
		if(empty($size[0])) goto set_size;
		$bytes = $this->size_unit_to_bytes(intval($size[0]), $size[1]);
		if($bytes <= 0) goto set_size;
		return $bytes;
	}

	public function get_input_time_interval(string $name) : int|false {
		set_interval:
		$this->clear();
		$this->print_help([
			' Type integer and unit separate by space, example: 30 sec',
			' Interval units: sec, min, hour, day',
		]);

		$line = $this->get_input($name);
		if($line == '#') return false;
		$size = explode(' ', $line);
		if(!isset($size[1])) goto set_interval;
		$size[0] = preg_replace('/\D/', '', $size[0]);
		if(empty($size[0])) goto set_interval;
		$interval = $this->time_unit_to_seconds(intval($size[0]), $size[1]);
		if($interval <= 0) goto set_interval;
		return $interval;
	}

	public function get_input_integer(string $name, int $min = 1, int $max = 2147483647) : int|false {
		set_number:
		$line = $this->get_input($name);
		if($line == '#') return false;
		$line = preg_replace('/\D/', '', $line);
		if($line == ''){
			$this->echo(" Type valid integer number");
			goto set_number;
		}
		$number = intval($line);
		if($number < $min){
			$this->echo(" Number must be have greater than or equal $min");
			goto set_number;
		} elseif($number > $max){
			$this->echo(" Number must be have less than or equal $max");
			goto set_number;
		}
		return $number;
	}

	public function get_write_buffer() : int|bool {
		$size = explode(' ', $this->config->get('WRITE_BUFFER_SIZE'));
		$write_buffer = $this->size_unit_to_bytes(intval($size[0]), $size[1] ?? '?');
		if($write_buffer <= 0){
			$this->clear();
			$write_buffer_size = $this->config->get('WRITE_BUFFER_SIZE');
			$this->pause(" Operation aborted: invalid config value for WRITE_BUFFER_SIZE=\"$write_buffer_size\", press any key to back to menu.");
			return false;
		}
		return $write_buffer;
	}

	public function trash(string $path) : bool {
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			if(substr($path, 1, 1) == ':'){
				$new_name = $this->get_path(substr($path, 0, 2)."/.Deleted/".substr($path, 3));
				if(file_exists($new_name) && !$this->delete($new_name)) return false;
				return $this->move($path, $new_name);
			} elseif(substr($path, 0, 2) == "\\\\"){
				$device = substr($path, 2);
				if(str_contains($device, "\\")){
					$new_name = $this->get_path($device."/.Deleted/".str_replace("\\\\$device", "", $path));
					if(file_exists($new_name) && !$this->delete($new_name)) return false;
					return $this->move($path, $new_name);
				}
			}
		} else {
			$output = [];
			$returnVar = 0;
			exec("gio trash ".escapeshellarg($path), $output, $returnVar);
			return ($returnVar === 0);
		}
		$this->write_error("FAILED TRASH \"$path\"");
		return false;
	}

	public function windows_only() : bool {
		$this->echo(" This tool is only available on windows operating system");
		$this->pause(" Press any key to back to menu");
		return false;
	}

	public function base64_length(string $string) : ?int {
		$string = trim(str_replace(["\r", "\n"], "", $string));
		$base64_length = strlen($string);
		$padding_length = substr_count($string, '=');
		$original_length = ($base64_length / 4) * 3 - $padding_length;
		if($original_length != (int)$original_length) return null;
		return $original_length;
	}

	public function clean_file_name(string $name) : string {
		return str_replace(["\\", '/', ':', '*', '?', '"', '<', '>', '|'], '_', $name);
	}

	public function clean_file_extension(string $extension) : string {
		return mb_strtolower(preg_replace("/\s/is", "", $extension));
	}

	public function array_to_lower(array $items) : array {
		$data = [];
		foreach($items as $item){
			$data[] = mb_strtolower($item);
		}
		return $data;
	}

	public function array_to_upper(array $items) : array {
		$data = [];
		foreach($items as $item){
			$data[] = mb_strtoupper($item);
		}
		return $data;
	}

	public function get_system_type() : int {
		switch(PHP_OS_FAMILY){
			case 'Windows': return SYSTEM_TYPE_WINDOWS;
			case 'Linux': return SYSTEM_TYPE_LINUX;
			case 'Darwin': return SYSTEM_TYPE_MACOS;
			default: return SYSTEM_TYPE_UNKNOWN;
		}
	}

	public function detect_eol(string $content) : string {
		if(str_contains($content, "\r\n")){
			return "\r\n";
		} elseif(str_contains($content, "\n")){
			return "\n";
		} elseif(str_contains($content, "\r")){
			return "\r";
		} else {
			return "\r\n";
		}
	}

	public function has_utf8_bom(string $content) : bool {
		return str_contains($content, $this->utf8_bom);
	}

	public function convert_color_to_ansi(string $color_code) : string {
		$code = strtoupper($color_code);
		if(strlen($code) !== 2 || !isset($this->console_color_map[$code[0]]) || !isset($this->console_color_map[$code[1]])){
			return "\033[0m";
		}
		[$fg, $bg] = [$this->console_color_map[$code[1]][0], $this->console_color_map[$code[0]][1]];
		return "\033[{$fg};{$bg}m";
	}

	public function set_console_color(string $color_code) : bool {
		if($color_code == "XX") $color_code = $this->config->get('COLOR');
		if(!preg_match('/^[0-9A-Fa-f]{2}$/', $color_code)) return false;
		echo $this->convert_color_to_ansi($color_code);
		return true;
	}

	public function parse_input_path(string $string, bool $unique = true) : array {
		$string = trim($string);
		preg_match_all('/"([^"]+)"|\'([^\']+)\'|(\S+)/', $string, $matches);
		$folders = [];
		foreach($matches[0] as $match){
			$match = trim($match, '"\'');
			$folders[] = $this->get_path($match);
		}
		if(!$unique) return $folders;
		return array_unique($folders);
	}

	private function scan_dir_safe_extension(string $dir, array &$data, ?array $include_extensions, ?array $exclude_extensions, ?array $name_filters, bool $case_sensitive, bool $recursive) : void {
		$items = @scandir($dir);
		if($items === false) return;
		foreach($items as $item){
			if($item === '.' || $item === '..') continue;
			$full_path = $dir.DIRECTORY_SEPARATOR.$item;
			if(is_dir($full_path)){
				if(!$recursive) continue;
				if(!is_readable($full_path)) continue;
				$this->scan_dir_safe_extension($full_path, $data, $include_extensions, $exclude_extensions, $name_filters, $case_sensitive, $recursive);
				continue;
			}
			if(!is_file($full_path) || !is_readable($full_path)) continue;
			$ext = mb_strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
			if(!is_null($include_extensions) && !in_array($ext, $include_extensions)) continue;
			if(!is_null($exclude_extensions) && in_array($ext, $exclude_extensions)) continue;
			$basename = basename($full_path);
			if(!is_null($name_filters)){
				$check_name = $case_sensitive ? $basename : mb_strtolower($basename);
				if(!$this->filter($check_name, $name_filters)) continue;
			}
			$full_path = realpath($full_path);
			if($full_path !== false) $data[] = $full_path;
		}
	}

}

?>