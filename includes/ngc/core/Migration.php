<?php

/* NGC-TOOLKIT v2.3.0 */

declare(strict_types=1);

namespace NGC\Core;

use PDO;

class Migration extends MySQL {

	protected string $table_version = 'ngc_version';
	protected string $table_config = 'ngc_config';
	protected array $tables = [];

	public function __construct(){
		parent::__construct();
	}

	public function table_exists(string $table) : bool {
		if(isset($this->tables[$table])) return $this->tables[$table];
		$result = $this->query("SHOW TABLES LIKE '$table'");
		$this->tables[$table] = ($result && $result->rowCount() == 1);
		return $this->tables[$table];
	}

	public function migrate() : void {
		if(!$this->table_exists($this->table_version)){
			$this->query("
				CREATE TABLE `$this->table_version` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`table_name` varchar(32) NOT NULL,
					`version` int(11) NOT NULL DEFAULT 0,
					PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
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
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
			$this->set_version($this->table_config, 1);
			$this->tables[$this->table_config] = true;
		}
	}

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

	public function set_version(string $table, int $version) : void {
		if($version == 1){
			$this->query("INSERT INTO `$this->table_version` SET `table_name` = '$table', `version` = '$version'");
		} else {
			$this->query("UPDATE `$this->table_version` SET `version` = '$version' WHERE `table_name` = '$table'");
		}
	}

	public function get_value(string $name, ?string $default = null) : ?string {
		$result = $this->query("SELECT `value` FROM `$this->table_config` WHERE `name` = '$name'", PDO::FETCH_OBJ);
		if($result && $result->rowCount() == 1){
			$row = $result->fetch();
			return $row->value;
		}
		return $default;
	}

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
