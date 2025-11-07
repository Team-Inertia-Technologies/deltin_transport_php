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

        $sql = "SELECT 
                    t.iTripID as id,
                    t.dtTrip,
                    t.iGrpID AS grpID,
                    r.vName as route,
                    r.vDestination as destination,
                    t.iCapacity,
                    t.iAvaialed as availed,
                    t.iRequested as pax,
                    t.iVehicleID
                FROM st_trips t
                LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                WHERE $whereClause
                ORDER BY t.dtTrip DESC";

        $res = sql_query($sql);
        $rowData = [];

        while ($row = sql_fetch_assoc($res)) {
            // Format date and time
            $dateTime = date('d/m/Y H:i', strtotime($row['dtTrip']));

            // Build vehicle details string
            $vehicleDetail = '';
            if (!empty($row['iVehicleID'])) {
                $vehicleIDs = explode(',', $row['iVehicleID']);
                $vehicleDetails = [];

                foreach ($vehicleIDs as $vehicleID) {
                    $vehicleID = trim($vehicleID);
                    if (!empty($vehicleID)) {
                        $vehicleSql = "SELECT v.vRnum, vc.iCapacity 
                                      FROM vehicle v
                                      LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                                      WHERE v.iVehicleID = $vehicleID AND v.cStatus = 'A'";
                        $vehicleRes = sql_query($vehicleSql);
                        if ($vehicleRow = sql_fetch_assoc($vehicleRes)) {
                            $capacity = $vehicleRow['iCapacity'] ?? 0;
                            $vehicleDetails[] = $vehicleRow['vRnum'] . ' (' . $capacity . ')';
                        }
                    }
                }
                $vehicleDetail = implode(' , ', $vehicleDetails);
            }

            $rowData[] = [
                "id" => (int) $row['id'],
                "grpID" => (int) $row['grpID'],
                "dateTime" => $dateTime,
                "route" => $row['route'] ?? '',
                "destination" => $row['destination'] ?? '',
                "vehicleDetail" => $vehicleDetail,
                "pax" => (int) ($row['pax'] ?? 0),
                "availed" => (int) ($row['availed'] ?? 0)
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
        
        $rdOpt  = [];
        
        while ($routeRow = sql_fetch_assoc($routesRes)) {
            $rdOpt [] = [
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
                LEFT JOIN vendor ven ON v.iVendorID = ven.iVendorID AND ven.cStatus = 'A'
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
            
            // Collect unique vendors and their vehicles
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
                
                // Add vehicle to this vendor
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
                "driver" => [
                    "driverID" => $driverID,
                    "driverName" => $row['driverName'] ?? '',
                    "driverMobile" => $row['driverMobile'] ?? ''
                ]
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
                                    st.vMobile as staffMobile
                                FROM st_request req
                                INNER JOIN staff st ON req.iStaffID = st.iStaffID AND st.cStatus = 'A'
                                INNER JOIN st_trips t ON req.iTripID = t.iTripID
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
                            "staffMobile" => $staffRow['staffMobile'] ?? ''
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

        // Convert maps to arrays
        $vendors = array_values($vendorsMap);
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

            // Calculate total capacity for this trip
            $totalCapacity = 0;
            foreach ($vehicles as $vehicle) {
                // Support multiple field name variations for capacity
                $capacity = intval($vehicle['vhCap'] ?? $vehicle['vhCaps'] ?? $vehicle['capacity'] ?? 0);
                $totalCapacity += $capacity;
            }

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

                    if ($vehID <= 0) {
                        $errors[] = "Invalid vehicle ID in trip $tripIndex, vehicle $vehicleIndex";
                        continue;
                    }

                    $insertValues[] = "($currentTripID, $groupID, $routeID, '$tripDateTime', $vehID, $driverID, $totalCapacity, 1, 'A')";
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