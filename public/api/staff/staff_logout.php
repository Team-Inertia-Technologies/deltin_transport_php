<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true); 
$_REQUEST = array_merge($_REQUEST, $request ?? []); 

$token = isset($_REQUEST['token']) ? db_input($_REQUEST['token']) : '';
$user_id = DecodeParam($token);
// echo "user" . $user_id;
// exit;
// Validate user_id before using in SQL query
if (empty($user_id) || !is_numeric($user_id)) {
    $response = ['statusCode' => 400, 'message' => "Invalid token"];
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

sql_query("UPDATE staff SET cActive='N' WHERE iStaffID=" . intval($user_id));

$response = ['statusCode' => 200, 'message' => "Logged out successfully"];
http_response_code(200);
header('Content-Type: application/json');
echo json_encode($response);