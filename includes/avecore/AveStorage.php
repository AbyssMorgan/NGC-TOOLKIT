<?php

/* AVE-PHP v2.2.4 */

declare(strict_types=1);

namespace AveCore;

use AVE;

class AveStorage {

	private aVE $ave;
	
	public function __construct(AVE $ave){
		$this->ave = $ave;
	}

	public function mysql(string $label) : IniFile {
		return new IniFile($this->ave->get_file_path("{$this->ave->app_data}/MySQL/$label.ini"), true);
	}

	public function ftp(string $label) : IniFile {
		return new IniFile($this->ave->get_file_path("{$this->ave->app_data}/FTP/$label.ini"), true);
	}

}

?>