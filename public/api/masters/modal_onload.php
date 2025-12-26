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

$token    = trim($request->token);
$driverId = intval($request->driverId ?? 0);

if (!$token || !$driverId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Missing token or driverId"]
    ]);
    exit;
}

$userid = DecodeParam($token);

/* ---------- VERIFY TOKEN ---------- */
$auth = sql_query(
    "SELECT iDriverID FROM driver WHERE iDriverID='$userid' AND cStatus='A'",
    "AUTH"
);

if (!sql_num_rows($auth)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Invalid Token"]
    ]);
    exit;
}
/* =========================================================
   DRIVER DETAILS
========================================================= */
$driverSql = "
SELECT 
    d.vName,
    d.iType,
    d.cStatus,
    d.vMobileNum,
    ve.vName AS vVendorName,
    v.vRnum AS vVehicleNo
    vc.vName AS vehicleName
FROM driver d
LEFT JOIN driver_vehicle_assoc dva 
    ON dva.iDriverID = d.iDriverID
LEFT JOIN vehicle v 
    ON v.iVehicleID = dva.iVehicleID
LEFT JOIN vehicle_category vc ON vc.iVCatID = v.iCatID
LEFT JOIN vendor ve 
    ON ve.iVendorID = d.iVendorID
WHERE d.iDriverID = $driverId
";

$driverRes = sql_query($driverSql, "DRIVER.DETAILS");
$driverRow = sql_fetch_array($driverRes);

$driverDetails = [
    "driverName" => $driverRow['vName'] ?? "",
    "driverType" => intval($driverRow['iType'] ?? 0),
    "status"     => $driverRow['cStatus'] ?? "",
    "mob"        => $driverRow['vMobileNum'] ?? "",
    "vendor"     => $driverRow['vVendorName'] ?? "",
    "vehi"       => $driverRow['vVehicleNo'] ?? "",
    "vehicleName"   => $driverRow['vehicleName'] ?? ""
];

/* =========================================================
   TRIPS (fleet_booking)
========================================================= */
$tripSql = "
SELECT
    vPickUpLocation,
    vDropLocation,
    vName AS GuestName,
    iPax,
    DATE_FORMAT(vPickUpTime,'%d/%m/%Y %H:%i') AS fromTime,
    DATE_FORMAT(vDropTime,'%d/%m/%Y %H:%i') AS toTime
FROM fleet_booking
WHERE iDriverID = $driverId AND cType IN ('C','N')
ORDER BY vPickUpTime DESC
LIMIT 2
";

$tripRes = sql_query($tripSql, "TRIPS");

$tripsArr = [];
while ($t = sql_fetch_array($tripRes)) {
    $tripsArr[] = [
        "from"     => $t['vPickUpLocation'],
        "to"       => $t['vDropLocation'],
        "name"     => $t['GuestName'],
        "capacity" => $t['iPax'],
        "fromTime" => $t['fromTime'],
        "toTime"   => $t['toTime']
    ];
}

/* =========================================================
   DRIVER HISTORY (booking_log)
========================================================= */
$historySql = "
SELECT
    cRefType,
    vRefName,
    DATE_FORMAT(dtAdded,'%d/%m/%Y %H:%i') AS dateTime
FROM fleet_booking_log
WHERE iUserID = $driverId
ORDER BY dtAdded DESC
";

$historyRes = sql_query($historySql, "HISTORY");

$driverHistoryArr = [];
while ($h = sql_fetch_array($historyRes)) {
    $driverHistoryArr[] = [
        "code"     => $h['cRefType'],
        "status"   => $h['vRefName'],
        "message"  => $h['vRefName'],
        "dateTime" => $h['dateTime']
    ];
}
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    "statusCode" => 200,
    "data" => [
        "driverDetials"     => $driverDetails,
        "tripsArr"          => $tripsArr,
        "driverHistoryArr"  => $driverHistoryArr
    ]
]);
exit;
