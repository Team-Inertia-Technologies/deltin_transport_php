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

if (!$token) {
	http_response_code(400);
	header('Content-Type: application/json');
	echo json_encode([
		"statusCode" => 400,
		"error" => [
			"message" => "Missing token."
		]
	]);
	exit;
}

$driverID = DecodeParam($token);

$query = "SELECT * FROM driver WHERE iDriverID = '$driverID' AND cStatus = 'A'";
$result = sql_query($query, 'DRIVER.PROFILE');
if (!sql_num_rows($result)) {
	http_response_code(400);
	header('Content-Type: application/json');
	echo json_encode([
		"statusCode" => 400,
		"error" => [
			"message" => "Invalid Token."
		]
	]);
	exit;
}

$driverData = sql_fetch_assoc($result);
$tripQuery = "SELECT COUNT(*) AS tripCount FROM fleet_booking WHERE iDriverID = '$driverID'";
$tripResult = sql_query($tripQuery, 'DRIVER.TRIPS');
$tripRow = sql_fetch_assoc($tripResult);
$noOfTrips = intval($tripRow['tripCount'] ?? 0);

$profile = [
	"driverID" => intval($driverData['iDriverID']),
	"pic" => '',
	"name" => $driverData['vName'],
	"mobile" => $driverData['vMobileNum'],
	"altMobile" => $driverData['vMobileNum'],
	"address" => '',
	"rating" => 0,
	"noOfTrips" => $noOfTrips
];
$carDetails =[
	"licenseNo" => $driverData['vEmpCode'],
	"licenseValidDate" => $driverData['dExpiry'],
	"BadgeNo" => $driverData['vBatchNo'],
	"BadgeValidDate" => $driverData['dExpiry'],
];
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
	"statusCode" => 200,
	"message" => "Driver profile fetched successfully.",
	"data" => array(
	"profile" => $profile,
	"carDetails" => $carDetails
	)
]);
exit;