<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";

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

    // ===================== CASE: VIEW =====================
    case 'VIEW':
        $today = date('Y-m-d');
        $currentDateTime = date('Y-m-d H:i:s');

        // Get requested pickups (future requests - date is future OR date is today but time hasn't passed)
        $requestedSql = "SELECT 
                            r.iTrReqID as requestId,
                            rt.vName as route_name,
                            rs.vName as stop_name,
                            t.dtTrip as trip_date,
                            r.tPickup as pickup_time,
                            r.cStatus
                        FROM st_request r
                        INNER JOIN st_route rt ON r.iRouteID = rt.iRouteID
                        INNER JOIN st_route_stops rs ON r.iStopID = rs.iStopID
                        INNER JOIN st_trips t ON r.iTripID = t.iTripID
                        WHERE r.iStaffID = $user_id 
                        AND r.cStatus = 'A'
                        AND t.cStatus IN ('A', 'C')
                        AND (
                            DATE(t.dtTrip) > '$today' 
                            OR (DATE(t.dtTrip) = '$today' AND CONCAT(DATE(t.dtTrip), ' ', r.tPickup) > '$currentDateTime')
                        )
                        ORDER BY t.dtTrip ASC";

        $requestedRes = sql_query($requestedSql);
        $requestedPickups = [];

        while ($row = sql_fetch_assoc($requestedRes)) {

            $tripDate = date('j M Y', strtotime($row['trip_date']));
            $pickupTime = date('H:i', strtotime($row['pickup_time']));

            $requestedPickups[] = [
                 "requestId" => db_output2($row['requestId']),
                "route" => db_output2($row['route_name']),
                "place" => db_output2($row['stop_name']),
                "date" => $tripDate . " | " . $pickupTime
            ];
        }

        // Get previous pickups (past requests - date is past OR date is today but time has passed)
        $previousSql = "SELECT 
                            r.iTrReqID,
                            rt.vName as route_name,
                            rs.vName as stop_name,
                            t.dtTrip as trip_date,
                            r.tPickup as pickup_time,
                            r.cStatus,
                            r.dtIn,
                            r.dtOut
                        FROM st_request r
                        INNER JOIN st_route rt ON r.iRouteID = rt.iRouteID
                        INNER JOIN st_route_stops rs ON r.iStopID = rs.iStopID
                        INNER JOIN st_trips t ON r.iTripID = t.iTripID
                        WHERE r.iStaffID = $user_id 
                        AND r.cStatus = 'A'
                        AND t.cStatus IN ('A', 'C')
                        AND (
                            DATE(t.dtTrip) < '$today'
                            OR (DATE(t.dtTrip) = '$today' AND CONCAT(DATE(t.dtTrip), ' ', r.tPickup) <= '$currentDateTime')
                        )
                        ORDER BY t.dtTrip DESC
                        LIMIT 3";

        $previousRes = sql_query($previousSql);
        $previousPickups = [];

        while ($row = sql_fetch_assoc($previousRes)) {
            $tripDate = date('j M Y', strtotime($row['trip_date']));
            $pickupTime = date('H:i', strtotime($row['pickup_time']));

            // Determine status based on dtIn and dtOut
            $status = 'failed'; // default
            if (!empty($row['dtIn']) && !empty($row['dtOut'])) {
                $status = 'completed';
            }

            $previousPickups[] = [
                "route" => db_output2($row['route_name']),
                "place" => db_output2($row['stop_name']),
                "date" => $tripDate . " | " . $pickupTime,
                "status" => $status
            ];
        }

        $leaveModule = GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode='STAFF_LEAVE_MODULE'");
        $shiftInfo = GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode='STAFF_SHIFT_INFO'");

        echo json_encode([
            "data" => [
                "requestedPickups" => $requestedPickups,
                "previousPickups" => $previousPickups,
                "leaveModule" => $leaveModule == '1',
                "shiftInfo" => $shiftInfo == '1'
            ],
            "statusCode" => 200
        ]);
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