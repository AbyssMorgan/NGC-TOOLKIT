<?php

declare(strict_types=1);

namespace App\Services;

class GuardPattern {

	private array $folders = [];
	private array $files = [];
	private string $file;
	private string $input;

	public function __construct(array $folders = [], array $files = []){
		$this->folders = $folders;
		$this->files = $files;
	}

	public function addFolders(array|string $folders) : void {
		if(gettype($folders) == 'string') $folders = [$folders];
		$this->folders = array_unique(array_merge($this->folders, $folders));
	}

	public function setFolders(array|string $folders) : void {
		if(gettype($folders) == 'string') $folders = [$folders];
		$this->folders = $folders;
	}

	public function getFolders() : array {
		return $this->folders;
	}

	public function addFiles(array|string $files) : void {
		if(gettype($files) == 'string') $files = [$files];
		$this->files = array_unique(array_merge($this->files, $files));
	}

	public function setFiles(array|string $files) : void {
		if(gettype($files) == 'string') $files = [$files];
		$this->files = $files;
	}

	public function getFiles() : array {
		return $this->files;
	}

	public function getInput() : string {
		return rtrim($this->input, "\\/");
	}

	public function setInput(string $input) : void {
		$this->input = $input;
	}

	public function get() : string {
		$data = ['input:'.$this->getInput()];
		foreach($this->getFolders() as $folder){
			array_push($data, "folder:$folder");
		}
		foreach($this->getFiles() as $file){
			array_push($data, "file:$file");
		}
		return implode("\r\n", $data);
	}

	public function load(string $pattern) : void {
		$data = explode("\n", str_replace(["\r\n","\r"],"\n", $pattern));
		$folders = [];
		$files = [];
		foreach($data as $pat){
			if(substr($pat, 0, 7) == 'folder:'){
				array_push($folders, substr($pat, 7));
			} else if(substr($pat, 0, 5) == 'file:'){
				array_push($files, substr($pat, 5));
			} else if(substr($pat, 0, 6) == 'input:'){
				$this->setInput(substr($pat, 6));
			}
		}
		$this->setFolders($folders);
		$this->setFiles($files);
	}

}

?>
