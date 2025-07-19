<?php

/**
 * NGC-TOOLKIT v2.7.1 – Component
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
use NGC\Core\IniFile;

/**
 * Provides functionality to retrieve information about volumes (drives/mounts).
 */
class VolumeInfo {

	/**
	 * The core toolkit or script instance.
	 * @var Toolkit|Script
	 */
	private Toolkit|Script $core;

	/**
	 * An array of folders to search for volumes.
	 * @var array
	 */
	private array $search_folders;

	/**
	 * Constructor for the VolumeInfo class.
	 *
	 * @param Toolkit|Script $core The core toolkit or script instance.
	 * @param array|null $search_folders An optional array of folders to search for volumes.
	 * If null, default search folders are used based on the system type.
	 */
	public function __construct(Toolkit|Script $core, ?array $search_folders = null){
		$this->core = $core;
		if($this->core->get_system_type() == SYSTEM_TYPE_WINDOWS){
			$this->search_folders = $search_folders ?? [];
		} else {
			$this->search_folders = $search_folders ?? ['/media', '/mnt'];
		}
	}

	/**
	 * Retrieves information for a specific volume by its ID.
	 *
	 * @param string $volume_id The ID of the volume to retrieve information for.
	 * @return object|null An object containing volume information (path, name) or null if not found.
	 */
	public function get_volume_info(string $volume_id) : ?object {
		$volumes = $this->get_volumes(false);
		return $volumes[$volume_id] ?? null;
	}

	/**
	 * Retrieves the path of a volume by its ID.
	 *
	 * @param string $volume_id The ID of the volume to retrieve the path for.
	 * @return string|null The path of the volume or null if not found.
	 */
	public function get_path_by_volume_id(string $volume_id) : ?string {
		$info = $this->get_volume_info($volume_id);
		if(is_null($info)) return null;
		return $info->path;
	}

	/**
	 * Retrieves a list of all detected volumes.
	 *
	 * @param bool $with_config If true, the IniFile object for the volume's configuration will be included.
	 * @return array An associative array where keys are volume IDs and values are objects containing volume information.
	 */
	public function get_volumes(bool $with_config = false) : array {
		$data = [];
		if($this->core->get_system_type() == SYSTEM_TYPE_WINDOWS){
			foreach($this->core->drives as $drive){
				if($this->search_volumes($data, "$drive:", $with_config) == 0){
					$items = $this->core->get_folders("$drive:", false, false);
					foreach($items as $item){
						$this->search_volumes($data, $this->core->get_path($item), $with_config);
					}
				}
			}
		}
		if(!is_null($this->search_folders)){
			foreach($this->search_folders as $search_folder){
				if(!file_exists($search_folder)) continue;
				foreach($this->core->get_folders($search_folder, false, false) as $drive){
					$this->search_volumes($data, $drive, $with_config);
				}
			}
		}
		return $data;
	}

	/**
	 * Searches for volume information within a given path.
	 *
	 * This private method checks if a ".Volume/VolumeInfo.ini" file exists in the specified path,
	 * extracts volume information, and adds it to the provided data array.
	 *
	 * @param array $data The array to populate with volume information.
	 * @param string $path The path to search for volume information.
	 * @param bool $with_config If true, the IniFile object for the volume's configuration will be included.
	 * @return int The number of volumes found in the given path (0 or 1).
	 */
	private function search_volumes(array &$data, string $path, bool $with_config) : int {
		$count = 0;
		$ini_file = $this->core->get_path("$path/.Volume/VolumeInfo.ini");
		if(file_exists($ini_file)){
			$ini = new IniFile($ini_file);
			$volume_id = $ini->get('volume_id');
			$item = [
				'path' => $path,
				'name' => $ini->get('name'),
			];
			if($with_config) $item['config'] = $ini;
			$data[$volume_id] = (object)$item;
			$count++;
		}
		return $count;
	}

}

?>