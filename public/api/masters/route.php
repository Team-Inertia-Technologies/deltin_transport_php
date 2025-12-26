<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";
//include "../api_common.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true); // Decode as associative array
$_REQUEST = array_merge($_REQUEST, $request ?? []); // Merge with $_REQUEST
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
$NOW=NOW;
switch ($mode) {

    // ===================== CASE 1: LIST =====================
    case 'LIST':
        $sql = "SELECT iRouteID, vName, vDestination,iRank,cStatus FROM st_route WHERE cStatus != 'X' ORDER BY iRank";
        $res = sql_query($sql);

        $rowData = [];
        $routesOpt = [];

        while ($row = sql_fetch_assoc($res)) {
            // For the main route list
            $rowData[] = [
                "id" => (int) $row['iRouteID'],
                "route" => db_output2($row['vName']),
                "destination" => db_output2($row['vDestination']),
                "rank" => intval($row['iRank']),
                "status" => db_output2($row['cStatus'])
            ];

            // For dropdown options
            $routesOpt[] = [
                "id" => (int) $row['iRouteID'],
                "name" => db_output2($row['vName'])
            ];
        }

        echo json_encode([
            "statusCode" => 200,

            "data" => [
                "message" => "Route list fetched successfully",
                "rowData" => $rowData,
                "routesOpt" => $routesOpt
            ]
        ]);
        break;

    // ===================== CASE: ADD_ROUTE =====================
    case 'ADD_ROUTE':
        $routeInfo = $_REQUEST['routeInfo'] ?? [];

        $route = db_input($routeInfo['route'] ?? '');
        $dest = db_input($routeInfo['dest'] ?? '');
        $rdpList = $routeInfo['rdp'] ?? [];
        // Check if user has approval rights
        if (checkUserModuleAccess($user_id, 'STAFF_ROUTE_APPROVE')) {
            $cStatus = 'A'; // approved
        } else {
            $cStatus = 'D'; // draft
        }

        // Basic validation
        if (empty($route) || empty($dest)) {
            echo json_encode([
                "error" => [
                    "message" => "Route name and destination are required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Generate next route ID
        $iRouteID = NextID('iRouteID', 'st_route');

        // Insert main route
        $sql = "INSERT INTO st_route (iRouteID, vName, vDestination, cStatus)
            VALUES ($iRouteID, '" . db_input($route) . "', '" . db_input($dest) . "', '" . db_input($cStatus) . "')";

        if (sql_query($sql)) {

            // Insert stops if provided
            if (is_array($rdpList) && !empty($rdpList)) {

                foreach ($rdpList as $rdp) {
                    $pickupPt = db_input($rdp['pickupPt'] ?? '');
                    $durationRaw = trim($rdp['duration'] ?? '');

                    if (!empty($pickupPt) && !empty($durationRaw)) {
                        // Convert duration like "00:05" → 5 minutes, "01:00" → 60 minutes
                        $minutes = 0;
                        if (strpos($durationRaw, ':') !== false) {
                            $durationParts = explode(':', $durationRaw);
                            $hours = isset($durationParts[0]) ? intval($durationParts[0]) : 0;
                            $mins = isset($durationParts[1]) ? intval($durationParts[1]) : 0;
                            $minutes = ($hours * 60) + $mins;
                        } else {
                            $minutes = intval($durationRaw);
                        }

                        $iStopID = NextID('iStopID', 'st_route_stops');
                        $iRank = GetMaxRank('st_route_stops', "iRouteID=$iRouteID and cStatus='A'", 'iRank');

                        $stopSql = "INSERT INTO st_route_stops 
                        (iStopID, iRouteID, vName, tOffsetFromStart, iRank, cStatus)
                        VALUES ($iStopID, $iRouteID, '" . db_input($pickupPt) . "', $minutes, $iRank, 'A')";
                        sql_query($stopSql);


                    }
                }
            }

            // Log the add operation (similar to vehicle.php)
            LogMasterEdit($iRouteID, 'RTE', 'I', $route, '', $user_id);

            // If status is 'D' (draft), also log to st_route_log table
            if ($cStatus == 'D') {
                $iRLogID = NextID('iRLogID', 'st_route_log');
                $logSql = "INSERT INTO st_route_log (iRLogID, iRouteID, iAddedBy, dtAdded, cStatus) 
                          VALUES ($iRLogID, $iRouteID, $user_id, '$NOW', 'D')";
                sql_query($logSql);
            }

            echo json_encode([
                "data" => [
                    "message" => "Route added successfully",
                    "iRouteID" => $iRouteID
                ],
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to add route"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE: ROUTE_DETAILS =====================
    case 'ROUTE_DETAILS':
        $id = isset($_REQUEST['iRouteID']) ? intval($_REQUEST['iRouteID']) : 0;
        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Invalid Route ID"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Get route details
        $sql = "SELECT iRouteID, vName, vDestination, cStatus FROM st_route WHERE iRouteID = $id";
        $res = sql_query($sql);

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Route not found"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $row = sql_fetch_assoc($res);

        // Get route stops
        $stopsSql = "SELECT iStopID, vName, tOffsetFromStart, iRank FROM st_route_stops 
                 WHERE iRouteID = $id AND cStatus = 'A' ORDER BY iRank";
        $stopsRes = sql_query($stopsSql);

        $rdpList = [];
        while ($stopRow = sql_fetch_assoc($stopsRes)) {
            $totalMinutes = intval($stopRow['tOffsetFromStart']);
            $hours = intval($totalMinutes / 60);
            $mins = $totalMinutes % 60;
            $durationFormatted = sprintf("%02d:%02d", $hours, $mins);

            $rdpList[] = [
                'iStopID' => intval($stopRow['iStopID']),
                'pickupPt' => db_output2($stopRow['vName']),
                'duration' => $durationFormatted, // Convert back to HH:MM format
                'iRank' => intval($stopRow['iRank'])
            ];
        }

        echo json_encode([
            "statusCode" => 200,

            "data" => [
                'routeData' => [
                    'iRouteID' => intval($row['iRouteID']),
                    'route' => db_output2($row['vName'] ?? ''),
                    'dest' => db_output2($row['vDestination'] ?? ''),
                    'rdp' => $rdpList,
                    'status' => $row['cStatus'] ?? 'D',
                    "message" => "Route details fetched successfully"
                ]
            ]
        ]);
        break;

    // ===================== CASE: UPDATE_ROUTE =====================
    case 'UPDATE_ROUTE':
        $id = intval($_REQUEST['iRouteID'] ?? 0);
        $routeInfo = $_REQUEST['routeInfo'] ?? [];

        $route = db_input($routeInfo['route'] ?? '');
        $dest = db_input($routeInfo['dest'] ?? '');
        $rdpList = $routeInfo['rdp'] ?? [];

        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Route ID is required for update"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Basic validation
        if (empty($route) || empty($dest)) {
            echo json_encode([
                "error" => [
                    "message" => "Route name and destination are required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Check if user has approval rights - if not, set status to 'D'
        $updateStatus = '';
        if (!checkUserModuleAccess($user_id, 'STAFF_ROUTE_APPROVE')) {
            $updateStatus = ", cStatus = 'D'";
        }

        // Update main route
        $sql = "UPDATE st_route SET vName = '" . db_input($route) . "', vDestination = '" . db_input($dest) . "' $updateStatus
            WHERE iRouteID = $id";

        $result = sql_query($sql);

        if ($result) {
            // Get existing stops for this route
            $existingStopsSql = "SELECT iStopID FROM st_route_stops WHERE iRouteID = $id AND cStatus = 'A'";
            $existingStopsRes = sql_query($existingStopsSql);
            $existingStopIDs = [];
            while ($existingRow = sql_fetch_assoc($existingStopsRes)) {
                $existingStopIDs[] = intval($existingRow['iStopID']);
            }

            $processedStopIDs = [];

            // Process stops if provided
            if (is_array($rdpList) && !empty($rdpList)) {
                foreach ($rdpList as $rdp) {
                    $pickupPt = db_input($rdp['pickupPt'] ?? '');
                    $durationRaw = trim($rdp['duration'] ?? '');
                    $iStopID = intval($rdp['iStopID'] ?? 0);

                    if (!empty($pickupPt) && !empty($durationRaw)) {
                        // Convert duration like "00:05" → 5 minutes, "01:00" → 60 minutes
                        $minutes = 0;
                        if (strpos($durationRaw, ':') !== false) {
                            $durationParts = explode(':', $durationRaw);
                            $hours = isset($durationParts[0]) ? intval($durationParts[0]) : 0;
                            $mins = isset($durationParts[1]) ? intval($durationParts[1]) : 0;
                            $minutes = ($hours * 60) + $mins;
                        } else {
                            $minutes = intval($durationRaw);
                        }

                        if ($iStopID > 0 && in_array($iStopID, $existingStopIDs)) {
                            // Update existing stop
                            $stopSql = "UPDATE st_route_stops SET 
                                    vName = '" . db_input($pickupPt) . "', 
                                    tOffsetFromStart = $minutes 
                                    WHERE iStopID = $iStopID AND iRouteID = $id";
                            sql_query($stopSql);
                            $processedStopIDs[] = $iStopID;
                        } else {
                            // Insert new stop
                            $newStopID = NextID('iStopID', 'st_route_stops');
                            $iRank = GetMaxRank('st_route_stops', "iRouteID=$id and cStatus='A'", 'iRank');

                            $stopSql = "INSERT INTO st_route_stops 
                            (iStopID, iRouteID, vName, tOffsetFromStart, iRank, cStatus)
                            VALUES ($newStopID, $id, '" . db_input($pickupPt) . "', $minutes, $iRank, 'A')";
                            sql_query($stopSql);
                            $processedStopIDs[] = $newStopID;
                        }
                    }
                }
            }

            // Soft delete stops that were not processed (removed from the list)
            $stopsToDelete = array_diff($existingStopIDs, $processedStopIDs);
            if (!empty($stopsToDelete)) {
                $deleteStopIDs = implode(',', $stopsToDelete);
                $deleteStopsSql = "UPDATE st_route_stops SET cStatus = 'X' WHERE iStopID IN ($deleteStopIDs)";
                sql_query($deleteStopsSql);
            }

            // Log the update operation (similar to vehicle.php)
            LogMasterEdit($id, 'RTE', 'U', $route, '', $user_id);

            // If user doesn't have approval rights, log to st_route_log table
            if (!checkUserModuleAccess($user_id, 'STAFF_ROUTE_APPROVE')) {
                $iRLogID = NextID('iRLogID', 'st_route_log');
                $logSql = "INSERT INTO st_route_log (iRLogID, iRouteID, iAddedBy, dtAdded, cStatus) 
                          VALUES ($iRLogID, $id, $user_id, '$NOW', 'D')";
                sql_query($logSql);
            }

            echo json_encode([
                "data" => [
                    "message" => "Route updated successfully",
                    "iRouteID" => $id
                ],
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to update route"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE: DELETE_ROUTE =====================
    case 'DELETE_ROUTE':
        $id = intval($_REQUEST['iRouteID'] ?? 0);

        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Route ID is required for deletion"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Update cStatus to 'X' instead of actual deletion
        $sql = "UPDATE st_route SET cStatus = 'X' WHERE iRouteID = $id AND cStatus != 'X'";
        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            // Also mark route stops as deleted
            $stopsSql = "UPDATE st_route_stops SET cStatus = 'X' WHERE iRouteID = $id";
            sql_query($stopsSql);

            // Log the delete operation
            LogMasterEdit($id, 'RTE', 'D', '', '', $user_id);

            echo json_encode([
                "statusCode" => 200,
                "data" => [
                    "message" => "Route deleted successfully"
                ]
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            echo json_encode([
                "statusCode" => 200,
                "data" => [
                    "message" => "Route not found or already deleted"
                ]
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to delete route"
                ],
                "statusCode" => 500
            ]);
        }
        break;
    // ===================== CASE: RANK_ROUTE =====================
    case 'RANK_ROUTE':

        $routeOrder = $_REQUEST['routeOrder'] ?? [];

        if (!is_array($routeOrder) || empty($routeOrder)) {
            echo json_encode([
                "error" => [
                    "message" => "routeOrder must be a non-empty array"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $rank = 1;

        foreach ($routeOrder as $iRouteID) {
            $iRouteID = intval($iRouteID);

            // Skip only invalid zero/negative values
            if ($iRouteID <= 0)
                continue;

            $sql = "UPDATE st_route 
                SET iRank = $rank
                WHERE iRouteID = $iRouteID";

            sql_query($sql);

            // Log update
            LogMasterEdit($iRouteID, 'RTE', 'U', "Rank updated to $rank", '', $user_id);

            $rank++;
        }

        echo json_encode([
            "data" => [
                "message" => "Route ranks updated successfully",
                "updatedCount" => count($routeOrder)
            ],
            "statusCode" => 200
        ]);
        break;
    case 'APPROVE_ROUTE':
        $iRouteID = intval($_REQUEST['iRouteID'] ?? 0);
        
        if ($iRouteID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Route ID is required for approval"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if (checkUserModuleAccess($user_id, 'STAFF_ROUTE_APPROVE')) {
            // User has access - update route status to 'A' (approved)
            $sql = "UPDATE st_route SET cStatus = 'A' WHERE iRouteID = $iRouteID";
            $result = sql_query($sql);

            if ($result && sql_affected_rows() > 0) {
                // Update st_route_log table with approval details
                $updateLogSql = "UPDATE st_route_log SET 
                                iApprovedBy = $user_id, 
                                dtApproved = '$NOW', 
                                cStatus = 'A' 
                                WHERE iRouteID = $iRouteID AND cStatus = 'D'";
                sql_query($updateLogSql);

                // Log the approval operation
                LogMasterEdit($iRouteID, 'RTE', 'U', 'Route approved', '', $user_id);

                echo json_encode([
                    "data" => [
                        "message" => "Route approved successfully",
                        "iRouteID" => $iRouteID
                    ],
                    "statusCode" => 200
                ]);
            } else if ($result && sql_affected_rows() == 0) {
                echo json_encode([
                    "data" => [
                        "message" => "Route not found or already approved"
                    ],
                    "statusCode" => 200
                ]);
            } else {
                echo json_encode([
                    "error" => [
                        "message" => "Failed to approve route"
                    ],
                    "statusCode" => 500
                ]);
            }
        } else {
            // User does not have access
            echo json_encode([
                "error" => [
                    "message" => "No access - You don't have permission to approve routes"
                ],
                "statusCode" => 403
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