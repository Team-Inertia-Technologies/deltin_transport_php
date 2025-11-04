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
            AND cStatus = 'A'";

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
        // Optimized query with JOINs to get vendor and vehicle data in single query
        $sql = "SELECT d.iDriverID, d.vName, d.vMobileNum, d.vEmpCode, d.iVendorID, 
                       d.iAreaID, d.iRank, d.cStatus, v.vName as vendor_name, d.iVehicleID,
                       vh.vRnum, vh.iSeats
                FROM driver d
                LEFT JOIN vendor v ON d.iVendorID = v.iVendorID AND v.cStatus = 'A'
                LEFT JOIN vehicle vh ON d.iVehicleID  = vh.iVehicleID AND vh.cStatus = 'A'
                WHERE d.cStatus = 'A' 
                ORDER BY d.iRank DESC";
        $res = sql_query($sql);

        $rowData = [];
        while ($row = sql_fetch_assoc($res)) {
            // Format vehicle assignment
            // $vehicleAssigned = '';
            // if (!empty($row['vRnum'])) {
            //     $vehicleAssigned = $row['vRnum'];
            //     if (!empty($row['iSeats'])) {
            //         $vehicleAssigned .= ' (' . $row['iSeats'] . ')';
            //     }
            // }

            $driver = [
                'id' => intval($row['iDriverID']),
                'fullName' => db_output2($row['vName']),
                'mobileNumber' => $row['vMobileNum'],
                'owner' => db_output2($row['vendor_name'] ?? '')
               // 'vehicleAssigned' => $vehicleAssigned
            ];
            $rowData[] = $driver;
        }

        echo json_encode([
            "data" => [
                "rowData" => $rowData
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE 2: ONLOAD =====================
    case 'ONLOAD':
        // Available options (areas from gen_area table)
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

        // Vendor options with "choose" option
        $vendorOpt = [['id' => 0, 'title' => 'Choose']];
        $vendorSql = "SELECT iVendorID, vName FROM vendor WHERE cStatus = 'A' ORDER BY vName";
        $vendorRes = sql_query($vendorSql);
        while ($vendorRow = sql_fetch_assoc($vendorRes)) {
            $vendorOpt[] = [
                'id' => intval($vendorRow['iVendorID']),
                'title' => $vendorRow['vName']
            ];
        }

        echo json_encode([
            "data" => [
                'availableOpt' => $availableOpt,
                'driverTypeOpt' => $driverTypeOpt,
                'vendorOpt' => $vendorOpt
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE 3: DRIVER_DETAILS =====================
    case 'DRIVER_DETAILS':
        $id = isset($_REQUEST['driverId']) ? intval($_REQUEST['driverId']) : 0;
        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Invalid Driver ID"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Optimized query with JOIN to get vendor data and all new fields
        $sql = "SELECT d.iDriverID, d.vName, d.vMobileNum, d.vEmpCode, d.iVendorID, 
                       d.iType, d.vBatchNo, d.dExpiry, d.iAreaID, d.iRank, d.cStatus, 
                       v.vName as vendor_name
                FROM driver d
                LEFT JOIN vendor v ON d.iVendorID = v.iVendorID AND v.cStatus = 'A'
                WHERE d.iDriverID = $id";
        $res = sql_query($sql);

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Driver not found"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $row = sql_fetch_assoc($res);

        // Get availability areas for this driver from driver_area_assoc table
        $areaSql = "SELECT iAreaID FROM driver_area_assoc WHERE iDriverID = $id";
        $areaRes = sql_query($areaSql);

        $selectedAvailOpt = [];
        while ($areaRow = sql_fetch_assoc($areaRes)) {
            $selectedAvailOpt[] = intval($areaRow['iAreaID']);
        }

        // Prepare option arrays
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

        // Vendor options with "choose" option
        $vendorOpt = [['id' => 0, 'title' => 'Choose']];
        $vendorSql = "SELECT iVendorID, vName FROM vendor WHERE cStatus = 'A' ORDER BY vName";
        $vendorRes = sql_query($vendorSql);
        while ($vendorRow = sql_fetch_assoc($vendorRes)) {
            $vendorOpt[] = [
                'id' => intval($vendorRow['iVendorID']),
                'title' => $vendorRow['vName']
            ];
        }

        // Driver data using the same variable names as in ADD/UPDATE
        $driverData = [
            'iDriverID' => intval($row['iDriverID']),
            'name' => db_output2($row['vName']),
            'mobNum' => $row['vMobileNum'],
            'empCode' => db_output2($row['vEmpCode']),
            'vendorID' => intval($row['iVendorID']),
            'type' => intval($row['iType'] ?? 0),
            'batchNo' => db_output2($row['vBatchNo'] ?? ''),
            'dateOfExp' => $row['dExpiry'] ?? '',
            'cStatus' => $row['cStatus'],
            'availability' => $selectedAvailOpt
        ];

        echo json_encode([
            "data" => [
           
                'driverData' => $driverData,
                'availableOpt' => $availableOpt,
                'driverTypeOpt' => $driverTypeOpt,
                'vendorOpt' => $vendorOpt
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE 4: UPDATE_DRIVER =====================
    case 'UPDATE_DRIVER':
        // Handle form data with the new structure (matching ADD_DRIVER)
        $id = intval($_REQUEST['iDriverID'] ?? 0);
        $type = intval($_REQUEST['type'] ?? 0); // Driver type
        $vendorID = intval($_REQUEST['vendorID'] ?? 0); // Vendor ID
        $empCode = db_input($_REQUEST['empCode'] ?? ''); // Employee code
        $name = db_input($_REQUEST['name'] ?? ''); // Driver name
        $mobNum = db_input($_REQUEST['mobNum'] ?? ''); // Mobile number
        $availability = $_REQUEST['availability'] ?? []; // Area availability array
        $batchNo = db_input($_REQUEST['batchNo'] ?? ''); // Batch number (vBatchNo)
          
        $dateOfExp = db_input($_REQUEST['dateOfExp'] ?? ''); // Expiry date (dExpiry)

        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Driver ID is required for update"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Basic validation
        if (empty($name)) {
            echo json_encode([
                "error" => [
                    "message" => "Driver name is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if (empty($mobNum)) {
            echo json_encode([
                "error" => [
                    "message" => "Mobile number is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate duplicates
        $validation = validateDriverData($mobNum, $empCode, $id);
        if (!$validation['valid']) {
            echo json_encode([
                "error" => [
                    "message" => $validation['message']
                ],
                "statusCode" => 409
            ]);
            exit;
        }

        // Update driver with new structure
        $sql = "UPDATE driver SET 
                    vName = '$name',
                    vMobileNum = '$mobNum',
                    vEmpCode = '$empCode',
                    iVendorID = $vendorID,
                    iType = $type,
                    vBatchNo = '$batchNo',
                    dExpiry = " . (!empty($dateOfExp) ? "'$dateOfExp'" : "NULL") . "
                WHERE iDriverID = $id AND cStatus = 'A'";

        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            // Update availability areas - first delete existing associations
            $deleteAreaSql = "DELETE FROM driver_area_assoc WHERE iDriverID = $id";
            sql_query($deleteAreaSql);

            // Insert new area associations
            if (is_array($availability) && !empty($availability)) {
                foreach ($availability as $areaId) {
                    $areaId = intval($areaId);
                    if ($areaId > 0) {
                        $areaSql = "INSERT INTO driver_area_assoc (iDriverID, iAreaID) VALUES ($id, $areaId)";
                        sql_query($areaSql);
                    }
                }
            }

            // Log the update operation
            LogMasterEdit($id, 'DRV', 'U', $name, '', $user_id);

            echo json_encode([
                "data" => [
                    "message" => "Driver updated successfully"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            // Log the update operation even if no changes were made
            LogMasterEdit($id, 'DRV', 'U', $name, '', $user_id);
            
            echo json_encode([
                "data" => [
                    "message" => "No changes were made to driver"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to update driver"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE 5: ADD_DRIVER =====================
    case 'ADD_DRIVER':
        // Handle form data with the new structure
        $type = intval($_REQUEST['type'] ?? 0); // Driver type
        $vendorID = intval($_REQUEST['vendorID'] ?? 0); // Vendor ID
        $empCode = db_input($_REQUEST['empCode'] ?? ''); // Employee code
        $name = db_input($_REQUEST['name'] ?? ''); // Driver name
        $mobNum = db_input($_REQUEST['mobNum'] ?? ''); // Mobile number
        $availability = $_REQUEST['availability'] ?? []; // Area availability array
        $batchNo = db_input($_REQUEST['batchNo'] ?? ''); // Batch number (vBatchNo)
        $dateOfExp = db_input($_REQUEST['dateOfExp'] ?? ''); // Expiry date (dExpiry)
        $cStatus = 'A'; // Default active status

        // Basic validation
        if (empty($name)) {
            echo json_encode([
                "error" => [
                    "message" => "Driver name is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if (empty($mobNum)) {
            echo json_encode([
                "error" => [
                    "message" => "Mobile number is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if ($vendorID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Vendor is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate duplicates
        $validation = validateDriverData($mobNum, $empCode, 0);
        if (!$validation['valid']) {
            echo json_encode([
                "error" => [
                    "message" => $validation['message']
                ],
                "statusCode" => 409
            ]);
            exit;
        }

        $iDriverID = NextID('iDriverID', 'driver');

        // Insert driver with new fields including vBatchNo and dExpiry
        $sql = "INSERT INTO driver (iDriverID, vName, vMobileNum, vEmpCode, iVendorID, iType, vBatchNo, dExpiry, iRank, cStatus) 
                VALUES ($iDriverID, '$name', '$mobNum', '$empCode', $vendorID, $type, '$batchNo', 
                    " . (!empty($dateOfExp) ? "'$dateOfExp'" : "NULL") . ", $iDriverID, '$cStatus')";

        if (sql_query($sql)) {
            // Handle availability areas array - insert multiple area associations
            if (is_array($availability) && !empty($availability)) {
                foreach ($availability as $areaId) {
                    $areaId = intval($areaId);
                    if ($areaId > 0) {
                        $areaSql = "INSERT INTO driver_area_assoc (iDriverID, iAreaID) VALUES ($iDriverID, $areaId)";
                        sql_query($areaSql);
                    }
                }
            }

            // Log the add operation
            LogMasterEdit($iDriverID, 'DRV', 'I', $name, '', $user_id);

            echo json_encode([
                "data" => [
                    "message" => "Driver added successfully"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to add driver"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE 6: DELETE_DRIVER =====================
    case 'DELETE_DRIVER':
        $id = intval($_REQUEST['iDriverID'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Driver ID is required for deletion"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Update cStatus to 'X' instead of actual deletion
        $sql = "UPDATE driver SET cStatus = 'X' WHERE iDriverID = $id AND cStatus != 'X'";
        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            // Log the delete operation
            LogMasterEdit($id, 'DRV', 'D', '', '', $user_id);

            echo json_encode([
                "data" => [
                    "message" => "Driver deleted successfully"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            echo json_encode([
                "data" => [
                    "message" => "Driver not found or already deleted"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to delete driver"
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