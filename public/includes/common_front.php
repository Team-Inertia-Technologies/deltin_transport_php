<?php
include "config.inc.php"; // db configurations
include "define.inc.php"; // # defines
include "generic.inc.php"; // # common functions
include "common.inc.php"; // # project specific functions
include "userdat_front.php"; // #
include "sql.inc.php"; // # sql functions
include "custom.php"; // # sql functions
include "dynamic_front.inc.php"; // # sql functions

sql_query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

if(!$logged && $NO_REDIRECT==0)
{
	session_destroy();
	ForceOut(9);
	exit;
}
?>