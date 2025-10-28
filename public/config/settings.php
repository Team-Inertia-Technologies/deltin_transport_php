<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";

$OTHER_SETTINGS = array();


$setting_query = "select  vCode as CODE ,vValue as VALUE ,vDesc as DESCR  from sys_settings where cStatus='A'  and iGroupID in (10,11,12,13) ";
$setting_result = sql_query($setting_query);
if (sql_num_rows($setting_result)) {
	while ($setting_data = sql_fetch_assoc($setting_result)) {
		$OTHER_SETTINGS[$setting_data['CODE']] = $setting_data;
	}

	$response = array('statusCode' => 200, 'message' => "Successfully fetched the settings", 'data' => $OTHER_SETTINGS);
} else {
	$response = array('statusCode' => 400, 'message' => 'No data', 'data' => array());
}
http_response_code(200);
header('Content-Type: application/json');
echo json_encode($response);
exit;