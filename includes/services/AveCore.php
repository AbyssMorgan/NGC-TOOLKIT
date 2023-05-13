<?php

declare(strict_types=1);

namespace App\Services;

use IntlTimeZone;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use DirectoryIterator;

class AveCore extends CommandLine {

	public int $core_version = 2;

	public IniFile $config;

	public Logs $log_event;
	public Logs $log_error;
	public Logs $log_data;

	public string $logo;
	public string $path;
	public string $tool_name;
	public string $subtool_name;
	public array $folders_state = [];
	public $tool;

	public function __construct(array $arguments){
		parent::__construct($arguments);
		date_default_timezone_set(IntlTimeZone::createDefault()->getID());
		$this->path = $this->get_file_path(__DIR__."/../..");
		$this->logo = '';
	}

	public function select_action() : bool {
		do {
			$this->clear();
			$this->title("$this->app_name v$this->version > $this->tool_name");
			$this->tool->help();
			echo " Action: ";
			$line = $this->get_input();
			if($line == '#') return false;
			$response = $this->tool->action($line);
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
		$this->folders_state[$folder] = '[DONE]';
		$this->print_folders_state();
	}

	public function print_folders_state() : void {
		$this->clear();
		foreach($this->folders_state as $folder_name => $state){
			echo " Scan: \"$folder_name\" $state\r\n";
		}
	}

	public function set_tool(string $name) : void {
		$this->tool_name = $name;
		$this->subtool_name = '';
		$this->title("$this->app_name v$this->version > $this->tool_name");
		$this->write_log("Set Tool: $this->tool_name");
	}

	public function set_subtool(string $name) : void {
		$this->subtool_name = $name;
		$this->title("$this->app_name v$this->version > $this->tool_name > $this->subtool_name");
		$this->write_log("Set Tool: $this->tool_name > $this->subtool_name");
	}

	public function set_progress(int $progress, int $errors) : void {
		$title = "$this->app_name v$this->version > $this->tool_name";
		if(!empty($this->subtool_name)) $title .= " > $this->subtool_name";
		$this->title("$title > Files: $progress Errors: $errors");
	}

	public function set_progress_ex(string $label, int $progress, int $total) : void {
		$title = "$this->app_name v$this->version > $this->tool_name";
		if(!empty($this->subtool_name)) $title .= " > $this->subtool_name";
		$this->title("$title > $label: $progress / $total");
	}

	public function clear() : void {
		$this->cls();
		if($this->config->get('AVE_SHOW_LOGO', false)){
			echo "$this->logo\r\n";
		} else {
			echo "\r\n";
		}
	}

	public function get_version_number(string $version) : int {
		$ver = explode(".", $version);
		return 10000 * intval($ver[0]) + 100*intval($ver[1]) + intval($ver[2]);
	}

	public function print_help(array $help) : void {
		echo implode("\r\n", $help)."\r\n\r\n";
	}

	public function progress(int|float $count, int|float $total) : void {
		if($total > 0){
			$percent = sprintf("%.02f", ($count / $total) * 100.0);
			echo " Progress: $percent %        \r";
		}
	}

	public function is_valid_label(string $label) : bool {
		return preg_match('/(?=[a-zA-Z0-9_\-]{3,20}$)/i', $label) == 1;
	}

	public function progress_ex(string $label, int|float $count, int|float $total) : void {
		if($total > 0){
			$percent = sprintf("%.02f", ($count / $total) * 100.0);
			echo " $label Progress: $percent %                                        \r";
		}
	}

	public function getHashFromIDX(string $path, array &$keys, bool $progress) : int {
		if(!file_exists($path)) return 0;
		$cnt = 0;
		$size = filesize($path);
		$fp = @fopen($path, "r");
		if($fp){
			while(($line = fgets($fp)) !== false){
				$line = trim($line);
				$keys[pathinfo($line, PATHINFO_FILENAME)] = $line;
				$cnt++;
				if($progress) $this->progress(ftell($fp), $size);
			}
			fclose($fp);
		}
		return $cnt;
	}

	public function formatBytes(int $bytes, int $precision = 2) : string {
		if($bytes <= 0) return '0.00 B';
		$i = floor(log($bytes)/log(1024));
		$sizes = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
		return sprintf('%.'.$precision.'f', $bytes/pow(1024, $i)).' '.$sizes[$i];
	}

	public function getFiles(string $path, array|null $extensions = null, array|null $except = null) : array {
		$data = [];
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS));
		foreach($files as $file){
			if($file->isDir() || $file->isLink()) continue;
			if(!is_null($extensions) && !in_array(strtolower($file->getExtension()), $extensions)) continue;
			if(!is_null($except) && in_array(strtolower($file->getExtension()), $except)) continue;
			$fp = $file->getRealPath();
			if(!$fp) continue;
			array_push($data, $fp);
		}
		return $data;
	}

	public function getFolders(string $path) : array {
		$data = [];
		$files = new DirectoryIterator($path);
		array_push($data, $path);
		foreach($files as $file){
			if($file->isDir() && !$file->isDot()){
				$data = array_merge($data, $this->getFolders($file->getRealPath()));
			}
		}
		return $data;
	}

	public function exit(int $seconds = 10, bool $open_log = false) : void {
		$this->write_log("Exit");
		$this->open_logs($open_log, false);
		$this->timeout($seconds);
	}

	public function init_logs(){
		$timestamp = date("Y-m-d His");
		$this->log_event = new Logs($this->get_file_path($this->config->get('AVE_LOG_FOLDER')."/$timestamp-Event.txt"), true, true);
		$this->log_error = new Logs($this->get_file_path($this->config->get('AVE_LOG_FOLDER')."/$timestamp-Error.txt"), true, true);
		$this->log_data = new Logs($this->get_file_path($this->config->get('AVE_DATA_FOLDER')."/$timestamp.txt"), false, true);
	}

	public function open_logs(bool $open_event = false, bool $init = true) : void {
		$this->log_event->close();
		$this->log_error->close();
		$this->log_data->close();
		if($this->config->get('AVE_OPEN_LOG_EVENT', true) && $open_event && file_exists($this->log_event->getPath())){
			$this->open_file($this->log_event->getPath());
		}
		if(file_exists($this->log_data->getPath())){
			$this->open_file($this->log_data->getPath());
		}
		if(file_exists($this->log_error->getPath())){
			$this->open_file($this->log_error->getPath());
		}
		if($init) $this->init_logs();
	}

	public function timeout(int $seconds) : void {
		$this->title("$this->app_name v$this->version > Exit $seconds seconds");
		if($seconds > 0){
			sleep(1);
			$seconds--;
			$this->timeout($seconds);
		} else {
			exit(0);
		}
	}

	public function write_log(string|array $data) : void {
		if($this->config->get('AVE_LOG_EVENT', true)){
			$this->log_event->write($data);
		}
	}

	public function write_error(string|array $data) : void {
		if($this->config->get('AVE_LOG_ERROR', true)){
			$this->log_error->write($data);
		}
	}

	public function write_data(string|array $data) : void {
		$this->log_data->write($data);
	}

	public function rmdir(string $path, bool $log = true) : bool {
		if(!file_exists($path) || !is_dir($path)) return false;
		if(rmdir($path)){
			if($log) $this->write_log("DELETE \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED DELETE \"$path\"");
			return false;
		}
	}

	public function unlink(string $path, bool $log = true) : bool {
		if(!file_exists($path) || is_dir($path)) return false;
		if(unlink($path)){
			if($log) $this->write_log("DELETE \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED DELETE \"$path\"");
			return false;
		}
	}

	public function mkdir(string $path, bool $log = true) : bool {
		if(file_exists($path) && is_dir($path)) return true;
		if(mkdir($path, 0755, true)){
			if($log) $this->write_log("MKDIR \"$path\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED MKDIR \"$path\"");
			return false;
		}
	}

	public function rename(string $from, string $to, bool $log = true) : bool {
		if($from == $to) return true;
		if(file_exists($to) && pathinfo($from, PATHINFO_DIRNAME) != pathinfo($to, PATHINFO_DIRNAME)){
			if($log) $this->write_error("FAILED RENAME \"$from\" \"$to\" FILE EXIST");
			return false;
		}
		$dir = pathinfo($to, PATHINFO_DIRNAME);
		if(!file_exists($dir)) $this->mkdir($dir);
		if(rename($from, $to)){
			if($log) $this->write_log("RENAME \"$from\" \"$to\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED RENAME \"$from\" \"$to\"");
			return false;
		}
	}

	public function copy(string $from, string $to, bool $log = true) : bool {
		if($from == $to) return true;
		if(file_exists($to) && pathinfo($from, PATHINFO_DIRNAME) != pathinfo($to, PATHINFO_DIRNAME)){
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\" FILE EXIST");
			return false;
		}
		$dir = pathinfo($to, PATHINFO_DIRNAME);
		if(!file_exists($dir)) $this->mkdir($dir);
		if(copy($from, $to)){
			if($log) $this->write_log("COPY \"$from\" \"$to\"");
			return true;
		} else {
			if($log) $this->write_error("FAILED COPY \"$from\" \"$to\"");
			return false;
		}
	}

}

?>
