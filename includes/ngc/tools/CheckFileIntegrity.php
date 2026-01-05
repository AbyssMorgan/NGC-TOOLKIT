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

namespace NGC\Tools;

use Toolkit;
use NGC\Services\GuardPattern;
use NGC\Services\GuardDriver;
use NGC\Core\IniFile;

class CheckFileIntegrity {

	private string $name = "Check File Integrity";
	private string $action;
	private Toolkit $core;

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
	}

	public function help() : void {
		$this->core->print_help([
			' Actions:',
			' 0 - Create pattern',
			' 1 - Generate guard',
			' 2 - Check integrity',
			' 3 - Get files tree',
			' 4 - Update guard: Remove missing files',
			' 5 - Update guard: Add unknown files',
			' 6 - Update guard: Update changed files',
			' 7 - Update guard: Update missing + unknown',
		]);
	}

	public function action(string $action) : bool {
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_create_pattern();
			case '1': return $this->tool_guard_generate();
			case '2': return $this->tool_check_integrity();
			case '3': return $this->tool_get_files_tree();
			case '4': return $this->tool_update_remove_missing();
			case '5': return $this->tool_update_add_unknown();
			case '6': return $this->tool_update_changed();
			case '7': return $this->tool_update_missing_and_unknown();
		}
		return false;
	}

	public function tool_create_pattern() : bool {
		$this->core->clear();
		$this->core->set_subtool("Create pattern");

		$input = $this->core->get_input_folder(" Input (Folder): ");
		if($input === false) return false;

		$output = $this->core->get_input_folder(" Output (Folder): ", true);
		if($output === false) return false;

		set_name:
		$line = $this->core->get_input(" Name: ");
		if($line == '#') return false;

		$pattern_file = $this->core->parse_input_path($line);
		if(!isset($pattern_file[0])) goto set_name;
		$pattern_file = \preg_replace('/[^A-Za-z0-9_\-]/', '_', $pattern_file[0]).".ngc-pat";

		$pattern = new GuardPattern();
		$pattern->set_input($input);

		set_folders:
		$line = $this->core->get_input(" Folders: ");
		if($line == '#') return false;
		foreach($this->core->parse_input_path($line) as $folder){
			$pattern->add_folders(\str_replace([$input.DIRECTORY_SEPARATOR, $input], "", $folder));
		}

		if(!empty($line)){
			if($this->core->get_confirm(" More (Y/N): ")) goto set_folders;
		}

		set_files:
		$line = $this->core->get_input(" Files: ");
		if($line == '#') return false;
		foreach($this->core->parse_input_path($line) as $file){
			$pattern->add_files(\str_replace([$input.DIRECTORY_SEPARATOR, $input], "", $file));
		}

		if(!empty($line)){
			if($this->core->get_confirm(" More (Y/N): ")) goto set_files;
		}

		$file_name = $this->core->get_path("$output/$pattern_file");

		\file_put_contents($file_name, $pattern->get());

		$this->core->open_logs(false);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_guard_generate() : bool {
		$this->core->clear();
		$this->core->set_subtool("Guard generate");

		set_pattern:
		$pattern_file = $this->core->get_input_file(" Pattern (.ngc-pat): ", true);
		if($pattern_file === false) return false;

		$pattern = new GuardPattern();
		$pattern->load(\file_get_contents($pattern_file));

		$input = $pattern->get_input();
		if(!\file_exists($input) || !\is_dir($input) || empty($input)){
			$this->core->echo(" Invalid input folder: \"$input\"");
			goto set_pattern;
		}

		$files = \count($pattern->get_files());
		$folders = \count($pattern->get_folders());
		$this->core->echo(" Loaded $folders folders and $files files");

		$guard_file = \str_replace(\chr(0x5C).\chr(0x5C), \chr(0x5C), $this->core->get_path("$input/".\pathinfo($pattern_file, PATHINFO_FILENAME).".ngc-guard"));

		$cwd = \getcwd();
		\chdir($input);
		$this->core->echo(" Generate $guard_file");
		$guard = new GuardDriver($guard_file, $pattern->get_folders(), $pattern->get_files());
		$guard->generate();
		\chdir($cwd);

		$this->core->open_logs(false);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_check_integrity() : bool {
		$this->core->clear();
		$this->core->set_subtool("Check integrity");

		set_guard:
		$guard_file = $this->core->get_input_file(" Guard (.ngc-guard): ", true);
		if($guard_file === false) return false;

		$ini = new IniFile($guard_file, true);
		if(\is_null($ini->get('keys'))){
			$this->core->echo(" File don't contain one of required information (Not a valid guard file ?)");
			goto set_guard;
		}

		$input = \pathinfo($guard_file, PATHINFO_DIRNAME);

		$cwd = \getcwd();
		\chdir($input);
		$this->core->echo(" Validate files from $guard_file");
		$guard = new GuardDriver($guard_file, $ini->get('folders_to_scan'), $ini->get('files_to_scan'));
		$validation = $guard->validate();
		\chdir($cwd);

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
					\array_push($errors['damaged'], $error['file']);
					break;
				}
				case 'unknown': {
					$unknown++;
					\array_push($errors['unknown'], $error['file']);
					break;
				}
				case 'missing': {
					$missing++;
					\array_push($errors['missing'], $error['file']);
					break;
				}
			}
		}

		$this->core->write_data("State: $damaged damaged, $missing missing, $unknown unknown");
		$this->core->write_data(["", "Damaged:"]);
		$this->core->write_data($errors['damaged'] ?? []);
		$this->core->write_data(["", "Missing:"]);
		$this->core->write_data($errors['missing'] ?? []);
		$this->core->write_data(["", "Unknown:"]);
		$this->core->write_data($errors['unknown'] ?? []);

		$this->core->open_logs(false);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_get_files_tree() : bool {
		$this->core->clear();
		$this->core->set_subtool("Get files tree");

		set_guard:
		$guard_file = $this->core->get_input_file(" Guard (.ngc-guard): ", true);
		if($guard_file === false) return false;

		$ini = new IniFile($guard_file, true);
		if(\is_null($ini->get('keys'))){
			$this->core->echo(" File don't contain one of required information (Not a valid guard file ?)");
			goto set_guard;
		}

		$guard = new GuardDriver($guard_file);
		$tree_file = "$guard_file.txt";

		\file_put_contents($tree_file, \print_r($guard->get_tree(), true));

		$this->core->open_file($tree_file);

		$this->core->open_logs(false);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_update_remove_missing() : bool {
		$this->core->clear();
		$this->core->set_subtool("Update remove missing");
		$guard_file = $this->tool_guard_set_file();
		if(\is_null($guard_file)) return false;
		$this->tool_guard_update($guard_file, ['damaged' => false, 'unknown' => false, 'missing' => true]);
		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_update_add_unknown() : bool {
		$this->core->clear();
		$this->core->set_subtool("Update add unknown");
		$guard_file = $this->tool_guard_set_file();
		if(\is_null($guard_file)) return false;
		$this->tool_guard_update($guard_file, ['damaged' => false, 'unknown' => true, 'missing' => false]);
		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_update_changed() : bool {
		$this->core->clear();
		$this->core->set_subtool("Update changed");
		$guard_file = $this->tool_guard_set_file();
		if(\is_null($guard_file)) return false;
		$this->tool_guard_update($guard_file, ['damaged' => true, 'unknown' => false, 'missing' => false]);
		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_update_missing_and_unknown() : bool {
		$this->core->clear();
		$this->core->set_subtool("Update missing and unknown");
		$guard_file = $this->tool_guard_set_file();
		if(\is_null($guard_file)) return false;
		$this->tool_guard_update($guard_file, ['damaged' => false, 'unknown' => true, 'missing' => true]);
		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_guard_set_file() : ?string {
		set_guard:
		$guard_file = $this->core->get_input_file(" Guard (.ngc-guard): ", true);
		if($guard_file === false) return null;

		$ini = new IniFile($guard_file, true, true);
		if(\is_null($ini->get('keys'))){
			$this->core->echo(" File don't contain one of required information (Not a valid guard file ?)");
			goto set_guard;
		}

		return $guard_file;
	}

	public function tool_guard_update(string $guard_file, array $params) : void {
		$ini = new IniFile($guard_file, true, true);
		$input = \pathinfo($guard_file, PATHINFO_DIRNAME);
		$cwd = \getcwd();
		\chdir($input);
		$this->core->echo(" Validate files from $guard_file");
		$guard = new GuardDriver($guard_file, $ini->get('folders_to_scan'), $ini->get('files_to_scan'));
		$validation = $guard->validate($params);

		$guard->load($ini);

		foreach($validation as $error){
			$file = $error['file'];
			switch($error['type']){
				case 'unknown': {
					$this->core->write_log("ADD FILE \"$file\"");
					$guard->scan_file($file);
					break;
				}
				case 'damaged': {
					$this->core->write_log("UPDATE FILE \"$file\"");
					$guard->scan_file($file, true);
					break;
				}
				case 'missing': {
					$this->core->write_log("REMOVE FILE \"$file\"");
					$key = \strtoupper(\hash('md5', \str_replace(["\\", "/"], ":", \pathinfo($file, PATHINFO_DIRNAME))));
					if(isset($guard->data[$key][\pathinfo($file, PATHINFO_BASENAME)])) unset($guard->data[$key][\pathinfo($file, PATHINFO_BASENAME)]);
					if(empty($guard->data[$key])){
						if(isset($guard->data[$key])) unset($guard->data[$key]);
					}
					if(isset($guard->keys[$key])) unset($guard->keys[$key]);
					$key = \array_search($file, $guard->file_list);
					if($key !== false){
						if(isset($guard->file_list[$key])) unset($guard->file_list[$key]);
					}
					break;
				}
			}
		}

		$ini->set_all($guard->get(), true);

		\chdir($cwd);
	}

}

?>