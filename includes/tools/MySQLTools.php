<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use PDO;
use PDOException;
use App\Services\IniFile;
use App\Services\DataBaseBackup;

class MySQLTools {

	private string $name = "MySQLTools";

	private array $params = [];
	private string $action;
	private string $path;
	private AVE $ave;

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
		$this->path = $this->ave->get_file_path($this->ave->app_data."/MySQL");
	}

	public function help() : void {
		$this->ave->print_help([
			' Actions:',
			' 0 - Configure connection',
			' 1 - Remove connection',
			' 2 - Open config folder',
			' 3 - Show connections',
			' 4 - Make backup',
			' 5 - Clone DB1 to DB2 (overwrite)',
			' 6 - Open backup folder',
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
		}
		return false;
	}

	public function getConfigPath(string $label) : string {
		return $this->ave->get_file_path("$this->path/$label.ini");
	}

	public function getConfig(string $label) : IniFile {
		return new IniFile($this->getConfigPath($label), true);
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
			$line = $this->ave->get_input(" Overwrite (Y/N): ");
			if(strtoupper($line[0] ?? 'N') == 'N') goto set_label;
		}

		$this->ave->clear();
		$this->ave->print_help([
		 	" Setup label: \"$label\"",
			" Default port is: 3306",
		]);

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			$this->ave->echo(" Invalid output folder");
			goto set_output;
		}

		set_db_connection:
		$db['host'] = $this->ave->get_input(" DB Host: ");
		$db['port'] = $this->ave->get_input(" DB Port: ");
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
			$retry = strtoupper($this->ave->get_input(" Retry (Y/N): "));
			if(strtoupper($retry) == 'Y') goto try_login_same;
			goto set_db_connection;
		}
		$conn = null;

		$this->ave->clear();
		$this->ave->print_help([
			" Connection test completed successfully.",
			" Set additional config for label: \"$label\"",
		]);

		set_backup_structure:
		$backup['structure'] = strtoupper($this->ave->get_input(" Backup structure (Y/N): "));
		if(!in_array($backup['structure'][0] ?? '?', ['Y', 'N'])) goto set_backup_structure;

		set_backup_data:
		$backup['data'] = strtoupper($this->ave->get_input(" Backup data (Y/N): "));
		if(!in_array($backup['data'][0] ?? '?', ['Y', 'N'])) goto set_backup_data;

		set_backup_compress:
		$backup['compress'] = strtoupper($this->ave->get_input(" Compress after backup (Y/N): "));
		if(!in_array($backup['compress'][0] ?? '?', ['Y', 'N'])) goto set_backup_compress;

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
			'BACKUP_TYPE_STRUCTURE' => $backup['structure'][0] == 'Y',
			'BACKUP_TYPE_DATA' => $backup['data'][0] == 'Y',
			'BACKUP_COMPRESS' => $backup['compress'][0] == 'Y',
			'BACKUP_PATH' => $output,
		], true);

		$this->ave->write_log("Setup connection for \"$label\"");

		$this->ave->clear();
		$this->ave->pause(" Setup connection for \"$label\" done, press enter to back to menu");

		return false;
	}

	public function ToolRemoveConnection() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("RemoveConnection");

		set_label:
		$label = $this->ave->get_input(" Label: ");
		if($label == '#') return false;
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
		$files = $this->ave->getFiles($this->path, ['ini']);
		foreach($files as $file){
			$ini = new IniFile($file);
			if($ini->isValid() && $ini->isSet('DB_HOST')){
				$label = pathinfo($file, PATHINFO_FILENAME);
				$this->ave->echo(" $label".str_repeat(" ",20-strlen($label))," ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
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

		set_label:
		$label = $this->ave->get_input(" Label: ");
		if($label == '#') return false;
		if(!$this->ave->is_valid_label($label)){
			$this->ave->echo(" Invalid label");
			goto set_label;
		}

		if(!file_exists($this->getConfigPath($label))){
			$this->ave->echo(" Label \"$label\" not exists");
			goto set_label;
		}

		$ini = $this->getConfig($label);
		$path = $this->ave->get_file_path($ini->get('BACKUP_PATH')."/$label");

		if(!$this->ave->is_valid_device($path)){
			$this->ave->echo(" Output device \"$path\" is not available");
			goto set_label;
		}

		$this->ave->write_log("Initialize backup for \"$label\"");
		$this->ave->echo(" Initialize backup service");
		$backup = new DataBaseBackup($path, $ini->get('BACKUP_QUERY_LIMIT'), $ini->get('BACKUP_INSERT_LIMIT'), $ini->get('FOLDER_DATE_FORMAT'));

		$this->ave->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$backup->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))) goto set_label;

		$this->ave->echo(" Create backup");
		$tables = $backup->getTables();
		$progress = 0;
		$total = count($tables);
		$this->ave->set_progress_ex('Tables', $progress, $total);
		foreach($tables as $table){
			$progress++;
			$this->ave->write_log("Create backup for table $table");
			$errors = $backup->backupTable($table, $ini->get('BACKUP_TYPE_STRUCTURE'), $ini->get('BACKUP_TYPE_DATA'));
			if(!empty($errors)) $this->ave->write_error($errors);
			$this->ave->echo();
			$this->ave->set_progress_ex('Tables', $progress, $total);
		}
		$this->ave->echo();
		$this->ave->write_log("Finish backup for \"$label\"");
		$backup->disconnect();

		$output = $backup->getOutput();
		$cl = $this->ave->config->get('AVE_BACKUP_COMPRESS_LEVEL');
		$at = $this->ave->config->get('AVE_BACKUP_COMPRESS_TYPE');
		if($ini->get('BACKUP_COMPRESS', false)){
			$this->ave->echo(" Compressing backup");
			$this->ave->write_log("Compressing backup");
			$sql = $this->ave->get_file_path("$output/*.sql");
			system("7z a -mx$cl -t$at \"$output.7z\" \"$sql\"");
			$this->ave->echo();
			if(file_exists("$output.7z")){
				$this->ave->echo(" Compress backup into \"$output.7z\" success");
				$this->ave->write_log("Compress backup into \"$output.7z\" success");
				foreach($tables as $table){
					$this->ave->unlink($this->ave->get_file_path("$output/$table.sql"));
				}
				$this->ave->rmdir($output);
				$this->ave->open_file($ini->get('BACKUP_PATH'));
			} else {
				$this->ave->echo(" Compress backup into \"$output.7z\" fail");
				$this->ave->write_log("Compress backup into \"$output.7z\" fail");
				$this->ave->open_file($output);
			}
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

		set_label_source:
		$source = $this->ave->get_input(" Source label: ");
		if($source == '#') return false;
		if(!$this->ave->is_valid_label($source)){
			$this->ave->echo(" Invalid label");
			goto set_label_source;
		}

		if(!file_exists($this->getConfigPath($source))){
			$this->ave->echo(" Source label \"$source\" not exists");
			goto set_label_source;
		}

		$ini_source = $this->getConfig($source);
		$path = $this->ave->get_file_path($ini_source->get('BACKUP_PATH')."/$source");

		$this->ave->write_log("Initialize backup for \"$source\"");
		$this->ave->echo(" Initialize backup service");
		$backup = new DataBaseBackup($path, $ini_source->get('BACKUP_QUERY_LIMIT'), $ini_source->get('BACKUP_INSERT_LIMIT'), $ini_source->get('FOLDER_DATE_FORMAT'));

		$this->ave->echo(" Connecting to: ".$ini_source->get('DB_HOST').":".$ini_source->get('DB_PORT')."@".$ini_source->get('DB_USER'));
		if(!$backup->connect($ini_source->get('DB_HOST'), $ini_source->get('DB_USER'), $ini_source->get('DB_PASSWORD'), $ini_source->get('DB_NAME'), $ini_source->get('DB_PORT'))) goto set_label_source;

		set_label_destination:
		$destination = $this->ave->get_input(" Destination label: ");
		if($destination == '#') return false;
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
			$confirmation = strtoupper($this->ave->get_input(" Output database is not empty, continue (Y/N): "));
			if($confirmation != 'Y'){
				$this->ave->pause(" Clone \"$source\" to \"$destination\" aborted, press enter to back to menu");
				return false;
			}
		}

		$v = $this->ave->config->get('AVE_BACKUP_MAX_ALLOWED_PACKET');
		$this->ave->echo(" Try call SET GLOBAL `max_allowed_packet` = $v; (Y/N): ");
		$confirmation = strtoupper($this->ave->get_input());
		if($confirmation == 'Y'){
			if(!$backup->set_max_allowed_packet($v)){
				$this->ave->echo("SET GLOBAL `max_allowed_packet` = $v; fail, continue");
			}
		}

		$this->ave->echo(" Clone \"$source\" to \"$destination\"");
		$tables = $backup->getTables();
		$progress = 0;
		$total = count($tables);
		$this->ave->set_progress_ex('Tables', $progress, $total);
		foreach($tables as $table){
			$progress++;
			$this->ave->write_log("Clone table $table");
			$errors = $backup->cloneTable($table);
			if(!empty($errors)) $this->ave->write_error($errors);
			$this->ave->echo();
			$this->ave->set_progress_ex('Tables', $progress, $total);
		}
		$this->ave->echo();
		$this->ave->write_log("Finish clone \"$source\" to \"$destination\"");
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
		$path = $this->ave->get_file_path($ini->get('BACKUP_PATH')."/$label");

		if(!$this->ave->is_valid_device($path)){
			$this->ave->echo(" Output device \"$path\" is not available");
			return false;
		}

		$this->ave->write_log("Initialize backup for \"$label\"");
		$this->ave->echo(" Initialize backup service");
		$backup = new DataBaseBackup($path, $ini->get('BACKUP_QUERY_LIMIT'), $ini->get('BACKUP_INSERT_LIMIT'), $ini->get('FOLDER_DATE_FORMAT'));

		$this->ave->echo(" Connecting to: ".$ini->get('DB_HOST').":".$ini->get('DB_PORT')."@".$ini->get('DB_USER'));
		if(!$backup->connect($ini->get('DB_HOST'), $ini->get('DB_USER'), $ini->get('DB_PASSWORD'), $ini->get('DB_NAME'), $ini->get('DB_PORT'))){
			$this->ave->echo(" Failed connect to database");
			return false;
		}

		$this->ave->echo(" Create backup");
		$tables = $backup->getTables();
		$total = count($tables);
		foreach($tables as $table){
			$this->ave->write_log("Create backup for table $table");
			$errors = $backup->backupTable($table, $ini->get('BACKUP_TYPE_STRUCTURE'), $ini->get('BACKUP_TYPE_DATA'));
			if(!empty($errors)) $this->ave->write_error($errors);
			$this->ave->echo();
		}
		$this->ave->echo();
		$this->ave->write_log("Finish backup for \"$label\"");
		$backup->disconnect();

		$output = $backup->getOutput();
		$cl = $this->ave->config->get('AVE_BACKUP_COMPRESS_LEVEL');
		$at = $this->ave->config->get('AVE_BACKUP_COMPRESS_TYPE');
		if($ini->get('BACKUP_COMPRESS', false)){
			$this->ave->echo(" Compressing backup");
			$this->ave->write_log("Compressing backup");
			$sql = $this->ave->get_file_path("$output/*.sql");
			system("7z a -mx$cl -t$at \"$output.7z\" \"$sql\"");
			$this->ave->echo();
			if(file_exists("$output.7z")){
				$this->ave->echo(" Compress backup into \"$output.7z\" success");
				$this->ave->write_log("Compress backup into \"$output.7z\" success");
				foreach($tables as $table){
					$this->ave->unlink($this->ave->get_file_path("$output/$table.sql"));
				}
				$this->ave->rmdir($output);
			} else {
				$this->ave->echo(" Compress backup into \"$output.7z\" fail");
				$this->ave->write_log("Compress backup into \"$output.7z\" fail");
			}
		}

		$this->ave->echo(" Backup for \"$label\" done");
		$this->ave->write_log(" Backup for \"$label\" done");
		return true;
	}

	public function ToolOpenBackupFolder() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("OpenBackupFolder");

		set_label:
		$label = $this->ave->get_input(" Label: ");
		if($label == '#') return false;
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

}

?>
