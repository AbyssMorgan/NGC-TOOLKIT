<?php

/* NGC-TOOLKIT v2.6.0 */

declare(strict_types=1);

namespace NGC\Extensions;

class Console {

	private object $core;
	public string $script;
	public string $path;

	public function __construct(object $core){
		$this->core = $core;
	}

	public function execute(string $path) : bool {
		$this->core->title($path);
		$this->core->clear();
		$this->script = $path;
		$this->path = pathinfo($path, PATHINFO_DIRNAME);
		$content = file_get_contents($path);
		if(strpos($content, "@AppType NGC_SCRIPT") === false){
			$this->core->echo();
			$this->core->echo(" File \"$path\" is not a valid {$this->core->app_name} Script");
			$this->core->echo();
			$this->core->pause(" Press any key to close");
			return false;
		}
		$params = explode(" ", explode("\n", $content, 2)[0]);
		if($this->core->get_version_number($this->core->version) < $this->core->get_version_number($params[2])){
			$this->core->echo();
			$this->core->echo(" File \"$path\" require NGC-TOOLKIT v{$params[2]}+");
			$this->core->echo();
			$this->core->pause(" Press any key to close");
			return false;
		}
		$pos = strpos($content, "<?");
		$content = substr($content, $pos);
		chdir($this->path);
		eval(str_replace(["?>", "<?php", "<?"], "", $content));
		chdir($this->core->path);
		return true;
	}

}

?>