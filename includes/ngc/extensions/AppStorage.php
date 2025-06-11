<?php

/**
 * NGC-TOOLKIT v2.6.1 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Extensions;

use Script;
use Toolkit;
use NGC\Core\IniFile;

class AppStorage {

	private Toolkit|Script $core;

	public function __construct(Toolkit|Script $core){
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