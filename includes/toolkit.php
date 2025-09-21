<?php

/**
 * NGC-TOOLKIT v2.7.3 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

error_reporting(E_ALL);

set_exception_handler(function(Throwable $e) : void {
	$file = $e->getFile();
	$line_count = $e->getLine();
	$message = "\r\n";
	$message .= " File: $file\r\n";
	$message .= " Line: $line_count\r\n";
	$message .= " Error: ".$e->getMessage()."\r\n";
	$message .= " Trace: \r\n";
	$message .= preg_replace('/^/m', ' ', $e->getTraceAsString())."\r\n\r\n";
	echo $message;
	echo " ABORT, PRESS ANY KEY TO EXIT\r\n";
	if(file_exists('.git')){
		file_put_contents('NGC-TOOLKIT-CRASH-'.date('Y-m-d His').'.txt', $message);
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

$includes = array_merge(get_includes_list("$includes_path/includes.lst"), get_includes_list("$includes_path/tools.lst"));
foreach($includes as $include){
	require_once "$includes_path/$include";
}

require_once "$includes_path/programs/Toolkit.php";

$toolkit = new Toolkit($argv);
if(!$toolkit->abort) $toolkit->execute();

?>