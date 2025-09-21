<?php

/**
 * NGC-TOOLKIT v2.7.3 – Component
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

/**
 * Provides methods for managing NGC-TOOLKIT application storage.
 */
class AppStorage {

	/**
	 * The core toolkit or script instance.
	 * @var Toolkit|Script
	 */
	private Toolkit|Script $core;

	/**
	 * Constructs a new AppStorage instance.
	 *
	 * @param Toolkit|Script $core The core Toolkit or Script instance providing application context.
	 */
	public function __construct(Toolkit|Script $core){
		$this->core = $core;
	}

	/**
	 * Checks if a MySQL configuration file exists for the given label.
	 *
	 * @param string|null $label The label of the MySQL configuration.
	 * @return bool True if the MySQL configuration file exists, false otherwise.
	 */
	public function has_mysql(?string $label) : bool {
		if(is_null($label)) return false;
		return file_exists($this->core->get_path("{$this->core->app_data}/MySQL/$label.ini"));
	}

	/**
	 * Retrieves an IniFile instance for the specified MySQL configuration.
	 *
	 * @param string $label The label of the MySQL configuration.
	 * @return IniFile An IniFile instance representing the MySQL configuration file.
	 */
	public function mysql(string $label) : IniFile {
		return new IniFile($this->core->get_path("{$this->core->app_data}/MySQL/$label.ini"), true);
	}

	/**
	 * Checks if an FTP configuration file exists for the given label.
	 *
	 * @param string|null $label The label of the FTP configuration.
	 * @return bool True if the FTP configuration file exists, false otherwise.
	 */
	public function has_ftp(?string $label) : bool {
		if(is_null($label)) return false;
		return file_exists($this->core->get_path("{$this->core->app_data}/FTP/$label.ini"));
	}

	/**
	 * Retrieves an IniFile instance for the specified FTP configuration.
	 *
	 * @param string $label The label of the FTP configuration.
	 * @return IniFile An IniFile instance representing the FTP configuration file.
	 */
	public function ftp(string $label) : IniFile {
		return new IniFile($this->core->get_path("{$this->core->app_data}/FTP/$label.ini"), true);
	}

}

?>