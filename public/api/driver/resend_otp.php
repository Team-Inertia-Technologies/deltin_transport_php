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

$token            = trim($request->token ?? '');
$iFleet_BookingID = intval($request->id ?? 0);

if (!$token) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => ["message" => "Missing token."],
        "statusCode" => 400
    ]);
    exit;
}

$userid = DecodeParam($token);

// -------------------- VERIFY TOKEN --------------------
$authRes = sql_query("SELECT iDriverID, vName FROM driver WHERE iDriverID='$userid' AND cStatus='A'", 'AUTH.DETAILS');

if (!sql_num_rows($authRes)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => ["message" => "Invalid Token."],
        "statusCode" => 400
    ]);
    exit;
}

$driverID = intval($userid);

// -------------------- RESEND OTP --------------------
if ($iFleet_BookingID <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => ["message" => "Booking ID is required"],
        "statusCode" => 400
    ]);
    exit;
}

$bookingCheckSql = "SELECT fb.iFleet_BookingID, fb.vName, fb.vMobileNo, fb.vPickUpTime, fb.vPickUpLocation,
                           fb.vBookingCode, fb.iVehicleID, fb.iDriverID,
                           v.vRnum, d.vName AS driverName, d.vMobileNum, d.vDeviceToken
                    FROM fleet_booking fb
                    LEFT JOIN vehicle v ON fb.iVehicleID = v.iVehicleID
                    LEFT JOIN driver d ON fb.iDriverID = d.iDriverID
                    WHERE fb.iFleet_BookingID = $iFleet_BookingID AND fb.iDriverID = $driverID AND fb.cStatus = 'A'
                    LIMIT 1";
$bookingCheckRes = sql_query($bookingCheckSql);

if (sql_num_rows($bookingCheckRes) == 0) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => ["message" => "Booking not found or cancelled"],
        "statusCode" => 404
    ]);
    exit;
}

$bookingData = sql_fetch_assoc($bookingCheckRes);
$iVehicleID = intval($bookingData['iVehicleID'] ?? 0);
$iDriverID = intval($bookingData['iDriverID'] ?? 0);

if ($iVehicleID <= 0 || $iDriverID <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => ["message" => "Vehicle and driver must be assigned before resenting OTP"],
        "statusCode" => 400
    ]);
    exit;
}

if (empty($bookingData['vRnum']) || empty($bookingData['driverName'])) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => ["message" => "Assigned vehicle or driver not found"],
        "statusCode" => 404
    ]);
    exit;
}

$vMobileNo = $bookingData['vMobileNo'] ?? '';
$vName = db_output2($bookingData['vName']) ?? '';
$vPickUpLocation = db_output2($bookingData['vPickUpLocation']) ?? '';
$pickup_time = !empty($bookingData['vPickUpTime']) ? date('h:i A', strtotime($bookingData['vPickUpTime'])) : '';
$booking_code = $bookingData['vBookingCode'] ?? '';
$dtAdded = NOW;

if (empty($vMobileNo)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => ["message" => "Passenger mobile number not found"],
        "statusCode" => 400
    ]);
    exit;
}

if (empty($booking_code)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => ["message" => "Booking OTP/code not found"],
        "statusCode" => 400
    ]);
    exit;
}

// Resend WhatsApp allocation message (includes booking OTP/code)
SendVehAllocationMessage(
    $vMobileNo,
    db_input($vName),
    db_input($bookingData['driverName']),
    $bookingData['vRnum'],
    $vPickUpLocation,
    $pickup_time,
    $booking_code
);

sql_query("
    INSERT INTO fleet_communication (cType, vCode, vMobile, cMode, dtCreated, iUserAdded)
    VALUES ('C', '+91', '$vMobileNo', 'WA', '$dtAdded', $driverID)
");

// Resend FCM notification to driver
$deviceToken = $bookingData['vDeviceToken'] ?? '';
$phoneNo = $bookingData['vMobileNum'] ?? '';
$vNotifiText = "New trip assigned: Pick up " . db_input($vName) . " from " . $vPickUpLocation . " at " . $pickup_time;

if (!empty($deviceToken)) {
    createNotification1($iDriverID, $deviceToken, $phoneNo, $iFleet_BookingID, $vNotifiText);

    $title = "Trip Assigned";
    $body = "Pick up " . db_input($vName) . " from " . $vPickUpLocation . " at " . $pickup_time;
    sendFcmNotification2($deviceToken, $iFleet_BookingID, $title, $body);
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    "data" => [
        "message" => "OTP resent successfully",
        "bookingId" => $iFleet_BookingID
    ],
    "statusCode" => 200
]);