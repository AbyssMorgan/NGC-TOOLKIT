<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;

class AveConsole {

	private AVE $ave;
	public string $script;
	public string $path;

	public function __construct(AVE $ave){
		$this->ave = $ave;
	}

	public function execute(string $path) : bool {
		$this->ave->title($path);
		$this->script = $path;
		$this->path = pathinfo($path, PATHINFO_DIRNAME);
		chdir($this->path);
		eval(str_replace(["?>", "<?php", "<?"], "", file_get_contents($path)));
		chdir($this->ave->path);
		return true;
	}

}

?>
