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

$driverIds = [];

if ($mode === 'SINGLE') {

    if (
        empty($request->driver_ids) ||
        !is_array($request->driver_ids)
    ) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            "statusCode" => 400,
            "error" => ["message" => "driver_ids must be an array with one driver ID"]
        ]);
        exit;
    }

    if (count($request->driver_ids) !== 1) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            "statusCode" => 400,
            "error" => ["message" => "SINGLE mode accepts exactly one driver ID"]
        ]);
        exit;
    }

    $driverId = intval($request->driver_ids[0]);

    if ($driverId <= 0) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            "statusCode" => 400,
            "error" => ["message" => "Invalid driver ID"]
        ]);
        exit;
    }

    $driverIds[] = $driverId;

} else {

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


$logoutDateTime = NOW;
if (!empty($request->logout_time)) {
    $providedDateTime = trim($request->logout_time);
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $providedDateTime);
    if ($dt && $dt->format('Y-m-d H:i:s') === $providedDateTime) {
        $logoutDateTime = $providedDateTime;
    } else {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            "statusCode" => 400,
            "error" => ["message" => "Invalid logout_datetime format. Use YYYY-MM-DD HH:MM:SS"]
        ]);
        exit;
    }
}

$signedOut = [];
$failed    = [];
$vehicleIds = [];

if (!empty($request->vehicle_ids) && is_array($request->vehicle_ids)) {
    foreach ($request->vehicle_ids as $vid) {
        $vid = intval($vid);
        if ($vid > 0) {
            $vehicleIds[] = $vid;
        }
    }
}

foreach ($driverIds as $driverId) {

    $sql = "UPDATE driver SET dtLoggedOut = '$logoutDateTime', dtLoggedIn = NULL WHERE iDriverID = {$driverId}";
    $result = sql_query($sql);
    if ($result && sql_affected_rows() > 0) {
        if (!empty($vehicleIds)) {
            $vehicleList = implode(',', $vehicleIds);
            sql_query("UPDATE driver_vehicle_assoc SET cStatus = 'X' WHERE iDriverID = {$driverId} AND iVehicleID IN ($vehicleList) AND cStatus = 'A' ");
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
    "logout_time" => $logoutDateTime,
    "signed_out" => $signedOut,
    "failed" => $failed
]);