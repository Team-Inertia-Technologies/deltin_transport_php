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
$VehicleID = isset($request->vehicle_id) && is_numeric($request->vehicle_id) ? (int)$request->vehicle_id : 0;
$vehicle_status = trim($request->vehicle_status);
$vehicle_status = ($vehicle_status === 'atStation') ? 'Y' : 'N';
$station = trim($request->station);


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


// -------------------- UPDATE TRIP STATUS TO STARTED --------------------
$id = NextID('iLDID', 'log_driver_signin');
$NOW = NOW;
$query = "UPDATE driver set dtLoggedOut = '$NOW', dtLoggedIn = NULL WHERE iDriverID = $driverID";
sql_query("INSERT INTO log_driver_signin (iLDID, iDriverID, dtEntry, cType, iVehicleID, cVehicleDropped, cStatus) VALUES ($id, $driverID, '$NOW', 'OUT', '$VehicleID', '$vehicle_status', 'A')", 'DRIVER.ATTENDANCE');
if ($VehicleID > 0) {
    sql_query("UPDATE driver_vehicle_assoc set cStatus = 'X' WHERE iDriverID = $driverID AND iVehicleID = $VehicleID AND cStatus = 'A'");
}
$result = sql_query($query, 'TRIP.START');
if (sql_affected_rows() > 0) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 200,
        "message" => "Attendance marked successfully."
    ]);
} else {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => "Failed to mark attendance."
        ]
    ]);
}
