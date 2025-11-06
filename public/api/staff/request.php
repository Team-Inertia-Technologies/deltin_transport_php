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

    // ===================== CASE: ADD_REQUEST =====================
    case 'ADD_REQUEST':
        $route = intval($_REQUEST['route'] ?? 0);
        $pickUp = intval($_REQUEST['pickUp'] ?? 0);
        $time = $_REQUEST['time'] ?? '';
        $days = $_REQUEST['days'] ?? [];
        
        // Validate required fields
        if ($route == 0 || $pickUp == 0 || empty($time) || empty($days)) {
            echo json_encode([
                "error" => [
                    "message" => "Missing required fields: route, pickUp, time, or days"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        if (!is_array($days)) {
            $days = [$days];
        }
        
        $successCount = 0;
        $errors = [];
        
        // Process each day (tripID)
        foreach ($days as $tripID) {
            $tripID = intval($tripID);
            
            // Skip if tripID is 0 (Select All option)
            if ($tripID == 0) continue;
            
            // Verify the trip exists and get trip details
            $tripSql = "SELECT iTripID, dtTrip FROM st_trips WHERE iTripID = $tripID AND cStatus = 'A'";
            $tripRes = sql_query($tripSql);
            
            if (sql_num_rows($tripRes) == 0) {
                $errors[] = "Trip ID $tripID not found";
                continue;
            }
            
            $tripData = sql_fetch_assoc($tripRes);
            $tripDateTime = $tripData['dtTrip'];
            
            // Check if request already exists for this staff, route, and trip
            $existingSql = "SELECT iTrReqID FROM st_request 
                           WHERE iStaffID = $user_id AND iRouteID = $route AND iTripID = $tripID AND cStatus = 'A'";
            $existingRes = sql_query($existingSql);
            
            if (sql_num_rows($existingRes) > 0) {
                $errors[] = "Request already exists for trip on " . date('Y-m-d', strtotime($tripDateTime));
                continue;
            }

            // Get stop's offset time to calculate pickup time
            $stopSql = "SELECT tOffsetFromStart FROM st_route_stops 
                       WHERE iStopID = $pickUp AND cStatus = 'A'";
            $stopRes = sql_query($stopSql);
            
            if (sql_num_rows($stopRes) == 0) {
                $errors[] = "Stop ID $pickUp not found";
                continue;
            }
            
            $stopData = sql_fetch_assoc($stopRes);
            $offsetMinutes = intval($stopData['tOffsetFromStart']);
            
            // Calculate pickup time: trip start time + stop offset
            $tripStartTime = date('H:i:s', strtotime($tripDateTime));
            $pickupTime = date('H:i:s', strtotime($tripStartTime) + ($offsetMinutes * 60));
            
            // Get next ID and rank
            $iTrReqID = NextID('iTrReqID', 'st_request');
            $nextRank = GetMaxRank('st_request', "iStaffID=$user_id and cStatus='A'", 'iRank');
            
            // Insert request
            $currentDateTime = date('Y-m-d H:i:s');
            
            $insertSql = "INSERT INTO st_request (
                iTrReqID,
                iStaffID, 
                iRouteID, 
                dPickup, 
                tPickup, 
                iStopID, 
                iTripID, 
                dtReq, 
                iRank, 
                cStatus
            ) VALUES (
                $iTrReqID,
                $user_id,
                $route,
                DATE('$tripDateTime'),
                '$pickupTime',
                $pickUp,
                $tripID,
                '$currentDateTime',
                $nextRank,
                'A'
            )";
            
            if (sql_query($insertSql)) {
                $successCount++;
            } else {
                $errors[] = "Failed to save request for trip on " . date('Y-m-d', strtotime($tripDateTime));
            }
        }
        
        // Prepare response
        if ($successCount > 0) {
            $message = "$successCount request(s) submitted successfully";
            if (!empty($errors)) {
                $message .= ". Some requests failed: " . implode(", ", $errors);
            }
            
            echo json_encode([
                "data" => [
                    "message" => $message,
                    "successCount" => $successCount,
                    "errors" => $errors
                ],
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "No requests were saved. " . implode(", ", $errors)
                ],
                "statusCode" => 400
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