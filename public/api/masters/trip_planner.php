<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";
include "../api_common.php";
header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true);
$_REQUEST = [...$_REQUEST, ...($request ?? [])];
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

    // ===================== CASE 1: LIST_PLANNER =====================
    case 'LIST_PLANNER':
        $sql = "SELECT 
    t.iTripID,
    DATE(t.dtTrip) AS dtTrip,
    TIME(t.dtTrip) AS tripTime,
    t.iRouteID,
    r.vName AS route,
    r.vDestination AS destination,
    tva.iVehicleID,
    v.vRnum AS vehicleRegNo,
    v.vName AS vehicleName,
    t.cStatus AS status
FROM st_trips t
 LEFT OUTER JOIN st_trip_vehicle_assoc tva ON t.iTripID = tva.iTripID
LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
LEFT JOIN vehicle v ON tva.iVehicleID = v.iVehicleID
WHERE t.cStatus != 'X'
ORDER BY t.iRouteID, DATE(t.dtTrip), TIME(t.dtTrip);
";

        $res = sql_query($sql);
        $routeData = [];

        // First pass: collect all data by route and date
        while ($row = sql_fetch_assoc($res)) {
            $routeID = $row['iRouteID'];
            $dtTrip = $row['dtTrip'];

            if (!isset($routeData[$routeID])) {
                $routeData[$routeID] = [
                    'route' => $row['route'],
                    'destination' => $row['destination'],
                    'dates' => []
                ];
            }

            if (!isset($routeData[$routeID]['dates'][$dtTrip])) {
                $routeData[$routeID]['dates'][$dtTrip] = [];
            }

            $routeData[$routeID]['dates'][$dtTrip][] = $row;
        }

        $rowData = [];

        // Second pass: group by unique timing and status combinations
        foreach ($routeData as $routeID => $routeInfo) {
            $dateGroups = [];

            // Group dates by their timing and status patterns
            foreach ($routeInfo['dates'] as $date => $trips) {
                $timingPattern = [];
                $statusPattern = [];
                foreach ($trips as $trip) {
                    $timingPattern[] = $trip['tripTime'];
                    $statusPattern[] = $trip['status'];
                }
                sort($timingPattern); // Sort to ensure consistent pattern matching
                $uniqueStatuses = array_unique($statusPattern);
                sort($uniqueStatuses);

                // Create pattern key with both timing and status
                $patternKey = implode('|', $timingPattern) . '::' . implode('|', $uniqueStatuses);

                if (!isset($dateGroups[$patternKey])) {
                    $dateGroups[$patternKey] = [
                        'dates' => [],
                        'trips' => [],
                        'statuses' => $uniqueStatuses
                    ];
                }

                $dateGroups[$patternKey]['dates'][] = $date;
                $dateGroups[$patternKey]['trips'] = [...$dateGroups[$patternKey]['trips'], ...$trips];
            }

            // Create separate groups for each timing and status pattern
            foreach ($dateGroups as $patternKey => $groupData) {
                sort($groupData['dates']); // Sort dates
                $fromDate = min($groupData['dates']);
                $toDate = max($groupData['dates']);

                // Determine overall set status
                $allStatuses = $groupData['statuses'];
                $overallStatus = '';
                $overallStatusCode = '';

                if (count($allStatuses) == 1) {
                    $overallStatusCode = $allStatuses[0];
                } else {
                    // Mixed statuses - determine priority
                    if (in_array('A', $allStatuses)) {
                        $overallStatusCode = 'A'; // Active takes priority
                    } elseif (in_array('P', $allStatuses)) {
                        $overallStatusCode = 'P'; // Pending next
                    } elseif (in_array('D', $allStatuses)) {
                        $overallStatusCode = 'D'; // Draft next
                    } elseif (in_array('C', $allStatuses)) {
                        $overallStatusCode = 'C'; // Completed last
                    } else {
                        $overallStatusCode = 'Mixed';
                    }
                }

                // Convert overall status to readable format
                switch ($overallStatusCode) {
                    case 'A':
                        $overallStatus = 'Active';
                        break;
                    case 'C':
                        $overallStatus = 'Completed';
                        break;
                    case 'P':
                        $overallStatus = 'Pending';
                        break;
                    case 'D':
                        $overallStatus = 'Draft';
                        break;
                    default:
                        $overallStatus = 'Unknown';
                        break;
                }

                // Extract unique timings from trips
                $uniqueTimings = [];

                foreach ($groupData['trips'] as $trip) {

                    // Convert time to proper 07:33 format
                    $formattedTime = date('H:i', strtotime($trip['tripTime']));

                    if (!in_array($formattedTime, $uniqueTimings)) {
                        $uniqueTimings[] = $formattedTime;
                    }
                }
                // Sort timings
                sort($uniqueTimings);

                // Count total trips for this set
                $totalTrips = count($groupData['trips']);

                $rowData[] = [
                    "routeID" => (int) $routeID,
                    "route" => db_output2($routeInfo['route'] ?? ''),
                    "destination" => db_output2($routeInfo['destination'] ?? ''),
                    "fromDate" => date('d/m/Y', strtotime($fromDate)),
                    "toDate" => date('d/m/Y', strtotime($toDate)),
                    "dayCount" => count($groupData['dates']),
                    "status" => $overallStatus,
                    "statusCode" => $overallStatusCode,
                    "timings" => $uniqueTimings, // Array format for APPROVE_SET compatibility
                    "totalTrips" => $totalTrips
                ];
            }
        }

        // Sort by route and from date
        usort($rowData, function ($a, $b) {
            if ($a['routeID'] == $b['routeID']) {
                return strtotime(str_replace('/', '-', $b['fromDate'])) - strtotime(str_replace('/', '-', $a['fromDate']));
            }
            return $a['routeID'] - $b['routeID'];
        });

        echo json_encode([
            "data" => [
                "rowData" => $rowData,
                "totalGroups" => count($rowData)
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE APPROVE_TRIP_PLANNER =====================
    case 'APPROVE_TRIP_PLANNER':
        $routeID = intval($_REQUEST['routeID'] ?? 0);
        $fromDate = $_REQUEST['fromDate'] ?? '';
        $toDate = $_REQUEST['toDate'] ?? '';
        $timings = $_REQUEST['timings'] ?? [];
        $currentStatus = $_REQUEST['currentStatus'] ?? '';
        if (!checkUserModuleAccess($user_id, 'STAFF_TRIP_APPROVE')) {
            echo json_encode([
                "error" => [
                    "message" => "Access denied. You don't have permission to approve trips."
                ],
                "statusCode" => 403
            ]);
            exit;
        }
        // Validate required parameters
        if ($routeID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Missing or invalid routeID parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if (empty($fromDate) || empty($toDate)) {
            echo json_encode([
                "error" => [
                    "message" => "Missing fromDate or toDate parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if (empty($timings) || !is_array($timings)) {
            echo json_encode([
                "error" => [
                    "message" => "Missing or invalid timings parameter (should be array)"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if (empty($currentStatus)) {
            echo json_encode([
                "error" => [
                    "message" => "Missing currentStatus parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Convert date format from d/m/Y to Y-m-d if needed
        $fromDateFormatted = date('Y-m-d', strtotime(str_replace('/', '-', $fromDate)));
        $toDateFormatted = date('Y-m-d', strtotime(str_replace('/', '-', $toDate)));

        // Create timing condition for SQL
        $timingConditions = [];
        foreach ($timings as $timing) {
            $timingConditions[] = "TIME(t.dtTrip) = '" . db_input($timing) . "'";
        }
        $timingClause = '(' . implode(' OR ', $timingConditions) . ')';

        // Find matching trips in the set
        $findTripsSql = "SELECT t.iTripID, t.cStatus, COUNT(*) as tripCount
                        FROM st_trips t
                        WHERE t.iRouteID = $routeID
                        AND DATE(t.dtTrip) BETWEEN '$fromDateFormatted' AND '$toDateFormatted'
                        AND $timingClause
                        AND t.cStatus = '" . db_input($currentStatus) . "'
                        AND t.cStatus != 'X'";

        $findTripsRes = sql_query($findTripsSql);

        if (sql_num_rows($findTripsRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "No matching trips found for the specified set"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $tripData = sql_fetch_assoc($findTripsRes);
        $tripCount = $tripData['tripCount'];

        $updateSql = "UPDATE st_trips t SET 
                        t.cStatus = 'A',
                        t.iTripApprovedBy = $user_id
                      WHERE t.iRouteID = $routeID
                        AND DATE(t.dtTrip) BETWEEN '$fromDateFormatted' AND '$toDateFormatted'
                        AND $timingClause
                        AND t.cStatus = '" . db_input($currentStatus) . "'
                        AND t.cStatus != 'X'";

        if (sql_query($updateSql)) {
            $affectedRows = sql_affected_rows();

            if ($affectedRows > 0) {
                echo json_encode([
                    "data" => [
                        "message" => "Trip set approved successfully",
                        "routeID" => $routeID,
                        "fromDate" => $fromDate,
                        "toDate" => $toDate,
                        "timings" => $timings,
                        "previousStatus" => $currentStatus,
                        "newStatus" => "A"
                        // "tripsUpdated" => $affectedRows,
                        // "expectedTrips" => $tripCount
                    ],
                    "statusCode" => 200
                ]);
            } else {
                echo json_encode([
                    "error" => [
                        "message" => "No trips were updated. Set may have already been approved or conditions don't match."
                    ],
                    "statusCode" => 400
                ]);
            }
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Database error occurred while approving trip set"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE APPROVE_TRIP_PLANNER_WITH_DATA =====================
    case 'TRIP_PLANNER_EDIT':
        $routeID = intval($_REQUEST['routeID'] ?? 0);
        $fromDate = $_REQUEST['fromDate'] ?? '';
        $toDate = $_REQUEST['toDate'] ?? '';
        $timings = $_REQUEST['timings'] ?? [];
        $currentStatus = $_REQUEST['currentStatus'] ?? '';

        if (!checkUserModuleAccess($user_id, 'STAFF_TRIP_APPROVE')) {
            echo json_encode([
                "error" => [
                    "message" => "Access denied. You don't have permission to approve trips."
                ],
                "statusCode" => 403
            ]);
            exit;
        }

        // Validate required parameters
        if ($routeID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Missing or invalid routeID parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if (empty($fromDate) || empty($toDate)) {
            echo json_encode([
                "error" => [
                    "message" => "Missing fromDate or toDate parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if (empty($timings) || !is_array($timings)) {
            echo json_encode([
                "error" => [
                    "message" => "Missing or invalid timings parameter (should be array)"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if (empty($currentStatus)) {
            echo json_encode([
                "error" => [
                    "message" => "Missing currentStatus parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Convert date format from d/m/Y to Y-m-d if needed
        $fromDateFormatted = date('Y-m-d', strtotime(str_replace('/', '-', $fromDate)));
        $toDateFormatted = date('Y-m-d', strtotime(str_replace('/', '-', $toDate)));

        // Calculate the new date range (one day after fromDate to calculated toDate)
        $newFromDate = date('Y-m-d', strtotime($fromDateFormatted . ' +1 day'));

        // Calculate the number of days in the original request
        $originalDays = (strtotime($toDateFormatted) - strtotime($fromDateFormatted)) / (60 * 60 * 24) + 1;

        // Calculate new toDate based on original duration
        $newToDate = date('Y-m-d', strtotime($newFromDate . ' +' . ($originalDays - 1) . ' days'));

        // Create timing condition for SQL
        $timingConditions = [];
        foreach ($timings as $timing) {
            $timingConditions[] = "TIME(t.dtTrip) = '" . db_input($timing) . "'";
        }
        $timingClause = '(' . implode(' OR ', $timingConditions) . ')';

        // Find matching trips in the set
        $findTripsSql = "SELECT t.iTripID, t.cStatus, COUNT(*) as tripCount
                        FROM st_trips t
                        WHERE t.iRouteID = $routeID
                        AND DATE(t.dtTrip) BETWEEN '$fromDateFormatted' AND '$toDateFormatted'
                        AND $timingClause
                        AND t.cStatus = '" . db_input($currentStatus) . "'";

        $findTripsRes = sql_query($findTripsSql);

        if (sql_num_rows($findTripsRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "No matching trips found for the specified set"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $tripData = sql_fetch_assoc($findTripsRes);
        $tripCount = $tripData['tripCount'];

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

        $timingsWithAssignments = [];
        foreach ($timings as $timing) {
            $tripAssignmentsSql = "SELECT DISTINCT
                                    TIME(t.dtTrip) as tripTime,
                                    tva.iVehicleID,
                                    v.vRnum as vehicleNumber,
                                    vc.iCapacity as vehicleCapacity,
                                    ven.iVendorID,
                                    ven.vName as vendorName,
                                    tva.iDriverID,
                                    d.vName as driverName,
                                    d.vMobileNum as driverMobile
                                FROM st_trips t
                                INNER JOIN st_trip_vehicle_assoc tva ON t.iTripID = tva.iTripID
                                INNER JOIN vehicle v ON tva.iVehicleID = v.iVehicleID AND v.cStatus = 'A' AND v.cServiceType IN ('S','B')
                                LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                                LEFT JOIN vendor ven ON v.iVendorID = ven.iVendorID AND ven.cStatus = 'A' AND ven.cType IN ('B','S')
                                LEFT JOIN driver d ON tva.iDriverID = d.iDriverID AND d.cStatus = 'A'
                                WHERE t.iRouteID = $routeID
                                AND DATE(t.dtTrip) BETWEEN '$fromDateFormatted' AND '$toDateFormatted'
                                AND TIME(t.dtTrip) = '" . db_input($timing) . "'
                                AND t.cStatus = 'A'
                                AND t.cStatus != 'X'
                                AND tva.iVehicleID > 0
                                ORDER BY v.vRnum";

            $assignmentsRes = sql_query($tripAssignmentsSql);
            $assignments = [];

            while ($assignmentRow = sql_fetch_assoc($assignmentsRes)) {
                $assignments[] = [
                    "vhId" => (int) ($assignmentRow['iVehicleID'] ?? 0),
                    "vhNum" => db_output2($assignmentRow['vehicleNumber'] ?? ''),
                    "vhCap" => (int) ($assignmentRow['vehicleCapacity'] ?? 0),
                    "vendorID" => (int) ($assignmentRow['iVendorID'] ?? 0),
                    "vhOwner" => db_output2($assignmentRow['vendorName'] ?? ''),
                    "driverId" => (int) ($assignmentRow['iDriverID'] ?? 0),
                    //   "driverName" => db_output2($assignmentRow['driverName'] ?? ''),
                    "driverMobile" => db_output2($assignmentRow['driverMobile'] ?? '')
                ];
            }

            $timingsWithAssignments[] = [
                "time" => (new DateTime($timing))->format('H:i'),
                "vehicle" => $assignments
            ];
        }

        // Get table array with vehicle details and vendor-specific drivers
        $tableArrSql = "SELECT v.iVehicleID, v.vRnum, vc.iCapacity, ven.vName as vOwner, ven.iVendorID, v.iType
                       FROM vehicle v
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       LEFT JOIN vendor ven ON v.iVendorID = ven.iVendorID AND ven.cStatus = 'A' and ven.cType IN ('B','S') 
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
                "vhId" => (int) $tableRow['iVehicleID'],
                "vhNum" => db_output2($tableRow['vRnum'] ?? ''),
                "vhCap" => (int) ($tableRow['iCapacity'] ?? 0),
                "vhOwner" => db_output2($tableRow['vOwner'] ?? ''),
                "vhDriver" => $vhDriver
            ];
        }

        echo json_encode([
            "data" => [
                "message" => "Trip planner data fetched successfully",
                "routeID" => $routeID,
                "fromDate" => $newFromDate,
                "toDate" => $newToDate,
                //"dayCount" => $originalDays,
                //  "timings" => $timings,
                "timingsWithAssignments" => $timingsWithAssignments,
                // "previousStatus" => $currentStatus,
                // "newStatus" => "A",
                // "tripsUpdated" => $affectedRows,
                "rdOpt" => $rdOpt,
                "vehiOpt" => $vehiOpt,
                "modeOpt" => $modeOpt,
                "vendorOpt" => $vendorOpt,
                "tableArr" => $tableArr
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
