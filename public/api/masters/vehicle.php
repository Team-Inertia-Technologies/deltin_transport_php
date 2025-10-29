<?php
include "../../includes/common_api.php";
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

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
        // Available options (areas from gen_area table - same as vendor.php)
        $AREA_ARR_RAW = GetXArrFromYID("SELECT iAreaID, vName FROM gen_area where cStatus='A' ORDER BY iRank", "3");
        $availableOpt = [];
        foreach ($AREA_ARR_RAW as $id => $label) {
            $availableOpt[] = ['id' => intval($id), 'label' => $label];
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
        // Optimized query with JOINs to get vendor data
        $sql = "SELECT v.iVehicleID, v.vName, v.vRnum, v.iVendorID, v.iSeats, v.iType,
                       v.fRate, v.dRegistration, v.dExpiry, v.vTouristPerNo, v.cStatus, vn.vName as vendor_name
                FROM vehicle v
                LEFT JOIN vendor vn ON v.iVendorID = vn.iVendorID AND vn.cStatus = 'A'
                WHERE v.cStatus = 'A' 
                ORDER BY v.iVehicleID DESC";
        $res = sql_query($sql);

        $data = [];
        while ($row = sql_fetch_assoc($res)) {
            // Get availability areas for this vehicle from vehicle_area_assoc table (same as vendor.php)
            $vehicleID = intval($row['iVehicleID']);
            $areaSql = "SELECT iAreaID FROM vehicle_area_assoc WHERE iVehicleID = $vehicleID";
            $areaRes = sql_query($areaSql);

            $availability = [];
            while ($areaRow = sql_fetch_assoc($areaRes)) {
                $availability[] = intval($areaRow['iAreaID']);
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

        // Get availability areas for this vehicle from vehicle_area_assoc table (same as vendor.php)
        $areaSql = "SELECT iAreaID FROM vehicle_area_assoc WHERE iVehicleID = $id";
        $areaRes = sql_query($areaSql);

        $availability = [];
        while ($areaRow = sql_fetch_assoc($areaRes)) {
            $availability[] = intval($areaRow['iAreaID']);
        }

        $vehicle = [
            'iVehicleID' => $row['iVehicleID'],
            'vName' => $row['vName'],
            'vRnum' => $row['vRnum'],
            'iSeats' => $row['iSeats'],
            'iAreaID' => $row['iAreaID'],
            'cStatus' => $row['cStatus'],
            'availability' => $availability,
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
        $availability = $_REQUEST['availability'] ?? []; // Handle as array (same as vendor.php)
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
            // Update availability areas - first delete existing associations (same as vendor.php)
            $deleteAreaSql = "DELETE FROM vehicle_area_assoc WHERE iVehicleID = $id";
            sql_query($deleteAreaSql);

            // Insert new area associations
            if (is_array($availability) && !empty($availability)) {
                foreach ($availability as $areaId) {
                    $areaId = intval($areaId);
                    if ($areaId > 0) {
                        $areaSql = "INSERT INTO vehicle_area_assoc (iVehicleID, iAreaID) VALUES ($id, $areaId)";
                        sql_query($areaSql);
                    }
                }
            }

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
    // ===================== CASE 5: ADD =====================
    case 'ADD_VEHICLE':
        // Handle form data with the new structure
        $type = intval($_REQUEST['type'] ?? 0); // Driver type
        $category = intval($_REQUEST['category'] ?? 0); // Vehicle category
        $vendor = intval($_REQUEST['vendor'] ?? 0); // Vendor ID
        $vehiNum = db_input($_REQUEST['vehiNum'] ?? ''); // Vehicle number
        $availability = $_REQUEST['availability'] ?? []; // Area availability array
        $dateOfReg = db_input($_REQUEST['dateOfReg'] ?? ''); // Registration date
        $dateOfExp = db_input($_REQUEST['dateOfExp'] ?? ''); // Expiry date
       
        $perNum = db_input($_REQUEST['perNum'] ?? ''); // Permit number
        $cStatus = 'A'; // Default active status

        // Basic validation
        if (empty($vehiNum)) {
            echo json_encode([
                "status" => 400,
                "message" => "Vehicle number is required"
            ]);
            exit;
        }

        if ($vendor <= 0) {
            echo json_encode([
                "status" => 400,
                "message" => "Vendor is required"
            ]);
            exit;
        }

        if ($category <= 0) {
            echo json_encode([
                "status" => 400,
                "message" => "Category is required"
            ]);
            exit;
        }

        // Validate vehicle registration number
        $validation = validateVehicleData($vehiNum, 0);
        if (!$validation['valid']) {
            echo json_encode([
                "status" => 409,
                "message" => $validation['message']
            ]);
            exit;
        }

        $iVehicleID = NextID('iVehicleID', 'vehicle');
        
        // Using the newly added database fields
        $sql = "INSERT INTO vehicle (iVehicleID, vRnum, iCatID, iVendorID, iType, dRegistration, dExpiry, vTouristPerNo, cStatus) 
                VALUES ($iVehicleID, '$vehiNum', $category, $vendor, $type, 
                    " . (!empty($dateOfReg) ? "'$dateOfReg'" : "NULL") . ", 
                    " . (!empty($dateOfExp) ? "'$dateOfExp'" : "NULL") . ", 
                    '$perNum', '$cStatus')";

        if (sql_query($sql)) {
            // Handle availability areas array - insert multiple area associations
            if (is_array($availability) && !empty($availability)) {
                foreach ($availability as $areaId) {
                    $areaId = intval($areaId);
                    if ($areaId > 0) {
                        $areaSql = "INSERT INTO vehicle_area_assoc (iVehicleID, iAreaID) VALUES ($iVehicleID, $areaId)";
                        sql_query($areaSql);
                    }
                }
            }

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