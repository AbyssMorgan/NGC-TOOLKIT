<?php

/* AVE-PHP v2.2.2 */

declare(strict_types=1);

namespace AveCore;

class ADM {

	private BinaryFile $index;
	private BinaryFile $container;
	private ?string $path;
	private bool $is_open_index;
	private bool $is_open_container;
	private bool $is_changed;
	private array $allocation;

	const ADM_HEADER_INDEX = 		'ADM_V1_INDEX:';
	const ADM_HEADER_CONTAINER =	'ADM_V1_CONTAINER:';
	
	public function __construct(){
		$this->container = new BinaryFile();
		$this->index = new BinaryFile();
		$this->is_changed = false;
		$this->is_open_index = false;
		$this->is_open_container = false;
		$this->allocation = [
			'files' => [],
			'free_offset' => strlen(self::ADM_HEADER_CONTAINER),
		];
	}

	public function open(string $path) : bool {
		if($this->is_open_index) return false;
		$index_file = pathinfo($path, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.pathinfo($path, PATHINFO_FILENAME)."-index.adm";		
		if(!$this->index->open($index_file)) return false;
		$this->is_open_index = true;
		$this->path = $path;
		if($this->index->size() == 0){
			$this->index->write(self::ADM_HEADER_INDEX, 0, strlen(self::ADM_HEADER_INDEX));
			$this->save_index(true);
		}
		$this->allocation = $this->get_index();
		return true;
	}

	public function close() : bool {
		if(!$this->is_open()) return false;
		if(!$this->save_index()) return false;
		$this->index->close();
		if($this->is_open_container) $this->container->close();
		$this->path = null;
		$this->is_changed = false;
		$this->is_open_index = false;
		$this->is_open_container = false;
		return true;
	}

	public function is_open() : bool {
		return $this->is_open_index;
	}

	public function get_allocation() : array {
		return $this->allocation;
	}

	public function import(string $path, ?string $internal_name = null, bool $save = true) : bool {
		if(!$this->is_open() || !file_exists($path) || is_dir($path)) return false;
		if(is_null($internal_name)) $internal_name = pathinfo($path, PATHINFO_BASENAME);
		if($this->has_file($internal_name)) return false;
		$offset = $this->allocation['free_offset'];
		$length = filesize($path);
		$content = file_get_contents($path);
		if(!$this->open_container()) return false;
		$this->container->write($content, $offset, $length);
		$this->allocation['free_offset'] += $length;
		$this->allocation['files'][$internal_name] = [
			'offset' => $offset,
			'length' => $length,
			'path' => $path,
			'name' => pathinfo($path, PATHINFO_BASENAME),
		];
		if($save){
			$this->save_index(true);
		} else {
			$this->is_changed = true;
		}
		return true;
	}

	public function export(string $path, string $internal_name, bool $restore_original_name = false) : bool {
		if(!$this->is_open() || !$this->has_file($internal_name)) return false;
		if(is_dir($path) || $restore_original_name){
			$path = $path.DIRECTORY_SEPARATOR.$this->get_file_info($internal_name, 'name');
		}
		if(file_exists($path)) return false;
		if(!$this->open_container()) return false;
		$content = $this->container->read($this->get_file_info($internal_name, 'offset'), $this->get_file_info($internal_name, 'length'));
		$buffer = new BinaryFile();
		$buffer->open($path, $this->get_file_info($internal_name, 'length'));
		$buffer->write($content, 0, $this->get_file_info($internal_name, 'length'));
		$buffer->close();
		return true;
	}

	public function delete(string $internal_name, bool $save = true) : bool {
		if(!$this->is_open() || !$this->has_file($internal_name)) return false;
		if(!$this->open_container()) return false;
		$this->container->write(str_repeat("\0", $this->get_file_info($internal_name, 'length')), $this->get_file_info($internal_name, 'offset'), $this->get_file_info($internal_name, 'length'));
		unset($this->allocation['files'][$internal_name]);
		if($save){
			$this->save_index(true);
		} else {
			$this->is_changed = true;
		}
		return true;
	}

	public function has_file(string $internal_name) : bool {
		if(!$this->is_open()) return false;
		return isset($this->allocation['files'][$internal_name]);
	}

	public function get_internal_names() : array|false {
		if(!$this->is_open()) return false;
		return array_keys($this->allocation['files']);
	}

	public function get_file_info(string $internal_name, string $key = null) : mixed {
		if(!$this->is_open()) return false;
		if(is_null($key)) return $this->allocation['files'][$internal_name];
		return $this->allocation['files'][$internal_name][$key] ?? false;
	}

	public function save_index(bool $force = false) : bool {
		if(!$this->is_open()) return false;
		if(!$this->is_changed && !$force) return true;
		$data = gzcompress(json_encode($this->allocation));
		if($this->index->write($data, strlen(self::ADM_HEADER_INDEX), strlen(bin2hex($data)) / 2) === false) return false;
		$this->is_changed = false;
		return true;		
	}

	public function get_index() : array|false {
		if(!$this->is_open()) return false;
		$allocation = json_decode(gzuncompress($this->index->read(strlen(self::ADM_HEADER_INDEX))), true);
		$keys = array_column($allocation['files'], 'offset');
		array_multisort($keys, SORT_ASC, SORT_NUMERIC, $allocation['files']);
		return $allocation;
	}

	public function compact() : bool {
		if(!$this->is_open()) return false;
		if(!$this->open_container()) return false;
		$new_offset = strlen(self::ADM_HEADER_CONTAINER);
		foreach($this->allocation['files'] as $internal_name => $file){
			$content = $this->container->read($file['offset'], $file['length']);
			$this->container->write($content, $new_offset, $file['length']);
			$this->allocation['files'][$internal_name]['offset'] = $new_offset;
			$this->allocation['files'][$internal_name]['length'] = $file['length'];
			$new_offset += $file['length'];
			$this->save_index(true);
		}
		$this->allocation['free_offset'] = $new_offset;
		$this->save_index(true);
		$this->container->truncate($new_offset);
		return true;
	}

	private function open_container() : bool {
		if($this->is_open_container) return true;
		if(!$this->container->open($this->path)) return false;
		if($this->container->size() == 0){
			$this->container->write(self::ADM_HEADER_CONTAINER, 0, strlen(self::ADM_HEADER_CONTAINER));
		}
		$this->is_open_container = true;
		return true;
	}

}

?>