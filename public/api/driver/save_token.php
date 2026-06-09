<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";

$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

$Token = $request->token;
$driver_ID = intval(DecodeParam($Token));
$deviceToken = $request->device_token;
if (!$driver_ID || !$deviceToken) {
    $output = array(
        'statusCode' => 400,
        'message' => 'Missing driver ID or Device Token'
    );
} else {
    $updateQuery = "UPDATE driver SET vDeviceToken = '$deviceToken' WHERE iDriverID = $driver_ID";
    if (sql_query($updateQuery)) {
        $output = array(
            'statusCode' => 200,
            'message' => 'Success!'
        );
    } else {

        $output = array(
            'statusCode' => 500,
            'message' => 'Failed to save token'
        );
    }
}

header('Content-Type: application/json');
echo json_encode($output);
exit;
