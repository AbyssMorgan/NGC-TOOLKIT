<?php

declare(strict_types=1);

use App\Services\IniFile;
use App\Services\AveCore;

use App\Tools\AveSettings;
use App\Tools\FileNamesEditor;
use App\Tools\FileFunctions;
use App\Tools\MediaSorter;
use App\Tools\DirectoryFunctions;
use App\Tools\MediaTools;
use App\Tools\CheckFileIntegrity;
use App\Tools\MySQLTools;
use App\Tools\FileEditor;

class AVE extends AveCore {

	public IniFile $mkvmerge;

	public string $app_data;
	public bool $abort = false;

	public string $app_name = "AVE-PHP";
	public string $version = "1.7.0";
	public string $utilities_version = "1.0.0";

	private array $folders_to_scan = [
		'bin',
		'includes',
		'commands',
	];

	public function __construct(array $arguments){
		parent::__construct($arguments);
		dl('php_imagick.dll');
		$this->logo = "\r\n $this->app_name Toolkit v$this->version by Abyss Morgan\r\n";
		$changed = false;

		$ave_utilities_path = $this->get_file_path($this->get_variable("%PROGRAMFILES%")."/AVE-UTILITIES");

		$ave_utilities = false;
		if(file_exists($ave_utilities_path)){
			$ave_utilities_main = new IniFile($this->get_file_path("$ave_utilities_path/main.ini"));
			$ave_utilities_imagick = new IniFile($this->get_file_path("$ave_utilities_path/imagick.ini"));
			if($ave_utilities_main->get('APP_VERSION') == $this->utilities_version && $ave_utilities_imagick->get('APP_VERSION') == $this->utilities_version){
				$ave_utilities = true;
			}
		}

		if(!$ave_utilities){
			$this->echo();
			$this->echo(" Invalid AVE-UTILITIES version detected: v".$ave_utilities_main->get('APP_VERSION')." required: v$this->utilities_version");
			$this->echo();
			$this->pause();
			die("");
		}

		$this->app_data = $this->get_file_path($this->get_variable("%LOCALAPPDATA%")."/AVE");
		$old_config = $this->get_file_path("$this->path/config/user.ini");
		$new_config = $this->get_file_path("$this->app_data/config.ini");
		$old_mysql_config = $this->get_file_path("$this->path/config/mysql");
		$new_mysql_config = $this->get_file_path("$this->app_data/MySQL");

		if(!file_exists($this->app_data)) mkdir($this->app_data);
		if(!file_exists($new_mysql_config)) mkdir($new_mysql_config);

		if(file_exists($old_config)){
			$this->config = new IniFile($old_config, true);
			$this->rename($old_config, $new_config, false);
		}

		if(file_exists($old_mysql_config)){
			$files = $this->getFiles($old_mysql_config, ['ini']);
			foreach($files as $file){
				$this->rename($file, $this->get_file_path("$new_mysql_config/".pathinfo($file, PATHINFO_BASENAME)), false);
			}
			@rmdir($old_mysql_config);
		}

		$old_config_folder = $this->get_file_path("$this->path/config");
		if(file_exists($old_config_folder)) @rmdir($old_config_folder);

		$config_default = new IniFile($this->get_file_path("$this->path/includes/config/default.ini"), true);
		$this->config = new IniFile($new_config, true);
		$this->mkvmerge = new IniFile($this->get_file_path("$this->path/includes/config/mkvmerge.ini"), true);

		if($this->get_version_number($this->config->get('APP_VERSION','0.0.0')) < 10500){
			$this->config->unset(['AVE_LOG_FOLDER','AVE_DATA_FOLDER','AVE_EXTENSIONS_AUDIO']);
		}

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
		ini_set('memory_limit', -1);

		$dev = file_exists($this->get_file_path("$this->path/.git"));
		if($dev){
			if(file_get_contents("$this->path/version") != $this->version){
				file_put_contents("$this->path/version", $this->version);
			}
		}
		if($check_for_updates && !$dev){
			$this->tool = new AveSettings($this);
			$this->tool->ToolCheckForUpdates(false);
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
			case '--sort-settings': {
				$config = new IniFile("$this->path/includes/config/default.ini", true);
				$config->save();
				break;
			}
			case '--interactive': {
				while(!$this->abort){
					$this->abort = $this->select_tool();
				}
				$this->exit(10, false);
				break;
			}
			default: {
				$this->echo("Unknown command: \"$this->command\"");
				break;
			}
		}
	}

	public function select_tool() : bool {
		$this->write_log("Select Tool");
		$this->clear();
		$this->title("$this->app_name v$this->version");
		$this->tool = null;
		$this->tool_name = '';
		$this->print_help([
			' Tools:',
			' 0 - File Names Editor',
			' 1 - File Functions',
			' 2 - Media Sorter',
			' 3 - Directory Functions',
			' 4 - Media Tools',
			' 5 - Check File Integrity',
			' 6 - MySQL Tools',
			' 7 - File Editor',
			' H - Help',
		]);

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
			case 'H': {
				$this->tool = new AveSettings($this);
				break;
			}
		}
		if(!$this->abort && !is_null($this->tool)){
			return $this->select_action($dynamic_action[1] ?? null);
		}
		return false;
	}

}

?>
