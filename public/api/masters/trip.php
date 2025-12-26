<?php
// ini_set('display_errors', 1);

include "../../includes/common_api.php";
//include "../api_common.php";
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
$NOW = NOW;
switch ($mode) {

    // ===================== CASE 1: LIST =====================
    case 'LIST':
        $fromDate = $_REQUEST['fromDate'] ?? '';
        $toDate = $_REQUEST['toDate'] ?? '';
        $routeID = intval($_REQUEST['routeID'] ?? 0);

        // Build WHERE conditions
        $whereConditions = ["t.cStatus != 'X'"];

        if (!empty($fromDate)) {
            $whereConditions[] = "DATE(t.dtTrip) >= '" . db_input($fromDate) . "'";
        }

        if (!empty($toDate)) {
            $whereConditions[] = "DATE(t.dtTrip) <= '" . db_input($toDate) . "'";
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
                    "date" => date('d/m/Y', strtotime($row['dtTrip'])),
                    "time" => date('g:i A', strtotime($row['dtTrip'])),
                    "route" => db_output2($row['route'] ?? ''),
                    "destination" => db_output2($row['destination'] ?? ''),
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
                // Note: totalPax will be calculated separately by counting actual staff requests
                $groupedTrips[$grpID]['totalAvailed'] += (int) ($row['availed'] ?? 0);
            }
        }

        // Calculate correct totalPax for each group by counting actual staff requests
        foreach ($groupedTrips as $grpID => $tripGroup) {
            $staffCountSql = "SELECT COUNT(DISTINCT req.iStaffID) as totalStaff
                             FROM st_request req
                             INNER JOIN st_trips t ON req.iTripID = t.iTripID
                             WHERE t.iGrpID = $grpID 
                             AND req.cStatus = 'A'
                             AND t.cStatus != 'X'";
            $staffCountRes = sql_query($staffCountSql);
            $staffCountRow = sql_fetch_assoc($staffCountRes);
            $groupedTrips[$grpID]['totalPax'] = (int) ($staffCountRow['totalStaff'] ?? 0);
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
                "date" => $tripGroup['date'],
                "time" => $tripGroup['time'],
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
                "name" => db_output2($routeRow['vName'])
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

        // Get mode options from vehicle category table
        $modeSql = "SELECT iVCatID, vName FROM vehicle_category WHERE cStatus = 'A' ORDER BY vName";
        $modeRes = sql_query($modeSql);

        $modeOpt = [
            ["id" => 0, "name" => "Choose"]
        ];

        while ($modeRow = sql_fetch_assoc($modeRes)) {
            $modeOpt[] = [
                "id" => (int) $modeRow['iVCatID'],
                "name" => db_output2($modeRow['vName'])
            ];
        }

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

        // Get routes for rdOpt 
        $routesSql = "SELECT iRouteID, vName, vDestination FROM st_route WHERE cStatus = 'A' ORDER BY iRank";
        $routesRes = sql_query($routesSql);

        $rdOpt = [];

        while ($routeRow = sql_fetch_assoc($routesRes)) {
            $rdOpt[] = [
                "id" => (int) $routeRow['iRouteID'],
                "route" => db_output2($routeRow['vName'] ?? ''),
                "dest" => db_output2($routeRow['vDestination'] ?? '')
            ];
        }

        // Get table array with vehicle details and vendor-specific drivers
        $tableArrSql = "SELECT v.iVehicleID, v.vRnum, vc.iCapacity, ven.vName as vOwner, ven.iVendorID
                       FROM vehicle v
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       LEFT JOIN vendor ven ON v.iVendorID = ven.iVendorID AND ven.cStatus = 'A' and ven.cType IN ('B','T') 
                       WHERE v.cStatus = 'A'
                       ORDER BY v.vRnum";
        $tableArrRes = sql_query($tableArrSql);

        $tableArr = [];

        while ($tableRow = sql_fetch_assoc($tableArrRes)) {
            $vendorID = (int) ($tableRow['iVendorID'] ?? 0);

            // Get drivers for this specific vendor only
            $vhDriver = [];
            if ($vendorID > 0) {
                $vendorDriversSql = "SELECT d.iDriverID, d.vName as drName, d.cStatus
                                   FROM driver d
                                   WHERE d.cStatus = 'A' AND d.iVendorID = $vendorID
                                   ORDER BY d.vName";
                $vendorDriversRes = sql_query($vendorDriversSql);

                while ($driverRow = sql_fetch_assoc($vendorDriversRes)) {
                    $vhDriver[] = [
                        "id" => (int) $driverRow['iDriverID'],
                        "drName" => db_output2($driverRow['drName']),
                        "active" => $driverRow['cStatus']
                    ];
                }
            }

            $tableArr[] = [
                "id" => (int) $tableRow['iVehicleID'],
                "vhNum" => db_output2($tableRow['vRnum'] ?? ''),
                "vhCap" => (int) ($tableRow['iCapacity'] ?? 0),
                "vhOwner" => db_output2($tableRow['vOwner'] ?? ''),
                "vhDriver" => $vhDriver  // Vendor-specific driver list for each vehicle
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
                    t.iRequested as requestedPax,
                    t.iAvaialed as availedPax,
                    t.cStatus as tripStatus,
                    r.iRank
                FROM st_trips t
                LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                LEFT JOIN vehicle v ON t.iVehicleID = v.iVehicleID
                LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                LEFT JOIN vendor ven ON v.iVendorID = ven.iVendorID AND ven.cStatus = 'A' AND ven.cType IN ('B','T')
                LEFT JOIN driver d ON t.iDriverID = d.iDriverID
                WHERE t.iGrpID = $iGrpID AND t.cStatus != 'X'
                ORDER BY t.iTripID, r.iRank";

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
        $totalCapacity = 0;
        $totalRequestedPax = 0;
        $totalAvailedPax = 0;

        while ($row = sql_fetch_assoc($res)) {
            // Set route info (same for all trips in group)
            if (empty($routeInfo)) {
                $routeInfo = [
                    "routeName" => $row['routeName'] ?? '',
                    "destination" => $row['destination'] ?? '',
                    "tripDateTime" => date('d/m/Y H:i', strtotime($row['dtTrip']))
                ];
            }

            // Calculate totals by summing vehicle capacities and passenger counts
            $totalCapacity += (int) ($row['vehicleCapacity'] ?? 0);
            // Note: totalRequestedPax will be calculated separately by counting actual staff requests
            $totalAvailedPax += (int) ($row['availedPax'] ?? 0);

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
            $tripStatusText = "";
            $tripStatus = $row['tripStatus'];
            if ($tripStatus != ' A' || $tripStatus != ' D') {
                $tripStatusText = isset($STAFF_TRIP_STATUS[$tripStatus]) ? $STAFF_TRIP_STATUS[$tripStatus] : "";

            }
            // Get driver ID from current row
            $driverID = (int) ($row['iDriverID'] ?? 0);

            // Get all drivers for this specific vehicle's vendor
            $vhDriver = [];
            if ($vendorID > 0) {
                $vendorDriversSql = "SELECT d.iDriverID, d.vName as drName, d.cStatus
                                   FROM driver d
                                   WHERE d.cStatus = 'A' AND d.iVendorID = $vendorID
                                   ORDER BY d.vName";
                $vendorDriversRes = sql_query($vendorDriversSql);

                while ($driverRow = sql_fetch_assoc($vendorDriversRes)) {
                    $vhDriver[] = [
                        "id" => (int) $driverRow['iDriverID'],
                        "drName" => db_output2($driverRow['drName']),
                        "active" => $driverRow['cStatus']
                    ];
                }
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
                "driverMobile" => $row['driverMobile'] ?? '',
                "requestedPax" => (int) ($row['requestedPax'] ?? 0),
                "availedPax" => (int) ($row['availedPax'] ?? 0),
                "tripStatus" => $tripStatus,
                "tripStatusText" => $tripStatusText,
                "vhDriver" => $vhDriver  // Array of all drivers for this vehicle's vendor
            ];
        }

        // Calculate correct totalRequestedPax by counting actual staff requests
        $staffCountSql = "SELECT COUNT(DISTINCT req.iStaffID) as totalStaff
                         FROM st_request req
                         INNER JOIN st_trips t ON req.iTripID = t.iTripID
                         WHERE t.iGrpID = $iGrpID 
                         AND req.cStatus = 'A'
                         AND t.cStatus != 'X'";
        $staffCountRes = sql_query($staffCountSql);
        $staffCountRow = sql_fetch_assoc($staffCountRes);
        $totalRequestedPax = (int) ($staffCountRow['totalStaff'] ?? 0);

        // Add totals to route info
        $routeInfo["totalCapacity"] = $totalCapacity;
        $routeInfo["totalRequestedPax"] = $totalRequestedPax;
        $routeInfo["totalAvailedPax"] = $totalAvailedPax;

        // Get stops information for this route and trip group
        $stops = [];
        if (!empty($routeInfo)) {
            // Get the route ID from the first trip
            $routeIDSql = "SELECT iRouteID FROM st_trips WHERE iGrpID = $iGrpID AND cStatus != 'X' LIMIT 1";
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
                    $tripTimeSql = "SELECT dtTrip FROM st_trips WHERE iGrpID = $iGrpID AND cStatus != 'X' LIMIT 1";
                    $tripTimeRes = sql_query($tripTimeSql);
                    $tripTimeRow = sql_fetch_assoc($tripTimeRes);

                    $tripStartTime = date('H:i:s', strtotime($tripTimeRow['dtTrip']));
                    $pickupTime = date('H:i', strtotime($tripStartTime) + ($offsetMinutes * 60));

                    // Get staff who will board at this stop for any trip in this group
                    $staffSql = "SELECT DISTINCT
                                    st.iStaffID,
                                    st.vName as staffName,
                                    st.vMobile as staffMobile,
                                    req.iTripID,
                                    v.vRnum as vehicleNumber,
                                    req.dtIn
                                FROM st_request req
                                INNER JOIN staff st ON req.iStaffID = st.iStaffID AND st.cStatus = 'A'
                                INNER JOIN st_trips t ON req.iTripID = t.iTripID
                                LEFT JOIN vehicle v ON t.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
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
                            "tripID" => (int) $staffRow['iTripID'],
                            "vehicleNumber" => $staffRow['vehicleNumber'] ?? '',
                            "entered" => !empty($staffRow['dtIn']),
                            "enteredTime" => $staffRow['dtIn'] ? date('H:i', strtotime($staffRow['dtIn'])) : null
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

        // First, get the trip datetime for this group to check for conflicts
        $tripDateTimeSql = "SELECT dtTrip FROM st_trips WHERE iGrpID = $iGrpID AND cStatus != 'X' LIMIT 1";
        $tripDateTimeRes = sql_query($tripDateTimeSql);
        $tripDateTimeRow = sql_fetch_assoc($tripDateTimeRes);
        $currentTripDateTime = $tripDateTimeRow['dtTrip'] ?? '';

        // Get vehicles that are already assigned to other trips at the same time
        $conflictingVehiclesSql = "SELECT DISTINCT t.iVehicleID
                                  FROM st_trips t
                                  WHERE t.dtTrip = '$currentTripDateTime'
                                  AND t.iGrpID != $iGrpID
                                  AND t.cStatus != 'X'
                                  AND t.iVehicleID > 0";
        $conflictingVehiclesRes = sql_query($conflictingVehiclesSql);

        $conflictingVehicleIDs = [];
        while ($conflictRow = sql_fetch_assoc($conflictingVehiclesRes)) {
            $conflictingVehicleIDs[] = (int) $conflictRow['iVehicleID'];
        }

        // Get vehicle options (excluding vehicles already assigned at the same time)
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
            $vehicleID = (int) $vehicleRow['iVehicleID'];

            // Skip vehicles that are already assigned to other trips at the same time
            if (in_array($vehicleID, $conflictingVehicleIDs)) {
                continue;
            }

            $capacity = $vehicleRow['iCapacity'] ?? 0;
            $vehiOpt[] = [
                "id" => $vehicleID,
                "name" => $vehicleRow['vRnum'] . ' (' . $capacity . ')'
            ];
        }

        // Get mode options from vehicle category table (same as ADD_ONLOAD)
        $modeSql = "SELECT iVCatID, vName FROM vehicle_category WHERE cStatus = 'A' ORDER BY vName";
        $modeRes = sql_query($modeSql);

        $modeOpt = [
            ["id" => 0, "name" => "Choose"]
        ];

        while ($modeRow = sql_fetch_assoc($modeRes)) {
            $modeOpt[] = [
                "id" => (int) $modeRow['iVCatID'],
                "name" => db_output2($modeRow['vName'])
            ];
        }

        // Get vendor options (same as ADD_ONLOAD)
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

        // Get table array with vehicle details and vendor-specific drivers (same as ADD_ONLOAD)
        $tableArrSql = "SELECT v.iVehicleID, v.vRnum, vc.iCapacity, ven.vName as vOwner, ven.iVendorID
                       FROM vehicle v
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       LEFT JOIN vendor ven ON v.iVendorID = ven.iVendorID AND ven.cStatus = 'A' and ven.cType IN ('B','T') 
                       WHERE v.cStatus = 'A'
                       ORDER BY v.vRnum";
        $tableArrRes = sql_query($tableArrSql);

        $tableArr = [];

        while ($tableRow = sql_fetch_assoc($tableArrRes)) {
            $vehicleID = (int) $tableRow['iVehicleID'];

            // Skip vehicles that are already assigned to other trips at the same time
            if (in_array($vehicleID, $conflictingVehicleIDs)) {
                continue;
            }

            $vendorID = (int) ($tableRow['iVendorID'] ?? 0);

            // Get drivers for this specific vendor only
            $vhDriver = [];
            if ($vendorID > 0) {
                $vendorDriversSql = "SELECT d.iDriverID, d.vName as drName, d.cStatus
                                   FROM driver d
                                   WHERE d.cStatus = 'A' AND d.iVendorID = $vendorID
                                   ORDER BY d.vName";
                $vendorDriversRes = sql_query($vendorDriversSql);

                while ($driverRow = sql_fetch_assoc($vendorDriversRes)) {
                    $vhDriver[] = [
                        "id" => (int) $driverRow['iDriverID'],
                        "drName" => db_output2($driverRow['drName']),
                        "active" => $driverRow['cStatus']
                    ];
                }
            }

            $tableArr[] = [
                "id" => $vehicleID,
                "vhNum" => db_output2($tableRow['vRnum'] ?? ''),
                "vhCap" => (int) ($tableRow['iCapacity'] ?? 0),
                "vhOwner" => db_output2($tableRow['vOwner'] ?? ''),
                "vhDriver" => $vhDriver  // Vendor-specific driver list for each vehicle
            ];
        }
        $CANCELATION_STATUS = array("NS" => "No Show", "XP" => "Cancel with payment", "XN" => "Cancel without payment", "X" => "Remove");
        $cancelOpt = [['id' => 0, 'name' => 'Choose']];
        foreach ($CANCELATION_STATUS as $id => $name) {
            $cancelOpt[] = ['id' => $id, 'name' => $name];
        }
        echo json_encode([
            "data" => [
                "iGrpID" => $iGrpID,
                "routeInfo" => $routeInfo,
                "trip_details" => $vehicles,
                "tripCount" => count($vehicles),
                "tableArr" => $tableArr,
                "vehiOpt" => $vehiOpt,
                "modeOpt" => $modeOpt,
                "vendorOpt" => $vendorOpt,
                "stops" => $stops,
                "cancelOpt" => $cancelOpt
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE TRIP_MANIFEST =====================
    case 'TRIP_MANIFEST':
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

        // Single optimized query to get all trip, route, vehicle, stops and staff information
        $manifestSql = "SELECT 
                            -- Trip and Route Info
                            t.dtTrip,
                            r.iRouteID,
                            r.vName as routeName,
                            r.vDestination as destination,
                            
                            -- Vehicle Info
                            t.iVehicleID,
                            v.vRnum as vehicleNumber,
                            vc.iCapacity as vehicleCapacity,
                            
                            -- Stop Info
                            s.iStopID,
                            s.vName as stopName,
                            s.tOffsetFromStart,
                            s.iRank as stopRank,
                            
                            -- Staff Info
                            req.iStaffID,
                            st.vName as staffName,
                            st.vMobile as staffMobile,
                            req.dtIn,
                            
                            -- Trip requested pax
                            t.iRequested,
                            
                            -- Sum total requested pax for this group
                            (SELECT SUM(t2.iRequested) 
                             FROM st_trips t2 
                             WHERE t2.iGrpID = $iGrpID 
                             AND t2.cStatus != 'X') as totalPaxRequested
                             
                        FROM st_trips t
                        LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                        LEFT JOIN vehicle v ON t.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
                        LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                        LEFT JOIN st_route_stops s ON r.iRouteID = s.iRouteID AND s.cStatus = 'A'
                        LEFT JOIN st_request req ON t.iTripID = req.iTripID AND req.iStopID = s.iStopID AND req.cStatus = 'A'
                        LEFT JOIN staff st ON req.iStaffID = st.iStaffID AND st.cStatus = 'A'
                        
                        WHERE t.iGrpID = $iGrpID AND t.cStatus != 'X'
                        ORDER BY s.iRank, st.vName, t.iTripID";

        $manifestRes = sql_query($manifestSql);

        // Initialize variables
        $tripDateTime = '';
        $routeName = '';
        $destination = '';
        $vehicleNumbers = [];
        $totalCapacity = 0;
        $totalPaxRequested = 0;
        $stops = [];
        $processedVehicles = [];
        $processedStops = [];

        while ($row = sql_fetch_assoc($manifestRes)) {
            // Set trip info once (same for all rows)
            if (empty($tripDateTime)) {
                $tripDateTime = date('d/m/Y H:i', strtotime($row['dtTrip']));
                $routeName = $row['routeName'] ?? '';
                $destination = $row['destination'] ?? '';
                $totalPaxRequested = (int) ($row['totalPaxRequested'] ?? 0);
            }

            // Process vehicles (avoid duplicates)
            $vehicleID = (int) ($row['iVehicleID'] ?? 0);
            if ($vehicleID > 0 && !in_array($vehicleID, $processedVehicles)) {
                $vehicleCapacity = (int) ($row['vehicleCapacity'] ?? 0);
                $vehicleNumbers[] = ($row['vehicleNumber'] ?? '') . ' (' . $vehicleCapacity . ')';
                $totalCapacity += $vehicleCapacity;
                $processedVehicles[] = $vehicleID;
            }

            // Process stops and staff
            $stopID = (int) ($row['iStopID'] ?? 0);
            if ($stopID > 0) {
                // Initialize stop if not processed
                if (!isset($processedStops[$stopID])) {
                    $offsetMinutes = intval($row['tOffsetFromStart']);
                    $tripStartTime = date('H:i:s', strtotime($row['dtTrip']));
                    $pickupTime = date('H:i', strtotime($tripStartTime) + ($offsetMinutes * 60));

                    $processedStops[$stopID] = [
                        "stopID" => $stopID,
                        "stopName" => $row['stopName'] ?? '',
                        "pickupTime" => $pickupTime,
                        "offsetMinutes" => $offsetMinutes,
                        "rank" => (int) ($row['stopRank'] ?? 0),
                        "staff" => [],
                        "staffCount" => 0
                    ];
                }

                // Add staff to stop if exists and not already added
                $staffID = (int) ($row['iStaffID'] ?? 0);
                if ($staffID > 0) {
                    $staffExists = false;
                    foreach ($processedStops[$stopID]['staff'] as $existingStaff) {
                        if ($existingStaff['staffID'] == $staffID) {
                            $staffExists = true;
                            break;
                        }
                    }

                    if (!$staffExists) {
                        $processedStops[$stopID]['staff'][] = [
                            "staffID" => $staffID,
                            "staffName" => $row['staffName'] ?? '',
                            "staffMobile" => $row['staffMobile'] ?? '',
                            "vehicleNumber" => $row['vehicleNumber'] ?? '',
                            "entered" => !empty($row['dtIn']),
                            "enteredTime" => $row['dtIn'] ? date('H:i', strtotime($row['dtIn'])) : null
                        ];
                        $processedStops[$stopID]['staffCount']++;
                    }
                }
            }
        }

        // Convert processed stops to indexed array and sort by rank
        $stops = array_values($processedStops);
        usort($stops, function ($a, $b) {
            return $a['rank'] - $b['rank'];
        });

        echo json_encode([
            "data" => [
                "iGrpID" => $iGrpID,
                "tripDateTime" => $tripDateTime,
                "routeName" => $routeName,
                "destination" => $destination,
                "totalPaxRequested" => $totalPaxRequested,
                "totalCapacity" => $totalCapacity,
                "vehicleNumbers" => $vehicleNumbers,
                "vehicleCount" => count($vehicleNumbers),
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
        if (checkUserModuleAccess($user_id, 'STAFF_TRIP_APPROVE')) {
            $cStatus = 'A'; // approved
        } else {
            $cStatus = 'D'; // draft
        }
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

            if (empty($time)) {
                echo json_encode([
                    "error" => [
                        "message" => "Time is required for trip at index $tripIndex. Please provide a valid time."
                    ],
                    "statusCode" => 400
                ]);
                exit;
            }

            // Clean up time format - remove extra spaces and ensure proper format
            $cleanTime = trim(str_replace(' ', '', $time));
            // Ensure time is in HH:MM:SS format
            if (strlen($cleanTime) == 5 && substr_count($cleanTime, ':') == 1) {
                $cleanTime .= ':00'; // Add seconds if missing
            }

            // Validate time format
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $cleanTime)) {
                echo json_encode([
                    "error" => [
                        "message" => "Invalid time format for trip at index $tripIndex. Please use HH:MM or HH:MM:SS format."
                    ],
                    "statusCode" => 400
                ]);
                exit;
            }

            // Process each date in the range
            foreach ($dateRange as $date) {
                $tripDateTime = $date . ' ' . $cleanTime;

                // Check if trip already exists for this route, date and time
                $duplicateCheck = checkDuplicateTrip($routeID, $tripDateTime);
                if ($duplicateCheck['duplicate_exists']) {
                    $errors[] = "Skipped creating trip for " . $tripDateTime . " - " . $duplicateCheck['message'];
                    continue; // Skip creating this trip and move to next date
                }

                // Check if we already have a group ID for this date/time combination
                if (!isset($dateTimeGroupMap[$tripDateTime])) {
                    $dateTimeGroupMap[$tripDateTime] = $currentGrpID;
                    $currentGrpID++; // Increment for next unique date/time combination
                }

                $groupID = $dateTimeGroupMap[$tripDateTime];

                // If no vehicles provided, create a trip entry with default vehicle ID (0)
                if (empty($vehicles)) {
                    // Create trip entry with default vehicle details - can be updated later
                    $insertValues[] = "($currentTripID, $groupID, $routeID, '" . db_input($tripDateTime) . "', 0, 0, 0, $user_id, 1, '$NOW', '$cStatus')";
                    $currentTripID++; // Increment for next record
                } else {
                    // Process each vehicle for this trip and date
                    foreach ($vehicles as $vehicleIndex => $vehicle) {
                        // Support multiple field name variations for vehicle ID
                        $vehID = intval($vehicle['vhId'] ?? $vehicle['vehID'] ?? 0);
                        // Support multiple field name variations for driver ID
                        $driverID = intval($vehicle['driverId'] ?? $vehicle['driverID'] ?? 0);
                        // Get individual vehicle capacity
                        $vehicleCapacity = intval($vehicle['vhCap'] ?? $vehicle['vhCaps'] ?? $vehicle['capacity'] ?? 0);

                        // Only validate vehicle ID if it's provided (not 0)
                        if ($vehID > 0) {
                            $insertValues[] = "($currentTripID, $groupID, $routeID, '" . db_input($tripDateTime) . "', $vehID, $driverID, $vehicleCapacity,$user_id, 1, '$NOW', '$cStatus')";
                            $currentTripID++; // Increment for next record
                        } else {
                            // Skip invalid vehicle entries but don't fail the entire operation
                            $errors[] = "Skipped invalid vehicle ID in trip $tripIndex, vehicle $vehicleIndex";
                        }
                    }
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
                iTripAddedBy,
                iRank, 
                dtAdded,
                cStatus
            ) VALUES " . implode(', ', $insertValues);

            if (sql_query($insertSql)) {
                $insertedCount = count($insertValues);
            } else {
                $sqlError = '';
                $errors[] = "Failed to insert trips. SQL Error: " . $sqlError;
                // Also log the problematic query for debugging
                error_log("Failed SQL Query: " . $insertSql);
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
                                   AND cStatus != 'X'";
            $existingVehiclesRes = sql_query($existingVehiclesSql);

            $conflictingVehicles = [];
            $existingTripVehicles = [];

            while ($row = sql_fetch_assoc($existingVehiclesRes)) {
                $existingTripVehicles[intval($row['iTripID'])] = intval($row['iVehicleID']);
            }

            // Check for conflicts (vehicle assigned to different trip in same group)
            foreach ($trip_details as $index => $trip) {
                $tripID = intval($trip['tripID'] ?? 0);
                $vehicleID = intval($trip['vhId'] ?? 0);

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
                $vehicleID = intval($trip['vhId'] ?? 0);
                $vehicleCapacity = intval($trip['vhCap'] ?? 0);
                //$vehicleOwnerID = intval($trip['vehicleOwnerID'] ?? 0);
                $driverID = intval($trip['driverId'] ?? 0);

                if ($tripID > 0) {
                    // UPDATE existing trip - use 0 as default for unassigned vehicles
                    $updateSql = "UPDATE st_trips SET 
                                    iVehicleID = " . ($vehicleID > 0 ? $vehicleID : "0") . ",
                                    iDriverID = " . ($driverID > 0 ? $driverID : "0") . ",
                                    iCapacity = $vehicleCapacity,iVehAssignedBy=$user_id
                                  WHERE iTripID = $tripID AND iGrpID = $iGrpID AND cStatus != 'X'";

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
                    $groupInfoSql = "SELECT iRouteID, dtTrip FROM st_trips WHERE iGrpID = $iGrpID AND cStatus != 'X' LIMIT 1";
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
                                        iTripAddedBy,
                                        iRank,
                                        dtAdded,
                                        cStatus
                                      ) VALUES (
                                        $newTripID,
                                        $iGrpID,
                                        $routeID,
                                        '" . db_input($tripDateTime) . "',
                                        " . ($vehicleID > 0 ? $vehicleID : "0") . ",
                                        " . ($driverID > 0 ? $driverID : "0") . ",
                                        $vehicleCapacity,
                                        $user_id,
                                        1,
                                        '$NOW',
                                        'A'
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
        $checkSql = "SELECT iTripID, iGrpID FROM st_trips WHERE iTripID = $iTripID AND cStatus != 'X'";
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

    // ===================== CASE MARK_TRIP_AS_COMPLETE =====================
    case 'MARK_TRIP_AS_COMPLETE':
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
        $checkSql = "SELECT iTripID, iGrpID FROM st_trips WHERE iTripID = $iTripID AND cStatus != 'X'";
        $checkRes = sql_query($checkSql);

        if (sql_num_rows($checkRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Trip not found or not active"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $tripRow = sql_fetch_assoc($checkRes);
        $iGrpID = $tripRow['iGrpID'];

        // Mark trip as complete (status = 'C')
        $completeSql = "UPDATE st_trips SET 
                          cStatus = 'C',iStatusChangedBy=$user_id
                        WHERE iTripID = $iTripID";

        if (sql_query($completeSql)) {
            if (sql_affected_rows() > 0) {
                echo json_encode([
                    "data" => [
                        "message" => "Trip marked as complete successfully",
                        "iTripID" => $iTripID,
                        "iGrpID" => intval($iGrpID)
                    ],
                    "statusCode" => 200
                ]);
            } else {
                echo json_encode([
                    "error" => [
                        "message" => "Failed to mark trip as complete"
                    ],
                    "statusCode" => 500
                ]);
            }
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Database error occurred while marking trip as complete"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE CHANGE_TRIP_STATUS =====================
    case 'CHANGE_TRIP_STATUS':
        $iTripID = intval($_REQUEST['iTripID'] ?? 0);
        $status = $_REQUEST['status'] ?? '';
        $reason = $_REQUEST['reason'] ?? '';

        // Validate required parameters
        if ($iTripID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Missing or invalid iTripID parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if (empty($status)) {
            echo json_encode([
                "error" => [
                    "message" => "Status parameter is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate status length (max 255 characters)
        if (strlen($status) > 255) {
            echo json_encode([
                "error" => [
                    "message" => "Status must not exceed 255 characters"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate reason length (max 255 characters)
        if (strlen($reason) > 255) {
            echo json_encode([
                "error" => [
                    "message" => "Reason must not exceed 255 characters"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate status against allowed values from STAFF_TRIP_STATUS
        $validStatuses = array_keys($STAFF_TRIP_STATUS);
        if (!in_array($status, $validStatuses)) {
            echo json_encode([
                "error" => [
                    "message" => "Invalid status. Allowed values: " . implode(', ', $validStatuses)
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Check if trip exists and is not already deleted
        $checkSql = "SELECT iTripID, iGrpID, cStatus FROM st_trips WHERE iTripID = $iTripID";
        $checkRes = sql_query($checkSql);

        if (sql_num_rows($checkRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Trip not found"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $tripRow = sql_fetch_assoc($checkRes);
        $iGrpID = $tripRow['iGrpID'];
        $currentStatus = $tripRow['cStatus'];

        // Update trip status with reason
        $updateSql = "UPDATE st_trips SET 
                        cStatus = '" . db_input($status) . "',
                        vCancellationReason = '" . db_input($reason) . "',
                        iStatusChangedBy = $user_id
                      WHERE iTripID = $iTripID";

        if (sql_query($updateSql)) {
            if (sql_affected_rows() > 0) {
                $statusText = isset($STAFF_TRIP_STATUS[$status]) ? $STAFF_TRIP_STATUS[$status] : $status;

                echo json_encode([
                    "data" => [
                        "message" => "Trip status changed successfully",
                        "iTripID" => $iTripID,
                        "iGrpID" => intval($iGrpID),
                        "oldStatus" => $currentStatus,
                        "newStatus" => $status,
                        "statusText" => $statusText,
                        "reason" => $reason
                    ],
                    "statusCode" => 200
                ]);
            } else {
                echo json_encode([
                    "error" => [
                        "message" => "No changes made to trip status"
                    ],
                    "statusCode" => 400
                ]);
            }
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Database error occurred while updating trip status"
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
