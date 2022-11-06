<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use App\Services\GuardPattern;
use App\Services\GuardDriver;
use App\Services\IniFile;

class CheckFileIntegrity {

	private string $name = "CheckFileIntegrity";

	private array $params = [];
	private string $action;
	private AVE $ave;

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
	}

	public function help(){
		$this->ave->print_help([
			' Actions:',
			' 0 - Create pattern',
			' 1 - Generate guard',
			' 2 - Check integrity',
			' 3 - Get files tree',
			' 4 - Update guard: Remove missing files',
			' 5 - Update guard: Add unknown files',
			' 6 - Update guard: Update changed files',
		]);
	}

	public function action(string $action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolCreatePatternAction();
			case '1': return $this->ToolGuardGenerateAction();
			case '2': return $this->ToolCheckIntegrityAction();
			case '3': return $this->ToolGetFilesTreeAction();
			case '4': return $this->ToolUpdateRemoveMissingAction();
			case '5': return $this->ToolUpdateAddUnknownAction();
			case '6': return $this->ToolUpdateChangedAction();
		}
		$this->ave->select_action();
	}

	public function ToolCreatePatternAction(){
		$this->ave->clear();
		$this->ave->set_subtool("CreatePattern");

		set_input:
		echo " Input (Folder): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder\r\n";
			goto set_input;
		}

		set_output:
		echo " Output (Folder): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if(file_exists($output) && !is_dir($output)){
			echo " Invalid output folder\r\n";
			goto set_output;
		}

		if(!file_exists($output)){
			if(!$this->ave->mkdir($output)){
				echo " Failed create output folder\r\n";
				goto set_output;
			}
		}

		set_name:
		echo " Name: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$pattern_file = $this->ave->get_folders($line);
		if(!isset($pattern_file[0])) goto set_name;
		$pattern_file = preg_replace('/[^A-Za-z0-9_\-]/', '_', $pattern_file[0]).".ave-pat";

		$pattern = new GuardPattern();
		$pattern->setInput($input);

		set_folders:
		echo " Folders: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		foreach($this->ave->get_folders($line) as $folder){
			$pattern->addFolders(str_replace([$input.DIRECTORY_SEPARATOR,$input], "", $folder));
		}

		if(!empty($line)){
			echo " More (Y/N): ";
			$line = $this->ave->get_input();
			if(strtoupper($line[0] ?? 'N') == 'Y') goto set_folders;
		}

		set_files:
		echo " Files: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		foreach($this->ave->get_folders($line) as $file){
			$pattern->addFiles(str_replace($input, "", $file));
		}

		if(!empty($line)){
			echo " More (Y/N): ";
			$line = $this->ave->get_input();
			if(strtoupper($line[0] ?? 'N') == 'Y') goto set_files;
		}

		$file_name = $output.DIRECTORY_SEPARATOR.$pattern_file;

		file_put_contents($file_name, $pattern->get());

		$this->ave->open_file($output);

		$this->ave->exit();
	}

	public function ToolGuardGenerateAction(){
		$this->ave->clear();
		$this->ave->set_subtool("GuardGenerate");

		set_pattern:
		echo " Pattern (.ave-pat): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_pattern;
		$pattern_file = $folders[0];

		if(!file_exists($pattern_file)){
			echo " Pattern file not exists\r\n";
			goto set_pattern;
		}

		$pattern = new GuardPattern();
		$pattern->load(file_get_contents($pattern_file));

		$input = $pattern->getInput();
		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder: \"$input\"\r\n";
			goto set_pattern;
		}

		$files = count($pattern->getFiles());
		$folders = count($pattern->getFolders());
		echo " Loaded $folders folders and $files files\r\n";

		$guard_file = str_replace(chr(0x5C).chr(0x5C), chr(0x5C), pathinfo($pattern_file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.pathinfo($pattern_file, PATHINFO_FILENAME).".ave-guard");

		$cwd = getcwd();
		chdir($input);
		echo " Generate $guard_file\r\n";
		$guard = new GuardDriver($guard_file, $pattern->getFolders(), $pattern->getFiles());
		$guard->generate();
		chdir($cwd);

		$this->ave->open_file(pathinfo($pattern_file, PATHINFO_DIRNAME));

		$this->ave->exit();
	}

	public function ToolCheckIntegrityAction(){
		$this->ave->clear();
		$this->ave->set_subtool("CheckIntegrity");

		set_input:
		echo " Input (Folder): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder\r\n";
			goto set_input;
		}

		set_guard:
		echo " Guard (.ave-guard): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_guard;
		$guard_file = $folders[0];

		if(!file_exists($guard_file)){
			echo " Guard file not exists\r\n";
			goto set_guard;
		}

		$ini = new IniFile($guard_file, true);
		if(is_null($ini->get('keys'))){
			echo " File don't contain one of required information (Not a valid guard file ?)\r\n";
			goto set_guard;
		}

		$cwd = getcwd();
		chdir($input);
		echo " Validate files from $guard_file\r\n";
		$guard = new GuardDriver($guard_file, $ini->get('folders_to_scan'), $ini->get('files_to_scan'));
		$validation = $guard->validate();
		chdir($cwd);

		$damaged = 0;
		$missing = 0;
		$unknown = 0;

		$errors = [
			'damaged' => [],
			'unknown' => [],
			'missing' => [],
		];

		foreach($validation as $error){
			switch($error['type']){
				case 'damaged': {
					$damaged++;
					array_push($errors['damaged'], $error['file']);
					break;
				}
				case 'unknown': {
					$unknown++;
					array_push($errors['unknown'], $error['file']);
					break;
				}
				case 'missing': {
					$missing++;
					array_push($errors['missing'], $error['file']);
					break;
				}
			}
		}

		$this->ave->write_data("State: $damaged damaged, $missing missing, $unknown unknown");
		$this->ave->write_data("\r\nDamaged:");
		$this->ave->write_data($errors['damaged'] ?? []);
		$this->ave->write_data("\r\nMissing:");
		$this->ave->write_data($errors['missing'] ?? []);
		$this->ave->write_data("\r\nUnknown:");
		$this->ave->write_data($errors['unknown'] ?? []);

		$this->ave->exit();
	}

	public function ToolGetFilesTreeAction(){
		$this->ave->clear();
		$this->ave->set_subtool("GetFilesTree");

		set_guard:
		echo " Guard (.ave-guard): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_guard;
		$guard_file = $folders[0];

		if(!file_exists($guard_file)){
			echo " Guard file not exists\r\n";
			goto set_guard;
		}

		$ini = new IniFile($guard_file, true);
		if(is_null($ini->get('keys'))){
			echo " File don't contain one of required information (Not a valid guard file ?)\r\n";
			goto set_guard;
		}

		$guard = new GuardDriver($guard_file);
		$tree_file = "$guard_file.txt";

		file_put_contents($tree_file, print_r($guard->getTree(), true));

		$this->ave->open_file($tree_file);

		$this->ave->exit();
	}

	public function ToolUpdateRemoveMissingAction(){
		$this->ave->clear();
		$this->ave->set_subtool("UpdateRemoveMissing");

		set_input:
		echo " Input (Folder): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder\r\n";
			goto set_input;
		}

		set_guard:
		echo " Guard (.ave-guard): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_guard;
		$guard_file = $folders[0];

		if(!file_exists($guard_file)){
			echo " Guard file not exists\r\n";
			goto set_guard;
		}

		$ini = new IniFile($guard_file, true);
		if(is_null($ini->get('keys'))){
			echo " File don't contain one of required information (Not a valid guard file ?)\r\n";
			goto set_guard;
		}

		$cwd = getcwd();
		chdir($input);
		echo " Validate files from $guard_file\r\n";
		$guard = new GuardDriver($guard_file, $ini->get('folders_to_scan'), $ini->get('files_to_scan'));
		$validation = $guard->validate(['damaged' => false, 'unknown' => false, 'missing' => true]);

		foreach($validation as $error){
			$file = $error['file'];
			$this->ave->write_log("REMOVE FILE \"$file\"");
			$key = strtoupper(hash('md5', str_replace(["\\", "/"], ":", pathinfo($file, PATHINFO_DIRNAME))));
			$arr = $ini->get($key);
			if(isset($arr[pathinfo($file, PATHINFO_BASENAME)])) unset($arr[pathinfo($file, PATHINFO_BASENAME)]);
			if(!empty($arr)){
				$ini->set($key, $arr);
			} else {
				$ini->unset($key);
				$arr = $ini->get('keys');
				if(isset($arr[$key])) unset($arr[$key]);
				$ini->set('keys', $arr);
				$arr = $ini->get('file_list');
				$key = array_search($arr, pathinfo($file, PATHINFO_DIRNAME));
				if($key !== false){
					if(isset($arr[$key])) unset($arr[$key]);
					$ini->set('file_list', $arr);
				}
			}
		}

		$ini->save();

		chdir($cwd);

		$this->ave->exit(10, true);
	}

	public function ToolUpdateAddUnknownAction(){
		$this->ave->clear();
		$this->ave->set_subtool("UpdateAddUnknown");

		set_input:
		echo " Input (Folder): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder\r\n";
			goto set_input;
		}

		set_guard:
		echo " Guard (.ave-guard): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_guard;
		$guard_file = $folders[0];

		if(!file_exists($guard_file)){
			echo " Guard file not exists\r\n";
			goto set_guard;
		}

		$ini = new IniFile($guard_file, true);
		if(is_null($ini->get('keys'))){
			echo " File don't contain one of required information (Not a valid guard file ?)\r\n";
			goto set_guard;
		}

		$cwd = getcwd();
		chdir($input);
		echo " Validate files from $guard_file\r\n";
		$guard = new GuardDriver($guard_file, $ini->get('folders_to_scan'), $ini->get('files_to_scan'));
		$validation = $guard->validate(['damaged' => false, 'unknown' => true, 'missing' => false]);

		$guard->load($ini);

		foreach($validation as $error){
			$file = $error['file'];
			$this->ave->write_log("ADD FILE \"$file\"");
			$guard->scanFile($file);
		}

		$ini->setAll($guard->get(), true);

		chdir($cwd);

		$this->ave->exit(10, true);
	}

	public function ToolUpdateChangedAction(){
		$this->ave->clear();
		$this->ave->set_subtool("UpdateChanged");

		set_input:
		echo " Input (Folder): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || !is_dir($input)){
			echo " Invalid input folder\r\n";
			goto set_input;
		}

		set_guard:
		echo " Guard (.ave-guard): ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_guard;
		$guard_file = $folders[0];

		if(!file_exists($guard_file)){
			echo " Guard file not exists\r\n";
			goto set_guard;
		}

		$ini = new IniFile($guard_file, true);
		if(is_null($ini->get('keys'))){
			echo " File don't contain one of required information (Not a valid guard file ?)\r\n";
			goto set_guard;
		}

		$cwd = getcwd();
		chdir($input);
		echo " Validate files from $guard_file\r\n";
		$guard = new GuardDriver($guard_file, $ini->get('folders_to_scan'), $ini->get('files_to_scan'));
		$validation = $guard->validate(['damaged' => true, 'unknown' => false, 'missing' => false]);

		$guard->load($ini);

		foreach($validation as $error){
			$file = $error['file'];
			$this->ave->write_log("UPDATE FILE \"$file\"");
			$guard->scanFile($file, true);
		}

		$ini->setAll($guard->get(), true);

		chdir($cwd);

		$this->ave->exit(10, true);
	}

}

?>
