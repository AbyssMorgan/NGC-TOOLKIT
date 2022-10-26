<?php
	declare(strict_types=1);
	error_reporting(E_ALL);

	$includes_path = __DIR__;
	require_once("$includes_path/dictionaries/MediaOrientation.php");
	require_once("$includes_path/services/Logs.php");
	require_once("$includes_path/services/IniFile.php");
	require_once("$includes_path/services/CommandLine.php");
	require_once("$includes_path/services/GuardDriver.php");
	require_once("$includes_path/extensions/MediaFunctions.php");
	require_once("$includes_path/AVE.php");
	require_once("$includes_path/tools/NamesGenerator.php");
	require_once("$includes_path/tools/FileFunctions.php");
	require_once("$includes_path/tools/MediaSorter.php");
	require_once("$includes_path/tools/DirectoryFunctions.php");
	require_once("$includes_path/tools/MediaTools.php");

	try {
		$ave = new AVE($argv);
		$ave->execute();
	}
	catch(\Exception $e){
		echo $e->getMessage()."\r\n";
		echo $e->getTraceAsString()."\r\n";
	}
?>
