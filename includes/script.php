<?php

/**
 * NGC-TOOLKIT v2.8.0 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

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

function get_includes_list(string $path) : array {
	if(!file_exists($path)) throw new Exception("File not exists \"$path\"");
	$data = [];
	$file = fopen($path, "r");
	if(!$file) throw new Exception("Failed open \"$path\"");
	while(($line = fgets($file)) !== false){
		$data[] = trim($line);
	}
	fclose($file);
	return $data;
}

$includes_path = __DIR__;

$includes = get_includes_list("$includes_path/includes.lst");
foreach($includes as $include){
	require_once "$includes_path/$include";
}

require_once "$includes_path/ngc/extensions/Console.php";
require_once "$includes_path/programs/Script.php";

$script = new Script($argv);
$script->execute();

?>