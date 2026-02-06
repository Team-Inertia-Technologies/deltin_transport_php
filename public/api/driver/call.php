<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";

date_default_timezone_set('Asia/Calcutta');

/* -------------------- HEADERS -------------------- */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");
header("Expires: " . gmdate("D, d M Y H:i:s", 1) . " GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* -------------------- INPUT -------------------- */
$postdata = file_get_contents("php://input");
$request  = json_decode($postdata);

$token  = trim($request->token ?? '');
$mobile = trim($request->mobile ?? '');

/* -------------------- BASIC VALIDATION -------------------- */
if ($token === '' || $mobile === '') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Token and mobile are required'
    ]);
    exit;
}
$driverID = DecodeParam($token);
$tonumber = GetXFromYID("Select vMobileNum from driver where iDriverID='$driverID' AND cStatus='A' ");

/* -------------------- EXOTEL FUNCTION -------------------- */
function triggerExotelExoMLCall($fromNumber, $tonumber)
{
    // Exotel credentials
    $api_key   = 'ab4f6f769ee189fa5e4d57b79789de3b987fab33a413819f';
    $api_token = '5f1f43db51a120f9027c32fbecc3de88410a92a0396c9da0';
    $sid       = 'deltacorp1';
    $callerId = '07314852425';

    $url = "https://api.exotel.com/v1/Accounts/$sid/Calls/connect.json";

    $data = [
        'From'     => $tonumber,
        'To'       => $fromNumber,
        'CallerId' => $callerId,
        'Record'   => 'true',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, "$api_key:$api_token");

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        curl_close($ch);
        return [
            'status'  => 'error',
            'message' => 'cURL error: ' . curl_error($ch)
        ];
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($http_code === 200 && isset($decoded['Call']['Sid'])) {
        return [
            'status'      => 'success',
            'call_sid'    => $decoded['Call']['Sid'],
            'call_status' => $decoded['Call']['Status']
        ];
    }

    if (isset($decoded['RestException']['Message'])) {
        return [
            'status'  => 'error',
            'message' => $decoded['RestException']['Message']
        ];
    }

    return [
        'status'  => 'error',
        'message' => "Unexpected error (HTTP $http_code)"
    ];
}

$result = triggerExotelExoMLCall($mobile, $tonumber);
echo json_encode($result);
exit;
