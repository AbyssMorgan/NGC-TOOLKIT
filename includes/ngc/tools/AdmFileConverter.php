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

namespace NGC\Tools;

use Toolkit;
use Exception;
use NGC\Core\IniFile;
use NGC\Core\JournalService;

class AdmFileConverter {

	private string $name = "ADM File Converter";
	private string $action;
	private Toolkit $core;

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
	}

	public function help() : void {
		$this->core->print_help([
			' Actions:',
			' 0 - Ini converter (INI <=> GZ-INI)',
			' 1 - ADM Journal converter (ADM-JOURNAL => Text)',
		]);
	}

	public function action(string $action) : bool {
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_ini_converter();
			case '1': return $this->tool_adm_journal_converter();
		}
		return false;
	}

	public function tool_ini_converter() : bool {
		$this->core->set_subtool("Ini converter");

		set_mode:
		$this->core->clear();
		$this->core->print_help([
			' Modes:',
			' 0 - Convert INI to GZ-INI',
			' 1 - Convert GZ-INI to INI',
			' 2 - Print GZ-INI/INI',
		]);

		$line = $this->core->get_input(" Mode: ");
		if($line == '#') return false;

		$params = [
			'mode' => $line[0] ?? '?',
		];

		if(!in_array($params['mode'], ['0', '1', '2'])) goto set_mode;

		$this->core->clear();

		set_input:
		$input = $this->core->get_input_file(" Input (File): ", true);
		if($input === false) return false;

		try {
			$ini = new IniFile($input, true, $params['mode'] == '0');
		}
		catch(Exception $e){
			$this->core->echo(" Failed parse file: ".$e->getMessage());
			goto set_input;
		}

		if($params['mode'] == '2'){
			$this->core->write_data(print_r($ini->get_all(), true));
			$this->core->open_logs();
		} else {
			set_output:
			$output = $this->core->get_input_file(" Output (File): ", false, true);
			if($output === false) return false;

			if(file_exists($output)){
				if(!$this->core->get_confirm(" Output file exists, overwrite (Y/N): ")) goto set_output;
			}

			if($input == $output){
				$ini->save();
			} else {
				$new = new IniFile($output, true, $params['mode'] == '0');
				$new->set_all($ini->get_all());
				$new->save();
			}
		}

		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function tool_adm_journal_converter() : bool {
		$this->core->clear();
		$this->core->set_subtool("ADM Journal converter");

		set_input:
		$input = $this->core->get_input_file(" Input (File): ", true);
		if($input === false) return false;

		try {
			$journal = new JournalService($input);
		}
		catch(Exception $e){
			$this->core->echo(" Failed parse file: ".$e->getMessage());
			goto set_input;
		}

		set_output:
		$output = $this->core->get_input_file(" Output (File): ", false, true);
		if($output === false) return false;

		if(file_exists($output)){
			if(!$this->core->get_confirm(" Output file exists, overwrite (Y/N): ")) goto set_output;
		}

		file_put_contents($output, implode("\r\n", $journal->read()));

		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

}

?>