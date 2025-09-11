<?php

/**
 * NGC-TOOLKIT v2.7.2 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Tools;

use Toolkit;

class DirectorySorter {

	private string $name = "Directory Sorter";
	private string $action;
	private Toolkit $core;

	public function __construct(Toolkit $core){
		$this->core = $core;
		$this->core->set_tool($this->name);
	}

	public function help() : void {
		$this->core->print_help([
			' Actions:',
			' 0 - Sort by items quantity (First parent)',
		]);
	}

	public function action(string $action) : bool {
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_sort_folders_items_quantity();
		}
		return false;
	}

	public function tool_sort_folders_items_quantity() : bool {
		$this->core->clear();
		$this->core->set_subtool("Sort by items quantity");

		$interval = $this->core->get_input_integer(" Quantity interval: ");
		if($interval === false) return false;

		$folders = $this->core->get_input_multiple_folders(" Folders: ");
		if($folders === false) return false;

		$errors = 0;
		$this->core->set_errors($errors);
		foreach($folders as $folder){
			$files = $this->core->get_folders($folder, false, false);
			foreach($files as $file){
				if(!file_exists($file)) continue 1;
				$quantity = count($this->core->get_files($file));
				$multiplier = floor(($quantity - 1) / $interval);
				if($quantity == 0) $multiplier = 0;
				$end = intval($interval * ($multiplier + 1));
				$new_name = $this->core->get_path("$folder/$end/".pathinfo($file, PATHINFO_BASENAME));
				if(!$this->core->move($file, $new_name)){
					$errors++;
				}
				$this->core->set_errors($errors);
			}
			unset($files);
			$this->core->set_folder_done($folder);
		}

		$this->core->open_logs(true);
		$this->core->pause(" Operation done, press any key to back to menu");
		return false;
	}

}

?>