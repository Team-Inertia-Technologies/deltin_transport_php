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
        // Get all active and inactive staff with route and stop details
        $sql = "SELECT 
                    s.iStaffID,
                    s.vCode,
                    s.vName,
                    s.vMobile,
                    s.iRouteID,
                    s.iStopID,
                    s.cStatus,
                    r.vName as routeName,
                    st.vName as stopName
                FROM staff s
                LEFT JOIN st_route r ON s.iRouteID = r.iRouteID AND r.cStatus = 'A'
                LEFT JOIN st_route_stops st ON s.iStopID = st.iStopID AND st.cStatus = 'A'
                WHERE s.cStatus IN ('A', 'I')
                ORDER BY s.dtRegistered DESC";
        
        $res = sql_query($sql);
        $staffList = [];
        
        while ($row = sql_fetch_assoc($res)) {
            $staffList[] = [
                'id' => (int) $row['iStaffID'],
                'code' => db_output2($row['vCode']),
                'name' => db_output2($row['vName']),
                'mobile' => db_output2($row['vMobile']),
                'routeId' => (int) ($row['iRouteID'] ?? 0),
                'routeName' => db_output2($row['routeName'] ?? ''),
                'stopId' => (int) ($row['iStopID'] ?? 0),
                'stopName' => db_output2($row['stopName'] ?? ''),
                'status' => $row['cStatus']
            ];
        }
        
        // Get routes and stops for dropdowns
        $routeStopsQuery = "SELECT r.iRouteID, r.vName as routeName, s.iStopID, s.vName as stopName 
                            FROM st_route r 
                            LEFT JOIN st_route_stops s ON r.iRouteID = s.iRouteID 
                            WHERE r.cStatus = 'A' AND s.cStatus = 'A'
                            ORDER BY r.iRouteID, s.iStopID";
        $routeStopsResult = sql_query($routeStopsQuery);
        
        $routes = [];
        $currentRouteId = null;
        $currentRoute = null;
        
        while ($row = sql_fetch_assoc($routeStopsResult)) {
            if ($currentRouteId !== $row['iRouteID']) {
                if ($currentRoute !== null) {
                    $routes[] = $currentRoute;
                }
                
                $currentRouteId = $row['iRouteID'];
                $currentRoute = [
                    "id" => (int) $row['iRouteID'],
                    "name" => db_output2($row['routeName']),
                    "stops" => []
                ];
            }
            
            if ($row['iStopID'] !== null) {
                $currentRoute["stops"][] = [
                    "id" => (int) $row['iStopID'],
                    "name" => db_output2($row['stopName'])
                ];
            }
        }
        
        if ($currentRoute !== null) {
            $routes[] = $currentRoute;
        }
        
        echo json_encode([
            "data" => [
                "staff" => $staffList,
                "routes" => $routes
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE 2: ADD =====================
    case 'ADD':
        $vCode = db_input($_REQUEST['code'] ?? '');
        $vName = db_input($_REQUEST['name'] ?? '');
        $vMobile = db_input($_REQUEST['mobile'] ?? '');
        $iRouteID = intval($_REQUEST['routeId'] ?? 0);
        $iStopID = intval($_REQUEST['stopId'] ?? 0);
        
        // Validate required fields
        if (empty($vCode)) {
            echo json_encode([
                "error" => [
                    "message" => "Staff code is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        if (empty($vName)) {
            echo json_encode([
                "error" => [
                    "message" => "Staff name is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        if (empty($vMobile)) {
            echo json_encode([
                "error" => [
                    "message" => "Mobile number is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        // Validate mobile number format
        if (!preg_match('/^[0-9]{10}$/', $vMobile)) {
            echo json_encode([
                "error" => [
                    "message" => "Invalid mobile number format"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        // Check for duplicate vCode or vMobile
        $checkSql = "SELECT iStaffID, vCode, vMobile FROM staff WHERE (vCode = '$vCode' OR vMobile = '$vMobile') AND cStatus != 'X'";
        $checkRes = sql_query($checkSql);
        
        if (sql_num_rows($checkRes) > 0) {
            $existingRow = sql_fetch_assoc($checkRes);
            if ($existingRow['vCode'] === $vCode) {
                echo json_encode([
                    "error" => [
                        "message" => "Staff code already exists"
                    ],
                    "statusCode" => 409
                ]);
                exit;
            }
            if ($existingRow['vMobile'] === $vMobile) {
                echo json_encode([
                    "error" => [
                        "message" => "Mobile number already exists"
                    ],
                    "statusCode" => 409
                ]);
                exit;
            }
        }
        
        // Create new staff record
        $iStaffID = NextID('iStaffID', 'staff');
        $cStatus = 'A';
        $dtRegistered = NOW;
        
        $sql = "INSERT INTO staff (iStaffID, vCode, vName, vMobile, iRouteID, iStopID, dtRegistered, cStatus) 
                VALUES ($iStaffID, '$vCode', '$vName', '$vMobile', $iRouteID, $iStopID, '$dtRegistered', '$cStatus')";
        
        if (sql_query($sql)) {
            echo json_encode([
                "data" => [
                    "id" => $iStaffID,
                    "message" => "Staff member added successfully"
                ],
                "statusCode" => 201
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to add staff member"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE 3: EDIT (Get single staff details) =====================
    case 'EDIT':
        $iStaffID = intval($_REQUEST['id'] ?? 0);
        
        if (empty($iStaffID)) {
            echo json_encode([
                "error" => [
                    "message" => "Staff ID is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        $sql = "SELECT 
                    s.iStaffID,
                    s.vCode,
                    s.vName,
                    s.vMobile,
                    s.iRouteID,
                    s.iStopID,
                    s.cStatus,
                    r.vName as routeName,
                    st.vName as stopName
                FROM staff s
                LEFT JOIN st_route r ON s.iRouteID = r.iRouteID AND r.cStatus = 'A'
                LEFT JOIN st_route_stops st ON s.iStopID = st.iStopID AND st.cStatus = 'A'
                WHERE s.iStaffID = $iStaffID AND s.cStatus != 'X'";
        
        $res = sql_query($sql);
        
        if (sql_num_rows($res) > 0) {
            $row = sql_fetch_assoc($res);
            
            // Get available routes and stops for dropdown
            $routeStopsQuery = "SELECT r.iRouteID, r.vName as routeName, s.iStopID, s.vName as stopName 
                                FROM st_route r 
                                LEFT JOIN st_route_stops s ON r.iRouteID = s.iRouteID 
                                WHERE r.cStatus = 'A' AND s.cStatus = 'A'
                                ORDER BY r.iRouteID, s.iStopID";
            $routeStopsResult = sql_query($routeStopsQuery);
            
            $routes = [];
            $currentRouteId = null;
            $currentRoute = null;
            
            while ($routeRow = sql_fetch_assoc($routeStopsResult)) {
                if ($currentRouteId !== $routeRow['iRouteID']) {
                    if ($currentRoute !== null) {
                        $routes[] = $currentRoute;
                    }
                    
                    $currentRouteId = $routeRow['iRouteID'];
                    $currentRoute = [
                        "id" => (int) $routeRow['iRouteID'],
                        "name" => db_output2($routeRow['routeName']),
                        "stops" => []
                    ];
                }
                
                if ($routeRow['iStopID'] !== null) {
                    $currentRoute["stops"][] = [
                        "id" => (int) $routeRow['iStopID'],
                        "name" => db_output2($routeRow['stopName'])
                    ];
                }
            }
            
            if ($currentRoute !== null) {
                $routes[] = $currentRoute;
            }
            
            $staffData = [
                'id' => (int) $row['iStaffID'],
                'code' => db_output2($row['vCode']),
                'name' => db_output2($row['vName']),
                'mobile' => db_output2($row['vMobile']),
                'routeId' => (int) ($row['iRouteID'] ?? 0),
                'routeName' => db_output2($row['routeName'] ?? ''),
                'stopId' => (int) ($row['iStopID'] ?? 0),
                'stopName' => db_output2($row['stopName'] ?? ''),
                'status' => $row['cStatus']
            ];
            
            echo json_encode([
                "data" => [
                    "staff" => $staffData,
                    "routes" => $routes
                ],
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Staff member not found"
                ],
                "statusCode" => 404
            ]);
        }
        break;

    // ===================== CASE 4: UPDATE =====================
    case 'UPDATE':
        $iStaffID = intval($_REQUEST['id'] ?? 0);
        $vCode = db_input($_REQUEST['code'] ?? '');
        $vName = db_input($_REQUEST['name'] ?? '');
        $vMobile = db_input($_REQUEST['mobile'] ?? '');
        $iRouteID = intval($_REQUEST['routeId'] ?? 0);
        $iStopID = intval($_REQUEST['stopId'] ?? 0);
        
        if (empty($iStaffID)) {
            echo json_encode([
                "error" => [
                    "message" => "Staff ID is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        // Validate required fields
        if (empty($vCode)) {
            echo json_encode([
                "error" => [
                    "message" => "Staff code is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        if (empty($vName)) {
            echo json_encode([
                "error" => [
                    "message" => "Staff name is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        if (empty($vMobile)) {
            echo json_encode([
                "error" => [
                    "message" => "Mobile number is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        // Validate mobile number format
        if (!preg_match('/^[0-9]{10}$/', $vMobile)) {
            echo json_encode([
                "error" => [
                    "message" => "Invalid mobile number format"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        // Check if staff exists
        $existsSql = "SELECT iStaffID FROM staff WHERE iStaffID = $iStaffID AND cStatus != 'X'";
        $existsRes = sql_query($existsSql);
        
        if (sql_num_rows($existsRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Staff member not found"
                ],
                "statusCode" => 404
            ]);
            exit;
        }
        
        // Check for duplicate vCode or vMobile (excluding current staff)
        $checkSql = "SELECT iStaffID, vCode, vMobile FROM staff WHERE (vCode = '$vCode' OR vMobile = '$vMobile') AND iStaffID != $iStaffID AND cStatus != 'X'";
        $checkRes = sql_query($checkSql);
        
        if (sql_num_rows($checkRes) > 0) {
            $existingRow = sql_fetch_assoc($checkRes);
            if ($existingRow['vCode'] === $vCode) {
                echo json_encode([
                    "error" => [
                        "message" => "Staff code already exists"
                    ],
                    "statusCode" => 409
                ]);
                exit;
            }
            if ($existingRow['vMobile'] === $vMobile) {
                echo json_encode([
                    "error" => [
                        "message" => "Mobile number already exists"
                    ],
                    "statusCode" => 409
                ]);
                exit;
            }
        }
        
        // Update staff record
        $sql = "UPDATE staff SET 
                    vCode = '$vCode',
                    vName = '$vName',
                    vMobile = '$vMobile',
                    iRouteID = $iRouteID,
                    iStopID = $iStopID
                WHERE iStaffID = $iStaffID";
        
        if (sql_query($sql)) {
            echo json_encode([
                "data" => [
                    "message" => "Staff member updated successfully"
                ],
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to update staff member"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE 5: DELETE =====================
    case 'DELETE':
        $iStaffID = intval($_REQUEST['id'] ?? 0);
        
        if (empty($iStaffID)) {
            echo json_encode([
                "error" => [
                    "message" => "Staff ID is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }
        
        // Check if staff exists
        $existsSql = "SELECT iStaffID FROM staff WHERE iStaffID = $iStaffID AND cStatus != 'X'";
        $existsRes = sql_query($existsSql);
        
        if (sql_num_rows($existsRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Staff member not found"
                ],
                "statusCode" => 404
            ]);
            exit;
        }
        
        // Soft delete - mark as deleted
        $sql = "UPDATE staff SET cStatus = 'X' WHERE iStaffID = $iStaffID";
        
        if (sql_query($sql)) {
            echo json_encode([
                "data" => [
                    "message" => "Staff member deleted successfully"
                ],
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to delete staff member"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE 6: ONLOAD =====================
    case 'ONLOAD':
        $routeStopsQuery = "SELECT r.iRouteID, r.vName as routeName, s.iStopID, s.vName as stopName 
                            FROM st_route r 
                            LEFT JOIN st_route_stops s ON r.iRouteID = s.iRouteID 
                            WHERE r.cStatus = 'A' AND s.cStatus = 'A'
                            ORDER BY r.iRouteID, s.iStopID";
        $routeStopsResult = sql_query($routeStopsQuery);
        
        $routes = [];
        $currentRouteId = null;
        $currentRoute = null;
        
        while ($row = sql_fetch_assoc($routeStopsResult)) {
            if ($currentRouteId !== $row['iRouteID']) {
                if ($currentRoute !== null) {
                    $routes[] = $currentRoute;
                }
                
                $currentRouteId = $row['iRouteID'];
                $currentRoute = [
                    "id" => (int) $row['iRouteID'],
                    "name" => db_output2($row['routeName']),
                    "stops" => []
                ];
            }
            
            if ($row['iStopID'] !== null) {
                $currentRoute["stops"][] = [
                    "id" => (int) $row['iStopID'],
                    "name" => db_output2($row['stopName'])
                ];
            }
        }
        
        if ($currentRoute !== null) {
            $routes[] = $currentRoute;
        }
        
        echo json_encode([
            "data" => [
                "routes" => $routes
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE 7: TOGGLE_STATUS =====================
    case 'TOGGLE_STATUS':
        $id = intval($_REQUEST['iStaffID'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Staff ID is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Check current status
        $currentSql = "SELECT cStatus FROM staff WHERE iStaffID = $id AND cStatus IN ('A', 'I')";
        $currentRes = sql_query($currentSql);
        
        if (sql_num_rows($currentRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Staff member not found"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        // Use the reusable toggle function
        $result = toggleStatus($id, 'staff', 'iStaffID', 'cStatus', 'vName', 'STF', $user_id);
        echo json_encode($result);
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