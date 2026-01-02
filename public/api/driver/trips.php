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
    fb.cBookingFor,
    fb.vName AS guestName,
    fb.vMobileNo AS guestMobile,
    fb.iPax,
    fb.iBaggage,
    fb.vPickUpLocation AS fromLocation,
    fb.vDropLocation AS toLocation,
    v.vRnum AS vehicleNo,
    vc.vName AS vehicleName,
    p.vName AS propertyName
FROM fleet_booking fb
LEFT JOIN vehicle v ON v.iVehicleID = fb.iVehicleID
LEFT JOIN vehicle_category vc ON vc.iVCatID = v.iCatID
LEFT JOIN property p ON p.iPropertyID = fb.iPropertyID

WHERE 
    fb.cType = 'N'
    AND (
        fb.iDriverID = '{$driverID}' OR fb.iVehicleID IN (SELECT iVehicleID FROM driver_vehicle_assoc WHERE iDriverID = '{$driverID}')
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
    "name" => "",
    "number" => ""
];

$settingsQuery = "SELECT vCode, vValue FROM sys_setting WHERE vCode IN ('PING_DRIVER_LOCATION', 'DRIVERLOC_PING_DURATION')";
$settingsResult = sql_query($settingsQuery);

$settings = [];
while ($row = sql_fetch_assoc($settingsResult)) {
$settings[$row['vCode']] = $row['vValue'];
}

$pingDriverLocation = $settings['PING_DRIVER_LOCATION'] ?? 'N';
$pingDuration = $settings['DRIVERLOC_PING_DURATION'] ?? null;

if ($pingDriverLocation === 'Y') {
    $pingDuration = (int)$pingDuration;
} else {
    $pingDuration = null;
}

while ($row = sql_fetch_assoc($res)) {

    if ($vehicle["number"] === "" && !empty($row["vehicleNo"])) {
        $vehicle["name"] = $row["vehicleName"];
        $vehicle["number"]  = $row["vehicleNo"];
       
    }

    $trips[] = [
        "id"            => $row["iFleet_BookingID"],
        "dateTime"   => $row["vPickUpTime"],
        "name"    => $row["guestName"],
        "mobile"  => $row["guestMobile"],
        "pax"           => intval($row["iPax"]),
        "bags"       => intval($row["iBaggage"]),
        "from" => $row["fromLocation"],
        "to"   => $row["toLocation"],
        "type" => $row["cBookingFor"] == 'G' ? 'Guest' : 'Staff',
        "active" => true,
    ];
}

if (empty($trips)) {
    http_response_code(200);
    header("Content-Type: application/json");
    echo json_encode([
        "statusCode" => 200,
        "message" => "No trips found for this driver.",
        "data" => [
            "car" => (object)[],
            "requests" => []
        ]
    ]);
    exit;
}

$response = [
    "statusCode" => 200,
    "message" => "Trips fetched successfully",
    "data" => [
        "car" => $vehicle,
        "requests"   => $trips,
        "location_ping_seconds" => $pingDuration
    ]
];

header("Content-Type: application/json");
echo json_encode($response);
exit;
