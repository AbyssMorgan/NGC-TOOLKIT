<?php

/* AVE-PHP v2.2.0 */

declare(strict_types=1);

namespace AveCore;

use PDO;
use PDOException;
use PDOStatement;

class MySQL {

	public ?PDO $db;

	function __construct(){

	}
	
	public function connect(string $host, string $user, string $password, string $dbname, int $port = 3306) : bool {
		$options = [
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION SQL_BIG_SELECTS=1;SET NAMES utf8mb4;',
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];
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

	public function disconnect() : void {
		$this->db = null;
	}

	public function get_connection() : ?PDO {
		return $this->db;
	}

	public function get_data_base() : ?string {
		$sth = $this->query("SELECT DATABASE() as `name`;");
		$result = $sth->fetch(PDO::FETCH_OBJ);
		return $result->name ?? null;
	}

	public function escape(mixed $string) : string {
		$string = strval($string) ?? '';
		if(empty($string)) return '';
		return str_replace(['\\', "\0", "\n", "\r", "'", '"', "\x1a"], ['\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'], $string);
	}

	public function query(string $query, ?int $fetchMode = null) : PDOStatement|false {
		return $this->db->query($query, $fetchMode);
	}

	public function results_to_string(array $results, string $separator = '|') : string {
		$data = " ".implode($separator, array_keys($results[0]))."\r\n";
		foreach($results as $result){
			$data .= " ".implode($separator, $result)."\r\n";
		}
		return $data;
	}

}

?>