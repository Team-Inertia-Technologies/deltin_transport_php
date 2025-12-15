<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";

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

switch ($mode) {

    // ===================== CASE 1: LIST_PLANNER =====================
    case 'LIST_PLANNER':
        // First get route and date range groups
        $sql = "SELECT 
    t.iRouteID,
    r.vName AS route,
    r.vDestination AS destination,
    MIN(DATE(t.dtTrip)) AS fromDate,
    MAX(DATE(t.dtTrip)) AS toDate,
    COUNT(DISTINCT DATE(t.dtTrip)) AS dayCount
FROM st_trips t
LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
WHERE t.cStatus != 'X'
GROUP BY t.iRouteID, r.vName, r.vDestination
ORDER BY MIN(DATE(t.dtTrip)) DESC, r.vName;
";

        $res = sql_query($sql);
        $rowData = [];

        // Process each route group
        while ($row = sql_fetch_assoc($res)) {
            $routeID = $row['iRouteID'];
            $fromDate = $row['fromDate'];
            $toDate = $row['toDate'];

            // Get all timings and vehicles for this route within the date range
            $tripsSql = "SELECT 
        t.iTripID,
        DATE(t.dtTrip) AS dtTrip,
        TIME(t.dtTrip) AS tripTime,
        t.iVehicleID,
        v.vRnum AS vehicleRegNo,
        t.cStatus AS status
    FROM st_trips t
    LEFT JOIN vehicle v ON t.iVehicleID = v.iVehicleID
    WHERE t.cStatus != 'X' 
        AND t.iRouteID = $routeID
        AND DATE(t.dtTrip) BETWEEN '$fromDate' AND '$toDate'
    ORDER BY TIME(t.dtTrip), DATE(t.dtTrip);
    ";

            $tripsRes = sql_query($tripsSql);
            $timings = [];

            // Group by timing and collect vehicles
            while ($tripRow = sql_fetch_assoc($tripsRes)) {
                // Convert status code to readable format
                switch ($tripRow['status']) {
                    case 'A':
                        $statusText = 'Active';
                        break;
                    case 'C':
                        $statusText = 'Completed';
                        break;
                    case 'P':
                        $statusText = 'Pending';
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

                $timeKey = $tripRow['tripTime'];
                
                if (!isset($timings[$timeKey])) {
                    $timings[$timeKey] = [
                        "tripTime" => $tripRow['tripTime'] ? date('g:i A', strtotime($tripRow['tripTime'])) : '',
                        "vehicles" => []
                    ];
                }

                $timings[$timeKey]["vehicles"][] = [
                    "iTripID" => (int) $tripRow['iTripID'],
                    "dtTrip" => date('d/m/Y', strtotime($tripRow['dtTrip'])),
                    "vehicleID" => (int) $tripRow['iVehicleID'],
                    "vehicleRegNo" => db_output2($tripRow['vehicleRegNo'] ?? ''),
                    "status" => $statusText,
                    "statusCode" => $tripRow['status']
                ];
            }

            // Convert timings to indexed array
            $timingsArray = array_values($timings);

            $rowData[] = [
                "routeID" => (int) $routeID,
                "route" => db_output2($row['route'] ?? ''),
                "destination" => db_output2($row['destination'] ?? ''),
                "fromDate" => date('d/m/Y', strtotime($fromDate)),
                "toDate" => date('d/m/Y', strtotime($toDate)),
                "dayCount" => (int) $row['dayCount'],
                "timings" => $timingsArray,
                "totalTrips" => array_sum(array_map(function($timing) { return count($timing["vehicles"]); }, $timingsArray))
            ];
        }

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
