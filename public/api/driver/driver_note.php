<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";
date_default_timezone_set('Asia/Calcutta');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Expires: " . gmdate("D, d M Y H:i:s", 1) . " GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

$token      = trim($request->token);
$iVehicleID = intval($request->VehicleID);
$reviewType = trim($request->reviewType);
$reviewText = trim($request->reviewText);

if (!$token) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => "Missing token."
        ]
    ]);
    exit;
}

$userid = DecodeParam($token);

// -------------------- VERIFY TOKEN --------------------
$q = "SELECT iDriverID, vName FROM driver WHERE iDriverID='$userid' AND cStatus='A'";
$r = sql_query($q, 'AUTH.DETAILS');

if (!sql_num_rows($r)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'statusCode' => 400,
        "error" => [
            "message" => "Invalid Token."
        ]
    ]);
    exit;
}

$driverID = intval($userid);


// -------------------- INSERT DRIVER NOTE --------------------
$id = NextID('iNoteID', 'driver_notes');
$NOW = NOW;
$sql = "INSERT INTO driver_notes (iNoteID, iDriverID, iVehicleID, cType, vText, dtCreated) VALUES ($id,'$driverID', '$iVehicleID', '$reviewType', '$reviewText', '$NOW')";
$result = sql_query($sql, 'DRIVER.NOTE.INSERT');
if (sql_affected_rows() > 0) {
	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode([
		"statusCode" => 200,
		"message" => "Driver note added successfully."
	]);
	exit;
} else {
	http_response_code(400);
	header('Content-Type: application/json');
	echo json_encode([
		"statusCode" => 400,
		"error" => [
			"message" => "Failed to add driver note."
		]
	]);
	exit;
}