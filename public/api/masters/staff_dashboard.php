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
                "error" => [
                    "message" => "Date parameter is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            echo json_encode([
                "error" => [
                    "message" => "Invalid date format. Use YYYY-MM-DD"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Build WHERE conditions
        $whereConditions = ["DATE(t.dtTrip) = '" . db_input($date) . "'", "t.cStatus != 'X'"];

        // Add time filtering if provided
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
                    t.iCapacity,
                    t.iRequested as requestedPax,
                    t.iAvaialed as availedPax,
                    v.vRnum as vehicleNumber,
                    vc.iCapacity as vehicleCapacity
                FROM st_trips t
                LEFT OUTER JOIN st_trip_vehicle_assoc tv ON t.iTripID = tv.iTripID
                LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                LEFT JOIN vehicle v ON tv.iVehicleID = v.iVehicleID
                LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                WHERE $whereClause
                ORDER BY t.dtTrip, t.iTripID";

        $res = sql_query($sql);
        $groupedTrips = [];

        // Group trips by route (same from and to)
        while ($row = sql_fetch_assoc($res)) {
            $routeName = db_output2($row['routeName'] ?? '');
            $destination = db_output2($row['destination'] ?? '');
            $routeKey = $routeName . '|' . $destination; // Create unique key for same route

            $tripTime = date('H:i', strtotime($row['dtTrip']));
            $currentTime = date('H:i');
            $currentDate = date('Y-m-d');

            if (!isset($groupedTrips[$routeKey])) {
                $groupedTrips[$routeKey] = [
                    "from" => $routeName,
                    "to" => $destination,
                    "vehicleInfo" => []
                ];
            }

            // Determine status based on time, capacity and cStatus
            $status = "pending"; // Default status
            $totalRequestedPax = (int) ($row['requestedPax'] ?? 0);
            $vehicleCapacity = (int) ($row['vehicleCapacity'] ?? 0);
            $tripStatus = $row['cStatus'] ?? '';

            // Check if trip is over (past time on same date or past date)
            if ($date < $currentDate || ($date == $currentDate && $tripTime < $currentTime)) {
                if ($tripStatus === 'C') {
                    $status = "complete";
                } else {
                    $status = "success";
                }
            }
            // Check if requested pax exceeds vehicle capacity
            else if ($totalRequestedPax > $vehicleCapacity) {
                $status = "overbooked";
            }
            // Check if trip is upcoming (future time on same date or future date)
            else if ($date > $currentDate || ($date == $currentDate && $tripTime > $currentTime)) {
                $status = "scheduled";
            }

            // Find existing vehicleInfo entry for this time or create new one
            $timeExists = false;
            foreach ($groupedTrips[$routeKey]['vehicleInfo'] as &$vehicleInfo) {
                if ($vehicleInfo['time'] === $tripTime) {
                    // Add vehicle to existing time slot
                    $vehicleInfo['vehiNum'][] = [
                        "num" => db_output2($row['vehicleNumber'] ?? ''),
                        "count" => $vehicleCapacity
                    ];
                    $vehicleInfo['pax'] += $totalRequestedPax;
                    // Update grpID if not already set or if current grpID is different
                    if (!isset($vehicleInfo['grpID']) || $vehicleInfo['grpID'] === 0) {
                        $vehicleInfo['grpID'] = (int) ($row['iGrpID'] ?? 0);
                    }
                    $timeExists = true;
                    break;
                }
            }

            if (!$timeExists) {
                $groupedTrips[$routeKey]['vehicleInfo'][] = [
                    "time" => $tripTime,
                    "pax" => $totalRequestedPax,
                    "status" => $status,
                    "grpID" => (int) ($row['iGrpID'] ?? 0),
                    "iTripID"=> (int) ($row['iTripID'] ?? 0),
                    "vehiNum" => [
                        [
                            "num" => db_output2($row['vehicleNumber'] ?? ''),
                            "count" => $vehicleCapacity
                        ]
                    ]
                ];
            }
        }

        // Convert grouped trips to the required format
        $trips = [];
        foreach ($groupedTrips as $routeKey => $tripData) {
            $trips[] = $tripData;
        }

        echo json_encode([
            "data" => [
                "message" => "Success",
                "trips" => $trips
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