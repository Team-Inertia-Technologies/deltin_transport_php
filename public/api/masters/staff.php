<?php
ini_set('display_errors', 1);

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

        $sql = "SELECT 
                    s.iStaffID,
                    s.vCode,
                    s.vName,
                    s.vMobile,
                    s.iRouteID,
                    s.iStopID,
                    s.iDepartmentID,
                    s.iPropertyID,
                    s.cStatus,
                    s.dtLastLogin,
                    r.vName as routeName,
                    st.vName as stopName,
                    d.vName as departmentName,
                    p.vName as propertyName
                FROM staff s
                LEFT JOIN st_route r ON s.iRouteID = r.iRouteID AND r.cStatus = 'A'
                LEFT JOIN st_route_stops st ON s.iStopID = st.iStopID AND st.cStatus = 'A'
                LEFT JOIN department d ON s.iDepartmentID = d.iDepartmentID AND d.cStatus = 'A'
                LEFT JOIN property p ON s.iPropertyID = p.iPropertyID AND p.cStatus = 'A'
                WHERE s.cStatus IN ('A', 'I','P')
                ORDER BY CASE WHEN s.cStatus = 'P' THEN 0 ELSE 1 END ASC, s.dtRegistered DESC";

        $res = sql_query($sql);
        $staffList = [];


        while ($row = sql_fetch_assoc($res)) {

            $staffList[] = [
                'id' => (int) $row['iStaffID'],
                'code' => db_output2($row['vCode']),
                'name' => db_output2($row['vName']),
                'mobile' => $row['vMobile'],
                'mobile_mask' => maskMobileNumber($row['vMobile']),
                'routeId' => (int) ($row['iRouteID'] ?? 0),
                'routeName' => db_output2($row['routeName'] ?? ''),
                'stopId' => (int) ($row['iStopID'] ?? 0),
                'stopName' => db_output2($row['stopName'] ?? ''),
                'departmentId' => (int) ($row['iDepartmentID'] ?? 0),
                'departmentName' => db_output2($row['departmentName'] ?? ''),
                'propertyId' => (int) ($row['iPropertyID'] ?? 0),
                'propertyName' => db_output2($row['propertyName'] ?? ''),
                'loginDate' => isset($row['dtLastLogin']) ? date('d-m-Y', strtotime($row['dtLastLogin'])) : '',
                'loginTime' => isset($row['dtLastLogin']) ? date('h:i A', strtotime($row['dtLastLogin'])) : '',
                'status' => $row['cStatus']
            ];
        }

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

        // Get departments for dropdown
        $departmentQuery = "SELECT iDepartmentID, vName FROM department WHERE cStatus = 'A' ORDER BY vName";
        $departmentResult = sql_query($departmentQuery);
        $departments = [];

        while ($row = sql_fetch_assoc($departmentResult)) {
            $departments[] = [
                "id" => (int) $row['iDepartmentID'],
                "name" => db_output2($row['vName'])
            ];
        }

        $propertyQuery = "SELECT iPropertyID , vName FROM property WHERE cStatus = 'A' ORDER BY iRank";
        $propertytResult = sql_query($propertyQuery);
        $properties = [];

        while ($row = sql_fetch_assoc($propertytResult)) {
            $properties[] = [
                "id" => (int) $row['iPropertyID'],
                "name" => db_output2($row['vName'])
            ];
        }


        echo json_encode([
            "data" => [
                "staff" => $staffList,
                "routes" => $routes,
                "departments" => $departments,
                "properties" => $properties
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
        $iDepartmentID = intval($_REQUEST['departmentId'] ?? 0);
        $iPropertyID = intval($_REQUEST['propertyId'] ?? 0);

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

        $sql = "INSERT INTO staff (iStaffID, vCode, vName, vMobile, iRouteID, iStopID, iDepartmentID,iPropertyID, dtRegistered, cStatus) 
                VALUES ($iStaffID, '$vCode', '$vName', '$vMobile', $iRouteID, $iStopID, $iDepartmentID, $iPropertyID, '$dtRegistered', '$cStatus')";

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
                    s.iDepartmentID,
                    s.iPropertyID,
                    s.cStatus,
                    r.vName as routeName,
                    st.vName as stopName,
                    d.vName as departmentName
                FROM staff s
                LEFT JOIN st_route r ON s.iRouteID = r.iRouteID AND r.cStatus = 'A'
                LEFT JOIN st_route_stops st ON s.iStopID = st.iStopID AND st.cStatus = 'A'
                LEFT JOIN department d ON s.iDepartmentID = d.iDepartmentID AND d.cStatus = 'A'
                 LEFT JOIN property p ON s.iPropertyID = p.iPropertyID AND p.cStatus = 'A'
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

            // Get departments for dropdown
            $departmentQuery = "SELECT iDepartmentID, vName FROM department WHERE cStatus = 'A' ORDER BY vName";
            $departmentResult = sql_query($departmentQuery);
            $departments = [];

            while ($deptRow = sql_fetch_assoc($departmentResult)) {
                $departments[] = [
                    "id" => (int) $deptRow['iDepartmentID'],
                    "name" => db_output2($deptRow['vName'])
                ];
            }
            $propertyQuery = "SELECT iPropertyID , vName FROM property WHERE cStatus = 'A' ORDER BY iRank";
            $propertytResult = sql_query($propertyQuery);
            $properties = [];

            while ($row = sql_fetch_assoc($propertytResult)) {
                $properties[] = [
                    "id" => (int) $row['iPropertyID'],
                    "name" => db_output2($row['vName'])
                ];
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
                'departmentId' => (int) ($row['iDepartmentID'] ?? 0),
                'departmentName' => db_output2($row['departmentName'] ?? ''),
                'propertyId' => (int) ($row['iPropertyID'] ?? 0),
                'status' => $row['cStatus']
            ];

            echo json_encode([
                "data" => [
                    "staff" => $staffData,
                    "routes" => $routes,
                    "departments" => $departments,
                    "properties" => $properties
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
    case 'UPDATE_STAFF':
        $iStaffID = intval($_REQUEST['id'] ?? 0);
        $vCode = db_input($_REQUEST['code'] ?? '');
        $vName = db_input($_REQUEST['name'] ?? '');
        $vMobile = db_input($_REQUEST['mobile'] ?? '');
        $iRouteID = intval($_REQUEST['routeId'] ?? 0);
        $iStopID = intval($_REQUEST['stopId'] ?? 0);
        $iDepartmentID = intval($_REQUEST['departmentId'] ?? 0);
        $iPropertyID = intval($_REQUEST['propertyId'] ?? 0);

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
                    iStopID = $iStopID,
                    iDepartmentID = $iDepartmentID,
                    iPropertyID= $iPropertyID
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
    case 'DELETE_STAFF':
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

        // Get departments for dropdown
        $departmentQuery = "SELECT iDepartmentID, vName FROM department WHERE cStatus = 'A' ORDER BY vName";
        $departmentResult = sql_query($departmentQuery);
        $departments = [];

        while ($row = sql_fetch_assoc($departmentResult)) {
            $departments[] = [
                "id" => (int) $row['iDepartmentID'],
                "name" => db_output2($row['vName'])
            ];
        }
        $propertyQuery = "SELECT iPropertyID , vName FROM property WHERE cStatus = 'A' ORDER BY iRank";
        $propertytResult = sql_query($propertyQuery);
        $properties = [];

        while ($row = sql_fetch_assoc($propertytResult)) {
            $properties[] = [
                "id" => (int) $row['iPropertyID'],
                "name" => db_output2($row['vName'])
            ];
        }

        echo json_encode([
            "data" => [
                "routes" => $routes,
                "departments" => $departments,
                "properties" => $properties
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE 7: VIEW_STAFF =====================
    case 'STAFF_VIEW':
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

        // Get filter parameters
        $statusFilter = isset($_REQUEST['statusFilter']) ? $_REQUEST['statusFilter'] : 'A'; // A=All, E=Entered, NE=Not Entered
        $dateFrom = isset($_REQUEST['dateFrom']) ? $_REQUEST['dateFrom'] : ''; // From date (YYYY-MM-DD)
        $dateTo = isset($_REQUEST['dateTo']) ? $_REQUEST['dateTo'] : ''; // To date (YYYY-MM-DD)

        // Build WHERE conditions
        $whereConditions = ["r.iStaffID = $iStaffID", "r.cStatus = 'A'"];

        // Add status filter condition
        if ($statusFilter === 'E') {
            // Entered - has dtIn (entered time)
            $whereConditions[] = "r.dtIn IS NOT NULL";
        } elseif ($statusFilter === 'NE') {
            // Not Entered - no dtIn (entered time)
            $whereConditions[] = "r.dtIn IS NULL";
        }
        // For 'A' (All), no additional condition needed

        // Add date range filter conditions (only if provided)
        if (!empty($dateFrom) && !empty($dateTo)) {
            // Both from and to dates provided
            $whereConditions[] = "r.dPickup BETWEEN '$dateFrom' AND '$dateTo'";
        } elseif (!empty($dateFrom)) {
            // Only from date provided
            $whereConditions[] = "r.dPickup >= '$dateFrom'";
        } elseif (!empty($dateTo)) {
            // Only to date provided
            $whereConditions[] = "r.dPickup <= '$dateTo'";
        }
        // If no date filters provided, show all records (no additional date condition)

        $overviewSql = "
        SELECT 
            r.iTrReqID,
            r.iStaffID AS staffid,
            r.dPickup AS date,
            r.cStatus AS status,
            rt.vName AS route,
            rs.vName AS pickup,
            r.tPickup AS pickupTime,
            r.dtIn AS enteredTime,
            v.vRnum AS vehiNum,
            CASE 
                WHEN CONCAT(r.dPickup, ' ', r.tPickup) >= '$NOW' THEN 'UPCOMING'
                ELSE 'PAST'
            END AS sendStatus
        FROM st_request r
        INNER JOIN st_route rt ON r.iRouteID = rt.iRouteID
        INNER JOIN st_route_stops rs ON r.iStopID = rs.iStopID
        LEFT JOIN vehicle v ON r.iVehicleID = v.iVehicleID
        WHERE " . implode(' AND ', $whereConditions) . "
        ORDER BY sendStatus DESC, r.iTrReqID DESC
    ";

        $overviewRes = sql_query($overviewSql);
        //$overviewResCount= sql_num_rows($overviewRes);
        $rowData = [];
        while ($row = sql_fetch_assoc($overviewRes)) {
            $rowItem = [
                "requestId" => (int) $row['iTrReqID'],
                "staffid" => (int) $row['staffid'],
                "date" => date('j M Y', strtotime($row['date'])),
                //"status"      => db_output2($row['status']),
                "route" => db_output2($row['route']),
                "pickup" => db_output2($row['pickup']),
                "pickupTime" => date('H:i', strtotime($row['pickupTime'])),
                "enteredTime" => $row['enteredTime'] ? date('H:i', strtotime($row['enteredTime'])) : "",
                "status" => $row['sendStatus'],
                "vehiNum" => db_output2($row['vehiNum'])
            ];

            // // Only include vehicle number if it exists and is not empty
            // if (!empty($row['vehiNum'])) {
            //     $rowItem["vehiNum"] = db_output2($row['vehiNum']);
            // }

            $rowData[] = $rowItem;
        }

        echo json_encode([
            "data" => [
                "rowData" => $rowData
            ],
            // "count" => $overviewResCount,
            "statusCode" => 200
        ]);

        exit;

    // ===================== CASE 8: TOGGLE_STATUS =====================
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

        $result = toggleStatus($id, 'staff', 'iStaffID', 'cStatus', 'vName', 'STF', $user_id);
        echo json_encode($result);
        break;
    // ===================== CASE 9: IMPORT_STAFF =====================
    case 'IMPORT_STAFF':

        $rows = $_REQUEST['staffData'] ?? [];
        $inserted = [];
        $updated = [];
        $skipped = [];


        // STEP 1: Prepare Lookups


        $departmentMap = [];
        $departmentQuery = "SELECT iDepartmentID, vName FROM department WHERE cStatus = 'A'";
        $departmentResult = sql_query($departmentQuery);

        while ($deptRow = sql_fetch_assoc($departmentResult)) {
            $departmentMap[strtolower(trim($deptRow['vName']))] = (int) $deptRow['iDepartmentID'];
        }

        $propertyMap = [];
        $propertyQuery = "SELECT iPropertyID, vName FROM property WHERE cStatus = 'A'";
        $propertyResult = sql_query($propertyQuery);

        while ($propRow = sql_fetch_assoc($propertyResult)) {
            $propertyMap[strtolower(trim($propRow['vName']))] = (int) $propRow['iPropertyID'];
        }


        // STEP 2: VALIDATION PASS


        $validatedRows = [];

        foreach ($rows as $index => $row) {

            $vCode = isset($row['code']) ? db_input($row['code']) : '';
            $vName = isset($row['name']) ? db_input($row['name']) : '';
            $vMobile = isset($row['mobile']) ? db_input($row['mobile']) : '';
            $vAltMobile = isset($row['altmobile']) ? db_input($row['altmobile']) : '';
            $departmentName = isset($row['department']) ? trim($row['department']) : '';
            $propertyName = isset($row['property']) ? trim($row['property']) : '';

            $vMobile = preg_replace('/\s+/', '', $vMobile);

            // Required fields
            if ($vName === '' || $vMobile === '') {
                $skipped[] = [
                    "row" => $index + 1,
                    "name" => $vName,
                    "mobile" => $vMobile,
                    "reason" => "Missing required fields"
                ];
                continue;
            }

            // Mobile format
            if (!preg_match('/^[0-9]{10}$/', $vMobile)) {
                $skipped[] = [
                    "row" => $index + 1,
                    "name" => $vName,
                    "mobile" => $vMobile,
                    "reason" => "Invalid mobile number format"
                ];
                continue;
            }

            // Department check
            $iDepartmentID = 0;
            if ($departmentName !== '') {
                $deptKey = strtolower($departmentName);
                if (!isset($departmentMap[$deptKey])) {
                    $skipped[] = [
                        "row" => $index + 1,
                        "name" => $vName,
                        "mobile" => $vMobile,
                        "reason" => "Department '$departmentName' not found"
                    ];
                    continue;
                }
                $iDepartmentID = $departmentMap[$deptKey];
            }

            // Property check
            $iPropertyID = 0;
            if ($propertyName !== '') {
                $propKey = strtolower($propertyName);
                if (!isset($propertyMap[$propKey])) {
                    $skipped[] = [
                        "row" => $index + 1,
                        "name" => $vName,
                        "mobile" => $vMobile,
                        "reason" => "Property '$propertyName' not found"
                    ];
                    continue;
                }
                $iPropertyID = $propertyMap[$propKey];
            }

            // Store validated row for second pass
            $validatedRows[] = [
                "index" => $index,
                "vCode" => $vCode,
                "vName" => $vName,
                "vMobile" => $vMobile,
                "vAltMobile" => $vAltMobile,
                "iDepartmentID" => $iDepartmentID,
                "iPropertyID" => $iPropertyID
            ];
        }


        // STOP if ANY errors


        if (!empty($skipped)) {
            echo json_encode([
                "data" => [
                    "skipped" => $skipped,
                    "message" => "Import failed. Fix errors and try again."
                ],
                "statusCode" => 400
            ]);
            exit;
        }


        // STEP 3: INSERT/UPDATE PASS


        foreach ($validatedRows as $data) {

            extract($data);

            $checkSql = "SELECT iStaffID FROM staff 
                     WHERE vMobile = '$vMobile' 
                     AND cStatus != 'X'";
            $checkRes = sql_query($checkSql);

            if (sql_num_rows($checkRes) > 0) {

                $existing = sql_fetch_assoc($checkRes);
                $iStaffID = (int) $existing['iStaffID'];

                $sql = "UPDATE staff SET 
                        vName = '$vName',
                        vCode = '$vCode',
                        iDepartmentID = $iDepartmentID,
                        iPropertyID = $iPropertyID,
                        vAltmobile = '$vAltMobile'
                    WHERE iStaffID = $iStaffID";

                sql_query($sql);

                $updated[] = [
                    "row" => $data['index'] + 1,
                    "id" => $iStaffID,
                    "status" => "Updated"
                ];
            } else {

                $iStaffID = NextID('iStaffID', 'staff');
                $dtRegistered = NOW;

                $sql = "INSERT INTO staff 
                (iStaffID, vCode, vName, vMobile, vAltmobile, 
                 iRouteID, iStopID, iDepartmentID, iPropertyID, 
                 dtRegistered, cStatus)
                VALUES 
                ($iStaffID, '$vCode', '$vName', '$vMobile', '$vAltMobile',
                 0, 0, $iDepartmentID, $iPropertyID,
                 '$dtRegistered', 'A')";

                sql_query($sql);

                $inserted[] = [
                    "row" => $data['index'] + 1,
                    "id" => $iStaffID,
                    "status" => "Inserted"
                ];
            }
        }

        echo json_encode([
            "data" => [
                "inserted" => $inserted,
                "updated" => $updated,
                "message" => count($inserted) . " inserted, " .
                    count($updated) . " updated."
            ],
            "statusCode" => 200
        ]);
        exit;


    // ===================== CASE: APPROVE_STAFF =====================
    case 'APPROVE_STAFF':
        $ids = $_REQUEST['ids'] ?? [];

        // Accept a single id as well for convenience
        if (empty($ids) && !empty($_REQUEST['id'])) {
            $ids = [$_REQUEST['id']];
        }

        if (empty($ids) || !is_array($ids)) {
            echo json_encode([
                "error" => [
                    "message" => "Staff IDs are required (pass as 'ids' array)"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Check if the user has approval rights
        $canApprove = checkUserModuleAccess($user_id, 'STAFF_APPROVE');
        if (!$canApprove) {
            echo json_encode([
                "error" => [
                    "message" => "You do not have permission to approve staff"
                ],
                "statusCode" => 403
            ]);
            exit;
        }

        // Sanitize IDs — keep only positive integers
        $sanitizedIds = array_filter(array_map('intval', $ids), fn($v) => $v > 0);

        if (empty($sanitizedIds)) {
            echo json_encode([
                "error" => [
                    "message" => "No valid Staff IDs provided"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $idList = implode(',', $sanitizedIds);

        // Fetch all matching pending staff in one query
        $fetchSql = "SELECT iStaffID, cStatus FROM staff WHERE iStaffID IN ($idList) AND cStatus != 'X'";
        $fetchRes = sql_query($fetchSql);

        $foundIds    = [];
        $notPending  = [];

        while ($row = sql_fetch_assoc($fetchRes)) {
            if ($row['cStatus'] === 'P') {
                $foundIds[] = (int) $row['iStaffID'];
            } else {
                $notPending[] = (int) $row['iStaffID'];
            }
        }

        // IDs that weren't found at all
        $allFound   = array_merge($foundIds, $notPending);
        $notFound   = array_values(array_diff($sanitizedIds, $allFound));

        if (empty($foundIds)) {
            echo json_encode([
                "error" => [
                    "message" => "No pending staff members found to approve",
                    "notFound"  => $notFound,
                    "notPending" => $notPending
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Bulk approve in one UPDATE
        $approveIdList = implode(',', $foundIds);
        $approveSql = "UPDATE staff SET cStatus = 'A' WHERE iStaffID IN ($approveIdList) AND cStatus = 'P'";

        if (sql_query($approveSql)) {
            echo json_encode([
                "data" => [
                    "approved"   => $foundIds,
                    "notFound"   => $notFound,
                    "notPending" => $notPending,
                    "message"    => count($foundIds) . " staff member(s) approved successfully"
                ],
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to approve staff members"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE: BULK_STATUS =====================
    // Set status of multiple staff to 'A' (Active) or 'I' (Inactive)
    case 'BULK_APPROVE_INACTIVE_STATUS':
        $ids        = $_REQUEST['ids'] ?? [];
        $newStatus  = strtoupper(trim($_REQUEST['status'] ?? ''));
        $statusChangedBy =!empty($_REQUEST['statusChangedBy']) ? intval($_REQUEST['statusChangedBy']) : 1;

        // Accept a single id as well for convenience
        if (empty($ids) && !empty($_REQUEST['id'])) {
            $ids = [$_REQUEST['id']];
        }

        if (empty($ids) || !is_array($ids)) {
            echo json_encode([
                "error" => ["message" => "Staff IDs are required (pass as 'ids' array)"],
                "statusCode" => 400
            ]);
            exit;
        }

        $allowedStatuses = ['A', 'I'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            echo json_encode([
                "error" => ["message" => "Invalid status. Allowed values: A (Active), I (Inactive)"],
                "statusCode" => 400
            ]);
            exit;
        }

        // Permission check
        $canApprove = checkUserModuleAccess($user_id, 'STAFF_APPROVE');
        if (!$canApprove) {
            echo json_encode([
                "error" => ["message" => "You do not have permission to update staff status"],
                "statusCode" => 403
            ]);
            exit;
        }

        // Sanitize IDs
        $sanitizedIds = array_values(array_filter(array_map('intval', $ids), fn($v) => $v > 0));

        if (empty($sanitizedIds)) {
            echo json_encode([
                "error" => ["message" => "No valid Staff IDs provided"],
                "statusCode" => 400
            ]);
            exit;
        }

        $idList = implode(',', $sanitizedIds);

        // Fetch existing staff (exclude deleted)
        $fetchSql = "SELECT iStaffID, cStatus FROM staff WHERE iStaffID IN ($idList) AND cStatus != 'X'";
        $fetchRes = sql_query($fetchSql);

        $toUpdate    = [];  // eligible for the status change
        $alreadySet  = [];  // already in the target status
        $foundAll    = [];

        while ($row = sql_fetch_assoc($fetchRes)) {
            $sid = (int) $row['iStaffID'];
            $foundAll[] = $sid;
            if ($row['cStatus'] === $newStatus) {
                $alreadySet[] = $sid;
            } else {
                $toUpdate[] = $sid;
            }
        }

        $notFound = array_values(array_diff($sanitizedIds, $foundAll));

        if (empty($toUpdate)) {
            echo json_encode([
                "data" => [
                    "updated"    => [],
                    "alreadySet" => $alreadySet,
                    "notFound"   => $notFound,
                    "message"    => "All provided staff are already set to status '$newStatus'"
                ],
                "statusCode" => 200
            ]);
            exit;
        }

        $updateIdList = implode(',', $toUpdate);
        $statusLabel  = $newStatus === 'A' ? 'Active' : 'Inactive';
        $updateSql    = "UPDATE staff SET cStatus = '$newStatus', iApproved_UserID=$statusChangedBy WHERE iStaffID IN ($updateIdList) AND cStatus != 'X'";

        if (sql_query($updateSql)) {
            echo json_encode([
                "data" => [
                    "updated"    => $toUpdate,
                    "alreadySet" => $alreadySet,
                    "notFound"   => $notFound,
                    "message"    => count($toUpdate) . " staff member(s) set to $statusLabel"
                ],
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => ["message" => "Failed to update staff status"],
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
