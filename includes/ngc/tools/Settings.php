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

namespace NGC\Tools;

use Toolkit;
use NGC\Core\Request;
use NGC\Core\IniFile;

class Settings {

	private string $name = "Settings";
	private string $action;
	private Toolkit $core;

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
	}

	public function help() : void {
		$this->core->print_help([
			' Actions:',
			' 0 - Show documentation',
			' 1 - Open config folder',
			' 2 - Open logs folder',
			' 3 - Open data folder',
			' 4 - Open program folder',
			' 5 - Check for updates',
			' 6 - Restore default settings',
		]);
	}

	public function action(string $action) : bool {
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_show_documentation();
			case '1': return $this->tool_open_config_folder();
			case '2': return $this->tool_open_logs_folder();
			case '3': return $this->tool_open_data_folder();
			case '4': return $this->tool_open_program_folder();
			case '5': return $this->tool_check_for_updates();
			case '6': return $this->tool_restore_default_settings();
			case 'dev': return $this->tool_install_toolkit_script();
		}
		return false;
	}

	public function tool_show_documentation() : bool {
		$this->core->clear();
		$this->core->open_url("https://github.com/AbyssMorgan/NGC-TOOLKIT/wiki");
		return false;
	}

	public function tool_open_config_folder() : bool {
		$this->core->clear();
		$this->core->open_file($this->core->app_data, "");
		return false;
	}

	public function tool_open_logs_folder() : bool {
		$this->core->clear();
		$this->core->open_file($this->core->get_path($this->core->config->get('LOG_FOLDER')), "");
		return false;
	}

	public function tool_open_data_folder() : bool {
		$this->core->clear();
		$this->core->open_file($this->core->get_path($this->core->config->get('DATA_FOLDER')), "");
		return false;
	}

	public function tool_open_program_folder() : bool {
		$this->core->clear();
		$this->core->open_file($this->core->get_path("{$this->core->path}/.."), "");
		return false;
	}

	public function tool_check_for_updates(bool $response = true) : bool {
		$this->core->clear();
		$this->core->echo(" Check for updates ...");
		$version = '';
		if($this->check_for_updates($version)){
			$this->core->echo(" Update available v$version current v{$this->core->version}");
			if($this->core->get_confirm(" Open download website now (Y/N): ")){
				$this->core->open_url("https://github.com/AbyssMorgan/NGC-TOOLKIT/releases/tag/v$version");
			}
		} elseif($response){
			$this->core->echo(" No updates available");
			$this->core->pause();
		}
		return false;
	}

	public function tool_restore_default_settings() : bool {
		$this->core->clear();
		if($this->core->get_confirm(" Restore default settings (Y/N): ")){
			$config_default = new IniFile($this->core->get_path("{$this->core->path}/includes/config/default.ini"), true);
			switch($this->core->get_system_type()){
				case SYSTEM_TYPE_WINDOWS: {
					$config_default_system = new IniFile($this->core->get_path("{$this->core->path}/includes/config/windows.ini"), true);
					break;
				}
				case SYSTEM_TYPE_LINUX: {
					$config_default_system = new IniFile($this->core->get_path("{$this->core->path}/includes/config/linux.ini"), true);
					break;
				}
				case SYSTEM_TYPE_MACOS: {
					$config_default_system = new IniFile($this->core->get_path("{$this->core->path}/includes/config/macos.ini"), true);
					break;
				}
			}
			$config_default->update($config_default_system->get_all());
			$this->core->config->update($config_default->get_all(), true);
			$this->core->echo(" Settings have been reset");
		} else {
			$this->core->echo(" Settings reset has been cancelled");
		}
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function check_for_updates(string &$version) : bool {
		$request = new Request(false);
		$request->set_http_version(CURL_HTTP_VERSION_2);
		$response = $request->get("https://raw.githubusercontent.com/AbyssMorgan/NGC-TOOLKIT/master/version");
		if($response['code'] == 200){
			$ver_current = explode(".", $this->core->version);
			$ver_repo = explode(".", $response['data']);
			$ver_current = \intval($ver_current[0]) * 10000 + \intval($ver_current[1]) * 100 + \intval($ver_current[2]);
			$ver_repo = \intval($ver_repo[0]) * 10000 + \intval($ver_repo[1]) * 100 + \intval($ver_repo[2]);
			$version = \strval($response['data']);
			return ($ver_repo > $ver_current);
		}
		$this->core->echo(" Failed check for updates: {$response['code']}");
		return false;
	}

	public function tool_install_toolkit_script() : bool {
		if($this->core->get_system_type() != SYSTEM_TYPE_WINDOWS) return $this->core->windows_only();
		$this->core->clear();
		$program_path = realpath($this->core->path);
		if(!$this->core->is_admin()){
			$this->core->echo(" You must run {$this->core->app_name} as administrator to use this feature");
			$this->core->pause(" Press any key to back to menu");
		} else {
			if($this->core->get_confirm(" Install .ngcs scripts support (Y/N): ")){
				$this->core->echo(" ".exec('reg add HKEY_CLASSES_ROOT\.ngcs /ve /d "NGC.SCRIPT" /f'));
				$this->core->echo(" ".exec('reg add HKEY_CLASSES_ROOT\NGC.SCRIPT /ve /d "'.$this->core->app_name.' Script" /f'));
				$this->core->echo(" ".exec('reg add HKEY_CLASSES_ROOT\NGC.SCRIPT\DefaultIcon /ve /d "\"'.$program_path.'\NGC-TOOLKIT.ico\"" /f'));
				$this->core->echo(" ".exec('reg add HKEY_CLASSES_ROOT\NGC.SCRIPT\shell /f'));
				$this->core->echo(" ".exec('reg add HKEY_CLASSES_ROOT\NGC.SCRIPT\shell\open /f'));
				$this->core->echo(" ".exec('reg add HKEY_CLASSES_ROOT\NGC.SCRIPT\shell\open\command /ve /d "\"'.$program_path.'\bin\Script.cmd\" \"%1\" %*" /f'));
				$this->core->pause(" Operation done, press any key to back to menu");
			} else {
				$this->core->pause(" Operation aborted, press any key to back to menu");
			}
		}
		return false;
	}

}

?>