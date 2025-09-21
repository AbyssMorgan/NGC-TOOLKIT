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

namespace NGC\Core;

use PDO;

/**
 * Handles database migrations, ensuring necessary tables exist and managing table versions and configuration values.
 */
class Migration extends MySQL {
	
	/**
	 * The name of the table used to store database table versions.
	 * @var string
	 */
	protected string $table_version;

	/**
	 * The name of the table used to store configuration key-value pairs.
	 * @var string
	 */
	protected string $table_config;

	/**
	 * An internal cache to store the existence status of tables.
	 * @var array
	 */
	protected array $tables = [];

	/**
	 * Migration constructor.
	 *
	 * @param string $table_version The name of the table for storing versions. Defaults to 'ngc_version'.
	 * @param string $table_config The name of the table for storing configuration. Defaults to 'ngc_config'.
	 */
	public function __construct(string $table_version = 'ngc_version', string $table_config = 'ngc_config'){
		$this->table_version = $table_version;
		$this->table_config = $table_config;
		parent::__construct();
	}

	/**
	 * Checks if a given table exists in the database.
	 *
	 * @param string $table The name of the table to check.
	 * @return bool True if the table exists, false otherwise.
	 */
	public function table_exists(string $table) : bool {
		if(isset($this->tables[$table])) return $this->tables[$table];
		$result = $this->query("SHOW TABLES LIKE '$table'");
		$this->tables[$table] = ($result && $result->rowCount() == 1);
		return $this->tables[$table];
	}

	/**
	 * Performs database migrations.
	 *
	 * This method ensures that the `table_version` and `table_config` tables exist.
	 * If they don't, it creates them.
	 */
	public function migrate() : void {
		if(!$this->table_exists($this->table_version)){
			$this->query("
				CREATE TABLE `$this->table_version` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`table_name` varchar(32) NOT NULL,
					`version` int(11) NOT NULL DEFAULT 0,
					PRIMARY KEY (`id`)
				) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
			");
			$this->tables[$this->table_version] = true;
		}

		$version = $this->get_version($this->table_config, false);
		if($version < 1){
			$this->query("
				CREATE TABLE `$this->table_config` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`name` varchar(128) DEFAULT NULL,
					`value` text DEFAULT NULL,
					PRIMARY KEY (`id`)
				) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
			");
			$this->set_version($this->table_config, 1);
			$this->tables[$this->table_config] = true;
		}
	}

	/**
	 * Retrieves the current version of a given database table.
	 *
	 * Optionally performs a migration check before retrieving the version.
	 *
	 * @param string $table The name of the table to get the version for.
	 * @param bool $migrate If true, `migrate()` will be called before checking the version. Defaults to true.
	 * @return int The version number of the table, or 0 if not found.
	 */
	public function get_version(string $table, bool $migrate = true) : int {
		if($migrate) $this->migrate();
		if(!$this->table_exists($table)) return 0;
		$result = $this->query("SELECT `version` FROM `$this->table_version` WHERE `table_name` = '$table'", PDO::FETCH_OBJ);
		if($result && $result->rowCount() == 1){
			$row = $result->fetch();
			return intval($row->version);
		}
		return 0;
	}

	/**
	 * Sets or updates the version of a given database table.
	 *
	 * @param string $table The name of the table whose version is to be set.
	 * @param int $version The new version number.
	 */
	public function set_version(string $table, int $version) : void {
		if($version == 1){
			$this->query("INSERT INTO `$this->table_version` SET `table_name` = '$table', `version` = '$version'");
		} else {
			$this->query("UPDATE `$this->table_version` SET `version` = '$version' WHERE `table_name` = '$table'");
		}
	}

	/**
	 * Retrieves a configuration value by its name.
	 *
	 * @param string $name The name of the configuration setting.
	 * @param string|null $default The default value to return if the configuration name is not found. Defaults to null.
	 * @return string|null The retrieved configuration value, or the default value if not found.
	 */
	public function get_value(string $name, ?string $default = null) : ?string {
		$result = $this->query("SELECT `value` FROM `$this->table_config` WHERE `name` = '$name'", PDO::FETCH_OBJ);
		if($result && $result->rowCount() == 1){
			$row = $result->fetch();
			return $row->value;
		}
		return $default;
	}

	/**
	 * Sets or updates a configuration value.
	 *
	 * If the configuration name does not exist, it will be inserted. Otherwise, it will be updated.
	 *
	 * @param string $name The name of the configuration setting.
	 * @param string $value The value to set for the configuration setting.
	 */
	public function set_value(string $name, string $value) : void {
		$value = $this->escape($value);
		if(is_null($this->get_value($name))){
			$this->query("INSERT INTO `$this->table_config` SET `name` = '$name', `value` = '$value'");
		} else {
			$this->query("UPDATE `$this->table_config` SET `value` = '$value' WHERE `name` = '$name'");
		}
	}

}

?>