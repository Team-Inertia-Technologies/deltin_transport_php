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

switch ($mode) {

    // ===================== CASE : VIEW =====================
  case 'VIEW':

    $TODAY = TODAY;
    $date = !empty($_REQUEST['date']) ? $_REQUEST['date'] : $TODAY;
    $fromTime = $_REQUEST['fromTime'] ?? '';
    $toTime = $_REQUEST['toTime'] ?? '';

    if (empty($date)) {
        echo json_encode([
            "error" => ["message" => "Date parameter is required"],
            "statusCode" => 400
        ]);
        exit;
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        echo json_encode([
            "error" => ["message" => "Invalid date format. Use YYYY-MM-DD"],
            "statusCode" => 400
        ]);
        exit;
    }

    // WHERE conditions
    $whereConditions = [
        "DATE(t.dtTrip) = '" . db_input($date) . "'",
        "t.cStatus != 'X'"
    ];

    if (!empty($fromTime)) {
        $whereConditions[] = "TIME(t.dtTrip) >= '" . db_input($fromTime) . "'";
    }

    if (!empty($toTime)) {
        $whereConditions[] = "TIME(t.dtTrip) <= '" . db_input($toTime) . "'";
    }

    $whereClause = implode(' AND ', $whereConditions);

    $sql = "SELECT 
                t.iTripID,
                t.iGrpID,
                t.dtTrip,
                t.cStatus,
                r.vName as routeName,
                r.vDestination as destination,
                t.iRequested as requestedPax,
                v.vRnum as vehicleNumber,
                vc.iCapacity as vehicleCapacity
            FROM st_trips t
            LEFT JOIN st_trip_vehicle_assoc tv 
                ON t.iTripID = tv.iTripID AND tv.cStatus IN ('A','C')
            LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
            LEFT JOIN vehicle v ON tv.iVehicleID = v.iVehicleID
            LEFT JOIN vehicle_category vc 
                ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
            WHERE $whereClause
            ORDER BY t.dtTrip, t.iTripID";

    $res = sql_query($sql);

    $groupedTrips = [];
    $processedTrips = []; 

    $currentTime = date('H:i');
    $currentDate = date('Y-m-d');

    while ($row = sql_fetch_assoc($res)) {

        $routeName = db_output2($row['routeName'] ?? '');
        $destination = db_output2($row['destination'] ?? '');
        $routeKey = $routeName . '|' . $destination;

        $tripID = (int)$row['iTripID'];
        $grpID = (int)$row['iGrpID'];
        $tripTime = date('H:i', strtotime($row['dtTrip']));
        $tripStatus = $row['cStatus'] ?? '';

        $vehicleCapacity = (int)($row['vehicleCapacity'] ?? 0);
        $vehicleNumber = db_output2($row['vehicleNumber'] ?? '');

        $paxToAdd = 0;
        if (!isset($processedTrips[$tripID])) {
            $paxToAdd = (int)($row['requestedPax'] ?? 0);
            $processedTrips[$tripID] = true;
        }

        // Initialize route
        if (!isset($groupedTrips[$routeKey])) {
            $groupedTrips[$routeKey] = [
                "from" => $routeName,
                "to" => $destination,
                "vehicleInfo" => []
            ];
        }

        // Create unique key for time slot
        $timeKey = $tripTime;

        if (!isset($groupedTrips[$routeKey]['vehicleInfo'][$timeKey])) {

            // Determine status
            $status = "pending";

            if ($date < $currentDate || ($date == $currentDate && $tripTime < $currentTime)) {
                $status = ($tripStatus === 'C') ? "complete" : "success";
            } else {
                $status = "scheduled";
            }

            $groupedTrips[$routeKey]['vehicleInfo'][$timeKey] = [
                "time" => $tripTime,
                "pax" => 0,
                "status" => $status,
                "grpID" => $grpID,
                "iTripID" => $tripID,
                "vehiNum" => []
            ];
        }

   
        $groupedTrips[$routeKey]['vehicleInfo'][$timeKey]['pax'] += $paxToAdd;


        if (!empty($vehicleNumber)) {
            $groupedTrips[$routeKey]['vehicleInfo'][$timeKey]['vehiNum'][] = [
                "num" => $vehicleNumber,
                "count" => $vehicleCapacity
            ];
        }


        $totalCapacity = array_sum(array_column(
            $groupedTrips[$routeKey]['vehicleInfo'][$timeKey]['vehiNum'],
            'count'
        ));

        if ($groupedTrips[$routeKey]['vehicleInfo'][$timeKey]['pax'] > $totalCapacity) {
            $groupedTrips[$routeKey]['vehicleInfo'][$timeKey]['status'] = "overbooked";
        }
    }

    // Reset indexes (important for JSON)
    $finalTrips = [];

    foreach ($groupedTrips as $routeData) {
        $routeData['vehicleInfo'] = array_values($routeData['vehicleInfo']);
        $finalTrips[] = $routeData;
    }

    echo json_encode([
        "data" => [
            "message" => "Success",
            "trips" => $finalTrips
        ],
        "statusCode" => 200
    ]);

    break;


    case 'HEADER':
        $DATE = TODAY;
        $CURRENTTIME = CURRENTTIME;

        echo json_encode([
            "data" => [
                //"date" => date('d-m-y', strtotime($DATE)),0
                "date" => $DATE,
                "time" => $CURRENTTIME,
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