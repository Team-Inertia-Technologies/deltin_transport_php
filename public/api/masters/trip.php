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

    // ===================== CASE 1: LIST =====================
    case 'LIST':
        $fromDate = $_REQUEST['fromDate'] ?? '';
        $toDate = $_REQUEST['toDate'] ?? '';
        $routeID = intval($_REQUEST['routeID'] ?? 0);

        // Build WHERE conditions
        $whereConditions = ["t.cStatus = 'A'"];

        if (!empty($fromDate)) {
            $whereConditions[] = "DATE(t.dtTrip) >= '$fromDate'";
        }

        if (!empty($toDate)) {
            $whereConditions[] = "DATE(t.dtTrip) <= '$toDate'";
        }

        if ($routeID > 0) {
            $whereConditions[] = "t.iRouteID = $routeID";
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Modified query to get trips with vehicle details
        $sql = "SELECT 
                    t.iTripID as id,
                    t.dtTrip,
                    t.iGrpID AS grpID,
                    r.vName as route,
                    r.vDestination as destination,
                    t.iCapacity,
                    t.iAvaialed as availed,
                    t.iRequested as pax,
                    t.iVehicleID,
                    v.vRnum as vehicleNumber,
                    vc.iCapacity as vehicleCapacity
                FROM st_trips t
                LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                LEFT JOIN vehicle v ON t.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
                LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                WHERE $whereClause
                ORDER BY t.dtTrip DESC, t.iGrpID, t.iTripID";

        $res = sql_query($sql);
        $groupedTrips = [];
        $processedGroups = [];

        // Group trips by iGrpID and collect vehicle information
        while ($row = sql_fetch_assoc($res)) {
            $grpID = (int) $row['grpID'];

            if (!isset($groupedTrips[$grpID])) {
                $groupedTrips[$grpID] = [
                    "grpID" => $grpID,
                    "dateTime" => date('d/m/Y H:i', strtotime($row['dtTrip'])),
                    "route" => $row['route'] ?? '',
                    "destination" => $row['destination'] ?? '',
                    "totalCapacity" => 0,
                    "totalPax" => 0,
                    "totalAvailed" => 0,
                    "vehicles" => []
                ];
            }

            // Add vehicle details for this trip
            if (!empty($row['iVehicleID'])) {
                $vehicleCapacity = (int) ($row['vehicleCapacity'] ?? 0);
                $vehicleDetail = ($row['vehicleNumber'] ?? '') . ' (' . $vehicleCapacity . ')';

                $groupedTrips[$grpID]['vehicles'][] = [
                    "tripID" => (int) $row['id'],
                    "vehicleID" => (int) $row['iVehicleID'],
                    "vehicleDetail" => $vehicleDetail,
                    "capacity" => $vehicleCapacity
                ];

                // Accumulate totals
                $groupedTrips[$grpID]['totalCapacity'] += $vehicleCapacity;
                $groupedTrips[$grpID]['totalPax'] += (int) ($row['pax'] ?? 0);
                $groupedTrips[$grpID]['totalAvailed'] += (int) ($row['availed'] ?? 0);
            }
        }

        // Convert grouped trips to final row data format - grouped by IGrpID
        $rowData = [];
        foreach ($groupedTrips as $grpID => $tripGroup) {
            // Create vehicle details string (comma separated)
            $vehicleDetails = [];
            
            foreach ($tripGroup['vehicles'] as $vehicle) {
                $vehicleDetails[] = $vehicle['vehicleDetail'];
            }
            
            // Single row per group with all vehicles listed
            $rowData[] = [
                "grpID" => $grpID,
                "dateTime" => $tripGroup['dateTime'],
                "route" => $tripGroup['route'],
                "destination" => $tripGroup['destination'],
                "vehicleDetails" => implode(', ', $vehicleDetails), // Comma separated vehicle details
                "totalCapacity" => $tripGroup['totalCapacity'],
                "pax" => $tripGroup['totalPax'], // Sum of iRequested for the group
                "availed" => $tripGroup['totalAvailed'], // Sum of iAvailed for the group
                "vehicleCount" => count($tripGroup['vehicles'])
            ];
        }

        // Get routes for dropdown
        $routesSql = "SELECT iRouteID, vName FROM st_route WHERE cStatus = 'A' ORDER BY iRank";
        $routesRes = sql_query($routesSql);

        $routesOpt = [
            ["id" => 0, "name" => "All"]
        ];

        while ($routeRow = sql_fetch_assoc($routesRes)) {
            $routesOpt[] = [
                "id" => (int) $routeRow['iRouteID'],
                "name" => $routeRow['vName']
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

    // ===================== CASE ADD_ONLOAD =====================
    case 'ADD_ONLOAD':
        $vehicleSql = "SELECT v.iVehicleID, v.vRnum, vc.iCapacity 
                      FROM vehicle v
                      LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                      WHERE v.cStatus = 'A'
                      ORDER BY v.vRnum";
        $vehicleRes = sql_query($vehicleSql);

        $vehiOpt = [
            ["id" => 0, "name" => "Choose"]
        ];

        while ($vehicleRow = sql_fetch_assoc($vehicleRes)) {
            $capacity = $vehicleRow['iCapacity'] ?? 0;
            $vehiOpt[] = [
                "id" => (int) $vehicleRow['iVehicleID'],
                "name" => $vehicleRow['vRnum'] . ' (' . $capacity . ')'
            ];
        }

        // Get mode options (transportation modes)    
        $modeOpt = [
            ["id" => 0, "name" => "Choose"],
            ["id" => 1, "name" => "Bus"]
        ];

        // Get vendor options (vehicle owners/drivers)
        $vendorSql = "SELECT DISTINCT ven.iVendorID, ven.vName 
                     FROM vendor ven 
                     INNER JOIN vehicle v ON v.iVendorID = ven.iVendorID 
                     WHERE v.cStatus = 'A' AND ven.cStatus = 'A' AND ven.cType IN ('B','T')
                     ORDER BY ven.vName";
        $vendorRes = sql_query($vendorSql);

        $vendorOpt = [
            ["id" => 0, "name" => "Choose"]
        ];

        while ($vendorRow = sql_fetch_assoc($vendorRes)) {
            $vendorOpt[] = [
                "id" => (int) $vendorRow['iVendorID'],
                "name" => $vendorRow['vName']
            ];
        }

        // Get all drivers independently from driver table
        $allDriversSql = "SELECT d.iDriverID, d.vName as drName, d.cStatus
                         FROM driver d
                         WHERE d.cStatus IN ('A')
                         ORDER BY d.vName";
        $allDriversRes = sql_query($allDriversSql);

        $vhDriver = [];
        while ($driverRow = sql_fetch_assoc($allDriversRes)) {
            $vhDriver[] = [
                "id" => (int) $driverRow['iDriverID'],
                "drName" => $driverRow['drName'],
                "active" => $driverRow['cStatus']
            ];
        }

        // Get routes for rdOpt 
        $routesSql = "SELECT iRouteID, vName, vDestination FROM st_route WHERE cStatus = 'A' ORDER BY iRank";
        $routesRes = sql_query($routesSql);

        $rdOpt = [];

        while ($routeRow = sql_fetch_assoc($routesRes)) {
            $rdOpt[] = [
                "id" => (int) $routeRow['iRouteID'],
                "route" => $routeRow['vName'] ?? '',
                "dest" => $routeRow['vDestination'] ?? ''
            ];
        }

        // Get table array with vehicle details
        $tableArrSql = "SELECT v.iVehicleID, v.vRnum, vc.iCapacity, ven.vName as vOwner
                       FROM vehicle v
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       LEFT JOIN vendor ven ON v.iVendorID = ven.iVendorID AND ven.cStatus = 'A' and ven.cType IN ('B','T') 
                       WHERE v.cStatus = 'A'
                       ORDER BY v.vRnum";
        $tableArrRes = sql_query($tableArrSql);

        $tableArr = [];

        while ($tableRow = sql_fetch_assoc($tableArrRes)) {
            $tableArr[] = [
                "id" => (int) $tableRow['iVehicleID'],
                "vhNum" => $tableRow['vRnum'] ?? '',
                "vhCap" => (int) ($tableRow['iCapacity'] ?? 0),
                "vhOwner" => $tableRow['vOwner'] ?? '',
                "vhDriver" => $vhDriver  // Same driver list for all vehicles
            ];
        }

        echo json_encode([
            "data" => [
                "rdOpt" => $rdOpt,
                "vehiOpt" => $vehiOpt,
                "modeOpt" => $modeOpt,
                "vendorOpt" => $vendorOpt,
                "tableArr" => $tableArr
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE VIEW_TRIP =====================
    case 'VIEW_TRIP':
        $iGrpID = intval($_REQUEST['iGrpID'] ?? 0);

        if ($iGrpID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Missing or invalid iGrpID parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Get trip details with route, vehicle, and driver information
        $sql = "SELECT 
                    t.iTripID,
                    t.iGrpID,
                    t.dtTrip,
                    r.vName as routeName,
                    r.vDestination as destination,
                    v.iVehicleID,
                    v.vRnum as vehicleNumber,
                    vc.iCapacity as vehicleCapacity,
                    ven.iVendorID,
                    ven.vName as vehicleOwner,
                    ven.vContactNum as vendorMobile,
                    d.iDriverID,
                    d.vName as driverName,
                    d.vMobileNum as driverMobile,
                    t.iCapacity as totalCapacity
                FROM st_trips t
                LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                LEFT JOIN vehicle v ON t.iVehicleID = v.iVehicleID
                LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                LEFT JOIN vendor ven ON v.iVendorID = ven.iVendorID AND ven.cStatus = 'A' AND ven.cType IN ('B','T')
                LEFT JOIN driver d ON t.iDriverID = d.iDriverID
                WHERE t.iGrpID = $iGrpID AND t.cStatus = 'A'
                ORDER BY t.iTripID";

        $res = sql_query($sql);

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "No trips found for the specified group ID"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $routeInfo = [];
        $vehicles = [];
        $vendorsMap = [];
        $driversMap = [];

        while ($row = sql_fetch_assoc($res)) {
            // Set route info (same for all trips in group)
            if (empty($routeInfo)) {
                $routeInfo = [
                    "routeName" => $row['routeName'] ?? '',
                    "destination" => $row['destination'] ?? '',
                    "tripDateTime" => date('d/m/Y H:i', strtotime($row['dtTrip'])),
                    "totalCapacity" => (int) ($row['totalCapacity'] ?? 0)
                ];
            }

            // Collect vendors and their vehicles directly without validation
            $vendorID = (int) $row['iVendorID'];
            if ($vendorID > 0) {
                if (!isset($vendorsMap[$vendorID])) {
                    $vendorsMap[$vendorID] = [
                        "vendorID" => $vendorID,
                        "vendorName" => $row['vehicleOwner'] ?? '',
                        "vendorMobile" => $row['vendorMobile'] ?? '',
                        "vehicles" => []
                    ];
                }

                // Add vehicle to this vendor directly
                $vendorsMap[$vendorID]['vehicles'][] = [
                    "vehicleID" => (int) $row['iVehicleID'],
                    "vehicleNumber" => $row['vehicleNumber'] ?? '',
                    "vehicleCapacity" => (int) ($row['vehicleCapacity'] ?? 0)
                ];
            }

            // Collect unique drivers
            $driverID = (int) ($row['iDriverID'] ?? 0);
            if ($driverID > 0 && !isset($driversMap[$driverID])) {
                $driversMap[$driverID] = [
                    "driverID" => $driverID,
                    "driverName" => $row['driverName'] ?? '',
                    "driverMobile" => $row['driverMobile'] ?? ''
                ];
            }

            // Add trip details for main trip_details array
            $vehicles[] = [
                "tripID" => (int) $row['iTripID'],
                "vehicleID" => (int) $row['iVehicleID'],
                "vehicleNumber" => $row['vehicleNumber'] ?? '',
                "vehicleCapacity" => (int) ($row['vehicleCapacity'] ?? 0),
                "vehicleOwner" => $row['vehicleOwner'] ?? '',
                "vehicleOwnerID" => $vendorID,
                "driverID" => $driverID,
                "driverName" => $row['driverName'] ?? '',
                "driverMobile" => $row['driverMobile'] ?? ''

            ];
        }

        // Get stops information for this route and trip group
        $stops = [];
        if (!empty($routeInfo)) {
            // Get the route ID from the first trip
            $routeIDSql = "SELECT iRouteID FROM st_trips WHERE iGrpID = $iGrpID AND cStatus = 'A' LIMIT 1";
            $routeIDRes = sql_query($routeIDSql);

            if ($routeIDRow = sql_fetch_assoc($routeIDRes)) {
                $routeID = (int) $routeIDRow['iRouteID'];

                // Get all stops for this route with their timings
                $stopsSql = "SELECT 
                                s.iStopID,
                                s.vName as stopName,
                                s.tOffsetFromStart,
                                s.iRank
                            FROM st_route_stops s
                            WHERE s.iRouteID = $routeID AND s.cStatus = 'A'
                            ORDER BY s.iRank";
                $stopsRes = sql_query($stopsSql);

                while ($stopRow = sql_fetch_assoc($stopsRes)) {
                    $stopID = (int) $stopRow['iStopID'];
                    $offsetMinutes = intval($stopRow['tOffsetFromStart']);

                    // Calculate pickup time for this stop
                    // Get trip start time from any trip in this group
                    $tripTimeSql = "SELECT dtTrip FROM st_trips WHERE iGrpID = $iGrpID AND cStatus = 'A' LIMIT 1";
                    $tripTimeRes = sql_query($tripTimeSql);
                    $tripTimeRow = sql_fetch_assoc($tripTimeRes);

                    $tripStartTime = date('H:i:s', strtotime($tripTimeRow['dtTrip']));
                    $pickupTime = date('H:i', strtotime($tripStartTime) + ($offsetMinutes * 60));

                    // Get staff who will board at this stop for any trip in this group
                    $staffSql = "SELECT DISTINCT
                                    st.iStaffID,
                                    st.vName as staffName,
                                    st.vMobile as staffMobile,
                                    req.iVehicleID,
                                    v.vRnum as vehicleNumber,
                                    req.dtIn
                                FROM st_request req
                                INNER JOIN staff st ON req.iStaffID = st.iStaffID AND st.cStatus = 'A'
                                INNER JOIN st_trips t ON req.iTripID = t.iTripID
                                LEFT JOIN vehicle v ON req.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
                                WHERE t.iGrpID = $iGrpID 
                                AND req.iStopID = $stopID 
                                AND req.cStatus = 'A'
                                ORDER BY st.vName";
                    $staffRes = sql_query($staffSql);

                    $staffList = [];
                    while ($staffRow = sql_fetch_assoc($staffRes)) {
                        $staffList[] = [
                            "staffID" => (int) $staffRow['iStaffID'],
                            "staffName" => $staffRow['staffName'] ?? '',
                            "staffMobile" => $staffRow['staffMobile'] ?? '',
                            "vehicleNumber" => $staffRow['vehicleNumber'] ?? '',
                            "entered" => !empty($staffRow['dtIn'])
                        ];
                    }

                    $stops[] = [
                        "stopID" => $stopID,
                        "stopName" => $stopRow['stopName'] ?? '',
                        "pickupTime" => $pickupTime,
                        "offsetMinutes" => $offsetMinutes,
                        "rank" => (int) $stopRow['iRank'],
                        "staff" => $staffList,
                        "staffCount" => count($staffList)
                    ];
                }
            }
        }

        // Get ALL vendors and their vehicles with cStatus='A'
        $vendorsSql = "SELECT 
                        ven.iVendorID,
                        ven.vName as vendorName,
                        ven.vContactNum as vendorMobile,
                        v.iVehicleID,
                        v.vRnum as vehicleNumber,
                        vc.iCapacity as vehicleCapacity
                      FROM vendor ven
                      LEFT JOIN vehicle v ON ven.iVendorID = v.iVendorID AND v.cStatus = 'A'
                      LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                      WHERE ven.cStatus = 'A' AND ven.cType IN ('B','T')
                      ORDER BY ven.vName, v.vRnum";
        
        $vendorsRes = sql_query($vendorsSql);
        $allVendorsMap = [];
        
        while ($vendorRow = sql_fetch_assoc($vendorsRes)) {
            $vendorID = (int) $vendorRow['iVendorID'];
            
            if (!isset($allVendorsMap[$vendorID])) {
                $allVendorsMap[$vendorID] = [
                    "vendorID" => $vendorID,
                    "vendorName" => $vendorRow['vendorName'] ?? '',
                    "vendorMobile" => $vendorRow['vendorMobile'] ?? '',
                    "vehicles" => []
                ];
            }
            
            // Add vehicle if it exists
            if (!empty($vendorRow['iVehicleID'])) {
                $allVendorsMap[$vendorID]['vehicles'][] = [
                    "vehicleID" => (int) $vendorRow['iVehicleID'],
                    "vehicleNumber" => $vendorRow['vehicleNumber'] ?? '',
                    "vehicleCapacity" => (int) ($vendorRow['vehicleCapacity'] ?? 0)
                ];
            }
        }

        // Convert maps to arrays
        $vendors = array_values($allVendorsMap);
        $drivers = array_values($driversMap);

        echo json_encode([
            "data" => [
                "iGrpID" => $iGrpID,
                "routeInfo" => $routeInfo,
                "trip_details" => $vehicles,
                "tripCount" => count($vehicles),
                "vendors" => $vendors,
                "drivers" => $drivers,
                "stops" => $stops
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE ADD_TRIP =====================
    case 'ADD_TRIP':
        $fromDate = $_REQUEST['fromDate'] ?? '';
        $toDate = $_REQUEST['toDate'] ?? '';
        $routeID = intval($_REQUEST['routeID'] ?? 0);
        $tripInfo = $_REQUEST['tripInfo'] ?? [];

        // Validate required parameters
        if (empty($fromDate) || empty($toDate) || $routeID <= 0 || empty($tripInfo)) {
            echo json_encode([
                "error" => [
                    "message" => "Missing required parameters: fromDate, toDate, routeID, or tripInfo"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate date format and range
        $fromDateTime = DateTime::createFromFormat('Y-m-d', $fromDate);
        $toDateTime = DateTime::createFromFormat('Y-m-d', $toDate);

        if (!$fromDateTime || !$toDateTime || $fromDateTime > $toDateTime) {
            echo json_encode([
                "error" => [
                    "message" => "Invalid date range"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $errors = [];
        $insertValues = [];

        // Generate date range
        $dateRange = [];
        $currentDate = clone $fromDateTime;
        while ($currentDate <= $toDateTime) {
            $dateRange[] = $currentDate->format('Y-m-d');
            $currentDate->add(new DateInterval('P1D'));
        }

        // Get starting iTripID using NextID function
        $startingTripID = NextID('iTripID', 'st_trips');
        $currentTripID = $startingTripID;

        // Get starting iGrpID using NextID function
        $startingGrpID = NextID('iGrpID', 'st_trips');
        $currentGrpID = $startingGrpID;

        // Track group IDs for same date/time combinations
        $dateTimeGroupMap = [];

        // Process each trip in tripInfo array
        foreach ($tripInfo as $tripIndex => $trip) {
            $time = $trip['time'] ?? '';
            $vehicles = $trip['vehicle'] ?? [];

            if (empty($time) || empty($vehicles)) {
                $errors[] = "Trip at index $tripIndex missing time or vehicles";
                continue;
            }

            // No need to calculate total capacity since each vehicle is a separate trip record

            // Process each date in the range
            foreach ($dateRange as $date) {
                $tripDateTime = $date . ' ' . $time;

                // Check if we already have a group ID for this date/time combination
                if (!isset($dateTimeGroupMap[$tripDateTime])) {
                    $dateTimeGroupMap[$tripDateTime] = $currentGrpID;
                    $currentGrpID++; // Increment for next unique date/time combination
                }

                $groupID = $dateTimeGroupMap[$tripDateTime];

                // Process each vehicle for this trip and date
                foreach ($vehicles as $vehicleIndex => $vehicle) {
                    // Support multiple field name variations for vehicle ID
                    $vehID = intval($vehicle['vhId'] ?? $vehicle['vehID'] ?? 0);
                    // Support multiple field name variations for driver ID
                    $driverID = intval($vehicle['driverId'] ?? $vehicle['driverID'] ?? 0);
                    // Get individual vehicle capacity
                    $vehicleCapacity = intval($vehicle['vhCap'] ?? $vehicle['vhCaps'] ?? $vehicle['capacity'] ?? 0);

                    if ($vehID <= 0) {
                        $errors[] = "Invalid vehicle ID in trip $tripIndex, vehicle $vehicleIndex";
                        continue;
                    }

                    $insertValues[] = "($currentTripID, $groupID, $routeID, '$tripDateTime', $vehID, $driverID, $vehicleCapacity, 1, 'A')";
                    $currentTripID++; // Increment for next record
                }
            }
        }

        $insertedCount = 0;

        // Execute bulk insert if we have values to insert
        if (!empty($insertValues)) {
            // Lock table for better performance during bulk insert
            sql_query("LOCK TABLES st_trips WRITE");

            $insertSql = "INSERT INTO st_trips (
                iTripID,
                iGrpID,
                iRouteID, 
                dtTrip, 
                iVehicleID, 
                iDriverID, 
                iCapacity, 
                iRank, 
                cStatus
            ) VALUES " . implode(', ', $insertValues);

            if (sql_query($insertSql)) {
                $insertedCount = count($insertValues);
            } else {
                $errors[] = "Failed to insert: ";
            }

            // Unlock tables
            sql_query("UNLOCK TABLES");
        }

        // Prepare response
        $response = [
            "data" => [
                "message" => "Trip creation completed",
                // "insertedCount" => $insertedCount,
                // "dateRange" => count($dateRange),
                // "tripCount" => count($tripInfo)
            ],
            "statusCode" => 200
        ];

        if (!empty($errors)) {
            $response["warnings"] = $errors;
        }

        echo json_encode($response);
        break;

    // ===================== CASE UPDATE_TRIP =====================
    case 'UPDATE_TRIP':
        $iGrpID = intval($_REQUEST['iGrpID'] ?? 0);
        $trip_details = $_REQUEST['trip_details'] ?? [];

        // Validate required parameters
        if ($iGrpID <= 0 || empty($trip_details)) {
            echo json_encode([
                "error" => [
                    "message" => "Missing required parameters: iGrpID or trip_details"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $errors = [];
        $updatedCount = 0;
        $insertedCount = 0;

        // Check for duplicate vehicle IDs in the request
        $vehicleIDs = [];
        $duplicateVehicles = [];
        
        foreach ($trip_details as $index => $trip) {
            $vehicleID = intval($trip['vehicleID'] ?? 0);
            if ($vehicleID > 0) {
                if (in_array($vehicleID, $vehicleIDs)) {
                    $duplicateVehicles[] = $vehicleID;
                } else {
                    $vehicleIDs[] = $vehicleID;
                }
            }
        }

        if (!empty($duplicateVehicles)) {
            echo json_encode([
                "error" => [
                    "message" => "Duplicate vehicle IDs found in request: " . implode(', ', array_unique($duplicateVehicles))
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Check if any of these vehicles are already assigned to other trips in the same group
        if (!empty($vehicleIDs)) {
            $vehicleIDsStr = implode(',', $vehicleIDs);
            $existingVehiclesSql = "SELECT DISTINCT iVehicleID, iTripID 
                                   FROM st_trips 
                                   WHERE iGrpID = $iGrpID 
                                   AND iVehicleID IN ($vehicleIDsStr) 
                                   AND cStatus = 'A'";
            $existingVehiclesRes = sql_query($existingVehiclesSql);
            
            $conflictingVehicles = [];
            $existingTripVehicles = [];
            
            while ($row = sql_fetch_assoc($existingVehiclesRes)) {
                $existingTripVehicles[intval($row['iTripID'])] = intval($row['iVehicleID']);
            }
            
            // Check for conflicts (vehicle assigned to different trip in same group)
            foreach ($trip_details as $index => $trip) {
                $tripID = intval($trip['tripID'] ?? 0);
                $vehicleID = intval($trip['vehicleID'] ?? 0);
                
                foreach ($existingTripVehicles as $existingTripID => $existingVehicleID) {
                    if ($vehicleID == $existingVehicleID && $tripID != $existingTripID) {
                        $conflictingVehicles[] = $vehicleID;
                    }
                }
            }
            
            if (!empty($conflictingVehicles)) {
                echo json_encode([
                    "error" => [
                        "message" => "Vehicle(s) already assigned to other trips in this group: " . implode(', ', array_unique($conflictingVehicles))
                    ],
                    "statusCode" => 400
                ]);
                exit;
            }
        }

        // Start transaction
        sql_query("START TRANSACTION");

        try {
            foreach ($trip_details as $index => $trip) {
                $tripID = intval($trip['tripID'] ?? 0);
                $vehicleID = intval($trip['vehicleID'] ?? 0);
                $vehicleCapacity = intval($trip['vehicleCapacity'] ?? 0);
                $vehicleOwnerID = intval($trip['vehicleOwnerID'] ?? 0);
                $driverID = intval($trip['driverID'] ?? 0);

                // Validate required fields
                if ($vehicleID <= 0) {
                    $errors[] = "Invalid vehicleID in trip detail at index $index";
                    continue;
                }

                if ($tripID > 0) {
                    // UPDATE existing trip
                    $updateSql = "UPDATE st_trips SET 
                                    iVehicleID = $vehicleID,
                                    iDriverID = $driverID,
                                    iCapacity = $vehicleCapacity
                                  WHERE iTripID = $tripID AND iGrpID = $iGrpID AND cStatus = 'A'";

                    if (sql_query($updateSql)) {
                        if (sql_affected_rows() > 0) {
                            $updatedCount++;
                        } else {
                            $errors[] = "Trip with ID $tripID not found or no changes made";
                        }
                    } else {
                        $errors[] = "Failed to update trip with ID $tripID";
                    }
                } else {
                    // INSERT new trip (tripID = 0)
                    // Get the trip details from existing trips in the same group for route and datetime
                    $groupInfoSql = "SELECT iRouteID, dtTrip FROM st_trips WHERE iGrpID = $iGrpID AND cStatus = 'A' LIMIT 1";
                    $groupInfoRes = sql_query($groupInfoSql);

                    if ($groupInfoRow = sql_fetch_assoc($groupInfoRes)) {
                        $routeID = intval($groupInfoRow['iRouteID']);
                        $tripDateTime = $groupInfoRow['dtTrip'];

                        // Get next trip ID
                        $newTripID = NextID('iTripID', 'st_trips');

                        $insertSql = "INSERT INTO st_trips (
                                        iTripID,
                                        iGrpID,
                                        iRouteID,
                                        dtTrip,
                                        iVehicleID,
                                        iDriverID,
                                        iCapacity,
                                        iRank,
                                        cStatus,
                                        dtCreated
                                      ) VALUES (
                                        $newTripID,
                                        $iGrpID,
                                        $routeID,
                                        '$tripDateTime',
                                        $vehicleID,
                                        $driverID,
                                        $vehicleCapacity,
                                        1,
                                        'A',
                                        NOW()
                                      )";

                        if (sql_query($insertSql)) {
                            $insertedCount++;
                        } else {
                            $errors[] = "Failed to insert new trip at index $index";
                        }
                    } else {
                        $errors[] = "Could not find group information for iGrpID $iGrpID";
                    }
                }
            }

            // Commit transaction if no critical errors
            sql_query("COMMIT");

            // Prepare response
            $response = [
                "data" => [
                    "message" => "Trip update completed",
                    "updatedCount" => $updatedCount,
                    "insertedCount" => $insertedCount,
                    "iGrpID" => $iGrpID
                ],
                "statusCode" => 200
            ];

            if (!empty($errors)) {
                $response["warnings"] = $errors;
            }

            echo json_encode($response);

        } catch (Exception $e) {
            // Rollback transaction on error
            sql_query("ROLLBACK");
            
            echo json_encode([
                "error" => [
                    "message" => "Transaction failed: " . $e->getMessage()
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE DELETE_TRIP =====================
    case 'DELETE_TRIP':
        $iTripID = intval($_REQUEST['iTripID'] ?? 0);

        // Validate required parameter
        if ($iTripID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Missing or invalid iTripID parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Check if trip exists and is active
        $checkSql = "SELECT iTripID, iGrpID FROM st_trips WHERE iTripID = $iTripID AND cStatus = 'A'";
        $checkRes = sql_query($checkSql);

        if (sql_num_rows($checkRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Trip not found or already deleted"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $tripRow = sql_fetch_assoc($checkRes);
        $iGrpID = $tripRow['iGrpID'];

        // Mark trip as deleted (status = 'X')
        $deleteSql = "UPDATE st_trips SET 
                        cStatus = 'X'
                      WHERE iTripID = $iTripID";

        if (sql_query($deleteSql)) {
            if (sql_affected_rows() > 0) {
                echo json_encode([
                    "data" => [
                        "message" => "Trip deleted successfully",
                        "iTripID" => $iTripID,
                        "iGrpID" => $iGrpID
                    ],
                    "statusCode" => 200
                ]);
            } else {
                echo json_encode([
                    "error" => [
                        "message" => "Failed to delete trip"
                    ],
                    "statusCode" => 500
                ]);
            }
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Database error occurred while deleting trip"
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