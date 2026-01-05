<?php

/**
 * NGC-TOOLKIT v2.8.0 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Core;

use PDO;
use PDOException;
use BadMethodCallException;
use Pdo\Mysql as PdoMySQL;

/**
 * A wrapper class for PDO to simplify database interactions.
 * @method PDOStatement prepare(string $query, array $options = [])
 * @method PDOStatement query(string $query, ?int $fetch_mode = null, mixed ...$fetch_mode_args)
 * @method int exec(string $statement)
 * @method string lastInsertId(?string $name = null)
 * @method bool beginTransaction()
 * @method bool commit()
 * @method bool rollBack()
 * @method bool inTransaction()
 * @method int errorCode()
 * @method array errorInfo()
 * @method mixed getAttribute(int $attribute)
 * @method bool setAttribute(int $attribute, mixed $value)
 * @method string quote(string $string, int $type = PDO::PARAM_STR)
 * @method void __wakeup()
 */
class MySQL {
	
	/**
	 * The PDO database connection instance.
	 * @var PDO|null
	 */
	public ?PDO $db;

	/**
	 * MySQL constructor.
	 */
	public function __construct(){
		
	}

	/**
	 * Magic method to catch calls to undefined methods and forward them to the PDO instance.
	 *
	 * @param string $name The name of the method being called.
	 * @param array $arguments The arguments passed to the method.
	 * @return mixed The result of the PDO method call.
	 * @throws BadMethodCallException If the method does not exist in MySQL wrapper or PDO.
	 */
	public function __call(string $name, array $arguments) : mixed {
		if($this->db && \method_exists($this->db, $name)){
			return $this->db->$name(...$arguments);
		}
		throw new BadMethodCallException("Method {$name} does not exist in MySQL wrapper or PDO.");
	}

	/**
	 * Establishes a connection to the MySQL database.
	 *
	 * @param string $host The database host.
	 * @param string $user The database username.
	 * @param string $password The database password.
	 * @param string $dbname The database name. Use "*" to connect without selecting a specific database initially.
	 * @param int $port The database port (default: 3306).
	 * @param array $options A key=>value array of driver-specific connection options.
	 * @return bool True on successful connection, false otherwise.
	 */
	public function connect(string $host, string $user, string $password, string $dbname, int $port = 3306, array $options = []) : bool {
		$defaults = [
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];
		if(PHP_VERSION_ID >= 80400 && class_exists('Pdo\\Mysql')){
			$defaults[PdoMySQL::ATTR_INIT_COMMAND] = 'SET SESSION SQL_BIG_SELECTS = 1; SET NAMES utf8mb4;';
		} else {
			$defaults[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET SESSION SQL_BIG_SELECTS = 1; SET NAMES utf8mb4;';
		}
		$options = \array_merge($defaults, $options);
		try {
			$this->db = new PDO("mysql:".($dbname == "*" ? "" : "dbname=$dbname;")."host=$host;port=$port;charset=utf8mb4", $user, $password, $options);
		}
		catch(PDOException $e){
			echo " Failed to connect:\r\n";
			echo " ".$e->getMessage()."\r\n";
			return false;
		}
		return true;
	}

	/**
	 * Disconnects from the database by setting the PDO instance to null.
	 */
	public function disconnect() : void {
		$this->db = null;
	}

	/**
	 * Returns the current PDO connection instance.
	 *
	 * @return PDO|null The PDO instance or null if not connected.
	 */
	public function get_connection() : ?PDO {
		return $this->db;
	}

	/**
	 * Retrieves the name of the current database.
	 *
	 * @return string|null The name of the current database, or null if not set or an error occurs.
	 */
	public function get_data_base() : ?string {
		$stmt_select = $this->prepare("SELECT DATABASE() AS `name`");
		$stmt_select->execute();
		if($row = $stmt_select->fetch(PDO::FETCH_OBJ)){
			return $row->name;
		} else {
			return null;
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
	 * Converts an array of query results into a formatted string.
	 *
	 * @param array $results An array of associative arrays, where each inner array represents a row.
	 * @param string $separator The separator to use between column values (default: '|').
	 * @return string The formatted string representation of the results.
	 */
	public function results_to_string(array $results, string $separator = '|') : string {
		$data = " ".\implode($separator, \array_keys($results[0]))."\r\n";
		foreach($results as $result){
			$data .= " ".\implode($separator, $result)."\r\n";
		}
		return $data;
	}

	/**
	 * Gets all items from the query
	 * @param string $query
	 * @param mixed $params
	 * @param int $mode
	 * @return array
	 */
	public function get(string $query, ?array $params = null, int $mode = PDO::FETCH_OBJ) : array {
		$stmt = $this->prepare($query);
		$stmt->execute($params);
		return $stmt->fetchAll($mode);
	}

	/**
	 * Get first element from query
	 * @param string $query
	 * @param mixed $params
	 * @param int $mode
	 * @return mixed
	 */
	public function first(string $query, ?array $params = null, int $mode = PDO::FETCH_OBJ) : mixed {
		$stmt = $this->prepare($query);
		$stmt->execute($params);
		return $stmt->fetch($mode);
	}

	/**
	 * Executes a query with values
	 * @param string $query
	 * @param mixed $params
	 * @return bool
	 */
	public function execute_query(string $query, ?array $params = null) : bool {
		$stmt = $this->prepare($query);
		return $stmt->execute($params);
	}

}

?>