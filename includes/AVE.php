<?php

declare(strict_types=1);

use App\Services\IniFile;
use App\Services\Logs;
use App\Services\CommandLine;

use App\Tools\NamesGenerator;
use App\Tools\FileFunctions;
use App\Tools\MediaSorter;
use App\Tools\DirectoryFunctions;
use App\Tools\MediaTools;

class AVE extends CommandLine {

	use App\Extensions\MediaFunctions;

	public IniFile $config;
	public IniFile $mkvmerge;

	public Logs $log_event;
	public Logs $log_error;
	public Logs $log_data;

	public string $path;

	private string $app_name = "AVE";
	private string $version = "1.0";
	private ?string $command;
	private array $arguments;
	private string $logo;
	private string $tool_name;
	private string $subtool_name;
	private array $folders_state = [];
	private $tool;

	public function __construct(array $arguments){
		parent::__construct();
		$this->path = __DIR__.DIRECTORY_SEPARATOR."..";
		unset($arguments[0]);
		$this->command = $arguments[1] ?? null;
		if(isset($arguments[1])) unset($arguments[1]);
		$this->arguments = array_values($arguments);
		$this->logo = file_get_contents("$this->path/meta/GUI.txt");
		$changed = false;
		$config_default = new IniFile("$this->path/config/default.ini", true);
		$this->config = new IniFile("$this->path/config/user.ini", true);
		$this->mkvmerge = new IniFile("$this->path/config/mkvmerge.ini", true);
		foreach($config_default->getAll() as $key => $value){
			if(!$this->config->isSet($key)){
				$this->config->set($key,$value);
				$changed = true;
			}
		}
		$config_default->close();
		if($changed){
			$this->config->save();
		}
		$keys = [
			'AVE_LOG_FOLDER',
			'AVE_DATA_FOLDER',
		];
		foreach($keys as $key){
			$this->config->set($key, $this->get_variable($this->config->get($key)));
		}

		$timestamp = date("Y-m-d His");

		$this->log_event = new Logs($this->config->get('AVE_LOG_FOLDER').DIRECTORY_SEPARATOR."$timestamp-Event.txt", true, true);
		$this->log_error = new Logs($this->config->get('AVE_LOG_FOLDER').DIRECTORY_SEPARATOR."$timestamp-Error.txt", true, true);
		$this->log_data = new Logs($this->config->get('AVE_DATA_FOLDER').DIRECTORY_SEPARATOR."$timestamp.txt", false, true);
		ini_set('memory_limit', $this->config->get('AVE_MAX_MEMORY_LIMIT'));
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
		if(is_null($this->command)){
			$this->select_tool();
		} else {
			switch(strtolower($this->command)){
				case 'get_color': {
					echo $this->config->get('AVE_COLOR') ?? 'AF';
					break;
				}
				default: {
					echo "Unknown command\r\n";
					break;
				}
			}
		}
	}

	public function set_tool(string $name) : void {
		$this->tool_name = $name;
		$this->subtool_name = '';
		$this->title("[$this->app_name v$this->version > $this->tool_name]");
		$this->log_event->write("Set Tool: $this->tool_name");
	}

	public function set_subtool(string $name) : void {
		$this->subtool_name = $name;
		$this->title("[$this->app_name v$this->version > $this->tool_name > $this->subtool_name]");
		$this->log_event->write("Set Tool: $this->tool_name > $this->subtool_name");
	}

	public function set_progress(int $progress, int $errors) : void {
		$title = "$this->app_name v$this->version > $this->tool_name";
		if(!empty($this->subtool_name)) $title .= " > $this->subtool_name";
		$this->title("[$title] Files: $progress Errors: $errors");
	}

	public function select_tool() : void {
		$this->log_event->write("Select Tool");
		$this->clear();
		$this->title("[$this->app_name v$this->version]");
		$this->tool = null;
		$this->tool_name = '';
		$this->print_help([
			' Tools:',
			' 0 - Names Generator',
			' 1 - File Functions',
			' 2 - Media Sorter',
			' 3 - Directory Functions',
			' 4 - Media Tools',
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
		}
		if(!is_null($this->tool)){
			$this->select_action();
		} else {
			$this->select_tool();
		}
	}

	public function select_action() : bool {
		$this->clear();
		$this->title("[$this->app_name v$this->version > $this->tool_name]");
		$this->tool->help();
		echo " Action: ";
		$line = $this->get_input();
		if($line == '#'){
			$this->select_tool();
		} else {
			$this->tool->action($line);
		}
		return true;
	}

	public function setup_folders(array $folders) : void {
		foreach($folders as $folder){
			$this->folders_state[$folder] = file_exists($folder) ? '' : '[NOT EXIST]';
			$this->log_event->write("Scan: $folder");
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
			$this->log_event->write("DELETE \"$path\"");
			return true;
		} else {
			$this->log_error->write("FAILED DELETE \"$path\"");
			return false;
		}
	}

	public function unlink(string $path) : bool {
		if(!file_exists($path) || is_dir($path)) return false;
		if(unlink($path)){
			$this->log_event->write("DELETE \"$path\"");
			return true;
		} else {
			$this->log_error->write("FAILED DELETE \"$path\"");
			return false;
		}
	}

	public function mkdir(string $path) : bool {
		if(mkdir($path, 0777, true)){
			$this->log_event->write("MKDIR \"$path\"");
			return true;
		} else {
			$this->log_error->write("FAILED MKDIR \"$path\"");
			return false;
		}
	}

	public function rename(string $from, string $to) : bool {
		if($from == $to) return true;
		if(file_exists($to) && pathinfo($from, PATHINFO_DIRNAME) != pathinfo($to, PATHINFO_DIRNAME)){
			$this->log_error->write("FAILED RENAME \"$from\" \"$to\" FILE EXIST");
			return false;
		}
		if(rename($from, $to)){
			$this->log_event->write("RENAME \"$from\" \"$to\"");
			return true;
		} else {
			$this->log_error->write("FAILED RENAME \"$from\" \"$to\"");
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

	public function getFiles(string $path, array|null $extensions = null, array|null $except = null) : array {
		$data = [];
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS));
		foreach($files as $file){
			if($file->isDir() || $file->isLink()) continue;
			if(!is_null($extensions) && !in_array(strtolower($file->getExtension()), $extensions)) continue;
			if(!is_null($except) && in_array(strtolower($file->getExtension()), $except)) continue;
			array_push($data, $file->getRealPath());
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

	public function exit(int $seconds = 10) : void {
		$this->log_event->write("Exit");
		$this->log_event->close();
		$this->log_error->close();
		$this->log_data->close();

		if(file_exists($this->log_data->getPath())){
			$this->open_file($this->log_data->getPath());
		}
		if(file_exists($this->log_error->getPath())){
			$this->open_file($this->log_error->getPath());
		}
		$this->timeout($seconds);
	}

	private function timeout(int $seconds) : void {
		$this->title("[$this->app_name v$this->version > Wait] $seconds seconds");
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
