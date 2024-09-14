<?php

declare(strict_types=1);

namespace NGC\Tools;

use Toolkit;
use PDO;
use PDOException;
use NGC\Core\IniFile;
use NGC\Core\Request;
use NGC\Core\MySQL;
use NGC\Services\DataBaseBackup;

class MySQLTools {

	private string $name = "MySQL Tools";
	private array $params = [];
	private string $action;
	private string $path;
	private Toolkit $core;
	private array $select_label = [];

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
		$this->path = $this->core->get_path("{$this->core->app_data}/MySQL");
		$this->select_label = [];
	}

	public function help() : void {
		$this->core->print_help([
			' Actions:',
			' 0  - Configure connection',
			' 1  - Remove connection',
			' 2  - Open config folder',
			' 3  - Show connections',
			' 4  - Make backup',
			' 5  - Clone DB1 to DB2 (overwrite)',
			' 6  - Open backup folder',
			' 7  - MySQL Console',
			' 8  - Backup selected: Table structure',
			' 9  - Backup selected: Table data',
			' 10 - Backup selected: Views',
			' 11 - Backup selected: Functions',
			' 12 - Backup selected: Procedures',
			' 13 - Backup selected: Events',
			' 14 - Backup selected: Triggers',
			' 15 - Fetch data base info',
			' 16 - Compare data base info',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_configure_connection();
			case '1': return $this->tool_remove_connection();
			case '2': return $this->tool_open_config_folder();
			case '3': return $this->tool_show_connections();
			case '4': return $this->tool_make_backup();
			case '5': return $this->tool_make_clone();
			case '6': return $this->tool_open_backup_folder();
			case '7': return $this->tool_mysql_console();
			case '8': return $this->tool_backup_selected_tables_structure();
			case '9': return $this->tool_backup_selected_tables_data();
			case '10': return $this->tool_backup_selected_views();
			case '11': return $this->tool_backup_selected_functions();
			case '12': return $this->tool_backup_selected_procedures();
			case '13': return $this->tool_backup_selected_events();
			case '14': return $this->tool_backup_selected_triggers();
			case '15': return $this->tool_fetch_data_base_info();
			case '16': return $this->tool_compare_data_base_info();
		}
		return false;
	}

	public function get_select_label() : void {
		$this->select_label = [];
		$i = 0;
		$files = scandir($this->path);
		foreach($files as $file){
			if($file == '..' || $file == '.') continue;
			$label = pathinfo($file, PATHINFO_FILENAME);
			$this->select_label[$i] = $label;
			$i++;
		}
		if(!empty($this->select_label)){
			$this->core->echo(" Labels: ");
			foreach($this->select_label as $i => $label){
				$this->core->echo(" $i - $label");
			}
			$this->core->echo();
		}
	}

	public function get_config_path(string $label) : string {
		return $this->core->get_path("$this->path/$label.ini");
	}

	public function get_config(string $label) : IniFile {
		$config = new IniFile($this->get_config_path($label), true);
		$this->check_config($config);
		return $config;
	}

	public function select_data_base(PDO $connection, ?DataBaseBackup $backup = null) : bool {
		$options = [];
		$i = 0;
		$this->core->echo();
		$this->core->echo(" Data bases: ");
		$items = $connection->query("SHOW DATABASES;", PDO::FETCH_OBJ);
		foreach($items as $item){
			$options[$i] = $item->Database;
			$this->core->echo(" $i - $item->Database");
			$i++;
		}
		$this->core->echo();
		select_database:
		$database = $this->core->get_input(" DataBase: ");
		if($database == '#') return false;
		if(!isset($options[$database])) goto select_database;
		$connection->query("USE ".$options[$database]);
		if(!is_null($backup)) $backup->set_data_base($options[$database]);
		return true;
	}

	public function get_data_base(PDO $connection) : ?string {
		$sth = $connection->query("SELECT DATABASE() as `name`;");
		$result = $sth->fetch(PDO::FETCH_OBJ);
		return $result->name ?? null;
	}

	public function tool_configure_connection() : bool {
		$this->core->clear();
		$this->core->set_subtool("Configure connection");

		$this->core->print_help([
			' Allowed characters: A-Z a-z 0-9 _ -',
			' Label length 3 - 32',
		]);

		set_label:
		$label = $this->core->get_input(" Label: ");
		if($label == '#') return false;
		if(!$this->core->is_valid_label($label)){
			$this->core->echo(" Invalid label");
			goto set_label;
		}

		if(file_exists($this->get_config_path($label))){
			$this->core->echo(" Label \"$label\" already in use");
			if(!$this->core->get_confirm(" Overwrite (Y/N): ")) goto set_label;
		}

		$this->core->clear();
		$this->core->print_help([
			" Setup label: \"$label\"",
		]);

		set_output:
		$line = $this->core->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		set_db_connection:
		$db['host'] = $this->core->get_input(" DB Host: ");
		if($db['host'] == '#') return false;
		$db['port'] = $this->core->get_input_integer(" DB Port (Default 3306): ", 0, 65353);
		if(!$db['port']) return false;
		$db['name'] = $this->core->get_input(" DB Name (Type * for none): ");
		if($db['name'] == '#') return false;
		$db['user'] = $this->core->get_input(" DB User: ");
		if($db['user'] == '#') return false;
		$db['password'] = $this->core->get_input_no_trim(" DB Pass: ");
		if($db['password'] == '#') return false;

		try_login_same:
		$options = [
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION SQL_BIG_SELECTS=1;',
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];
		try {
			$this->core->echo(" Connecting to: ".$db['host'].":".$db['port']."@".$db['user']);
			$conn = new PDO("mysql:".($db['name'] == "*" ? "" : "dbname=".$db['name'].";")."host=".$db['host'].";port=".$db['port'], $db['user'], $db['password'], $options);
		}
		catch(PDOException $e){
			$this->core->echo(" Failed to connect:");
			$this->core->echo(" ".$e->getMessage());
			if($this->core->get_confirm(" Retry (Y/N): ")) goto try_login_same;
			goto set_db_connection;
		}
		$conn = null;

		$this->core->clear();
		$this->core->print_help([
			" Connection test completed successfully.",
			" Set additional config for label: \"$label\"",
		]);

		$backup['structure'] = $this->core->get_confirm(" Backup structure (Y/N): ");
		$backup['data'] = $this->core->get_confirm(" Backup data (Y/N): ");
		$backup['compress'] = $this->core->get_confirm(" Compress after backup (Y/N): ");
		$backup['lock_tables'] = $this->core->get_confirm(" Lock tables during background backup (Y/N): ");

		$ini = $this->get_config($label);
		$ini->update([
			'DB_HOST' => $db['host'],
			'DB_USER' => $db['user'],
			'DB_PASSWORD' => $db['password'],
			'DB_NAME' => $db['name'],
			'DB_PORT' => intval($db['port']),
			'FOLDER_DATE_FORMAT' => "Y-m-d_His",
			'BACKUP_QUERY_LIMIT' => 50000,
			'BACKUP_INSERT_LIMIT' => 100,
			'BACKUP_TYPE_STRUCTURE' => $backup['structure'],
			'BACKUP_TYPE_DATA' => $backup['data'],
			'BACKUP_COMPRESS' => $backup['compress'],
			'BACKUP_PATH' => $output,
			'BACKUP_LOCK_TABLES' => $backup['lock_tables'],
		], true);

		$this->core->clear();
		$this->core->pause(" Setup connection for \"$label\" done, press any key to back to menu");

		return false;
	}

	public function tool_remove_connection() : bool {
		$this->core->clear();
		$this->core->set_subtool("Remove connection");

		$this->get_select_label();
		set_label:
		$label = $this->core->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->core->is_valid_label($label)){
			$this->core->echo(" Invalid label");
			goto set_label;
		}

		$path = $this->get_config_path($label);
		if(!file_exists($path)){
			$this->core->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$this->core->delete($path);

		return false;
	}

	public function tool_open_config_folder() : bool {
		$this->core->clear();
		$this->core->set_subtool("Open config folder");
		$this->core->open_file($this->path, '');
		return false;
	}

	public function tool_show_connections() : bool {
		$this->core->clear();
		$this->core->set_subtool("Show connections");

		$this->core->echo(" Connections:");
		$cnt = 0;
		$files = $this->core->get_files($this->path, ['ini']);
		foreach($files as $file){
			$ini = new IniFile($file);
			if($ini->is_valid() && $ini->is_set('DB_HOST')){
				$label = pathinfo($file, PATHINFO_FILENAME);
				$this->core->echo(" $label".str_repeat(" ",32-strlen($label))." ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
				$cnt++;
			}
		}

		if($cnt == 0){
			$this->core->echo(" No connections found");
		}

		$this->core->pause("\r\n Press any key to back to menu");
		return false;
	}

	public function tool_make_backup() : bool {
		$this->core->clear();
		$this->core->set_subtool("Make backup");

		$this->get_select_label();
		set_label:
		$label = $this->core->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->core->is_valid_label($label)){
			$this->core->echo(" Invalid label");
			goto set_label;
		}

		if(!file_exists($this->get_config_path($label))){
			$this->core->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$ini = $this->get_config($label);
		if($ini->get('BACKUP_ADD_LABEL_TO_PATH')){
			$path = $this->core->get_path($ini->get('BACKUP_PATH')."/$label");
		} else {
			$path = $this->core->get_path($ini->get('BACKUP_PATH'));
		}
		$callback = $ini->get('BACKUP_CURL_CALLBACK');
		$request = new Request();

		if(!$this->core->is_valid_device($path)){
			$this->core->echo(" Output device \"$path\" is not available");
			goto set_label;
		}

		if(!is_null($callback)){
			if(!$this->core->get_confirm(" Toggle website into maintenance (Y/N): ")){
				$callback = null;
			}
		}

		$lock_tables = $this->core->get_confirm(" Lock tables during backup (Y/N): ");

		$this->core->write_log("Initialize backup for \"$label\"");
		$this->core->echo(" Initialize backup service");
		$backup = new DataBaseBackup($path, $ini->get('BACKUP_QUERY_LIMIT'), $ini->get('BACKUP_INSERT_LIMIT'), $ini->get('FOLDER_DATE_FORMAT'));
		$backup->toggle_lock_tables($lock_tables);

		if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_START'], true);
		$this->core->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$backup->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))) goto set_label;
		if($ini->get('DB_NAME') == "*" && !$this->select_data_base($backup->get_source(), $backup)) return false;
		$this->core->echo(" Create backup");

		$items = $backup->get_tables();
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('Table', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Create backup for table $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "table:$item"], true);
			if($ini->get('BACKUP_TYPE_STRUCTURE')){
				$errors_structure = $backup->backup_table_structure($item);
			}
			if($ini->get('BACKUP_TYPE_DATA')){
				$errors_data = $backup->backup_table_data($item);
			}
			$errors = array_merge($errors_structure ?? [], $errors_data ?? []);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "table:$item"], true);
			}
			$this->core->set_progress_ex('Table', $progress, $total);
		}

		try {
			$items = $backup->get_views();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get views");
		}
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('View', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Create backup for view $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "view:$item"], true);
			$errors = $backup->backup_view($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "view:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "view:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "view:$item"], true);
			}
			$this->core->set_progress_ex('View', $progress, $total);
		}

		try {
			$items = $backup->get_functions();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get functions");
		}
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('Function', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Create backup for function $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "function:$item"], true);
			$errors = $backup->backup_function($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "function:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "function:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "function:$item"], true);
			}
			$this->core->set_progress_ex('Function', $progress, $total);
		}

		try {
			$items = $backup->get_procedures();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get procedures");
		}
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('Procedure', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Create backup for procedure $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "procedure:$item"], true);
			$errors = $backup->backup_procedure($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "procedure:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "procedure:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "procedure:$item"], true);
			}
			$this->core->set_progress_ex('Procedure', $progress, $total);
		}

		try {
			$items = $backup->get_events();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get events");
		}
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('Event', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Create backup for event $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "event:$item"], true);
			$errors = $backup->backup_event($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "event:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "event:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "event:$item"], true);
			}
			$this->core->set_progress_ex('Event', $progress, $total);
		}

		try {
			$items = $backup->get_triggers();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get triggers");
		}
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('Trigger', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Create backup for trigger $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "trigger:$item"], true);
			$errors = $backup->backup_trigger($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "trigger:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "trigger:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "trigger:$item"], true);
			}
			$this->core->set_progress_ex('Trigger', $progress, $total);
		}

		$this->core->echo();
		$this->core->write_log("Finish backup for \"$label\"");
		if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'BACKUP_END'], true);
		$backup->disconnect();

		$output = $backup->get_output();
		if($ini->get('BACKUP_COMPRESS', false)){
			$this->compress($callback, $output, $ini->get('BACKUP_PATH'), $request);
		} else {
			$this->core->open_file($output);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Backup for \"$label\" done, press any key to back to menu");
		return false;
	}

	public function tool_make_clone() : bool {
		$this->core->set_subtool("Make clone");

		reset_connection:
		$this->core->clear();
		$this->get_select_label();
		set_label_source:
		$source = $this->core->get_input(" Source label / ID: ");
		if($source == '#') return false;
		if(isset($this->select_label[$source])) $source = $this->select_label[$source];
		if(!$this->core->is_valid_label($source)){
			$this->core->echo(" Invalid label");
			goto set_label_source;
		}

		if(!file_exists($this->get_config_path($source))){
			$this->core->echo(" Source label \"$source\" not exists");
			goto set_label_source;
		}

		$ini_source = $this->get_config($source);
		if($ini_source->get('BACKUP_ADD_LABEL_TO_PATH')){
			$path = $this->core->get_path($ini_source->get('BACKUP_PATH')."/$source");
		} else {
			$path = $this->core->get_path($ini_source->get('BACKUP_PATH'));
		}
		$callback = $ini_source->get('BACKUP_CURL_CALLBACK');
		$request = new Request();

		if(!is_null($callback)){
			if(!$this->core->get_confirm(" Toggle website into maintenance (Y/N): ")){
				$callback = null;
			}
		}

		$lock_tables = $this->core->get_confirm(" Lock tables during clone (Y/N): ");

		$this->core->write_log("Initialize backup for \"$source\"");
		$this->core->echo(" Initialize backup service");
		$backup = new DataBaseBackup($path, $ini_source->get('BACKUP_QUERY_LIMIT'), $ini_source->get('BACKUP_INSERT_LIMIT'), $ini_source->get('FOLDER_DATE_FORMAT'));
		$backup->toggle_lock_tables($lock_tables);

		$this->core->echo(" Connecting to: ".$ini_source->get('DB_HOST').":".$ini_source->get('DB_PORT')."@".$ini_source->get('DB_USER'));
		if(!$backup->connect($ini_source->get('DB_HOST'), $ini_source->get('DB_USER'), $ini_source->get('DB_PASSWORD'), $ini_source->get('DB_NAME'), $ini_source->get('DB_PORT'))) goto set_label_source;
		if($ini_source->get('DB_NAME') == "*" && !$this->select_data_base($backup->get_source(), $backup)) return false;

		$this->core->clear();
		$this->get_select_label();
		set_label_destination:
		$destination = $this->core->get_input(" Destination label: ");
		if($destination == '#') return false;
		if(isset($this->select_label[$destination])) $destination = $this->select_label[$destination];
		if(!$this->core->is_valid_label($destination)){
			$this->core->echo(" Invalid label");
			goto set_label_destination;
		}

		if(!file_exists($this->get_config_path($destination))){
			$this->core->echo(" Destination label \"$destination\" not exists");
			goto set_label_destination;
		}

		$ini_dest = $this->get_config($destination);

		$this->core->echo(" Connecting to: ".$ini_dest->get('DB_HOST').":".$ini_dest->get('DB_PORT')."@".$ini_dest->get('DB_USER'));
		if(!$backup->connect_destination($ini_dest->get('DB_HOST'), $ini_dest->get('DB_USER'), $ini_dest->get('DB_PASSWORD'), $ini_dest->get('DB_NAME'), $ini_dest->get('DB_PORT'))) goto set_label_destination;
		if($ini_dest->get('DB_NAME') == "*" && !$this->select_data_base($backup->get_destination())) return false;

		$dbname_source = $this->get_data_base($backup->get_source());
		$dbname_destination = $this->get_data_base($backup->get_destination());

		if($ini_source->get('DB_HOST') == $ini_dest->get('DB_HOST') && $ini_source->get('DB_USER') == $ini_dest->get('DB_USER') && $dbname_source == $dbname_destination && $ini_source->get('DB_PORT') == $ini_dest->get('DB_PORT')){
			$backup->disconnect();
			$backup->disconnect_destination();
			$this->core->pause(" Destination database `$dbname_destination` is same as source database `$dbname_source`, press any key to reset connection");
			goto reset_connection;
		}

		$this->core->clear();
		if(!$this->core->get_confirm(" Clone database `$dbname_source` to `$dbname_destination` (Y/N): ")){
			$this->core->pause(" Clone `$dbname_source` to `$dbname_destination` aborted, press any key to back to menu");
			return false;
		}

		if(!$backup->is_destination_empty()){
			if(!$this->core->get_confirm(" Output database is not empty, continue (Y/N): ")){
				$this->core->pause(" Clone `$dbname_source` to `$dbname_destination` aborted, press any key to back to menu");
				return false;
			}
		}

		$this->core->echo(" Clone `$dbname_source` to `$dbname_destination`");
		if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_START'], true);

		$items = $backup->get_tables();
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('Table Structure', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Clone table Structure $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "table:$item"], true);
			$errors = $backup->clone_table_structure($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "table:$item"], true);
			}
			$this->core->set_progress_ex('Table Structure', $progress, $total);
		}

		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('Table Data', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Clone table data $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "table:$item"], true);
			$errors = $backup->clone_table_data($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "table:$item"], true);
			}
			$this->core->set_progress_ex('Table Data', $progress, $total);
		}

		try {
			$items = $backup->get_views();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get views");
		}
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('View', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Clone view $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "view:$item"], true);
			$errors = $backup->clone_view($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "view:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "view:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "view:$item"], true);
			}
			$this->core->set_progress_ex('View', $progress, $total);
		}

		try {
			$items = $backup->get_functions();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get functions");
		}
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('Function', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Clone function $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "function:$item"], true);
			$errors = $backup->clone_function($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "function:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "function:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "function:$item"], true);
			}
			$this->core->set_progress_ex('Function', $progress, $total);
		}

		try {
			$items = $backup->get_procedures();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get procedures");
		}
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('Procedure', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Clone procedure $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "procedure:$item"], true);
			$errors = $backup->clone_procedure($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "procedure:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "procedure:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "procedure:$item"], true);
			}
			$this->core->set_progress_ex('Procedure', $progress, $total);
		}

		try {
			$items = $backup->get_events();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get events");
		}
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('Event', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Clone event $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "event:$item"], true);
			$errors = $backup->clone_event($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "event:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "event:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "event:$item"], true);
			}
			$this->core->set_progress_ex('Event', $progress, $total);
		}

		try {
			$items = $backup->get_triggers();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get triggers");
		}
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex('Trigger', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->core->write_log("Clone trigger $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "trigger:$item"], true);
			$errors = $backup->clone_trigger($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "trigger:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "trigger:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "trigger:$item"], true);
			}
			$this->core->set_progress_ex('Trigger', $progress, $total);
		}

		$this->core->echo();
		$this->core->write_log("Finish clone `$dbname_source` to `$dbname_destination`");
		if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_END'], true);
		$backup->disconnect();
		$backup->disconnect_destination();

		$this->core->open_logs(true);
		$this->core->pause(" Clone for `$dbname_source` to `$dbname_destination` done, press any key to back to menu");
		return false;
	}

	public function tool_make_backup_cmd(string $label, ?string $dbname = null) : bool {
		if(!$this->core->is_valid_label($label)){
			$this->core->echo(" Invalid label \"$label\"");
			return false;
		}

		if(!file_exists($this->get_config_path($label))){
			$this->core->echo(" Label \"$label\" not exists");
			return false;
		}

		$ini = $this->get_config($label);
		if($ini->get('BACKUP_ADD_LABEL_TO_PATH')){
			$path = $this->core->get_path($ini->get('BACKUP_PATH')."/$label");
		} else {
			$path = $this->core->get_path($ini->get('BACKUP_PATH'));
		}
		$callback = $ini->get('BACKUP_CURL_CALLBACK');
		$request = new Request();

		if(!$this->core->is_valid_device($path)){
			$this->core->echo(" Output device \"$path\" is not available");
			return false;
		}

		if($ini->get('DB_NAME') == "*" && is_null($dbname)){
			$this->core->echo(" No data base selected");
			return false;
		}

		$this->core->write_log("Initialize backup for \"$label\"");
		$this->core->echo(" Initialize backup service");
		$backup = new DataBaseBackup($path, $ini->get('BACKUP_QUERY_LIMIT'), $ini->get('BACKUP_INSERT_LIMIT'), $ini->get('FOLDER_DATE_FORMAT'));
		$backup->toggle_lock_tables($ini->get('BACKUP_LOCK_TABLES'));

		if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_START'], true);
		$this->core->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$backup->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))){
			$this->core->echo(" Failed connect to database");
			return false;
		}
		if($ini->get('DB_NAME') == "*") $backup->get_source()->query("USE $dbname");
		
		$this->core->echo(" Create backup");

		$items = $backup->get_tables();
		foreach($items as $item){
			$this->core->write_log("Create backup for table $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "table:$item"], true);
			if($ini->get('BACKUP_TYPE_STRUCTURE')){
				$errors_structure = $backup->backup_table_structure($item);
			}
			if($ini->get('BACKUP_TYPE_DATA')){
				$errors_data = $backup->backup_table_data($item);
			}
			$errors = array_merge($errors_structure ?? [], $errors_data ?? []);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "table:$item"], true);
			}
		}

		try {
			$items = $backup->get_views();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get views");
		}
		foreach($items as $item){
			$this->core->write_log("Create backup for view $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "view:$item"], true);
			$errors = $backup->backup_view($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "view:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "view:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "view:$item"], true);
			}
		}

		try {
			$items = $backup->get_functions();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get functions");
		}
		foreach($items as $item){
			$this->core->write_log("Create backup for function $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "function:$item"], true);
			$errors = $backup->backup_function($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "function:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "function:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "function:$item"], true);
			}
		}

		try {
			$items = $backup->get_procedures();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get procedures");
		}
		foreach($items as $item){
			$this->core->write_log("Create backup for procedure $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "procedure:$item"], true);
			$errors = $backup->backup_procedure($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "procedure:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "procedure:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "procedure:$item"], true);
			}
		}

		try {
			$items = $backup->get_events();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get events");
		}
		foreach($items as $item){
			$this->core->write_log("Create backup for event $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "event:$item"], true);
			$errors = $backup->backup_event($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "event:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "event:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "event:$item"], true);
			}
		}

		try {
			$items = $backup->get_triggers();
		}
		catch(PDOException $e){
			$items = [];
			$this->core->write_error("Access denied for get triggers");
		}
		foreach($items as $item){
			$this->core->write_log("Create backup for trigger $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "trigger:$item"], true);
			$errors = $backup->backup_trigger($item);
			if(!empty($errors)){
				$this->core->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "trigger:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "trigger:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "trigger:$item"], true);
			}
		}

		$this->core->echo();
		$this->core->write_log("Finish backup for \"$label\"");
		if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'BACKUP_END'], true);
		$backup->disconnect();

		$output = $backup->get_output();
		if($ini->get('BACKUP_COMPRESS', false)){
			$this->compress($callback, $output, $ini->get('BACKUP_PATH'), $request);
		} else {
			$this->core->open_file($output);
		}

		$this->core->echo(" Backup for \"$label\" done");
		$this->core->write_log(" Backup for \"$label\" done");
		return true;
	}

	public function tool_open_backup_folder() : bool {
		$this->core->clear();
		$this->core->set_subtool("Open backup folder");

		$this->get_select_label();
		set_label:
		$label = $this->core->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->core->is_valid_label($label)){
			$this->core->echo(" Invalid label");
			goto set_label;
		}

		$path = $this->get_config_path($label);
		if(!file_exists($path)){
			$this->core->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$config = $this->get_config($label);
		$this->core->open_file($this->core->get_path($config->get('BACKUP_PATH')."/$label"), '');

		return false;
	}

	public function tool_mysql_console() : bool {
		$this->core->clear();
		$this->core->set_subtool("MySQL console");

		$this->get_select_label();
		set_label:
		$label = $this->core->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->core->is_valid_label($label)){
			$this->core->echo(" Invalid label");
			goto set_label;
		}

		if(!file_exists($this->get_config_path($label))){
			$this->core->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$ini = $this->get_config($label);

		$db = new MySQL();
		$this->core->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$db->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))) goto set_label;
		if($ini->get('DB_NAME') == "*" && !$this->select_data_base($db->get_connection())) return false;

		$save_output = $this->core->get_confirm(" Save query results in data file (Y/N): ");
		if($save_output){
			$this->core->write_data([" Query results for: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'), ""]);
		}

		clear:
		$this->core->clear();
		$this->core->print_help([
			" MySQL Console: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER')." [$label] Save results: ".($save_output ? 'Enabled' : 'Disabled'),
			" Additional commands: ",
			" @exit  - close connection",
			" @clear - clear console",
			" @open  - open data folder",
		]);

		try {
			query:
			if($save_output) $this->core->write_data("");
			$query = $this->core->get_input_no_trim(" MySQL: ");
			$lquery = strtolower($query);
			if($lquery == '@exit'){
				goto close_connection;
			} else if($lquery == '@clear'){
				goto clear;
			} else if($lquery == '@open'){
				$this->core->open_file($this->core->get_path($this->core->config->get('DATA_FOLDER')), '');
				goto query;
			}

			if($save_output) $this->core->write_data([" ".$query, ""]);
			$sth = $db->query($query);
			$results = $sth->fetchAll(PDO::FETCH_ASSOC);
			$last_insert_id = $db->get_connection()->lastInsertId();
			if($last_insert_id){
				$this->core->echo(" Last insert id: $last_insert_id");
				if($save_output) $this->core->write_data(" Last insert id: $last_insert_id");
			} else if(count($results) == 0){
				if(substr($lquery, 0, 6) == 'select' || substr($lquery, 0, 4) == 'show'){
					$this->core->echo(" MySQL returned an empty result");
					if($save_output) $this->core->write_data(" MySQL returned an empty result");
				} else {
					$this->core->echo(" Done");
					if($save_output) $this->core->write_data(" Done");
				}
			} else {
				$results = $db->results_to_string($results, $ini->get('SAVE_RESULTS_SEPARATOR'));
				$this->core->echo($results);
				if($save_output) $this->core->write_data($results);
			}
		}
		catch(PDOException $e){
			$this->core->echo(" ".$e->getMessage());
			if($save_output) $this->core->write_data(" ".$e->getMessage());
		}
		goto query;

		close_connection:
		$db->disconnect();

		$this->core->open_logs(true);
		$this->core->pause(" Connection \"$label\" closed, press any key to back to menu");
		return false;
	}

	public function tool_backup_selected_tables_structure() : bool {
		return $this->backup_selected('Table Structure', false);
	}

	public function tool_backup_selected_tables_data() : bool {
		return $this->backup_selected('Table Data', true);
	}

	public function tool_backup_selected_views() : bool {
		return $this->backup_selected('View', false);
	}

	public function tool_backup_selected_functions() : bool {
		return $this->backup_selected('Function', false);
	}

	public function tool_backup_selected_procedures() : bool {
		return $this->backup_selected('Procedure', false);
	}

	public function tool_backup_selected_events() : bool {
		return $this->backup_selected('Event', false);
	}

	public function tool_backup_selected_triggers() : bool {
		return $this->backup_selected('Trigger', false);
	}

	public function check_config(IniFile $config) : void {
		if(!$config->is_set('BACKUP_ADD_LABEL_TO_PATH')) $config->set('BACKUP_ADD_LABEL_TO_PATH', true);
		if(!$config->is_set('BACKUP_CURL_SEND_ERRORS')) $config->set('BACKUP_CURL_SEND_ERRORS', false);
		if(!$config->is_set('BACKUP_CURL_CALLBACK')) $config->set('BACKUP_CURL_CALLBACK', null);
		if(!$config->is_set('BACKUP_QUERY_LIMIT')) $config->set('BACKUP_QUERY_LIMIT', 50000);
		if(!$config->is_set('BACKUP_INSERT_LIMIT')) $config->set('BACKUP_INSERT_LIMIT', 100);
		if(!$config->is_set('BACKUP_TYPE_STRUCTURE')) $config->set('BACKUP_TYPE_STRUCTURE', true);
		if(!$config->is_set('BACKUP_TYPE_DATA')) $config->set('BACKUP_TYPE_DATA', true);
		if(!$config->is_set('BACKUP_COMPRESS')) $config->set('BACKUP_COMPRESS', true);
		if(!$config->is_set('FOLDER_DATE_FORMAT')) $config->set('FOLDER_DATE_FORMAT', 'Y-m-d_His');
		if(!$config->is_set('SAVE_RESULTS_SEPARATOR')) $config->set('SAVE_RESULTS_SEPARATOR', '|');
		if(!$config->is_set('BACKUP_LOCK_TABLES')) $config->set('BACKUP_LOCK_TABLES', false);
		if($config->is_changed()) $config->save();
	}

	public function compress(?string $callback, string $output, string $backup_path, Request $request) : void {
		if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'COMPRESS_BACKUP_START'], true);
		$this->core->echo(" Compressing backup");
		$this->core->write_log("Compressing backup");
		$sql = $this->core->get_path("$output/*");
		$cl = $this->core->config->get('BACKUP_COMPRESS_LEVEL');
		$at = $this->core->config->get('BACKUP_COMPRESS_TYPE');
		$this->core->exec("7z", "a -mx$cl -t$at \"$output.7z\" \"$sql\"");
		$this->core->echo();
		if(file_exists("$output.7z")){
			if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'COMPRESS_BACKUP_END'], true);
			$this->core->echo(" Compress backup into \"$output.7z\" success");
			$this->core->write_log("Compress backup into \"$output.7z\" success");
			$this->core->rrmdir($output);
			$this->core->open_file($backup_path);
		} else {
			if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'COMPRESS_BACKUP_ERROR'], true);
			$this->core->echo(" Compress backup into \"$output.7z\" fail");
			$this->core->write_log("Compress backup into \"$output.7z\" fail");
			$this->core->open_file($output);
		}
	}

	public function backup_selected(string $type, bool $need_lock) : bool {
		$ftype = explode(" ", $type);
		$ftype = $ftype[0];
		$stype = strtolower($type);
		$type = str_replace(" ", "", $type);
		$this->core->clear();
		$this->core->set_subtool("Backup selected ".$stype);

		$this->get_select_label();
		set_label:
		$label = $this->core->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->core->is_valid_label($label)){
			$this->core->echo(" Invalid label");
			goto set_label;
		}

		if(!file_exists($this->get_config_path($label))){
			$this->core->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$ini = $this->get_config($label);
		if($ini->get('BACKUP_ADD_LABEL_TO_PATH')){
			$path = $this->core->get_path($ini->get('BACKUP_PATH')."/$label");
		} else {
			$path = $this->core->get_path($ini->get('BACKUP_PATH'));
		}
		$callback = $ini->get('BACKUP_CURL_CALLBACK');
		$request = new Request();

		if(!$this->core->is_valid_device($path)){
			$this->core->echo(" Output device \"$path\" is not available");
			goto set_label;
		}

		if(!is_null($callback)){
			if(!$this->core->get_confirm(" Toggle website into maintenance (Y/N): ")){
				$callback = null;
			}
		}

		if($need_lock){
			$lock_tables = $this->core->get_confirm(" Lock tables during backup (Y/N): ");
		} else {
			$lock_tables = false;
		}

		$compress = $this->core->get_confirm(" Compress backup (Y/N): ");

		$this->core->print_help([
			" Type $stype you want to backup, separate with a space",
			" Use double quotes \" for escape name",
		]);
		$line = $this->core->get_input(" Names: ");
		if($line == '#') return false;
		$items = $this->core->get_input_folders($line);

		$this->core->write_log("Initialize backup for \"$label\"");
		$this->core->echo(" Initialize backup service");
		$backup = new DataBaseBackup($path, $ini->get('BACKUP_QUERY_LIMIT'), $ini->get('BACKUP_INSERT_LIMIT'), $ini->get('FOLDER_DATE_FORMAT'));
		$backup->toggle_lock_tables($lock_tables);

		if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_START'], true);
		$this->core->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$backup->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))) goto set_label;
		if($ini->get('DB_NAME') == "*" && !$this->select_data_base($backup->get_source(), $backup)) return false;

		$this->core->echo(" Create backup");
		$func = "get".$ftype."s";
		$items_in_db = $backup->$func();
		$progress = 0;
		$total = count($items);
		$this->core->set_progress_ex($type, $progress, $total);
		foreach($items as $item){
			$progress++;
			if(in_array($item, $items_in_db)){
				$this->core->write_log("Create backup for $stype $item");
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "$stype:$item"], true);
				$func = "backup".$type;
				$errors = $backup->$func($item);
				if(!empty($errors)){
					$this->core->write_error($errors);
					if($ini->get('BACKUP_CURL_SEND_ERRORS')){
						$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "$stype:$item", 'errors' => $errors];
					} else {
						$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "$stype:$item", 'errors' => ['Error reporting is disabled']];
					}
					if(!is_null($callback)) $request->get($callback, $cdata, true);
				} else {
					if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "$stype:$item"], true);
				}
			} else {
				$this->core->echo(" $type: $item not exists, skipping");
				$this->core->write_error("Create backup for $stype $item failed, $stype not exists");
			}
			$this->core->set_progress_ex($type, $progress, $total);
		}
		$this->core->echo();
		$this->core->write_log("Finish backup for \"$label\"");
		if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'BACKUP_END'], true);
		$backup->disconnect();

		$output = $backup->get_output();
		if($compress){
			$this->compress($callback, $output, $ini->get('BACKUP_PATH'), $request);
		} else {
			$this->core->open_file($output);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Backup for \"$label\" done, press any key to back to menu");
		return false;
	}

	public function tool_fetch_data_base_info() : bool {
		$this->core->clear();
		$this->core->set_subtool("Fetch data base info");

		$this->get_select_label();
		set_label:
		$label = $this->core->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->core->is_valid_label($label)){
			$this->core->echo(" Invalid label");
			goto set_label;
		}

		if(!file_exists($this->get_config_path($label))){
			$this->core->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$ini = $this->get_config($label);

		$db = new MySQL();
		$this->core->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$db->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))) goto set_label;
		if($ini->get('DB_NAME') == "*" && !$this->select_data_base($db->get_connection())) return false;

		$separator = $ini->get('SAVE_RESULTS_SEPARATOR');
		$this->core->write_data(str_replace("|", $separator, "Table|Engine|Collation|Rows|Data size|Data size (Bytes)|Index size|Index size (Bytes)|Row format"));

		$db_name = $db->get_data_base();

		$items = $db->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'BASE TABLE'", PDO::FETCH_OBJ);
		foreach($items as $item){
			$table = $item->{"Tables_in_$db_name"};
			$db->query("ANALYZE TABLE `$table`");
		}

		$items = $db->query("SELECT * FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = '$db_name'", PDO::FETCH_OBJ);
		foreach($items as $item){
			$data_size = $this->core->format_bytes(intval($item->DATA_LENGTH));
			$index_size = $this->core->format_bytes(intval($item->INDEX_LENGTH));
			$this->core->write_data(str_replace("|", $separator, "$item->TABLE_NAME|$item->ENGINE|$item->TABLE_COLLATION|$item->TABLE_ROWS|$data_size|$item->DATA_LENGTH|$index_size|$item->INDEX_LENGTH|$item->ROW_FORMAT"));
		}

		$db->disconnect();

		$this->core->open_logs(true);
		$this->core->pause(" Connection \"$label\" closed, press any key to back to menu");
		return false;
	}

	public function tool_compare_data_base_info() : bool {
		$this->core->clear();
		$this->core->set_subtool("Compare data base info");

		$this->get_select_label();
		set_label_source:
		$source = $this->core->get_input(" Source label / ID: ");
		if($source == '#') return false;
		if(isset($this->select_label[$source])) $source = $this->select_label[$source];
		if(!$this->core->is_valid_label($source)){
			$this->core->echo(" Invalid label");
			goto set_label_source;
		}

		if(!file_exists($this->get_config_path($source))){
			$this->core->echo(" Source label \"$source\" not exists");
			goto set_label_source;
		}

		$db_source = new MySQL();
		$ini_source = $this->get_config($source);
		$this->core->echo(" Connecting to: ".$ini_source->get('DB_HOST').":".$ini_source->get('DB_PORT')."@".$ini_source->get('DB_USER'));
		if(!$db_source->connect($ini_source->get('DB_HOST'), $ini_source->get('DB_USER'), $ini_source->get('DB_PASSWORD'), $ini_source->get('DB_NAME'), $ini_source->get('DB_PORT'))) goto set_label_source;
		if($ini_source->get('DB_NAME') == "*" && !$this->select_data_base($db_source->get_connection())) return false;

		set_label_destination:
		$destination = $this->core->get_input(" Destination label: ");
		if($destination == '#') return false;
		if(isset($this->select_label[$destination])) $destination = $this->select_label[$destination];
		if(!$this->core->is_valid_label($destination)){
			$this->core->echo(" Invalid label");
			goto set_label_destination;
		}

		if(!file_exists($this->get_config_path($destination))){
			$this->core->echo(" Destination label \"$destination\" not exists");
			goto set_label_destination;
		}

		if($source == $destination){
			$this->core->echo(" Destination label must be different than source label");
			goto set_label_destination;
		}

		$db_destination = new MySQL();
		$ini_destination = $this->get_config($destination);
		$this->core->echo(" Connecting to: ".$ini_destination->get('DB_HOST').":".$ini_destination->get('DB_PORT')."@".$ini_destination->get('DB_USER'));
		if(!$db_destination->connect($ini_destination->get('DB_HOST'), $ini_destination->get('DB_USER'), $ini_destination->get('DB_PASSWORD'), $ini_destination->get('DB_NAME'), $ini_destination->get('DB_PORT'))) goto set_label_destination;
		if($ini_destination->get('DB_NAME') == "*" && !$this->select_data_base($db_destination->get_connection())) return false;

		$info_source = [];
		$info_dest = [];

		$db_name = $db_source->get_data_base();
		$this->core->echo(" Fetch data base info for \"$source\"");
		$items = $db_source->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'BASE TABLE'", PDO::FETCH_OBJ);
		foreach($items as $item){
			$table = $item->{"Tables_in_$db_name"};
			$db_source->query("ANALYZE TABLE `$table`");
		}
		$items = $db_source->query("SELECT * FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = '$db_name'", PDO::FETCH_OBJ);
		foreach($items as $item){
			$info_source[$item->TABLE_NAME]['engine'] = $item->ENGINE;
			$info_source[$item->TABLE_NAME]['collation'] = $item->TABLE_COLLATION;
			$info_source[$item->TABLE_NAME]['rows'] = $item->TABLE_ROWS;
			$info_source[$item->TABLE_NAME]['data_size'] = $item->DATA_LENGTH;
			$info_source[$item->TABLE_NAME]['index_size'] = $item->INDEX_LENGTH;
			$info_source[$item->TABLE_NAME]['row_format'] = $item->ROW_FORMAT;
		}
		$db_source->disconnect();

		$db_name = $db_destination->get_data_base();
		$items = $db_destination->query("SHOW FULL TABLES WHERE TABLE_TYPE LIKE 'BASE TABLE'", PDO::FETCH_OBJ);
		foreach($items as $item){
			$table = $item->{"Tables_in_$db_name"};
			$db_destination->query("ANALYZE TABLE `$table`");
		}
		$this->core->echo(" Fetch data base info for \"$destination\"");
		$items = $db_destination->query("SELECT * FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = '$db_name'", PDO::FETCH_OBJ);
		foreach($items as $item){
			$info_dest[$item->TABLE_NAME]['engine'] = $item->ENGINE;
			$info_dest[$item->TABLE_NAME]['collation'] = $item->TABLE_COLLATION;
			$info_dest[$item->TABLE_NAME]['rows'] = $item->TABLE_ROWS;
			$info_dest[$item->TABLE_NAME]['data_size'] = $item->DATA_LENGTH;
			$info_dest[$item->TABLE_NAME]['index_size'] = $item->INDEX_LENGTH;
			$info_dest[$item->TABLE_NAME]['row_format'] = $item->ROW_FORMAT;
		}
		$db_destination->disconnect();

		$this->core->echo(" Check data base info differences");
		$this->core->write_data([
			"Data base info differences",
			"Source:      ".$ini_source->get('DB_HOST').":".$ini_source->get('DB_PORT')."@".$ini_source->get('DB_USER'),
			"Destination: ".$ini_destination->get('DB_HOST').":".$ini_destination->get('DB_PORT')."@".$ini_destination->get('DB_USER'),
			"",
		]);
		$errors = [
			'not_exists' => [],
			'engine' => [],
			'collation' => [],
			'rows' => [],
			'data_size' => [],
			'index_size' => [],
			'row_format' => [],
		];
		foreach($info_source as $table_name => $table_data){
			if(!isset($info_dest[$table_name])){
				array_push($errors['not_exists'], "Table \"$table_name\" not exists in destination");
			} else {
				if($info_source[$table_name]['engine'] != $info_dest[$table_name]['engine']){
					array_push($errors['engine'], "Table \"$table_name\" engine are different. Source: ".$info_source[$table_name]['engine']." Destination: ".$info_dest[$table_name]['engine']);
				}
				if($info_source[$table_name]['collation'] != $info_dest[$table_name]['collation']){
					array_push($errors['collation'], "Table \"$table_name\" collation are different. Source: ".$info_source[$table_name]['collation']." Destination: ".$info_dest[$table_name]['collation']);
				}
				if($info_source[$table_name]['rows'] != $info_dest[$table_name]['rows']){
					array_push($errors['rows'], "Table \"$table_name\" rows count are different. Source: ".$info_source[$table_name]['rows']." Destination: ".$info_dest[$table_name]['rows']);
				}
				if($info_source[$table_name]['data_size'] != $info_dest[$table_name]['data_size']){
					array_push($errors['data_size'], "Table \"$table_name\" data size are different. Source: ".$this->core->format_bytes($info_source[$table_name]['data_size'])." (".$info_source[$table_name]['data_size'].") Destination: ".$this->core->format_bytes($info_dest[$table_name]['data_size'])." (".$info_dest[$table_name]['data_size'].")");
				}
				if($info_source[$table_name]['index_size'] != $info_dest[$table_name]['index_size']){
					array_push($errors['index_size'], "Table \"$table_name\" index size are different. Source: ".$this->core->format_bytes($info_source[$table_name]['index_size'])." (".$info_source[$table_name]['index_size'].") Destination: ".$this->core->format_bytes($info_dest[$table_name]['index_size'])." (".$info_dest[$table_name]['index_size'].")");
				}
				if($info_source[$table_name]['row_format'] != $info_dest[$table_name]['row_format']){
					array_push($errors['row_format'], "Table \"$table_name\" row format are different. Source: ".$info_source[$table_name]['row_format']." Destination: ".$info_dest[$table_name]['row_format']);
				}
			}
		}

		foreach($errors as $error_data){
			if(!empty($error_data)) $this->core->write_data($error_data);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Comparison \"$source\" to \"$destination\" done, press any key to back to menu");
		return false;
	}

}

?>
