<?php

namespace App\Tools;

use AVE;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

class FileFunctions {

	private string $name = "FileFunctions";

	private array $params = [];
	private string $action;
	private AVE $ave;

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
	}

	public function help(){
		echo " Actions:\r\n";
		echo " 0 - Anti Duplicates\r\n";
		// echo " 1 - Sort Files: Date\r\n";
		// echo " 2 - Sort Files: Extension\r\n";
		echo " 3 - Sort Video + Images\r\n";
	}

	public function action(string $action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_antiduplicates_help();
		}
		$this->ave->select_action();
	}

	public function tool_antiduplicates_help(){
		$this->ave->clear();
		$this->ave->set_tool("$this->name > AntiDuplicates");

		echo implode("\r\n",[
			' CheckSum Name   Action',
			' a1       b1     Rename',
			' a2       b2     Delete',
		]);

		echo "\r\n\r\n Mode: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();

		$this->params = [
			'mode' => strtolower($line[0] ?? '?'),
			'action' => strtolower($line[1] ?? '?'),
		];

		if(!in_array($this->params['mode'],['a','b'])) return $this->tool_antiduplicates_help();
		if(!in_array($this->params['action'],['1','2'])) return $this->tool_antiduplicates_help();
		$this->ave->set_tool("$this->name > AntiDuplicates > ".$this->tool_antiduplicates_name($this->params['mode'])." > ".$this->tool_antiduplicates_actionname($this->params['action']));
		return $this->tool_cheksum_action();
	}

	public function tool_antiduplicates_name(string $mode){
		switch($mode){
			case 'a': return 'CheckSum';
			case 'b': return 'Name';
		}
		return 'Unknown';
	}

	public function tool_antiduplicates_actionname(string $mode){
		switch($mode){
			case '1': return 'Rename';
			case '2': return 'Delete';
		}
		return 'Unknown';
	}


}

?>
