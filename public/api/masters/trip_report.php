<?php
// ini_set('display_errors', 1);

include "../../includes/common_api.php";
include "../api_common.php";
header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);
$mode = $_REQUEST['mode'] ?? '';
$Token = $_REQUEST['token'] ?? '';
$user_id = intval(DecodeParam($Token));
$userCheckSql = "SELECT iUserID FROM users WHERE iUserID = $user_id AND cStatus = 'A'";
$userCheckRes = sql_query($userCheckSql);

if (sql_num_rows($userCheckRes) == 0) {
    echo json_encode([
        "error" => [
            "message" => "User not found or inactive"
        ],
        "statusCode" => 401
    ]);
    exit;
}
$NOW = NOW;
switch ($mode) {


    case 'TRIP_REPORT':
        $fromDate = $_REQUEST['fromDate'] ?? '';
        $toDate = $_REQUEST['toDate'] ?? '';
        $routeID = intval($_REQUEST['routeID'] ?? 0);
          $vendorID = intval($_REQUEST['vendorID'] ?? 0);

        $whereConditions = ["t.cStatus != 'X'"];

        if (!empty($fromDate)) {
            $whereConditions[] = "DATE(t.dtTrip) >= '" . db_input($fromDate) . "'";
        }

        if (!empty($toDate)) {
            $whereConditions[] = "DATE(t.dtTrip) <= '" . db_input($toDate) . "'";
        }

        if ($routeID > 0) {
            $whereConditions[] = "t.iRouteID = $routeID";
        }
        if ($vendorID > 0) {
            $whereConditions[] = " v.iVendorID = $vendorID";
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT 
                    t.iTripID as id,
                    t.dtTrip,
                    r.vName as route,
                    r.vDestination as destination,
                    t.iCapacity,
                    t.iAvaialed as availed,
                    t.iRequested as pax,
                    tva.iVehicleID,
                    v.vRnum as vehicleNumber,
                    vc.iCapacity as vehicleCapacity,
                    tva.iDriverID,
                    d.vName as driverName,
                    vn.vName as vendorName,
                    v.iVendorID as vendorID
                FROM st_trips t
                LEFT OUTER JOIN st_trip_vehicle_assoc tva ON t.iTripID = tva.iTripID AND tva.cStatus = 'C'
                LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                LEFT JOIN vehicle v ON tva.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
                LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                LEFT JOIN driver d ON tva.iDriverID = d.iDriverID AND d.cStatus = 'A'
                 LEFT JOIN driver d ON tva.iDriverID = d.iDriverID AND d.cStatus = 'A'
                WHERE $whereClause
                WHERE $whereClause
                ORDER BY t.dtTrip DESC, t.iTripID";

        $res = sql_query($sql);
        $trips = [];

        while ($row = sql_fetch_assoc($res)) {
            $tripID = (int) $row['id'];

            $trip = [
                "tripID" => $tripID,
                "date" => date('d/m/Y', strtotime($row['dtTrip'])),
                "time" => date('g:i A', strtotime($row['dtTrip'])),
                "route" => db_output2($row['route'] ?? ''),
                "destination" => db_output2($row['destination'] ?? ''),
                "capacity" => (int) ($row['vehicleCapacity'] ?? 0),
                "pax" => (int) ($row['pax'] ?? 0),
                "availed" => (int) ($row['availed'] ?? 0),
                "vehicleID" => (int) ($row['iVehicleID'] ?? 0),
                "vehicleNumber" => $row['vehicleNumber'] ?? '',
                "driverID" => (int) ($row['iDriverID'] ?? 0),
                "driverName" => db_output2($row['driverName']) ?? ''
            ];

            $trips[$tripID] = $trip;
        }

        $rowData = [];
        foreach ($trips as $trip) {
            $vehicleDetails = '';
            if (!empty($trip['vehicleNumber'])) {
                $vehicleDetails = $trip['vehicleNumber'] . ' (' . $trip['capacity'] . ')';
            }

            $rowData[] = [
                "tripID" => $trip['tripID'],
                "date" => $trip['date'],
                "time" => $trip['time'],
                "route" => $trip['route'],
                "destination" => $trip['destination'],
                "vehicleDetails" => $vehicleDetails,
                "capacity" => $trip['capacity'],
                "pax" => $trip['pax'],
                "availed" => $trip['availed'],
                "hasVehicle" => !empty($trip['vehicleNumber'])
            ];
        }

        $routesSql = "SELECT iRouteID, vName FROM st_route WHERE cStatus = 'A' ORDER BY iRank";
        $routesRes = sql_query($routesSql);

        $routesOpt = [
            ["id" => 0, "name" => "All"]
        ];

        while ($routeRow = sql_fetch_assoc($routesRes)) {
            $routesOpt[] = [
                "id" => (int) $routeRow['iRouteID'],
                "name" => db_output2($routeRow['vName'])
            ];
        }

        echo json_encode([
            "data" => [
                "rowData" => $rowData,
                "routesOpt" => $routesOpt,
                "fromDate" => $fromDate,
                "toDate" => $toDate,
                "routeID" => $routeID
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
