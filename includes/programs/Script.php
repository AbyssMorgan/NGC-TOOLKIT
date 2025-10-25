<?php

/**
 * NGC-TOOLKIT v2.7.4 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

use NGC\Core\Core;
use NGC\Core\IniFile;
use NGC\Extensions\Console;
use NGC\Extensions\AppStorage;
use NGC\Extensions\MediaFunctions;

class Script extends Core {

	public string $app_data;
	public bool $abort = false;
	public string $app_name = "NGC-TOOLKIT";
	public string $version = "2.7.4";
	public AppStorage $storage;
	public MediaFunctions $media;
	public string $script;

	public function __construct(array $arguments){
		parent::__construct($arguments);
		$this->require_utilities();
		$this->set_resources_folder($this->get_path("$this->path/includes/data"));
		if($this->abort) return;
		$config_default = new IniFile($this->get_path("$this->path/includes/config/default.ini"), true);
		switch($this->get_system_type()){
			case SYSTEM_TYPE_WINDOWS: {
				$config_default_system = new IniFile($this->get_path("{$this->path}/includes/config/windows.ini"), true);
				$this->app_data = $this->get_path($this->get_variable("%LOCALAPPDATA%")."/NGC-TOOLKIT");
				break;
			}
			case SYSTEM_TYPE_LINUX: {
				$config_default_system = new IniFile($this->get_path("{$this->path}/includes/config/linux.ini"), true);
				$this->app_data = $this->get_path($this->get_variable("\$HOME")."/.config/NGC-TOOLKIT");
				break;
			}
			case SYSTEM_TYPE_MACOS: {
				$config_default_system = new IniFile($this->get_path("{$this->path}/includes/config/macos.ini"), true);
				$this->app_data = $this->get_path($this->get_variable("\$HOME")."/Library/Application Support/NGC-TOOLKIT");
				break;
			}
		}
		if($this->get_system_type() == SYSTEM_TYPE_WINDOWS){
			dl('php_imagick.dll');
		} else {
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

		$this->logo = '';
		$changed = false;

		$path_config_toolkit = $this->get_path("$this->app_data/config.ini");

		if(!file_exists($this->app_data)) mkdir($this->app_data);

		$path_config_mysql = $this->get_path("$this->app_data/MySQL");
		if(!file_exists($path_config_mysql)) mkdir($path_config_mysql);

		$path_config_ftp = $this->get_path("$this->app_data/FTP");
		if(!file_exists($path_config_ftp)) mkdir($path_config_ftp);

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

		ini_set('memory_limit', -1);
	}

	public function execute() : void {
		array_unshift($this->arguments, $this->command);
		$path = $this->command;
		if(empty($path) || !file_exists($path)){
			$this->echo(" File \"$path\" not exists");
		} else {
			$console = new Console($this);
			$console->execute($path);
			$this->close(false);
		}
	}

}

?>