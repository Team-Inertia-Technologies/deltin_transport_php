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
        $date = $_REQUEST['date'] ?? NOW;
        
        // Validate date parameter
        if (empty($date)) {
            echo json_encode([
                "error" => [
                    "message" => "Date parameter is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate date format
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

        // Get all trips for the specified date
        $sql = "SELECT 
                    t.iTripID,
                    t.iGrpID,
                    t.dtTrip,
                    r.vName as routeName,
                    r.vDestination as destination,
                    t.iCapacity,
                    t.iRequested as requestedPax,
                    t.iAvaialed as availedPax,
                    v.vRnum as vehicleNumber,
                    vc.iCapacity as vehicleCapacity
                FROM st_trips t
                LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                LEFT JOIN vehicle v ON t.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
                LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                WHERE DATE(t.dtTrip) = '$date' AND t.cStatus = 'A'
                ORDER BY t.dtTrip, t.iGrpID";

        $res = sql_query($sql);
        $groupedTrips = [];

        // Group trips by iGrpID (trip groups)
        while ($row = sql_fetch_assoc($res)) {
            $grpID = (int) $row['iGrpID'];
            $tripTime = date('H:i', strtotime($row['dtTrip']));
            $currentTime = date('H:i');
            $currentDate = date('Y-m-d');
            
            if (!isset($groupedTrips[$grpID])) {
                $groupedTrips[$grpID] = [
                    "from" => db_output2($row['routeName'] ?? ''),
                    "to" => db_output2($row['destination'] ?? ''),
                    "vehicleInfo" => []
                ];
            }

            // Determine status based on time and capacity
            $status = "pending"; // Default status
            $totalRequestedPax = (int) ($row['requestedPax'] ?? 0);
            $vehicleCapacity = (int) ($row['vehicleCapacity'] ?? 0);
            
            // Check if trip is over (past time on same date or past date)
            if ($date < $currentDate || ($date == $currentDate && $tripTime < $currentTime)) {
                $status = "success";
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
            foreach ($groupedTrips[$grpID]['vehicleInfo'] as &$vehicleInfo) {
                if ($vehicleInfo['time'] === $tripTime) {
                    // Add vehicle to existing time slot
                    $vehicleInfo['vehiNum'][] = [
                        "num" => $row['vehicleNumber'] ?? '',
                        "count" => $totalRequestedPax
                    ];
                    $vehicleInfo['pax'] += $totalRequestedPax;
                    $timeExists = true;
                    break;
                }
            }

            if (!$timeExists) {
                // Create new time slot
                $groupedTrips[$grpID]['vehicleInfo'][] = [
                    "time" => $tripTime,
                    "pax" => $totalRequestedPax,
                    "status" => $status,
                    "vehiNum" => [
                        [
                            "num" => $row['vehicleNumber'] ?? '',
                            "count" => $totalRequestedPax
                        ]
                    ]
                ];
            }
        }

        // Convert grouped trips to the required format
        $trips = [];
        foreach ($groupedTrips as $grpID => $tripData) {
            $trips[] = $tripData;
        }

        echo json_encode([
            "data" => [
                "trips" => $trips
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