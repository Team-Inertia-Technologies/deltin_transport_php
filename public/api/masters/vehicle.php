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

// Function to validate vehicle registration number
function validateVehicleData($vRnum, $excludeVehicleID = 0) {
    if (empty($vRnum)) {
        return ['valid' => true];
    }
    
    $sql = "SELECT iVehicleID, vName FROM vehicle 
            WHERE vRnum = '$vRnum' AND iVehicleID != $excludeVehicleID AND cStatus != 'X'";
    
    $res = sql_query($sql);
    
    if (sql_num_rows($res) > 0) {
        $row = sql_fetch_assoc($res);
        return [
            'valid' => false,
            'message' => "Registration number already exists for vehicle: " . $row['vName']
        ];
    }
    
    return ['valid' => true];
}

switch ($mode) {

    // ===================== CASE 1: LIST =====================
    case 'LIST':
        // Optimized query with JOINs to get vendor and category data in single query
        $sql = "SELECT v.iVehicleID, v.vName, v.vRnum, v.iCatID, v.iVendorID, v.iSeats, 
                       v.iAreaID, v.cStatus, vn.vName as vendor_name, c.vName as category_name
                FROM vehicle v
                LEFT JOIN vendor vn ON v.iVendorID = vn.iVendorID AND vn.cStatus != 'X'
                LEFT JOIN category c ON v.iCatID = c.iCatID AND c.cStatus != 'X'
                WHERE v.cStatus != 'X' 
                ORDER BY v.iVehicleID DESC";
        $res = sql_query($sql);

        $data = [];
        while ($row = sql_fetch_assoc($res)) {
            $vehicle = [
                'iVehicleID' => $row['iVehicleID'],
                'vName' => $row['vName'],
                'vRnum' => $row['vRnum'],
                'iSeats' => $row['iSeats'],
                'iAreaID' => $row['iAreaID'],
                'cStatus' => $row['cStatus'],
                'vendor' => [
                    'id' => $row['iVendorID'],
                    'name' => $row['vendor_name'] ?? ''
                ],
                'category' => [
                    'id' => $row['iCatID'],
                    'name' => $row['category_name'] ?? ''
                ]
            ];
            $data[] = $vehicle;
        }

        echo json_encode([
            "status" => 200,
            "message" => "Vehicle list fetched successfully",
            "data" => $data
        ]);
        break;

    // ===================== CASE 2: VEHICLE_DETAILS =====================
    case 'VEHICLE_DETAILS':
        $id = isset($_REQUEST['iVehicleID']) ? intval($_REQUEST['iVehicleID']) : 0;
        if ($id <= 0) {
            echo json_encode([
                "status" => 400,
                "message" => "Invalid Vehicle ID"
            ]);
            exit;
        }

        // Optimized query with JOINs to get vendor and category data in single query
        $sql = "SELECT v.iVehicleID, v.vName, v.vRnum, v.iCatID, v.iVendorID, v.iSeats, 
                       v.iAreaID, v.cStatus, vn.vName as vendor_name, c.vName as category_name
                FROM vehicle v
                LEFT JOIN vendor vn ON v.iVendorID = vn.iVendorID AND vn.cStatus != 'X'
                LEFT JOIN category c ON v.iCatID = c.iCatID AND c.cStatus != 'X'
                WHERE v.iVehicleID = $id";
        $res = sql_query($sql);

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "status" => 404,
                "message" => "Vehicle not found"
            ]);
            exit;
        }

        $row = sql_fetch_assoc($res);
        
        $vehicle = [
            'iVehicleID' => $row['iVehicleID'],
            'vName' => $row['vName'],
            'vRnum' => $row['vRnum'],
            'iSeats' => $row['iSeats'],
            'iAreaID' => $row['iAreaID'],
            'cStatus' => $row['cStatus'],
            'vendor' => [
                'id' => $row['iVendorID'],
                'name' => $row['vendor_name'] ?? ''
            ],
            'category' => [
                'id' => $row['iCatID'],
                'name' => $row['category_name'] ?? ''
            ]
        ];

        echo json_encode([
            "status" => 200,
            "message" => "Vehicle details fetched successfully",
            "data" => $vehicle
        ]);
        break;
    // ===================== CASE 3: UPDATE_VEHICLE =====================
    case 'UPDATE_VEHICLE':
        $id = intval($_REQUEST['iVehicleID'] ?? 0);
        $vName = db_input($_REQUEST['vName'] ?? '');
        $vRnum = db_input($_REQUEST['vRnum'] ?? '');
        $iCatID = intval($_REQUEST['iCatID'] ?? 0);
        $iVendorID = intval($_REQUEST['iVendorID'] ?? 0);
        $iSeats = intval($_REQUEST['iSeats'] ?? 0);
        $iAreaID = intval($_REQUEST['iAreaID'] ?? 0);
        $cStatus = db_input($_REQUEST['cStatus'] ?? 'A');

        if ($id <= 0) {
            echo json_encode([
                "status" => 400,
                "message" => "Vehicle ID is required for update"
            ]);
            exit;
        }

        // Single query to validate registration number
        $validation = validateVehicleData($vRnum, $id);
        if (!$validation['valid']) {
            echo json_encode([
                "status" => 409,
                "message" => $validation['message']
            ]);
            exit;
        }

        // Check if vehicle exists and update in single operation
        $sql = "UPDATE vehicle SET 
                    vName = '$vName',
                    vRnum = '$vRnum',
                    iCatID = $iCatID,
                    iVendorID = $iVendorID,
                    iSeats = $iSeats,
                    iAreaID = $iAreaID,
                    cStatus = '$cStatus'
                WHERE iVehicleID = $id AND cStatus != 'X'";

        $result = sql_query($sql);
        
        if ($result && sql_affected_rows() > 0) {
            echo json_encode([
                "status" => 200,
                "message" => "Vehicle updated successfully"
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            echo json_encode([
                "status" => 404,
                "message" => "Vehicle not found or no changes made"
            ]);
        } else {
            echo json_encode([
                "status" => 500,
                "message" => "Failed to update vehicle"
            ]);
        }
        break;
    // ===================== CASE 4: ADD_VEHICLE =====================
    case 'ADD_VEHICLE':
        $vName = db_input($_REQUEST['vName'] ?? '');
        $vRnum = db_input($_REQUEST['vRnum'] ?? '');
        $iCatID = intval($_REQUEST['iCatID'] ?? 0);
        $iVendorID = intval($_REQUEST['iVendorID'] ?? 0);
        $iSeats = intval($_REQUEST['iSeats'] ?? 0);
        $iAreaID = intval($_REQUEST['iAreaID'] ?? 0);
        $cStatus = db_input($_REQUEST['cStatus'] ?? 'A');

        // Basic validation
        if (empty($vName)) {
            echo json_encode([
                "status" => 400,
                "message" => "Vehicle name is required"
            ]);
            exit;
        }

        if (empty($vRnum)) {
            echo json_encode([
                "status" => 400,
                "message" => "Registration number is required"
            ]);
            exit;
        }

        // Single query to validate registration number
        $validation = validateVehicleData($vRnum, 0);
        if (!$validation['valid']) {
            echo json_encode([
                "status" => 409,
                "message" => $validation['message']
            ]);
            exit;
        }

        $iVehicleID = NextID('iVehicleID', 'vehicle');
        $sql = "INSERT INTO vehicle (iVehicleID, vName, vRnum, iCatID, iVendorID, iSeats, iAreaID, cStatus) 
                VALUES ($iVehicleID, '$vName', '$vRnum', $iCatID, $iVendorID, $iSeats, $iAreaID, '$cStatus')";

        if (sql_query($sql)) {
            echo json_encode([
                "status" => 200,
                "message" => "Vehicle added successfully",
                "data" => ["iVehicleID" => $iVehicleID]
            ]);
        } else {
            echo json_encode([
                "status" => 500,
                "message" => "Failed to add vehicle"
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