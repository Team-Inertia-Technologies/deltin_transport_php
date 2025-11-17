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

    // ===================== CASE: ADD_ONLOAD =====================
    case 'ADD_ONLOAD':
        $staffSql = "SELECT iRouteID, iStopID FROM staff WHERE iStaffID = $user_id AND cStatus = 'A'";
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
            
            // Get pickup options (stops) for this route with timing calculations
            $stopsSql = "SELECT iStopID, vName, tOffsetFromStart FROM st_route_stops 
                        WHERE iRouteID = $routeID AND cStatus = 'A' 
                        ORDER BY iRank";
            $stopsRes = sql_query($stopsSql);
            
            // Get the earliest trip time for this route to calculate stop timings
            $earliestTripSql = "SELECT TIME(dtTrip) as trip_time 
                               FROM st_trips 
                               WHERE iRouteID = $routeID AND cStatus = 'A' 
                               ORDER BY TIME(dtTrip) 
                               LIMIT 1";
            $earliestTripRes = sql_query($earliestTripSql);
            $baseTime = '00:00:00'; // Default base time
            
            if (sql_num_rows($earliestTripRes) > 0) {
                $earliestTripData = sql_fetch_assoc($earliestTripRes);
                $baseTime = $earliestTripData['trip_time'];
            }
            
            $pickUpOpt = [];
            while ($stopRow = sql_fetch_assoc($stopsRes)) {
                $offsetMinutes = intval($stopRow['tOffsetFromStart']);
                $stopTime = date('H:i', strtotime($baseTime) + $offsetMinutes * 60);
                
                $pickUpOpt[] = [
                    "id" => (int) $stopRow['iStopID'],
                    "name" => db_output2($stopRow['vName']) . " | " . $stopTime
                ];
            }
            
            // Get time options from existing trips for this route with their specific days
            $timeSql = "SELECT TIME(dtTrip) as trip_time 
                       FROM st_trips 
                       WHERE iRouteID = $routeID AND cStatus = 'A' 
                       GROUP BY TIME(dtTrip)
                       ORDER BY trip_time";
            $timeRes = sql_query($timeSql);
            
            $timeOpt = [];
            $timeIndex = 1; // Start from 1 for time IDs
            
            while ($timeRow = sql_fetch_assoc($timeRes)) {
                $tripTime = $timeRow['trip_time'];
                
                // Get days array for this specific time
                $today = date('Y-m-d');
                $maxDate = date('Y-m-d', strtotime('+7 days'));
                $currentDateTime = date('Y-m-d H:i:s');
                $twoHoursFromNow = date('Y-m-d H:i:s', strtotime('+2 hours'));
                
                $daysSql = "SELECT iTripID, DATE(dtTrip) as trip_date, dtTrip
                           FROM st_trips 
                           WHERE iRouteID = $routeID AND cStatus = 'A' 
                           AND TIME(dtTrip) = '$tripTime'
                           AND DATE(dtTrip) >= '$today' AND DATE(dtTrip) <= '$maxDate'
                           AND dtTrip >= '$twoHoursFromNow'
                           ORDER BY trip_date 
                           LIMIT 7";
                $daysRes = sql_query($daysSql);
                
                $daysArr = [];
                
                // Only add "Select All" if there are actual days available
                if (sql_num_rows($daysRes) > 0) {
                    $daysArr[] = ["id" => 0, "name" => "Select All"];
                }
                
                while ($dayRow = sql_fetch_assoc($daysRes)) {
                    $tripDate = $dayRow['trip_date'];
                    $tripID = (int) $dayRow['iTripID'];
                    $dayName = date('l, j F', strtotime($tripDate)); // Format: "Monday, 26 August"
                    
                    $daysArr[] = [
                        "id" => $tripID,
                        "name" => $dayName
                    ];
                }
                
                // Only include time if it has available days
                if (count($daysArr) > 1) { // More than just "Select All"
                    $timeOpt[] = [
                        "id" => $timeIndex,
                        "name" => date('H:i', strtotime($tripTime)),
                        "time" => $tripTime,
                        "daysArr" => $daysArr
                    ];
                    $timeIndex++;
                }
            }
            
            // Only include route if it has time options with available days
            if (!empty($timeOpt)) {
                $routes[] = [
                    "id" => $routeID,
                    "name" => db_output2($routeName),
                    "pickUpOpt" => $pickUpOpt,
                    "timeOpt" => $timeOpt
                ];
            }
        }
        
        echo json_encode([
            "data" => [
                "routes" => $routes,
                "staffRouteID" => $staffRouteID,
                "staffStopID" => $staffStopID
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: ADD_REQUEST =====================
    // Logic: When adding a new request, increase iRequested in st_trips table.
    // If trip capacity is exceeded, check for other trips with same grpID.
    // If all trips in group are full, add to the last trip anyway.
    case 'ADD_REQUEST':
        $route = intval($_REQUEST['route'] ?? 0);
        $pickUp = intval($_REQUEST['pickUp'] ?? 0);
        $time = intval($_REQUEST['time'] ?? 0);
        $days = $_REQUEST['days'] ?? [];
        
        // Validate required fields
        if ($route == 0 || $pickUp == 0 || $time == 0 || empty($days)) {
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
        
        // Get route details
        $routeSql = "SELECT vName, vDestination FROM st_route WHERE iRouteID = $route AND cStatus = 'A'";
        $routeRes = sql_query($routeSql);
        
        if (sql_num_rows($routeRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Route not found"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        $routeData = sql_fetch_assoc($routeRes);
        $routeName = $routeData['vName'];
        $destination = $routeData['vDestination'];
        
        // Get the actual time value - support both old (trip ID) and new (time value) structure
        $selectedTimeValue = $_REQUEST['timeValue'] ?? '';
        
        if (!empty($selectedTimeValue)) {
            // New structure: time value is provided directly
            $selectedTime = $selectedTimeValue;
        } else {
            // Fallback to old structure: get time from trip ID
            $timeTripSql = "SELECT TIME(dtTrip) as trip_time FROM st_trips WHERE iTripID = $time AND cStatus = 'A'";
            $timeTripRes = sql_query($timeTripSql);
            
            if (sql_num_rows($timeTripRes) == 0) {
                echo json_encode([
                    "error" => [
                        "message" => "Selected time not found. Please provide timeValue parameter."
                    ],
                    "statusCode" => 400
                ]);
                exit;
            }
            
            $timeTripData = sql_fetch_assoc($timeTripRes);
            $selectedTime = $timeTripData['trip_time'];
        }
        
        $timing = date('H:i', strtotime($selectedTime));
        
        // Get pickup point name
        $pickupPointSql = "SELECT vName FROM st_route_stops WHERE iStopID = $pickUp AND cStatus = 'A'";
        $pickupPointRes = sql_query($pickupPointSql);
        $pickupPointName = '';
        
        if (sql_num_rows($pickupPointRes) > 0) {
            $pickupPointData = sql_fetch_assoc($pickupPointRes);
            $pickupPointName = db_output2($pickupPointData['vName']);
        }
        
        $successCount = 0;
        $errors = [];
        $selectedDays = [];
        
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
            $tripDate = date('Y-m-d', strtotime($tripData['dtTrip']));
            $tripDateTime = "$tripDate $selectedTime";
            
            // Check if trip is within 2 hours from now (booking restriction)
            $currentDateTime = date('Y-m-d H:i:s');
            $twoHoursFromNow = date('Y-m-d H:i:s', strtotime('+2 hours'));
            
            if ($tripDateTime < $twoHoursFromNow) {
                $errors[] = "Cannot book trip on $tripDate - bookings must be made at least 2 hours in advance";
                continue;
            }
            
            // Check if request already exists for this staff, route, and trip
            $existingSql = "SELECT iTrReqID FROM st_request 
                           WHERE iStaffID = $user_id AND iRouteID = $route AND iTripID = $tripID AND cStatus = 'A'";
            $existingRes = sql_query($existingSql);
            
            if (sql_num_rows($existingRes) > 0) {
                $errors[] = "Request already exists for trip on $tripDate";
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
            $pickupTime = date('H:i:s', strtotime($selectedTime) + $offsetMinutes * 60);
            
            // Get next ID and rank
            $iTrReqID = NextID('iTrReqID', 'st_request');
            $nextRank = GetMaxRank('st_request', "iStaffID=$user_id and cStatus='A'", 'iRank');
            
            // Find the best available trip for this request
            $finalTripID = $tripID;
            
            // Get the group ID for this trip
            $grpSql = "SELECT iGrpID FROM st_trips WHERE iTripID = $tripID AND cStatus = 'A'";
            $grpRes = sql_query($grpSql);
            
            if (sql_num_rows($grpRes) > 0) {
                $grpData = sql_fetch_assoc($grpRes);
                $grpID = (int) $grpData['iGrpID'];
                
                // Get all trips in this group with their current capacity status
                $groupTripsSql = "SELECT iTripID, iCapacity, iRequested 
                                 FROM st_trips 
                                 WHERE iGrpID = $grpID AND cStatus = 'A' 
                                 ORDER BY iTripID";
                $groupTripsRes = sql_query($groupTripsSql);
                
                $availableTrip = null;
                $lastTrip = null;
                
                while ($groupTripRow = sql_fetch_assoc($groupTripsRes)) {
                    $currentTripID = (int) $groupTripRow['iTripID'];
                    $capacity = (int) $groupTripRow['iCapacity'];
                    $requested = (int) $groupTripRow['iRequested'];
                    
                    $lastTrip = $currentTripID; // Keep track of last trip
                    
                    // Check if this trip has available capacity
                    if ($requested < $capacity) {
                        $availableTrip = $currentTripID;
                        break; // Found available trip, use it
                    }
                }
                
                // Use available trip if found, otherwise use the last trip in group
                $finalTripID = $availableTrip ?? $lastTrip ?? $tripID;
            }
            
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
                '$tripDate',
                '$pickupTime',
                $pickUp,
                $finalTripID,
                '$currentDateTime',
                $nextRank,
                'A'
            )";
            
            if (sql_query($insertSql)) {
                // Update iRequested count in st_trips table
                $updateTripSql = "UPDATE st_trips 
                                 SET iRequested = iRequested + 1 
                                 WHERE iTripID = $finalTripID AND cStatus = 'A'";
                
                if (sql_query($updateTripSql)) {
                    $successCount++;
                    $selectedDays[] = date('l, j F', strtotime($tripDate));
                } else {
                    // If trip update fails, rollback the request insert
                    $deleteSql = "DELETE FROM st_request WHERE iTrReqID = $iTrReqID";
                    sql_query($deleteSql);
                    $errors[] = "Failed to update trip capacity for trip on $tripDate";
                }
            } else {
                $errors[] = "Failed to save request for trip on $tripDate";
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
                    "routeName" => db_output2($routeName),
                    "destination" => db_output2($destination),
                    "pickupPoint" => $pickupPointName,
                    "timing" => $timing,
                    "selectedDays" => $selectedDays,
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


    // ===================== CASE: DELETE_REQUEST =====================
    case 'DELETE_REQUEST':
        $requestID = intval($_REQUEST['requestID'] ?? 0);
        
        if ($requestID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Missing or invalid requestID parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        // Get request details before deletion to update trip count
        $requestSql = "SELECT iTripID FROM st_request 
                      WHERE iTrReqID = $requestID AND iStaffID = $user_id AND cStatus = 'A'";
        $requestRes = sql_query($requestSql);
        
        if (sql_num_rows($requestRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Request not found or access denied"
                ],
                "statusCode" => 404
            ]);
            exit;
        }
        
        $requestData = sql_fetch_assoc($requestRes);
        $tripID = (int) $requestData['iTripID'];
        
        // Delete the request (set status to 'C')
        $deleteSql = "UPDATE st_request 
                     SET cStatus = 'C' 
                     WHERE iTrReqID = $requestID AND iStaffID = $user_id AND cStatus = 'A'";
        
        if (sql_query($deleteSql)) {
            // Decrease iRequested count in st_trips table
            $updateTripSql = "UPDATE st_trips 
                             SET iRequested = GREATEST(0, iRequested - 1) 
                             WHERE iTripID = $tripID AND cStatus = 'A'";
            
            if (sql_query($updateTripSql)) {
                echo json_encode([
                    "data" => [
                        "message" => "Request cancelled successfully"
                    ],
                    "statusCode" => 200
                ]);
            } else {
                // If trip update fails, rollback the request deletion
                $rollbackSql = "UPDATE st_request 
                               SET cStatus = 'A' 
                               WHERE iTrReqID = $requestID";
                sql_query($rollbackSql);
                
                echo json_encode([
                    "error" => [
                        "message" => "Failed to update trip capacity"
                    ],
                    "statusCode" => 500
                ]);
            }
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to cancel request"
                ],
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