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

use NGC\Core\Core;
use NGC\Core\IniFile;
use NGC\Extensions\AppStorage;
use NGC\Extensions\MediaFunctions;
use NGC\Tools\AdmFileConverter;
use NGC\Tools\CheckFileIntegrity;
use NGC\Tools\DirectoryFunctions;
use NGC\Tools\DirectoryNamesEditor;
use NGC\Tools\DirectorySorter;
use NGC\Tools\FileEditor;
use NGC\Tools\FileFunctions;
use NGC\Tools\FileNamesEditor;
use NGC\Tools\FileSorter;
use NGC\Tools\FtpTools;
use NGC\Tools\MediaSorter;
use NGC\Tools\MediaTools;
use NGC\Tools\MySQLTools;
use NGC\Tools\Settings;

class Toolkit extends Core {

	public string $app_data;
	public bool $abort = false;
	public string $app_name = "NGC-TOOLKIT";
	public string $version = "2.8.0";
	public AppStorage $storage;
	public MediaFunctions $media;

	public function __construct(array $arguments){
		parent::__construct($arguments);
		$this->require_utilities();
		$this->set_resources_folder($this->get_path("{$this->path}/includes/data"));
		if($this->abort) return;
		$config_default = new IniFile($this->get_path("$this->path/includes/config/default.ini"), true);
		switch($this->get_system_type()){
			case SYSTEM_TYPE_WINDOWS: {
				$config_default_system = new IniFile($this->get_path("$this->path/includes/config/windows.ini"), true);
				$this->app_data = $this->get_path($this->get_variable("%LOCALAPPDATA%")."/NGC-TOOLKIT");
				break;
			}
			case SYSTEM_TYPE_LINUX: {
				$config_default_system = new IniFile($this->get_path("$this->path/includes/config/linux.ini"), true);
				$this->app_data = $this->get_path($this->get_variable("\$HOME")."/.config/NGC-TOOLKIT");
				break;
			}
			case SYSTEM_TYPE_MACOS: {
				$config_default_system = new IniFile($this->get_path("$this->path/includes/config/macos.ini"), true);
				$this->app_data = $this->get_path($this->get_variable("\$HOME")."/Library/Application Support/NGC-TOOLKIT");
				break;
			}
		}
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			\dl('php_imagick.dll');
		} else {
			$open_file_binary = null;
			$variants = ['xdg-open', 'nautilus', 'dolphin'];
			foreach($variants as $variant){
				if(\file_exists("/usr/bin/$variant")){
					$open_file_binary = $variant;
				}
			}
			$config_default_system->set('OPEN_FILE_BINARY', $open_file_binary);
		}

		$config_default->update($config_default_system->get_all());

		$this->logo = "\r\n $this->app_name v$this->version by Abyss Morgan\r\n";
		$changed = false;

		$path_config_toolkit = $this->get_path("$this->app_data/config.ini");

		if(!\file_exists($this->app_data)) \mkdir($this->app_data);

		$path_config_mysql = $this->get_path("$this->app_data/MySQL");
		if(!\file_exists($path_config_mysql)) \mkdir($path_config_mysql);

		$path_config_ftp = $this->get_path("$this->app_data/FTP");
		if(!\file_exists($path_config_ftp)) \mkdir($path_config_ftp);

		$this->config = new IniFile($path_config_toolkit, true);
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

		$this->set_console_color($this->config->get('COLOR'));

		$check_for_updates = false;
		if($this->command == '--interactive'){
			if($this->version != $this->config->get('APP_VERSION')){
				$this->config->set('APP_VERSION', $this->version);
				$changed = true;
			}
			if($this->config->get('CHECK_FOR_UPDATES')){
				$next_check_update = $this->config->get('APP_NEXT_CHECK_FOR_UPDATE', \date("U") - 3600);
				if(\date("U") >= $next_check_update){
					$this->config->set('APP_NEXT_CHECK_FOR_UPDATE', \date("U") + 86400 * $this->config->get('CHECK_FOR_UPDATES_DAYS'));
					$changed = true;
					$check_for_updates = true;
				}
			}

			$program_files = $this->get_variable("%PROGRAMFILES%");
			$files = [
				$this->get_path("$program_files/NGC-UTILITIES/php/8.3"),
			];

			$items = $this->get_folders($this->get_path("$program_files/NGC-UTILITIES/core"), false, false);
			foreach($items as $item){
				if(\pathinfo($item, PATHINFO_BASENAME) != $this->utilities_version) \array_push($files, $item);
			}

			foreach($files as $file){
				if(!\file_exists($file)) continue;
				if(!$this->is_admin()){
					$this->echo();
					$this->echo(" Please run once NGC-TOOLKIT as administrator for remove old NGC-UTILITIES files");
					$this->echo();
					$this->pause();
					$this->close(false);
					return;
				}
				$this->rrmdir($file, false);
				if(\str_contains($file, "php")){
					$this->delete($this->get_path(\pathinfo($file, PATHINFO_DIRNAME)."/".\pathinfo($file, PATHINFO_BASENAME).".ini"), false);
				}
			}
		}
		if($changed){
			$this->config->save();
		}
		$keys = [
			'LOG_FOLDER',
			'DATA_FOLDER',
		];
		foreach($keys as $key){
			$this->config->set($key, $this->get_variable($this->config->get($key)));
			if(!$this->is_valid_path($this->config->get($key))){
				$this->config->set($key, $this->get_variable($config_default->get($key)));
			}
		}

		$config_default->close();

		$this->init_logs();

		\ini_set('memory_limit', -1);

		$dev = \file_exists($this->get_path("$this->path/.git"));
		if($dev){
			if(\file_get_contents("$this->path/version") != $this->version){
				\file_put_contents("$this->path/version", $this->version);
			}
		}
		if($check_for_updates && !$dev){
			$this->tool = new Settings($this);
			$this->tool->tool_check_for_updates(false);
		}
	}

	public function execute() : void {
		switch(\mb_strtolower($this->command ?? '')){
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
				while(!$this->abort){
					$this->abort = $this->select_tool();
					\gc_collect_cycles();
				}
				$this->close(false);
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
			' F1 - File Functions',
			' F2 - File Sorter',
			' F3 - File Names Editor',
			' F4 - File Editor',
			' D1 - Directory Functions',
			' D2 - Directory Sorter',
			' D3 - Directory Names Editor',
			' M1 - Media Tools',
			' M2 - Media Sorter',
			' T1 - MySQL Tools',
			' T2 - FTP Tools',
			' O1 - Check File Integrity',
			' O2 - ADM File Converter',
			' H  - Help',
			' #  - Close program',
		];
		$this->print_help($options);

		$line = $this->get_input(' Tool: ');
		$dynamic_action = \explode(' ', $line);
		switch(\mb_strtoupper($dynamic_action[0])){
			case 'F1': {
				$this->tool = new FileFunctions($this);
				break;
			}
			case 'F2': {
				$this->tool = new FileSorter($this);
				break;
			}
			case 'F3': {
				$this->tool = new FileNamesEditor($this);
				break;
			}
			case 'F4': {
				$this->tool = new FileEditor($this);
				break;
			}
			case 'D1': {
				$this->tool = new DirectoryFunctions($this);
				break;
			}
			case 'D2': {
				$this->tool = new DirectorySorter($this);
				break;
			}
			case 'D3': {
				$this->tool = new DirectoryNamesEditor($this);
				break;
			}
			case 'M1': {
				$this->tool = new MediaTools($this);
				break;
			}
			case 'M2': {
				$this->tool = new MediaSorter($this);
				break;
			}
			case 'T1': {
				$this->tool = new MySQLTools($this);
				break;
			}
			case 'T2': {
				$this->tool = new FtpTools($this);
				break;
			}
			case 'O1': {
				$this->tool = new CheckFileIntegrity($this);
				break;
			}
			case 'O2': {
				$this->tool = new AdmFileConverter($this);
				break;
			}
			case 'H': {
				$this->tool = new Settings($this);
				break;
			}
			case '#': {
				$this->close();
				break;
			}
		}
		if(!$this->abort && !\is_null($this->tool)){
			$response = $this->select_action($dynamic_action[1] ?? null);
			\gc_collect_cycles();
			return $response;
		}
		return false;
	}

}

?>