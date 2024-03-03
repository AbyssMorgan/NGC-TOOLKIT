<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;
use Exception;
use AveCore\IniFile;
use AveCore\JournalService;

class AdmFileConverter {

	private string $name = "ADM File Converter";
	private array $params = [];
	private string $action;
	private AVE $ave;

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
	}

	public function help() : void {
		$this->ave->print_help([
			' Actions:',
			' 0 - Ini converter (INI <=> GZ-INI)',
			' 1 - ADM Journal converter (ADM-JOURNAL => Text)',
		]);
	}

	public function action(string $action) : bool {
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->ToolIniConverter();
			case '1': return $this->ToolAdmJournalConverter();
		}
		return false;
	}

	public function ToolIniConverter() : bool {
		$this->ave->set_subtool("Ini converter");

		set_mode:
		$this->ave->clear();
		$this->ave->print_help([
			' Modes:',
			' 0 - Convert INI to GZ-INI',
			' 1 - Convert GZ-INI to INI',
			' 2 - Print GZ-INI/INI',
		]);

		$line = $this->ave->get_input(" Mode: ");
		if($line == '#') return false;

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
		];

		if(!in_array($this->params['mode'], ['0', '1', '2'])) goto set_mode;

		$this->ave->clear();

		set_input:
		$line = $this->ave->get_input(" Input: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || is_dir($input)){
			$this->ave->echo(" Invalid input file");
			goto set_input;
		}

		try {
			$ini = new IniFile($input, true, $this->params['mode'] == '0');
		}
		catch(Exception $e){
			$this->ave->echo(" Failed parse file: ".$e->getMessage());
			goto set_input;
		}

		if($this->params['mode'] == '2'){
			$this->ave->write_data(print_r($ini->get_all(), true));
			$this->ave->open_logs();
		} else {
			set_output:
			$line = $this->ave->get_input(" Output: ");
			if($line == '#') return false;
			$folders = $this->ave->get_input_folders($line);
			if(!isset($folders[0])) goto set_output;
			$output = $folders[0];
	
			if(file_exists($output) && is_dir($output)){
				$this->ave->echo(" Invalid output file");
				goto set_output;
			}
	
			if(file_exists($output)){
				if(!$this->ave->get_confirm(" Output file exists, overwrite (Y/N): ")) goto set_output;
			}
	
			if($input == $output){
				$ini->save();
			} else {
				$new = new IniFile($output, true, $this->params['mode'] == '0');
				$new->set_all($ini->get_all());
				$new->save();
			}
		}

		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

	public function ToolAdmJournalConverter() : bool {
		$this->ave->clear();
		$this->ave->set_subtool("ADM Journal converter");

		set_input:
		$line = $this->ave->get_input(" Input: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		if(!file_exists($input) || is_dir($input)){
			$this->ave->echo(" Invalid input file");
			goto set_input;
		}

		try {
			$journal = new JournalService($input);
		}
		catch(Exception $e){
			$this->ave->echo(" Failed parse file: ".$e->getMessage());
			goto set_input;
		}

		set_output:
		$line = $this->ave->get_input(" Output: ");
		if($line == '#') return false;
		$folders = $this->ave->get_input_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		if(file_exists($output) && is_dir($output)){
			$this->ave->echo(" Invalid output file");
			goto set_output;
		}

		if(file_exists($output)){
			if(!$this->ave->get_confirm(" Output file exists, overwrite (Y/N): ")) goto set_output;
		}

		$directory = pathinfo($output, PATHINFO_DIRNAME);
		if(!file_exists($directory) && !$this->ave->mkdir($directory)){
			$this->ave->echo(" Failed create destination directory \"$directory\"");
			goto set_output;
		}
		
		file_put_contents($output, implode("\r\n", $journal->read()));

		$this->ave->pause(" Operation done, press any key to back to menu");
		return false;
	}

}

?>
