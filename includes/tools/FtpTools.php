<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use App\Services\Logs;
use App\Services\IniFile;
use App\Services\AveFtp;
use FtpClient\FtpClient;
use FtpClient\FtpException;

class FtpTools {

	private string $name = "FtpTools";

	private array $params = [];
	private string $action;
	private string $path;
	private AVE $ave;

	private $select_label = [];

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
		$this->path = $this->ave->get_file_path($this->ave->app_data."/FTP");
		$this->select_label = [];
	}

	public function help() : void {
		$this->ave->print_help([
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
			case '0': return $this->ToolConfigureConnection();
			case '1': return $this->ToolRemoveConnection();
			case '2': return $this->ToolOpenConfigFolder();
			case '3': return $this->ToolShowConnections();
			case '4': return $this->ToolGetFileList();
			case '5': return $this->ToolDownloadFiles();
			case '6': return $this->ToolUploadFiles();
			case '7': return $this->ToolDeleteFiles();
			case '8': return $this->ToolDeleteEmptyFolders();
			case '9': return $this->ToolDeleteStructure();
			case '10': return $this->ToolCopyFilesFromFTPToFTP();
			case '11': return $this->ToolImportFileZillaXML();
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
		return $config;
	}

	public function ToolConfigureConnection() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ConfigureConnection");

		$this->ave->print_help([
			' Allowed characters: A-Z a-z 0-9 _ -',
			' Label length 3 - 32',
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

		$ftp = new FtpClient();

		set_ftp_connection:
		$auth['host'] = $this->ave->get_input(" FTP Host: ");
		if($auth['host'] == '#') return false;
		$auth['port'] = $this->ave->get_input_integer(" FTP Port (Default 21): ", 0, 65353);
		if(!$auth['port']) return false;
		$auth['ssl'] = $this->ave->get_confirm(" FTP SSL (Y/N): ");

		try {
			try_login_same:
			$this->ave->echo(" Connecting to: ".$auth['host'].":".$auth['port']);
			$ftp->connect($auth['host'], $auth['ssl'], $auth['port']);
		}
		catch(FtpException $e){
			$this->ave->echo(" Failed to connect:");
			$this->ave->echo(" ".$e->getMessage());
			if($this->ave->get_confirm(" Retry (Y/N): ")) goto try_login_same;
			goto set_ftp_connection;
		}

		set_ftp_user:
		$auth['user'] = $this->ave->get_input(" FTP User: ");
		if($auth['user'] == '#') return false;
		$auth['password'] = $this->ave->get_input_no_trim(" FTP Pass: ");
		if($auth['password'] == '#') return false;
		try {
			try_login_same_user:
			$ftp->login($auth['user'], $auth['password']);
		}
		catch(FtpException $e){
			$this->ave->echo(" Failed to login:");
			$this->ave->echo(" ".$e->getMessage());
			if($this->ave->get_confirm(" Retry (Y/N): ")) goto try_login_same_user;
			goto set_ftp_user;
		}
		$ftp->close();

		$ini = $this->getConfig($label);
		$ini->update([
			'FTP_HOST' => $auth['host'],
			'FTP_USER' => $auth['user'],
			'FTP_PASSWORD' => $auth['password'],
			'FTP_SSL' => $auth['ssl'],
			'FTP_PORT' => intval($auth['port']),
		], true);

		$this->ave->clear();
		$this->ave->pause(" Setup connection for \"$label\" done, press any key to back to menu");

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
			if($ini->isValid() && $ini->isSet('FTP_HOST')){
				$label = pathinfo($file, PATHINFO_FILENAME);
				$this->ave->echo(" $label".str_repeat(" ",32-strlen($label))." ".$ini->get('FTP_HOST').":".$ini->get('FTP_PORT')."@".$ini->get('FTP_USER'));
				$cnt++;
			}
		}

		if($cnt == 0){
			$this->ave->echo(" No connections found");
		}

		$this->ave->pause("\r\n Press any key to back to menu");
		return false;
	}

	public function ToolGetFileList() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("GetFileList");

		$ftp = $this->SetupFTP(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new AveFtp($ftp);

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			$this->ave->echo(" Invalid output folder");
			goto set_output;
		}

		set_input:
		$input = $this->ave->get_input(" FTP folder: ");
		if($input == '#'){
			$ftp->close();
			return false;
		}
		if(!$ftp->chdir($input)){
			$this->ave->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		$this->ave->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->ave->get_input(" Extensions: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->ave->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->ave->get_input(" Name filter: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$filters = null;
		} else {
			$filters = explode(" ", $line);
		}

		$csv_file = $this->ave->get_file_path("$output/FtpList ".date("Y-m-d His").".csv");
		$this->ave->unlink($csv_file);
		$csv = new Logs($csv_file, false, true);
		if($this->ave->get_confirm(" Simplified list (Y/N): ")){
			$this->ave->clear();
			$this->ave->echo(" Get file list from \"$input\"");
			$csv->write($remote->get_files($input, $extensions, null, $filters));
		} else {
			$this->ave->clear();
			$this->ave->echo(" Get file list from \"$input\"");
			$files = $remote->get_files_meta($input, $extensions, null, $filters);
			if(!empty($files)){
				$s = $this->ave->config->get('AVE_CSV_SEPARATOR');
				$csv->write('"File path"'.$s.'"Dir name"'.$s.'"File name"'.$s.'"Date"'.$s.'"Size"'.$s.'"Permission"');
			}
			foreach($files as $file){
				$meta = [
					'"'.$file['path'].'"',
					'"'.$file['directory'].'"',
					'"'.$file['name'].'"',
					'"'.$file['date'].'"',
					'"'.$this->ave->format_bytes($file['size']).'"',
					'"'.$file['permission'].'"',
				];
				$csv->write(implode($this->ave->config->get('AVE_CSV_SEPARATOR'), $meta));
			}
		}
		$this->ave->echo(" Saved results into ".$csv->getPath());
		$csv->close();
		$ftp->close();

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolDownloadFiles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("DownloadFiles");

		$ftp = $this->SetupFTP(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new AveFtp($ftp);

		set_input:
		$input = $this->ave->get_input(" FTP folder: ");
		if($input == '#'){
			$ftp->close();
			return false;
		}
		if(!$ftp->chdir($input)){
			$this->ave->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		$this->ave->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->ave->get_input(" Extensions: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->ave->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->ave->get_input(" Name filter: ");
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
		$line = $this->ave->get_input(" Output: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if((file_exists($output) && !is_dir($output)) || !$this->ave->mkdir($output)){
			$this->ave->echo(" Invalid output folder");
			goto set_output;
		}

		$errors = 0;

		$this->ave->clear();
		$this->ave->echo(" Get file list from \"$input\"");
		$files = $remote->get_files($input, $extensions, null, $filters);
		$total = count($files);
		$items = 0;
		$this->ave->echo(" Download files to \"$output\"");
		$this->ave->progress($items, $total);
		$this->ave->set_errors($errors);
		foreach($files as $file){
			$items++;
			$local_file = $this->ave->get_file_path(str_replace($input, $output, $file));
			$directory = pathinfo($local_file, PATHINFO_DIRNAME);
			if(file_exists($local_file)) $this->ave->unlink($local_file);
			if(!file_exists($directory)) $this->ave->mkdir($directory);
			if($ftp->get($local_file, $file, FTP_BINARY, 0)){
				$this->ave->write_log("DOWNLOAD \"$file\" AS \"$local_file\"");
			} else {
				$this->ave->write_error("FAILED DOWNLOAD \"$file\" AS \"$local_file\"");
				$errors++;
			}
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}
		$ftp->close();

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolUploadFiles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("UploadFiles");

		$ftp = $this->SetupFTP(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new AveFtp($ftp);

		set_input:
		$line = $this->ave->get_input(" Input: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			$this->ave->echo(" Invalid input folder");
			goto set_input;
		}

		$this->ave->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->ave->get_input(" Extensions: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->ave->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->ave->get_input(" Name filter: ");
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
		$output = $this->ave->get_input(" FTP folder: ");
		if($output == '#'){
			$ftp->close();
			return false;
		}
		if(!$remote->folder_exists($output) && !$ftp->mkdir($output)){
			$this->ave->echo(" Cannot access/create folder \"$output\"");
			goto set_output;
		}

		$errors = 0;
		$this->ave->set_errors($errors);

		$directories = [];
		$this->ave->clear();
		$this->ave->echo(" Prepare directories");
		$files = $this->ave->get_files($input, $extensions, null, $filters);
		foreach($files as $file){
			array_push($directories, str_ireplace([$input, "\\"], [$output, "/"], pathinfo($file, PATHINFO_DIRNAME)));
		}
		$directories = array_unique($directories);

		$total = count($directories);
		$items = 0;
		$this->ave->progress($items, $total);
		foreach($directories as $directory){
			$items++;
			if(!$remote->folder_exists($directory)){
				if($ftp->mkdir($directory, true)){
					$this->ave->write_log("FTP MKDIR \"$directory\"");
				} else {
					$this->ave->write_error("FAILED FTP MKDIR \"$directory\"");
					$errors++;
				}
			}
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}

		$total = count($files);
		$items = 0;
		$this->ave->echo(" Upload files from \"$input\"");
		$this->ave->progress($items, $total);
		foreach($files as $file){
			$items++;
			$remote_file = str_ireplace([$input, "\\"], [$output, "/"], $file);
			if($ftp->put($remote_file, $file, FTP_BINARY, 0)){
				$this->ave->write_log("UPLOAD \"$file\" AS \"$remote_file\"");
			} else {
				$this->ave->write_error("FAILED UPLOAD \"$file\" AS \"$remote_file\"");
				$errors++;
			}
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}
		$ftp->close();

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolDeleteFiles() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("DeleteFiles");

		$ftp = $this->SetupFTP(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new AveFtp($ftp);

		set_input:
		$input = $this->ave->get_input(" FTP folder: ");
		if($input == '#'){
			$ftp->close();
			return false;
		}
		if(!$ftp->chdir($input)){
			$this->ave->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		$this->ave->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->ave->get_input(" Extensions: ");
		if($line == '#'){
			$ftp->close();
			return false;
		}
		if(empty($line)){
			$extensions = null;
		} else {
			$extensions = explode(" ", $line);
		}

		$this->ave->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->ave->get_input(" Name filter: ");
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

		$this->ave->clear();
		$this->ave->echo(" Get file list from \"$input\"");
		$files = $remote->get_files($input, $extensions, null, $filters);
		$total = count($files);
		$items = 0;
		$this->ave->echo(" Delete files from \"$input\"");
		$this->ave->progress($items, $total);
		$this->ave->set_errors($errors);
		foreach($files as $file){
			$items++;
			if($ftp->delete($file)){
				$this->ave->write_log("DELETE \"$file\"");
			} else {
				$this->ave->write_error("FAILED DELETE \"$file\"");
				$errors++;
			}
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}
		$ftp->close();

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolDeleteEmptyFolders() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("DeleteEmptyFolders");

		$ftp = $this->SetupFTP(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new AveFtp($ftp);

		set_input:
		$input = $this->ave->get_input(" FTP folder: ");
		if($input == '#'){
			$ftp->close();
			return false;
		}
		if(!$ftp->chdir($input)){
			$this->ave->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		$errors = 0;

		$this->ave->clear();
		$this->ave->echo(" Get folders list from \"$input\"");
		$files = array_reverse($remote->get_folders($input));
		$total = count($files);
		$items = 0;
		$this->ave->echo(" Delete empty folders from \"$input\"");
		$this->ave->progress($items, $total);
		$this->ave->set_errors($errors);
		foreach($files as $file){
			$items++;
			if(!$remote->hasFiles($file)){
				if($ftp->rmdir($file, false)){
					$this->ave->write_log("DELETE \"$file\"");
				} else {
					$this->ave->write_error("FAILED DELETE \"$file\"");
					$errors++;
				}
			}
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}
		$ftp->close();

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolDeleteStructure() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("DeleteStructure");

		$ftp = $this->SetupFTP(" Label / ID: ");
		if(!$ftp) return false;

		$remote = new AveFtp($ftp);

		set_input:
		$input = $this->ave->get_input(" FTP folder: ");
		if($input == '#'){
			$ftp->close();
			return false;
		}
		if(!$ftp->chdir($input)){
			$this->ave->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		$errors = 0;

		$this->ave->clear();
		$this->ave->echo(" Get file list from \"$input\"");
		$files = $remote->get_files($input);
		$total = count($files);
		$items = 0;
		$this->ave->echo(" Delete files from \"$input\"");
		$this->ave->progress($items, $total);
		$this->ave->set_errors($errors);
		foreach($files as $file){
			$items++;
			if($ftp->delete($file)){
				$this->ave->write_log("DELETE \"$file\"");
			} else {
				$this->ave->write_error("FAILED DELETE \"$file\"");
				$errors++;
			}
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}

		$this->ave->echo(" Get folders list from \"$input\"");
		$files = array_reverse($remote->get_folders($input));
		$total = count($files);
		$items = 0;
		$this->ave->echo(" Delete empty folders from \"$input\"");
		$this->ave->progress($items, $total);
		$this->ave->set_errors($errors);
		foreach($files as $file){
			$items++;
			if(!$remote->hasFiles($file)){
				if($ftp->rmdir($file, false)){
					$this->ave->write_log("DELETE \"$file\"");
				} else {
					$this->ave->write_error("FAILED DELETE \"$file\"");
					$errors++;
				}
			}
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}
		$ftp->close();

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolCopyFilesFromFTPToFTP() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("CopyFilesFromFTPToFTP");

		$ftp_source = $this->SetupFTP(" Source label / ID: ");
		if(!$ftp_source) return false;

		$ftp_destination = $this->SetupFTP(" Destination label / ID: ", false);
		if(!$ftp_destination){
			$ftp_source->close();
			return false;
		}

		$remote_source = new AveFtp($ftp_source);
		$remote_destination = new AveFtp($ftp_destination);

		set_input:
		$input = $this->ave->get_input(" FTP input: ");
		if($input == '#'){
			$ftp_source->close();
			$ftp_destination->close();
			return false;
		}
		if(!$ftp_source->chdir($input)){
			$this->ave->echo(" Cannot change current folder to \"$input\"");
			goto set_input;
		}

		set_output:
		$output = $this->ave->get_input(" FTP output: ");
		if($output == '#'){
			$ftp_source->close();
			$ftp_destination->close();
			return false;
		}
		if(!$remote_destination->folder_exists($output) && !$ftp_destination->mkdir($output)){
			$this->ave->echo(" Cannot access/create folder \"$output\"");
			goto set_output;
		}

		$this->ave->echo(" Empty for all, separate with spaces for multiple");
		$line = $this->ave->get_input(" Extensions: ");
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

		$this->ave->echo(" Empty for none, separate with spaces for multiple");
		$line = $this->ave->get_input(" Name filter: ");
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

		$create_empty_folders = $this->ave->get_confirm(" Create empty folders (Y/N): ");

		$errors = 0;
		$this->ave->set_errors($errors);

		$directories = [];
		$this->ave->clear();
		$this->ave->echo(" Get file list from \"$input\"");
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
		$this->ave->echo(" Create folders in \"$output\"");
		$this->ave->progress($items, $total);
		foreach($directories as $directory){
			$items++;
			if(!$remote_destination->folder_exists($directory)){
				if($ftp_destination->mkdir($directory, true)){
					$this->ave->write_log("FTP MKDIR \"$directory\"");
				} else {
					$this->ave->write_error("FAILED FTP MKDIR \"$directory\"");
					$errors++;
				}
			}
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}

		$total = count($files);
		$items = 0;
		$this->ave->echo(" Copy files to \"$output\"");
		$this->ave->progress($items, $total);
		$this->ave->set_errors($errors);
		foreach($files as $file){
			$items++;
			$fp = fopen('php://memory', 'r+');
			if($ftp_source->fget($fp, $file, FTP_BINARY)){
				fseek($fp, 0);
				$remote_file = str_ireplace($input, $output, $file);
				if($ftp_destination->fput($remote_file, $fp, FTP_BINARY)){
					$this->ave->write_log("COPY \"$file\" \"$remote_file\"");
				} else {
					$this->ave->write_error("FAILED UPLOAD \"$file\"");
					$errors++;
				}
			} else {
				$this->ave->write_error("FAILED DOWNLOAD \"$file\"");
				$errors++;
			}
			fclose($fp);
			$this->ave->progress($items, $total);
			$this->ave->set_errors($errors);
		}
		$ftp_source->close();
		$ftp_destination->close();

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolImportFileZillaXML() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ImportFileZillaXML");

		set_xml_file:
		$line = $this->ave->get_input(" XML file: ");
		if($line == '#') return false;
		$line = $this->ave->get_input_folders($line);
		if(!isset($line[0])) goto set_xml_file;
		$input = $line[0];

		if(!file_exists($input) || is_dir($input)){
			$this->ave->echo(" Invalid file");
			goto set_xml_file;
		}

		$xml = file_get_contents($this->ave->get_file_path($input));

		$xml = str_replace(["\r","\n","\t"], '', $xml);
		$xml = preg_replace('/<Folder [^>]+>[^>]+>/', '<Folder><Server>', $xml);
		$xml = str_replace(['<Folder>', '</Folder>', '<Servers>', '</Servers>'], '', $xml);
		$xml = @simplexml_load_string($xml);
		if($xml === false){
			$this->ave->echo(" Failed parse XML");
			goto set_xml_file;
		}

		$data = json_decode(json_encode($xml), true);

		if(isset($data['Server']) && gettype($data['Server']) == 'array'){
			foreach($data['Server'] as $key => $server){
				if(!isset($server['Name'])){
					$this->ave->echo(" Import servers[$key] failed, missing property: Name");
					continue;
				}
				if(!isset($server['Host'])){
					$this->ave->echo(" Import ".$server['Name']." failed, missing property: Host");
					continue;
				}
				if(!isset($server['Port'])){
					$this->ave->echo(" Import ".$server['Name']." failed, missing property: Port");
					continue;
				}
				if(!isset($server['User'])){
					$this->ave->echo(" Import ".$server['Name']." failed, missing property: User");
					continue;
				}
				if(!isset($server['Pass'])){
					$this->ave->echo(" Import ".$server['Name']." failed, missing property: Pass");
					continue;
				}
				if(!isset($server['Protocol'])){
					$this->ave->echo(" Import ".$server['Name']." failed, missing property: Protocol");
					continue;
				}
				$label = substr(preg_replace("/[^A-Za-z0-9_\-]/", '', str_replace(" ", "_", trim($server['Name']))), 0, 32);
				if(strlen($label) < 3) substr($label."___", 0, 3);
				if($this->ave->is_valid_label($label)){
					$ini = $this->getConfig($label);
					$ini->update([
						'FTP_HOST' => $server['Host'],
						'FTP_USER' => $server['User'],
						'FTP_PASSWORD' => base64_decode($server['Pass']),
						'FTP_SSL' => ($server['Protocol'] == 1),
						'FTP_PORT' => intval($server['Port']),
					], true);
					$ini->close();
					$this->ave->echo(" Import ".$server['Name']." as $label success");
				} else {
					$this->ave->echo(" Import ".$server['Name']." failed, invalid label");
				}
			}
		}

		$this->ave->open_logs(true);
		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function SetupFTP(string $name, bool $print = true) : FtpClient|bool {
		if($print) $this->getSelectLabel();
		set_label:
		$label = $this->ave->get_input($name);
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

		$ftp = new FtpClient();
		try {
			$ftp->connect($ini->get('FTP_HOST'), $ini->get('FTP_SSL'), $ini->get('FTP_PORT'));
			$ftp->login($ini->get('FTP_USER'), $ini->get('FTP_PASSWORD'));
		}
		catch(FtpException $e){
			$this->ave->echo(" Failed to connect:");
			$this->ave->echo(" ".$e->getMessage());
			goto set_label;
		}
		$ftp->set_option(FTP_TIMEOUT_SEC, 300);
		$ftp->pasv(true);

		return $ftp;
	}

}

?>
