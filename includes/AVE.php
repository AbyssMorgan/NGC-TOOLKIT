<?php

use App\Services\IniFile;
use App\Services\Logs;
use App\Services\CommandLine;

use App\Tools\NamesGenerator;
use App\Tools\FileFunctions;

class AVE extends CommandLine {

	use App\Extensions\MediaFunctions;

	public IniFile $config;

	public Logs $log_event;
	public Logs $log_error;
	public Logs $log_data;

	public string $path;

	private string $app_name = "AVE";
	private string $version = "1.0";
	private string|null $command;
	private array $arguments;
	private string $logo;
	private string $tool_name;
	private string $subtool_name;
	private array $folders_state = [];
	private $tool;

	public function __construct(array $arguments){
		parent::__construct();
		$this->path = __DIR__."/..";
		unset($arguments[0]);
		$this->command = $arguments[1] ?? null;
		if(isset($arguments[1])) unset($arguments[1]);
		$this->arguments = array_values($arguments);
		$this->logo = file_get_contents("$this->path/meta/GUI.txt");
		$changed = false;
		$config_default = new IniFile("$this->path/config/default.ini", true);
		$this->config = new IniFile("$this->path/config/user.ini", true);
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

		$this->log_event = new Logs($this->config->get('AVE_LOG_FOLDER').DIRECTORY_SEPARATOR."$timestamp-Event.txt");
		$this->log_error = new Logs($this->config->get('AVE_LOG_FOLDER').DIRECTORY_SEPARATOR."$timestamp-Error.txt");
		$this->log_data = new Logs($this->config->get('AVE_DATA_FOLDER').DIRECTORY_SEPARATOR."$timestamp.txt", false);
	}

	public function clear(){
		$this->cls();
		if($this->config->get('AVE_SHOW_LOGO')){
			echo "$this->logo\r\n";
		} else {
			echo "\r\n";
		}
	}

	public function execute(){
		switch($this->command){
			case 'GET_COLOR': {
				echo $this->config->get('AVE_COLOR') ?? 'AF';
				break;
			}
			default: {
				$this->select_tool();
				break;
			}
		}
	}

	public function set_tool(string $name){
		$this->tool_name = $name;
		$this->subtool_name = '';
		$this->title("[$this->app_name v$this->version > $this->tool_name]");
		$this->log_event->write("Set Tool: $this->tool_name");
	}

	public function set_subtool(string $name){
		$this->subtool_name = $name;
		$this->title("[$this->app_name v$this->version > $this->tool_name > $this->subtool_name]");
		$this->log_event->write("Set Tool: $this->tool_name > $this->subtool_name");
	}

	public function set_progress($progress, $errors){
		$this->title("[$this->app_name v$this->version > $this->tool_name] Files: $progress Errors: $errors");
	}

	public function select_tool(){
		$this->log_event->write("Select Tool");
		$this->clear();
		$this->title("[$this->app_name v$this->version]");
		$this->tool = null;
		$this->tool_name = '';
		echo " Tools:\r\n";
		echo " 0 - Names Generator\r\n";
		echo " 1 - File Functions\r\n";
		// echo " 2 - Directory Functions\r\n";
		echo "\r\n Tool: ";
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

				break;
			}
		}
		if(!is_null($this->tool)){
			$this->select_action();
		} else {
			$this->select_tool();
		}
	}

	public function select_action(){
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

	}

	public function setup_folders(array $folders){
		foreach($folders as $folder){
			$this->folders_state[$folder] = file_exists($folder) ? '' : '[NOT EXIST]';
			$this->log_event->write("Scan: $folder");
		}
		$this->print_folders_state();
	}

	public function set_folder_done(string $folder){
		$this->folders_state[$folder] = '[DONE]';
		$this->print_folders_state();
	}

	public function print_folders_state(){
		$this->clear();
		foreach($this->folders_state as $folder_name => $state){
			echo " Scan: \"$folder_name\" $state\r\n";
		}
	}

	public function unlink($path){
		if(!file_exists($path) || is_dir($path)) return false;
		if(unlink($file)){
			$this->log_event->write("DELETE \"$path\"");
			return true;
		} else {
			$this->log_error->write("FAILED DELETE \"$path\"");
			return false;
		}
	}

	public function mkdir(string $path){
		if(file_exists($path)) return false;
		if(mkdir($path, octdec($this->config->get('AVE_DEFAULT_FOLDER_PERMISSION')), true)){
			$this->log_event->write("MKDIR \"$path\"");
			return true;
		} else {
			$this->log_error->write("FAILED MKDIR \"$path\"");
			return false;
		}
	}

	public function rename(string $from, string $to){
		if($from == $to) return true;
		if(file_exists($to)){
			$this->log_error->write("FAILED RENAME \"$from\" \"$to\" FILE EXISTS");
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

	public function print_help(array $help){
		echo implode("\r\n", $help)."\r\n\r\n";
	}

	public function exit(int $seconds = 10){
		$this->log_event->write("Exit");
		if(file_exists($this->log_data->getPath())){
			$this->open_file($this->log_data->getPath());
		}
		if(file_exists($this->log_error->getPath())){
			$this->open_file($this->log_error->getPath());
		}
		$this->timeout($seconds);
	}

	private function timeout(int $seconds){
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
