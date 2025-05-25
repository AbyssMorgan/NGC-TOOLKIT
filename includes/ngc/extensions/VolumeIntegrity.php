<?php

/* NGC-TOOLKIT v2.6.0 */

declare(strict_types=1);

namespace NGC\Extensions;

use PDO;
use Exception;
use PDOException;

class VolumeIntegrity {

	private string $database;
	private string $disk;
	private object $core;
	private ?PDO $db;
	private array $data;
	private int $allocation;

	public function __construct(object $core, string $database, string $disk, int $allocation = 0){
		$this->core = $core;
		$this->database = $database;
		$this->disk = $disk;
		$this->allocation = $allocation;
		$this->data = [];
		$this->connect();
	}

	private function create() : bool {
		try {
			$db = new PDO("sqlite:$this->database");
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$db->exec('PRAGMA journal_mode = MEMORY;');
			$db->exec('PRAGMA synchronous = OFF;');
			$db->exec("CREATE TABLE IF NOT EXISTS `media_items` (
				`id` INTEGER NOT NULL,
				`path` TEXT,
				`checksum` TEXT,
				`size` INTEGER,
				`modification_date` INTEGER,
				`validation_date` INTEGER,
				PRIMARY KEY(`id` AUTOINCREMENT)
			)");
			$db = null;
			$this->allocate();
		}
		catch(Exception $e){
			$this->core->echo(" Failed create sqlite data base \"$this->database\"");
			$this->core->echo(" ".$e->getMessage());
			return false;
		}
		return true;
	}

	private function connect() : bool {
		if(!file_exists($this->database)){
			if(!$this->create()) return false;
		}
		try {
			$this->allocate();
			$this->db = new PDO("sqlite:$this->database");
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->db->exec('PRAGMA journal_mode = MEMORY;');
			$this->db->exec('PRAGMA synchronous = OFF;');
			return true;
		}
		catch(PDOException $e){
			$this->core->echo(" Failed open sqlite data base \"$this->database\"");
			$this->core->echo(" ".$e->getMessage());
			return false;
		}
	}

	public function disconnect() : void {
		$this->db = null;
	}

	public function load() : bool {
		if(is_null($this->db)) return false;
		$this->data = $this->get_all();
		return true;
	}

	public function get_all() : array|false {
		if(is_null($this->db)) return false;
		$data = [];
		$items = $this->db->query("SELECT * FROM `media_items`", PDO::FETCH_OBJ);
		foreach($items as $item){
			$data[$item->path] = (object)[
				'id' => $item->id,
				'checksum' => $item->checksum,
				'size' => $item->size,
				'modification_date' => $item->modification_date,
				'validation_date' => $item->validation_date,
			];
		}
		return $data;
	}

	private function container_escape(string $path) : string {
		return str_ireplace(["$this->disk\\", "$this->disk/", "\\"], ["", "", "/"], $path);
	}

	public function set(string $path, array $values = []) : bool {
		if(is_null($this->db)) return false;
		
		$path = $this->container_escape($path);
		
		if(!isset($this->data[$path])){
			$this->data[$path] = (object)[
				'id' => null,
				'checksum' => null,
				'size' => null,
				'modification_date' => null,
				'validation_date' => null,
			];
		}
		
		$columns = [];
		$placeholders = [];
		$params = [];
		
		if(isset($values['checksum'])){
			$this->data[$path]->checksum = $values['checksum'];
			$columns[] = "`checksum`";
			$placeholders[] = ":checksum";
			$params[':checksum'] = $values['checksum'];
		}
		if(isset($values['size'])){
			$this->data[$path]->size = $values['size'];
			$columns[] = "`size`";
			$placeholders[] = ":size";
			$params[':size'] = $values['size'];
		}
		if(isset($values['modification_date'])){
			$this->data[$path]->modification_date = $values['modification_date'];
			$columns[] = "`modification_date`";
			$placeholders[] = ":modification_date";
			$params[':modification_date'] = $values['modification_date'];
		}
		if(isset($values['validation_date'])){
			$this->data[$path]->validation_date = $values['validation_date'];
			$columns[] = "`validation_date`";
			$placeholders[] = ":validation_date";
			$params[':validation_date'] = $values['validation_date'];
		}
		
		if(isset($this->data[$path]->id)){
			$updateParts = [];
			foreach($params as $key => $value){
				$column = trim($key, ':');
				$updateParts[] = "`$column` = $key";
			}
			$updateClause = implode(", ", $updateParts);
			$stmt = $this->db->prepare("UPDATE `media_items` SET $updateClause WHERE `id` = :id");
			$params[':id'] = $this->data[$path]->id;
		} else {
			$columns[] = "`path`";
			$placeholders[] = ":path";
			$params[':path'] = $path;
			$columnsClause = implode(", ", $columns);
			$placeholdersClause = implode(", ", $placeholders);
			$stmt = $this->db->prepare("INSERT INTO `media_items` ($columnsClause) VALUES ($placeholdersClause)");
		}
		
		foreach($params as $key => $value){
			$stmt->bindValue($key, $value);
		}
		
		$stmt->execute();
		
		if(!isset($this->data[$path]->id)){
			$this->data[$path]->id = $this->db->lastInsertId();
		}
	
		return true;
	}
	
	public function unset(string $path) : bool {
		if(is_null($this->db)) return false;
		$path = $this->container_escape($path);
		$this->db->query("DELETE FROM `media_items` WHERE `path` = '$path'");
		if(isset($this->data[$path])) unset($this->data[$path]);
		return true;
	}

	public function get(string $path) : ?object {
		if(is_null($this->db)) return null;
		$path = $this->container_escape($path);
		if(isset($this->data[$path])) return (object)$this->data[$path];
		$result = $this->db->query("SELECT * FROM `media_items` WHERE `path` = '$path'", PDO::FETCH_OBJ);
		if(!$result) return null;
		$row = $result->fetch();
		if(!$row) return null;
		return (object)[
			'id' => $row->id,
			'checksum' => $row->checksum,
			'size' => $row->size,
			'modification_date' => $row->modification_date,
			'validation_date' => $row->validation_date,
		];
	}

	public function rename(string $path_input, string $path_output) : bool {
		$stmt = $this->db->prepare("UPDATE `media_items` SET `path` = :path_output WHERE `path` = :path_input");
		$stmt->bindValue(':path_input', $path_input);			
		$stmt->bindValue(':path_output', $path_output);			
		return $stmt->execute();
	}

	public function cleanup(array $items) : array|false {
		if(is_null($this->db)) return false;
		$except = [];
		foreach($items as $item){
			$except[] = $this->container_escape($item);
		}
		unset($items, $item);
		$removed = [];
		$items = array_diff(array_keys($this->data), $except);
		foreach($items as $path){
			$removed[] = $path;
			$this->unset($path);
		}
		return $removed;
	}

	public function get_loaded() : array {
		return $this->data;
	}

	public function allocate() : void {
		$current_size = filesize($this->database);
		if($current_size < $this->allocation){
			$empty_space = $this->allocation - $current_size;
			$file = fopen($this->database, 'r+');
			fseek($file, $empty_space - 1, SEEK_END);
			fwrite($file, "\0");
			fclose($file);
		}
	}

}

?>