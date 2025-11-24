<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";
include "../api_common.php";
header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);
$mode = $_REQUEST['mode'] ?? '';
$Token = $_REQUEST['token'] ?? '';
$user_id = intval(DecodeParam($Token));
$staffCheckSql = "SELECT iStaffID FROM staff WHERE iStaffID = $user_id AND cStatus = 'A'";
$staffCheckRes = sql_query($staffCheckSql);

if (sql_num_rows($staffCheckRes) == 0) {
    echo json_encode([
        "error" => [
            "message" => "User not found or inactive"
        ],
        "statusCode" => 401
    ]);
    exit;
}
switch ($mode) {

    // ===================== CASE: ADD_ONLOAD =====================
    case 'SCAN':
        $vehicleCode = $_REQUEST['vehicleCode'] ?? '';
        $datetime = NOW;
        if (empty($vehicleCode)) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle code is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        $vehicleID = deCodeParamSMS($vehicleCode);
        // $response = checkVehicleAvailability($vehicleID, $datetime);

        // if ($response['statusCode'] != 200) {
        //     echo json_encode($response);
        //     exit;
        // }

        $response = checkStaffRequest($user_id, $datetime);

        if ($response['statusCode'] != 200) {
            echo json_encode($response);
            exit;
        }
        // fetch vehicle registration number
        $vehicleID = intval($vehicleID);
        $vehSql = "SELECT vRnum FROM vehicle WHERE iVehicleID = $vehicleID LIMIT 1";
        $vehRes = sql_query($vehSql);

        if (sql_num_rows($vehRes) == 0) {
            echo json_encode([
                "error" => ["message" => "Vehicle not found"],
                "statusCode" => 404
            ]);
            exit;
        }
        $date = TODAY;
        // find the most recent request for this staff and vehicle
        $reqSql = "SELECT iTrReqID FROM st_request WHERE iStaffID = $user_id AND dPickup= $date AND cStatus='A' LIMIT 1";
        $reqRes = sql_query($reqSql);

        if (sql_num_rows($reqRes) == 0) {
            echo json_encode([
                "error" => ["message" => "No matching request found"],
                "statusCode" => 404
            ]);
            exit;
        }

        $reqRow = sql_fetch_assoc($reqRes);
        $requestID = intval($reqRow['iTrReqID']);

        // update the request: mark vehicle as entered and store registration number
        $updateSql = "
    UPDATE st_request SET iVehicleID = '{$vehicleID}', dtIn = NOW() WHERE iTrReqID = {$requestID} LIMIT 1
";
        $updateSqlRes = sql_query($updateSql);

        if ($updateSqlRes) {
            echo json_encode([
                "data" => [
                    "message" => "Success"
                ],
                "statusCode" => 200
            ]);
        }

        break;


    // ===================== DEFAULT =====================
    default:
        echo json_encode([
            "error" => [
                "message" => "Invalid mode parameter"
            ],
            "statusCode" => 400
        ]);
        break;
}
