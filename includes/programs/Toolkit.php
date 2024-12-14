<?php

declare(strict_types=1);

use NGC\Core\Core;
use NGC\Core\IniFile;
use NGC\Core\AppBuffer;

use NGC\Extensions\AppStorage;
use NGC\Extensions\MediaFunctions;

use NGC\Tools\AdmFileConverter;
use NGC\Tools\Settings;
use NGC\Tools\CheckFileIntegrity;
use NGC\Tools\DirectoryFunctions;
use NGC\Tools\DirectoryNamesEditor;
use NGC\Tools\FileEditor;
use NGC\Tools\FileFunctions;
use NGC\Tools\FileNamesEditor;
use NGC\Tools\FtpTools;
use NGC\Tools\MediaSorter;
use NGC\Tools\MediaTools;
use NGC\Tools\MySQLTools;

class Toolkit extends Core {

	public IniFile $mkvmerge;
	public AppBuffer $app_buffer;
	public string $app_data;
	public bool $abort = false;
	public string $app_name = "NGC-TOOLKIT";
	public string $version = "2.3.3";
	public AppStorage $storage;
	public MediaFunctions $media;

	public function __construct(array $arguments){
		parent::__construct($arguments, true);
		if($this->abort) return;
		$config_default = new IniFile($this->get_path("$this->path/includes/config/default.ini"), true);
		if($this->windows){
			dl('php_imagick.dll');
			dl('php_exif.dll');
			$config_default_system = new IniFile($this->get_path("$this->path/includes/config/windows.ini"), true);
			$old_app_data = $this->get_path($this->get_variable("%LOCALAPPDATA%")."/AVE-PHP");
			$this->app_data = $this->get_path($this->get_variable("%LOCALAPPDATA%")."/NGC-TOOLKIT");
			if(file_exists($old_app_data) && !file_exists($this->app_data)){
				rename($old_app_data, $this->app_data);
			}
		} else {
			$config_default_system = new IniFile($this->get_path("$this->path/includes/config/linux.ini"), true);
			$this->app_data = $this->get_path("/etc/NGC-TOOLKIT");
			$open_file_binary = null;
			$variants = ['xdg-open', 'nautilus', 'dolphin'];
			foreach($variants as $variant){
				if(file_exists("/usr/bin/$variant")){
					$open_file_binary = $variant;
				}
			}
			$config_default_system->set('OPEN_FILE_BINARY', $open_file_binary);
		}

		$config_default->update($config_default_system->get_all());

		$this->logo = "\r\n $this->app_name v$this->version by Abyss Morgan\r\n";
		$changed = false;

		$path_config_toolkit = $this->get_path("$this->app_data/config.ini");

		if(!file_exists($this->app_data)) mkdir($this->app_data);

		$path_config_mysql = $this->get_path("$this->app_data/MySQL");
		if(!file_exists($path_config_mysql)) mkdir($path_config_mysql);

		$path_config_ftp = $this->get_path("$this->app_data/FTP");
		if(!file_exists($path_config_ftp)) mkdir($path_config_ftp);

		$this->config = new IniFile($path_config_toolkit, true);
		$this->mkvmerge = new IniFile($this->get_path("$this->path/includes/config/mkvmerge.ini"), true);
		$this->storage = new AppStorage($this);
		$this->media = new MediaFunctions($this);

		foreach($config_default->get_all() as $key => $value){
			if(!$this->config->is_set($key)){
				$this->config->set($key, $value);
				$changed = true;
			}
		}

		foreach($this->config->all_except(['APP_NEXT_CHECK_FOR_UPDATE', 'APP_VERSION']) as $key => $value){
			if(!$config_default->is_set($key)){
				$this->config->unset($key);
				$changed = true;
			}
		}

		if($this->windows) popen('color '.$this->config->get('COLOR'), 'w');

		$check_for_updates = false;
		if($this->command == '--interactive'){
			if($this->version != $this->config->get('APP_VERSION')){
				$this->config->set('APP_VERSION', $this->version);
				$changed = true;
			}
			if($this->config->get('CHECK_FOR_UPDATES')){
				$next_check_update = $this->config->get('APP_NEXT_CHECK_FOR_UPDATE', date("U") - 3600);
				if(date("U") >= $next_check_update){
					$this->config->set('APP_NEXT_CHECK_FOR_UPDATE', date("U") + 86400 * $this->config->get('CHECK_FOR_UPDATES_DAYS'));
					$changed = true;
					$check_for_updates = true;
				}
			}

			$files = [
				$this->get_path($this->get_variable("%PROGRAMFILES%")."/NGC-UTILITIES/main"),
				$this->get_path($this->get_variable("%PROGRAMFILES%")."/NGC-UTILITIES/php/8.1"),
			];

			$items = $this->get_folders_ex($this->get_path($this->get_variable("%PROGRAMFILES%")."/NGC-UTILITIES/core"));
			foreach($items as $item){
				if(pathinfo($item, PATHINFO_BASENAME) != $this->utilities_version) array_push($files, $item);
			}

			foreach($files as $file){
				if(!file_exists($file)) continue;
				if(!$this->is_admin()){
					$this->echo();
					$this->echo(" Please run once NGC-TOOLKIT as administrator for remove old NGC-UTILITIES files");
					$this->echo();
					$this->pause();
					$this->exit(0, false);
					return;
				}
				$this->rrmdir($file, false);
				if(strpos($file, "php") !== false){
					$this->delete($this->get_path(pathinfo($file, PATHINFO_DIRNAME)."/".pathinfo($file, PATHINFO_BASENAME).".ini"), false);
				}
			}
		}
		if($changed){
			$this->config->save();
		}
		$keys = [
			'LOG_FOLDER',
			'DATA_FOLDER',
			'BUFFER_FOLDER',
		];
		foreach($keys as $key){
			$this->config->set($key, $this->get_variable($this->config->get($key)));
			if(!$this->is_valid_device($this->config->get($key))){
				$this->config->set($key, $this->get_variable($config_default->get($key)));
			}
		}

		$config_default->close();

		$this->init_logs();

		$this->app_buffer = new AppBuffer($this->get_path($this->config->get('BUFFER_FOLDER')));
		ini_set('memory_limit', -1);

		$dev = file_exists($this->get_path("$this->path/.git"));
		if($dev){
			if(file_get_contents("$this->path/version") != $this->version){
				file_put_contents("$this->path/version", $this->version);
			}
		}
		if($check_for_updates && !$dev){
			$this->tool = new Settings($this);
			$this->tool->tool_check_for_updates(false);
		}
	}

	public function execute() : void {
		switch(strtolower($this->command ?? '')){
			case '--make-backup': {
				if(empty($this->arguments[0] ?? '')){
					$this->print_help([" Usage: --make-backup <label> [dbname]"]);
				} else {
					$this->tool = new MySQLTools($this);
					$this->tool->tool_make_backup_cmd($this->arguments[0] ?? '', $this->arguments[1] ?? null);
				}
				break;
			}
			case '--interactive': {
				$this->can_exit = false;
				while(!$this->abort){
					$this->abort = $this->select_tool();
				}
				$this->exit(0, false);
				break;
			}
			default: {
				$this->echo("Unknown command: \"$this->command\"");
				break;
			}
		}
	}

	public function select_tool() : bool {
		$this->clear();
		$this->title("$this->app_name v$this->version");
		$this->tool = null;
		$this->tool_name = '';
		$options = [
			' Tools:',
			' 0  - File Names Editor',
			' 1  - File Functions',
			' 2  - Media Sorter',
			' 3  - Directory Functions',
			' 4  - Media Tools',
			' 5  - Check File Integrity',
			' 6  - MySQL Tools',
			' 7  - File Editor',
			' 8  - FTP Tools',
			' 9  - ADM File Converter',
			' 10 - Directory Names Editor',
			' H  - Help',
		];
		if(!$this->windows) array_push($options, ' # - Close program');
		$this->print_help($options);

		$line = $this->get_input(' Tool: ');
		$dynamic_action = explode(' ', $line);
		switch(strtoupper($dynamic_action[0])){
			case '0': {
				$this->tool = new FileNamesEditor($this);
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
			case '7': {
				$this->tool = new FileEditor($this);
				break;
			}
			case '8': {
				$this->tool = new FtpTools($this);
				break;
			}
			case '9': {
				$this->tool = new AdmFileConverter($this);
				break;
			}
			case '10': {
				$this->tool = new DirectoryNamesEditor($this);
				break;
			}
			case 'H': {
				$this->tool = new Settings($this);
				break;
			}
			case '#': {
				if($this->can_exit || !$this->windows) return true;
			}
		}
		if(!$this->abort && !is_null($this->tool)){
			return $this->select_action($dynamic_action[1] ?? null);
		}
		return false;
	}

}

?>
