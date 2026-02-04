<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";
date_default_timezone_set('Asia/Calcutta');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$request = json_decode(file_get_contents("php://input"));

$token = trim($request->token ?? '');
$mode  = strtoupper(trim($request->mode ?? ''));
$VehicleID = isset($request->vehicleID) && is_numeric($request->vehicleID) ? (int)$request->vehicleID : 0;

if (!$token) {
    http_response_code(400);
	header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Missing token"]
    ]);
    exit;
}

if (!in_array($mode, ['SINGLE', 'ALL'])) {
    http_response_code(400);
	header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Invalid mode"]
    ]);
    exit;
}

/* =========================
   DRIVER ID HANDLING
========================= */
$driverIds = [];

if ($mode === 'SINGLE') {

    $driverId = intval($request->driver_id ?? 0);

    if ($driverId <= 0) {
        http_response_code(400);
		header('Content-Type: application/json');
        echo json_encode([
            "statusCode" => 400,
            "error" => ["message" => "Invalid driver_id"]
        ]);
        exit;
    }

    $driverIds[] = $driverId;

} else { // MODE = ALL

    if (
        empty($request->driver_ids) ||
        !is_array($request->driver_ids)
    ) {
        http_response_code(400);
		header('Content-Type: application/json');
        echo json_encode([
            "statusCode" => 400,
            "error" => ["message" => "driver_ids must be a non-empty array"]
        ]);
        exit;
    }

    foreach ($request->driver_ids as $id) {
        $id = intval($id);
        if ($id > 0) {
            $driverIds[] = $id;
        }
    }

    if (empty($driverIds)) {
        http_response_code(400);
		header('Content-Type: application/json');
        echo json_encode([
            "statusCode" => 400,
            "error" => ["message" => "No valid driver IDs provided"]
        ]);
        exit;
    }
}

/* =========================
   SIGN OUT DRIVERS
========================= */
$signedOut = [];
$failed    = [];
$now = NOW;
foreach ($driverIds as $driverId) {

    $sql = "UPDATE driver SET dtLoggedOut = '$now', dtLoggedIn = NULL WHERE iDriverID = {$driverId}";
    $result = sql_query($sql);
    if ($result && sql_affected_rows() > 0) {
		if ($VehicleID > 0) {
			sql_query("UPDATE driver_vehicle_assoc set cStatus = 'X' WHERE iDriverID = $driverId AND iVehicleID = $VehicleID AND cStatus = 'A'");
		}
        $signedOut[] = $driverId;
    } else {
        $failed[] = $driverId;
    }
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    "statusCode" => 200,
    "message" => "Driver sign-out processed",
    "mode" => $mode,
    "signed_out" => $signedOut,
    "failed" => $failed
]);
