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
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['statusCode' => 400, 'message' => 'Invalid Token.']);
    exit;
}

$user = sql_fetch_assoc($r);

$driverID = intval($userid);
$sql = "
SELECT 
	fb.iFleet_BookingID,
	fb.vPickUpTime,
	fb.vName AS name,
	fb.vMobileNo AS mobile,
	fb.iPax AS pax,
	fb.iBaggage AS bags,
	fb.vPickUpLocation AS fromlocation,
	fb.vDropLocation AS tolocation,
	p.vName AS propertyName
FROM fleet_booking fb
LEFT JOIN property p ON p.iPropertyID = fb.iPropertyID
WHERE fb.iDriverID = $driverID
AND fb.vPickUpTime >= NOW()
ORDER BY fb.vPickUpTime ASC
LIMIT 1
";
$r = sql_query($sql, 'DRIVER.NEXTTRIP');
$nextTrip = sql_fetch_assoc($r);
if (!empty($nextTrip)) {

    $pickupTimestamp = strtotime($nextTrip['vPickUpTime']);
    $formattedTime = date("g:i A", $pickupTimestamp);
    $now = time();
    $diff = $pickupTimestamp - $now;

    if ($diff <= 0) {
        $durationText = "Trip is starting now";
    } else {
        $minutes = round($diff / 60);

        if ($minutes < 60) {
            $durationText = "Next trip:  $minutes minutes";
        } else {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;

            if ($mins > 0) {
                $durationText = "Next trip: {$hours}h {$mins}m";
            } else {
                $durationText = "Next trip: {$hours} hours";
            }
        }
    }
    $nextTrip['time'] = $formattedTime;
    $nextTrip['duration'] = $durationText;
    $nextTrip['type'] = "guest";
}

if(empty($nextTrip)) {
	http_response_code(400);
	header('Content-Type: application/json');
	echo json_encode(['statusCode' => 400, 'message' => 'No upcoming trips found.']);
	exit;
} else
{
	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode([
		'statusCode' => 200,
		'message' => 'Next trip fetched successfully.',
		'nextTrip' => $nextTrip
	]);
	exit;
}