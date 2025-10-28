<?php
##	GETCONNECTED	###################################################################################
$CON = GetConnected();
$S3OBJ = (defined('USE_OBJECT_STORAGE') && USE_OBJECT_STORAGE=='Y')?ObjectStorage():null;

##	USER SESSION VARIABLES	###################################################################################
$logged = $sess_user_id = $sess_app_reg = $sess_app_spot = $sess_app_id = 0;
$sess_info_str = '';

##	SYS_SETTINGS	###################################################################################
$q = "select cType, vCode, cData, vValue from sys_settings where cStatus='A'";
$r = sql_query($q, 'DYN.30');
while (list($sys_type, $sys_code, $sys_data, $sys_value) = sql_fetch_row($r)) {
	if ($sys_data == 'I')
		$sys_value = intval($sys_value);
	else if ($sys_data == 'N')
		$sys_value = floatval($sys_value);
	else if ($sys_data == 'B')
		$sys_value = boolval($sys_value);
	else
		$sys_value = strval($sys_value); // C, D

	if ($sys_type == 'D') // define
		define($sys_code, $sys_value);
	else if ($sys_type == 'V') // variable
		${$sys_code} = $sys_value;
	else if ($sys_type == 'A') // arrays
	{
		$x = json_decode($sys_value);

		foreach ($x as $key => $val)
			${$sys_code}[$key] = $val;
	}
}

##	SESSION->INFO	###################################################################################
$lbl_display = 'none'; // used for LBL_ERR

?>