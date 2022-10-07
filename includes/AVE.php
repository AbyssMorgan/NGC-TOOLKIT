<?php

class AVE extends CommandLine {

	public IniFile $config;
	public string $path;

	private string $app_name = "AVE";
	private string $version = "1.0";
	private string|null $command;
	private array $arguments;
	private string $logo;
	private string $tool_name;
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
	}

	public function clear(){
		$this->cls();
		if($this->config->get('AVE_SHOW_LOGO')) echo $this->logo;
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
		$this->title("[$this->app_name v$this->version > $this->tool_name]");
	}

	public function set_progress($progress, $errors){
		$this->title("[$this->app_name v$this->version > $this->tool_name] Files: $progress Errors: $errors");
	}

	public function select_tool(){
		$this->clear();
		$this->title("[$this->app_name v$this->version]");
		$this->tool = null;
		$this->tool_name = '';
		echo " Tools:\r\n";
		echo " 0 - Names Generator\r\n";
		echo " 1 - File Functions\r\n";
		echo " 2 - Directory Functions\r\n";
		echo "\r\n Tool: ";
		$line = $this->get_input();
		switch($line){
			case '0': {
				$this->tool = new NamesGenerator($this);
				break;
			}
			case '1': {

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
		echo "\r\n Action: ";
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

	public function exit(int $seconds = 10){
		$this->title("[$this->app_name v$this->version > Wait] $seconds seconds");
		if($seconds > 0){
			sleep(1);
			$seconds--;
			$this->exit($seconds);
		} else {
			exit(0);
		}
	}

}

?>
