<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use PDO;
use PDOException;
use App\Services\IniFile;
use App\Services\DataBaseBackup;
use App\Services\DataBase;
use App\Services\Request;

class MySQLTools {

	private string $name = "MySQLTools";

	private array $params = [];
	private string $action;
	private string $path;
	private AVE $ave;

	private $select_label = [];

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
		$this->path = $this->ave->get_file_path($this->ave->app_data."/MySQL");
		$this->select_label = [];
	}

	public function help() : void {
		$this->ave->print_help([
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
			case '0': return $this->ToolConfigureConnection();
			case '1': return $this->ToolRemoveConnection();
			case '2': return $this->ToolOpenConfigFolder();
			case '3': return $this->ToolShowConnections();
			case '4': return $this->ToolMakeBackup();
			case '5': return $this->ToolMakeClone();
			case '6': return $this->ToolOpenBackupFolder();
			case '7': return $this->ToolMySQLConsole();
			case '8': return $this->ToolBackupSelectedTablesStructure();
			case '9': return $this->ToolBackupSelectedTablesData();
			case '10': return $this->ToolBackupSelectedViews();
			case '11': return $this->ToolBackupSelectedFunctions();
			case '12': return $this->ToolBackupSelectedProcedures();
			case '13': return $this->ToolBackupSelectedEvents();
			case '14': return $this->ToolBackupSelectedTriggers();
			case '15': return $this->ToolFetchDataBaseInfo();
			case '16': return $this->ToolCompareDataBaseInfo();
		}
		return false;
	}

	public function getSelectLabel() : void {
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
			$this->ave->echo(" Labels: ");
			foreach($this->select_label as $i => $label){
				$this->ave->echo(" $i - $label");
			}
			$this->ave->echo();
		}
	}

	public function getConfigPath(string $label) : string {
		return $this->ave->get_file_path("$this->path/$label.ini");
	}

	public function getConfig(string $label) : IniFile {
		$config = new IniFile($this->getConfigPath($label), true);
		$this->checkConfig($config);
		return $config;
	}

	public function ToolConfigureConnection() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ConfigureConnection");

		$this->ave->print_help([
			' Allowed characters: A-Z a-z 0-9 _ -',
			' Label length 3 - 20',
		]);

		set_label:
		$label = $this->ave->get_input(" Label: ");
		if($label == '#') return false;
		if(!$this->ave->is_valid_label($label)){
			$this->ave->echo(" Invalid label");
			goto set_label;
		}

		if(file_exists($this->getConfigPath($label))){
			$this->ave->echo(" Label \"$label\" already in use");
			if(!$this->ave->get_confirm(" Overwrite (Y/N): ")) goto set_label;
		}

		$this->ave->clear();
		$this->ave->print_help([
		 	" Setup label: \"$label\"",
		]);

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			$this->ave->echo(" Invalid output folder");
			goto set_output;
		}

		set_db_connection:
		$db['host'] = $this->ave->get_input(" DB Host: ");
		set_port:
		$db['port'] = preg_replace('/\D/', '', $this->ave->get_input(" DB Port (Defualt 3306): "));
		if($db['port'] == '') goto set_port;
		$db['name'] = $this->ave->get_input(" DB Name: ");
		$db['user'] = $this->ave->get_input(" DB User: ");
		$db['password'] = $this->ave->get_input_no_trim(" DB Pass: ");

		try_login_same:
		$options = [
			PDO::ATTR_EMULATE_PREPARES => true,
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION SQL_BIG_SELECTS=1;',
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		];
		try {
			$this->ave->echo(" Connecting to: ".$db['host'].":".$db['port']."@".$db['user']);
			$conn = new PDO("mysql:dbname=".$db['name'].";host=".$db['host'].";port=".$db['port'], $db['user'], $db['password'], $options);
		}
		catch(PDOException $e){
			$this->ave->echo(" Failed to connect:");
			$this->ave->echo(" ".$e->getMessage());
			if($this->ave->get_confirm(" Retry (Y/N): ")) goto try_login_same;
			goto set_db_connection;
		}
		$conn = null;

		$this->ave->clear();
		$this->ave->print_help([
			" Connection test completed successfully.",
			" Set additional config for label: \"$label\"",
		]);

		$backup['structure'] = $this->ave->get_confirm(" Backup structure (Y/N): ");
		$backup['data'] = $this->ave->get_confirm(" Backup data (Y/N): ");
		$backup['compress'] = $this->ave->get_confirm(" Compress after backup (Y/N): ");
		$backup['lock_tables'] = $this->ave->get_confirm(" Lock tables during background backup (Y/N): ");

		$ini = $this->getConfig($label);
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

		$this->ave->write_log("Setup connection for \"$label\"");

		$this->ave->clear();
		$this->ave->pause(" Setup connection for \"$label\" done, press enter to back to menu");

		return false;
	}

	public function ToolRemoveConnection() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("RemoveConnection");

		$this->getSelectLabel();
		set_label:
		$label = $this->ave->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->ave->is_valid_label($label)){
			$this->ave->echo(" Invalid label");
			goto set_label;
		}

		$path = $this->getConfigPath($label);
		if(!file_exists($path)){
			$this->ave->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$this->ave->unlink($path);

		return false;
	}

	public function ToolOpenConfigFolder() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("OpenConfigFolder");
		$this->ave->open_file($this->path, '');
		return false;
	}

	public function ToolShowConnections() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ShowConnections");

		$this->ave->echo(" Connections:");
		$cnt = 0;
		$files = $this->ave->get_files($this->path, ['ini']);
		foreach($files as $file){
			$ini = new IniFile($file);
			if($ini->isValid() && $ini->isSet('DB_HOST')){
				$label = pathinfo($file, PATHINFO_FILENAME);
				$this->ave->echo(" $label".str_repeat(" ",20-strlen($label))." ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
				$cnt++;
			}
		}

		if($cnt == 0){
			$this->ave->echo(" No connections found");
		}

		$this->ave->pause("\r\n Press enter to back to menu");
		return false;
	}

	public function ToolMakeBackup() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("MakeBackup");

		$this->getSelectLabel();
		set_label:
		$label = $this->ave->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->ave->is_valid_label($label)){
			$this->ave->echo(" Invalid label");
			goto set_label;
		}

		if(!file_exists($this->getConfigPath($label))){
			$this->ave->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$ini = $this->getConfig($label);
		if($ini->get('BACKUP_ADD_LABEL_TO_PATH')){
			$path = $this->ave->get_file_path($ini->get('BACKUP_PATH')."/$label");
		} else {
			$path = $this->ave->get_file_path($ini->get('BACKUP_PATH'));
		}
		$callback = $ini->get('BACKUP_CURL_CALLBACK');
		$request = new Request();

		if(!$this->ave->is_valid_device($path)){
			$this->ave->echo(" Output device \"$path\" is not available");
			goto set_label;
		}

		if(!is_null($callback)){
			if(!$this->ave->get_confirm(" Toggle website into maintenance (Y/N): ")){
				$callback = null;
			}
		}

		$lock_tables = $this->ave->get_confirm(" Lock tables during backup (Y/N): ");

		$this->ave->write_log("Initialize backup for \"$label\"");
		$this->ave->echo(" Initialize backup service");
		$backup = new DataBaseBackup($path, $ini->get('BACKUP_QUERY_LIMIT'), $ini->get('BACKUP_INSERT_LIMIT'), $ini->get('FOLDER_DATE_FORMAT'));
		$backup->toggleLockTables($lock_tables);

		if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_START'], true);
		$this->ave->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$backup->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))) goto set_label;

		$this->ave->echo(" Create backup");

		$items = $backup->getTables();
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('Table', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Create backup for table $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "table:$item"], true);
			if($ini->get('BACKUP_TYPE_STRUCTURE')){
				$errors_structure = $backup->backupTableStructure($item);
			}
			if($ini->get('BACKUP_TYPE_DATA')){
				$errors_data = $backup->backupTableData($item);
			}
			$errors = array_merge($errors_structure ?? [], $errors_data ?? []);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "table:$item"], true);
			}
			$this->ave->set_progress_ex('Table', $progress, $total);
		}

		try {
			$items = $backup->getViews();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get views");
		}
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('View', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Create backup for view $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "view:$item"], true);
			$errors = $backup->backupView($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "view:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "view:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "view:$item"], true);
			}
			$this->ave->set_progress_ex('View', $progress, $total);
		}

		try {
			$items = $backup->getFunctions();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get functions");
		}
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('Function', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Create backup for function $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "function:$item"], true);
			$errors = $backup->backupFunction($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "function:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "function:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "function:$item"], true);
			}
			$this->ave->set_progress_ex('Function', $progress, $total);
		}

		try {
			$items = $backup->getProcedures();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get procedures");
		}
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('Procedure', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Create backup for procedure $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "procedure:$item"], true);
			$errors = $backup->backupProcedure($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "procedure:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "procedure:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "procedure:$item"], true);
			}
			$this->ave->set_progress_ex('Procedure', $progress, $total);
		}

		try {
			$items = $backup->getEvents();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get events");
		}
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('Event', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Create backup for event $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "event:$item"], true);
			$errors = $backup->backupEvent($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "event:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "event:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "event:$item"], true);
			}
			$this->ave->set_progress_ex('Event', $progress, $total);
		}

		try {
			$items = $backup->getTriggers();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get triggers");
		}
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('Trigger', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Create backup for trigger $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "trigger:$item"], true);
			$errors = $backup->backupTrigger($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "trigger:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "trigger:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "trigger:$item"], true);
			}
			$this->ave->set_progress_ex('Trigger', $progress, $total);
		}

		$this->ave->echo();
		$this->ave->write_log("Finish backup for \"$label\"");
		if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'BACKUP_END'], true);
		$backup->disconnect();

		$output = $backup->getOutput();
		if($ini->get('BACKUP_COMPRESS', false)){
			$this->compress($callback, $output, $ini->get('BACKUP_PATH'), $request);
		} else {
			$this->ave->open_file($output);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Backup for \"$label\" done, press enter to back to menu");
		return false;
	}

	public function ToolMakeClone() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("MakeClone");

		$this->getSelectLabel();
		set_label_source:
		$source = $this->ave->get_input(" Source label / ID: ");
		if($source == '#') return false;
		if(isset($this->select_label[$source])) $source = $this->select_label[$source];
		if(!$this->ave->is_valid_label($source)){
			$this->ave->echo(" Invalid label");
			goto set_label_source;
		}

		if(!file_exists($this->getConfigPath($source))){
			$this->ave->echo(" Source label \"$source\" not exists");
			goto set_label_source;
		}

		$ini_source = $this->getConfig($source);
		if($ini_source->get('BACKUP_ADD_LABEL_TO_PATH')){
			$path = $this->ave->get_file_path($ini_source->get('BACKUP_PATH')."/$source");
		} else {
			$path = $this->ave->get_file_path($ini_source->get('BACKUP_PATH'));
		}
		$callback = $ini_source->get('BACKUP_CURL_CALLBACK');
		$request = new Request();

		if(!is_null($callback)){
			if(!$this->ave->get_confirm(" Toggle website into maintenance (Y/N): ")){
				$callback = null;
			}
		}

		$lock_tables = $this->ave->get_confirm(" Lock tables during clone (Y/N): ");

		$this->ave->write_log("Initialize backup for \"$source\"");
		$this->ave->echo(" Initialize backup service");
		$backup = new DataBaseBackup($path, $ini_source->get('BACKUP_QUERY_LIMIT'), $ini_source->get('BACKUP_INSERT_LIMIT'), $ini_source->get('FOLDER_DATE_FORMAT'));
		$backup->toggleLockTables($lock_tables);

		$this->ave->echo(" Connecting to: ".$ini_source->get('DB_HOST').":".$ini_source->get('DB_PORT')."@".$ini_source->get('DB_USER'));
		if(!$backup->connect($ini_source->get('DB_HOST'), $ini_source->get('DB_USER'), $ini_source->get('DB_PASSWORD'), $ini_source->get('DB_NAME'), $ini_source->get('DB_PORT'))) goto set_label_source;

		set_label_destination:
		$destination = $this->ave->get_input(" Destination label: ");
		if($destination == '#') return false;
		if(isset($this->select_label[$destination])) $destination = $this->select_label[$destination];
		if(!$this->ave->is_valid_label($destination)){
			$this->ave->echo(" Invalid label");
			goto set_label_destination;
		}

		if(!file_exists($this->getConfigPath($destination))){
			$this->ave->echo(" Destination label \"$destination\" not exists");
			goto set_label_destination;
		}

		if($source == $destination){
			$this->ave->echo(" Destination label must be different than source label");
			goto set_label_destination;
		}

		$ini_dest = $this->getConfig($destination);

		if($ini_source->get('DB_HOST') == $ini_dest->get('DB_HOST') && $ini_source->get('DB_USER') == $ini_dest->get('DB_USER') && $ini_source->get('DB_NAME') == $ini_dest->get('DB_NAME') && $ini_source->get('DB_PORT') == $ini_dest->get('DB_PORT')){
			$this->ave->echo(" Destination database is same as source database");
			goto set_label_destination;
		}

		$this->ave->echo(" Connecting to: ".$ini_dest->get('DB_HOST').":".$ini_dest->get('DB_PORT')."@".$ini_dest->get('DB_USER'));
		if(!$backup->connect_destination($ini_dest->get('DB_HOST'), $ini_dest->get('DB_USER'), $ini_dest->get('DB_PASSWORD'), $ini_dest->get('DB_NAME'), $ini_dest->get('DB_PORT'))) goto set_label_destination;

		if(!$backup->isDestinationEmpty()){
			if(!$this->ave->get_confirm(" Output database is not empty, continue (Y/N): ")){
				$this->ave->pause(" Clone \"$source\" to \"$destination\" aborted, press enter to back to menu");
				return false;
			}
		}

		$v = $this->ave->config->get('AVE_BACKUP_MAX_ALLOWED_PACKET');
		if($this->ave->get_confirm(" Try call SET GLOBAL `max_allowed_packet` = $v; (Y/N): ")){
			if(!$backup->set_max_allowed_packet($v)){
				$this->ave->echo("SET GLOBAL `max_allowed_packet` = $v; fail, continue");
			}
		}

		$this->ave->echo(" Clone \"$source\" to \"$destination\"");
		if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_START'], true);

		$items = $backup->getTables();
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('Table Structure', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Clone table Structure $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "table:$item"], true);
			$errors = $backup->cloneTableStructure($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "table:$item"], true);
			}
			$this->ave->set_progress_ex('Table Structure', $progress, $total);
		}

		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('Table Data', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Clone table data $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "table:$item"], true);
			$errors = $backup->cloneTableData($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "table:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "table:$item"], true);
			}
			$this->ave->set_progress_ex('Table Data', $progress, $total);
		}

		try {
			$items = $backup->getViews();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get views");
		}
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('View', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Clone view $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "view:$item"], true);
			$errors = $backup->cloneView($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "view:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "view:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "view:$item"], true);
			}
			$this->ave->set_progress_ex('View', $progress, $total);
		}

		try {
			$items = $backup->getFunctions();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get functions");
		}
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('Function', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Clone function $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "function:$item"], true);
			$errors = $backup->cloneFunction($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "function:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "function:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "function:$item"], true);
			}
			$this->ave->set_progress_ex('Function', $progress, $total);
		}

		try {
			$items = $backup->getProcedures();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get procedures");
		}
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('Procedure', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Clone procedure $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "procedure:$item"], true);
			$errors = $backup->cloneProcedure($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "procedure:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "procedure:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "procedure:$item"], true);
			}
			$this->ave->set_progress_ex('Procedure', $progress, $total);
		}

		try {
			$items = $backup->getEvents();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get events");
		}
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('Event', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Clone event $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "event:$item"], true);
			$errors = $backup->cloneEvent($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "event:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "event:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "event:$item"], true);
			}
			$this->ave->set_progress_ex('Event', $progress, $total);
		}

		try {
			$items = $backup->getTriggers();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get triggers");
		}
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex('Trigger', $progress, $total);
		foreach($items as $item){
			$progress++;
			$this->ave->write_log("Clone trigger $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "trigger:$item"], true);
			$errors = $backup->cloneTrigger($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
				if($ini_source->get('BACKUP_CURL_SEND_ERRORS')){
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "trigger:$item", 'errors' => $errors];
				} else {
					$cdata = ['maintenance' => true, 'state' => 'BACKUP_TABLE_ERROR', 'table' => "trigger:$item", 'errors' => ['Error reporting is disabled']];
				}
				if(!is_null($callback)) $request->get($callback, $cdata, true);
			} else {
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_END', 'table' => "trigger:$item"], true);
			}
			$this->ave->set_progress_ex('Trigger', $progress, $total);
		}

		$this->ave->echo();
		$this->ave->write_log("Finish clone \"$source\" to \"$destination\"");
		if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_END'], true);
		$backup->disconnect();
		$backup->disconnect_destination();

		$this->ave->open_logs(true);
		$this->ave->pause(" Clone for \"$source\" to \"$destination\" done, press enter to back to menu");
		return false;
	}

	public function ToolMakeBackupCMD(string $label) : bool {
		if(!$this->ave->is_valid_label($label)){
			$this->ave->echo(" Invalid label \"$label\"");
			return false;
		}

		if(!file_exists($this->getConfigPath($label))){
			$this->ave->echo(" Label \"$label\" not exists");
			return false;
		}

		$ini = $this->getConfig($label);
		if($ini->get('BACKUP_ADD_LABEL_TO_PATH')){
			$path = $this->ave->get_file_path($ini->get('BACKUP_PATH')."/$label");
		} else {
			$path = $this->ave->get_file_path($ini->get('BACKUP_PATH'));
		}
		$callback = $ini->get('BACKUP_CURL_CALLBACK');
		$request = new Request();

		if(!$this->ave->is_valid_device($path)){
			$this->ave->echo(" Output device \"$path\" is not available");
			return false;
		}

		$this->ave->write_log("Initialize backup for \"$label\"");
		$this->ave->echo(" Initialize backup service");
		$backup = new DataBaseBackup($path, $ini->get('BACKUP_QUERY_LIMIT'), $ini->get('BACKUP_INSERT_LIMIT'), $ini->get('FOLDER_DATE_FORMAT'));
		$backup->toggleLockTables($ini->get('BACKUP_LOCK_TABLES'));

		if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_START'], true);
		$this->ave->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$backup->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))){
			$this->ave->echo(" Failed connect to database");
			return false;
		}

		$this->ave->echo(" Create backup");

		$items = $backup->getTables();
		$total = count($items);
		foreach($items as $item){
			$this->ave->write_log("Create backup for table $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "table:$item"], true);
			if($ini->get('BACKUP_TYPE_STRUCTURE')){
				$errors_structure = $backup->backupTableStructure($item);
			}
			if($ini->get('BACKUP_TYPE_DATA')){
				$errors_data = $backup->backupTableData($item);
			}
			$errors = array_merge($errors_structure ?? [], $errors_data ?? []);
			if(!empty($errors)){
				$this->ave->write_error($errors);
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
			$items = $backup->getViews();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get views");
		}
		$total = count($items);
		foreach($items as $item){
			$this->ave->write_log("Create backup for view $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "view:$item"], true);
			$errors = $backup->backupView($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
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
			$items = $backup->getFunctions();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get functions");
		}
		$total = count($items);
		foreach($items as $item){
			$this->ave->write_log("Create backup for function $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "function:$item"], true);
			$errors = $backup->backupFunction($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
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
			$items = $backup->getProcedures();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get procedures");
		}
		$total = count($items);
		foreach($items as $item){
			$this->ave->write_log("Create backup for procedure $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "procedure:$item"], true);
			$errors = $backup->backupProcedure($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
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
			$items = $backup->getEvents();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get events");
		}
		$total = count($items);
		foreach($items as $item){
			$this->ave->write_log("Create backup for event $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "event:$item"], true);
			$errors = $backup->backupEvent($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
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
			$items = $backup->getTriggers();
		}
		catch(PDOException $e){
			$items = [];
			$this->ave->write_error("Access denied for get triggers");
		}
		$total = count($items);
		foreach($items as $item){
			$this->ave->write_log("Create backup for trigger $item");
			if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "trigger:$item"], true);
			$errors = $backup->backupTrigger($item);
			if(!empty($errors)){
				$this->ave->write_error($errors);
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

		$this->ave->echo();
		$this->ave->write_log("Finish backup for \"$label\"");
		if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'BACKUP_END'], true);
		$backup->disconnect();

		$output = $backup->getOutput();
		if($ini->get('BACKUP_COMPRESS', false)){
			$this->compress($callback, $output, $ini->get('BACKUP_PATH'), $request);
		} else {
			$this->ave->open_file($output);
		}

		$this->ave->echo(" Backup for \"$label\" done");
		$this->ave->write_log(" Backup for \"$label\" done");
		return true;
	}

	public function ToolOpenBackupFolder() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("OpenBackupFolder");

		$this->getSelectLabel();
		set_label:
		$label = $this->ave->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->ave->is_valid_label($label)){
			$this->ave->echo(" Invalid label");
			goto set_label;
		}

		$path = $this->getConfigPath($label);
		if(!file_exists($path)){
			$this->ave->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$config = $this->getConfig($label);
		$this->ave->open_file($this->ave->get_file_path($config->get('BACKUP_PATH')."/$label"), '');

		return false;
	}

	public function ToolMySQLConsole() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("MySQLConsole");

		$this->getSelectLabel();
		set_label:
		$label = $this->ave->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->ave->is_valid_label($label)){
			$this->ave->echo(" Invalid label");
			goto set_label;
		}

		if(!file_exists($this->getConfigPath($label))){
			$this->ave->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$ini = $this->getConfig($label);

		$db = new DataBase();
		$this->ave->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$db->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))) goto set_label;

		$save_output = $this->ave->get_confirm(" Save query results in data file (Y/N): ");
		if($save_output){
			$this->ave->write_data([" Query results for: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'), ""]);
		}

		clear:
		$this->ave->clear();
		$this->ave->print_help([
			" MySQL Console: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER')." [$label] Save results: ".($save_output ? 'Enabled' : 'Disabled'),
			" Additional commands: ",
			" @exit  - close connection",
			" @clear - clear console",
			" @open  - open data folder",
		]);

		try {
			query:
			if($save_output) $this->ave->write_data("");
			$query = $this->ave->get_input_no_trim(" MySQL: ");
			$lquery = strtolower($query);
			if($lquery == '@exit'){
				goto close_connection;
			} else if($lquery == '@clear'){
				goto clear;
			} else if($lquery == '@open'){
				$this->ave->open_file($this->ave->get_file_path($this->ave->config->get('AVE_DATA_FOLDER')), '');
				goto query;
			}

			if($save_output) $this->ave->write_data([" ".$query, ""]);
			$sth = $db->query($query);
			$results = $sth->fetchAll(PDO::FETCH_ASSOC);
			$last_insert_id = $db->getConnection()->lastInsertId();
			if($last_insert_id){
				$this->ave->echo(" Last insert id: $last_insert_id");
				if($save_output) $this->ave->write_data(" Last insert id: $last_insert_id");
			} else if(count($results) == 0){
				if(substr($lquery, 0, 6) == 'select' || substr($lquery, 0, 4) == 'show'){
					$this->ave->echo(" MySQL returned an empty result");
					if($save_output) $this->ave->write_data(" MySQL returned an empty result");
				} else {
					$this->ave->echo(" Done");
					if($save_output) $this->ave->write_data(" Done");
				}
			} else {
				$results = $db->resultsToString($results, $ini->get('SAVE_RESULTS_SEPARATOR'));
				$this->ave->echo($results);
				if($save_output) $this->ave->write_data($results);
			}
		}
		catch(PDOException $e){
			$this->ave->echo(" ".$e->getMessage());
			if($save_output) $this->ave->write_data(" ".$e->getMessage());
		}
		goto query;

		close_connection:
		$db->disconnect();

		$this->ave->open_logs(true);
		$this->ave->pause(" Connection \"$label\" closed, press enter to back to menu");
		return false;
	}

	public function ToolBackupSelectedTablesStructure() : bool {
		return $this->BackupSelected('Table Structure', false);
	}

	public function ToolBackupSelectedTablesData() : bool {
		return $this->BackupSelected('Table Data', true);
	}

	public function ToolBackupSelectedViews() : bool {
		return $this->BackupSelected('View', false);
	}

	public function ToolBackupSelectedFunctions() : bool {
		return $this->BackupSelected('Function', false);
	}

	public function ToolBackupSelectedProcedures() : bool {
		return $this->BackupSelected('Procedure', false);
	}

	public function ToolBackupSelectedEvents() : bool {
		return $this->BackupSelected('Event', false);
	}

	public function ToolBackupSelectedTriggers() : bool {
		return $this->BackupSelected('Trigger', false);
	}

	public function checkConfig(IniFile $config) : void {
		if(!$config->isSet('BACKUP_ADD_LABEL_TO_PATH')) $config->set('BACKUP_ADD_LABEL_TO_PATH', true);
		if(!$config->isSet('BACKUP_CURL_SEND_ERRORS')) $config->set('BACKUP_CURL_SEND_ERRORS', false);
		if(!$config->isSet('BACKUP_CURL_CALLBACK')) $config->set('BACKUP_CURL_CALLBACK', null);
		if(!$config->isSet('BACKUP_QUERY_LIMIT')) $config->set('BACKUP_QUERY_LIMIT', 50000);
		if(!$config->isSet('BACKUP_INSERT_LIMIT')) $config->set('BACKUP_INSERT_LIMIT', 100);
		if(!$config->isSet('BACKUP_TYPE_STRUCTURE')) $config->set('BACKUP_TYPE_STRUCTURE', true);
		if(!$config->isSet('BACKUP_TYPE_DATA')) $config->set('BACKUP_TYPE_DATA', true);
		if(!$config->isSet('BACKUP_COMPRESS')) $config->set('BACKUP_COMPRESS', true);
		if(!$config->isSet('FOLDER_DATE_FORMAT')) $config->set('FOLDER_DATE_FORMAT', 'Y-m-d_His');
		if(!$config->isSet('SAVE_RESULTS_SEPARATOR')) $config->set('SAVE_RESULTS_SEPARATOR', '|');
		if(!$config->isSet('BACKUP_LOCK_TABLES')) $config->set('BACKUP_LOCK_TABLES', false);
		if($config->isChanged()) $config->save();
	}

	public function compress(?string $callback, string $output, string $backup_path, Request $request) : void {
		if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'COMPRESS_BACKUP_START'], true);
		$this->ave->echo(" Compressing backup");
		$this->ave->write_log("Compressing backup");
		$sql = $this->ave->get_file_path("$output/*");
		$cl = $this->ave->config->get('AVE_BACKUP_COMPRESS_LEVEL');
		$at = $this->ave->config->get('AVE_BACKUP_COMPRESS_TYPE');
		$this->ave->exec("7z", "a -mx$cl -t$at \"$output.7z\" \"$sql\"");
		$this->ave->echo();
		if(file_exists("$output.7z")){
			if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'COMPRESS_BACKUP_END'], true);
			$this->ave->echo(" Compress backup into \"$output.7z\" success");
			$this->ave->write_log("Compress backup into \"$output.7z\" success");
			$this->ave->rrmdir($output);
			$this->ave->open_file($backup_path);
		} else {
			if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'COMPRESS_BACKUP_ERROR'], true);
			$this->ave->echo(" Compress backup into \"$output.7z\" fail");
			$this->ave->write_log("Compress backup into \"$output.7z\" fail");
			$this->ave->open_file($output);
		}
	}

	public function BackupSelected(string $type, bool $need_lock) : bool {
		$ftype = explode(" ", $type);
		$ftype = $ftype[0];
		$stype = strtolower($type);
		$sftype = strtolower($ftype);
		$type = str_replace(" ", "", $type);
		$this->ave->clear();
		$this->ave->set_subtool("BackupSelected".$type);

		$this->getSelectLabel();
		set_label:
		$label = $this->ave->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->ave->is_valid_label($label)){
			$this->ave->echo(" Invalid label");
			goto set_label;
		}

		if(!file_exists($this->getConfigPath($label))){
			$this->ave->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$ini = $this->getConfig($label);
		if($ini->get('BACKUP_ADD_LABEL_TO_PATH')){
			$path = $this->ave->get_file_path($ini->get('BACKUP_PATH')."/$label");
		} else {
			$path = $this->ave->get_file_path($ini->get('BACKUP_PATH'));
		}
		$callback = $ini->get('BACKUP_CURL_CALLBACK');
		$request = new Request();

		if(!$this->ave->is_valid_device($path)){
			$this->ave->echo(" Output device \"$path\" is not available");
			goto set_label;
		}

		if(!is_null($callback)){
			if(!$this->ave->get_confirm(" Toggle website into maintenance (Y/N): ")){
				$callback = null;
			}
		}

		if($need_lock){
			$lock_tables = $this->ave->get_confirm(" Lock tables during backup (Y/N): ");
		} else {
			$lock_tables = false;
		}

		$compress = $this->ave->get_confirm(" Compress backup (Y/N): ");

		$this->ave->print_help([
			" Type $stype you want to backup, separate with a space",
			" Use double quotes \" for escape name",
		]);
		$line = $this->ave->get_input(" Names: ");
		if($line == '#') return false;
		$items = $this->ave->get_input_folders($line);

		$this->ave->write_log("Initialize backup for \"$label\"");
		$this->ave->echo(" Initialize backup service");
		$backup = new DataBaseBackup($path, $ini->get('BACKUP_QUERY_LIMIT'), $ini->get('BACKUP_INSERT_LIMIT'), $ini->get('FOLDER_DATE_FORMAT'));
		$backup->toggleLockTables($lock_tables);

		if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_START'], true);
		$this->ave->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$backup->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))) goto set_label;

		$this->ave->echo(" Create backup");
		$func = "get".$ftype."s";
		$items_in_db = $backup->$func();
		$progress = 0;
		$total = count($items);
		$this->ave->set_progress_ex($type, $progress, $total);
		foreach($items as $item){
			$progress++;
			if(in_array($item, $items_in_db)){
				$this->ave->write_log("Create backup for $stype $item");
				if(!is_null($callback)) $request->get($callback, ['maintenance' => true, 'state' => 'BACKUP_TABLE_START', 'table' => "$stype:$item"], true);
				$func = "backup".$type;
				$errors = $backup->$func($item);
				if(!empty($errors)){
					$this->ave->write_error($errors);
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
				$this->ave->echo(" $type: $item not exists, skipping");
				$this->ave->write_error("Create backup for $stype $item failed, $stype not exists");
			}
			$this->ave->set_progress_ex($type, $progress, $total);
		}
		$this->ave->echo();
		$this->ave->write_log("Finish backup for \"$label\"");
		if(!is_null($callback)) $request->get($callback, ['maintenance' => false, 'state' => 'BACKUP_END'], true);
		$backup->disconnect();

		$output = $backup->getOutput();
		if($compress){
			$this->compress($callback, $output, $ini->get('BACKUP_PATH'), $request);
		} else {
			$this->ave->open_file($output);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Backup for \"$label\" done, press enter to back to menu");
		return false;
	}

	public function ToolFetchDataBaseInfo() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("FetchDataBaseInfo");

		$this->getSelectLabel();
		set_label:
		$label = $this->ave->get_input(" Label / ID: ");
		if($label == '#') return false;
		if(isset($this->select_label[$label])) $label = $this->select_label[$label];
		if(!$this->ave->is_valid_label($label)){
			$this->ave->echo(" Invalid label");
			goto set_label;
		}

		if(!file_exists($this->getConfigPath($label))){
			$this->ave->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$ini = $this->getConfig($label);

		$db = new DataBase();
		$this->ave->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$db->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))) goto set_label;

		$separator = $ini->get('SAVE_RESULTS_SEPARATOR');
		$this->ave->write_data(str_replace("|", $separator, "Table|Engine|Collation|Rows|Data size|Data size (Bytes)|Index size|Index size (Bytes)|Row format"));

		$db_name = $ini->get('DB_NAME');
		$items = $db->query("SELECT * FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = '$db_name'", PDO::FETCH_OBJ);
		foreach($items as $item){
			$data_size = $this->ave->format_bytes(intval($item->DATA_LENGTH));
			$index_size = $this->ave->format_bytes(intval($item->INDEX_LENGTH));
			$this->ave->write_data(str_replace("|", $separator, "$item->TABLE_NAME|$item->ENGINE|$item->TABLE_COLLATION|$item->TABLE_ROWS|$data_size|$item->DATA_LENGTH|$index_size|$item->INDEX_LENGTH|$item->ROW_FORMAT"));
		}

		$db->disconnect();

		$this->ave->open_logs(true);
		$this->ave->pause(" Connection \"$label\" closed, press enter to back to menu");
		return false;
	}

	public function ToolCompareDataBaseInfo() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("CompareDataBaseInfo");

		$this->getSelectLabel();
		set_label_source:
		$source = $this->ave->get_input(" Source label / ID: ");
		if($source == '#') return false;
		if(isset($this->select_label[$source])) $source = $this->select_label[$source];
		if(!$this->ave->is_valid_label($source)){
			$this->ave->echo(" Invalid label");
			goto set_label_source;
		}

		if(!file_exists($this->getConfigPath($source))){
			$this->ave->echo(" Source label \"$source\" not exists");
			goto set_label_source;
		}

		$db_source = new DataBase();
		$ini_source = $this->getConfig($source);
		$this->ave->echo(" Connecting to: ".$ini_source->get('DB_HOST').":".$ini_source->get('DB_PORT')."@".$ini_source->get('DB_USER'));
		if(!$db_source->connect($ini_source->get('DB_HOST'), $ini_source->get('DB_USER'), $ini_source->get('DB_PASSWORD'), $ini_source->get('DB_NAME'), $ini_source->get('DB_PORT'))) goto set_label_source;

		set_label_destination:
		$destination = $this->ave->get_input(" Destination label: ");
		if($destination == '#') return false;
		if(isset($this->select_label[$destination])) $destination = $this->select_label[$destination];
		if(!$this->ave->is_valid_label($destination)){
			$this->ave->echo(" Invalid label");
			goto set_label_destination;
		}

		if(!file_exists($this->getConfigPath($destination))){
			$this->ave->echo(" Destination label \"$destination\" not exists");
			goto set_label_destination;
		}

		if($source == $destination){
			$this->ave->echo(" Destination label must be different than source label");
			goto set_label_destination;
		}

		$db_destination = new DataBase();
		$ini_destination = $this->getConfig($destination);
		$this->ave->echo(" Connecting to: ".$ini_destination->get('DB_HOST').":".$ini_destination->get('DB_PORT')."@".$ini_destination->get('DB_USER'));
		if(!$db_destination->connect($ini_destination->get('DB_HOST'), $ini_destination->get('DB_USER'), $ini_destination->get('DB_PASSWORD'), $ini_destination->get('DB_NAME'), $ini_destination->get('DB_PORT'))) goto set_label_destination;

		$info_source = [];
		$info_dest = [];

		$db_name = $ini_source->get('DB_NAME');
		$this->ave->echo(" Fetch data base info for \"$source\"");
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

		$db_name = $ini_destination->get('DB_NAME');
		$this->ave->echo(" Fetch data base info for \"$destination\"");
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

		$this->ave->echo(" Check data base info differences");
		$this->ave->write_data([
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
					array_push($errors['data_size'], "Table \"$table_name\" data size are different. Source: ".$this->ave->format_bytes($info_source[$table_name]['data_size'])." (".$info_source[$table_name]['data_size'].") Destination: ".$this->ave->format_bytes($info_dest[$table_name]['data_size'])." (".$info_dest[$table_name]['data_size'].")");
				}
				if($info_source[$table_name]['index_size'] != $info_dest[$table_name]['index_size']){
					array_push($errors['index_size'], "Table \"$table_name\" index size are different. Source: ".$this->ave->format_bytes($info_source[$table_name]['index_size'])." (".$info_source[$table_name]['index_size'].") Destination: ".$this->ave->format_bytes($info_dest[$table_name]['index_size'])." (".$info_dest[$table_name]['index_size'].")");
				}
				if($info_source[$table_name]['row_format'] != $info_dest[$table_name]['row_format']){
					array_push($errors['row_format'], "Table \"$table_name\" row format are different. Source: ".$info_source[$table_name]['row_format']." Destination: ".$info_dest[$table_name]['row_format']);
				}
			}
		}

		foreach($errors as $error_type => $error_data){
			if(!empty($error_data)) $this->ave->write_data($error_data);
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Comparison \"$source\" to \"$destination\" done, press enter to back to menu");
		return false;
	}

}

?>
