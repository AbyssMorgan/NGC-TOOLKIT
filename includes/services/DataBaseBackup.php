<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

class DataBaseBackup {

	protected ?PDO $source;
	protected ?PDO $destination;
	protected string $database;

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

	public function __construct(string $path, int $query_limit = 50000, int $insert_limit = 100, string $date_format = "Y-m-d_His"){
		$date = date($date_format);
		$this->query_limit = $query_limit;
		$this->insert_limit = $insert_limit;
		$this->path = $path.DIRECTORY_SEPARATOR.$date;
		$this->header = base64_decode("U0VUIFNRTF9NT0RFID0gIk5PX0FVVE9fVkFMVUVfT05fWkVSTyI7ClNUQVJUIFRSQU5TQUNUSU9OOwpTRVQgdGltZV96b25lID0gIiswMDowMCI7CgovKiE0MDEwMSBTRVQgQE9MRF9DSEFSQUNURVJfU0VUX0NMSUVOVD1AQENIQVJBQ1RFUl9TRVRfQ0xJRU5UICovOwovKiE0MDEwMSBTRVQgQE9MRF9DSEFSQUNURVJfU0VUX1JFU1VMVFM9QEBDSEFSQUNURVJfU0VUX1JFU1VMVFMgKi87Ci8qITQwMTAxIFNFVCBAT0xEX0NPTExBVElPTl9DT05ORUNUSU9OPUBAQ09MTEFUSU9OX0NPTk5FQ1RJT04gKi87Ci8qITQwMTAxIFNFVCBOQU1FUyB1dGY4bWI0ICovOw==");
		$this->footer = base64_decode("Q09NTUlUOwoKLyohNDAxMDEgU0VUIENIQVJBQ1RFUl9TRVRfQ0xJRU5UPUBPTERfQ0hBUkFDVEVSX1NFVF9DTElFTlQgKi87Ci8qITQwMTAxIFNFVCBDSEFSQUNURVJfU0VUX1JFU1VMVFM9QE9MRF9DSEFSQUNURVJfU0VUX1JFU1VMVFMgKi87Ci8qITQwMTAxIFNFVCBDT0xMQVRJT05fQ09OTkVDVElPTj1AT0xEX0NPTExBVElPTl9DT05ORUNUSU9OICovOw==");
	}

	public function getOutput() : string {
		return $this->path;
	}

	public function set_max_allowed_packet(int $value) : bool {
		try {
			$this->destination->query("SET GLOBAL `max_allowed_packet` = $value;");
			return true;
		}
		catch(PDOException $e){
			echo " ".$e->getMessage()."\r\n";
			return false;
		}
	}

	public function connect(string $host, string $user, string $password, string $dbname, int $port = 3306) : bool {
		$options = [
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION SQL_BIG_SELECTS=1;',
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];
		try {
			$this->source = new PDO("mysql:dbname=$dbname;host=$host;port=$port;charset=utf8mb4", $user, $password, $options);
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
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION SQL_BIG_SELECTS=1;',
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];
		try {
			$this->destination = new PDO("mysql:dbname=$dbname;host=$host;port=$port;charset=utf8mb4", $user, $password, $options);
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
		$tables = $this->destination->query('SHOW TABLES', PDO::FETCH_OBJ);
		return $tables->rowCount() == 0;
	}

	public function getTables() : array {
		$data = [];
		$tables = $this->source->query('SHOW TABLES', PDO::FETCH_OBJ);
		foreach($tables as $table){
			array_push($data, $table->{'Tables_in_'.$this->database});
		}
		return $data;
	}

	public function getColumns(string $table) : array {
		$data = [];
		$columns = $this->source->query("SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE `TABLE_SCHEMA` = '$this->database' AND `TABLE_NAME` = '$table'", PDO::FETCH_OBJ);
		foreach($columns as $column){
			$data[$column->COLUMN_NAME] = strtolower($column->DATA_TYPE);
		}
		return $data;
	}

	public function getCreation(string $table) : string {
		$creation = $this->source->query("SHOW CREATE TABLE `$table`", PDO::FETCH_OBJ);
		$data = $creation->fetch(PDO::FETCH_OBJ);
		return $data->{'Create Table'}.';';
	}

	public function getDrop(string $table) : string {
		return "DROP TABLE IF EXISTS `$table`;";
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

	public function backupTable(string $table, bool $backup_structure = true, bool $backup_data = true) : array {
		if(!file_exists($this->path)) mkdir($this->path, 0755, true);
		$errors = [];
		$offset = 0;
		$file_path = $this->path.DIRECTORY_SEPARATOR."$table.sql";
		$columns = $this->getColumns($table);
		if(file_exists($file_path)) unlink($file_path);
		$file = fopen($file_path, "wb");

		fwrite($file, "-- やあ --\n\n");
		fwrite($file, $this->getHeader()."\n\n");
		if($backup_structure){
			fwrite($file, $this->getDrop($table)."\n\n");
			fwrite($file, $this->getCreation($table)."\n\n");
		}

		if($backup_data){
			try {
				echo " Table: $table Progress: 0.00 %        \r";
				$insert = $this->getInsert($table, array_keys($columns))." VALUES\n";
				$this->source->query("LOCK TABLE `$table` WRITE");
				$results = $this->source->query("SELECT count(*) AS cnt FROM `$table`");
				$row = $results->fetch(PDO::FETCH_OBJ);
				$count = $row->cnt;
				if($count > 0){
					do {
						$percent = sprintf("%.02f", ($offset / $count) * 100.0);
						echo " Table: $table Progress: $percent %        \r";
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
							$query .= '('.implode(',',$values).')';
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
				$this->source->query("UNLOCK TABLES");
				echo " Table: $table Progress: 100.00 %        \r\n";
			}
			catch(PDOException $e){
				try {
					$this->source->query("UNLOCK TABLES");
				}
				catch(PDOException $ee){

				}
				echo "\n Failed make backup for table $table, skipping\r\n";
				echo " ".$e->getMessage()."\r\n";
				$errors[] = "Failed make backup for table $table reason: ".$e->getMessage();
			}
		} else {
			echo " Table: $table Progress: 100.00 %        \r\n";
		}

		fwrite($file, "\n".$this->getFooter()."\n");

		fclose($file);
		return $errors;
	}

	public function cloneTable(string $table) : array {
		$errors = [];
		$offset = 0;
		$columns = $this->getColumns($table);

		$this->destination->query($this->getHeader());
		$this->destination->query($this->getDrop($table));
		$this->destination->query($this->getCreation($table));

		try {
			echo " Table: $table Progress: 0.00 %        \r";
			$insert = $this->getInsert($table, array_keys($columns))." VALUES\n";
			$this->source->query("LOCK TABLE `$table` WRITE");
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
						$query .= '('.implode(',',$values).'),'."\n";
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
			$this->source->query("UNLOCK TABLES");
			echo " Table: $table Progress: 100.00 %        \r";
		}
		catch(PDOException $e){
			try {
				$this->source->query("UNLOCK TABLES");
			}
			catch(PDOException $ee){

			}
			echo " Failed clone table $table skipping\r\n";
			echo " ".$e->getMessage()."\r\n";
			$errors[] = "Failed clone table $table reason: ".$e->getMessage();
		}

		$this->destination->query($this->getFooter());
		return $errors;
	}

	public function backupAll(bool $backup_structure = true, bool $backup_data = true) : array {
		$errors = [];
		$tables = $this->getTables();
		foreach($tables as $table){
			array_merge($errors, $this->backupTable($table));
		}
		return $errors;
	}

	public function cloneAll() : array {
		$errors = [];
		$tables = $this->getTables();
		foreach($tables as $table){
			array_merge($errors, $this->cloneTable($table));
		}
		return $errors;
	}

}

?>
