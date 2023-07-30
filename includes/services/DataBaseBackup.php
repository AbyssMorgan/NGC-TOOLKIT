<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

class DataBaseBackup {

	protected ?PDO $source;
	protected ?PDO $destination;
	protected string $database;

	private ?string $path;
	private string $alters;
	private bool $lock_tables = false;
	private int $query_limit;
	private int $insert_limit;
	private string $header;
	private string $footer;
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

	public function __construct(?string $path = null, int $query_limit = 50000, int $insert_limit = 100, string $date_format = "Y-m-d_His"){
		$date = date($date_format);
		$this->query_limit = $query_limit;
		$this->insert_limit = $insert_limit;
		if(!is_null($path)) $this->path = $path.DIRECTORY_SEPARATOR.$date;
		$this->header = base64_decode("U0VUIFNRTF9NT0RFID0gIk5PX0FVVE9fVkFMVUVfT05fWkVSTyI7ClNUQVJUIFRSQU5TQUNUSU9OOwpTRVQgdGltZV96b25lID0gIiswMDowMCI7CgovKiE0MDEwMSBTRVQgQE9MRF9DSEFSQUNURVJfU0VUX0NMSUVOVD1AQENIQVJBQ1RFUl9TRVRfQ0xJRU5UICovOwovKiE0MDEwMSBTRVQgQE9MRF9DSEFSQUNURVJfU0VUX1JFU1VMVFM9QEBDSEFSQUNURVJfU0VUX1JFU1VMVFMgKi87Ci8qITQwMTAxIFNFVCBAT0xEX0NPTExBVElPTl9DT05ORUNUSU9OPUBAQ09MTEFUSU9OX0NPTk5FQ1RJT04gKi87Ci8qITQwMTAxIFNFVCBOQU1FUyB1dGY4bWI0ICovOw==");
		$this->footer = base64_decode("Q09NTUlUOwoKLyohNDAxMDEgU0VUIENIQVJBQ1RFUl9TRVRfQ0xJRU5UPUBPTERfQ0hBUkFDVEVSX1NFVF9DTElFTlQgKi87Ci8qITQwMTAxIFNFVCBDSEFSQUNURVJfU0VUX1JFU1VMVFM9QE9MRF9DSEFSQUNURVJfU0VUX1JFU1VMVFMgKi87Ci8qITQwMTAxIFNFVCBDT0xMQVRJT05fQ09OTkVDVElPTj1AT0xEX0NPTExBVElPTl9DT05ORUNUSU9OICovOw==");
		$this->alters = '';
	}

	public function getAlters() : string {
		return $this->alters;
	}

	public function resetAlters() : void {
		$this->alters = '';
	}

	public function getOutput() : string {
		return $this->path;
	}

	public function toggleLockTables(bool $toggle) : void {
		$this->lock_tables = $toggle;
	}

	public function connect(string $host, string $user, string $password, string $dbname, int $port = 3306) : bool {
		$options = [
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION SQL_BIG_SELECTS=1;SET character_set_results = binary;',
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];
		try {
			$this->source = new PDO("mysql:dbname=$dbname;host=$host;port=$port;charset=UTF8", $user, $password, $options);
		}
		catch(PDOException $e){
			echo " Failed to connect:\r\n";
			echo " ".$e->getMessage()."\r\n";
			return false;
		}
		$this->database = $dbname;
		return true;
	}

	public function connect_destination(string $host, string $user, string $password, string $dbname, int $port = 3306) : bool {
		$options = [
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION SQL_BIG_SELECTS=1;SET character_set_results = binary;',
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];
		try {
			$this->destination = new PDO("mysql:dbname=$dbname;host=$host;port=$port;charset=UTF8", $user, $password, $options);
		}
		catch(PDOException $e){
			echo " Failed to connect:\r\n";
			echo " ".$e->getMessage()."\r\n";
			return false;
		}
		return true;
	}

	public function disconnect() : void {
		$this->source = null;
	}

	public function disconnect_destination() : void {
		$this->destination = null;
	}

	public function escape(mixed $string) : string {
		$string = strval($string) ?? '';
		if(empty($string)) return '';
		return str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"], ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $string);
	}

	public function isDestinationEmpty() : bool {
		$items = $this->destination->query('SHOW TABLES', PDO::FETCH_OBJ);
		return $items->rowCount() == 0;
	}

	public function getTables() : array {
		$data = [];
		$items = $this->source->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'BASE TABLE'", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->{'Tables_in_'.$this->database});
		}
		return $data;
	}

	public function getViews() : array {
		$data = [];
		$items = $this->source->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'VIEW'", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->{'Tables_in_'.$this->database});
		}
		return $data;
	}

	public function getFunctions() : array {
		$data = [];
		$items = $this->source->query("SHOW FUNCTION STATUS WHERE `Db` = '$this->database' AND `Type` = 'FUNCTION'", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->Name);
		}
		return $data;
	}

	public function getProcedures() : array {
		$data = [];
		$items = $this->source->query("SHOW PROCEDURE STATUS WHERE `Db` = '$this->database' AND `Type` = 'PROCEDURE'", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->Name);
		}
		return $data;
	}

	public function getEvents() : array {
		$data = [];
		$items = $this->source->query("SHOW EVENTS", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->Name);
		}
		return $data;
	}

	public function getTriggers() : array {
		$data = [];
		$items = $this->source->query("SHOW TRIGGERS", PDO::FETCH_OBJ);
		foreach($items as $item){
			array_push($data, $item->Trigger);
		}
		return $data;
	}

	public function getColumns(string $name) : array {
		$data = [];
		$columns = $this->source->query("SELECT `COLUMN_NAME`, `DATA_TYPE` FROM INFORMATION_SCHEMA.COLUMNS WHERE `TABLE_SCHEMA` = '$this->database' AND `TABLE_NAME` = '$name'", PDO::FETCH_OBJ);
		foreach($columns as $column){
			$data[$column->COLUMN_NAME] = strtolower($column->DATA_TYPE);
		}
		return $data;
	}

	public function getTableCreation(string $name) : array {
		$creation = $this->source->query("SHOW CREATE TABLE `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		$query = str_replace(["\r\n", "\r"], "\n", $data->{'Create Table'}.';');
		$items = explode("\n", $query);
		$alters = [];
		$removals = [];
		foreach($items as $key => &$item){
			$item = trim($item);
			if(strpos($item, 'CONSTRAINT ') !== false){
				$item = rtrim($item, ",");
				array_push($alters, "ALTER TABLE `$name` ADD $item;");
				array_push($removals, ", $item");
			} else if(empty($item)){
				unset($items[$key]);
			}
		}
		return [
			'query' => str_replace($removals, "", implode(" ", $items)),
			'alters' => implode("\n", $alters),
		];
	}

	public function getViewCreation(string $name) : string {
		$creation = $this->source->query("SHOW CREATE VIEW `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		return $data->{'Create View'}.';';
	}

	public function getFunctionCreation(string $name) : string {
		$creation = $this->source->query("SHOW CREATE FUNCTION `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		return "DELIMITER $$\n".$data->{'Create Function'}."$$\nDELIMITER ;\n";
	}

	public function getProcedureCreation(string $name) : string {
		$creation = $this->source->query("SHOW CREATE PROCEDURE `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		return "DELIMITER $$\n".$data->{'Create Procedure'}."$$\nDELIMITER ;\n";
	}

	public function getEventCreation(string $name) : string {
		$creation = $this->source->query("SHOW CREATE EVENT `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		return "DELIMITER $$\n".$data->{'Create Event'}."$$\nDELIMITER ;\n";
	}

	public function getTriggerCreation(string $name) : string {
		$creation = $this->source->query("SHOW CREATE TRIGGER `$name`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		return "DELIMITER $$\n".$data->{'SQL Original Statement'}."$$\nDELIMITER ;\n";
	}

	public function getTableDrop(string $name) : string {
		return "DROP TABLE IF EXISTS `$name`;";
	}

	public function getViewDrop(string $name) : string {
		return "DROP VIEW IF EXISTS `$name`;";
	}

	public function getFunctionDrop(string $name) : string {
		return "DROP FUNCTION IF EXISTS `$name`;";
	}

	public function getProcedureDrop(string $name) : string {
		return "DROP PROCEDURE IF EXISTS `$name`;";
	}

	public function getEventDrop(string $name) : string {
		return "DROP EVENT IF EXISTS `$name`;";
	}

	public function getTriggerDrop(string $name) : string {
		return "DROP TRIGGER IF EXISTS `$name`;";
	}

	public function getHeader() : string {
		return $this->header;
	}

	public function getFooter() : string {
		return $this->footer;
	}

	public function getInsert(string $table, array $columns) : string {
		$columns_string = '';
		foreach($columns as $column){
			$columns_string .= "`$column`,";
		}
		$columns_string = substr($columns_string, 0, -1);
		return "INSERT INTO `$table` ($columns_string)";
	}

	public function backupTableStructure(string $table) : array {
		if(!file_exists($this->path.DIRECTORY_SEPARATOR."structure")) mkdir($this->path.DIRECTORY_SEPARATOR."structure", 0755, true);
		$errors = [];
		$file_path = $this->path.DIRECTORY_SEPARATOR."structure".DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		try {
			$creation = $this->getTableCreation($table);
			fwrite($file, "-- やあ --\n\n");
			fwrite($file, $this->getHeader()."\n\n");
			fwrite($file, $this->getTableDrop($table)."\n\n");
			fwrite($file, $creation['query']."\n\n");
			fwrite($file, "\n".$this->getFooter()."\n");
			echo " Table structure: $table Progress: 100.00 %        \r\n";
		}
		catch(PDOException $e){
			echo "\n Failed make backup for table structure $table, skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed make backup for table structure $table reason: ".$e->getMessage();
		}
		fclose($file);
		if(!empty($creation['alters'])){
			if(!file_exists($this->path.DIRECTORY_SEPARATOR."alters")) mkdir($this->path.DIRECTORY_SEPARATOR."alters", 0755, true);
			$file_path = $this->path.DIRECTORY_SEPARATOR."alters".DIRECTORY_SEPARATOR."$table.sql";
			if(file_exists($file_path)) unlink($file_path);
			$file = fopen($file_path, "a");
			fwrite($file, $creation['alters']."\n\n");
			fclose($file);
		}
		return $errors;
	}

	public function cloneTableStructure(string $table) : array {
		$errors = [];
		try {
			$creation = $this->getTableCreation($table);
			$this->destination->query($this->getHeader());
			$this->destination->query($this->getTableDrop($table));
			$this->destination->query($creation['query']);
			$this->alters .= $creation['alters']."\n";
			$this->destination->query($this->getFooter());
			echo " Table structure: $table Progress: 100.00 %        \r";
		}
		catch(PDOException $e){
			echo " Failed clone table $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone table structure $table reason: ".$e->getMessage();
		}
		return $errors;
	}

	public function backupTableData(string $table) : array {
		if(!file_exists($this->path.DIRECTORY_SEPARATOR."data")) mkdir($this->path.DIRECTORY_SEPARATOR."data", 0755, true);
		$errors = [];
		$file_path = $this->path.DIRECTORY_SEPARATOR."data".DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		try {
			$offset = 0;
			$columns = $this->getColumns($table);
			fwrite($file, "-- やあ --\n\n");
			fwrite($file, $this->getHeader()."\n\n");
			fwrite($file, "SET foreign_key_checks = 0;\n\n");
			echo " Table data: $table Progress: 0.00 %        \r";
			$insert = $this->getInsert($table, array_keys($columns))." VALUES\n";
			if($this->lock_tables) $this->source->query("LOCK TABLE `$table` WRITE");
			$results = $this->source->query("SELECT count(*) AS cnt FROM `$table`");
			$row = $results->fetch(PDO::FETCH_OBJ);
			$count = $row->cnt;
			if($count > 0){
				do {
					$percent = sprintf("%.02f", ($offset / $count) * 100.0);
					echo " Table data: $table Progress: $percent %        \r";
					$rows = $this->source->query("SELECT * FROM `$table` LIMIT $offset, $this->query_limit", PDO::FETCH_OBJ);
					$seek = 0;
					$query = '';
					foreach($rows as $row){
						if($seek == 0){
							$query .= $insert;
						}
						$values = [];
						foreach($columns as $column => $type){
							if(is_null($row->$column)){
								$values[] = "NULL";
							} else if($type == 'bit'){
								if(empty($row->$column)){
									$values[] = "b'0'";
								} else {
									$values[] = "b'".decbin(intval($row->$column))."'";
								}
							} else if($type == 'blob' || $type == 'binary' || $type == 'longblob'){
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
			fwrite($file, "\n".$this->getFooter()."\n");
			echo " Table data: $table Progress: 100.00 %        \r\n";
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

	public function cloneTableData(string $table) : array {
		$errors = [];
		$offset = 0;
		try {
			$columns = $this->getColumns($table);
			$this->destination->query($this->getHeader());
			$this->destination->query("SET foreign_key_checks = 0;");
			echo " Table: $table Progress: 0.00 %        \r";
			$insert = $this->getInsert($table, array_keys($columns))." VALUES\n";
			if($this->lock_tables) $this->source->query("LOCK TABLE `$table` WRITE");
			$results = $this->source->query("SELECT count(*) AS cnt FROM `$table`");
			$row = $results->fetch(PDO::FETCH_OBJ);
			$count = $row->cnt;
			if($count > 0){
				do {
					$percent = sprintf("%.02f", ($offset / $count) * 100.0);
					echo " Table: $table Progress: $percent %        \r";
					$rows = $this->source->query("SELECT * FROM `$table` LIMIT $offset, $this->query_limit", PDO::FETCH_OBJ);
					$seek = 0;
					foreach($rows as $row){
						if($seek == 0){
							$query = $insert;
						}
						$values = [];
						foreach($columns as $column => $type){
							if(is_null($row->$column)){
								$values[] = "NULL";
							} else if($type == 'bit'){
								if(empty($row->$column)){
									$values[] = "b'0'";
								} else {
									$values[] = "b'".decbin(intval($row->$column))."'";
								}
							} else if($type == 'blob' || $type == 'binary' || $type == 'longblob'){
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
			$this->destination->query($this->getFooter());
			echo " Table: $table Progress: 100.00 %        \r";
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

	public function backupView(string $table) : array {
		if(!file_exists($this->path.DIRECTORY_SEPARATOR."views")) mkdir($this->path.DIRECTORY_SEPARATOR."views", 0755, true);
		$errors = [];
		$file_path = $this->path.DIRECTORY_SEPARATOR."views".DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		try {
			fwrite($file, "-- やあ --\n\n");
			fwrite($file, $this->getHeader()."\n\n");
			fwrite($file, $this->getViewDrop($table)."\n\n");
			fwrite($file, $this->getViewCreation($table)."\n\n");
			fwrite($file, "\n".$this->getFooter()."\n");
			echo " View: $table Progress: 100.00 %        \r\n";
		}
		catch(PDOException $e){
			echo "\n Failed make backup for view $table, skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed make backup for view $table reason: ".$e->getMessage();
		}
		fclose($file);
		return $errors;
	}

	public function cloneView(string $table) : array {
		$errors = [];
		try {
			$this->destination->query($this->getHeader());
			$this->destination->query($this->getViewDrop($table));
			$this->destination->query($this->getViewCreation($table));
			$this->destination->query($this->getFooter());
		}
		catch(PDOException $e){
			echo " Failed clone view $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone view $table reason: ".$e->getMessage();
		}
		echo " View: $table Progress: 100.00 %        \r\n";
		return $errors;
	}

	public function backupFunction(string $table) : array {
		if(!file_exists($this->path.DIRECTORY_SEPARATOR."functions")) mkdir($this->path.DIRECTORY_SEPARATOR."functions", 0755, true);
		$errors = [];
		$file_path = $this->path.DIRECTORY_SEPARATOR."functions".DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		fwrite($file, "-- やあ --\n\n");
		try {
			fwrite($file, $this->getHeader()."\n\n");
			fwrite($file, $this->getFunctionDrop($table)."\n\n");
			fwrite($file, $this->getFunctionCreation($table)."\n\n");
			fwrite($file, "\n".$this->getFooter()."\n");
		}
		catch(PDOException $e){
			echo " Failed clone function $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone function $table reason: ".$e->getMessage();
		}
		echo " Function: $table Progress: 100.00 %        \r\n";
		fclose($file);
		return $errors;
	}

	public function cloneFunction(string $table) : array {
		$errors = [];
		try {
			$this->destination->query($this->getHeader());
			$this->destination->query($this->getFunctionDrop($table));
			$this->destination->query($this->getFunctionCreation($table));
			$this->destination->query($this->getFooter());
		}
		catch(PDOException $e){
			echo " Failed clone function $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone function $table reason: ".$e->getMessage();
		}
		echo " Function: $table Progress: 100.00 %        \r\n";
		return $errors;
	}

	public function backupProcedure(string $table) : array {
		if(!file_exists($this->path.DIRECTORY_SEPARATOR."procedures")) mkdir($this->path.DIRECTORY_SEPARATOR."procedures", 0755, true);
		$errors = [];
		$file_path = $this->path.DIRECTORY_SEPARATOR."procedures".DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		fwrite($file, "-- やあ --\n\n");
		try {
			fwrite($file, $this->getHeader()."\n\n");
			fwrite($file, $this->getProcedureDrop($table)."\n\n");
			fwrite($file, $this->getProcedureCreation($table)."\n\n");
			fwrite($file, "\n".$this->getFooter()."\n");
		}
		catch(PDOException $e){
			echo " Failed clone procedure $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone procedure $table reason: ".$e->getMessage();
		}
		echo " Procedure: $table Progress: 100.00 %        \r\n";
		fclose($file);
		return $errors;
	}

	public function cloneProcedure(string $table) : array {
		$errors = [];
		try {
			$this->destination->query($this->getHeader());
			$this->destination->query($this->getProcedureDrop($table));
			$this->destination->query($this->getProcedureCreation($table));
			$this->destination->query($this->getFooter());
		}
		catch(PDOException $e){
			echo " Failed clone procedure $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone procedure $table reason: ".$e->getMessage();
		}
		echo " Procedure: $table Progress: 100.00 %        \r\n";
		return $errors;
	}

	public function backupEvent(string $table) : array {
		if(!file_exists($this->path.DIRECTORY_SEPARATOR."events")) mkdir($this->path.DIRECTORY_SEPARATOR."events", 0755, true);
		$errors = [];
		$file_path = $this->path.DIRECTORY_SEPARATOR."events".DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		fwrite($file, "-- やあ --\n\n");
		try {
			fwrite($file, $this->getHeader()."\n\n");
			fwrite($file, $this->getEventDrop($table)."\n\n");
			fwrite($file, $this->getEventCreation($table)."\n\n");
			fwrite($file, "\n".$this->getFooter()."\n");
		}
		catch(PDOException $e){
			echo " Failed clone event $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone event $table reason: ".$e->getMessage();
		}
		echo " Event: $table Progress: 100.00 %        \r\n";
		fclose($file);
		return $errors;
	}

	public function cloneEvent(string $table) : array {
		$errors = [];
		try {
			$this->destination->query($this->getHeader());
			$this->destination->query($this->getEventDrop($table));
			$this->destination->query($this->getEventCreation($table));
			$this->destination->query($this->getFooter());
		}
		catch(PDOException $e){
			echo " Failed clone event $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone event $table reason: ".$e->getMessage();
		}
		echo " Event: $table Progress: 100.00 %        \r\n";
		return $errors;
	}

	public function backupTrigger(string $table) : array {
		if(!file_exists($this->path.DIRECTORY_SEPARATOR."triggers")) mkdir($this->path.DIRECTORY_SEPARATOR."triggers", 0755, true);
		$errors = [];
		$file_path = $this->path.DIRECTORY_SEPARATOR."triggers".DIRECTORY_SEPARATOR."$table.sql";
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "a");
		fwrite($file, "-- やあ --\n\n");
		try {
			fwrite($file, $this->getHeader()."\n\n");
			fwrite($file, $this->getTriggerDrop($table)."\n\n");
			fwrite($file, $this->getTriggerCreation($table)."\n\n");
			fwrite($file, "\n".$this->getFooter()."\n");
		}
		catch(PDOException $e){
			echo " Failed clone trigger $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone trigger $table reason: ".$e->getMessage();
		}
		echo " Trigger: $table Progress: 100.00 %        \r\n";
		fclose($file);
		return $errors;
	}

	public function cloneTrigger(string $table) : array {
		$errors = [];
		try {
			$this->destination->query($this->getHeader());
			$this->destination->query($this->getTriggerDrop($table));
			$this->destination->query($this->getTriggerCreation($table));
			$this->destination->query($this->getFooter());
		}
		catch(PDOException $e){
			echo " Failed clone trigger $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone trigger $table reason: ".$e->getMessage();
		}
		echo " Trigger: $table Progress: 100.00 %        \r\n";
		return $errors;
	}

	public function backupAll(bool $backup_structure = true, bool $backup_data = true) : array {
		$errors = [];
		$items = $this->getTables();
		foreach($items as $item){
			array_merge($errors, $this->backupTableStructure($item));
			array_merge($errors, $this->backupTableData($item));
		}
		$items = $this->getViews();
		foreach($items as $item){
			array_merge($errors, $this->backupView($item));
		}
		$items = $this->getFunctions();
		foreach($items as $item){
			array_merge($errors, $this->backupFunction($item));
		}
		$items = $this->getProcedures();
		foreach($items as $item){
			array_merge($errors, $this->backupProcedure($item));
		}
		$items = $this->getEvents();
		foreach($items as $item){
			array_merge($errors, $this->backupEvent($item));
		}
		$items = $this->getTriggers();
		foreach($items as $item){
			array_merge($errors, $this->backupTrigger($item));
		}
		return $errors;
	}

	public function cloneAll() : array {
		$this->resetAlters();
		$errors = [];
		$items = $this->getTables();
		foreach($items as $item){
			array_merge($errors, $this->cloneTableStructure($item));
		}
		$this->destination->query($this->getAlters());
		foreach($items as $item){
			array_merge($errors, $this->cloneTableData($item));
		}
		$items = $this->getViews();
		foreach($items as $item){
			array_merge($errors, $this->cloneView($item));
		}
		$items = $this->getFunctions();
		foreach($items as $item){
			array_merge($errors, $this->cloneFunction($item));
		}
		$items = $this->getProcedures();
		foreach($items as $item){
			array_merge($errors, $this->cloneProcedure($item));
		}
		$items = $this->getEvents();
		foreach($items as $item){
			array_merge($errors, $this->cloneEvent($item));
		}
		$items = $this->getTriggers();
		foreach($items as $item){
			array_merge($errors, $this->cloneTrigger($item));
		}
		return $errors;
	}

}

?>
