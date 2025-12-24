<?php
include "../includes/config.inc.php"; // db configurations
include "../includes/define.inc.php"; // # defines
include "../includes/generic.inc.php"; // # common functions
include "../includes/common.inc.php"; // # project specific functions
include "../includes/sql.inc.php"; // # sql functions
include "../includes/custom.php"; // custom functions created for this project
include "../includes/fleet_log.inc.php"; // fleet logging functions

require_once("api_common.php");

$CON = GetConnected();

$q = "select cType, vCode, cData, vValue from sys_settings where cStatus='A'";
$r = sql_query($q, 'DYN.30');
while(list($sys_type, $sys_code, $sys_data, $sys_value) = sql_fetch_row($r))
{
	if($sys_data=='I')
		$sys_value = intval($sys_value);
	else if($sys_data=='N')
		$sys_value = floatval($sys_value);
	else if($sys_data=='B')
		$sys_value = boolval($sys_value);
	else
		$sys_value = strval($sys_value); // C, D

	if($sys_type=='D') // define
		define($sys_code, $sys_value);
	else if($sys_type=='V') // variable
		${$sys_code} = $sys_value;
	else if($sys_type=='A') // arrays
	{
		$x = json_decode($sys_value);

		foreach($x as $key=>$val)
			${$sys_code}[$key] = $val;
	}
}

?>