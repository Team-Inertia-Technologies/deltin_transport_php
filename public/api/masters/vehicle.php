<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true); // Decode as associative array
$_REQUEST = array_merge($_REQUEST, $request ?? []); // Merge with $_REQUEST
$mode = $_REQUEST['mode'] ?? '';
$Token = $_REQUEST['token'] ?? '';
$user_id = intval(DecodeParam($Token));


// Validate user_id exists in user table
if ($user_id <= 0) {
    echo json_encode([
        "statusCode" => 401,
        "message" => "Invalid or missing user token"
    ]);
    exit;
}

$userCheckSql = "SELECT iUserID FROM users WHERE iUserID = $user_id AND cStatus = 'A'";
$userCheckRes = sql_query($userCheckSql);

if (sql_num_rows($userCheckRes) == 0) {
    echo json_encode([
        "statusCode" => 401,
        "message" => "User not found or inactive"
    ]);
    exit;
}
$VEHICLE_CATEGORY_ARR = GetXArrFromYID("SELECT iVCatID, vName FROM vehicle_category WHERE cStatus='A' ORDER BY vName", "3");
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

// Function to validate category ID
function validateCategoryData($categoryID)
{
    global $VEHICLE_CATEGORY_ARR;

    if ($categoryID <= 0) {
        return [
            'valid' => false,
            'message' => "Category is required"
        ];
    }

    if (!isset($VEHICLE_CATEGORY_ARR[$categoryID])) {
        return [
            'valid' => false,
            'message' => "Invalid category selected. Please choose a valid category from the list."
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

        // Driver type options with "choose" option
        $driverTypeOpt = [['id' => 0, 'title' => 'Choose']];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $title) {
            $driverTypeOpt[] = [
                'id' => intval($id),
                'title' => $title
            ];
        }

        // Category options with "choose" option
        $categoryOpt = [['id' => 0, 'title' => 'Choose']];
        foreach ($VEHICLE_CATEGORY_ARR as $id => $title) {
            $categoryOpt[] = [
                'id' => intval($id),
                'title' => $title
            ];
        }

        // Vendor options with "choose" option
        $vendorOpt = [['id' => 0, 'name' => 'Choose']];
        $vendorSql = "SELECT iVendorID, vName FROM vendor WHERE cStatus = 'A' ORDER BY vName";
        $vendorRes = sql_query($vendorSql);
        while ($vendorRow = sql_fetch_assoc($vendorRes)) {
            $vendorOpt[] = [
                'id' => intval($vendorRow['iVendorID']),
                'name' => $vendorRow['vName']
            ];
        }

        echo json_encode([
            "statusCode" => 200,
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
            $vehicleID = intval($row['iVehicleID']);
            
            // Get availability areas and names in one query using JOIN
            $areaSql = "SELECT vaa.iAreaID, ga.vName 
                        FROM vehicle_area_assoc vaa 
                        LEFT JOIN gen_area ga ON vaa.iAreaID = ga.iAreaID AND ga.cStatus = 'A'
                        WHERE vaa.iVehicleID = $vehicleID 
                        ORDER BY ga.iRank";
            $areaRes = sql_query($areaSql);

            $availability = [];
            $availabilityNames = [];
            while ($areaRow = sql_fetch_assoc($areaRes)) {
                $availability[] = intval($areaRow['iAreaID']);
                if (!empty($areaRow['vName'])) {
                    $availabilityNames[] = $areaRow['vName'];
                }
            }

            $vehicle[] = [
                'id' => $row['iVehicleID'],
                'vehicleNumber' => $row['vRnum'],
                'vehicleCapacity' => $row['iSeats'],
                'rate' => $row['fRate'],
                'vehicleOwnerID' => $row['iVendorID'],
                'vehicleOwner' => $row['vendor_name'] ?? '',
                'availabilityID' => $availability,
                'availability' => $availabilityNames
            ];
             $rowData[] = $vehicle;
        }

        echo json_encode([
            "statusCode" => 200,
            "message" => "Vehicle list fetched successfully",
           "data" => [
                "rowData" => $rowData
            ]
        ]);
        break;

    // ===================== CASE 3: VEHICLE_DETAILS =====================
    case 'VEHICLE_DETAILS':
        $id = isset($_REQUEST['iVehicleID']) ? intval($_REQUEST['iVehicleID']) : 0;
        if ($id <= 0) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Invalid Vehicle ID"
            ]);
            exit;
        }

        // Optimized query with JOINs to get vendor and category data in single query
        $sql = "SELECT v.iVehicleID, v.vName, v.vRnum, v.iCatID, v.iVendorID, v.iSeats, v.iType,
                       v.iAreaID, v.dRegistration, v.dExpiry, v.vTouristPerNo, v.cStatus, 
                       vn.vName as vendor_name, c.vName as category_name
                FROM vehicle v
                LEFT JOIN vendor vn ON v.iVendorID = vn.iVendorID AND vn.cStatus = 'A'
                LEFT JOIN vehicle_category c ON v.iCatID = c.iVCatID AND c.cStatus = 'A'
                WHERE v.iVehicleID = $id";
        $res = sql_query($sql);

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "statusCode" => 404,
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

        // Prepare option arrays with "choose" options
        $AREA_ARR_RAW = GetXArrFromYID("SELECT iAreaID, vName FROM gen_area where cStatus='A' ORDER BY iRank", "3");
        $availableOpt = [];
        foreach ($AREA_ARR_RAW as $id => $label) {
            $availableOpt[] = ['id' => intval($id), 'label' => $label];
        }

        // Driver type options with "choose" option
        $driverTypeOpt = [['id' => 0, 'title' => 'Choose']];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $title) {
            $driverTypeOpt[] = [
                'id' => intval($id),
                'title' => $title
            ];
        }

        // Category options with "choose" option
        $categoryOpt = [['id' => 0, 'title' => 'Choose']];
        foreach ($VEHICLE_CATEGORY_ARR as $id => $title) {
            $categoryOpt[] = [
                'id' => intval($id),
                'title' => $title
            ];
        }

        // Vendor options with "choose" option
        $vendorOpt = [['id' => 0, 'name' => 'Choose']];
        $vendorSql = "SELECT iVendorID, vName FROM vendor WHERE cStatus = 'A' ORDER BY vName";
        $vendorRes = sql_query($vendorSql);
        while ($vendorRow = sql_fetch_assoc($vendorRes)) {
            $vendorOpt[] = [
                'id' => intval($vendorRow['iVendorID']),
                'name' => $vendorRow['vName']
            ];
        }

        echo json_encode([
            "statusCode" => 200,
            "message" => "Vehicle details fetched successfully",
            "data" => [
               
                'vehicleData' => [
                    'iVehicleID' => intval($row['iVehicleID']),
                    'vName' => $row['vName'] ?? '',
                    'vRnum' => $row['vRnum'] ?? '',
                    'iSeats' => intval($row['iSeats'] ?? 0),
                    'dateOfReg' => $row['dRegistration'] ?? '',
                    'dateOfExp' => $row['dExpiry'] ?? '',
                    'perNum' => $row['vTouristPerNo'] ?? '',
                     'selectedDriverType' => intval($row['iType'] ?? 0),
                'selectedAvailOpt' => $availability,
                'selectedCategoryType' => intval($row['iCatID'] ?? 0),
                'selectedVendor' => intval($row['iVendorID'] ?? 0),
                    'cStatus' => $row['cStatus'] ?? 'A'
                ],
                'availableOpt' => $availableOpt,
                'driverTypeOpt' => $driverTypeOpt,
                'categoryOpt' => $categoryOpt,
                'vendorOpt' => $vendorOpt
            ]
        ]);
        break;
    // ===================== CASE 4: UPDATE_VEHICLE =====================
    case 'UPDATE_VEHICLE':
        // Handle form data with the new structure (matching ADD_VEHICLE)
        $id = intval($_REQUEST['iVehicleID'] ?? 0);
        $type = intval($_REQUEST['type'] ?? 0); // Driver type
        $category = intval($_REQUEST['category'] ?? 0); // Vehicle category
        $vendor = intval($_REQUEST['vendor'] ?? 0); // Vendor ID
        $vehiNum = db_input($_REQUEST['vehiNum'] ?? ''); // Vehicle number
        $availability = $_REQUEST['availability'] ?? []; // Area availability array
        $dateOfReg = db_input($_REQUEST['dateOfReg'] ?? ''); // Registration date
        $dateOfExp = db_input($_REQUEST['dateOfExp'] ?? ''); // Expiry date
        $touTax = db_input($_REQUEST['touTax'] ?? ''); // Tourist tax (if needed)
        $perNum = db_input($_REQUEST['perNum'] ?? ''); // Permit number

        if ($id <= 0) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Vehicle ID is required for update"
            ]);
            exit;
        }

        // Basic validation
        if (empty($vehiNum)) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Vehicle number is required"
            ]);
            exit;
        }

        // Validate category
        $categoryValidation = validateCategoryData($category);
        if (!$categoryValidation['valid']) {
            echo json_encode([
                "statusCode" => 400,
                "message" => $categoryValidation['message']
            ]);
            exit;
        }

        // Validate vehicle registration number
        $validation = validateVehicleData($vehiNum, $id);
        if (!$validation['valid']) {
            echo json_encode([
                "statusCode" => 409,
                "message" => $validation['message']
            ]);
            exit;
        }

        // Update vehicle with new structure
        $sql = "UPDATE vehicle SET 
                    vRnum = '$vehiNum',
                    iCatID = $category,
                    iVendorID = $vendor,
                    iType = $type,
                    dRegistration = " . (!empty($dateOfReg) ? "'$dateOfReg'" : "NULL") . ",
                    dExpiry = " . (!empty($dateOfExp) ? "'$dateOfExp'" : "NULL") . ",
                    vTouristPerNo = '$perNum'
                WHERE iVehicleID = $id AND cStatus = 'A'";

        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            // Update availability areas - first delete existing associations
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

            // Log the update operation
            LogMasterEdit($id, 'VHC', 'U', $vehiNum, '', $user_id);

            echo json_encode([
                "data" => [
                    "message" => "Vehicle updated successfully"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            // Log the update operation even if no changes were made
            LogMasterEdit($id, 'VHC', 'U', $vehiNum, '', $user_id);
            
            echo json_encode([
                "data" => [
                    "message" => "No changes were made to vehicle"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "data" => [
                    "message" => "Failed to update vehicle"
                ],
                "token" => $Token,
                "statusCode" => 500
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
                "statusCode" => 400,
                "message" => "Vehicle number is required"
            ]);
            exit;
        }

        // Validate category
        $categoryValidation = validateCategoryData($category);
        if (!$categoryValidation['valid']) {
            echo json_encode([
                "statusCode" => 400,
                "message" => $categoryValidation['message']
            ]);
            exit;
        }

        // Validate vehicle registration number
        $validation = validateVehicleData($vehiNum, 0);
        if (!$validation['valid']) {
            echo json_encode([
                "statusCode" => 409,
                "message" => $validation['message']
            ]);
            exit;
        }

        $iVehicleID = NextID('iVehicleID', 'vehicle');

        // Using the newly added database fields
        $sql = "INSERT INTO vehicle (iVehicleID, vRnum, iVCatID, iVendorID, iType, dRegistration, dExpiry, vTouristPerNo, cStatus) 
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

            // Log the add operation
            LogMasterEdit($iVehicleID, 'VHC', 'I', $vehiNum, '', $user_id);

            echo json_encode([
                "statusCode" => 200,
                "message" => "Vehicle added successfully",
                "data" => ["iVehicleID" => $iVehicleID]
            ]);
        } else {
            echo json_encode([
                "statusCode" => 500,
                "message" => "Failed to add vehicle"
            ]);
        }
        break;


    // ===================== CASE 6: DELETE_VEHICLE =====================
    case 'DELETE_VEHICLE':
        $id = intval($_REQUEST['iVehicleID'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Vehicle ID is required for deletion"
            ]);
            exit;
        }

        // Update cStatus to 'X' instead of actual deletion
        $sql = "UPDATE vehicle SET cStatus = 'X' WHERE iVehicleID = $id AND cStatus != 'X'";
        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            // Log the delete operation
            LogMasterEdit($id, 'VHC', 'D', '', '', $user_id);

            echo json_encode([
                "statusCode" => 200,
                "message" => "Vehicle deleted successfully"
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            echo json_encode([
                "statusCode" => 200,
                "message" => "Vehicle not found or already deleted"
            ]);
        } else {
            echo json_encode([
                "statusCode" => 500,
                "message" => "Failed to delete vehicle"
            ]);
        }
        break;

    // ===================== DEFAULT =====================
    default:
        echo json_encode([
            "statusCode" => 400,
            "message" => "Invalid mode parameter"
        ]);
        break;
}