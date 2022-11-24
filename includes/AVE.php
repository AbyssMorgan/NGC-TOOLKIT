<?php

declare(strict_types=1);

use App\Services\IniFile;
use App\Services\Logs;
use App\Services\CommandLine;
use App\Services\GuardDriver;

use App\Tools\NamesGenerator;
use App\Tools\FileFunctions;
use App\Tools\MediaSorter;
use App\Tools\DirectoryFunctions;
use App\Tools\MediaTools;
use App\Tools\CheckFileIntegrity;
use App\Tools\MySQLTools;

class AVE extends CommandLine {

	public IniFile $config;
	public IniFile $mkvmerge;

	public Logs $log_event;
	public Logs $log_error;
	public Logs $log_data;

	public string $path;
	public bool $abort = false;
	public bool $open_log = false;

	private string $app_name = "AVE";
	private string $version = "1.4.3";
	private ?string $command;
	private array $arguments;
	private string $logo;
	private string $tool_name;
	private string $subtool_name;
	private array $folders_state = [];
	private string $guard_file;
	private $tool;

	private array $folders_to_scan = [
		'bin',
		'includes',
		'commands',
	];

	private array $files_to_scan = [
		'config/default.ini',
		'config/mkvmerge.ini',
	];

	public function __construct(array $arguments){
		parent::__construct();
		date_default_timezone_set(IntlTimeZone::createDefault()->getID());
		$this->path = __DIR__.DIRECTORY_SEPARATOR."..";
		unset($arguments[0]);
		$this->command = $arguments[1] ?? null;
		if(isset($arguments[1])) unset($arguments[1]);
		$this->arguments = array_values($arguments);
		$this->logo = "\r\n AVE-PHP Toolkit v$this->version by Abyss Morgan\r\n";
		$changed = false;
		$config_default = new IniFile("$this->path/config/default.ini", true);
		$this->config = new IniFile("$this->path/config/user.ini", true);
		$this->mkvmerge = new IniFile("$this->path/config/mkvmerge.ini", true);
		$this->guard_file = "$this->path/AVE.ave-guard";
		foreach($config_default->getAll() as $key => $value){
			if(!$this->config->isSet($key)){
				$this->config->set($key, $value);
				$changed = true;
			}
		}

		foreach($this->config->allExcept(['APP_NEXT_CHECK_FOR_UPDATE', 'APP_VERSION']) as $key => $value){
			if(!$config_default->isSet($key)){
				$this->config->unset($key);
				$changed = true;
			}
		}

		popen('color '.$this->config->get('AVE_COLOR'), 'w');

		$version_changed = false;
		$check_for_updates = false;
		if($this->command == '--interactive'){
			if($this->version != $this->config->get('APP_VERSION')){
				$this->config->set('APP_VERSION', $this->version);
				$changed = true;
				$version_changed = true;
			}
			if($this->config->get('AVE_CHECK_FOR_UPDATES')){
				$next_check_update = $this->config->get('APP_NEXT_CHECK_FOR_UPDATE', date("U") - 3600);
				if(date("U") >= $next_check_update){
					$this->config->set('APP_NEXT_CHECK_FOR_UPDATE', date("U") + 86400 * $this->config->get('AVE_CHECK_FOR_UPDATES_DAYS'));
					$changed = true;
					$check_for_updates = true;
				}
			}
		}
		if($changed){
			$this->config->save();
		}
		$keys = [
			'AVE_LOG_FOLDER',
			'AVE_DATA_FOLDER',
		];
		foreach($keys as $key){
			$this->config->set($key, $this->get_variable($this->config->get($key)));
			if(!$this->is_valid_device($this->config->get($key))){
				$this->config->set($key, $this->get_variable($config_default->get($key)));
			}
		}

		$config_default->close();

		$this->init_logs();
		ini_set('memory_limit', $this->config->get('AVE_MAX_MEMORY_LIMIT'));

		$dev = file_exists($this->path.DIRECTORY_SEPARATOR."_get_package.cmd");

		if($version_changed && !$dev){
			echo " Check for remove unused files...\r\n";
			$validation = $this->validate(['damaged' => false, 'unknown' => true, 'missing' => false]);
			foreach($validation as $error){
				if($error['type'] == 'unknown'){
					$this->unlink($this->path.DIRECTORY_SEPARATOR.$error['file']);
				}
			}
		}

		if($check_for_updates && !$dev){
			$this->tool_update();
		}
	}

	public function check_for_updates(string &$version) : bool {
		$ch = curl_init("https://raw.githubusercontent.com/AbyssMorgan/AVE-PHP/master/version");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$response = curl_exec($ch);
		if(!curl_errno($ch)){
			$version = $response;
			return ($this->version != $response);
		} else {
			$error = curl_error($ch);
  		echo " Failed check for updates: $error\r\n";
			return false;
		}
	}

	public function download_update($version){
		echo " Download update...\r\n";
		$file = $this->path.DIRECTORY_SEPARATOR."AVE-PHP.7z";
		if(file_exists($file)) unlink($file);
		$fh = fopen($file, "wb");
		$ch = curl_init("https://github.com/AbyssMorgan/AVE-PHP/releases/download/v$version/AVE-PHP.7z");
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_FILE, $fh);
		$response = curl_exec($ch);
		if(!curl_errno($ch)){
			exec("START \"\" \"\"");
			$this->abort = true;
		} else {
  		echo " Failed download updates: $error\r\n";
		}
	}

	public function tool_update(bool $response = false){
		echo " Check for updates ...\r\n";
		$version = '';
		if($this->check_for_updates($version)){
			echo " Update available AVE-PHP v$version current v$this->version\r\n";
			echo " Download now (Y/N): ";
			$line = $this->get_input();
			if(strtoupper($line[0] ?? 'N') == 'Y'){
				$this->download_update($version);
			}
		} else if($response){
			echo " No updates available\r\n";
			$this->pause();
		}
	}

	public function clear() : void {
		$this->cls();
		if($this->config->get('AVE_SHOW_LOGO')){
			echo "$this->logo\r\n";
		} else {
			echo "\r\n";
		}
	}

	public function execute() : void {
		switch(strtolower($this->command ?? '')){
			case '--make-backup': {
				if(empty($this->arguments[0] ?? '')){
					$this->print_help([" Usage: --make-backup <label>"]);
				} else {
					$this->tool = new MySQLTools($this);
					$this->tool->ToolMakeBackupCMD($this->arguments[0] ?? '');
				}
				break;
			}
			case '--guard-generate': {
				$guard = new GuardDriver($this->guard_file);
				$cwd = getcwd();
				chdir($this->path);
				$guard->setFolders($this->folders_to_scan);
				$guard->setFiles($this->files_to_scan);
				$guard->generate();
				chdir($cwd);
				break;
			}
			case '--sort-settings': {
				$config = new IniFile("$this->path/config/default.ini", true);
				$config->save();
				break;
			}
			case '--put-version': {
				file_put_contents("$this->path/version", $this->version);
				break;
			}
			case '--guard-validate': {
				echo print_r($this->validate(), true);
				break;
			}
			case '--interactive': {
				while(!$this->abort){
					$this->abort = $this->select_tool();
				}
				$this->exit(10, $this->open_log);
				break;
			}
			default: {
				echo "Unknown command: \"$this->command\"\r\n";
				break;
			}
		}
	}

	public function validate(array $flags = ['damaged' => true, 'unknown' => true, 'missing' => true]) : array {
		$guard = new GuardDriver($this->guard_file);
		$cwd = getcwd();
		chdir($this->path);
		$guard->setFolders($this->folders_to_scan);
		$guard->setFiles($this->files_to_scan);
		$validation = $guard->validate($flags);
		chdir($cwd);
		return $validation;
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
		$this->title("[$title] Files: $progress Errors: $errors");
	}

	public function set_progress_ex(string $label, int $progress, int $total) : void {
		$title = "$this->app_name v$this->version > $this->tool_name";
		if(!empty($this->subtool_name)) $title .= " > $this->subtool_name";
		$this->title("[$title] $label: $progress / $total");
	}

	public function select_tool() : bool {
		$this->write_log("Select Tool");
		$this->clear();
		$this->title("$this->app_name v$this->version");
		$this->tool = null;
		$this->tool_name = '';
		$this->print_help([
			' Tools:',
			' 0 - Names Generator',
			' 1 - File Functions',
			' 2 - Media Sorter',
			' 3 - Directory Functions',
			' 4 - Media Tools',
			' 5 - Check File Integrity',
			' 6 - MySQL Tools',
			' U - Check for updates',
		]);

		echo ' Tool: ';
		$line = $this->get_input();
		switch($line){
			case '0': {
				$this->tool = new NamesGenerator($this);
				break;
			}
			case '1': {
				$this->tool = new FileFunctions($this);
				break;
			}
			case '2': {
				$this->tool = new MediaSorter($this);
				break;
			}
			case '3': {
				$this->tool = new DirectoryFunctions($this);
				break;
			}
			case '4': {
				$this->tool = new MediaTools($this);
				break;
			}
			case '5': {
				$this->tool = new CheckFileIntegrity($this);
				break;
			}
			case '6': {
				$this->tool = new MySQLTools($this);
				break;
			}
			case 'U': {
				$this->clear();
				$this->title("$this->app_name v$this->version > CheckForUpdates");
				$this->tool_update(true);
				return true;
				break;
			}
		}
		if(!$this->abort && !is_null($this->tool)){
			return $this->select_action();
		}
		return false;
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

	public function rmdir(string $path) : bool {
		if(!file_exists($path) || !is_dir($path)) return false;
		if(rmdir($path)){
			$this->write_log("DELETE \"$path\"");
			return true;
		} else {
			$this->write_error("FAILED DELETE \"$path\"");
			return false;
		}
	}

	public function unlink(string $path) : bool {
		if(!file_exists($path) || is_dir($path)) return false;
		if(unlink($path)){
			$this->write_log("DELETE \"$path\"");
			return true;
		} else {
			$this->write_error("FAILED DELETE \"$path\"");
			return false;
		}
	}

	public function mkdir(string $path) : bool {
		if(file_exists($path) && is_dir($path)) return true;
		if(mkdir($path, 0777, true)){
			$this->write_log("MKDIR \"$path\"");
			return true;
		} else {
			$this->write_error("FAILED MKDIR \"$path\"");
			return false;
		}
	}

	public function rename(string $from, string $to) : bool {
		if($from == $to) return true;
		if(file_exists($to) && pathinfo($from, PATHINFO_DIRNAME) != pathinfo($to, PATHINFO_DIRNAME)){
			$this->write_error("FAILED RENAME \"$from\" \"$to\" FILE EXIST");
			return false;
		}
		if(rename($from, $to)){
			$this->write_log("RENAME \"$from\" \"$to\"");
			return true;
		} else {
			$this->write_error("FAILED RENAME \"$from\" \"$to\"");
			return false;
		}
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

	public function write_log(string|array $data) : void {
		if($this->config->get('AVE_LOG_EVENT')){
			$this->log_event->write($data);
		}
	}

	public function write_error(string|array $data) : void {
		if($this->config->get('AVE_LOG_ERROR')){
			$this->log_error->write($data);
		}
	}

	public function write_data(string|array $data) : void {
		$this->log_data->write($data);
	}

	public function exit(int $seconds = 10, bool $open_log = false) : void {
		$this->write_log("Exit");
		$this->open_logs($open_log, false);
		$this->timeout($seconds);
	}

	public function init_logs(){
		$timestamp = date("Y-m-d His");
		$this->log_event = new Logs($this->config->get('AVE_LOG_FOLDER').DIRECTORY_SEPARATOR."$timestamp-Event.txt", true, true);
		$this->log_error = new Logs($this->config->get('AVE_LOG_FOLDER').DIRECTORY_SEPARATOR."$timestamp-Error.txt", true, true);
		$this->log_data = new Logs($this->config->get('AVE_DATA_FOLDER').DIRECTORY_SEPARATOR."$timestamp.txt", false, true);
	}

	public function open_logs(bool $open_event = false, bool $init = true) : void {
		$this->log_event->close();
		$this->log_error->close();
		$this->log_data->close();
		if($open_event && file_exists($this->log_event->getPath())){
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

	private function timeout(int $seconds) : void {
		$this->title("$this->app_name v$this->version > Exit $seconds seconds");
		if($seconds > 0){
			sleep(1);
			$seconds--;
			$this->timeout($seconds);
		} else {
			exit(0);
		}
	}

}

?>
