<?php
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

	require_once "$includes_path/programs/Toolkit.php";

	require_once "$includes_path/ngc/tools/AdmFileConverter.php";
	require_once "$includes_path/ngc/tools/Settings.php";
	require_once "$includes_path/ngc/tools/CheckFileIntegrity.php";
	require_once "$includes_path/ngc/tools/DirectoryFunctions.php";
	require_once "$includes_path/ngc/tools/DirectoryNamesEditor.php";
	require_once "$includes_path/ngc/tools/FileEditor.php";
	require_once "$includes_path/ngc/tools/FileFunctions.php";
	require_once "$includes_path/ngc/tools/FileNamesEditor.php";
	require_once "$includes_path/ngc/tools/FtpTools.php";
	require_once "$includes_path/ngc/tools/MediaSorter.php";
	require_once "$includes_path/ngc/tools/MediaTools.php";
	require_once "$includes_path/ngc/tools/MySQLTools.php";

	$toolkit = new Toolkit($argv);
	if(!$toolkit->abort) $toolkit->execute();
?>