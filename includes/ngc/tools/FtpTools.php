<?php

declare(strict_types=1);

namespace NGC\Tools;

use Toolkit;
use NGC\Core\Logs;
use NGC\Core\IniFile;
use NGC\Core\FtpService;
use FtpClient\FtpClient;
use FtpClient\FtpException;

class FtpTools {

	private string $name = "Ftp Tools";
	private array $params = [];
	private string $action;
	private string $path;
	private Toolkit $core;
	private array $select_label = [];

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
		$this->path = $this->core->get_path("{$this->core->app_data}/FTP");
		$this->select_label = [];
	}

	public function help() : void {
		$this->core->print_help([
			' Actions:',
			' 0  - Configure connection',
			' 1  - Remove connection',
			' 2  - Open config folder',
			' 3  - Show connections',
			' 4  - Get file list',
			' 5  - Download files',
			' 6  - Upload files',
			' 7  - Delete files',
			' 8  - Delete empty folders',
			' 9  - Delete structure (folders and files)',
			' 10 - Copy files from FTP to FTP',
			' 11 - Import FileZilla XML',
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
			case '4': return $this->tool_get_file_list();
			case '5': return $this->tool_download_files();
			case '6': return $this->tool_upload_files();
			case '7': return $this->tool_delete_files();
			case '8': return $this->tool_delete_empty_folders();
			case '9': return $this->tool_delete_structure();
			case '10': return $this->tool_copy_files_from_ftp_to_ftp();
			case '11': return $this->tool_import_file_zilla_xml();
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
		return $config;
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

		$ftp = new FtpClient();

		set_ftp_connection:
		$auth['host'] = $this->core->get_input(" FTP Host: ");
		if($auth['host'] == '#') return false;
		$auth['port'] = $this->core->get_input_integer(" FTP Port (Default 21): ", 0, 65353);
		if(!$auth['port']) return false;
		$auth['ssl'] = $this->core->get_confirm(" FTP SSL (Y/N): ");

		try {
			try_login_same:
			$this->core->echo(" Connecting to: {$auth['host']}:{$auth['port']}");
			$ftp->connect($auth['host'], $auth['ssl'], $auth['port']);
		}
		catch(FtpException $e){
			$this->core->echo(" Failed to connect:");
			$this->core->echo(" ".$e->getMessage());
			if($this->core->get_confirm(" Retry (Y/N): ")) goto try_login_same;
			goto set_ftp_connection;
		}

		set_ftp_user:
		$auth['user'] = $this->core->get_input(" FTP User: ");
		if($auth['user'] == '#') return false;
		$auth['password'] = $this->core->get_input_no_trim(" FTP Pass: ");
		if($auth['password'] == '#') return false;
		try {
			try_login_same_user:
			$ftp->login($auth['user'], $auth['password']);
		}
		catch(FtpException $e){
			$this->core->echo(" Failed to login:");
			$this->core->echo(" ".$e->getMessage());
			if($this->core->get_confirm(" Retry (Y/N): ")) goto try_login_same_user;
			goto set_ftp_user;
		}
		$ftp->close();

		$ini = $this->get_config($label);
		$ini->update([
			'FTP_HOST' => $auth['host'],
			'FTP_USER' => $auth['user'],
			'FTP_PASSWORD' => $auth['password'],
			'FTP_SSL' => $auth['ssl'],
			'FTP_PORT' => intval($auth['port']),
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
			if($ini->is_valid() && $ini->is_set('FTP_HOST')){
				$label = pathinfo($file, PATHINFO_FILENAME);
				$this->core->echo(" $label".str_repeat(" ", 32 - strlen($label))." ".$ini->get('FTP_HOST').":".$ini->get('FTP_PORT')."@".$ini->get('FTP_USER'));
				$cnt++;
			}
		}

		if($cnt == 0){
			$this->core->echo(" No connections found");
		}

		$this->core->pause("\r\n Press any key to back to menu");
		return false;
	}

	public function tool_get_file_list() : bool {
		$this->core->clear();
		$this->core->set_subtool("Get file list");

		$ftp = $this->setup_ftp(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new FtpService($ftp);

		set_output:
		$line = $this->core->get_input(" Output: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		set_input:
		$input = $this->core->get_input(" FTP folder: ");
		if($input == '#'){
			$ftp->close();
			return false;
		}
		if(!$ftp->chdir($input)){
			$this->core->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		$this->core->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->core->get_input(" Extensions: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->core->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->core->get_input(" Name filter: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$filters = null;
		} else {
			$filters = explode(" ", $line);
		}

		$csv_file = $this->core->get_path("$output/FtpList ".date("Y-m-d His").".csv");
		$this->core->delete($csv_file);
		$csv = new Logs($csv_file, false, true);
		if($this->core->get_confirm(" Simplified list (Y/N): ")){
			$this->core->clear();
			$this->core->echo(" Get file list from \"$input\"");
			$csv->write($remote->get_files($input, $extensions, null, $filters));
		} else {
			$this->core->clear();
			$this->core->echo(" Get file list from \"$input\"");
			$files = $remote->get_files_meta($input, $extensions, null, $filters);
			if(!empty($files)){
				$s = $this->core->config->get('CSV_SEPARATOR');
				$csv->write("\"File path\"{$s}\"Dir name\"{$s}\"File name\"{$s}\"Date\"{$s}\"Size\"{$s}\"Permission\"");
			}
			foreach($files as $file){
				$meta = [
					'"'.$file['path'].'"',
					'"'.$file['directory'].'"',
					'"'.$file['name'].'"',
					'"'.$file['date'].'"',
					'"'.$this->core->format_bytes($file['size']).'"',
					'"'.$file['permission'].'"',
				];
				$csv->write(implode($this->core->config->get('CSV_SEPARATOR'), $meta));
			}
		}
		$this->core->echo(" Saved results into ".$csv->get_path());
		$csv->close();
		$ftp->close();

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_download_files() : bool {
		$this->core->clear();
		$this->core->set_subtool("Download files");

		$ftp = $this->setup_ftp(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new FtpService($ftp);

		set_input:
		$input = $this->core->get_input(" FTP folder: ");
		if($input == '#'){
			$ftp->close();
			return false;
		}
		if(!$ftp->chdir($input)){
			$this->core->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		$this->core->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->core->get_input(" Extensions: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->core->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->core->get_input(" Name filter: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$filters = null;
		} else {
			$filters = explode(" ", $line);
		}

		set_output:
		$line = $this->core->get_input(" Output: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->core->mkdir($output)){
			$this->core->echo(" Invalid output folder");
			goto set_output;
		}

		$errors = 0;

		$this->core->clear();
		$this->core->echo(" Get file list from \"$input\"");
		$files = $remote->get_files($input, $extensions, null, $filters);
		$total = count($files);
		$items = 0;
		$this->core->echo(" Download files to \"$output\"");
		$this->core->progress($items, $total);
		$this->core->set_errors($errors);
		foreach($files as $file){
			$items++;
			$local_file = $this->core->get_path(str_replace($input, $output, $file));
			$directory = pathinfo($local_file, PATHINFO_DIRNAME);
			if(file_exists($local_file)) $this->core->delete($local_file);
			if(!file_exists($directory)) $this->core->mkdir($directory);
			if($ftp->get($local_file, $file, FTP_BINARY, 0)){
				$this->core->write_log("DOWNLOAD \"$file\" AS \"$local_file\"");
			} else {
				$this->core->write_error("FAILED DOWNLOAD \"$file\" AS \"$local_file\"");
				$errors++;
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$ftp->close();

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_upload_files() : bool {
		$this->core->clear();
		$this->core->set_subtool("Upload files");

		$ftp = $this->setup_ftp(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new FtpService($ftp);

		set_input:
		$line = $this->core->get_input(" Input: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		$folders = $this->core->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->core->echo(" Invalid input folder");
			goto set_input;
		}

		$this->core->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->core->get_input(" Extensions: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->core->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->core->get_input(" Name filter: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$filters = null;
		} else {
			$filters = explode(" ", $line);
		}

		set_output:
		$output = $this->core->get_input(" FTP folder: ");
		if($output == '#'){
			$ftp->close();
			return false;
		}
		if(!$remote->folder_exists($output) && !$ftp->mkdir($output)){
			$this->core->echo(" Cannot access/create folder \"$output\"");
			goto set_output;
		}

		$errors = 0;
		$this->core->set_errors($errors);

		$directories = [];
		$this->core->clear();
		$this->core->echo(" Prepare directories");
		$files = $this->core->get_files($input, $extensions, null, $filters);
		foreach($files as $file){
			array_push($directories, str_ireplace([$input, "\\"], [$output, "/"], pathinfo($file, PATHINFO_DIRNAME)));
		}
		$directories = array_unique($directories);

		$total = count($directories);
		$items = 0;
		$this->core->progress($items, $total);
		foreach($directories as $directory){
			$items++;
			if(!$remote->folder_exists($directory)){
				if($ftp->mkdir($directory, true)){
					$this->core->write_log("FTP MKDIR \"$directory\"");
				} else {
					$this->core->write_error("FAILED FTP MKDIR \"$directory\"");
					$errors++;
				}
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}

		$total = count($files);
		$items = 0;
		$this->core->echo(" Upload files from \"$input\"");
		$this->core->progress($items, $total);
		foreach($files as $file){
			$items++;
			$remote_file = str_ireplace([$input, "\\"], [$output, "/"], $file);
			if($ftp->put($remote_file, $file, FTP_BINARY, 0)){
				$this->core->write_log("UPLOAD \"$file\" AS \"$remote_file\"");
			} else {
				$this->core->write_error("FAILED UPLOAD \"$file\" AS \"$remote_file\"");
				$errors++;
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$ftp->close();

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_delete_files() : bool {
		$this->core->clear();
		$this->core->set_subtool("Delete files");

		$ftp = $this->setup_ftp(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new FtpService($ftp);

		set_input:
		$input = $this->core->get_input(" FTP folder: ");
		if($input == '#'){
			$ftp->close();
			return false;
		}
		if(!$ftp->chdir($input)){
			$this->core->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		$this->core->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->core->get_input(" Extensions: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->core->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->core->get_input(" Name filter: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$filters = null;
		} else {
			$filters = explode(" ", $line);
		}

		$errors = 0;

		$this->core->clear();
		$this->core->echo(" Get file list from \"$input\"");
		$files = $remote->get_files($input, $extensions, null, $filters);
		$total = count($files);
		$items = 0;
		$this->core->echo(" Delete files from \"$input\"");
		$this->core->progress($items, $total);
		$this->core->set_errors($errors);
		foreach($files as $file){
			$items++;
			if($ftp->delete($file)){
				$this->core->write_log("DELETE \"$file\"");
			} else {
				$this->core->write_error("FAILED DELETE \"$file\"");
				$errors++;
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$ftp->close();

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_delete_empty_folders() : bool {
		$this->core->clear();
		$this->core->set_subtool("Delete empty folders");

		$ftp = $this->setup_ftp(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new FtpService($ftp);

		set_input:
		$input = $this->core->get_input(" FTP folder: ");
		if($input == '#'){
			$ftp->close();
			return false;
		}
		if(!$ftp->chdir($input)){
			$this->core->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		$errors = 0;

		$this->core->clear();
		$this->core->echo(" Get folders list from \"$input\"");
		$files = array_reverse($remote->get_folders($input));
		$total = count($files);
		$items = 0;
		$this->core->echo(" Delete empty folders from \"$input\"");
		$this->core->progress($items, $total);
		$this->core->set_errors($errors);
		foreach($files as $file){
			$items++;
			if(!$remote->has_files($file)){
				if($ftp->rmdir($file, false)){
					$this->core->write_log("DELETE \"$file\"");
				} else {
					$this->core->write_error("FAILED DELETE \"$file\"");
					$errors++;
				}
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$ftp->close();

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_delete_structure() : bool {
		$this->core->clear();
		$this->core->set_subtool("Delete structure");

		$ftp = $this->setup_ftp(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new FtpService($ftp);

		set_input:
		$input = $this->core->get_input(" FTP folder: ");
		if($input == '#'){
			$ftp->close();
			return false;
		}
		if(!$ftp->chdir($input)){
			$this->core->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		$errors = 0;

		$this->core->clear();
		$this->core->echo(" Get file list from \"$input\"");
		$files = $remote->get_files($input);
		$total = count($files);
		$items = 0;
		$this->core->echo(" Delete files from \"$input\"");
		$this->core->progress($items, $total);
		$this->core->set_errors($errors);
		foreach($files as $file){
			$items++;
			if($ftp->delete($file)){
				$this->core->write_log("DELETE \"$file\"");
			} else {
				$this->core->write_error("FAILED DELETE \"$file\"");
				$errors++;
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}

		$this->core->echo(" Get folders list from \"$input\"");
		$files = array_reverse($remote->get_folders($input));
		$total = count($files);
		$items = 0;
		$this->core->echo(" Delete empty folders from \"$input\"");
		$this->core->progress($items, $total);
		$this->core->set_errors($errors);
		foreach($files as $file){
			$items++;
			if(!$remote->has_files($file)){
				if($ftp->rmdir($file, false)){
					$this->core->write_log("DELETE \"$file\"");
				} else {
					$this->core->write_error("FAILED DELETE \"$file\"");
					$errors++;
				}
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$ftp->close();

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_copy_files_from_ftp_to_ftp() : bool {
		$this->core->clear();
		$this->core->set_subtool("Copy files from FTP to FTP");

		$ftp_source = $this->setup_ftp(" Source label / ID: ");
		if(!$ftp_source) return false;

		$ftp_destination = $this->setup_ftp(" Destination label / ID: ", false);
		if(!$ftp_destination){
			$ftp_source->close();
			return false;
		}

		$remote_source = new FtpService($ftp_source);
		$remote_destination = new FtpService($ftp_destination);

		set_input:
		$input = $this->core->get_input(" FTP input: ");
		if($input == '#'){
			$ftp_source->close();
			$ftp_destination->close();
			return false;
		}
		if(!$ftp_source->chdir($input)){
			$this->core->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		set_output:
		$output = $this->core->get_input(" FTP output: ");
		if($output == '#'){
			$ftp_source->close();
			$ftp_destination->close();
			return false;
		}
		if(!$remote_destination->folder_exists($output) && !$ftp_destination->mkdir($output)){
			$this->core->echo(" Cannot access/create folder \"$output\"");
			goto set_output;
		}

		$this->core->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->core->get_input(" Extensions: ");
		if($line == '#'){
			$ftp_source->close();
			$ftp_destination->close();
			return false;
		}
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->core->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->core->get_input(" Name filter: ");
		if($line == '#'){
			$ftp_source->close();
			$ftp_destination->close();
			return false;
		}
		if(empty($line)){
			$filters = null;
		} else {
			$filters = explode(" ", $line);
		}

		$create_empty_folders = $this->core->get_confirm(" Create empty folders (Y/N): ");

		$errors = 0;
		$this->core->set_errors($errors);

		$directories = [];
		$this->core->clear();
		$this->core->echo(" Get file list from \"$input\"");
		$ftp_destination->set_option(FTP_TIMEOUT_SEC, 3600);

		$files = $remote_source->get_files($input, $extensions, null, $filters);
		if(!$create_empty_folders){
			foreach($files as $file){
				array_push($directories, str_ireplace($input, $output, pathinfo($file, PATHINFO_DIRNAME)));
			}
			$directories = array_unique($directories);
		} else {
			$folders = $remote_source->get_folders($input);
			foreach($folders as $folder){
				array_push($directories, str_ireplace($input, $output, $folder));
			}
		}

		$total = count($directories);
		$items = 0;
		$this->core->echo(" Create folders in \"$output\"");
		$this->core->progress($items, $total);
		foreach($directories as $directory){
			$items++;
			if(!$remote_destination->folder_exists($directory)){
				if($ftp_destination->mkdir($directory, true)){
					$this->core->write_log("FTP MKDIR \"$directory\"");
				} else {
					$this->core->write_error("FAILED FTP MKDIR \"$directory\"");
					$errors++;
				}
			}
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}

		$total = count($files);
		$items = 0;
		$this->core->echo(" Copy files to \"$output\"");
		$this->core->progress($items, $total);
		$this->core->set_errors($errors);
		foreach($files as $file){
			$items++;
			$fp = fopen('php://memory', 'r+');
			if($ftp_source->fget($fp, $file, FTP_BINARY)){
				fseek($fp, 0);
				$remote_file = str_ireplace($input, $output, $file);
				if($ftp_destination->fput($remote_file, $fp, FTP_BINARY)){
					$this->core->write_log("COPY \"$file\" \"$remote_file\"");
				} else {
					$this->core->write_error("FAILED UPLOAD \"$file\"");
					$errors++;
				}
			} else {
				$this->core->write_error("FAILED DOWNLOAD \"$file\"");
				$errors++;
			}
			fclose($fp);
			$this->core->progress($items, $total);
			$this->core->set_errors($errors);
		}
		$ftp_source->close();
		$ftp_destination->close();

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_import_file_zilla_xml() : bool {
		$this->core->clear();
		$this->core->set_subtool("Import file zilla XML");

		set_xml_file:
		$line = $this->core->get_input(" XML file: ");
		if($line == '#') return false;
		$line = $this->core->get_input_folders($line);
		if(!isset($line[0])) goto set_xml_file;
		$input = $line[0];

		if(!file_exists($input) || is_dir($input)){
			$this->core->echo(" Invalid file");
			goto set_xml_file;
		}

		$xml = file_get_contents($this->core->get_path($input));

		$xml = str_replace(["\r", "\n", "\t"], '', $xml);
		$xml = preg_replace('/<Folder [^>]+>[^>]+>/', '<Folder><Server>', $xml);
		$xml = str_replace(['<Folder>', '</Folder>', '<Servers>', '</Servers>'], '', $xml);
		$xml = @simplexml_load_string($xml);
		if($xml === false){
			$this->core->echo(" Failed parse XML");
			goto set_xml_file;
		}

		$data = json_decode(json_encode($xml), true);

		if(isset($data['Server']) && gettype($data['Server']) == 'array'){
			if(isset($data['Server']['Name'])) $data['Server'] = [$data['Server']];
			foreach($data['Server'] as $key => $server){
				if(!isset($server['Name'])){
					$this->core->echo(" Import servers[$key] failed, missing property: Name");
					continue;
				}
				if(!isset($server['Host'])){
					$this->core->echo(" Import {$server['Name']} failed, missing property: Host");
					continue;
				}
				if(!isset($server['Port'])){
					$this->core->echo(" Import {$server['Name']} failed, missing property: Port");
					continue;
				}
				if(!isset($server['User'])){
					$this->core->echo(" Import {$server['Name']} failed, missing property: User");
					continue;
				}
				if(!isset($server['Pass'])){
					$this->core->echo(" Import {$server['Name']} failed, missing property: Pass");
					continue;
				}
				if(!isset($server['Protocol'])){
					$this->core->echo(" Import {$server['Name']} failed, missing property: Protocol");
					continue;
				}
				$label = substr(preg_replace("/[^A-Za-z0-9_\-]/", '', str_replace(" ", "_", trim($server['Name']))), 0, 32);
				if(strlen($label) < 3) substr($label."___", 0, 3);
				if($this->core->is_valid_label($label)){
					$ini = $this->get_config($label);
					$ini->update([
						'FTP_HOST' => $server['Host'],
						'FTP_USER' => $server['User'],
						'FTP_PASSWORD' => base64_decode($server['Pass']),
						'FTP_SSL' => false,
						'FTP_PORT' => intval($server['Port']),
					], true);
					$ini->close();
					$this->core->echo(" Import {$server['Name']} as $label success");
				} else {
					$this->core->echo(" Import {$server['Name']} failed, invalid label");
				}
			}
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function setup_ftp(string $name, bool $print = true) : FtpClient|bool {
		if($print) $this->get_select_label();
		set_label:
		$label = $this->core->get_input($name);
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

		$ftp = new FtpClient();
		try {
			$ftp->connect($ini->get('FTP_HOST'), $ini->get('FTP_SSL'), $ini->get('FTP_PORT'));
			$ftp->login($ini->get('FTP_USER'), $ini->get('FTP_PASSWORD'));
		}
		catch(FtpException $e){
			$this->core->echo(" Failed to connect:");
			$this->core->echo(" ".$e->getMessage());
			goto set_label;
		}
		$ftp->set_option(FTP_TIMEOUT_SEC, 300);
		$ftp->pasv(true);

		return $ftp;
	}

}

?>