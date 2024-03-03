<?php
	declare(strict_types=1);
	error_reporting(E_ALL);

	set_exception_handler(function(Throwable $e){
		$error = $e->getMessage()."\r\n".$e->getFile().':'.$e->getLine()."\r\n".$e->getTraceAsString()."\r\n\r\n";
		echo $error."ABORT, PRESS ANY KEY TO EXIT\r\n";
		if(file_exists('.git')){
			file_put_contents('AVE-PHP-CRASH-'.date('Y-m-d His').'.txt', $error);
		}
		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') system("PAUSE > nul");
	});

	require __DIR__.'/../vendor/autoload.php';

	$includes_path = __DIR__;
	
	require_once("$includes_path/avecore/Logs.php");
	require_once("$includes_path/avecore/IniFile.php");
	require_once("$includes_path/avecore/Core.php");
	require_once("$includes_path/avecore/MySQL.php");
	require_once("$includes_path/avecore/Request.php");
	require_once("$includes_path/avecore/FtpService.php");
	require_once("$includes_path/avecore/BitFunctions.php");
	require_once("$includes_path/avecore/BitArray.php");
	require_once("$includes_path/avecore/JournalService.php");
	require_once("$includes_path/avecore/AppBuffer.php");
	require_once("$includes_path/avecore/BinaryFile.php");
	
	require_once("$includes_path/services/GuardPattern.php");
	require_once("$includes_path/services/GuardDriver.php");
	require_once("$includes_path/services/FaceDetector.php");
	require_once("$includes_path/services/DataBaseBackup.php");
	require_once("$includes_path/services/MediaFunctions.php");
	require_once("$includes_path/services/StringConverter.php");

	require_once("$includes_path/AVE.php");
	
	require_once("$includes_path/tools/AdmFileConverter.php");
	require_once("$includes_path/tools/AveConsole.php");
	require_once("$includes_path/tools/AveSettings.php");
	require_once("$includes_path/tools/CheckFileIntegrity.php");
	require_once("$includes_path/tools/DirectoryFunctions.php");
	require_once("$includes_path/tools/DirectoryNamesEditor.php");
	require_once("$includes_path/tools/FileEditor.php");
	require_once("$includes_path/tools/FileFunctions.php");
	require_once("$includes_path/tools/FileNamesEditor.php");
	require_once("$includes_path/tools/FtpTools.php");
	require_once("$includes_path/tools/MediaSorter.php");
	require_once("$includes_path/tools/MediaTools.php");
	require_once("$includes_path/tools/MySQLTools.php");

	$ave = new AVE($argv);
	if(!$ave->abort) $ave->execute();
?>
