<?php

/**
 * NGC-TOOLKIT v2.7.0 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Extensions;

use Script;
use Toolkit;

/**
 * Class responsible for running .ngcs scripts
 */
class Console {

	/**
	 * The core toolkit or script instance.
	 * @var Toolkit|Script
	 */
	private Toolkit|Script $core;

	/**
	 * Path of script that execute
	 * @var string
	 */
	public string $script;

	/**
	 * Path of script directory
	 * @var string
	 */
	public string $path;

	/**
	 * Constructor for the Console class.
	 * @param \Toolkit|\Script $core
	 */
	public function __construct(Toolkit|Script $core){
		$this->core = $core;
	}

	/**
	 * Execute .ngcs script file
	 * @param string $path The path to the .ngcs file.
	 * @return bool
	 */
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