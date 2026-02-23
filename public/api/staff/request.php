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
        $flag = isset($_REQUEST['flag']) ? $_REQUEST['flag'] : 'APP';
        $staffSql = "SELECT iRouteID, iStopID 
                 FROM staff 
                 WHERE iStaffID = $user_id AND cStatus = 'A'";
        $staffRes = sql_query($staffSql);
        $staffData = sql_fetch_assoc($staffRes);

        $staffRouteID = (int) ($staffData['iRouteID'] ?? 0);
        $staffStopID  = (int) ($staffData['iStopID'] ?? 0);

        $routesSql = "SELECT iRouteID, vName, vDestination 
                  FROM st_route 
                  WHERE cStatus = 'A' 
                  ORDER BY iRank";
        $routesRes = sql_query($routesSql);

        $routes = [];

        while ($routeRow = sql_fetch_assoc($routesRes)) {

            $routeID   = (int) $routeRow['iRouteID'];
            $routeName = db_output2($routeRow['vName']);

            // Query stops for this route (used later for each time slot)
            $stopsSql = "SELECT iStopID, vName, tOffsetFromStart 
                     FROM st_route_stops 
                     WHERE iRouteID = $routeID AND cStatus = 'A'
                     ORDER BY iRank";

            // ---- FETCH AVAILABLE TIME SLOTS ----
            $timeSql = "SELECT TIME(dtTrip) AS trip_time
                    FROM st_trips
                    WHERE iRouteID = $routeID AND cStatus = 'A'
                    GROUP BY TIME(dtTrip)
                    ORDER BY trip_time";
            $timeRes = sql_query($timeSql);

            $timeOpt = [];
            $timeIndex = 1;

            while ($timeRow = sql_fetch_assoc($timeRes)) {

                $tripTime = $timeRow['trip_time']; // base time for this slot (HH:MM:SS)


                $today = date('Y-m-d');
                $maxDays = GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode ='ST_REQ_THRESHOLD'");
                $maxDays = intval($maxDays);
                $maxDate = date('Y-m-d', strtotime("+$maxDays days"));

                if ($flag == 'APP') {
                    $twoHoursFromNow = date('Y-m-d H:i:s', strtotime('+2 hours'));
                    $timeCondition = " AND dtTrip >= '" . db_input($twoHoursFromNow) . "'";
                }

                $daysSql = "SELECT iTripID, DATE(dtTrip) AS trip_date
                    FROM st_trips
                    WHERE iRouteID = $routeID
                    AND cStatus = 'A'
                    AND TIME(dtTrip) = '" . db_input($tripTime) . "'
                    AND DATE(dtTrip) >= '" . db_input($today) . "'
                    AND DATE(dtTrip) <= '" . db_input($maxDate) . "'
                    $timeCondition
                    ORDER BY trip_date
                    LIMIT $maxDays";
                $daysRes = sql_query($daysSql);

                $daysArr = [];
                if (sql_num_rows($daysRes) > 0) {
                    $daysArr[] = ["id" => 0, "name" => "Select All"];
                }

                while ($dayRow = sql_fetch_assoc($daysRes)) {
                    $daysArr[] = [
                        "id"   => (int) $dayRow['iTripID'],
                        "name" => date('l, j F', strtotime($dayRow['trip_date']))
                    ];
                }

                // Skip this time slot if no valid days
                if (count($daysArr) <= 1) {
                    continue;
                }

                // ---- PICKUP OPTIONS FOR THIS TIME SLOT ----
                $pickUpOpt = [];
                $stopsRes = sql_query($stopsSql);

                while ($stopRow = sql_fetch_assoc($stopsRes)) {

                    $offsetMinutes = intval($stopRow['tOffsetFromStart']);

                    // stopTime = tripTime + offset
                    $stopTime = date('H:i', strtotime($tripTime) + ($offsetMinutes * 60));

                    $pickUpOpt[] = [
                        "id"   => (int) $stopRow['iStopID'],
                        "name" => db_output2($stopRow['vName']) . " | " . $stopTime
                    ];
                }

                // Final time slot entry
                $timeOpt[] = [
                    "id"       => $timeIndex,
                    "name"     => date('H:i', strtotime($tripTime)),
                    "time"     => $tripTime,
                    "daysArr"  => $daysArr,
                    "pickUpOpt" => $pickUpOpt
                ];

                $timeIndex++;
            }

            // Include route only if time options exist
            if (!empty($timeOpt)) {
                $routes[] = [
                    "id"        => $routeID,
                    "name"      => $routeName,
                    //    "pickUpOpt" => [],        
                    "timeOpt"   => $timeOpt
                ];
            }
        }

        echo json_encode([
            "data" => [
                "routes"       => $routes,
                "staffRouteID" => $staffRouteID,
                "staffStopID"  => $staffStopID
            ],
            "statusCode" => 200
        ]);

        break;


    // ===================== CASE: ADD_REQUEST =====================
    case 'ADD_REQUEST':
        $route = intval($_REQUEST['route'] ?? 0);
        $pickUp = intval($_REQUEST['pickUp'] ?? 0);
        $time = intval($_REQUEST['time'] ?? 0);
        // $timeValue = $_REQUEST['timeValue'] ?? '';
        $days = $_REQUEST['days'] ?? [];
        $flag = isset($_REQUEST['flag']) ? $_REQUEST['flag'] : 'APP';
        $staffID = isset($_REQUEST['staffID']) ? intval($_REQUEST['staffID'] ?? 0) : 0;
        if ($flag == 'CTRL') {
            $staff = $staffID > 0 ? $staffID : $user_id;
        } else {
            $staff = $user_id;
        }

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
        if (empty($selectedTimeValue)) {
            echo json_encode([
                "error" => [
                    "message" => "Invalid time selected"
                ],
                "statusCode" => 400
            ]);
            exit;
        }


        if (!empty($selectedTimeValue)) {
            // New structure: time value is provided directly
            $selectedTime = $selectedTimeValue;
        }
        //  else {
        //     // Fallback to old structure: get time from trip ID
        //     $timeTripSql = "SELECT TIME(dtTrip) as trip_time FROM st_trips WHERE iTripID = $time AND cStatus = 'A'";
        //     $timeTripRes = sql_query($timeTripSql);

        //     if (sql_num_rows($timeTripRes) == 0) {
        //         echo json_encode([
        //             "error" => [
        //                 "message" => "Selected time not found. Please provide timeValue parameter."
        //             ],
        //             "statusCode" => 400
        //         ]);
        //         exit;
        //     }

        //     $timeTripData = sql_fetch_assoc($timeTripRes);
        //     $selectedTime = $timeTripData['trip_time'];
        // }

        $timing = date('H:i', strtotime($selectedTime));

        // Get pickup point name
        $pickupPointSql = "SELECT vName FROM st_route_stops WHERE iStopID = $pickUp AND cStatus = 'A'";
        $pickupPointRes = sql_query($pickupPointSql);
        $pickupPointName = '';

        if (sql_num_rows($pickupPointRes) > 0) {
            $pickupPointData = sql_fetch_assoc($pickupPointRes);
            $pickupPointName = db_output2($pickupPointData['vName']);
        }

        // Calculate the maximum allowed date (2 days from now)
        $currentDate = date('Y-m-d');
        $maxAllowedDate = date('Y-m-d', strtotime('+2 days'));

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
                $errors['tripNotFound'][] = $tripID;
                continue;
            }

            $tripData = sql_fetch_assoc($tripRes);
            $tripDate = date('Y-m-d', strtotime($tripData['dtTrip']));
            $tripDateTime = "$tripDate $selectedTime";

            // **NEW VALIDATION: Check if trip date is within the next 2 days**
            if ($tripDate > $maxAllowedDate) {
                $errors['beyondAllowed'][] = date('d M Y', strtotime($tripDate));
                continue;
            }

            // Check if trip date is in the past
            if ($tripDate < $currentDate) {
                $errors['pastDate'][] = date('d M Y', strtotime($tripDate));
                continue;
            }

            // Check if trip is within 2 hours from now (booking restriction)
            $currentDateTime = date('Y-m-d H:i:s');
            $twoHoursFromNow = date('Y-m-d H:i:s', strtotime('+2 hours'));

            // if ($tripDateTime < $twoHoursFromNow) {
            //     $errors[] = "Cannot book trip on $tripDate - bookings must be made at least 2 hours in advance";
            //     continue;
            // }
            //  Do not allow request if that person already has a request on that day (any route/time)
            $dayConflictSql = "SELECT iTrReqID FROM st_request WHERE iStaffID = $staff AND dPickup = '" . db_input($tripDate) . "' AND cStatus = 'A' LIMIT 1";
            $dayConflictRes = sql_query($dayConflictSql);
            if (sql_num_rows($dayConflictRes) > 0) {
                $errors['dayConflict'][] = $tripDate;

                continue;
            }
            // Check if request already exists for this staff, route, and trip
            $existingSql = "SELECT iTrReqID FROM st_request 
                           WHERE iStaffID = $staff AND iRouteID = $route AND iTripID = $tripID AND cStatus = 'A'";
            $existingRes = sql_query($existingSql);

            if (sql_num_rows($existingRes) > 0) {
                $errors['alreadyExists'][] = $tripDate;
                continue;
            }

            // Get stop's offset time to calculate pickup time
            $stopSql = "SELECT tOffsetFromStart FROM st_route_stops 
                       WHERE iStopID = $pickUp AND cStatus = 'A'";
            $stopRes = sql_query($stopSql);

            if (sql_num_rows($stopRes) == 0) {
                $errors['stopMissing'][] = $pickUp;

                continue;
            }

            $stopData = sql_fetch_assoc($stopRes);
            $offsetMinutes = intval($stopData['tOffsetFromStart']);

            // Calculate pickup time: trip start time + stop offset
            $pickupTime = date('H:i:s', strtotime($selectedTime) + $offsetMinutes * 60);

            // Get next ID and rank
            $iTrReqID = NextID('iTrReqID', 'st_request');
            $nextRank = GetMaxRank('st_request', "", 'iRank');

            // Find the best available trip for this request
            $finalTripID = $tripID;

            // Get the group ID for this trip
            // $grpSql = "SELECT iGrpID FROM st_trips WHERE iTripID = $tripID AND cStatus = 'A'";
            // $grpRes = sql_query($grpSql);

            // if (sql_num_rows($grpRes) > 0) {
            //     $grpData = sql_fetch_assoc($grpRes);
            //     $grpID = (int) $grpData['iGrpID'];

            //     // Get all trips in this group with their current capacity status
            //     $groupTripsSql = "SELECT iTripID, iCapacity, iRequested 
            //                      FROM st_trips 
            //                      WHERE iGrpID = $grpID AND cStatus = 'A' 
            //                      ORDER BY iTripID";
            //     $groupTripsRes = sql_query($groupTripsSql);

            //     $availableTrip = null;
            //     $lastTrip = null;

            //     while ($groupTripRow = sql_fetch_assoc($groupTripsRes)) {
            //         $currentTripID = (int) $groupTripRow['iTripID'];
            //         $capacity = (int) $groupTripRow['iCapacity'];
            //         $requested = (int) $groupTripRow['iRequested'];

            //         $lastTrip = $currentTripID; // Keep track of last trip

            //         // Check if this trip has available capacity
            //         if ($requested < $capacity) {
            //             $availableTrip = $currentTripID;
            //             break; // Found available trip, use it
            //         }
            //     }

            //     // Use available trip if found, otherwise use the last trip in group
            //     $finalTripID = $availableTrip ?? $lastTrip ?? $tripID;
            // }

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
                $staff,
                $route,
                '" . db_input($tripDate) . "',
                '" . db_input($pickupTime) . "',
                $pickUp,
                $finalTripID,
                '" . db_input($currentDateTime) . "',
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
                    $errors['capacityFailed'][] = $tripDate;
                }
            } else {
                $errors['saveFailed'][] = $tripDate;
            }
        }
        $staffCheckSql = "SELECT iRouteID, iStopID FROM staff WHERE iStaffID = $staff AND cStatus = 'A'";
        $staffCheckRes = sql_query($staffCheckSql);

        if (sql_num_rows($staffCheckRes) > 0) {
            $staffRow = sql_fetch_assoc($staffCheckRes);

            // If both are zero, update them with the requested values
            if ((int)$staffRow['iRouteID'] == 0 && (int)$staffRow['iStopID'] == 0) {

                $updateStaffSql = "UPDATE staff 
                           SET iRouteID = $route, iStopID = $pickUp 
                           WHERE iStaffID = $staff AND cStatus = 'A'";

                sql_query($updateStaffSql);
            }
        }

        $finalErrors = [];

        if (!empty($errors['dayConflict'])) {
            $dates = array_unique($errors['dayConflict']);
            $finalErrors[] = "Already has request on: " . implode(", ", $dates);
        }

        if (!empty($errors['alreadyExists'])) {
            $dates = array_unique($errors['alreadyExists']);
            $finalErrors[] = "Request already exists on: " . implode(", ", $dates);
        }

        if (!empty($errors['tripNotFound'])) {
            $ids = array_unique($errors['tripNotFound']);
            $finalErrors[] = "Trip not found: " . implode(", ", $ids);
        }

        if (!empty($errors['stopMissing'])) {
            $finalErrors[] = "Pickup stop not found";
        }

        if (!empty($errors['saveFailed'])) {
            $dates = array_unique($errors['saveFailed']);
            $finalErrors[] = "Failed to save request on: " . implode(", ", $dates);
        }
        if (!empty($errors['capacityFailed'])) {
            $dates = array_unique($errors['capacityFailed']);
            $finalErrors[] = "Failed to capacity ";
        }

        $errors = $finalErrors;



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
