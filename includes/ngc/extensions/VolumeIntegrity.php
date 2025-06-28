<?php

/**
 * NGC-TOOLKIT v2.7.0 – Component
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
use PDO;
use Exception;
use PDOException;

/**
 * Manages the integrity and metadata of media items stored within a SQLite database,
 * associated with a specific disk volume.
 */
class VolumeIntegrity {

	/**
	 * The path to the SQLite database file.
	 * @var string
	 */
	private string $database;

	/**
	 * The path to the disk volume this integrity manager is associated with.
	 * @var string
	 */
	private string $disk;

	/**
	 * The core toolkit or script instance.
	 * @var Toolkit|Script
	 */
	private Toolkit|Script $core;

	/**
	 * The PDO database connection instance.
	 * @var PDO|null
	 */
	private ?PDO $db;

	/**
	 * Cached volume integrity data
	 * @var array
	 */
	private array $data;

	/**
	 * The desired allocation size for the database file in bytes.
	 * @var int
	 */
	private int $allocation;

	/**
	 * Constructor for VolumeIntegrity.
	 *
	 * @param Toolkit|Script $core The core Toolkit or Script instance for output.
	 * @param string $database The path to the SQLite database file.
	 * @param string $disk The path to the disk volume this integrity manager is associated with.
	 * @param int $allocation The desired allocation size for the database file in bytes. Defaults to 0.
	 */
	public function __construct(Toolkit|Script $core, string $database, string $disk, int $allocation = 0){
		$this->core = $core;
		$this->database = $database;
		$this->disk = $disk;
		$this->allocation = $allocation;
		$this->data = [];
		$this->connect();
	}

	/**
	 * Creates the SQLite database and the 'media_items' table if they do not exist.
	 * Sets PRAGMA journal_mode to MEMORY and PRAGMA synchronous to OFF for performance.
	 *
	 * @return bool True if the database and table were created successfully, false otherwise.
	 */
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

	/**
	 * Establishes a connection to the SQLite database.
	 * If the database file does not exist, it attempts to create it.
	 * Sets PDO error mode to exception and PRAGMA settings for performance.
	 *
	 * @return bool True if the connection was successful, false otherwise.
	 */
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

	/**
	 * Disconnects from the SQLite database by setting the PDO object to null.
	 */
	public function disconnect() : void {
		$this->db = null;
	}

	/**
	 * Loads all media items from the database into the internal data array.
	 *
	 * @return bool True if the data was loaded successfully, false if the database is not connected.
	 */
	public function load() : bool {
		if(is_null($this->db)) return false;
		$this->data = $this->get_all();
		return true;
	}

	/**
	 * Retrieves all media items from the 'media_items' table in the database.
	 *
	 * @return array|false An associative array of media items, keyed by their path, or false if the database is not connected.
	 * Each item is an object with properties: id, checksum, size, modification_date, validation_date.
	 */
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

	/**
	 * Escapes the disk path from a given full path, standardizing directory separators to forward slashes.
	 *
	 * @param string $path The full path to escape.
	 * @return string The escaped path relative to the disk.
	 */
	private function container_escape(string $path) : string {
		return str_ireplace(["$this->disk\\", "$this->disk/", "\\"], ["", "", "/"], $path);
	}

	/**
	 * Inserts or updates a media item's metadata in the database and the internal data array.
	 *
	 * @param string $path The path of the media item.
	 * @param array $values An associative array of values to set (e.g., 'checksum', 'size', 'modification_date', 'validation_date').
	 * @return bool True if the operation was successful, false if the database is not connected.
	 */
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

	/**
	 * Deletes a media item from the database and the internal data array.
	 *
	 * @param string $path The path of the media item to unset.
	 * @return bool True if the operation was successful, false if the database is not connected.
	 */
	public function unset(string $path) : bool {
		if(is_null($this->db)) return false;
		$path = $this->container_escape($path);
		$this->db->query("DELETE FROM `media_items` WHERE `path` = '$path'");
		if(isset($this->data[$path])) unset($this->data[$path]);
		return true;
	}

	/**
	 * Retrieves a single media item's metadata from the database or the loaded data.
	 *
	 * @param string $path The path of the media item to retrieve.
	 * @return object|null An object containing the media item's data, or null if not found or database not connected.
	 */
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

	/**
	 * Renames the path of a media item in the database.
	 *
	 * @param string $path_input The current path of the media item.
	 * @param string $path_output The new path for the media item.
	 * @return bool True if the rename operation was successful, false otherwise.
	 */
	public function rename(string $path_input, string $path_output) : bool {
		$stmt = $this->db->prepare("UPDATE `media_items` SET `path` = :path_output WHERE `path` = :path_input");
		$stmt->bindValue(':path_input', $path_input);
		$stmt->bindValue(':path_output', $path_output);
		return $stmt->execute();
	}

	/**
	 * Cleans up the database by removing media items whose paths are not present in the provided list.
	 *
	 * @param array $items An array of current valid media item paths.
	 * @return array|false An array of paths that were removed, or false if the database is not connected.
	 */
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

	/**
	 * Defragments the `media_items` table by re-assigning sequential IDs and updating the sequence.
	 * This can help optimize database size and query performance.
	 *
	 * @return bool True if defragmentation was successful, false if the database is not connected.
	 */
	public function defragment() : bool {
		if(is_null($this->db)) return false;
		$id = 1;
		$offset = 0;
		$this->db->beginTransaction();
		do {
			$stmt = $this->db->prepare("SELECT `id` FROM `media_items` ORDER BY `id` ASC LIMIT 50000 OFFSET $offset");
			$stmt->execute();
			$items = $stmt->fetchAll(PDO::FETCH_COLUMN);
			foreach($items as $item){
				if($id != $item){
					$update = $this->db->prepare("UPDATE `media_items` SET `id` = :new_id WHERE `id` = :old_id");
					$update->execute([':new_id' => $id, ':old_id' => $item]);
				}
				$id++;
			}
			$offset += count($items);
		} while(count($items) > 0);
		$this->db->commit();
		$stmt = $this->db->query("SELECT MAX(`id`) as `max_id` FROM `media_items`");
		$max_id = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
		$next_id = $max_id + 1;
		$this->db->exec("UPDATE `sqlite_sequence` SET `seq` = $next_id WHERE `name` = 'media_items'");
		return true;
	}

	/**
	 * Returns the currently loaded media items data.
	 *
	 * @return array An associative array of loaded media items.
	 */
	public function get_loaded() : array {
		return $this->data;
	}

	/**
	 * Allocates space for the database file to a specified size.
	 * If the current file size is less than the allocation, it expands the file.
	 */
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