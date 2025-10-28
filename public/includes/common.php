<?php
include "config.inc.php"; // db configurations
include "define.inc.php"; // # defines
include "generic.inc.php"; // # common functions
include "common.inc.php"; // # project specific functions
include "userdat.php"; // #
include "sql.inc.php"; // # sql functions
include "custom.php"; // custom functions created for this project
include "dynamic.inc.php"; // */
include "common.master.php";
include_once DOCROOT.'includes/libs/google_client/vendor/autoload.php';


sql_query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");


if(!$logged && $NO_REDIRECT==0)
{
	session_destroy();
	ForceOut(9);
	exit;
}
if ($logged) {
	//include "access.inc.php"; // */
	$USER_PROPERTY_ARR = GetXArrFromYID('select iPropertyID from users_property_assoc where iUserID=' . $sess_user_id, '2');

	//$HEADER_LOGO = SITE_ADDRESS.'images/DELTIN LOGO.png';
	if (!empty($USER_PROPERTY_ARR) && count($USER_PROPERTY_ARR) && count($USER_PROPERTY_ARR)==1) {
		$PROPERTY_LOGO = GetXFromYID('select vLogo from property where iPropertyID=' . implode(',', $USER_PROPERTY_ARR));
		//$HEADER_LOGO = (!empty($PROPERTY_LOGO) && StoreFileExist('PROPERTY_LOGO', $PROPERTY_LOGO)) ? StoreFilePath('PROPERTY_LOGO', $PROPERTY_LOGO)[0] : $HEADER_LOGO;
	} 

	// if(basename($_SERVER['SCRIPT_FILENAME'])!='change-password.php' && basename($_SERVER['SCRIPT_FILENAME'])!='logout.php')
	// {
	// 	$IS_LAST_PASSWORD_TEMP = GetXFromYID('select cIsTemp from log_user_temppassword where cStatus="A" and iUserID='.$sess_user_id.' order by dtEntry desc limit 1');
	// 	if(!empty($IS_LAST_PASSWORD_TEMP) && $IS_LAST_PASSWORD_TEMP=='Y')
	// 	{
	// 		header('location:change-password.php');
	// 		exit;
	// 	}
	// }
}


$PAGE_TITLE = "Deltin App | ";

?>