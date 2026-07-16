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

        // Helper: fetch vehicles assigned to trips
        $getTripVehicles = function (array $tripIds) {
            $vehiclesByTrip = [];
            if (empty($tripIds)) {
                return $vehiclesByTrip;
            }

            $tripIdsStr = implode(',', array_map('intval', $tripIds));
            $vehSql = "SELECT 
                            tva.iTripID,
                            v.iVehicleID,
                            v.vRnum,
                            vc.vName as categoryName
                       FROM st_trip_vehicle_assoc tva
                       INNER JOIN vehicle v ON tva.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       WHERE tva.iTripID IN ($tripIdsStr)
                       AND tva.cStatus IN ('A', 'C')
                       AND tva.iVehicleID > 0
                       ORDER BY v.vRnum";
            $vehRes = sql_query($vehSql);

            while ($vehRow = sql_fetch_assoc($vehRes)) {
                $tid = (int) $vehRow['iTripID'];
                if (!isset($vehiclesByTrip[$tid])) {
                    $vehiclesByTrip[$tid] = [];
                }
                $vehiclesByTrip[$tid][] = [
                    "id" => (int) $vehRow['iVehicleID'],
                    "number" => db_output2($vehRow['vRnum']),
                    "type" => db_output2($vehRow['categoryName'] ?? '')
                ];
            }

            return $vehiclesByTrip;
        };

        // Get requested pickups (future requests - date is future OR date is today but time hasn't passed)
        $requestedSql = "SELECT 
                            r.iTrReqID as requestId,
                            r.iTripID as tripId,
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
        $requestedRows = [];
        $requestedTripIds = [];

        while ($row = sql_fetch_assoc($requestedRes)) {
            $requestedRows[] = $row;
            $requestedTripIds[] = (int) $row['tripId'];
        }

        $requestedVehiclesByTrip = $getTripVehicles($requestedTripIds);
        $requestedPickups = [];

        foreach ($requestedRows as $row) {
            $tripDate = date('j M Y', strtotime($row['trip_date']));
            $pickupTime = date('H:i', strtotime($row['pickup_time']));
            $tripId = (int) $row['tripId'];

            $requestedPickups[] = [
                "requestId" => db_output2($row['requestId']),
                "tripId" => $tripId,
                "route" => db_output2($row['route_name']),
                "place" => db_output2($row['stop_name']),
                "date" => $tripDate . " | " . $pickupTime,
                "vehicles" => $requestedVehiclesByTrip[$tripId] ?? []
            ];
        }

        // Get previous pickups (past requests - date is past OR date is today but time has passed)
        $previousSql = "SELECT 
                            r.iTrReqID,
                            r.iTripID as tripId,
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
        $previousRows = [];
        $previousTripIds = [];

        while ($row = sql_fetch_assoc($previousRes)) {
            $previousRows[] = $row;
            $previousTripIds[] = (int) $row['tripId'];
        }

        $previousVehiclesByTrip = $getTripVehicles($previousTripIds);
        $previousPickups = [];

        foreach ($previousRows as $row) {
            $tripDate = date('j M Y', strtotime($row['trip_date']));
            $pickupTime = date('H:i', strtotime($row['pickup_time']));
            $tripId = (int) $row['tripId'];

            // Determine status based on dtIn and dtOut
            $status = 'failed'; // default
            if (!empty($row['dtIn']) && !empty($row['dtOut'])) {
                $status = 'completed';
            }

            $previousPickups[] = [
                "tripId" => $tripId,
                "route" => db_output2($row['route_name']),
                "place" => db_output2($row['stop_name']),
                "date" => $tripDate . " | " . $pickupTime,
                "status" => $status,
                "vehicles" => $previousVehiclesByTrip[$tripId] ?? []
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