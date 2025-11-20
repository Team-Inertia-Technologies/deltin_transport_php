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

    // Fetch staff route & stop
    $staffSql = "SELECT iRouteID, iStopID 
                 FROM staff 
                 WHERE iStaffID = $user_id AND cStatus = 'A'";
    $staffRes = sql_query($staffSql);
    $staffData = sql_fetch_assoc($staffRes);

    $staffRouteID = (int) ($staffData['iRouteID'] ?? 0);
    $staffStopID  = (int) ($staffData['iStopID'] ?? 0);

    // Fetch all active routes
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

            // ---- DAYS FOR THIS TIME SLOT ----
            $today = date('Y-m-d');
            $maxDate = date('Y-m-d', strtotime('+7 days'));
            $twoHoursFromNow = date('Y-m-d H:i:s', strtotime('+2 hours'));

            $daysSql = "SELECT iTripID, DATE(dtTrip) AS trip_date
                        FROM st_trips
                        WHERE iRouteID = $routeID AND cStatus = 'A'
                        AND TIME(dtTrip) = '$tripTime'
                        AND DATE(dtTrip) >= '$today' 
                        AND DATE(dtTrip) <= '$maxDate'
                        AND dtTrip >= '$twoHoursFromNow'
                        ORDER BY trip_date 
                        LIMIT 7";
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
            $stopsRes = sql_query($stopsSql); // re-run stops query fresh

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
                "pickUpOpt"=> $pickUpOpt  // moved here
            ];

            $timeIndex++;
        }

        // Include route only if time options exist
        if (!empty($timeOpt)) {
            $routes[] = [
                "id"        => $routeID,
                "name"      => $routeName,
            //    "pickUpOpt" => [],         // maintained for compatibility (not removed)
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
    // === Configuration / inputs ===
    $route = intval($_REQUEST['route'] ?? 0);
    $pickUp = intval($_REQUEST['pickUp'] ?? 0);
    $time = intval($_REQUEST['time'] ?? 0); // REQUIRED: trip ID (old structure)
    $days = $_REQUEST['days'] ?? [];
   // $debugMode = !empty($_REQUEST['debug']) && $_REQUEST['debug'] == 1; // &debug=1 during testing

    // basic validation
    if ($route == 0 || $pickUp == 0 || $time == 0 || empty($days)) {
        echo json_encode([
            "error" => [
                "message" => "Missing required fields: route, pickUp, time (trip ID), or days"
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    if (!is_array($days)) {
        $days = [$days];
    }

    // === Get route details ===
    $routeSql = "SELECT vName, vDestination FROM st_route WHERE iRouteID = $route AND cStatus = 'A'";
    $routeRes = sql_query($routeSql);
    if (sql_num_rows($routeRes) == 0) {
        echo json_encode([
            "error" => ["message" => "Route not found"],
            "statusCode" => 400
        ]);
        exit;
    }
    $routeData = sql_fetch_assoc($routeRes);
    $routeName = $routeData['vName'];
    $destination = $routeData['vDestination'];

    // === Determine base selected time from provided trip ID ($time) ===
    $timeTripSql = "SELECT TIME(dtTrip) AS trip_time FROM st_trips WHERE iTripID = $time AND cStatus = 'A'";
    $timeTripRes = sql_query($timeTripSql);
    if (sql_num_rows($timeTripRes) == 0) {
        echo json_encode([
            "error" => ["message" => "Selected time not found for trip ID $time"],
            "statusCode" => 400
        ]);
        exit;
    }
    $timeTripData = sql_fetch_assoc($timeTripRes);
    $selectedTime = date('H:i:s', strtotime($timeTripData['trip_time'])); // normalized H:i:s
    $timing = date('H:i', strtotime($selectedTime)); // for response

    // === Pickup point name for response ===
    $pickupPointSql = "SELECT vName FROM st_route_stops WHERE iStopID = $pickUp AND cStatus = 'A'";
    $pickupPointRes = sql_query($pickupPointSql);
    $pickupPointName = '';
    if (sql_num_rows($pickupPointRes) > 0) {
        $pickupPointData = sql_fetch_assoc($pickupPointRes);
        $pickupPointName = db_output2($pickupPointData['vName']);
    }

    // === Prepare counters and arrays ===
    $successCount = 0;
    $errors = [];
    $selectedDays = [];

    // === Process each day (tripID provided in $days) ===
    foreach ($days as $tripIDRaw) {
        $tripID = intval($tripIDRaw);

        // skip special "select all" value 0
        if ($tripID === 0) continue;

        // fetch trip info
        $tripSql = "SELECT iTripID, dtTrip FROM st_trips WHERE iTripID = $tripID AND cStatus = 'A'";
        $tripRes = sql_query($tripSql);
        if (sql_num_rows($tripRes) == 0) {
            $errors[] = "Trip ID $tripID not found";
            continue;
        }

        $tripData = sql_fetch_assoc($tripRes);
        $tripDate = date('Y-m-d', strtotime($tripData['dtTrip'])); // date part

        // build trip start timestamp using trip's date + selectedTime (selectedTime from $time trip id)
        $tripStartTs = strtotime($tripDate . ' ' . $selectedTime);
        if ($tripStartTs === false) {
            $errors[] = "Invalid trip start datetime for trip $tripID";
            continue;
        }

        // Optional: booking window check (uncomment to enable)
        // $twoHoursFromNow = strtotime('+2 hours');
        // if ($tripStartTs < $twoHoursFromNow) {
        //     $errors[] = "Cannot book trip on " . date('j M Y', $tripStartTs) . " - bookings must be made at least 2 hours in advance";
        //     continue;
        // }

        // skip if request already exists for this staff/route/trip
        $existingSql = "SELECT iTrReqID FROM st_request 
                        WHERE iStaffID = $user_id AND iRouteID = $route AND iTripID = $tripID AND cStatus = 'A'";
        $existingRes = sql_query($existingSql);
        if (sql_num_rows($existingRes) > 0) {
            $errors[] = "Request already exists for trip on " . date('j M Y', $tripStartTs);
            continue;
        }

        // fetch stop offset - assumed integer minutes (0,5,10,...)
        $stopSql = "SELECT tOffsetFromStart FROM st_route_stops WHERE iStopID = $pickUp AND cStatus = 'A'";
        $stopRes = sql_query($stopSql);
        if (sql_num_rows($stopRes) == 0) {
            $errors[] = "Stop ID $pickUp not found";
            continue;
        }
        $stopData = sql_fetch_assoc($stopRes);
        $offsetMinutes = intval($stopData['tOffsetFromStart']);

        // guard offset sanity
        if ($offsetMinutes < 0 || $offsetMinutes > 24 * 60) {
            $errors[] = "Invalid offset for stop $pickUp: " . $stopData['tOffsetFromStart'];
            continue;
        }

        // calculate pickup timestamp, date, and time
        $pickupTs = $tripStartTs + ($offsetMinutes * 60);
        $pickupTime = date('H:i:s', $pickupTs);   // tPickup
        $pickupDate = date('Y-m-d', $pickupTs);   // dPickup (handles midnight rollover)
        $pickupDateTime = date('Y-m-d H:i:s', $pickupTs);

        // Optional: check booking against pickup datetime (uncomment to enable)
        // if (strtotime($pickupDateTime) < strtotime('+2 hours')) {
        //     $errors[] = "Cannot book pickup on " . date('j M Y H:i', $pickupTs) . " - must be at least 2 hours ahead";
        //     continue;
        // }

        // Determine final trip by checking group capacity (preserve your original logic)
        $finalTripID = $tripID;
        $grpSql = "SELECT iGrpID FROM st_trips WHERE iTripID = $tripID AND cStatus = 'A'";
        $grpRes = sql_query($grpSql);
        if (sql_num_rows($grpRes) > 0) {
            $grpData = sql_fetch_assoc($grpRes);
            $grpID = (int)$grpData['iGrpID'];

            $groupTripsSql = "SELECT iTripID, iCapacity, iRequested 
                              FROM st_trips 
                              WHERE iGrpID = $grpID AND cStatus = 'A' 
                              ORDER BY iTripID";
            $groupTripsRes = sql_query($groupTripsSql);

            $availableTrip = null;
            $lastTrip = null;
            while ($groupTripRow = sql_fetch_assoc($groupTripsRes)) {
                $currentTripID = (int)$groupTripRow['iTripID'];
                $capacity = (int)$groupTripRow['iCapacity'];
                $requested = (int)$groupTripRow['iRequested'];

                $lastTrip = $currentTripID;
                if ($requested < $capacity) {
                    $availableTrip = $currentTripID;
                    break;
                }
            }
            $finalTripID = $availableTrip ?? $lastTrip ?? $tripID;
        }

        // Prepare insert
        $iTrReqID = NextID('iTrReqID', 'st_request');
        $nextRank = GetMaxRank('st_request', "iStaffID=$user_id and cStatus='A'", 'iRank');
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
            '$pickupDate',
            '$pickupTime',
            $pickUp,
            $finalTripID,
            '$currentDateTime',
            $nextRank,
            'A'
        )";

        if (sql_query($insertSql)) {
            // update requested count for the used trip
            $updateTripSql = "UPDATE st_trips 
                              SET iRequested = iRequested + 1 
                              WHERE iTripID = $finalTripID AND cStatus = 'A'";

            if (sql_query($updateTripSql)) {
                $successCount++;
                $selectedDays[] = date('l, j F', strtotime($pickupDate));
                // if ($debugMode) {
                //     $errors[] = "DEBUG: trip=$tripID usedTrip=$finalTripID pickup='$pickupDate $pickupTime' offsetMin=$offsetMinutes";
                // }
            } else {
                // rollback inserted request to avoid partial state
                $deleteSql = "DELETE FROM st_request WHERE iTrReqID = $iTrReqID";
                sql_query($deleteSql);
                $errors[] = "Failed to update trip capacity for trip on " . date('j M Y', $pickupTs);
            }
        } else {
            $errors[] = "Failed to save request for trip on " . date('j M Y', $pickupTs);
        }
    } // end foreach days

    // === If staff recorded route/stop are zero, update them ===
    $staffCheckSql = "SELECT iRouteID, iStopID FROM staff WHERE iStaffID = $user_id AND cStatus = 'A'";
    $staffCheckRes = sql_query($staffCheckSql);
    if (sql_num_rows($staffCheckRes) > 0) {
        $staffRow = sql_fetch_assoc($staffCheckRes);
        if ((int)$staffRow['iRouteID'] == 0 && (int)$staffRow['iStopID'] == 0) {
            $updateStaffSql = "UPDATE staff 
                               SET iRouteID = $route, iStopID = $pickUp 
                               WHERE iStaffID = $user_id AND cStatus = 'A'";
            sql_query($updateStaffSql);
        }
    }

    // === Final response ===
    if ($successCount > 0) {
        $message = "$successCount request(s) submitted successfully";
        if (!empty($errors)) {
            $message .= ". Some requests failed: " . implode("; ", $errors);
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
                "message" => "No requests were saved. " . implode("; ", $errors)
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
