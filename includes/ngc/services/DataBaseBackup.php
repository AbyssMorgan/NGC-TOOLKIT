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

namespace NGC\Services;

use PDO;
use PDOException;

/**
 * Class DataBaseBackup
 *
 * This class provides functionalities for backing up and cloning MySQL databases,
 * including tables, views, functions, procedures, events, and triggers.
 */
class DataBaseBackup {

	/**
	 * The source PDO connection object.
	 * @var PDO|null
	 */
	protected ?PDO $source;

	/**
	 * The destination PDO connection object.
	 * @var PDO|null
	 */
	protected ?PDO $destination;

	/**
	 * The name of the database being backed up or cloned.
	 * @var string
	 */
	protected string $database;

	/**
	 * The base path for storing backup files.
	 * @var string|null
	 */
	private ?string $path;

	/**
	 * Stores ALTER TABLE statements for cloning.
	 * @var string
	 */
	private string $alters;

	/**
	 * Flag to indicate whether tables should be locked during data backup/cloning.
	 * @var bool
	 */
	private bool $lock_tables = false;

	/**
	 * The limit for the number of rows fetched in a single query during data operations.
	 * @var int
	 */
	private int $query_limit;

	/**
	 * The limit for the number of rows per INSERT statement.
	 * @var int
	 */
	private int $insert_limit;

	/**
	 * The header content for SQL backup files.
	 * @var string
	 */
	private string $header;

	/**
	 * The footer content for SQL backup files.
	 * @var string
	 */
	private string $footer;

	/**
	 * The date string used in backup file names.
	 * @var string
	 */
	private string $date;

	/**
	 * The permissions for created directories.
	 * @var int
	 */
	private int $permissions;

	/**
	 * Data types that do not require quotes in SQL INSERT statements.
	 * @var array
	 */
	private array $types_no_quotes = [
		'bigint',
		'double',
		'float',
		'int',
		'json',
		'tinyint',
		'mediumint',
		'smallint',
		'year',
	];

	/**
	 * DataBaseBackup constructor.
	 *
	 * @param string|null $path The base path to store backup files. If null, no file backups will be made.
	 * @param int $query_limit The number of rows to fetch per query for data operations.
	 * @param int $insert_limit The number of rows per INSERT statement for data operations.
	 * @param string $date_format The format for the date string used in directory names.
	 * @param int $permissions The file permissions for created directories.
	 */
	public function __construct(?string $path = null, int $query_limit = 50000, int $insert_limit = 100, string $date_format = "Y-m-d_His", int $permissions = 0755){
		$this->date = date($date_format);
		$this->query_limit = $query_limit;
		$this->insert_limit = $insert_limit;
		$this->permissions = $permissions;
		if(!is_null($path)) $this->path = $path;
		$this->header = base64_decode("U0VUIFNRTF9NT0RFID0gIk5PX0FVVE9fVkFMVUVfT05fWkVSTyI7ClNUQVJUIFRSQU5TQUNUSU9OOwpTRVQgdGltZV96b25lID0gIiswMDowMCI7CgovKiE0MDEwMSBTRVQgQE9MRF9DSEFSQUNURVJfU0VUX0NMSUVOVD1AQENIQVJBQ1RFUl9TRVRfQ0xJRU5UICovOwovKiE0MDEwMSBTRVQgQE9MRF9DSEFSQUNURVJfU0VUX1JFU1VMVFM9QEBDSEFSQUNURVJfU0VUX1JFU1VMVFMgKi87Ci8qITQwMTAxIFNFVCBAT0xEX0NPTExBVElPTl9DT05ORUNUSU9OPUBAQ09MTEFUSU9OX0NPTk5FQ1RJT04gKi87Ci8qITQwMTAxIFNFVCBOQU1FUyB1dGY4bWI0ICovOw==");
		$this->footer = base64_decode("Q09NTUlUOwoKLyohNDAxMDEgU0VUIENIQVJBQ1RFUl9TRVRfQ0xJRU5UPUBPTERfQ0hBUkFDVEVSX1NFVF9DTElFTlQgKi87Ci8qITQwMTAxIFNFVCBDSEFSQUNURVJfU0VUX1JFU1VMVFM9QE9MRF9DSEFSQUNURVJfU0VUX1JFU1VMVFMgKi87Ci8qITQwMTAxIFNFVCBDT0xMQVRJT05fQ09OTkVDVElPTj1AT0xEX0NPTExBVElPTl9DT05ORUNUSU9OICovOw==");
		$this->alters = '';
	}

	/**
	 * Get the accumulated ALTER TABLE statements.
	 *
	 * @return string The string containing all ALTER TABLE statements.
	 */
	public function get_alters() : string {
		return $this->alters;
	}

	/**
	 * Reset the accumulated ALTER TABLE statements.
	 *
	 * @return void
	 */
	public function reset_alters() : void {
		$this->alters = '';
	}

	/**
	 * Get the output path for backup files.
	 *
	 * @param string|null $folder An optional subfolder within the main backup path (e.g., "structure", "data").
	 * @return string The full output path.
	 */
	public function get_output(?string $folder = null) : string {
		$path = $this->path.DIRECTORY_SEPARATOR."{$this->database}_{$this->date}";
		if(!is_null($folder)) $path .= DIRECTORY_SEPARATOR.$folder;
		return $path;
	}

	/**
	 * Toggle table locking during data operations.
	 *
	 * @param bool $toggle True to enable table locking, false to disable.
	 * @return void
	 */
	public function toggle_lock_tables(bool $toggle) : void {
		$this->lock_tables = $toggle;
	}

	/**
	 * Establishes a PDO connection to the source database.
	 *
	 * @param string $host The database host.
	 * @param string $user The database username.
	 * @param string $password The database password.
	 * @param string $dbname The database name. Use "*" to connect without specifying a database (e.g., for showing databases).
	 * @param int $port The database port.
	 * @return bool True on successful connection, false otherwise.
	 */
	public function connect(string $host, string $user, string $password, string $dbname, int $port = 3306) : bool {
		$options = [
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION SQL_BIG_SELECTS=1;SET character_set_results = binary;',
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];
		try {
			$this->source = new PDO("mysql:".($dbname == "*" ? "" : "dbname=$dbname;")."host=$host;port=$port;charset=UTF8", $user, $password, $options);
		}
		catch(PDOException $e){
			echo " Failed to connect:\r\n";
			echo " ".$e->getMessage()."\r\n";
			return false;
		}
		$this->database = $dbname;
		return true;
	}

	/**
	 * Establishes a PDO connection to the destination database for cloning operations.
	 *
	 * @param string $host The database host.
	 * @param string $user The database username.
	 * @param string $password The database password.
	 * @param string $dbname The database name. Use "*" to connect without specifying a database.
	 * @param int $port The database port.
	 * @return bool True on successful connection, false otherwise.
	 */
	public function connect_destination(string $host, string $user, string $password, string $dbname, int $port = 3306) : bool {
		$options = [
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION SQL_BIG_SELECTS=1;SET character_set_results = binary;',
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];
		try {
			$this->destination = new PDO("mysql:".($dbname == "*" ? "" : "dbname=$dbname;")."host=$host;port=$port;charset=UTF8", $user, $password, $options);
		}
		catch(PDOException $e){
			echo " Failed to connect:\r\n";
			echo " ".$e->getMessage()."\r\n";
			return false;
		}
		return true;
	}

	/**
	 * Get the source PDO connection object.
	 *
	 * @return PDO|null The source PDO object, or null if not connected.
	 */
	public function get_source() : ?PDO {
		return $this->source;
	}

	/**
	 * Get the destination PDO connection object.
	 *
	 * @return PDO|null The destination PDO object, or null if not connected.
	 */
	public function get_destination() : ?PDO {
		return $this->destination;
	}

	/**
	 * Disconnects from the source database.
	 *
	 * @return void
	 */
	public function disconnect() : void {
		$this->source = null;
	}

	/**
	 * Disconnects from the destination database.
	 *
	 * @return void
	 */
	public function disconnect_destination() : void {
		$this->destination = null;
	}

	/**
	 * Sets the name of the database to be operated on.
	 *
	 * @param string $dbname The database name.
	 * @return void
	 */
	public function set_data_base(string $dbname) : void {
		$this->database = $dbname;
	}

	/**
	 * Escapes special characters in a string for use in SQL statements.
	 *
	 * @param mixed $string The string to escape.
	 * @return string The escaped string.
	 */
	public function escape(mixed $string) : string {
		$string = strval($string) ?? '';
		if(empty($string)) return '';
		return str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"], ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $string);
	}

	/**
	 * Checks if the destination database is empty (contains no tables).
	 *
	 * @return bool True if the destination database is empty, false otherwise.
	 */
	public function is_destination_empty() : bool {
		$items = $this->destination->query('SHOW TABLES', PDO::FETCH_OBJ);
		return $items->rowCount() == 0;
	}

	/**
	 * Retrieves a list of all base tables in the current database.
	 *
	 * @return array An array of table names.
	 */
	public function get_tables() : array {
		$data = [];
		$items = $this->source->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'BASE TABLE'", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->{'Tables_in_'.$this->database});
		}
		return $data;
	}

	/**
	 * Retrieves a list of all views in the current database.
	 *
	 * @return array An array of view names.
	 */
	public function get_views() : array {
		$data = [];
		$items = $this->source->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW'", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->{'Tables_in_'.$this->database});
		}
		return $data;
	}

	/**
	 * Retrieves a list of all functions in the current database.
	 *
	 * @return array An array of function names.
	 */
	public function get_functions() : array {
		$data = [];
		$items = $this->source->query("SHOW FUNCTION STATUS WHERE `Db` = '$this->database' AND `Type` = 'FUNCTION'", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->Name);
		}
		return $data;
	}

	/**
	 * Retrieves a list of all procedures in the current database.
	 *
	 * @return array An array of procedure names.
	 */
	public function get_procedures() : array {
		$data = [];
		$items = $this->source->query("SHOW PROCEDURE STATUS WHERE `Db` = '$this->database' AND `Type` = 'PROCEDURE'", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->Name);
		}
		return $data;
	}

	/**
	 * Retrieves a list of all events in the current database.
	 *
	 * @return array An array of event names.
	 */
	public function get_events() : array {
		$data = [];
		$items = $this->source->query("SHOW EVENTS", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->Name);
		}
		return $data;
	}

	/**
	 * Retrieves a list of all triggers in the current database.
	 *
	 * @return array An array of trigger names.
	 */
	public function get_triggers() : array {
		$data = [];
		$items = $this->source->query("SHOW TRIGGERS", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->Trigger);
		}
		return $data;
	}

	/**
	 * Retrieves the column names and their data types for a given table.
	 *
	 * @param string $name The name of the table.
	 * @return array An associative array where keys are column names and values are their data types (lowercase).
	 */
	public function get_columns(string $name) : array {
		$data = [];
		$columns = $this->source->query("SELECT `COLUMN_NAME`, `DATA_TYPE` FROM INFORMATION_SCHEMA.COLUMNS WHERE `TABLE_SCHEMA` = '$this->database' AND `TABLE_NAME` = '$name'", PDO::FETCH_OBJ);
		foreach($columns as $column){
			$data[$column->COLUMN_NAME] = mb_strtolower($column->DATA_TYPE);
		}
		return $data;
	}

	/**
	 * Retrieves the CREATE TABLE statement and associated ALTER TABLE statements for a given table.
	 * This method separates foreign key constraints into ALTER TABLE statements.
	 *
	 * @param string $name The name of the table.
	 * @return array An associative array with 'query' (CREATE TABLE statement) and 'alters' (ALTER TABLE statements).
	 */
	public function get_table_creation(string $name) : array {
		$creation = $this->source->query("SHOW CREATE TABLE `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		$query = str_replace(["\r\n", "\r"], "\n", $data->{'Create Table'}.';');
		$items = explode("\n", $query);
		$alters = [];
		$removals = [];
		foreach($items as $key => &$item){
			$item = trim($item);
			if(str_contains($item, 'CONSTRAINT ')){
				$item = rtrim($item, ",");
				array_push($alters, "ALTER TABLE `$name` ADD $item;");
				array_push($removals, ", $item");
			} elseif(empty($item)){
				unset($items[$key]);
			}
		}
		return [
			'query' => str_replace($removals, "", implode(" ", $items)),
			'alters' => implode("\n", $alters),
		];
	}

	/**
	 * Retrieves the CREATE VIEW statement for a given view.
	 *
	 * @param string $name The name of the view.
	 * @return string The CREATE VIEW statement.
	 */
	public function get_view_creation(string $name) : string {
		$creation = $this->source->query("SHOW CREATE VIEW `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		return $data->{'Create View'}.';';
	}

	/**
	 * Retrieves the CREATE FUNCTION statement for a given function.
	 *
	 * @param string $name The name of the function.
	 * @return string The CREATE FUNCTION statement, enclosed in DELIMITER commands.
	 */
	public function get_function_creation(string $name) : string {
		$creation = $this->source->query("SHOW CREATE FUNCTION `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		return "DELIMITER $$\n".$data->{'Create Function'}."$$\nDELIMITER ;\n";
	}

	/**
	 * Retrieves the CREATE PROCEDURE statement for a given procedure.
	 *
	 * @param string $name The name of the procedure.
	 * @return string The CREATE PROCEDURE statement, enclosed in DELIMITER commands.
	 */
	public function get_procedure_creation(string $name) : string {
		$creation = $this->source->query("SHOW CREATE PROCEDURE `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		return "DELIMITER $$\n".$data->{'Create Procedure'}."$$\nDELIMITER ;\n";
	}

	/**
	 * Retrieves the CREATE EVENT statement for a given event.
	 *
	 * @param string $name The name of the event.
	 * @return string The CREATE EVENT statement, enclosed in DELIMITER commands.
	 */
	public function get_event_creation(string $name) : string {
		$creation = $this->source->query("SHOW CREATE EVENT `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		return "DELIMITER $$\n".$data->{'Create Event'}."$$\nDELIMITER ;\n";
	}

	/**
	 * Retrieves the CREATE TRIGGER statement for a given trigger.
	 *
	 * @param string $name The name of the trigger.
	 * @return string The CREATE TRIGGER statement, enclosed in DELIMITER commands.
	 */
	public function get_triger_creation(string $name) : string {
		$creation = $this->source->query("SHOW CREATE TRIGGER `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		return "DELIMITER $$\n".$data->{'SQL Original Statement'}."$$\nDELIMITER ;\n";
	}

	/**
	 * Generates a DROP TABLE IF EXISTS statement for a given table.
	 *
	 * @param string $name The name of the table.
	 * @return string The DROP TABLE statement.
	 */
	public function get_table_drop(string $name) : string {
		return "DROP TABLE IF EXISTS `$name`;";
	}

	/**
	 * Generates a DROP VIEW IF EXISTS statement for a given view.
	 *
	 * @param string $name The name of the view.
	 * @return string The DROP VIEW statement.
	 */
	public function get_view_drop(string $name) : string {
		return "DROP VIEW IF EXISTS `$name`;";
	}

	/**
	 * Generates a DROP FUNCTION IF EXISTS statement for a given function.
	 *
	 * @param string $name The name of the function.
	 * @return string The DROP FUNCTION statement.
	 */
	public function get_function_drop(string $name) : string {
		return "DROP FUNCTION IF EXISTS `$name`;";
	}

	/**
	 * Generates a DROP PROCEDURE IF EXISTS statement for a given procedure.
	 *
	 * @param string $name The name of the procedure.
	 * @return string The DROP PROCEDURE statement.
	 */
	public function get_procedure_drop(string $name) : string {
		return "DROP PROCEDURE IF EXISTS `$name`;";
	}

	/**
	 * Generates a DROP EVENT IF EXISTS statement for a given event.
	 *
	 * @param string $name The name of the event.
	 * @return string The DROP EVENT statement.
	 */
	public function get_event_drop(string $name) : string {
		return "DROP EVENT IF EXISTS `$name`;";
	}

	/**
	 * Generates a DROP TRIGGER IF EXISTS statement for a given trigger.
	 *
	 * @param string $name The name of the trigger.
	 * @return string The DROP TRIGGER statement.
	 */
	public function get_trigger_drop(string $name) : string {
		return "DROP TRIGGER IF EXISTS `$name`;";
	}

	/**
	 * Get the SQL header content.
	 *
	 * @return string The header SQL.
	 */
	public function get_header() : string {
		return $this->header;
	}

	/**
	 * Get the SQL footer content.
	 *
	 * @return string The footer SQL.
	 */
	public function get_footer() : string {
		return $this->footer;
	}

	/**
	 * Generates the beginning of an INSERT INTO statement with specified columns.
	 *
	 * @param string $table The table name.
	 * @param array $columns An array of column names.
	 * @return string The INSERT INTO part of the SQL statement.
	 */
	public function get_insert(string $table, array $columns) : string {
		$columns_string = '';
		foreach($columns as $column){
			$columns_string .= "`$column`,";
		}
		$columns_string = substr($columns_string, 0, -1);
		return "INSERT INTO `$table` ($columns_string)";
	}

	/**
	 * Backs up the structure (CREATE TABLE statement) of a given table to a SQL file.
	 * Foreign key constraints are separated into a separate "alters" file.
	 *
	 * @param string $table The name of the table to backup.
	 * @return array An array of errors encountered during the backup process.
	 */
	public function backup_table_structure(string $table) : array {
		if(!file_exists($this->get_output("structure"))) mkdir($this->get_output("structure"), $this->permissions, true);
		$errors = [];
		$file_path = $this->get_output("structure").DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		try {
			$creation = $this->get_table_creation($table);
			fwrite($file, "-- やあ --\n\n");
			fwrite($file, $this->get_header()."\n\n");
			fwrite($file, $this->get_table_drop($table)."\n\n");
			fwrite($file, $creation['query']."\n\n");
			fwrite($file, "\n".$this->get_footer()."\n");
			echo " Table structure: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		}
		catch(PDOException $e){
			echo "\n Failed make backup for table structure $table, skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed make backup for table structure $table reason: ".$e->getMessage();
		}
		fclose($file);
		if(!empty($creation['alters'])){
			if(!file_exists($this->get_output("alters"))) mkdir($this->get_output("alters"), $this->permissions, true);
			$file_path = $this->get_output("alters").DIRECTORY_SEPARATOR."$table.sql";
			if(file_exists($file_path)) unlink($file_path);
			$file = fopen($file_path, "a");
			fwrite($file, $creation['alters']."\n\n");
			fclose($file);
		}
		return $errors;
	}

	/**
	 * Clones the structure (CREATE TABLE statement) of a given table to the destination database.
	 * Foreign key constraints are accumulated in the `alters` property for later execution.
	 *
	 * @param string $table The name of the table to clone.
	 * @return array An array of errors encountered during the cloning process.
	 */
	public function clone_table_structure(string $table) : array {
		$errors = [];
		try {
			$creation = $this->get_table_creation($table);
			$this->destination->query($this->get_header());
			$this->destination->query($this->get_table_drop($table));
			$this->destination->query($creation['query']);
			$this->alters .= $creation['alters']."\n";
			$this->destination->query($this->get_footer());
			echo " Table structure: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		}
		catch(PDOException $e){
			echo " Failed clone table $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone table structure $table reason: ".$e->getMessage();
		}
		return $errors;
	}

	/**
	 * Backs up the data of a given table to a SQL file as INSERT statements.
	 *
	 * @param string $table The name of the table to backup.
	 * @return array An array of errors encountered during the backup process.
	 */
	public function backup_table_data(string $table) : array {
		if(!file_exists($this->get_output("data"))) mkdir($this->get_output("data"), $this->permissions, true);
		$errors = [];
		$file_path = $this->get_output("data").DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		try {
			$offset = 0;
			$columns = $this->get_columns($table);
			fwrite($file, "-- やあ --\n\n");
			fwrite($file, $this->get_header()."\n\n");
			fwrite($file, "SET foreign_key_checks = 0;\n\n");
			echo " Table data: `$this->database`.`$table` Progress: 0.00 %        \r";
			$insert = $this->get_insert($table, array_keys($columns))." VALUES\n";
			if($this->lock_tables) $this->source->query("LOCK TABLE `$table` WRITE");
			$results = $this->source->query("SELECT count(*) AS cnt FROM `$table`");
			$row = $results->fetch(PDO::FETCH_OBJ);
			$count = $row->cnt;
			if($count > 0){
				do {
					$percent = sprintf("%.02f", ($offset / $count) * 100.0);
					echo " Table data: `$this->database`.`$table` Progress: $percent %        \r";
					$rows = $this->source->query("SELECT * FROM `$table` LIMIT $offset, $this->query_limit", PDO::FETCH_OBJ);
					$seek = 0;
					$query = '';
					foreach($rows as $row){
						if($seek == 0){
							$query .= $insert;
						}
						$values = [];
						foreach($columns as $column => $type){
							if($row->$column === '0'){
								if(in_array($type, $this->types_no_quotes)){
									$values[] = "0";
								} else {
									$values[] = "'0'";
								}
							} elseif(is_null($row->$column)){
								$values[] = "NULL";
							} elseif($type == 'bit'){
								if(empty($row->$column)){
									$values[] = "b'0'";
								} else {
									$values[] = "b'".decbin(intval($row->$column))."'";
								}
							} elseif($type == 'blob' || $type == 'binary' || $type == 'longblob'){
								if(empty($row->$column)){
									$values[] = "''";
								} else {
									$values[] = "0x".bin2hex($row->$column);
								}
							} else {
								if(in_array($type, $this->types_no_quotes)){
									$values[] = $row->$column;
								} else {
									$values[] = "'".$this->escape($row->$column)."'";
								}
							}
						}
						$query .= '('.implode(',', $values).')';
						unset($values);
						$seek++;
						if($seek >= $this->insert_limit){
							$seek = 0;
							$query .= ";\n";
						} else {
							$query .= ",\n";
						}
						$offset++;
					}
					if(!empty($query)) fwrite($file, substr($query, 0, -2).";\n");
					unset($query);
				} while($rows->rowCount() > 0);
				if(isset($rows)) unset($rows);
			}
			if($this->lock_tables) $this->source->query("UNLOCK TABLES");
			fwrite($file, "SET foreign_key_checks = 1;\n\n");
			fwrite($file, "\n".$this->get_footer()."\n");
			echo " Table data: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		}
		catch(PDOException $e){
			try {
				if($this->lock_tables) $this->source->query("UNLOCK TABLES");
			}
			catch(PDOException $ee){

			}
			echo "\n Failed make backup for table data $table, skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed make backup for table data $table reason: ".$e->getMessage();
		}
		fclose($file);
		return $errors;
	}

	/**
	 * Clones the data of a given table from the source to the destination database.
	 * Data is inserted in chunks to manage memory and performance.
	 *
	 * @param string $table The name of the table to clone data from.
	 * @return array An array of errors encountered during the cloning process.
	 */
	public function clone_table_data(string $table) : array {
		$errors = [];
		$offset = 0;
		try {
			$columns = $this->get_columns($table);
			$this->destination->query($this->get_header());
			$this->destination->query("SET foreign_key_checks = 0;");
			echo " Table: `$this->database`.`$table` Progress: 0.00 %        \r";
			$insert = $this->get_insert($table, array_keys($columns))." VALUES\n";
			if($this->lock_tables) $this->source->query("LOCK TABLE `$table` WRITE");
			$results = $this->source->query("SELECT count(*) AS cnt FROM `$table`");
			$row = $results->fetch(PDO::FETCH_OBJ);
			$count = $row->cnt;
			if($count > 0){
				do {
					$percent = sprintf("%.02f", ($offset / $count) * 100.0);
					echo " Table: `$this->database`.`$table` Progress: $percent %        \r";
					$rows = $this->source->query("SELECT * FROM `$table` LIMIT $offset, $this->query_limit", PDO::FETCH_OBJ);
					$seek = 0;
					foreach($rows as $row){
						if($seek == 0){
							$query = $insert;
						}
						$values = [];
						foreach($columns as $column => $type){
							if($row->$column === '0'){
								if(in_array($type, $this->types_no_quotes)){
									$values[] = "0";
								} else {
									$values[] = "'0'";
								}
							} elseif(is_null($row->$column)){
								$values[] = "NULL";
							} elseif($type == 'bit'){
								if(empty($row->$column)){
									$values[] = "b'0'";
								} else {
									$values[] = "b'".decbin(intval($row->$column))."'";
								}
							} elseif($type == 'blob' || $type == 'binary' || $type == 'longblob'){
								if(empty($row->$column)){
									$values[] = "''";
								} else {
									$values[] = "0x".bin2hex($row->$column);
								}
							} else {
								if(in_array($type, $this->types_no_quotes)){
									$values[] = $row->$column;
								} else {
									$values[] = "'".$this->escape($row->$column)."'";
								}
							}
						}
						$query .= '('.implode(',', $values).'),'."\n";
						unset($values);
						$seek++;
						if($seek >= $this->insert_limit){
							$seek = 0;
							$this->destination->query(substr($query, 0, -2).";");
							unset($query);
						}
						$offset++;
					}
				} while($rows->rowCount() > 0);
				if(isset($rows)) unset($rows);
				if(isset($query)){
					$this->destination->query(substr($query, 0, -2).";");
					unset($query);
				}
			}
			if($this->lock_tables) $this->source->query("UNLOCK TABLES");
			$this->destination->query("SET foreign_key_checks = 1;");
			$this->destination->query($this->get_footer());
			echo " Table: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		}
		catch(PDOException $e){
			try {
				if($this->lock_tables) $this->source->query("UNLOCK TABLES");
			}
			catch(PDOException $ee){

			}
			echo " Failed clone table data $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone table data $table reason: ".$e->getMessage();
		}
		return $errors;
	}

	/**
	 * Backs up the structure (CREATE VIEW statement) of a given view to a SQL file.
	 *
	 * @param string $table The name of the view to backup.
	 * @return array An array of errors encountered during the backup process.
	 */
	public function backup_view(string $table) : array {
		if(!file_exists($this->get_output("views"))) mkdir($this->get_output("views"), $this->permissions, true);
		$errors = [];
		$file_path = $this->get_output("views").DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		try {
			fwrite($file, "-- やあ --\n\n");
			fwrite($file, $this->get_header()."\n\n");
			fwrite($file, $this->get_view_drop($table)."\n\n");
			fwrite($file, $this->get_view_creation($table)."\n\n");
			fwrite($file, "\n".$this->get_footer()."\n");
			echo " View: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		}
		catch(PDOException $e){
			echo "\n Failed make backup for view $table, skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed make backup for view $table reason: ".$e->getMessage();
		}
		fclose($file);
		return $errors;
	}

	/**
	 * Clones the structure (CREATE VIEW statement) of a given view to the destination database.
	 *
	 * @param string $table The name of the view to clone.
	 * @return array An array of errors encountered during the cloning process.
	 */
	public function clone_view(string $table) : array {
		$errors = [];
		try {
			$this->destination->query($this->get_header());
			$this->destination->query($this->get_view_drop($table));
			$this->destination->query($this->get_view_creation($table));
			$this->destination->query($this->get_footer());
		}
		catch(PDOException $e){
			echo " Failed clone view $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone view $table reason: ".$e->getMessage();
		}
		echo " View: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		return $errors;
	}

	/**
	 * Backs up the definition (CREATE FUNCTION statement) of a given function to a SQL file.
	 *
	 * @param string $table The name of the function to backup.
	 * @return array An array of errors encountered during the backup process.
	 */
	public function backup_function(string $table) : array {
		if(!file_exists($this->get_output("functions"))) mkdir($this->get_output("functions"), $this->permissions, true);
		$errors = [];
		$file_path = $this->get_output("functions").DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		fwrite($file, "-- やあ --\n\n");
		try {
			fwrite($file, $this->get_header()."\n\n");
			fwrite($file, $this->get_function_drop($table)."\n\n");
			fwrite($file, $this->get_function_creation($table)."\n\n");
			fwrite($file, "\n".$this->get_footer()."\n");
		}
		catch(PDOException $e){
			echo " Failed clone function $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone function $table reason: ".$e->getMessage();
		}
		echo " Function: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		fclose($file);
		return $errors;
	}

	/**
	 * Clones the definition (CREATE FUNCTION statement) of a given function to the destination database.
	 *
	 * @param string $table The name of the function to clone.
	 * @return array An array of errors encountered during the cloning process.
	 */
	public function clone_function(string $table) : array {
		$errors = [];
		try {
			$this->destination->query($this->get_header());
			$this->destination->query($this->get_function_drop($table));
			$this->destination->query($this->get_function_creation($table));
			$this->destination->query($this->get_footer());
		}
		catch(PDOException $e){
			echo " Failed clone function $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone function $table reason: ".$e->getMessage();
		}
		echo " Function: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		return $errors;
	}

	/**
	 * Backs up the definition (CREATE PROCEDURE statement) of a given procedure to a SQL file.
	 *
	 * @param string $table The name of the procedure to backup.
	 * @return array An array of errors encountered during the backup process.
	 */
	public function backup_procedure(string $table) : array {
		if(!file_exists($this->get_output("procedures"))) mkdir($this->get_output("procedures"), $this->permissions, true);
		$errors = [];
		$file_path = $this->get_output("procedures").DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		fwrite($file, "-- やあ --\n\n");
		try {
			fwrite($file, $this->get_header()."\n\n");
			fwrite($file, $this->get_procedure_drop($table)."\n\n");
			fwrite($file, $this->get_procedure_creation($table)."\n\n");
			fwrite($file, "\n".$this->get_footer()."\n");
		}
		catch(PDOException $e){
			echo " Failed clone procedure $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone procedure $table reason: ".$e->getMessage();
		}
		echo " Procedure: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		fclose($file);
		return $errors;
	}

	/**
	 * Clones the definition (CREATE PROCEDURE statement) of a given procedure to the destination database.
	 *
	 * @param string $table The name of the procedure to clone.
	 * @return array An array of errors encountered during the cloning process.
	 */
	public function clone_procedure(string $table) : array {
		$errors = [];
		try {
			$this->destination->query($this->get_header());
			$this->destination->query($this->get_procedure_drop($table));
			$this->destination->query($this->get_procedure_creation($table));
			$this->destination->query($this->get_footer());
		}
		catch(PDOException $e){
			echo " Failed clone procedure $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone procedure $table reason: ".$e->getMessage();
		}
		echo " Procedure: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		return $errors;
	}

	/**
	 * Backs up the definition (CREATE EVENT statement) of a given event to a SQL file.
	 *
	 * @param string $table The name of the event to backup.
	 * @return array An array of errors encountered during the backup process.
	 */
	public function backup_event(string $table) : array {
		if(!file_exists($this->get_output("events"))) mkdir($this->get_output("events"), $this->permissions, true);
		$errors = [];
		$file_path = $this->get_output("events").DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		fwrite($file, "-- やあ --\n\n");
		try {
			fwrite($file, $this->get_header()."\n\n");
			fwrite($file, $this->get_event_drop($table)."\n\n");
			fwrite($file, $this->get_event_creation($table)."\n\n");
			fwrite($file, "\n".$this->get_footer()."\n");
		}
		catch(PDOException $e){
			echo " Failed clone event $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone event $table reason: ".$e->getMessage();
		}
		echo " Event: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		fclose($file);
		return $errors;
	}

	/**
	 * Clones the definition (CREATE EVENT statement) of a given event to the destination database.
	 *
	 * @param string $table The name of the event to clone.
	 * @return array An array of errors encountered during the cloning process.
	 */
	public function clone_event(string $table) : array {
		$errors = [];
		try {
			$this->destination->query($this->get_header());
			$this->destination->query($this->get_event_drop($table));
			$this->destination->query($this->get_event_creation($table));
			$this->destination->query($this->get_footer());
		}
		catch(PDOException $e){
			echo " Failed clone event $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone event $table reason: ".$e->getMessage();
		}
		echo " Event: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		return $errors;
	}

	/**
	 * Backs up the definition (CREATE TRIGGER statement) of a given trigger to a SQL file.
	 *
	 * @param string $table The name of the trigger to backup.
	 * @return array An array of errors encountered during the backup process.
	 */
	public function backup_trigger(string $table) : array {
		if(!file_exists($this->path.DIRECTORY_SEPARATOR."triggers")) mkdir($this->path.DIRECTORY_SEPARATOR."triggers", $this->permissions, true);
		$errors = [];
		$file_path = $this->path.DIRECTORY_SEPARATOR."triggers".DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		fwrite($file, "-- やあ --\n\n");
		try {
			fwrite($file, $this->get_header()."\n\n");
			fwrite($file, $this->get_trigger_drop($table)."\n\n");
			fwrite($file, $this->get_triger_creation($table)."\n\n");
			fwrite($file, "\n".$this->get_footer()."\n");
		}
		catch(PDOException $e){
			echo " Failed clone trigger $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone trigger $table reason: ".$e->getMessage();
		}
		echo " Trigger: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		fclose($file);
		return $errors;
	}

	/**
	 * Clones the definition (CREATE TRIGGER statement) of a given trigger to the destination database.
	 *
	 * @param string $table The name of the trigger to clone.
	 * @return array An array of errors encountered during the cloning process.
	 */
	public function clone_trigger(string $table) : array {
		$errors = [];
		try {
			$this->destination->query($this->get_header());
			$this->destination->query($this->get_trigger_drop($table));
			$this->destination->query($this->get_triger_creation($table));
			$this->destination->query($this->get_footer());
		}
		catch(PDOException $e){
			echo " Failed clone trigger $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone trigger $table reason: ".$e->getMessage();
		}
		echo " Trigger: `$this->database`.`$table` Progress: 100.00 %        \r\n";
		return $errors;
	}

	/**
	 * Backs up the entire database, including tables (structure and data), views, functions, procedures, events, and triggers.
	 *
	 * @return array An array of all errors encountered during the backup process.
	 */
	public function backup_all() : array {
		$errors = [];
		$items = $this->get_tables();
		foreach($items as $item){
			$errors = array_merge($errors, $this->backup_table_structure($item));
			$errors = array_merge($errors, $this->backup_table_data($item));
		}
		$items = $this->get_views();
		foreach($items as $item){
			$errors = array_merge($errors, $this->backup_view($item));
		}
		$items = $this->get_functions();
		foreach($items as $item){
			$errors = array_merge($errors, $this->backup_function($item));
		}
		$items = $this->get_procedures();
		foreach($items as $item){
			$errors = array_merge($errors, $this->backup_procedure($item));
		}
		$items = $this->get_events();
		foreach($items as $item){
			$errors = array_merge($errors, $this->backup_event($item));
		}
		$items = $this->get_triggers();
		foreach($items as $item){
			$errors = array_merge($errors, $this->backup_trigger($item));
		}
		return $errors;
	}

	/**
	 * Clones the entire database from the source to the destination, including tables (structure and data), views, functions, procedures, events, and triggers.
	 * Table structures are cloned first, then data, and finally other database objects.
	 *
	 * @return array An array of all errors encountered during the cloning process.
	 */
	public function clone_all() : array {
		$this->reset_alters();
		$errors = [];
		$items = $this->get_tables();
		foreach($items as $item){
			$errors = array_merge($errors, $this->clone_table_structure($item));
		}
		if(!empty($this->get_alters())){
			try {
				$this->destination->query($this->get_alters());
			}
			catch(PDOException $e){
				echo " Failed to execute ALTER TABLE statements: ".$e->getMessage()."\r\n";
				$errors[] = "Failed to execute ALTER TABLE statements reason: ".$e->getMessage();
			}
		}
		foreach($items as $item){
			$errors = array_merge($errors, $this->clone_table_data($item));
		}
		$items = $this->get_views();
		foreach($items as $item){
			$errors = array_merge($errors, $this->clone_view($item));
		}
		$items = $this->get_functions();
		foreach($items as $item){
			$errors = array_merge($errors, $this->clone_function($item));
		}
		$items = $this->get_procedures();
		foreach($items as $item){
			$errors = array_merge($errors, $this->clone_procedure($item));
		}
		$items = $this->get_events();
		foreach($items as $item){
			$errors = array_merge($errors, $this->clone_event($item));
		}
		$items = $this->get_triggers();
		foreach($items as $item){
			$errors = array_merge($errors, $this->clone_trigger($item));
		}
		return $errors;
	}

}

?>