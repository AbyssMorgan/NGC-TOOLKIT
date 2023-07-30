<?php

declare(strict_types=1);

use App\Services\IniFile;
use App\Services\AveCore;

use App\Tools\AveSettings;
use App\Tools\AveConsole;
use App\Tools\FileNamesEditor;
use App\Tools\FileFunctions;
use App\Tools\MediaSorter;
use App\Tools\DirectoryFunctions;
use App\Tools\MediaTools;
use App\Tools\CheckFileIntegrity;
use App\Tools\MySQLTools;
use App\Tools\FileEditor;
use App\Tools\FtpTools;

class AVE extends AveCore {

	public IniFile $mkvmerge;

	public string $app_data;
	public bool $abort = false;

	public string $app_name = "AVE-PHP";
	public string $version = "1.9.0";

	private array $folders_to_scan = [
		'bin',
		'includes',
		'commands',
	];

	public function __construct(array $arguments){
		parent::__construct($arguments, true);
		dl('php_imagick.dll');
		$this->logo = "\r\n $this->app_name Toolkit v$this->version by Abyss Morgan\r\n";
		$changed = false;

		$this->app_data = $this->get_file_path($this->get_variable("%LOCALAPPDATA%")."/AVE");
		$path_config_ave = $this->get_file_path("$this->app_data/config.ini");
		$path_config_mysql = $this->get_file_path("$this->app_data/MySQL");
		$path_config_ftp = $this->get_file_path("$this->app_data/FTP");

		if(!file_exists($this->app_data)) mkdir($this->app_data);
		if(!file_exists($path_config_mysql)) mkdir($path_config_mysql);
		if(!file_exists($path_config_ftp)) mkdir($path_config_ftp);

		$config_default = new IniFile($this->get_file_path("$this->path/includes/config/default.ini"), true);
		$this->config = new IniFile($path_config_ave, true);
		$this->mkvmerge = new IniFile($this->get_file_path("$this->path/includes/config/mkvmerge.ini"), true);

		if($this->get_version_number($this->config->get('APP_VERSION','0.0.0')) < 10900){
			$this->config->unset(['AVE_EXTENSIONS_VIDEO_FOLLOW']);
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
			case '--script': {
				$this->logo = '';
				$path = $this->arguments[0] ?? '';
				if(empty($path) || !file_exists($path)){
					$this->echo(" File \"$path\" not exists");
				} else {
					$this->tool = new AveConsole($this);
					$this->tool->execute($path);
					$this->exit(0, false);
				}
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
			' 8 - FTP Tools',
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
			case '8': {
				$this->tool = new FtpTools($this);
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
