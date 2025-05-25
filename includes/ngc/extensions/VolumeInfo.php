<?php

/* NGC-TOOLKIT v2.6.0 */

declare(strict_types=1);

namespace NGC\Extensions;

use NGC\Core\IniFile;

class VolumeInfo {

	private object $core;
	private array $search_folders;

	public function __construct(object $core, ?array $search_folders = null){
		$this->core = $core;
		if($this->core->get_system_type() == SYSTEM_TYPE_WINDOWS){
			$this->search_folders = $search_folders ?? [];
		} else {
			$this->search_folders = $search_folders ?? ['/media', '/mnt'];
		}
	}
	
	public function get_volume_info(string $volume_id) : ?object {
		$volumes = $this->get_volumes(false);
		return $volumes[$volume_id] ?? null;
	}

	public function get_path_by_volume_id(string $volume_id) : ?string {
		$info = $this->get_volume_info($volume_id);
		if(is_null($info)) return null;
		return $info->path;
	}

	public function get_volumes(bool $with_config = false) : array {
		$data = [];
		if($this->core->get_system_type() == SYSTEM_TYPE_WINDOWS){
			foreach($this->core->drives as $drive){
				if($this->search_volumes($data, "$drive:", $with_config) == 0){
					$items = $this->core->get_folders_ex("$drive:");
					foreach($items as $item){
						$this->search_volumes($data, $this->core->get_path($item), $with_config);
					}
				}
			}
		}
		if(!is_null($this->search_folders)){
			foreach($this->search_folders as $search_folder){
				if(!file_exists($search_folder)) continue;
				foreach($this->core->get_folders_ex($search_folder) as $drive){
					$this->search_volumes($data, $drive, $with_config);
				}
			}
		}
		return $data;
	}

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