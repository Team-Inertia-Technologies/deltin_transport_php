<?php
// error_reporting(E_ALL);
// ini_set('display_errors', '1');
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";
//$sess_user_id = 1;

$input = json_decode(file_get_contents('php://input'), true);

$txtpassword = isset($input['currentPassword']) ? trim($input['currentPassword']) : '';
$txtnewpassword = isset($input['newPassword']) ? trim($input['newPassword']) : '';
$txtnewpassword2 = isset($input['confirmPassword']) ? trim($input['confirmPassword']) : '';
$token = isset($input['token']) ? db_input($input['token']) : '';
$user_id = DecodeParam($token);

// if ($txtnewpassword !=$txtnewpassword2) 
// {
// 	$response = array(
// 		"error" => array(
// 			"message" => "New password and Confirm password do not match",
// 		),
// 		"statusCode" => 400,
// 	);
// 	http_response_code(400);
// 	header('Content-Type: application/json');
// 	echo json_encode($response);
// 	exit;
// }

$currentPassword = GetXFromYID('select vPassword from users where iUserID=' . $user_id);
if (htmlspecialchars_decode($currentPassword) != $txtpassword) 
{
	$response = array(
		"error" => array(
			"message" => "Current password is incorrect",
		),
		"statusCode" => 400,
	);
	http_response_code(400);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
} else {
	$values = " vPassword='$txtnewpassword'";
	sql_query("UPDATE users SET $values WHERE iUserID=$user_id");
	$response = array(
		"data" => array(
			"message" => "Password changed successfully",
		),
		"statusCode" => 200,
	);
	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
}