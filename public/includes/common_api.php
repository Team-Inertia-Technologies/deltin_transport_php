<?php
include "config.inc.php"; // db configurations
include "define.inc.php"; // # defines
include "generic.inc.php"; // # common functions
include "common.inc.php"; // # project specific functions
include "userdat.php"; // #
include "sql.inc.php"; // # sql functions
include "custom.php"; // # sql functions
include "dynamic.inc.php";
//include "dynamic_api.inc.php"; // # sql functions
include "common.master.php";

include_once DOCROOT.'includes/libs/google_client/vendor/autoload.php';

sql_query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
?>
