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

$token      = trim($request->token ?? '');
$booking_id = isset($request->id) ? intval($request->id) : 0;
$lat        = floatval($request->lat ?? 0);
$long       = floatval($request->log ?? 0);

if (!$token) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Missing token."]
    ]);
    exit;
}

$userid = DecodeParam($token);

// -------------------- VERIFY TOKEN --------------------
$q = "SELECT iDriverID FROM driver WHERE iDriverID='$userid' AND cStatus='A'";
$r = sql_query($q, 'AUTH.DETAILS');

if (!sql_num_rows($r)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Invalid Token."]
    ]);
    exit;
}

$driverID = intval($userid);
$NOW = NOW;
$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');

$statusQuery = "
    SELECT 
        COUNT(*) AS totalTrips,
        SUM(CASE WHEN cType IN ('S','G','P','R') THEN 1 ELSE 0 END) AS activeTrips,
        SUM(CASE WHEN cType = 'N' THEN 1 ELSE 0 END) AS upcomingTrips
    FROM fleet_booking
    WHERE iDriverID = $driverID
    AND vPickUpTime BETWEEN '$todayStart' AND '$todayEnd'
";

$statusResult = sql_query($statusQuery, 'DRIVER.TRIP.STATUS');
$statusRow = sql_fetch_array($statusResult);

$totalTrips    = intval($statusRow['totalTrips']);
$activeTrips   = intval($statusRow['activeTrips']);
$upcomingTrips = intval($statusRow['upcomingTrips']);


if ($activeTrips > 0) {
    $cOnTrip = 'N';      
} elseif ($totalTrips == 0) {
    $cOnTrip = 'I';      
} elseif ($upcomingTrips > 0) {
    $cOnTrip = 'Y';      
} else {
    $cOnTrip = 'I';     
}

sql_query("UPDATE driver SET vLat='$lat', vLong='$long', dtPinned='$NOW', cAvailable='$cOnTrip' WHERE iDriverID=$driverID", 'DRIVER.LOCATION.UPDATE');

$res = sql_query("UPDATE driver_vehicle_assoc SET vLat='$lat', vLong='$long', cType='$cOnTrip' WHERE iDriverID=$driverID", 'DRIVER.LOCATION.UPDATE');

if (!$res) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Failed to update location."]
    ]);
    exit;
}

// -------------------- SUCCESS RESPONSE --------------------
header('Content-Type: application/json');
echo json_encode([
    "data" => [],
    "statusCode" => 200,
    "Available" => $cOnTrip,
    "message" => "Location updated successfully."
]);
exit;