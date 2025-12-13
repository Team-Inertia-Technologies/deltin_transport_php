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

    // ===================== CASE 1: LIST_PLANNER =====================
    case 'LIST_PLANNER':
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

        $sql = "SELECT 
    r.vName AS route,
    r.vDestination AS destination,
    t.dtAdded,
    t.cStatus AS status
FROM st_trips t
LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
WHERE $whereClause
GROUP BY t.dtAdded, r.vName, r.vDestination, t.cStatus
ORDER BY t.dtAdded;
";

        $res = sql_query($sql);
        $rowData = [];

        // Process the query results according to the actual SQL structure
        while ($row = sql_fetch_assoc($res)) {
            // Convert status code to readable format
            $statusText = '';
            switch ($row['status']) {
                case 'A':
                    $statusText = 'Active';
                    break;
                case 'C':
                    $statusText = 'Completed';
                    break;
                case 'P':
                    $statusText = 'Pending';
                    break;
                case 'X':
                    $statusText = 'Cancelled';
                    break;
                default:
                    $statusText = 'Unknown';
                    break;
            }

            $rowData[] = [
                "datetime" => date('d/m/Y g:i A', strtotime($row['dtAdded'])),
                "route" => db_output2($row['route'] ?? ''),
                "destination" => db_output2($row['destination'] ?? ''),
                "status" => $statusText,
                "statusCode" => $row['status']
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

        // Build WHERE conditions for specific dtAdded date
        $whereConditions = [
            "t.cStatus != 'X'",
            "DATE(t.dtAdded) = '" . db_input($dtAdded) . "'"
        ];

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT 
    t.iRouteID,
    r.vName AS route,
    r.vDestination AS destination,
    TIME(t.dtTrip) AS tripTime,
    DATE(t.dtTrip) AS fromDate,
    DATE(t.dtTrip) AS toDate,
    t.cStatus AS status
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
            $statusText = '';
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
                "statusCode" => $row['status']
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
