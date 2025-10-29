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
function validateVehicleData($vRnum, $excludeVehicleID = 0)
{
    if (empty($vRnum)) {
        return ['valid' => true];
    }

    $sql = "SELECT iVehicleID, vName FROM vehicle 
            WHERE vRnum = '$vRnum' AND iVehicleID != $excludeVehicleID AND cStatus = 'A'";

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

    // ===================== CASE 1: ONLOAD =====================
    case 'ONLOAD':
        // Available options (days of week)
        $availableOpt = [];
        foreach ($WEEKDAY_ARR as $id => $label) {
            $availableOpt[] = [
                'id' => $id,
                'label' => $label
            ];
        }

        // Driver type options
        $driverTypeOpt = [];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $title) {
            $driverTypeOpt[] = [
                'id' => $id,
                'title' => $title
            ];
        }

        // Category options from vehicle_category table
        $categoryOpt = [];
        $categorySql = "SELECT iCatID, vName FROM category WHERE cStatus = 'A' ORDER BY vName";
        $categoryRes = sql_query($categorySql);
        while ($categoryRow = sql_fetch_assoc($categoryRes)) {
            $categoryOpt[] = [
                'id' => $categoryRow['iCatID'],
                'title' => $categoryRow['vName']
            ];
        }

        // Vendor options
        $vendorOpt = [];
        $vendorSql = "SELECT iVendorID, vName FROM vendor WHERE cStatus = 'A' ORDER BY vName";
        $vendorRes = sql_query($vendorSql);
        while ($vendorRow = sql_fetch_assoc($vendorRes)) {
            $vendorOpt[] = [
                'id' => $vendorRow['iVendorID'],
                'name' => $vendorRow['vName']
            ];
        }

        echo json_encode([
            "status" => 200,
            "message" => "Options loaded successfully",
            "data" => [
                'availableOpt' => $availableOpt,
                'driverTypeOpt' => $driverTypeOpt,
                'categoryOpt' => $categoryOpt,
                'vendorOpt' => $vendorOpt
            ]
        ]);
        break;

    // ===================== CASE 2: LIST =====================
    case 'LIST':
        // Optimized query with JOINs to get vendor and vehicle_category data in single query
        $sql = "SELECT v.iVehicleID, v.vName, v.vRnum, v.iVendorID, v.iSeats, v.vDays,
                       v.fRate, v.cStatus, vn.vName as vendor_name
                FROM vehicle v
                LEFT JOIN vendor vn ON v.iVendorID = vn.iVendorID AND vn.cStatus = 'A'
                WHERE v.cStatus = 'A' 
                ORDER BY v.iVehicleID DESC";
        $res = sql_query($sql);

        $data = [];
        while ($row = sql_fetch_assoc($res)) {
            // Transform vDays (e.g., "0,5,6") into availability array
            $availableDays = [];
            if (!empty($row['vDays'])) {
                $availableDays = explode(',', $row['vDays']);
                $availableDays = array_map('trim', $availableDays);
            }
            
            // Create availability array for all 7 days
            $availability = [];
            for ($i = 0; $i <= 6; $i++) {
                $dayName = $WEEKDAY_ARR3[$i] ?? 'S'; // Get short name (SUN, MON, etc.)
                $availability[] = [
                    'id' => $i,
                    'name' => $dayName,
                    'available' => in_array((string)$i, $availableDays)
                ];
            }
            
            $data[] = [
                'id' => $row['iVehicleID'],
                'vehicleNumber' => $row['vRnum'],
                'vehicleCapacity' => $row['iSeats'],
                'rate' => $row['fRate'],
                'vehicleOwnerID' => $row['iVendorID'],
                'vehicleOwner' => $row['vendor_name'] ?? '',
                'availability' => $availability
            ];
        }

        echo json_encode([
            "status" => 200,
            "message" => "Vehicle list fetched successfully",
            "data" => $data
        ]);
        break;

    // ===================== CASE 3: VEHICLE_DETAILS =====================
    case 'VEHICLE_DETAILS':
        $id = isset($_REQUEST['iVehicleID']) ? intval($_REQUEST['iVehicleID']) : 0;
        if ($id <= 0) {
            echo json_encode([
                "status" => 400,
                "message" => "Invalid Vehicle ID"
            ]);
            exit;
        }

        // Optimized query with JOINs to get vendor and vehicle_category data in single query
        $sql = "SELECT v.iVehicleID, v.vName, v.vRnum, v.iCatID, v.iVendorID, v.iSeats, 
                       v.iAreaID, v.cStatus, vn.vName as vendor_name, c.vName as category_name
                FROM vehicle v
                LEFT JOIN vendor vn ON v.iVendorID = vn.iVendorID AND vn.cStatus = 'A'
                LEFT JOIN category c ON v.iCatID = c.iCatID AND c.cStatus = 'A'
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
            'vehicle_category' => [
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
    // ===================== CASE 4: UPDATE_VEHICLE =====================
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
                WHERE iVehicleID = $id AND cStatus = 'A'";

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
    // ===================== CASE 5: ADD_VEHICLE =====================
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