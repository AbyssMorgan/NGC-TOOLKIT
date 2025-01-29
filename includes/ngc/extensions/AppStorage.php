<?php

/* NGC-TOOLKIT v2.4.0 */

declare(strict_types=1);

namespace NGC\Extensions;

use NGC\Core\IniFile;

class AppStorage {

	private object $core;

	public function __construct(object $core){
		$this->core = $core;
	}

	public function has_mysql(?string $label) : bool {
		if(is_null($label)) return false;
		return file_exists($this->core->get_path("{$this->core->app_data}/MySQL/$label.ini"));
	}

	public function mysql(string $label) : IniFile {
		return new IniFile($this->core->get_path("{$this->core->app_data}/MySQL/$label.ini"), true);
	}

	public function has_ftp(?string $label) : bool {
		if(is_null($label)) return false;
		return file_exists($this->core->get_path("{$this->core->app_data}/FTP/$label.ini"));
	}

	public function ftp(string $label) : IniFile {
		return new IniFile($this->core->get_path("{$this->core->app_data}/FTP/$label.ini"), true);
	}

}

?>