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

$token = trim($request->token);
$userid = DecodeParam($token);


// -------------------- VERIFY TOKEN --------------------
$q = "SELECT iDriverID, vName FROM driver WHERE iDriverID='$userid' AND cStatus='A'";
$r = sql_query($q, 'AUTH.LEAD');

if (!sql_num_rows($r)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['statusCode' => 401, 'message' => 'Invalid Token.']);
    exit;
}

$user = sql_fetch_assoc($r);

// -------------------- FETCH DRIVER TRIPS --------------------
$driverID = intval($userid);

// SQL QUERY
$sql = "
SELECT 
    fb.iFleet_BookingID,
    fb.vPickUpTime,
    fb.vName AS guestName,
    fb.vMobileNo AS guestMobile,
    fb.iPax,
    fb.iBaggage,
    locFrom.iFleet_LocationID AS fromLocation,
    locTo.iFleet_LocationID AS toLocation,

    -- VEHICLE INFO
    v.vRnum AS vehicleNo,
    v.vName AS vehicleName,

    -- PROPERTY
    p.vPropertyName AS propertyName

FROM fleet_booking fb

LEFT JOIN fleet_location locFrom ON locFrom.iFleet_LocationID = fb.iFleet_LocationID_From
LEFT JOIN fleet_location locTo   ON locTo.iFleet_LocationID   = fb.iFleet_LocationID_To

LEFT JOIN vehicle v ON v.iVehicleID = fb.iVehicleID
LEFT JOIN property p ON p.iPropertyID = fb.iPropertyID

WHERE 
    fb.cStatus = 'A'
    AND (
        fb.iDriverID = '{$driverID}' 
        OR fb.iVehicleID IN (
            SELECT iVehicleID FROM driver_vehicle_assoc WHERE iDriverID = '{$driverID}'
        )
    )
ORDER BY fb.vPickUpTime ASC
";

$res = sql_query($sql, "TRIPS.LIST");
if (!$res) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => "Failed to fetch trips."
        ]
    ]);
    exit;
}

$trips = [];
$vehicle = [
    "vehicle_no" => "",
    "vehicle_name" => ""
];

while ($row = sql_fetch_assoc($res)) {

    if ($vehicle["vehicle_no"] === "" && !empty($row["vehicleNo"])) {
        $vehicle["vehicle_no"]  = $row["vehicleNo"];
        $vehicle["vehicle_name"] = $row["vehicleName"];
    }

    $trips[] = [
        "id"            => $row["iFleet_BookingID"],
        "pickup_time"   => $row["vPickUpTime"],
        "guest_name"    => $row["guestName"],
        "guest_mobile"  => $row["guestMobile"],
        "pax"           => intval($row["iPax"]),
        "baggage"       => intval($row["iBaggage"]),
        "from_location" => $row["fromLocation"],
        "to_location"   => $row["toLocation"],
    ];
}

if (empty($trips)) {
    http_response_code(404);
    header("Content-Type: application/json");
    echo json_encode([
        "statusCode" => 404,
        "message" => "No trips found for this driver.",
        "data" => [
            "vehicle" => (object)[],
            "trips" => []
        ]
    ]);
    exit;
}

$response = [
    "statusCode" => 200,
    "message" => "Trips fetched successfully",
    "data" => [
        "vehicle" => $vehicle,
        "trips"   => $trips
    ]
];

header("Content-Type: application/json");
echo json_encode($response);
exit;
