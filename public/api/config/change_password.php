<?php
// error_reporting(E_ALL);
// ini_set('display_errors', '1');
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";
//$sess_user_id = 1;
$txtpassword = isset($_POST['txtpassword']) ? trim($_POST['txtpassword']) : '';
$txtnewpassword = isset($_POST['txtnewpassword']) ? trim($_POST['txtnewpassword']) : '';
$txtnewpassword2 = isset($_POST['txtnewpassword2']) ? trim($_POST['txtnewpassword2']) : '';

if ($txtnewpassword !=$txtnewpassword2) 
{
	$response = array('statusCode' => 400, 'message' => "New password and confirm password do not match", 'data' => array());
	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
}

$currentPassword = GetXFromYID('select vPassword from users where iUserID=' . $sess_user_id);
if (htmlspecialchars_decode($currentPassword) != $txtpassword) 
{
	$response = array('statusCode' => 400, 'message' => "Current password is incorrect", 'data' => array());
	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
} else {
	$values = " vPassword='$txtnewpassword'";
	$QUERY = UpdataData('users', $values, "iUserID=$sess_user_id");
	$response = array('statusCode' => 200, 'message' => "Password changed successfully", 'data' => array());
	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
}