<?php
	declare(strict_types=1);
	error_reporting(E_ALL);

	$GLOBALS['script_name'] = $argv[1] ?? '';

	set_exception_handler(function(Throwable $e) : void {
		$file = $e->getFile();
		$line_count = $e->getLine();
		if(str_contains(str_replace("\\", "/", $file), "ngc/extensions/Console.php")){
			$file = $GLOBALS['script_name'];
			$fp = fopen($file, "r");
			if($fp !== false){
				while(($line = fgets($fp)) !== false){
					if(str_contains($line, "<?")) break;
					$line_count++;
				}
				fclose($fp);
			}
		}
		$message = "\r\n";
		$message .= " File: $file\r\n";
		$message .= " Line: $line_count\r\n";
		$message .= " Error: ".$e->getMessage()."\r\n";
		$message .= " Trace: \r\n";
		$message .= preg_replace('/^/m', ' ', $e->getTraceAsString())."\r\n\r\n";
		echo $message;
		echo " ABORT, PRESS ANY KEY TO EXIT\r\n";
		if(!empty($GLOBALS['script_name'])){
			$date = date('Y-m-d His');
			@file_put_contents("{$GLOBALS['script_name']}-crash-$date.txt", $message);
		}
		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') system("PAUSE > nul");
	});

	require __DIR__.'/../vendor/autoload.php';

	$includes_path = __DIR__;

	$includes_list_file = "$includes_path/includes.lst";
	if(!file_exists($includes_list_file)) throw new Exception("File not exists includes.lst");

	$file = fopen($includes_list_file, "r");
	if(!$file) throw new Exception("Failed open includes.lst");
	while(($line = fgets($file)) !== false){
		$name = trim($line);
		require_once "$includes_path/$name";
	}
	fclose($file);

	require_once "$includes_path/ngc/extensions/Console.php";
	require_once "$includes_path/programs/Script.php";

	$script = new Script($argv);
	$script->execute();
?>