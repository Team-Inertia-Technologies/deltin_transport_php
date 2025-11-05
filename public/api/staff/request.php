<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true); // Decode as associative array
$_REQUEST = array_merge($_REQUEST, $request ?? []); // Merge with $_REQUEST
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

    // ===================== CASE: ADD_ONLOAD =====================
    case 'ADD_ONLOAD':
        // Get staff route and stop information
        $staffSql = "SELECT iRouteID, iStopID FROM staff WHERE iUserID = $user_id AND cStatus = 'A'";
        $staffRes = sql_query($staffSql);
        $staffData = sql_fetch_assoc($staffRes);
        
        $staffRouteID = (int) ($staffData['iRouteID'] ?? 0);
        $staffStopID = (int) ($staffData['iStopID'] ?? 0);
        
        // Get routes with their pickup options (stops) and time options from trips
        $routesSql = "SELECT iRouteID, vName, vDestination FROM st_route WHERE cStatus = 'A' ORDER BY iRank";
        $routesRes = sql_query($routesSql);
        
        $routes = [];
        
        while ($routeRow = sql_fetch_assoc($routesRes)) {
            $routeID = (int) $routeRow['iRouteID'];
            $routeName = $routeRow['vName'];
            
            // Get pickup options (stops) for this route
            $stopsSql = "SELECT iStopID, vName FROM st_route_stops 
                        WHERE iRouteID = $routeID AND cStatus = 'A' 
                        ORDER BY iRank";
            $stopsRes = sql_query($stopsSql);
            
            $pickUpOpt = [];
            while ($stopRow = sql_fetch_assoc($stopsRes)) {
                $pickUpOpt[] = [
                    "id" => (int) $stopRow['iStopID'],
                    "name" => $stopRow['vName']
                ];
            }
            
            // Get time options from existing trips for this route
            $timeSql = "SELECT DISTINCT TIME(dtTrip) as trip_time 
                       FROM st_trips 
                       WHERE iRouteID = $routeID AND cStatus = 'A' 
                       ORDER BY trip_time";
            $timeRes = sql_query($timeSql);
            
            $timeOpt = [];
            $timeId = 1;
            while ($timeRow = sql_fetch_assoc($timeRes)) {
                $timeOpt[] = [
                    "id" => $timeId++,
                    "name" => date('H:i', strtotime($timeRow['trip_time']))
                ];
            }
            
            $routes[] = [
                "id" => $routeID,
                "name" => $routeName,
                "pickUpOpt" => $pickUpOpt,
                "timeOpt" => $timeOpt
            ];
        }
        
        // Get days array from trips - max 7 days from today or however many days exist
        $today = date('Y-m-d');
        $maxDate = date('Y-m-d', strtotime('+7 days'));
        
        $daysSql = "SELECT iTripID, DATE(dtTrip) as trip_date 
                   FROM st_trips 
                   WHERE cStatus = 'A' AND DATE(dtTrip) >= '$today' AND DATE(dtTrip) <= '$maxDate'
                   GROUP BY DATE(dtTrip)
                   ORDER BY trip_date 
                   LIMIT 7";
        $daysRes = sql_query($daysSql);
        
        $daysArr = [
            ["id" => 0, "name" => "Select All"]
        ];
        
        while ($dayRow = sql_fetch_assoc($daysRes)) {
            $tripDate = $dayRow['trip_date'];
            $tripID = (int) $dayRow['iTripID'];
            $dayName = date('l, j F', strtotime($tripDate)); // Format: "Monday, 26 August"
            
            $daysArr[] = [
                "id" => $tripID,
                "name" => $dayName
            ];
        }
        
        echo json_encode([
            "data" => [
                "routes" => $routes,
                "daysArr" => $daysArr,
                "staffRouteID" => $staffRouteID,
                "staffStopID" => $staffStopID
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