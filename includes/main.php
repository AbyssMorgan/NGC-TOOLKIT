<?php
	declare(strict_types=1);
	error_reporting(E_ALL);

	set_exception_handler(function(Throwable $e){
		$error = $e->getMessage()."\r\n".$e->getFile().':'.$e->getLine()."\r\n".$e->getTraceAsString()."\r\n\r\n";
		echo $error."ABORT, PRESS ENTER TO EXIT\r\n";
		if(file_exists('.git')){
			file_put_contents('AVE-PHP-CRASH-'.date('Y-m-d His').'.txt', $error);
		}
		system("PAUSE > nul");
	});

	$includes_path = __DIR__;
	require_once("$includes_path/dictionaries/MediaOrientation.php");
	require_once("$includes_path/services/Logs.php");
	require_once("$includes_path/services/IniFile.php");
	require_once("$includes_path/services/AveCore.php");
	require_once("$includes_path/services/GuardPattern.php");
	require_once("$includes_path/services/GuardDriver.php");
	require_once("$includes_path/services/FaceDetector.php");
	require_once("$includes_path/services/DataBase.php");
	require_once("$includes_path/services/DataBaseBackup.php");
	require_once("$includes_path/services/Request.php");
	require_once("$includes_path/services/MediaFunctions.php");
	require_once("$includes_path/services/StringConverter.php");
	require_once("$includes_path/AVE.php");
	require_once("$includes_path/tools/AveSettings.php");
	require_once("$includes_path/tools/NamesGenerator.php");
	require_once("$includes_path/tools/FileFunctions.php");
	require_once("$includes_path/tools/MediaSorter.php");
	require_once("$includes_path/tools/DirectoryFunctions.php");
	require_once("$includes_path/tools/MediaTools.php");
	require_once("$includes_path/tools/CheckFileIntegrity.php");
	require_once("$includes_path/tools/MySQLTools.php");

	$ave = new AVE($argv);
	if(!$ave->abort) $ave->execute();
?>
