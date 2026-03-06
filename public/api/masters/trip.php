<?php
// ini_set('display_errors', 1);

include "../../includes/common_api.php";
include "../api_common.php";
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
        $driverID =  isset($_REQUEST['driverID']) ? intval($_REQUEST['driverID'] ?? 0) : 0;
        $vendorID = isset($_REQUEST['vendorID']) ? intval($_REQUEST['vendorID'] ?? 0) : 0;

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
        if ($driverID > 0) {
            $whereConditions[] = "tva.iDriverID = $driverID";
        }
        if ($vendorID > 0) {
            $whereConditions[] = "v.iVendorID = $vendorID";
        }

        $whereClause = implode(' AND ', $whereConditions);


        $sql = "SELECT 
                    t.iTripID as id,
                    t.dtTrip,
                    r.vName as route,
                    r.vDestination as destination,
                    t.iCapacity,
                    t.iAvaialed as availed,
                    t.iRequested as pax,
                    tva.iVehicleID,
                    v.vRnum as vehicleNumber,
                    vc.iCapacity as vehicleCapacity,
                    tva.iDriverID,
                    d.vName as driverName,
                    vn.iVendorID as vendorID,
                    vn.vName as vendorName
                FROM st_trips t
                LEFT OUTER JOIN st_trip_vehicle_assoc tva ON t.iTripID = tva.iTripID
                LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                LEFT JOIN vehicle v ON tva.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
                LEFT JOIN vendor vn ON vn.iVendorID  = v.iVendorID
                LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                LEFT JOIN driver d ON tva.iDriverID = d.iDriverID AND d.cStatus = 'A'
                WHERE $whereClause
                ORDER BY t.dtTrip DESC, t.iTripID";

        $res = sql_query($sql);
        $trips = [];

        // Process trips without grouping
        while ($row = sql_fetch_assoc($res)) {
            $tripID = (int) $row['id'];

            // Create individual trip entry
            $trip = [
                "tripID" => $tripID,
                "date" => date('d/m/Y', strtotime($row['dtTrip'])),
                "time" => date('g:i A', strtotime($row['dtTrip'])),
                "route" => db_output2($row['route'] ?? ''),
                "destination" => db_output2($row['destination'] ?? ''),
                "capacity" => (int) ($row['vehicleCapacity'] ?? 0),
                "pax" => 0,
                "availed" => (int) ($row['availed'] ?? 0),
                "vehicleID" => (int) ($row['iVehicleID'] ?? 0),
                "vehicleNumber" => $row['vehicleNumber'] ?? '',
                "driverID" => (int) ($row['iDriverID'] ?? 0),
                "driverName" => db_output2($row['driverName']) ?? '',
                "vendorName" => db_output2($row['vendorName']) ?? '',
                "vendorID" => (int) ($row['vendorID'] ?? 0)
            ];

            $trips[$tripID] = $trip;
        }

        // Calculate correct pax count for each trip by counting actual staff requests
        foreach ($trips as $tripID => $trip) {
            $staffCountSql = "SELECT COUNT(DISTINCT req.iStaffID) as totalStaff
                             FROM st_request req
                             WHERE req.iTripID = $tripID 
                             AND req.cStatus = 'A'";
            $staffCountRes = sql_query($staffCountSql);
            $staffCountRow = sql_fetch_assoc($staffCountRes);
            $trips[$tripID]['pax'] = (int) ($staffCountRow['totalStaff'] ?? 0);
        }

        // Convert to final row data format
        $rowData = [];
        foreach ($trips as $trip) {
            $vehicleDetails = '';
            if (!empty($trip['vehicleNumber'])) {
                $vehicleDetails = $trip['vehicleNumber'] . ' (' . $trip['capacity'] . ')';
            }

            $rowData[] = [
                "tripID" => $trip['tripID'],
                "date" => $trip['date'],
                "time" => $trip['time'],
                "route" => $trip['route'],
                "destination" => $trip['destination'],
                "vehicleDetails" => $vehicleDetails,
                "capacity" => $trip['capacity'],
                "pax" => $trip['pax'],
                "availed" => $trip['availed'],
                "hasVehicle" => !empty($trip['vehicleNumber']),
                "driverID" =>  $trip['driverID'],
                "driverName" => $trip['driverName'],
                "vendorName" => $trip['vendorName'],
                "vendorID" => $trip['vendorID'],
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
        $driverSQL = "SELECT iDriverID, vName FROM driver WHERE cStatus = 'A' ORDER BY vName";
        $driverRes = sql_query($driverSQL);

        $driverOpt = [
            ["id" => 0, "name" => "All"]
        ];

        while ($driverRow = sql_fetch_assoc($driverRes)) {
            $driverOpt[] = [
                "id" => (int) $driverRow['iDriverID'],
                "name" => db_output2($driverRow['vName'])
            ];
        }

        $vendorSQL = "SELECT iVendorID, vName FROM vendor WHERE cStatus = 'A' ORDER BY vName";
        $vendorRes = sql_query($vendorSQL);

        $vendorOpt = [
            ["id" => 0, "name" => "All"]
        ];

        while ($vendorRow = sql_fetch_assoc($vendorRes)) {
            $vendorOpt[] = [
                "id" => (int) $vendorRow['iVendorID'],
                "name" => db_output2($vendorRow['vName'])
            ];
        }

        echo json_encode([
            "data" => [
                "rowData" => $rowData,
                "routesOpt" => $routesOpt,
                "fromDate" => $fromDate,
                "toDate" => $toDate,
                "routeID" => $routeID,
                "driverOpt" => $driverOpt,
                "vendorOpt" => $vendorOpt
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
              AND v.cServiceType IN ('S','B')
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
                     WHERE v.cStatus = 'A' AND ven.cStatus = 'A' AND ven.cType IN ('B','S')
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
        $tableArrSql = "SELECT v.iVehicleID, v.vRnum, vc.iCapacity, 
                       ven.vName as vOwner, ven.iVendorID, v.iType
               FROM vehicle v
               LEFT JOIN vehicle_category vc 
                    ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
               LEFT JOIN vendor ven 
                    ON v.iVendorID = ven.iVendorID 
                    AND ven.cStatus = 'A' 
                    AND ven.cType IN ('B','S') 
               WHERE v.cStatus = 'A'
               AND v.cServiceType IN ('S','B')
               ORDER BY v.vRnum";
        $tableArrRes = sql_query($tableArrSql);

        $tableArr = [];

        while ($tableRow = sql_fetch_assoc($tableArrRes)) {
            $vendorID = (int) ($tableRow['iVendorID'] ?? 0);
            $vehicleType = (int) ($tableRow['iType'] ?? 0);

            $vhDriver = [];

            if ($vendorID > 0) {

                // Normal vendor drivers
                $vendorDriversSql = "SELECT d.iDriverID, d.vName as drName, d.cStatus
                         FROM driver d
                         WHERE d.cStatus = 'A'
                         AND d.iVendorID = $vendorID
                         ORDER BY d.vName";
            } elseif ($vendorID == 0 && $vehicleType == 3) {

                // Company vehicle type 3 → get drivers of type 3
                $vendorDriversSql = "SELECT d.iDriverID, d.vName as drName, d.cStatus
                         FROM driver d
                         WHERE d.cStatus = 'A'
                         AND d.iType = 3
                         ORDER BY d.vName";
            } else {
                $vendorDriversSql = "";
            }

            if (!empty($vendorDriversSql)) {
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
        $iTripID = intval($_REQUEST['iTripID'] ?? 0);

        if ($iTripID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Missing or invalid iTripID parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Get trip details with route, vehicle, and driver information using association table
        $sql = "SELECT 
                    t.iTripID,
                    t.dtTrip,
                    r.vName as routeName,
                    r.vDestination as destination,
                    tva.iVehicleID,
                    v.vRnum as vehicleNumber,
                    vc.iCapacity as vehicleCapacity,
                    ven.iVendorID,
                    ven.vName as vehicleOwner,
                    ven.vContactNum as vendorMobile,
                    tva.iDriverID,
                    d.vName as driverName,
                    d.vMobileNum as driverMobile,
                    t.iRequested as requestedPax,
                    t.iAvaialed as availedPax,
                    tva.cStatus as tripStatus,
                    r.iRank,
                    tva.vCancellationReason,
                    tva.cStatus as vehicleAssignStatus,
                    tva.iTVAID 
                FROM st_trips t
                LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                LEFT OUTER JOIN st_trip_vehicle_assoc tva ON t.iTripID = tva.iTripID
                LEFT JOIN vehicle v ON tva.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
                LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                LEFT JOIN vendor ven ON v.iVendorID = ven.iVendorID AND ven.cStatus = 'A' AND ven.cType IN ('B','S')
                LEFT JOIN driver d ON tva.iDriverID = d.iDriverID AND d.cStatus = 'A'
                WHERE t.iTripID = $iTripID AND t.cStatus != 'X'
                ORDER BY tva.iTVAID";

        $res = sql_query($sql);

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "No trip found for the specified trip ID"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $routeInfo = [];
        $vehicles = [];
        $totalCapacity = 0;
        $totalRequestedPax = 0;
        $totalAvailedPax = 0;
        $tripDateTime = '';

        while ($row = sql_fetch_assoc($res)) {

            if (empty($routeInfo)) {
                $routeInfo = [
                    "routeName" => $row['routeName'] ?? '',
                    "destination" => $row['destination'] ?? '',
                    "tripDateTime" => date('d/m/Y H:i', strtotime($row['dtTrip']))
                ];
                $tripDateTime = $row['dtTrip'];
                $totalRequestedPax = (int) ($row['requestedPax'] ?? 0);
                $totalAvailedPax = (int) ($row['availedPax'] ?? 0);
            }


            $vehicleID = (int) ($row['iVehicleID'] ?? 0);
            // if ($vehicleID > 0 && $row['vehicleAssignStatus'] == 'A') {
            if ($vehicleID > 0) {
                $vendorID = (int) ($row['iVendorID'] ?? 0);
                $driverID = (int) ($row['iDriverID'] ?? 0);

                // Calculate capacity
                $vehicleCapacity = (int) ($row['vehicleCapacity'] ?? 0);
                $totalCapacity += $vehicleCapacity;

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

                $tripStatusText = "";
                $tripStatus = $row['tripStatus'];
                if ($tripStatus != 'A' && $tripStatus != 'D') {
                    $tripStatusText = isset($STAFF_TRIP_STATUS[$tripStatus]) ? $STAFF_TRIP_STATUS[$tripStatus] : "";
                }

                // Add vehicle details
                $vehicles[] = [
                    "vehicleID" => $vehicleID,
                    "vehicleNumber" => $row['vehicleNumber'] ?? '',
                    "vehicleCapacity" => $vehicleCapacity,
                    "vehicleOwner" => $row['vehicleOwner'] ?? '',
                    "vehicleOwnerID" => $vendorID,
                    "driverID" => $driverID,
                    "driverName" => $row['driverName'] ?? '',
                    "driverMobile" => $row['driverMobile'] ?? '',
                    "tripStatus" => $tripStatus,
                    "tripStatusText" => $tripStatusText,
                    "vhDriver" => $vhDriver,
                    "cancellationReason" => $row['vCancellationReason'] ?? '',
                    "iTVAID" => $row['iTVAID'] ?? ''
                ];
            }
        }

        // Calculate correct totalRequestedPax by counting actual staff requests
        $staffCountSql = "SELECT COUNT(DISTINCT req.iStaffID) as totalStaff
                         FROM st_request req
                         WHERE req.iTripID = $iTripID 
                         AND req.cStatus = 'A'";
        $staffCountRes = sql_query($staffCountSql);
        $staffCountRow = sql_fetch_assoc($staffCountRes);
        $totalRequestedPax = (int) ($staffCountRow['totalStaff'] ?? 0);

        // Add totals to route info
        $routeInfo["totalCapacity"] = $totalCapacity;
        $routeInfo["totalRequestedPax"] = $totalRequestedPax;
        $routeInfo["totalAvailedPax"] = $totalAvailedPax;

        // Get stops information for this route and trip
        $stops = [];
        if (!empty($routeInfo)) {
            // Get the route ID from the trip
            $routeIDSql = "SELECT iRouteID FROM st_trips WHERE iTripID = $iTripID AND cStatus != 'X' LIMIT 1";
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
                    $tripStartTime = date('H:i:s', strtotime($tripDateTime));
                    $pickupTime = date('H:i', strtotime($tripStartTime) + ($offsetMinutes * 60));

                    // Get staff who will board at this stop for this trip
                    $staffSql = "SELECT DISTINCT
                                    st.iStaffID,
                                    st.vName as staffName,
                                    st.vMobile as staffMobile,
                                    req.iTripID,
                                    tva.iVehicleID,
                                    v.vRnum as vehicleNumber,
                                    req.dtIn
                                FROM st_request req
                                INNER JOIN staff st ON req.iStaffID = st.iStaffID AND st.cStatus = 'A'
                                LEFT OUTER JOIN st_trip_vehicle_assoc tva ON req.iTripID = tva.iTripID
                                LEFT JOIN vehicle v ON tva.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
                                WHERE req.iTripID = $iTripID 
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

        // Get trip datetime for this trip to check for conflicts
        $currentTripDateTime = $tripDateTime;

        // Get vehicles that are already assigned to other trips at the same time
        $conflictingVehiclesSql = "SELECT DISTINCT tva.iVehicleID
                                  FROM st_trips t
                                  INNER JOIN st_trip_vehicle_assoc tva ON t.iTripID = tva.iTripID
                                  WHERE t.dtTrip = '$currentTripDateTime'
                                  AND t.iTripID != $iTripID
                                  AND t.cStatus != 'X'
                                  AND tva.iVehicleID > 0";
        $conflictingVehiclesRes = sql_query($conflictingVehiclesSql);

        $conflictingVehicleIDs = [];
        while ($conflictRow = sql_fetch_assoc($conflictingVehiclesRes)) {
            $conflictingVehicleIDs[] = (int) $conflictRow['iVehicleID'];
        }

        $vehicleSql = "SELECT v.iVehicleID, v.vRnum, vc.iCapacity 
                      FROM vehicle v
                      LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                      WHERE v.cStatus = 'A' AND v.cServiceType IN ('S','B')
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

        $vendorSql = "SELECT DISTINCT ven.iVendorID, ven.vName 
                     FROM vendor ven 
                     INNER JOIN vehicle v ON v.iVendorID = ven.iVendorID 
                     WHERE v.cStatus = 'A' AND ven.cStatus = 'A' AND ven.cType IN ('B','S')
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

        $tableArrSql = "SELECT v.iVehicleID, v.vRnum, vc.iCapacity, ven.vName as vOwner, ven.iVendorID,v.iVendorID as vehicleVendorID,v.iType
                FROM vehicle v
                LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                LEFT JOIN vendor ven ON v.iVendorID = ven.iVendorID 
                    AND ven.cStatus = 'A' 
                    AND ven.cType IN ('B','S') 
                WHERE v.cStatus = 'A'  AND v.cServiceType IN ('S','B')
                ORDER BY v.vRnum";
        $tableArrRes = sql_query($tableArrSql);

        $tableArr = [];

        while ($tableRow = sql_fetch_assoc($tableArrRes)) {
            $vehicleID = (int) $tableRow['iVehicleID'];

            // Skip vehicles that are already assigned to other trips at the same time
            if (in_array($vehicleID, $conflictingVehicleIDs)) {
                continue;
            }

            $vendorID = (int) ($tableRow['vehicleVendorID'] ?? 0);
            $vehicleType = (int) ($tableRow['iType'] ?? 0);

            $vhDriver = [];

            if ($vendorID > 0) {

                // Normal vendor-specific drivers
                $vendorDriversSql = "SELECT d.iDriverID, d.vName as drName, d.cStatus
                         FROM driver d
                         WHERE d.cStatus = 'A' 
                         AND d.iVendorID = $vendorID
                         ORDER BY d.vName";
            } elseif ($vendorID == 0 && $vehicleType == 3) {

                // Special case: vendor = 0 and type = 3
                $vendorDriversSql = "SELECT d.iDriverID, d.vName as drName, d.cStatus
                         FROM driver d
                         WHERE d.cStatus = 'A' 
                         AND d.iVendorID = 0
                         AND d.iType = 3
                         ORDER BY d.vName";
            } else {
                $vendorDriversSql = '';
            }

            if (!empty($vendorDriversSql)) {
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
                "vhNum" => db_output2($tableRow['vRnum']) ?? '',
                "vhCap" => (int) ($tableRow['iCapacity'] ?? 0),
                "vhOwner" => db_output2($tableRow['vOwner']) ?? '',
                "vhDriver" => $vhDriver  // Vendor-specific driver list for each vehicle
            ];
        }
        $CANCELATION_STATUS = array("NS" => "No Show", "XP" => "Cancel with payment", "XN" => "Cancel without payment", "X" => "Remove");
        $cancelOpt = [['id' => 0, 'name' => 'Choose']];
        foreach ($CANCELATION_STATUS as $id => $name) {
            $cancelOpt[] = ['id' => $id, 'name' => $name];
        }
        $window = intval(GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode = 'QR_SCAN_WINDOW_POST'")) ?? 0;
        // echo "currentTripDateTime: $currentTripDateTime\n";
        // echo "window: $window\n";
        // Convert string to timestamp
        $currentTripTS = strtotime($currentTripDateTime);
        //   echo "currentTripTS: $currentTripTS\n";

        $maxWindowTS = strtotime("+{$window} minutes", $currentTripTS);
        $maxWindow = date('Y-m-d H:i:s', $maxWindowTS);

        $addVehButton = true;
        // echo "NOW: $NOW, maxWindow: $maxWindow\n";
        if (strtotime($NOW) >= $maxWindow) {
            $addVehButton = false;
        }


        echo json_encode([
            "data" => [
                "iTripID" => $iTripID,
                "routeInfo" => $routeInfo,
                "trip_details" => $vehicles,
                "vehicleCount" => count($vehicles),
                "tableArr" => $tableArr,
                "vehiOpt" => $vehiOpt,
                "modeOpt" => $modeOpt,
                "vendorOpt" => $vendorOpt,
                "stops" => $stops,
                "cancelOpt" => $cancelOpt,
                "addVehButton" => $addVehButton
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE TRIP_MANIFEST =====================
    case 'TRIP_MANIFEST':
        $iTripID = intval($_REQUEST['iTripID'] ?? 0);

        if ($iTripID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Missing or invalid iTripID parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $manifestSql = "SELECT 
                            t.dtTrip,
                            r.iRouteID,
                            r.vName as routeName,
                            r.vDestination as destination,
                            tva.iVehicleID,
                            v.vRnum as vehicleNumber,
                            vc.iCapacity as vehicleCapacity,
                            s.iStopID,
                            s.vName as stopName,
                            s.tOffsetFromStart,
                            s.iRank as stopRank,
                            req.iStaffID,
                            st.vName as staffName,
                            st.vMobile as staffMobile,
                            req.dtIn,
                            t.iRequested  as totalPaxRequested          
                        FROM st_trips t
                        LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                        LEFT OUTER JOIN st_trip_vehicle_assoc tva ON t.iTripID = tva.iTripID AND tva.cStatus IN ('A','C')
                        LEFT JOIN vehicle v ON tva.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
                        LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                        LEFT JOIN st_route_stops s ON r.iRouteID = s.iRouteID AND s.cStatus = 'A'
                        LEFT JOIN st_request req ON t.iTripID = req.iTripID AND req.iStopID = s.iStopID AND req.cStatus = 'A'
                        LEFT JOIN staff st ON req.iStaffID = st.iStaffID AND st.cStatus = 'A'
                        
                        WHERE t.iTripID = $iTripID AND t.cStatus != 'X'
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

            $stopID = (int) ($row['iStopID'] ?? 0);
            if ($stopID > 0) {
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
                "iGrpID" => $iTripID,
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
        $vehicleAssociations = [];
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
                    continue;
                }


                $tripIDForThisRow = $currentTripID; // LOCK TripID

                $insertValues[] = "($tripIDForThisRow,$tripIDForThisRow, $routeID, '" . db_input($tripDateTime) . "', 0, $user_id, 1, '$NOW', '$cStatus')";

                if (!empty($vehicles)) {
                    foreach ($vehicles as $vehicle) {
                        $vehID = intval($vehicle['vhId'] ?? $vehicle['vehID'] ?? 0);
                        $driverID = intval($vehicle['driverId'] ?? $vehicle['driverID'] ?? 0);

                        if ($vehID > 0) {
                            $vehicleAssociations[] = [
                                'tripID' => $tripIDForThisRow,
                                'vehicleID' => $vehID,
                                'driverID' => $driverID,
                                'assignedBy' => $user_id
                            ];
                        }
                    }
                }

                $currentTripID++;
            }
        }

        $insertedCount = 0;



        if (!empty($insertValues)) {

            sql_query("LOCK TABLES st_trips WRITE, st_trip_vehicle_assoc WRITE");

            $insertSql = "INSERT INTO st_trips (
                iTripID,
                iGrpID,
                iRouteID, 
                dtTrip, 
                iCapacity, 
                iTripAddedBy,
                iRank, 
                dtAdded,
                cStatus
            ) VALUES " . implode(', ', $insertValues);

            if (sql_query($insertSql)) {
                $insertedCount = count($insertValues);

                if (!empty($vehicleAssociations)) {
                    $assocInsertValues = [];
                    $startingTVAID = NextID('iTVAID', 'st_trip_vehicle_assoc');
                    $currentTVAID = $startingTVAID;

                    foreach ($vehicleAssociations as $assoc) {
                        $assocInsertValues[] = "($currentTVAID, {$assoc['tripID']}, {$assoc['vehicleID']}, {$assoc['driverID']}, {$assoc['assignedBy']}, '$NOW', 'A')";
                        $currentTVAID++;
                    }

                    if (!empty($assocInsertValues)) {
                        $assocInsertSql = "INSERT INTO st_trip_vehicle_assoc (
                            iTVAID,
                            iTripID,
                            iVehicleID,
                            iDriverID,
                            iVehAssignedBy,
                            dtAdded,
                            cStatus
                        ) VALUES " . implode(', ', $assocInsertValues);

                        if (!sql_query($assocInsertSql)) {
                            $errors[] = "Failed to insert vehicle associations. SQL Error: ";
                        }
                    }
                }
            } else {

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

        $iTripID = intval($_REQUEST['iTripID'] ?? 0);
        $trip_details = $_REQUEST['trip_details'] ?? [];

        if ($iTripID <= 0 || empty($trip_details)) {
            echo json_encode([
                "error" => [
                    "message" => "Missing required parameters: iTripID or trip_details"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $errors = [];
        $updatedCount = 0;
        $insertedCount = 0;

        // Extract vehicle IDs & validate duplicates
        $vehicleIDs = [];
        $duplicateVehicles = [];

        foreach ($trip_details as $trip) {
            $vID = intval($trip['vehicleID'] ?? $trip['vhId'] ?? 0);
            if ($vID > 0) {
                if (in_array($vID, $vehicleIDs)) {
                    $duplicateVehicles[] = $vID;
                } else {
                    $vehicleIDs[] = $vID;
                }
            }
        }

        if (!empty($duplicateVehicles)) {
            echo json_encode([
                "error" => [
                    "message" => "Duplicate vehicle IDs found: " . implode(", ", array_unique($duplicateVehicles))
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Fetch trip datetime
        $tripDateTimeRes = sql_query("SELECT dtTrip FROM st_trips WHERE iTripID = $iTripID AND cStatus != 'X'");
        if (sql_num_rows($tripDateTimeRes) == 0) {
            echo json_encode(["error" => ["message" => "Trip not found"], "statusCode" => 404]);
            exit;
        }

        $tripDateTime = sql_fetch_assoc($tripDateTimeRes)['dtTrip'];

        // Check for time conflicts with other trips
        if (!empty($vehicleIDs)) {
            $vehicleIDsStr = implode(',', $vehicleIDs);
            $conflictSql = "SELECT DISTINCT tva.iVehicleID
                        FROM st_trip_vehicle_assoc tva
                        INNER JOIN st_trips t ON tva.iTripID = t.iTripID
                        WHERE tva.iTripID != $iTripID
                        AND tva.iVehicleID IN ($vehicleIDsStr)
                        AND t.dtTrip = '$tripDateTime'
                        AND t.cStatus != 'X'";

            $confRes = sql_query($conflictSql);
            $conflictingVehicles = [];

            while ($r = sql_fetch_assoc($confRes)) {
                $conflictingVehicles[] = $r['iVehicleID'];
            }

            if (!empty($conflictingVehicles)) {
                echo json_encode([
                    "error" => [
                        "message" => "Vehicle(s) already assigned to other trips: " . implode(", ", array_unique($conflictingVehicles))
                    ],
                    "statusCode" => 400
                ]);
                exit;
            }
        }

        sql_query("START TRANSACTION");

        try {

            // Fetch existing associations for trip
            $existingTVAIDs = [];
            $resExisting = sql_query("SELECT iTVAID FROM st_trip_vehicle_assoc WHERE iTripID = $iTripID AND cStatus != 'X'");
            while ($row = sql_fetch_assoc($resExisting)) {
                $existingTVAIDs[] = intval($row['iTVAID']);
            }

            // Incoming IDs
            $incomingTVAIDs = [];
            foreach ($trip_details as $trip) {
                $incomingTVAIDs[] = intval($trip['iTVAID'] ?? 0);
            }

            // Determine which to deactivate
            // $toDeactivate = array_diff(array: $existingTVAIDs, $incomingTVAIDs);
            // if (!empty($toDeactivate)) {
            //     sql_query("UPDATE st_trip_vehicle_assoc SET cStatus='X' 
            //                 WHERE iTVAID IN (" . implode(',', $toDeactivate) . ")");
            // }

            // Process inserts/updates
            foreach ($trip_details as $trip) {

                $iTVAID = intval($trip['iTVAID'] ?? 0);
                $vehicleID = intval($trip['vehicleID'] ?? $trip['vhId'] ?? 0);
                $driverID = intval($trip['driverID'] ?? $trip['driverId'] ?? 0);

                if ($vehicleID <= 0)
                    continue;

                if ($iTVAID == 0) {
                    // INSERT NEW
                    $newID = NextID('iTVAID', 'st_trip_vehicle_assoc');
                    $insertSql = "INSERT INTO st_trip_vehicle_assoc (
                                iTVAID, iTripID, iVehicleID, iDriverID, iVehAssignedBy, dtAdded, cStatus
                              ) VALUES (
                                $newID, $iTripID, $vehicleID, $driverID, $user_id, '$NOW', 'A'
                              )";

                    if (sql_query($insertSql)) {
                        $insertedCount++;
                    } else {
                        $errors[] = "Insert failed for vehicle ID $vehicleID.";
                    }
                } else {
                    // UPDATE EXISTING
                    $updateSql = "UPDATE st_trip_vehicle_assoc SET
                                iVehicleID = $vehicleID,
                                iDriverID = $driverID,
                                cStatus = 'A'
                              WHERE iTVAID = $iTVAID";

                    if (sql_query($updateSql)) {
                        $updatedCount++;
                    } else {
                        $errors[] = "Update failed for association ID $iTVAID.";
                    }
                }
            }

            // Update trip capacity
            sql_query("UPDATE st_trips t SET 
                     iCapacity = (
                        SELECT COALESCE(SUM(vc.iCapacity), 0)
                        FROM st_trip_vehicle_assoc tva
                        INNER JOIN vehicle v ON tva.iVehicleID = v.iVehicleID
                        INNER JOIN vehicle_category vc ON v.iCatID = vc.iVCatID
                        WHERE tva.iTripID = $iTripID 
                        AND v.cStatus = 'A'
                        AND vc.cStatus = 'A'
                     )
                   WHERE t.iTripID = $iTripID");

            sql_query("COMMIT");

            echo json_encode([
                "data" => [
                    "message" => "Trip update completed",
                    "insertedCount" => $insertedCount,
                    "updatedCount" => $updatedCount,
                    "iTripID" => $iTripID
                ],
                "warnings" => $errors,
                "statusCode" => 200
            ]);
        } catch (Exception $e) {

            sql_query("ROLLBACK");
            echo json_encode([
                "error" => ["message" => "Transaction failed: " . $e->getMessage()],
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
        $checkSql = "SELECT iTripID FROM st_trips WHERE iTripID = $iTripID AND cStatus != 'X'";
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

        // Start transaction to delete trip and its vehicle associations
        sql_query("START TRANSACTION");

        try {
            // Mark trip as deleted (status = 'X')
            $deleteSql = "UPDATE st_trips SET 
                            cStatus = 'X'
                          WHERE iTripID = $iTripID";

            if (!sql_query($deleteSql)) {
                throw new Exception("Failed to delete trip");
            }

            // Mark all vehicle associations as deleted
            $deleteAssocSql = "UPDATE st_trip_vehicle_assoc SET 
                                cStatus = 'X'
                              WHERE iTripID = $iTripID";

            sql_query($deleteAssocSql); // This is optional, so don't fail if it doesn't work

            sql_query("COMMIT");

            echo json_encode([
                "data" => [
                    "message" => "Trip deleted successfully",
                    "iTripID" => $iTripID
                ],
                "statusCode" => 200
            ]);
        } catch (Exception $e) {
            sql_query("ROLLBACK");

            echo json_encode([
                "error" => [
                    "message" => "Failed to delete trip: " . $e->getMessage()
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE MARK_TRIP_AS_COMPLETE =====================
    case 'MARK_TRIP_AS_COMPLETE':
        $iTripID = intval($_REQUEST['iTripID'] ?? 0);
        $iTVAID = intval($_REQUEST['iTVAID'] ?? 0);

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
        $checkSql = "SELECT iTripID FROM st_trips WHERE iTripID = $iTripID";
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

        // Mark trip as complete (status = 'C')
        $completeSql = "UPDATE st_trip_vehicle_assoc SET 
                          cStatus = 'C',
                          iStatusChangedBy = $user_id
                        WHERE iTVAID = $iTVAID AND iTripID=$iTripID";

        if (sql_query($completeSql)) {
            $checkifAllComplete = GetXFromYID("SELECT count(*) from st_trip_vehicle_assoc where iTripID=$iTripID AND cStatus='A'");
            if ($checkifAllComplete == 0) {
                sql_query("UPDATE st_trips SET cStatus = 'C' WHERE iTripID=$iTripID");
            }


            if (sql_affected_rows() > 0) {
                echo json_encode([
                    "data" => [
                        "message" => "Trip marked as complete successfully",
                        "iTripID" => $iTripID
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
        $iTVAID = intval($_REQUEST['iTVAID'] ?? 0);

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
        // $validStatuses = array_keys($STAFF_TRIP_STATUS);
        // if (!in_array($status, $validStatuses)) {
        //     echo json_encode([
        //         "error" => [
        //             "message" => "Invalid status. Allowed values: " . implode(', ', $validStatuses)
        //         ],
        //         "statusCode" => 400
        //     ]);
        //     exit;
        // }


        $checkSql = "SELECT iTripID, cStatus FROM st_trips WHERE iTripID = $iTripID";
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
        $currentStatus = $tripRow['cStatus'];

        // Update trip status with reason
        $updateSql = "UPDATE st_trip_vehicle_assoc SET 
                        cStatus = '" . db_input($status) . "',
                        vCancellationReason = '" . db_input($reason) . "',
                        iStatusChangedBy = $user_id
                      WHERE iTripID = $iTripID AND iTVAID=$iTVAID";

        if (sql_query($updateSql)) {
            if (sql_affected_rows() > 0) {
                $statusText = isset($STAFF_TRIP_STATUS[$status]) ? $STAFF_TRIP_STATUS[$status] : $status;

                echo json_encode([
                    "data" => [
                        "message" => "Trip status changed successfully",
                        "iTripID" => $iTripID,
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

    case 'MARK_STAFF_AS_ENTERED':

        $iTripID = intval($_REQUEST['iTripID'] ?? 0);
        $staffIds = $_REQUEST['staffIds'] ?? [];
        $vehicleID = intval($_REQUEST['vehicleID'] ?? 0);

        if ($iTripID <= 0 || empty($staffIds) || !is_array($staffIds)) {
            echo json_encode([
                "error" => ["message" => "Invalid TripID or staffIds missing"],
                "statusCode" => 400
            ]);
            exit;
        }

        $datetime = NOW;


        $staffIds = array_map('intval', $staffIds);
        $idList = implode(',', $staffIds);

        //     $updateSql = "
        //     UPDATE st_request
        //     SET 
        //         dtIn  = IF(dtIn IS NULL, '" . db_input($datetime) . "', dtIn),
        //         dtOut = IF(dtOut IS NULL, '" . db_input($datetime) . "', dtOut),
        //         iVehicleID = $vehicleID
        //     WHERE iStaffID IN ($idList)
        //     AND iTripID = $iTripID
        // ";
        $updateSql = "
        UPDATE st_request
        SET 
            dtIn  = '$datetime',
            dtOut = '$datetime',
            iVehicleID = $vehicleID
        WHERE iStaffID IN ($idList)
        AND iTripID = $iTripID
    ";
        $updateRess = sql_query($updateSql);
        if ($updateRess) {
            echo json_encode([
                "data" => ["message" => "Staff marked as entered successfully"],
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => ["message" => "Failed to update staff entry status"],
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
