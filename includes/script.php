<?php
	declare(strict_types=1);
	error_reporting(E_ALL);

	set_exception_handler(function(Throwable $e) : void {
		$error = $e->getMessage()."\r\n".$e->getFile().':'.$e->getLine()."\r\n".$e->getTraceAsString()."\r\n\r\n";
		echo $error."ABORT, PRESS ANY KEY TO EXIT\r\n";
		try {
			file_put_contents('NGC-SCRIPT-CRASH-'.date('Y-m-d His').'.txt', $error);
		}
		catch(Exception $e){

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
