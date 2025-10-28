<?php
// error_reporting(E_ALL);
// ini_set('display_errors', '1');
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";

UpdateField('users', 'cActive', 'N', " iUserID=$sess_user_id");

session_destroy();
$response = array('statusCode' => 200, 'message' => "Logged out successfully");
http_response_code(200);
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>