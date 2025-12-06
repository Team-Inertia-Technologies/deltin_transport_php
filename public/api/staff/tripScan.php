<?php
//ini_set('display_errors', 1);

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

// ===================== CASE: SCAN =====================
case 'SCAN':
  // echo json_encode([
        //     "data" => $_REQUEST,
        //     "raw" => $postdata ?? null,
        //     "statusCode" => 200
        // ]);
        // exit;
    $vehicleCode = $_REQUEST['vehicleCode'] ?? '';
    $datetime = NOW;

    if (empty($vehicleCode)) {
        logQRScanError($user_id, "Vehicle code is required");
        echo json_encode([
            "error" => ["message" => "Vehicle code is required"],
            "statusCode" => 400
        ]);
        exit;
    }

    $vehicleID = intval(deCodeParamSMS($vehicleCode));
    $response = checkVehicleAvailability($vehicleID, $datetime);

    if ($response['statusCode'] != 200) {
        $errorMsg = $response['error']['message'] ?? 'Unknown error';
        logQRScanError($user_id, "Vehicle Availability: " . $errorMsg);
        echo json_encode($response);
        exit;
    }

    $response = checkStaffRequest($user_id, $datetime);
    if ($response['statusCode'] != 200) {
        $errorMsg = $response['error']['message'] ?? 'Unknown error';
        logQRScanError($user_id, "Staff Request: " . $errorMsg);
        echo json_encode($response);
        exit;
    }

    // Fetch vehicle number
    $vehSql = "SELECT vRnum FROM vehicle WHERE iVehicleID = {$vehicleID} LIMIT 1";
    $vehRes = sql_query($vehSql);

    if (sql_num_rows($vehRes) == 0) {
        logQRScanError($user_id, "Vehicle not found (ID: $vehicleID)");
        echo json_encode([
            "error" => ["message" => "Vehicle not found"],
            "statusCode" => 404
        ]);
        exit;
    }

    $vehData = sql_fetch_assoc($vehRes);
    $vehicleNum = $vehData['vRnum'];

    $date = TODAY;
    // Fetch the active request for the staff today
    $reqSql = "SELECT iTrReqID, iTripID FROM st_request 
               WHERE iStaffID = {$user_id} 
               AND dPickup = '{$date}' 
               AND cStatus = 'A' 
               LIMIT 1";
    $reqRes = sql_query($reqSql);

    if (sql_num_rows($reqRes) == 0) {
        logQRScanError($user_id, "No matching request found for date $date");
        echo json_encode([
            "error" => ["message" => "No matching request found"],
            "statusCode" => 404
        ]);
        exit;
    }

    $reqRow = sql_fetch_assoc($reqRes);
    $requestID = intval($reqRow['iTrReqID']);
    $tripID = intval($reqRow['iTripID']);

    // Check if already scanned
    $scanCheckSql = "SELECT dtIn FROM st_request WHERE iTrReqID = {$requestID} LIMIT 1";
    $scanCheckRes = sql_query($scanCheckSql);
    $scanCheckRow = sql_fetch_assoc($scanCheckRes);
    
    if (!empty($scanCheckRow['dtIn']) && $scanCheckRow['dtIn'] != '0000-00-00 00:00:00' && $scanCheckRow['dtIn'] != NULL) {
        logQRScanError($user_id, "Already marked as entered");
        echo json_encode([
            "error" => ["message" => "You are already marked as entered"],
            "statusCode" => 400
        ]);
        exit;
    }

    // Fetch route and pickup details
    $detailSql = "SELECT 
                    rt.vName AS routeName,
                    rs.vName AS pickupPt,
                    s.vName AS staffName
                  FROM st_request r
                  LEFT JOIN st_route rt ON r.iRouteID = rt.iRouteID
                  LEFT JOIN st_route_stops rs ON r.iStopID = rs.iStopID
                  LEFT JOIN staff s ON r.iStaffID = s.iStaffID
                  WHERE r.iTrReqID = {$requestID}
                  LIMIT 1";
    $detailRes = sql_query($detailSql);
    $d = sql_fetch_assoc($detailRes);
$CURRENTTIME=CURRENTTIME;
    // Update request (vehicle scanned entry time)
    $updateSql = "UPDATE st_request 
                    SET iVehicleID = '{$vehicleID}', dtIn = '$datetime'
                  WHERE iTrReqID = {$requestID} 
                  LIMIT 1";
                //  error_log($updateSql);

    if (sql_query($updateSql)) {
        // Increment availed count for trip — using correct tripID
        sql_query("UPDATE st_trips SET iAvaialed = iAvaialed + 1 
                   WHERE iTripID = {$tripID} LIMIT 1");

        echo json_encode([
            "data" => [
                "route"    => $d['routeName'] ?? '',
                "pickupPt" => $d['pickupPt'] ?? '',
                "vehi"     => $vehicleNum,
                "date"     => date('d/m/Y'),
                "time"     => (!empty($CURRENTTIME) ? date('H:i', strtotime($CURRENTTIME)) : date('H:i')),
                "name" => $d['staffName'] ?? ''
            ],
            "statusCode" => 200
        ]);
    } else {
        echo json_encode([
            "error" => ["message" => "Failed to update entry"],
            "statusCode" => 500
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
