<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use AveCore\Request;
use AveCore\IniFile;

class AveSettings {

	private string $name = "Ave Settings";
	private array $params = [];
	private string $action;
	private AVE $ave;

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
	}

	public function help() : void {
		$this->ave->print_help([
			' Actions:',
			' 0 - Show documentation',
			' 1 - Open config folder',
			' 2 - Open logs folder',
			' 3 - Open data folder',
			' 4 - Open program folder',
			' 5 - Check for updates',
			' 6 - Restore default settings',
			' 7 - Install .ave-php script support (Windows)',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_show_documentation();
			case '1': return $this->tool_open_config_folder();
			case '2': return $this->tool_open_logs_folder();
			case '3': return $this->tool_open_data_folder();
			case '4': return $this->tool_open_program_folder();
			case '5': return $this->tool_check_for_updates();
			case '6': return $this->tool_restore_default_settings();
			case '7': return $this->tool_install_ave_php_script();
		}
		return false;
	}

	public function tool_show_documentation() : bool {
		$this->ave->clear();
		$this->ave->open_url("https://github.com/AbyssMorgan/AVE-PHP/wiki");
		return false;
	}

	public function tool_open_config_folder() : bool {
		$this->ave->clear();
		$this->ave->open_file($this->ave->app_data, "");
		return false;
	}

	public function tool_open_logs_folder() : bool {
		$this->ave->clear();
		$this->ave->open_file($this->ave->get_file_path($this->ave->config->get('AVE_LOG_FOLDER')), "");
		return false;
	}

	public function tool_open_data_folder() : bool {
		$this->ave->clear();
		$this->ave->open_file($this->ave->get_file_path($this->ave->config->get('AVE_DATA_FOLDER')), "");
		return false;
	}

	public function tool_open_program_folder() : bool {
		$this->ave->clear();
		$this->ave->open_file($this->ave->get_file_path($this->ave->path."/.."), "");
		return false;
	}

	public function tool_check_for_updates(bool $response = true) : bool {
		$this->ave->clear();
		$this->ave->echo(" Check for updates ...");
		$version = '';
		if($this->check_for_updates($version)){
			$this->ave->echo(" Update available v$version current v".$this->ave->version);
			if($this->ave->get_confirm(" Open download website now (Y/N): ")){
				$this->ave->open_url("https://github.com/AbyssMorgan/AVE-PHP/releases/tag/v$version");
			}
		} else if($response){
			$this->ave->echo(" No updates available");
			$this->ave->pause();
		}
		return false;
	}

	public function tool_restore_default_settings() : bool {
		$this->ave->clear();
		if($this->ave->get_confirm(" Restore default settings (Y/N): ")){
			$config_default = new IniFile($this->ave->get_file_path($this->ave->path."/includes/config/default.ini"), true);
			if($this->ave->windows){
				$config_default_system = new IniFile($this->ave->get_file_path($this->ave->path."/includes/config/windows.ini"), true);
			} else {
				$config_default_system = new IniFile($this->ave->get_file_path($this->ave->path."/includes/config/linux.ini"), true);
			}
			$config_default->update($config_default_system->get_all());
			$this->ave->config->update($config_default->get_all(), true);
			$this->ave->echo(" Settings have been reset");
		} else {
			$this->ave->echo(" Settings reset has been cancelled");
		}
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function check_for_updates(string &$version) : bool {
		$request = new Request(false);
		$response = $request->get("https://raw.githubusercontent.com/AbyssMorgan/AVE-PHP/master/version");
		if($response['code'] == 200){
			$ver_current = explode(".", $this->ave->version);
			$ver_repo = explode(".", $response['data']);
			$ver_current = intval($ver_current[0])*10000 + intval($ver_current[1])*100 + intval($ver_current[2]);
			$ver_repo = intval($ver_repo[0])*10000 + intval($ver_repo[1])*100 + intval($ver_repo[2]);
			$version = strval($response['data']);
			return ($ver_repo > $ver_current);
		}
		$this->ave->echo(" Failed check for updates: ".$response['code']);
		return false;
	}

	public function tool_install_ave_php_script() : bool {
		$this->ave->clear();
		$program_path = realpath($this->ave->get_file_path($this->ave->path));
		if(!$this->ave->windows){
			$this->ave->echo(" This feature is available only on windows operating system.");
			$this->ave->echo(" Use command: /usr/bin/php8.3 \"$program_path\includes\main.php\" --script <path> [...]");
			$this->ave->pause(" Press any key to back to menu");
		} else if(!$this->ave->is_admin()){
			$this->ave->echo(" You must run ".$this->ave->app_name." as administrator to use this feature");
			$this->ave->pause(" Press any key to back to menu");
		} else {
			if($this->ave->get_confirm(" Install .ave-php scripts support (Y/N): ")){
				$this->ave->echo(" ".exec('reg add HKEY_CLASSES_ROOT\.ave-php /ve /d "'.$this->ave->app_name.'" /f'));
				$this->ave->echo(" ".exec('reg add HKEY_CLASSES_ROOT\AVE-PHP /ve /d "'.$this->ave->app_name.' Executable" /f'));
				$this->ave->echo(" ".exec('reg add HKEY_CLASSES_ROOT\AVE-PHP\DefaultIcon /ve /d "\"'.$program_path.'\ave-php.ico\"" /f'));
				$this->ave->echo(" ".exec('reg add HKEY_CLASSES_ROOT\AVE-PHP\shell /f'));
				$this->ave->echo(" ".exec('reg add HKEY_CLASSES_ROOT\AVE-PHP\shell\open /f'));
				$this->ave->echo(" ".exec('reg add HKEY_CLASSES_ROOT\AVE-PHP\shell\open\command /ve /d "\"'.$program_path.'\commands\AVE-PHP-SCRIPT.cmd\" \"%1\" %*" /f'));
				$this->ave->pause(" Operation done, press any key to back to menu");
			} else {
				$this->ave->pause(" Operation aborted, press any key to back to menu");
			}
		}
		return false;
	}

}

?>
