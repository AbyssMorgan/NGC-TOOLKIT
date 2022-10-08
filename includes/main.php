<?php
	$includes_path = __DIR__;
	require_once("$includes_path/services/Logs.php");
	require_once("$includes_path/services/IniFile.php");
	require_once("$includes_path/services/CommandLine.php");
	require_once("$includes_path/extensions/VideoFunctions.php");
	require_once("$includes_path/extensions/ImageFunctions.php");
	require_once("$includes_path/AVE.php");
	require_once("$includes_path/tools/NamesGenerator.php");

	$ave = new AVE($argv);
	$ave->execute();
?>
