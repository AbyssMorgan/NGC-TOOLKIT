<?php

declare(strict_types=1);

namespace App\Tools;

use AVE;

class MediaTools {

	private string $name = "MediaTools";

	private array $params = [];
	private string $action;
	private AVE $ave;

	public function __construct(AVE $ave){
		$this->ave = $ave;
		$this->ave->set_tool($this->name);
	}

	public function help(){
		$this->ave->print_help([
			' Actions:',
			' 0 - Merge:  Video + Audio',
		]);
	}

	public function action(string $action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_mergevideoaudio_action();
		}
		$this->ave->select_action();
	}

	public function tool_mergevideoaudio_action(){
		$this->ave->clear();
		$this->ave->set_subtool("MergeVideoAudio");

		echo " Video:  ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		$video = $folders[0];

		echo " Audio:  ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		$audio = $folders[0];

		echo " Output: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		$output = $folders[0];

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		if(!file_exists($video) || !is_dir($video)){
			$this->ave->log_error->write("INVALID INPUT \"$video\"");
		} else if(!file_exists($audio) || !is_dir($audio)){
			$this->ave->log_error->write("INVALID INPUT \"$audio\"");
		} else if(file_exists($output) && !is_dir($output)){
			$this->ave->log_error->write("INVALID OUTPUT \"$output\"");
		} else if(!file_exists($output)){
			$this->ave->mkdir($output);
		}

		if(file_exists($output)){
			$files_video = [];
			$files_audio = [];

			$files = $this->ave->getFiles($video, null, ['srt']);
			foreach($files as $file){
				$files_video[pathinfo($file, PATHINFO_FILENAME)] = $file;
			}

			$files = $this->ave->getFiles($audio, null, ['srt']);
			foreach($files as $file){
				$files_audio[pathinfo($file, PATHINFO_FILENAME)] = $file;
			}

			$items = 0;
			$total = count($files_video);
			foreach($files_video as $key => $file){
				$items++;
				if(!file_exists($file)){
					$this->ave->log_error->write("FILE NOT FOUND \"$file\"");
					$errors++;
				} else if(!isset($files_audio[$key])){
					$this->ave->log_error->write("AUDIO FILE NOT FOUND FOR \"$file\"");
					$errors++;
				} else {
					$audio = $files_audio[$key];
					$out = $output.DIRECTORY_SEPARATOR.$key.".mkv";
					if(file_exists($out)){
						$this->ave->log_error->write("FILE ALREADY EXISTS \"$out\"");
						$errors++;
					} else {
						exec("mkvmerge -o \"$out\" --no-audio --no-subtitles \"$file\" --no-video \"$audio\"");
						if(!file_exists($out)){
							$this->ave->log_error->write("FAILED MERGE FILE \"$file\" INTO \"$out\"");
							$errors++;
						} else {
							$this->ave->log_event->write("MERGE \"$file\" + \"$audio\" INTO \"$out\"");
							$progress++;
						}
					}
				}
				$this->ave->progress($items, $total);
				$this->ave->set_progress($progress, $errors);
			}
		}

		$this->ave->exit();
	}

}

?>
