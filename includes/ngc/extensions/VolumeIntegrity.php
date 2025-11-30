<?php

/**
 * NGC-TOOLKIT v2.7.4 – Component
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
use NGC\Core\IniFile;

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
	 * The name of the table used to store volume information.
	 * @var string
	 */
	protected string $table_data;

	/**
	 * An internal cache to store the existence status of tables.
	 * @var array
	 */
	protected array $tables = [];
	
	/**
	 * The id of the volume.
	 * @var string
	 */
	protected ?string $volume_id;

	/**
	 * The name of the volume.
	 * @var string
	 */
	protected ?string $volume_name;

	/**
	 * The group of the volume.
	 * @var string
	 */
	protected ?string $volume_group;

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
		$this->table_version = 'ngc_version';
		$this->table_config = 'ngc_config';
		$this->table_data = 'ngc_items';
		$this->connect();
		$this->volume_id = $this->get_value('VOLUME_ID', null);
		$this->volume_name = $this->get_value('VOLUME_NAME', null);
		$this->volume_group = $this->get_value('VOLUME_GROUP', null);
	}

	/**
	 * Creates the SQLite database and structure.
	 * Sets PRAGMA journal_mode to MEMORY and PRAGMA synchronous to OFF for performance.
	 *
	 * @return bool True if the database and table were created successfully, false otherwise.
	 */
	private function create() : bool {
		try {
			$this->db = new PDO("sqlite:$this->database");
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->db->exec('PRAGMA journal_mode = MEMORY;');
			$this->db->exec('PRAGMA synchronous = OFF;');
			$this->migrate();
			$this->db = null;
			$this->allocate();
		}
		catch(Exception $e){
			$this->core->echo(" Failed create sqlite data base \"$this->database\"");
			$this->core->echo(" ".$e->getMessage());
			return false;
		}
		return true;
	}

	private function table_exists(string $table_name) : bool{
		$stmt = $this->db->prepare("SELECT `name` FROM `sqlite_master` WHERE `type` = 'table' AND `name` = :table LIMIT 1");
		$stmt->bindValue(':table', $table_name, PDO::PARAM_STR);
		$stmt->execute();
		return $stmt->fetchColumn() !== false;
	}

	private function migrate() : void {
		if(!$this->table_exists($this->table_version)){
			$this->db->query("CREATE TABLE IF NOT EXISTS `$this->table_version` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `table_name` TEXT NOT NULL, `version` INTEGER NOT NULL DEFAULT 0)");
			$this->tables[$this->table_version] = true;
		}

		$version = $this->get_version($this->table_config, false);
		if($version < 1){
			$this->db->query("CREATE TABLE IF NOT EXISTS `$this->table_config` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `name` TEXT DEFAULT NULL, `value` TEXT DEFAULT NULL)");
			$this->set_version($this->table_config, 1);
			$this->tables[$this->table_config] = true;
		}

		if($this->table_exists('media_items')){
			$this->db->query("ALTER TABLE `media_items` ADD COLUMN `mime_type` TEXT");
			$this->db->query("ALTER TABLE `media_items` RENAME TO `ngc_items`");
			$this->set_version($this->table_data, 1);
			$this->tables[$this->table_data] = true;
		}

		$version = $this->get_version($this->table_data, false);
		if($version < 1){
			$this->db->query("CREATE TABLE IF NOT EXISTS `$this->table_data` (`id` INTEGER NOT NULL, `path` TEXT, `checksum` TEXT, `size` INTEGER, `modification_date` INTEGER, `validation_date` INTEGER, `mime_type` TEXT, PRIMARY KEY(`id` AUTOINCREMENT))");
			$this->set_version($this->table_data, 1);
			$this->tables[$this->table_data] = true;
		}
	}

	/**
	 * Establishes a connection to the SQLite database.
	 * If the database file does not exist, it attempts to create it.
	 * Sets PDO error mode to exception and PRAGMA settings for performance.
	 *
	 * @return bool True if the connection was successful, false otherwise.
	 */
	private function connect() : bool {
		if(!\file_exists($this->database)){
			if(!$this->create()) return false;
		}
		try {
			$this->allocate();
			$this->db = new PDO("sqlite:$this->database");
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$this->db->exec('PRAGMA journal_mode = MEMORY;');
			$this->db->exec('PRAGMA synchronous = OFF;');
			$this->migrate();
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
	 * @param string $order_by Column for order
	 * @param string $order Order type (ASC/DESC)
	 * @return bool True if the data was loaded successfully, false if the database is not connected.
	 */
	public function load(string $order_by = 'path', string $order = 'ASC') : bool {
		if(\is_null($this->db)) return false;
		$this->data = $this->get_all($order_by, $order);
		return true;
	}

	/**
	 * Retrieves all media items from the data table in the database.
	 *
	 * @param string $order_by Column for order
	 * @param string $order Order type (ASC/DESC)
	 * @return array|false An associative array of media items, keyed by their path, or false if the database is not connected.
	 * Each item is an object with properties: id, checksum, size, modification_date, validation_date.
	 */
	public function get_all(string $order_by = 'path', string $order = 'ASC') : array|false {
		if(\is_null($this->db)) return false;
		$order = strtoupper($order);
		if(!\in_array($order_by, ['id', 'path', 'checksum', 'mime_type', 'size', 'modification_date', 'validation_date'])){
			throw new Exception("Invalid order by value");
		}
		if(!\in_array($order, ['ASC', 'DESC'])){
			throw new Exception("Invalid order value");
		}
		$data = [];
		$items = $this->db->query("SELECT * FROM `$this->table_data` ORDER BY `$order_by` $order", PDO::FETCH_OBJ);
		foreach($items as $item){
			if(isset($data[$item->path])){
				$this->core->echo(" Duplicate integrity for \"$item->path\", removed.");
				$this->db->query("DELETE FROM `$this->table_data` WHERE `id` = $item->id");
				continue;
			}
			$data[$item->path] = (object)[
				'id' => $item->id,
				'checksum' => $item->checksum,
				'mime_type' => $item->mime_type,
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
	public function container_escape(string $path) : string {
		return \str_ireplace(["$this->disk\\", "$this->disk/", "\\"], ["", "", "/"], $path);
	}

	/**
	 * Inserts or updates a media item's metadata in the database and the internal data array.
	 *
	 * @param string $path The path of the media item.
	 * @param array $values An associative array of values to set (e.g., 'checksum', 'size', 'modification_date', 'validation_date').
	 * @return bool True if the operation was successful, false if the database is not connected.
	 */
	public function set(string $path, array $values = []) : bool {
		if(\is_null($this->db)) return false;

		$path = $this->container_escape($path);

		if(!isset($this->data[$path])){
			$this->data[$path] = (object)[
				'id' => null,
				'checksum' => null,
				'mime_type' => null,
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
		if(isset($values['mime_type'])){
			$this->data[$path]->mime_type = $values['mime_type'];
			$columns[] = "`mime_type`";
			$placeholders[] = ":mime_type";
			$params[':mime_type'] = $values['mime_type'];
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
			$update_parts = [];
			foreach($params as $key => $value){
				$column = \trim($key, ':');
				$update_parts[] = "`$column` = $key";
			}
			$update_claus = \implode(", ", $update_parts);
			$stmt = $this->db->prepare("UPDATE `$this->table_data` SET $update_claus WHERE `id` = :id");
			$params[':id'] = $this->data[$path]->id;
		} else {
			$columns[] = "`path`";
			$placeholders[] = ":path";
			$params[':path'] = $path;
			$columns_clause = \implode(", ", $columns);
			$placeholders_clause = \implode(", ", $placeholders);
			$stmt = $this->db->prepare("INSERT INTO `$this->table_data` ($columns_clause) VALUES ($placeholders_clause)");
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
		if(\is_null($this->db)) return false;
		$path = $this->container_escape($path);
		$query = $this->db->prepare("DELETE FROM `$this->table_data` WHERE `path` = :path");
		$query->bindParam(':path', $path);
		$query->execute();
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
		if(\is_null($this->db)) return null;
		$path = $this->container_escape($path);
		if(isset($this->data[$path])) return (object)$this->data[$path];
		$query = $this->db->prepare("SELECT * FROM `$this->table_data` WHERE `path` = :path");
		$query->bindParam(':path', $path);
		$result = $query->fetch(PDO::FETCH_OBJ);
		if(!$result) return null;
		$row = $result->fetch();
		if(!$row) return null;
		return (object)[
			'id' => $row->id,
			'checksum' => $row->checksum,
			'mime_type' => $row->mime_type,
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
		if(\is_null($this->db)) return false;
		$stmt = $this->db->prepare("UPDATE `$this->table_data` SET `path` = :path_output WHERE `path` = :path_input");
		$stmt->bindValue(':path_input', $path_input);
		$stmt->bindValue(':path_output', $path_output);
		return $stmt->execute();
	}

	/**
	 * Cleans up the database by removing media items whose paths are not present in the provided list.
	 *
	 * @param array $items An array of current valid media item paths.
	 * @param callable $callback Callback called after element removed
	 * @return bool True if operation was successful, false otherwise.
	 */
	public function cleanup(array $items, callable $callback) : bool {
		if(\is_null($this->db)) return false;
		$except = [];
		foreach($items as $item){
			$except[] = $this->container_escape($item);
		}
		unset($items, $item);
		$items = \array_diff(\array_keys($this->data), $except);
		foreach($items as $path){
			$this->unset($path);
			$callback($path);
		}
		return true;
	}

	/**
	 * Defragments the data table by re-assigning sequential IDs and updating the sequence.
	 * This can help optimize database size and query performance.
	 *
	 * @return bool True if defragmentation was successful, false if the database is not connected.
	 */
	public function defragment() : bool {
		if(\is_null($this->db)) return false;
		$id = 1;
		$offset = 0;
		$this->db->beginTransaction();
		do {
			$stmt = $this->db->prepare("SELECT `id` FROM `$this->table_data` ORDER BY `id` ASC LIMIT 50000 OFFSET $offset");
			$stmt->execute();
			$items = $stmt->fetchAll(PDO::FETCH_COLUMN);
			foreach($items as $item){
				if($id != $item){
					$update = $this->db->prepare("UPDATE `$this->table_data` SET `id` = :new_id WHERE `id` = :old_id");
					$update->execute([
						':new_id' => $id,
						':old_id' => $item,
					]);
				}
				$id++;
			}
			$offset += \count($items);
		} while(\count($items) > 0);
		$this->db->commit();
		$stmt = $this->db->query("SELECT MAX(`id`) as `max_id` FROM `$this->table_data`");
		$max_id = $stmt->fetch(PDO::FETCH_ASSOC)['max_id'] ?? 0;
		$next_id = $max_id + 1;
		$this->db->exec("UPDATE `sqlite_sequence` SET `seq` = $next_id WHERE `name` = '$this->table_data'");
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
		$current_size = \filesize($this->database);
		if($current_size < $this->allocation){
			$empty_space = $this->allocation - $current_size;
			$file = \fopen($this->database, 'r+');
			\fseek($file, $empty_space - 1, SEEK_END);
			\fwrite($file, "\0");
			\fclose($file);
		}
	}

	/**
	 * Escapes a string for safe use in SQL queries.
	 * Note: This is a basic escaping function and might not cover all edge cases.
	 * It's generally recommended to use prepared statements (PDO::prepare) for safer queries.
	 *
	 * @param mixed $string The string to escape.
	 * @return string The escaped string.
	 */
	public function escape(mixed $string) : string {
		$string = \strval($string ?? '');
		if(empty($string)) return '';
		return \str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"], ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $string);
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
		if(\is_null($this->db)) return -1;
		if($migrate) $this->migrate();
		if(!$this->table_exists($table)) return 0;
		$stmt = $this->db->prepare("SELECT `version` FROM `$this->table_version` WHERE `table_name` = :table");
		$stmt->bindValue(':table', $table, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_OBJ);
		return $row ? \intval($row->version) : 0;
	}

	/**
	 * Sets or updates the version of a given database table.
	 *
	 * @param string $table The name of the table whose version is to be set.
	 * @param int $version The new version number.
	 * @return bool True if operation was successful, false otherwise.
	 */
	public function set_version(string $table, int $version) : bool {
		if(\is_null($this->db)) return false;
		if($version == 1){
			$insert = $this->db->prepare("INSERT INTO `$this->table_version` (`table_name`, `version`) VALUES (:table, :version)");
			$insert->execute([
				':table' => $table,
				':version' => $version,
			]);
		} else {
			$update = $this->db->prepare("UPDATE `$this->table_version` SET `version` = :version WHERE `table_name` = :table");
			$update->execute([
				':version' => $version,
				':table' => $table,
			]);
		}
		return true;
	}

	/**
	 * Retrieves a configuration value by its name.
	 *
	 * @param string $name The name of the configuration setting.
	 * @param string|null $default The default value to return if the configuration name is not found. Defaults to null.
	 * @return string|null The retrieved configuration value, or the default value if not found.
	 */
	public function get_value(string $name, ?string $default = null) : ?string {
		if(\is_null($this->db)) return null;
		$stmt = $this->db->prepare("SELECT `value` FROM `$this->table_config` WHERE `name` = :name LIMIT 1");
		$stmt->bindValue(':name', $name, PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_OBJ);
		return $row ? $row->value : $default;
	}

	/**
	 * Sets or updates a configuration value.
	 *
	 * If the configuration name does not exist, it will be inserted. Otherwise, it will be updated.
	 *
	 * @param string $name The name of the configuration setting.
	 * @param string $value The value to set for the configuration setting.
	 * @return bool True if operation was successful, false otherwise.
	 */
	public function set_value(string $name, string $value) : bool {
		if(\is_null($this->db)) return false;
		$value = $this->escape($value);
		if(\is_null($this->get_value($name))){
			$insert = $this->db->prepare("INSERT INTO `$this->table_config` (`name`, `value`) VALUES (:name, :value)");
			$insert->execute([
				':name' => $name,
				':value' => $value,
			]);
		} else {
			$update = $this->db->prepare("UPDATE `$this->table_config` SET `value` = :value WHERE `name` = :name");
			$update->execute([
				':name' => $name,
				':value' => $value,
			]);
		}
		return true;
	}

	/**
	 * Get volume id
	 * @return string|null The volume id or null if not set.
	 */
	public function get_volume_id() : ?string {
		return $this->volume_id;
	}

	/**
	 * Get volume name
	 * @return string|null The volume name or null if not set.
	 */
	public function get_volume_name() : ?string {
		return $this->volume_name;
	}

	/**
	 * Get volume group
	 * @return string|null The volume group or null if not set.
	 */
	public function get_volume_group() : ?string {
		return $this->volume_group;
	}

	/**
	 * Auto update volume info in data base
	 * @param IniFile $volume_info Volume information
	 * @return bool True if operation was successful, false otherwise.
	 */
	public function update_volume_info(IniFile $volume_info) : bool {
		if(\is_null($this->db)) return false;
		if($volume_info->get('volume_id') !== $this->volume_id){
			$this->volume_id = $volume_info->get('volume_id');
			$this->set_value('VOLUME_ID', $this->volume_id);
		}
		if($volume_info->get('name') !== $this->volume_name){
			$this->volume_name = $volume_info->get('name');
			$this->set_value('VOLUME_NAME', $this->volume_name);
		}
		if($volume_info->get('group_name') !== $this->volume_group){
			$this->volume_group = $volume_info->get('group_name');
			$this->set_value('VOLUME_GROUP', $this->volume_group);
		}
		return true;
	}

	/**
	 * Get volume usage size in bytes
	 * @return int Volume size in bytes
	 */
	public function get_volume_size() : int {
		if(\is_null($this->db)) return 0;
		$result = $this->db->query("SELECT SUM(`size`) AS `size` FROM `$this->table_data`", PDO::FETCH_OBJ);
		return $result->fetch()->size ?? 0;
	}

}

?>