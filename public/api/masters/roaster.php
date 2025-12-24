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

$token          = trim($request->token);
$driverType     = intval($request->driverType ?? 0);
$vehicle_status         = trim($request->status ?? 'Y');

if (!$token) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => ["message" => "Missing token"]
    ]);
    exit;
}



/* ---------- BUILD FILTERS ---------- */
$where = " WHERE d.cStatus = 'A' ";

if ($vehicle_status === 'Y') {
    $where .= " AND dva.iDriverID IS NOT NULL";
}

if ($vehicle_status === 'N') {
    $where .= " AND dva.iDriverID IS NULL";
}

if ($driverType > 0) {
    $where .= " AND d.iType = $driverType";
}

/* ---------- MAIN QUERY ---------- */
$sql = "
SELECT
    d.iDriverID AS iRoasterID,
    DATE_FORMAT(d.dtLoggedIn, '%d/%m/%Y %H:%i') AS dateTime,
    d.vName AS name,
    d.vMobileNum AS mobile,
    v.vRnum AS vehicle,

    CASE 
        WHEN dva.iDriverID IS NULL THEN 'Unassigned'
        ELSE 'Assigned'
    END AS status,

    CASE 
        WHEN dva.iDriverID IS NULL THEN 'N'
        ELSE 'Y'
    END AS vehicleAllocated

FROM driver d
LEFT JOIN driver_vehicle_assoc dva ON dva.iDriverID = d.iDriverID
LEFT JOIN vehicle v ON v.iVehicleID = dva.iVehicleID
$where
ORDER BY d.dtLoggedIn DESC
";

$res = sql_query($sql, 'ROASTER.LIST');

$tripList = [];
while ($row = sql_fetch_array($res)) {
    $tripList[] = [
        "id" => intval($row['iRoasterID']),
        "dateTime" => $row['dateTime'],
        "name" => $row['name'],
        "mobile" => $row['mobile'],
        "vehicle" => $row['vehicle'] ?: "",
        "status" => $row['status'],
        "vehicleAllocated" => $row['vehicleAllocated']
    ];
}

/* ---------- STATIC FILTER OPTIONS ---------- */
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
        ]
    ]
];
http_response_code(200);
echo json_encode($response);
header('Content-Type: application/json');
exit;
