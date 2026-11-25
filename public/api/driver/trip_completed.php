<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 0);
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
$booking_id = intval($request->id);

if (!$token || !$booking_id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => "Missing token or booking_id."
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

$bookingRes = sql_query(
    "SELECT cDisposal, cType FROM fleet_booking WHERE iFleet_BookingID='$booking_id' AND iDriverID='$driverID' LIMIT 1",
    'BOOKING.CHECK'
);
$booking = sql_num_rows($bookingRes) ? sql_fetch_assoc($bookingRes) : null;
$alreadyComplete = $booking && strtoupper(trim((string)($booking['cType'] ?? ''))) === 'C';
$disposalFlag = $booking ? strtoupper(trim((string)($booking['cDisposal'] ?? 'N'))) : 'N';
$isDisposal = in_array($disposalFlag, ['Y', '1', 'YES', 'TRUE'], true);

if ($isDisposal) {
    $fromTime = tripCompletedValue($request, ['fromTime', 'startTime', 'dtFromTime', 'fromDateTime']);
    $toTime = tripCompletedValue($request, ['toTime', 'endTime', 'dtToTime', 'toDateTime']);
    $startKm = tripCompletedValue($request, ['startKm', 'iStartKm']);
    $endKm = tripCompletedValue($request, ['endKm', 'iEndKm']);
    if ($fromTime === null || $toTime === null || $startKm === null || $endKm === null) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            "statusCode" => 400,
            "error" => [
                "message" => "Start time, end time, start km and end km are required for disposal trips."
            ]
        ]);
        exit;
    }

    $reading = fleetApplyDisposalReading($booking_id, [
        'fromTime' => $fromTime,
        'toTime' => $toTime,
        'startKm' => $startKm,
        'endKm' => $endKm
    ]);
    if (!empty($reading['error'])) {
        http_response_code(intval($reading['statusCode'] ?? 400));
        header('Content-Type: application/json');
        echo json_encode([
            "statusCode" => intval($reading['statusCode'] ?? 400),
            "error" => [
                "message" => $reading['error']
            ]
        ]);
        exit;
    }
}

$prepared = fleetPrepareTripRate($booking_id);
if (!empty($prepared['error'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => $prepared['error']
        ]
    ]);
    exit;
}

// -------------------- UPDATE TRIP STATUS TO COMPLETED --------------------
$log_id = NextID('iLogID', 'fleet_booking_log');
$NOW = NOW;
$query = "UPDATE fleet_booking SET cType='C', vDropTime = '$NOW' WHERE iFleet_BookingID='$booking_id' AND iDriverID='$driverID'";
sql_query("INSERT INTO fleet_booking_log (iLogID, iFleet_BookingID, cRefType, vRefName, dtAdded, iUserID, cStatus) VALUES ($log_id, '$booking_id', 'C', 'Trip Compeleted', '$NOW', '$driverID', 'A')", 'TRIP.LOG');
$result = sql_query($query, 'TRIP.COMPLETE');
if (sql_affected_rows() > 0 || $alreadyComplete) {
    $rate = fleetApplyPreparedRate($prepared, $driverID);
    $payload = [
        "statusCode" => 200,
        "message" => "Trip completed successfully.",
        "rate" => $rate
    ];
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($payload);
} else {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => "Failed to complete trip. Please check booking ID and driver ID."
        ]
    ]);
}

function tripCompletedValue($request, $keys)
{
    if (!is_object($request)) {
        return null;
    }
    foreach ($keys as $key) {
        if (isset($request->$key) && $request->$key !== '') {
            return $request->$key;
        }
    }
    return null;
}
