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

$token              = trim($request->token ?? '');
$driverType         = intval($request->driverType ?? 0);
$vehicle_status     = trim($request->status ?? 'Y');
$showLoggedInOnly   = trim($request->showLoggedInOnly ?? 'Y');

if (!$token) {
    http_response_code(400);
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Missing token"]
    ]);
    exit;
}

/* ---------- BUILD FILTERS ---------- */
$where = " WHERE d.cStatus = 'A'";

if ($vehicle_status === 'Y') {
    $where .= " AND dva.iDriverID IS NOT NULL";
}

if ($vehicle_status === 'N') {
    $where .= " AND dva.iDriverID IS NULL";
}

if ($driverType > 0) {
    $where .= " AND d.iType = $driverType";
}

if ($showLoggedInOnly === 'Y') {
    $where .= " AND d.dtLoggedIn IS NOT NULL";
}

/* ---------- MAIN QUERY ---------- */
$sql = "
SELECT DISTINCT
    d.iDriverID AS iRoasterID,
    d.dtLoggedIn,
    d.vName AS name,
    d.vMobileNum AS mobile,
    v.iVehicleID,
    v.vRnum AS vehicle,
    vc.vName AS vehicleName,

    CASE 
        WHEN dva.iDriverID IS NULL THEN 'Unassigned'
        ELSE 'Assigned'
    END AS status,

    CASE 
        WHEN dva.iDriverID IS NULL THEN 'N'
        ELSE 'Y'
    END AS vehicleAllocated

FROM driver d
LEFT JOIN driver_vehicle_assoc dva ON dva.iDriverID = d.iDriverID AND dva.cStatus = 'A'
LEFT JOIN vehicle v ON v.iVehicleID = dva.iVehicleID
LEFT JOIN vehicle_category vc ON vc.iVCatID = v.iCatID
$where
GROUP BY d.iDriverID
ORDER BY d.dtLoggedIn DESC
";

$res = sql_query($sql, 'ROASTER.LIST');

$tripList = [];
$today = date('Y-m-d');

$currentTime = time();
$maxLoggedSeconds = 8 * 3600;

$currentTimeSql = date('Y-m-d H:i:s');
$todayDate      = date('Y-m-d');

while ($row = sql_fetch_assoc($res)) {

    $dateTime = "";
    if (!empty($row['dtLoggedIn'])) {
        $loggedDate = date('Y-m-d', strtotime($row['dtLoggedIn']));
        if ($loggedDate === $today) {
            $dateTime = date('h:i A', strtotime($row['dtLoggedIn']));
        } else {
            $dateTime = date('d/m/Y h:i A', strtotime($row['dtLoggedIn']));
        }
    }

    $loggedInStatus = 'N';
    if (!empty($row['dtLoggedIn'])) {
        $loggedInStatus = 'Y';
    }

    $overLoggedLimit = false;

    if (!empty($row['dtLoggedIn'])) {
        $loggedInTimestamp = strtotime($row['dtLoggedIn']);
        if (($currentTime - $loggedInTimestamp) >= $maxLoggedSeconds) {
            $overLoggedLimit = true;
        }
    }

    $currentTrip = null;
    $nextTrip    = null;

    $currentTripSql = "
        SELECT 
            iFleet_BookingID,
            vPickUpTime,
            vPickUpLocation,
            vDropLocation
        FROM fleet_booking
        WHERE 
            iDriverID = {$row['iRoasterID']}
            AND cType IN ('S','G', 'P', 'R', 'C')
            AND DATE(vPickUpTime) = '$todayDate'
            AND vPickUpTime <= '$currentTimeSql'
            AND cStatus = 'A'
        ORDER BY vPickUpTime DESC
        LIMIT 1
    ";

    $currRes = sql_query($currentTripSql, 'CURRENT.TRIP');
    if ($currRes && sql_num_rows($currRes)) {
        $currentTrip = sql_fetch_assoc($currRes);
    }

    $nextTripSql = "
    SELECT 
        iFleet_BookingID,
        vPickUpTime,
        vPickUpLocation,
        vDropLocation
    FROM fleet_booking
    WHERE 
        iDriverID = {$row['iRoasterID']}
        AND cType = 'N'
        AND DATE(vPickUpTime) = '$todayDate'
        AND vPickUpTime > '$currentTimeSql'
        AND cStatus = 'A'
    ORDER BY vPickUpTime ASC
    LIMIT 1
";

    $nextRes = sql_query($nextTripSql, 'NEXT.TRIP');
    if ($nextRes && sql_num_rows($nextRes)) {
        $nextTrip = sql_fetch_assoc($nextRes);
    }



    $tripList[] = [
        "id"               => intval($row['iRoasterID']),
        "dateTime"         => $dateTime,
        "name"             => db_output2($row['name']),
        "mobile"           => $row['mobile'],
        "vehicle_ids"       => intval($row['iVehicleID']),
        "vehicle"          => $row['vehicle'] ?: "",
        "vehicleName"      => db_output2($row['vehicleName'] ?: ""),
        "status"           => $row['status'],
        "vehicleAllocated" => $row['vehicleAllocated'],
        "loggedInStatus"   => $loggedInStatus,
        "overLoggedLimit"  => $overLoggedLimit,
        "currentTrip" => $currentTrip ? [
            "id"       => $currentTrip['iFleet_BookingID'],
            "time"     => $currentTrip['vPickUpTime'],
            "from"     => $currentTrip['vPickUpLocation'],
            "to"       => $currentTrip['vDropLocation']
        ] : null,

        "nextTrip" => $nextTrip ? [
            "id"       => $nextTrip['iFleet_BookingID'],
            "time"     => $nextTrip['vPickUpTime'],
            "from"     => $nextTrip['vPickUpLocation'],
            "to"       => $nextTrip['vDropLocation']
        ] : null
    ];
}

$response = [
    "statusCode" => 200,
    "data" => [
        "tripList" => $tripList,
        "allocatedOpt" => [
            ["id" => "Y", "name" => "Yes"],
            ["id" => "N", "name" => "No"]
        ],
        "driverTypeOpt" => [
            ["id" => 1, "name" => "Hired"],
            ["id" => 2, "name" => "Contract"],
            ["id" => 3, "name" => "Self"]
        ],
        "loginFilterOpt" => [
            ["id" => "N", "name" => "All Drivers"],
            ["id" => "Y", "name" => "Signed In Only"]
        ]
    ]
];

http_response_code(200);
header('Content-Type: application/json');
echo json_encode($response);
exit;
