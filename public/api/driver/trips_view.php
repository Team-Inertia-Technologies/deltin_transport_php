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

// -------------------- FETCH TRIP DETAILS --------------------
$sql = "
SELECT 
    fb.iFleet_BookingID,
    fb.vPickUpTime,
    fb.vName AS guestName,
    fb.vMobileNo AS guestMobile,
    fb.iPax,
    fb.iBaggage,
    fb.vRemarks,
    fb.iVehAssignedBy,
    fb.cBookingFor,
    fb.cType,
    fb.vPickUpLocation AS fromLocation,
    fb.vDropLocation AS toLocation,
    fb.vLatLong_From AS fromLatLong,
    fb.vLatLong_To AS toLatLong,
    v.vRnum AS vehicleNo,
    vc.vName AS vehicleName,
    p.vName AS propertyName
FROM fleet_booking fb
LEFT JOIN vehicle v ON v.iVehicleID = fb.iVehicleID
LEFT JOIN vehicle_category vc ON vc.iVCatID = v.iCatID
LEFT JOIN property p ON p.iPropertyID = fb.iPropertyID
WHERE fb.iFleet_BookingID = '{$booking_id}'
  AND (
        fb.iDriverID = '{$driverID}' OR fb.iVehicleID IN (SELECT iVehicleID FROM driver_vehicle_assoc WHERE iDriverID = '{$driverID}')
      )
LIMIT 1
";

$res = sql_query($sql, "TRIP.DETAILS");

if (!sql_num_rows($res)) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => "Trip not found for this driver."
        ]
    ]);
    exit;
}

$row = sql_fetch_assoc($res);
$staffID = intval($row['iVehAssignedBy']);
$supervisorName   = GetXFromYID("SELECT vName FROM users WHERE iUserID = $staffID");
$supervisorMobile = GetXFromYID("SELECT vPhone FROM users WHERE iUserID = $staffID");
$fromLat = null;
$fromLng = null;

if (!empty($row["fromLatLong"]) && strpos($row["fromLatLong"], ",") !== false) {
    list($fromLat, $fromLng) = explode(",", $row["fromLatLong"]);
    $fromLat = trim($fromLat);
    $fromLng = trim($fromLng);
}
$toLat = null;
$toLng = null;

if (!empty($row["toLatLong"]) && strpos($row["toLatLong"], ",") !== false) {
    list($toLat, $toLng) = explode(",", $row["toLatLong"]);
    $toLat = trim($toLat);
    $toLng = trim($toLng);
}
$cType = isset($row["cType"]) ? trim($row["cType"]) : '';
$response = [
    "statusCode" => 200,
    "message" => "Trip details fetched successfully",
    "data" => [
        "car" => [
            "name"   => $row["vehicleName"],
            "number" => $row["vehicleNo"]
        ],

        "trip" => [
            "id"           => $row["iFleet_BookingID"],
            "dateTime"     => $row["vPickUpTime"],
            "name"         => $row["guestName"],
            "maskedguest_mobile" => maskMobileNumber($row["guestMobile"]),
            "guest_mobile" => $row["guestMobile"],
            "pax"          => intval($row["iPax"]),
            "bags"         => intval($row["iBaggage"]),
            "from"         => $row["fromLocation"],
            "to"           => $row["toLocation"],
            "type"         => $row["cBookingFor"] == 'G' ? 'Guest' : 'Staff',
            "instru"       => $row["vRemarks"],
            "supervisor"   => [
                "name"   => $supervisorName,
                "maskednumber" => maskMobileNumber($supervisorMobile),
                "number" => $supervisorMobile
            ],
           "fromLoc" => [
                "lat" => $fromLat,
                "log" => $fromLng
            ],
            "toLoc" => [
                "lat" => $toLat,
                "log" => $toLng
            ],
            "tripStarted" => ($cType === 'S'),
            "guestPicked" => ($cType === 'G'),
        ]
    ]
];

http_response_code(200);
header("Content-Type: application/json");
echo json_encode($response);
exit;
