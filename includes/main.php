<?php
	$includes_path = __DIR__;
	require_once("$includes_path/Logs.php");
	require_once("$includes_path/IniFile.php");
	require_once("$includes_path/CommandLine.php");
	require_once("$includes_path/AVE.php");
	require_once("$includes_path/tools/NamesGenerator.php");

	$ave = new AVE($argv);
	$ave->execute();
?>
