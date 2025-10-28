<?php
include "../../includes/common_api.php";
header('Content-Type: application/json');

$mode = $_REQUEST['mode'] ?? '';
$user_id = intval(DecodeParam($Token));

// Validate user_id exists in user table
if ($user_id <= 0) {
    echo json_encode([
        "status" => 401,
        "message" => "Invalid or missing user token"
    ]);
    exit;
}

$userCheckSql = "SELECT iUserID FROM users WHERE iUserID = $user_id AND cStatus = 'A'";
$userCheckRes = sql_query($userCheckSql);

if (sql_num_rows($userCheckRes) == 0) {
    echo json_encode([
        "status" => 401,
        "message" => "User not found or inactive"
    ]);
    exit;
}

// Function to validate driver data and check duplicates
function validateDriverData($vMobileNum, $vEmpCode, $excludeDriverID = 0)
{
    $conditions = [];

    if (!empty($vMobileNum)) {
        $conditions[] = "vMobileNum = '$vMobileNum'";
    }

    if (!empty($vEmpCode)) {
        $conditions[] = "vEmpCode = '$vEmpCode'";
    }

    if (empty($conditions)) {
        return ['valid' => true];
    }

    $sql = "SELECT iDriverID, vName, vMobileNum, vEmpCode 
            FROM driver 
            WHERE (" . implode(' OR ', $conditions) . ") 
            AND iDriverID != $excludeDriverID 
            AND cStatus != 'X'";

    $res = sql_query($sql);

    while ($row = sql_fetch_assoc($res)) {
        if (!empty($vMobileNum) && $row['vMobileNum'] === $vMobileNum) {
            return [
                'valid' => false,
                'message' => "Mobile number already exists for driver: " . $row['vName']
            ];
        }
        if (!empty($vEmpCode) && $row['vEmpCode'] === $vEmpCode) {
            return [
                'valid' => false,
                'message' => "Employee code already exists for driver: " . $row['vName']
            ];
        }
    }

    return ['valid' => true];
}

switch ($mode) {

    // ===================== CASE 1: LIST =====================
    case 'LIST':
        // Optimized query with JOIN to get vendor data in single query
        $sql = "SELECT d.iDriverID, d.vName, d.vMobileNum, d.vEmpCode, d.iVendorID, 
                       d.iAreaID, d.iRank, d.cStatus, v.vName as vendor_name
                FROM driver d
                LEFT JOIN vendor v ON d.iVendorID = v.iVendorID AND v.cStatus != 'X'
                WHERE d.cStatus != 'X' 
                ORDER BY d.iRank DESC";
        $res = sql_query($sql);

        $data = [];
        while ($row = sql_fetch_assoc($res)) {
            $driver = [
                'iDriverID' => $row['iDriverID'],
                'vName' => db_output2($row['vName']),
                'vMobileNum' => $row['vMobileNum'],
                'vEmpCode' => db_output2($row['vEmpCode']),
                'iAreaID' => $row['iAreaID'],
                'cStatus' => $row['cStatus'],
                'vendor' => [
                    'id' => $row['iVendorID'],
                    'name' => $row['vendor_name'] ?? ''
                ]
            ];
            $data[] = $driver;
        }

        echo json_encode([
            "status" => 200,
            "message" => "Driver list fetched successfully",
            "data" => $data
        ]);
        break;

    // ===================== CASE 2: DRIVER_DETAILS =====================
    case 'DRIVER_DETAILS':
        $id = isset($_REQUEST['iDriverID']) ? intval($_REQUEST['iDriverID']) : 0;
        if ($id <= 0) {
            echo json_encode([
                "status" => 400,
                "message" => "Invalid Driver ID"
            ]);
            exit;
        }

        // Optimized query with JOIN to get vendor data in single query
        $sql = "SELECT d.iDriverID, d.vName, d.vMobileNum, d.vEmpCode, d.iVendorID, 
                       d.iAreaID, d.iRank, d.cStatus, v.vName as vendor_name
                FROM driver d
                LEFT JOIN vendor v ON d.iVendorID = v.iVendorID AND v.cStatus != 'X'
                WHERE d.iDriverID = $id";
        $res = sql_query($sql);

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "status" => 404,
                "message" => "Driver not found"
            ]);
            exit;
        }

        $row = sql_fetch_assoc($res);

        $driver = [
            'iDriverID' => $row['iDriverID'],
            'vName' => db_output2($row['vName']),
            'vMobileNum' => $row['vMobileNum'],
            'vEmpCode' => $row['vEmpCode'],
            'iAreaID' => $row['iAreaID'],
            'cStatus' => $row['cStatus'],
            'vendor' => [
                'id' => $row['iVendorID'],
                'name' => db_output2($row['vendor_name'] ?? '')
            ]
        ];

        echo json_encode([
            "status" => 200,
            "message" => "Driver details fetched successfully",
            "data" => $driver
        ]);
        break;

    // ===================== CASE 3: UPDATE_DRIVER =====================
    case 'UPDATE_DRIVER':
        $id = intval($_REQUEST['iDriverID'] ?? 0);
        $vName = db_input($_REQUEST['vName'] ?? '');
        $vMobileNum = db_input($_REQUEST['vMobileNum'] ?? '');
        $vEmpCode = db_input($_REQUEST['vEmpCode'] ?? '');
        $iVendorID = intval($_REQUEST['iVendorID'] ?? 0);
        $iAreaID = intval($_REQUEST['iAreaID'] ?? 0);
        $cStatus = db_input($_REQUEST['cStatus'] ?? 'A');

        if ($id <= 0) {
            echo json_encode([
                "status" => 400,
                "message" => "Driver ID is required for update"
            ]);
            exit;
        }

        // Single query to check driver existence and validate duplicates
        $validation = validateDriverData($vMobileNum, $vEmpCode, $id);
        if (!$validation['valid']) {
            echo json_encode([
                "status" => 409,
                "message" => $validation['message']
            ]);
            exit;
        }

        // Check if driver exists and update in single operation
        $sql = "UPDATE driver SET 
                    vName = '$vName',
                    vMobileNum = '$vMobileNum',
                    vEmpCode = '$vEmpCode',
                    iVendorID = $iVendorID,
                    iAreaID = $iAreaID,
                    cStatus = '$cStatus'
                WHERE iDriverID = $id AND cStatus != 'X'";

        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            echo json_encode([
                "status" => 200,
                "message" => "Driver updated successfully"
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            echo json_encode([
                "status" => 404,
                "message" => "Driver not found or no changes made"
            ]);
        } else {
            echo json_encode([
                "status" => 500,
                "message" => "Failed to update driver"
            ]);
        }
        break;

    // ===================== CASE 4: ADD_DRIVER =====================
    case 'ADD_DRIVER':
        $vName = db_input($_REQUEST['vName'] ?? '');
        $vMobileNum = db_input($_REQUEST['vMobileNum'] ?? '');
        $vEmpCode = db_input($_REQUEST['vEmpCode'] ?? '');
        $iVendorID = intval($_REQUEST['iVendorID'] ?? 0);
        $iAreaID = intval($_REQUEST['iAreaID'] ?? 0);
        $cStatus = db_input($_REQUEST['cStatus'] ?? 'A');

        // Basic validation
        if (empty($vName)) {
            echo json_encode([
                "status" => 400,
                "message" => "Driver name is required"
            ]);
            exit;
        }

        if (empty($vMobileNum)) {
            echo json_encode([
                "status" => 400,
                "message" => "Mobile number is required"
            ]);
            exit;
        }

        // Single query to validate duplicates
        $validation = validateDriverData($vMobileNum, $vEmpCode, 0);
        if (!$validation['valid']) {
            echo json_encode([
                "status" => 409,
                "message" => $validation['message']
            ]);
            exit;
        }

        $iDriverID = NextID('iDriverID', 'driver');
        $sql = "INSERT INTO driver (iDriverID, vName, vMobileNum, vEmpCode, iVendorID, iAreaID, iRank, cStatus) 
                VALUES ($iDriverID, '$vName', '$vMobileNum', '$vEmpCode', $iVendorID, $iAreaID, $iDriverID, '$cStatus')";

        if (sql_query($sql)) {
            echo json_encode([
                "status" => 200,
                "message" => "Driver added successfully",
                "data" => ["iDriverID" => $iDriverID]
            ]);
        } else {
            echo json_encode([
                "status" => 500,
                "message" => "Failed to add driver"
            ]);
        }
        break;

    // ===================== DEFAULT =====================
    default:
        echo json_encode([
            "status" => 400,
            "message" => "Invalid mode parameter"
        ]);
        break;
}