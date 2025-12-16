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
        $sql = "SELECT d.iDriverID, d.vName, d.vMobileNum, d.vEmpCode, d.iVendorID,  d.iType,
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

            // Get driver type name from VEHICLE_DRIVER_TYPE array
            $driverTypeID = intval($row['iType'] ?? 0);
            $driverTypeName = isset($VEHICLE_DRIVER_TYPE[$driverTypeID]) ? $VEHICLE_DRIVER_TYPE[$driverTypeID] : '';

            $driver = [
                'id' => intval($row['iDriverID']),
                'fullName' => db_output2($row['vName']),
                'mobileNumber' => db_output2($row['vMobileNum']),
                'owner' => db_output2($row['vendor_name'] ?? ''),
                'type' => $driverTypeID,
                'driverType' => $driverTypeName,
                'status' =>'A'
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
                'title' => db_output2($title)
            ];
        }

        // Vendor options with "choose" option
        $vendorOpt = [['id' => 0, 'title' => 'Choose']];
        $vendorSql = "SELECT iVendorID, vName FROM vendor WHERE cStatus = 'A' ORDER BY vName";
        $vendorRes = sql_query($vendorSql);
        while ($vendorRow = sql_fetch_assoc($vendorRes)) {
            $vendorOpt[] = [
                'id' => intval($vendorRow['iVendorID']),
                'title' => db_output2($vendorRow['vName'])
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

        $AREA_ARR_RAW = GetXArrFromYID("SELECT iAreaID, vName FROM gen_area where cStatus='A' ORDER BY iRank", "3");
        $availableOpt = [];
        foreach ($AREA_ARR_RAW as $id => $label) {
            $availableOpt[] = ['id' => intval($id), 'label' => $label];
        }

        $driverTypeOpt = [['id' => 0, 'title' => 'Choose']];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $title) {
            $driverTypeOpt[] = [
                'id' => intval($id),
                'title' => db_output2($title)
            ];
        }

        $vendorOpt = [['id' => 0, 'title' => 'Choose']];
        $vendorSql = "SELECT iVendorID, vName FROM vendor WHERE cStatus = 'A' ORDER BY vName";
        $vendorRes = sql_query($vendorSql);
        while ($vendorRow = sql_fetch_assoc($vendorRes)) {
            $vendorOpt[] = [
                'id' => intval($vendorRow['iVendorID']),
                'title' => db_output2($vendorRow['vName'])
            ];
        }

        // Driver data using the same variable names as in ADD/UPDATE
        $driverData = [
            'iDriverID' => intval($row['iDriverID']),
            'name' => db_output2($row['vName']),
            'mobNum' => db_output2($row['vMobileNum']),
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

        if ($result) {
            // Update availability areas - delete all existing associations first
            $deleteAreaSql = "DELETE FROM driver_area_assoc WHERE iDriverID = $id";
            $deleteResult = sql_query($deleteAreaSql);
            
            // Insert new area associations - select all and add again
            if (is_array($availability) && !empty($availability)) {
                foreach ($availability as $areaId) {
                    $areaId = intval($areaId);
                    if ($areaId > 0) {
                        $areaSql = "INSERT INTO driver_area_assoc (iDriverID, iAreaID) VALUES ($id, $areaId)";
                        sql_query($areaSql);
                    }
                }
            }

            LogMasterEdit($id, 'DRV', 'U', $name, '', $user_id);

            echo json_encode([
                "data" => [
                    "message" => "Driver updated successfully"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            // Update availability areas even if no driver changes were made
            $deleteAreaSql = "DELETE FROM driver_area_assoc WHERE iDriverID = $id";
            sql_query($deleteAreaSql);
            
            if (is_array($availability) && !empty($availability)) {
                foreach ($availability as $areaId) {
                    $areaId = intval($areaId);
                    if ($areaId > 0) {
                        $areaSql = "INSERT INTO driver_area_assoc (iDriverID, iAreaID) VALUES ($id, $areaId)";
                        sql_query($areaSql);
                    }
                }
            }
            
            LogMasterEdit($id, 'DRV', 'U', $name, '', $user_id);
            
            echo json_encode([
                "data" => [
                    "message" => "Driver availability updated successfully"
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

    // ===================== CASE 7: VIEW_VEHICLES =====================
    case 'VIEW_VEHICLES':
        // Get the current driver ID from request
        $currentDriverID = intval($_REQUEST['driverID'] ?? 0);
        
        // Get filter parameters
        $keyword = db_input($_REQUEST['keyword'] ?? '');
        $categoryID = intval($_REQUEST['categoryID'] ?? 0);
        $typeID = intval($_REQUEST['typeID'] ?? 0);
        
        // Get vehicle categories array
        $vehicleCategorySql = "SELECT iVCatID, vName, iCapacity FROM vehicle_category WHERE cStatus = 'A' ORDER BY vName";
        $vehicleCategoryRes = sql_query($vehicleCategorySql);
        

        $vehicleTypeOpt = [['id' => 0, 'name' => 'All']];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $name) {
            $vehicleTypeOpt[] = ['id' => intval($id), 'name' => $name];
        }
        
        $vehicleCategories = [];
        while ($categoryRow = sql_fetch_assoc($vehicleCategoryRes)) {
            $vehicleCategories[] = [
                'id' => intval($categoryRow['iVCatID']),
                'name' => db_output2($categoryRow['vName']),
                'capacity' => intval($categoryRow['iCapacity'])
            ];
        }

        // Build WHERE conditions for filtering
        $whereConditions = ["v.cStatus = 'A'"];
        
        // Add keyword search (search in vehicle registration number and category name)
        if (!empty($keyword)) {
            $whereConditions[] = "(UPPER(v.vRnum) LIKE UPPER('%$keyword%') OR UPPER(vc.vName) LIKE UPPER('%$keyword%'))";
        }
        
        // Add category filter
        if ($categoryID > 0) {
            $whereConditions[] = "v.iCatID = $categoryID";
        }
        
        // Add type filter
        if ($typeID > 0) {
            $whereConditions[] = "v.iType = $typeID";
        }
        
        $whereClause = implode(' AND ', $whereConditions);

        // Get vehicles array with category and registration number
        $vehicleSql = "SELECT v.iVehicleID, v.vRnum, v.iCatID, v.iType as vehicletype, vc.vName as categoryName, vc.iCapacity 
                       FROM vehicle v
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       WHERE $whereClause 
                       ORDER BY v.vRnum";
        $vehicleRes = sql_query($vehicleSql);
        
        $vehicles = [];
        while ($vehicleRow = sql_fetch_assoc($vehicleRes)) {
            $vehicleID = intval($vehicleRow['iVehicleID']);
            
            // Check if current driver was the last one assigned to this vehicle
            $lastAssigned = false;
            if ($currentDriverID > 0) {
                $lastAssignmentSql = "SELECT iDriverID FROM driver_vehicle_assoc 
                                      WHERE iVehicleID = $vehicleID 
                                      ORDER BY dtAssigned_From DESC, iDVID DESC 
                                      LIMIT 1";
                $lastAssignmentRes = sql_query($lastAssignmentSql);
                
                if (sql_num_rows($lastAssignmentRes) > 0) {
                    $lastAssignmentRow = sql_fetch_assoc($lastAssignmentRes);
                    $lastAssigned = (intval($lastAssignmentRow['iDriverID']) === $currentDriverID);
                }
            }
            
            $vehicles[] = [
                'id' => $vehicleID,
                'regNo' => db_output2($vehicleRow['vRnum']),
                'vehicletype' => intval($vehicleRow['vehicletype']),
                'categoryId' => intval($vehicleRow['iCatID']),
                'categoryName' => db_output2($vehicleRow['categoryName'] ?? ''),
                'capacity' => intval($vehicleRow['iCapacity'] ?? 0),
                'lastAssigned' => $lastAssigned
            ];
        }

        echo json_encode([
            "data" => [
                "vehicleCategories" => $vehicleCategories,
                "vehicles" => $vehicles,
                "vehicleTypeOpt" => $vehicleTypeOpt
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE 8: ASSIGN_VEHICLE =====================
    case 'ASSIGN_VEHICLE':
        $driverID = intval($_REQUEST['driverID'] ?? 0);
        $vehicleID = intval($_REQUEST['vehicleID'] ?? 0);
        $assignedFrom = NOW;
        $cStatus ='A';

        // Basic validation
        if ($driverID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Driver ID is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if ($vehicleID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle ID is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate driver exists and is active
        $driverCheckSql = "SELECT iDriverID, vName FROM driver WHERE iDriverID = $driverID AND cStatus = 'A'";
        $driverCheckRes = sql_query($driverCheckSql);
        
        if (sql_num_rows($driverCheckRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Driver not found or inactive"
                ],
                "statusCode" => 404
            ]);
            exit;
        }
        
        $driverRow = sql_fetch_assoc($driverCheckRes);

        // Validate vehicle exists and is active
        $vehicleCheckSql = "SELECT iVehicleID, vRnum FROM vehicle WHERE iVehicleID = $vehicleID AND cStatus = 'A'";
        $vehicleCheckRes = sql_query($vehicleCheckSql);
        
        if (sql_num_rows($vehicleCheckRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle not found or inactive"
                ],
                "statusCode" => 404
            ]);
            exit;
        }
        
        $vehicleRow = sql_fetch_assoc($vehicleCheckRes);

        // Check if there's already an active assignment for this driver-vehicle combination
        $existingAssignmentSql = "SELECT iDVID FROM driver_vehicle_assoc 
                                  WHERE iDriverID = $driverID AND iVehicleID = $vehicleID AND cStatus = 'A'";
        $existingAssignmentRes = sql_query($existingAssignmentSql);
        
        if (sql_num_rows($existingAssignmentRes) > 0) {
            // Deallocate the existing assignment by setting dtAssigned_To
            $currentDateTime = date('Y-m-d H:i:s');
            $deallocateSql = "UPDATE driver_vehicle_assoc 
                              SET dtAssigned_To = '$currentDateTime', cStatus = 'X' 
                              WHERE iDriverID = $driverID AND iVehicleID = $vehicleID AND cStatus = 'A'";
            sql_query($deallocateSql);
        }


        $iDVID = NextID('iDVID', 'driver_vehicle_assoc');

        // Insert new vehicle assignment
        $sql = "INSERT INTO driver_vehicle_assoc (iDVID, iVehicleID, iDriverID, dtAssigned_From, iAssigned_By, cStatus) 
                VALUES ($iDVID, $vehicleID, $driverID, 
                    " . (!empty($assignedFrom) ? "'$assignedFrom'" : "NULL") . ",  
                    $user_id, '$cStatus')";

        if (sql_query($sql)) {
            // Log the assignment operation
            LogMasterEdit($iDVID, 'DVA', 'I', "Driver: " . $driverRow['vName'] . " -> Vehicle: " . $vehicleRow['vRnum'], '', $user_id);

            echo json_encode([
                "data" => [
                    "message" => "Assigned successfully",
                    "assignmentID" => $iDVID,
                    "driverName" => db_output2($driverRow['vName']),
                    "vehicleRegNo" => db_output2($vehicleRow['vRnum'])
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to assign vehicle to driver"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE 9: VIEW_ASSIGNMENTS =====================
    case 'VIEW_ASSIGNMENTS':
        $driverID = intval($_REQUEST['driverID'] ?? 0);
        $vehicleID = intval($_REQUEST['vehicleID'] ?? 0);
        $status = db_input($_REQUEST['status'] ?? 'A');

        // Build WHERE clause based on filters
        $whereConditions = ["dva.cStatus = '$status'"];
        
        if ($driverID > 0) {
            $whereConditions[] = "dva.iDriverID = $driverID";
        }
        
        if ($vehicleID > 0) {
            $whereConditions[] = "dva.iVehicleID = $vehicleID";
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get vehicle assignments with driver and vehicle details
        $assignmentSql = "SELECT dva.iDVID, dva.iDriverID, dva.iVehicleID, dva.dtAssigned_From, 
                                 dva.dtAssigned_To, dva.iAssigned_By, dva.cStatus,
                                 d.vName as driverName, d.vMobileNum as driverMobile,
                                 v.vRnum as vehicleRegNo, vc.vName as categoryName,
                                 u.vName as assignedByName
                          FROM driver_vehicle_assoc dva
                          LEFT JOIN driver d ON dva.iDriverID = d.iDriverID
                          LEFT JOIN vehicle v ON dva.iVehicleID = v.iVehicleID
                          LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID
                          LEFT JOIN users u ON dva.iAssigned_By = u.iUserID
                          WHERE $whereClause
                          ORDER BY dva.dtAssigned_From DESC, dva.iDVID DESC";
        
        $assignmentRes = sql_query($assignmentSql);
        
        $assignments = [];
        while ($assignmentRow = sql_fetch_assoc($assignmentRes)) {
            $assignments[] = [
                'assignmentID' => intval($assignmentRow['iDVID']),
                'driverID' => intval($assignmentRow['iDriverID']),
                'driverName' => db_output2($assignmentRow['driverName'] ?? ''),
                'driverMobile' => db_output2($assignmentRow['driverMobile'] ?? ''),
                'vehicleID' => intval($assignmentRow['iVehicleID']),
                'vehicleRegNo' => db_output2($assignmentRow['vehicleRegNo'] ?? ''),
                'categoryName' => db_output2($assignmentRow['categoryName'] ?? ''),
                'assignedFrom' => $assignmentRow['dtAssigned_From'],
                'assignedTo' => $assignmentRow['dtAssigned_To'],
                'assignedBy' => db_output2($assignmentRow['assignedByName'] ?? ''),
                'status' => $assignmentRow['cStatus']
            ];
        }

        echo json_encode([
            "data" => [
                "assignments" => $assignments,
                "totalCount" => count($assignments)
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE 10: DRIVER_POPUP =====================
    case 'DRIVER_POPUP':
        // Get filter parameters
        $keyword = db_input($_REQUEST['keyword'] ?? '');
        $type = intval($_REQUEST['type'] ?? 0);

        // Build WHERE conditions
        $whereConditions = ["cStatus = 'A'"];
        
        // Add keyword search (search in driver name or mobile number)
        if (!empty($keyword)) {
            $whereConditions[] = "(UPPER(vName) LIKE UPPER('%$keyword%') OR vMobileNum LIKE '%$keyword%')";
        }
        
        // Add type filter
        if ($type > 0) {
            $whereConditions[] = "iType = $type";
        }
        
        $whereClause = implode(' AND ', $whereConditions);

        // Get list of active drivers with id, name, and phone number
        $driverSql = "SELECT iDriverID, vName, vMobileNum, iType 
                      FROM driver 
                      WHERE $whereClause 
                      ORDER BY vName";
        $driverRes = sql_query($driverSql);

        $drivers = [];
        while ($row = sql_fetch_assoc($driverRes)) {
            $drivers[] = [
                'id' => intval($row['iDriverID']),
                'name' => db_output2($row['vName']),
                'phone' => db_output2($row['vMobileNum']),
                'type' => intval($row['iType'] ?? 0)
            ];
        }

        // Driver type options
        $driverTypeOpt = [];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $title) {
            $driverTypeOpt[] = [
                'id' => intval($id),
                'title' => db_output2($title)
            ];
        }

        echo json_encode([
            "data" => [
                "drivers" => $drivers,
                "driverTypeOpt" => $driverTypeOpt
            ],
            "statusCode" => 200
        ]);
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