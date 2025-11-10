<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";
$token = isset($_POST['token']) ? db_input($_POST['token']) : '';
$user_id = DecodeParam($token);
sql_query("UPDATE staff SET cActive='N' WHERE iStaffID=$user_id");

$response = array('statusCode' => 200, 'message' => "Logged out successfully");
http_response_code(200);
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>