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
        // Get all trips data
        $sql = "SELECT 
    t.iTripID,
    DATE(t.dtTrip) AS dtTrip,
    TIME(t.dtTrip) AS tripTime,
    t.iRouteID,
    r.vName AS route,
    r.vDestination AS destination,
    t.iVehicleID,
    v.vRnum AS vehicleRegNo,
    v.vName AS vehicleName,
    t.cStatus AS status
FROM st_trips t
LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
LEFT JOIN vehicle v ON t.iVehicleID = v.iVehicleID
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
                    $timeKey = $trip['tripTime'];
                    if (!in_array($timeKey, $uniqueTimings)) {
                        $uniqueTimings[] = $timeKey;
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

    // ===================== CASE 2: GET_TRIPS_BY_DATE =====================
    case 'GET_TRIPS_BY_DATE':
        $dtAdded = $_REQUEST['dtAdded'] ?? '';

        // Validate required parameter
        if (empty($dtAdded)) {
            echo json_encode([
                "error" => [
                    "message" => "Missing dtAdded parameter"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Extract just the date part from dtAdded (in case it includes time)
        $dateOnly = date('Y-m-d', strtotime($dtAdded));

        // Build WHERE conditions for specific dtAdded date
        $whereConditions = [
            "t.cStatus != 'X'",
            "DATE(t.dtAdded) = '" . db_input($dateOnly) . "'"
        ];

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT 
    t.iRouteID,
    r.vName AS route,
    r.vDestination AS destination,
    TIME(t.dtTrip) AS tripTime,
    DATE(t.dtTrip) AS fromDate,
    DATE(t.dtTrip) AS toDate,
    t.cStatus AS status,
    MIN(DATE(t.dtTrip)) AS startDate,
    MAX(DATE(t.dtTrip)) AS endDate
FROM st_trips t
LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
WHERE $whereClause
GROUP BY t.iRouteID, r.vName, r.vDestination, TIME(t.dtTrip), DATE(t.dtTrip), t.cStatus
ORDER BY TIME(t.dtTrip), r.vName;
";

        $res = sql_query($sql);
        $rowData = [];

        // Process the query results grouped by timing
        while ($row = sql_fetch_assoc($res)) {
            // Convert status code to readable format
            switch ($row['status']) {
                case 'A':
                    $statusText = 'Active';
                    break;
                case 'C':
                    $statusText = 'Completed';
                    break;
                case 'D':
                    $statusText = 'Draft';
                    break;
                case 'X':
                    $statusText = 'Cancelled';
                    break;
                default:
                    $statusText = 'Unknown';
                    break;
            }

            $rowData[] = [
                "routeID" => (int) $row['iRouteID'],
                "route" => db_output2($row['route'] ?? ''),
                "destination" => db_output2($row['destination'] ?? ''),
                "tripTime" => date('g:i A', strtotime($row['tripTime'])),
                "fromDate" => date('d/m/Y', strtotime($row['fromDate'])),
                "toDate" => date('d/m/Y', strtotime($row['toDate'])),
                "status" => $statusText,
                "statusCode" => $row['status'],
                "startDate" => date('d/m/Y', strtotime($row['startDate'])),
                "endDate" => date('d/m/Y', strtotime($row['endDate']))
            ];
        }

        echo json_encode([
            "data" => [
                "rowData" => $rowData,
                "dtAdded" => $dtAdded,
                "totalTrips" => count($rowData)
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
