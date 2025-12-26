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


// Validate user_id exists in user table
if ($user_id <= 0) {
    echo json_encode([
        "error" => [
            "message" => "Invalid or missing user token"
        ],
        "statusCode" => 401
    ]);
    exit;
}

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
$VEHICLE_CATEGORY_ARR = GetXArrFromYID("SELECT iVCatID, vName FROM vehicle_category WHERE cStatus='A' ORDER BY vName", "3");
// Function to validate vehicle registration number
function validateVehicleData($vRnum, $excludeVehicleID = 0)
{
    if (empty($vRnum)) {
        return ['valid' => true];
    }

    $sql = "SELECT iVehicleID, vName FROM vehicle 
            WHERE vRnum = '" . db_input($vRnum) . "' AND iVehicleID != $excludeVehicleID AND cStatus = 'A'";

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
        // $AREA_ARR_RAW = GetXArrFromYID("SELECT iFlt_StationID, vName FROM gen_area where cStatus='A' ORDER BY iRank", "3");
       
         $AREA_ARR_RAW = GetXArrFromYID("SELECT iFlt_StationID , vName FROM fleet_station where cStatus='A' ORDER BY iRank", "3");
        $availableOpt = [];
        foreach ($AREA_ARR_RAW as $id => $label) {
            $availableOpt[] = ['id' => intval($id), 'label' => $label];
        }
        $driverTypeOpt = [['id' => 0, 'title' => 'Choose']];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $title) {
            $driverTypeOpt[] = [
                'id' => intval($id),
                'title' => $title
            ];
        }

        $categoryOpt = [['id' => 0, 'title' => 'Choose']];
        foreach ($VEHICLE_CATEGORY_ARR as $id => $title) {
            $categoryOpt[] = [
                'id' => intval($id),
                'title' => $title
            ];
        }

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

            "data" => [
                "message" => "Options loaded successfully",
                'availableOpt' => $availableOpt,
                'driverTypeOpt' => $driverTypeOpt,
                'categoryOpt' => $categoryOpt,
                'vendorOpt' => $vendorOpt
            ]
        ]);
        break;

    // ===================== CASE 2: LIST =====================
    case 'LIST':

        $sql = "SELECT v.iVehicleID, v.iVendorID, v.iSeats, v.vRnum, v.iType,
                       v.fRate, vn.vName as vendor_name, c.iCapacity as capacity,c.vName as catName 
                FROM vehicle v
                LEFT JOIN vendor vn ON v.iVendorID = vn.iVendorID AND vn.cStatus = 'A'
                LEFT JOIN vehicle_category c ON v.iCatID = c.iVCatID AND c.cStatus = 'A'
                WHERE v.cStatus = 'A' 
                ORDER BY v.iVehicleID DESC";
        $res = sql_query($sql);

        $data = [];
        $rowData = [];
        while ($row = sql_fetch_assoc($res)) {
            $vehicleID = intval($row['iVehicleID']);

            $areaSql = "SELECT vaa.iFlt_StationID, ga.vName 
                        FROM vehicle_station_assoc vaa 
                        LEFT JOIN fleet_station ga ON vaa.iFlt_StationID = ga.iFlt_StationID AND ga.cStatus = 'A'
                        WHERE vaa.iVehicleID = $vehicleID 
                        ORDER BY ga.iRank";
            $areaRes = sql_query($areaSql);

            $availability = [];
            $availabilityNames = [];
            while ($areaRow = sql_fetch_assoc($areaRes)) {
                $availability[] = intval($areaRow['iFlt_StationID']);
                if (!empty($areaRow['vName'])) {
                    $availabilityNames[] = $areaRow['vName'];
                }
            }

            // Get driver type name from VEHICLE_DRIVER_TYPE array
            $driverTypeID = intval($row['iType'] ?? 0);
            $driverTypeName = isset($VEHICLE_DRIVER_TYPE[$driverTypeID]) ? $VEHICLE_DRIVER_TYPE[$driverTypeID] : '';

            $vehicle = [
                'id' => $row['iVehicleID'],
                'vehicleNumber' => db_output2($row['vRnum']),
                'vehicleCapacity' => $row['capacity'],
                'vehicleCategory' => $row['catName'],
                // 'rate' => $row['fRate'],
                'vehicleOwnerID' => $row['iVendorID'],
                'vehicleOwner' => db_output2($row['vendor_name'] ?? ''),
                'iType' => $driverTypeID,
                'driverType' => $driverTypeName,
                'availabilityID' => $availability,
                'availability' => $availabilityNames
            ];
            $rowData[] = $vehicle;
        }

        echo json_encode([
            "statusCode" => 200,

            "data" => [
                "message" => "Vehicle list fetched successfully",
                "rowData" => $rowData
            ]
        ]);
        break;

    // ===================== CASE 3: VEHICLE_DETAILS =====================
    case 'VEHICLE_DETAILS':
        $id = isset($_REQUEST['iVehicleID']) ? intval($_REQUEST['iVehicleID']) : 0;
        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Invalid Vehicle ID"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Optimized query with JOINs to get vendor and category data in single query
        $sql = "SELECT v.iVehicleID, v.vName, v.vRnum, v.iCatID, v.iVendorID, v.iSeats, v.iType,
                       v.iFlt_StationID, v.dRegistration, v.dExpiry, v.vTouristPerNo, v.dTouristPerNoExpiry, v.cStatus, 
                       vn.vName as vendor_name, c.vName as category_name
                FROM vehicle v
                LEFT JOIN vendor vn ON v.iVendorID = vn.iVendorID AND vn.cStatus = 'A'
                LEFT JOIN vehicle_category c ON v.iCatID = c.iVCatID AND c.cStatus = 'A'
                WHERE v.iVehicleID = $id";
        $res = sql_query($sql);

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle not found"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $row = sql_fetch_assoc($res);

        $areaSql = "SELECT iFlt_StationID FROM vehicle_station_assoc WHERE iVehicleID = $id";
        $areaRes = sql_query($areaSql);

        $availability = [];
        while ($areaRow = sql_fetch_assoc($areaRes)) {
            $availability[] = intval($areaRow['iFlt_StationID']);
        }

        $AREA_ARR_RAW = GetXArrFromYID("SELECT iFlt_StationID, vName FROM fleet_station where cStatus='A' ORDER BY iRank", "3");
        $availableOpt = [];
        foreach ($AREA_ARR_RAW as $id => $label) {
            $availableOpt[] = ['id' => intval($id), 'label' => $label];
        }

        $driverTypeOpt = [['id' => 0, 'title' => 'Choose']];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $title) {
            $driverTypeOpt[] = [
                'id' => intval($id),
                'title' => $title
            ];
        }

        $categoryOpt = [['id' => 0, 'title' => 'Choose']];
        foreach ($VEHICLE_CATEGORY_ARR as $id => $title) {
            $categoryOpt[] = [
                'id' => intval($id),
                'title' => $title
            ];
        }

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

            "data" => [
                "message" => "Vehicle details fetched successfully",
                'vehicleData' => [
                    'iVehicleID' => intval($row['iVehicleID']),
                    'vName' => db_output2($row['vName'] ?? ''),
                    'vRnum' => db_output2($row['vRnum'] ?? ''),
                    //      'iSeats' => intval($row['iSeats'] ?? 0),
                    'dateOfReg' => $row['dRegistration'] ?? '',
                    'dateOfExp' => $row['dExpiry'] ?? '',
                    'perNum' => db_output2($row['vTouristPerNo'] ?? ''),
                    'perNumExpiry' => $row['dTouristPerNoExpiry'] ?? '',
                    'selectedDriverType' => intval($row['iType'] ?? 0),
                    'availability' => $availability,
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
        $perNumExpiry = db_input($_REQUEST['perNumExpiry'] ?? ''); // Tourist permit expiry date

        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle ID is required for update"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        if (empty($vehiNum)) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle number is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $categoryValidation = validateCategoryData($category);
        if (!$categoryValidation['valid']) {
            echo json_encode([
                "error" => [
                    "message" => $categoryValidation['message']
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $validation = validateVehicleData($vehiNum, $id);
        if (!$validation['valid']) {
            echo json_encode([
                "error" => [
                    "message" => $validation['message']
                ],
                "statusCode" => 409
            ]);
            exit;
        }

        $sql = "UPDATE vehicle SET 
                    vRnum = '" . db_input($vehiNum) . "',
                    iCatID = $category,
                    iVendorID = $vendor,
                    iType = $type,
                    dRegistration = " . (!empty($dateOfReg) ? "'" . db_input($dateOfReg) . "'" : "NULL") . ",
                    dExpiry = " . (!empty($dateOfExp) ? "'" . db_input($dateOfExp) . "'" : "NULL") . ",
                    vTouristPerNo = '" . db_input($perNum) . "',
                    dTouristPerNoExpiry = " . (!empty($perNumExpiry) ? "'" . db_input($perNumExpiry) . "'" : "NULL") . "
                WHERE iVehicleID = $id AND cStatus = 'A'";

        $result = sql_query($sql);

        if ($result) {
            // Update availability areas - delete all existing associations first
            $deleteAreaSql = "DELETE FROM vehicle_station_assoc WHERE iVehicleID = $id";
            $deleteResult = sql_query($deleteAreaSql);

            if (is_array($availability) && !empty($availability)) {
                foreach ($availability as $areaId) {
                    $areaId = intval($areaId);
                    if ($areaId > 0) {
                        $areaSql = "INSERT INTO vehicle_station_assoc (iVehicleID, iFlt_StationID) VALUES ($id, $areaId)";
                        sql_query($areaSql);
                    }
                }
            }

            LogMasterEdit($id, 'VHC', 'U', $vehiNum, '', $user_id);

            echo json_encode([
                "data" => [
                    "message" => "Vehicle updated successfully"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            // Update availability areas even if no vehicle changes were made
            $deleteAreaSql = "DELETE FROM vehicle_station_assoc WHERE iVehicleID = $id";
            sql_query($deleteAreaSql);


            if (is_array($availability) && !empty($availability)) {
                foreach ($availability as $areaId) {
                    $areaId = intval($areaId);
                    if ($areaId > 0) {
                        $areaSql = "INSERT INTO vehicle_station_assoc (iVehicleID, iFlt_StationID) VALUES ($id, $areaId)";
                        sql_query($areaSql);
                    }
                }
            }
            LogMasterEdit($id, 'VHC', 'U', $vehiNum, '', $user_id);

            echo json_encode([
                "data" => [
                    "message" => "Vehicle availability updated successfully"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to update vehicle"
                ],
                "statusCode" => 500
            ]);
        }
        break;
    // ===================== CASE 5: ADD =====================
    case 'ADD_VEHICLE':

        $type = intval($_REQUEST['type'] ?? 0); // Driver type
        $category = intval($_REQUEST['category'] ?? 0); // Vehicle category
        $vendor = intval($_REQUEST['vendor'] ?? 0); // Vendor ID
        $vehiNum = db_input($_REQUEST['vehiNum'] ?? ''); // Vehicle number
        $availability = $_REQUEST['availability'] ?? []; // Area availability array
        $dateOfReg = db_input($_REQUEST['dateOfReg'] ?? ''); // Registration date
        $dateOfExp = db_input($_REQUEST['dateOfExp'] ?? ''); // Expiry date

        $perNum = db_input($_REQUEST['perNum'] ?? ''); // Permit number
        $perNumExpiry = db_input($_REQUEST['perNumExpiry'] ?? ''); // Tourist permit expiry date
        $cStatus = 'A'; // Default active status

        // Basic validation
        if (empty($vehiNum)) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle number is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate category
        $categoryValidation = validateCategoryData($category);
        if (!$categoryValidation['valid']) {
            echo json_encode([
                "error" => [
                    "message" => $categoryValidation['message']
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $validation = validateVehicleData($vehiNum, 0);
        if (!$validation['valid']) {
            echo json_encode([
                "error" => [
                    "message" => $validation['message']
                ],
                "statusCode" => 409
            ]);
            exit;
        }

        $iVehicleID = NextID('iVehicleID', 'vehicle');

        // Using the newly added database fields
        $sql = "INSERT INTO vehicle (iVehicleID, vRnum, iCatID, iVendorID, iType, dRegistration, dExpiry, vTouristPerNo, dTouristPerNoExpiry, cStatus) 
                VALUES ($iVehicleID, '" . db_input($vehiNum) . "', $category, $vendor, $type, 
                    " . (!empty($dateOfReg) ? "'" . db_input($dateOfReg) . "'" : "NULL") . ", 
                    " . (!empty($dateOfExp) ? "'" . db_input($dateOfExp) . "'" : "NULL") . ", 
                    '" . db_input($perNum) . "', 
                    " . (!empty($perNumExpiry) ? "'" . db_input($perNumExpiry) . "'" : "NULL") . ", 
                    '" . db_input($cStatus) . "')";

        if (sql_query($sql)) {
            if (is_array($availability) && !empty($availability)) {
                foreach ($availability as $areaId) {
                    $areaId = intval($areaId);
                    if ($areaId > 0) {
                        $areaSql = "INSERT INTO vehicle_station_assoc (iVehicleID, iFlt_StationID) VALUES ($iVehicleID, $areaId)";
                        sql_query($areaSql);
                    }
                }
            }

            // Log the add operation
            LogMasterEdit($iVehicleID, 'VHC', 'I', $vehiNum, '', $user_id);

            echo json_encode([
                "statusCode" => 200,

                "data" => [
                    "message" => "Vehicle added successfully",
                    "iVehicleID" => $iVehicleID
                ]
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to add vehicle"
                ],
                "statusCode" => 500
            ]);
        }
        break;


    // ===================== CASE 6: DELETE_VEHICLE =====================
    case 'DELETE_VEHICLE':
        $id = intval($_REQUEST['iVehicleID'] ?? 0);

        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle ID is required for deletion"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $sql = "UPDATE vehicle SET cStatus = 'X' WHERE iVehicleID = $id AND cStatus != 'X'";
        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            
            LogMasterEdit($id, 'VHC', 'D', '', '', $user_id);

            echo json_encode([
                "statusCode" => 200,
                "data" => [
                    "message" => "Vehicle deleted successfully"
                ]
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            echo json_encode([
                "statusCode" => 200,
                "data" => [
                    "message" => "Vehicle not found or already deleted"
                ]
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to delete vehicle"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE 7: VEHICLE_DETAILS_WITH_TRIPS =====================
    case 'VEHICLE_DETAILS_WITH_TRIPS':
        $vehicleID = intval($_REQUEST['vehicleID'] ?? 0);
        
        if ($vehicleID <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle ID is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $vehicleSql = "SELECT v.iVehicleID, v.vRnum, vc.vName as categoryName
                       FROM vehicle v
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       WHERE v.iVehicleID = $vehicleID AND v.cStatus = 'A'";
        $vehicleRes = sql_query($vehicleSql);
        
        if (sql_num_rows($vehicleRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle not found"
                ],
                "statusCode" => 404
            ]);
            exit;
        }
        
        $vehicleRow = sql_fetch_assoc($vehicleRes);
        
        $currentDriverSql = "SELECT d.vName as driverName, d.vMobileNum as driverMobile
                             FROM driver_vehicle_assoc dva
                             LEFT JOIN driver d ON dva.iDriverID = d.iDriverID AND d.cStatus = 'A'
                             WHERE dva.iVehicleID = $vehicleID AND dva.cStatus = 'A'
                             ORDER BY dva.dtAssigned_From DESC, dva.iDVID DESC
                             LIMIT 1";
        $currentDriverRes = sql_query($currentDriverSql);
        
        $currentDriver = null;
        $currentDriverMobile = null;
        if (sql_num_rows($currentDriverRes) > 0) {
            $driverRow = sql_fetch_assoc($currentDriverRes);
            $currentDriver = $driverRow['driverName'];
            $currentDriverMobile = $driverRow['driverMobile'];
        }
 
        $currentDateTime = date('Y-m-d H:i:s');
        $previousTripsSql = "SELECT fb.vName as guestName, fb.iPax, fb.cBookingFor, fb.vPickUpTime
                             FROM fleet_booking fb
                             WHERE fb.iVehicleID = $vehicleID 
                             AND fb.cStatus = 'A'
                             AND fb.vPickUpTime < '$currentDateTime'
                             ORDER BY fb.vPickUpTime DESC
                             LIMIT 10";
        $previousTripsRes = sql_query($previousTripsSql);
        
        $previousTrips = [];
        while ($tripRow = sql_fetch_assoc($previousTripsRes)) {
            $previousTrips[] = [
                'guestName' => db_output2($tripRow['guestName']),
                'pax' => intval($tripRow['iPax']),
                'type' => ($tripRow['cBookingFor'] === 'S') ? 'Staff' : 'Guest',
                'pickUpTime' => $tripRow['vPickUpTime']
            ];
        }

        $nextTripsSql = "SELECT fb.vName as guestName, fb.iPax, fb.cBookingFor, fb.vPickUpTime
                         FROM fleet_booking fb
                         WHERE fb.iVehicleID = $vehicleID 
                         AND fb.cStatus = 'A'
                         AND fb.vPickUpTime >= '$currentDateTime'
                         ORDER BY fb.vPickUpTime ASC
                         LIMIT 10";
        $nextTripsRes = sql_query($nextTripsSql);
        
        $nextTrips = [];
        while ($tripRow = sql_fetch_assoc($nextTripsRes)) {
            $nextTrips[] = [
                'guestName' => db_output2($tripRow['guestName']),
                'pax' => intval($tripRow['iPax']),
                'type' => ($tripRow['cBookingFor'] === 'S') ? 'Staff' : 'Guest',
                'pickUpTime' => $tripRow['vPickUpTime']
            ];
        }
        
        echo json_encode([
            "data" => [
                "vehicleRNum" => db_output2($vehicleRow['vRnum']),
                "categoryName" => db_output2($vehicleRow['categoryName'] ?? ''),
                "currentlyAssignedDriver" => $currentDriver,
                "currentlyAssignedDriverMobile" => $currentDriverMobile,
                "tripHistory" => [
                    "previousTrips" => $previousTrips,
                    "nextTrips" => $nextTrips
                ]
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
