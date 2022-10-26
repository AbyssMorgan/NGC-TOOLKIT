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
			' 1 - Merge:  Video + SRT',
		]);
	}

	public function action(string $action){
		$this->params = [];
		$this->action = $action;
		switch($this->action){
			case '0': return $this->tool_mergevideoaudio_action();
			case '1': return $this->tool_mergevideosubtitles_action();
		}
		$this->ave->select_action();
	}

	public function tool_mergevideoaudio_action(){
		$this->ave->clear();
		$this->ave->set_subtool("MergeVideoAudio");

		set_video:
		echo " Video:  ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_video;
		$video = $folders[0];

		set_audio:
		echo " Audio:  ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_audio;
		$audio = $folders[0];

		set_output:
		echo " Output: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		if(!file_exists($video) || !is_dir($video)){
			echo "Invalid video folder\r\n";
			goto set_video;
		} else if(!file_exists($audio) || !is_dir($audio)){
			echo "Invalid audio folder\r\n";
			goto set_audio;
		} else if(file_exists($output) && !is_dir($output)){
			echo "Invalid output folder\r\n";
			goto set_output;
		} else if(!file_exists($output)){
			$this->ave->mkdir($output);
		}

		if(file_exists($output)){

			$files_video = [];
			$files_audio = [];

			$files = $this->ave->getFiles($video, $this->ave->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
			foreach($files as $file){
				$files_video[pathinfo($file, PATHINFO_FILENAME)] = $file;
			}

			$files = $this->ave->getFiles($audio, $this->ave->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
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
							$this->ave->log_error->write("FAILED MERGE \"$file\" + \"$audio\" INTO \"$out\"");
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

	public function tool_mergevideosubtitles_action(){
		$this->ave->clear();
		$this->ave->set_subtool("MergeVideoSubtitles");

		set_input:
		echo " Input:  ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_input;
		$input = $folders[0];

		set_output:
		echo " Output: ";
		$line = $this->ave->get_input();
		if($line == '#') return $this->ave->select_action();
		$folders = $this->ave->get_folders($line);
		if(!isset($folders[0])) goto set_output;
		$output = $folders[0];

		$progress = 0;
		$errors = 0;
		$this->ave->set_progress($progress, $errors);

		if(!file_exists($input) || !is_dir($input)){
			echo "Invalid input folder\r\n";
			goto set_input;
		} else if(file_exists($output) && !is_dir($output)){
			echo "Invalid output folder\r\n";
			goto set_output;
		} else if(!file_exists($output)){
			$this->ave->mkdir($output);
		}

		if(file_exists($output)){
			$lang = $this->ave->config->get('AVE_SUBTITLES_LANGUAGE');
			$files = $this->ave->getFiles($input, $this->ave->mkvmerge->get('MKV_MERGE_SUPPORTED_FILES'), ['srt']);
			$items = 0;
			$total = count($files);
			foreach($files as $file){
				$items++;
				if(!file_exists($file)) continue;
				$srt = pathinfo($file, PATHINFO_DIRNAME).DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_FILENAME).".srt";
				$out = $output.DIRECTORY_SEPARATOR.pathinfo($file, PATHINFO_BASENAME);
				if(file_exists($out)){
					$this->ave->log_error->write("FILE ALREADY EXISTS \"$out\"");
					$errors++;
				} else if(!file_exists($srt)){
					$this->ave->log_error->write("FILE NOT EXISTS \"$srt\"");
					$errors++;
				} else {
					exec("mkvmerge -o \"$out\" --default-track 0 --sub-charset 0:UTF-8 --language 0:$lang \"$srt\" \"$file\"");
					if(!file_exists($out)){
						$this->ave->log_error->write("FAILED MERGE \"$file\" + \"$srt\" INTO \"$out\"");
						$errors++;
					} else {
						$this->ave->log_event->write("MERGE \"$file\" + \"$srt\" INTO \"$out\"");
						$progress++;
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
