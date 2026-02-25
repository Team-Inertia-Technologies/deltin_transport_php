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


// -------------------- FETCH DRIVER CAR DETAILS --------------------
$driverID = intval($userid);
$sql = "
SELECT 
    v.iVehicleID,
    vc.vName AS vehicleName,
    v.vRnum AS vehicleNumber,
    v.iVendorID AS vehicleVendorID,
    v.iCatID AS vehicleCategoryID
FROM vehicle v
INNER JOIN driver_vehicle_assoc vda ON vda.iVehicleID = v.iVehicleID
INNER JOIN vehicle_category vc ON vc.iVCatID = v.iCatID
WHERE vda.iDriverID = $driverID
AND vda.cStatus = 'A'
";

$r = sql_query($sql, 'DRIVER.CAR');
$car = sql_fetch_assoc($r);

$category = GetXFromYID("SELECT vName FROM vehicle_category WHERE iVCatID = " . intval($car['vehicleCategoryID']), 'vehicle.CATEGORY');
$vendor = GetXFromYID("SELECT vName FROM vendor WHERE iVendorID = " . intval($car['vehicleVendorID']), 'vehicle.VENDOR');

// -------------------- FETCH DRIVER NOTES --------------------
$notesQuery = "
    SELECT 
        dtCreated AS dateTime, 
        cType AS reviewType, 
        vText AS reviewText 
    FROM driver_notes 
    WHERE iDriverID = $driverID 
      AND iVehicleID = " . intval($car['iVehicleID']) . "
    ORDER BY dtCreated DESC
";

$noteResult = sql_query($notesQuery, 'DRIVER.NOTES');
$notes = [];

while ($row = sql_fetch_assoc($noteResult)) {
    $notes[] = $row;
}

// -------------------- SEND RESPONSE --------------------
if (!sql_num_rows($r)) {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'statusCode' => 200,
        'message' => 'No car associated with this driver.',
        'carDetails' => [
            'vehicleID' => 0,
        ]
    ]);
    exit;
}

// -------------------- FINAL RESPONSE --------------------
header('Content-Type: application/json');
echo json_encode([
    'statusCode' => 200,
    'message' => 'Success',
    'driver' => [
        'id' => $user['iDriverID'],
        'name' => $user['vName']
    ],
    'carDetails' => [
        'vehicleID' => $car['iVehicleID'],
        'name' => $car['vehicleName'],
        'number' => $car['vehicleNumber'],
        'vendor' => $vendor,
        'category' => $category
    ],
    'driversNote' => $notes
]);

exit;
