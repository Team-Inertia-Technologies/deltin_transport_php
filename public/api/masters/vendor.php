<?php

ini_set('display_errors', 1);

include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");
// $id=1;
// $mode = 'LIST';
//$Token=EncodeParam($id);

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
// Define arrays for ONLOAD mode
$AREA_ARR_RAW = GetXArrFromYID("SELECT iAreaID, vName FROM gen_area where cStatus='A' ORDER BY iRank", "3");
$STATE_ARR_RAW = GetXArrFromYID("SELECT iStateID, vName FROM gen_state where cStatus='A' ORDER BY vName", "3");

// Convert to id-label object format
$availableOpt = [];
foreach ($AREA_ARR_RAW as $id => $label) {
    $availableOpt[] = ['id' => intval($id), 'label' => $label];
}

$stateOpt = [['id' => 0, 'title' => 'Choose']];
foreach ($STATE_ARR_RAW as $id => $label) {
    $stateOpt[] = ['id' => intval($id), 'title' => $label];
}

$serviceOpt = [];
foreach ($SERVICE_OFFERED as $id => $label) {
    $serviceOpt[] = ['id' => $id, 'title' => $label];
}


function validateVendorData($vContactNum, $vEmail, $excludeVendorID = 0)
{
    $conditions = [];

    if (!empty($vContactNum)) {
        $conditions[] = "vContactNum = '$vContactNum'";
    }

    if (!empty($vEmail)) {
        $conditions[] = "vEmail = '$vEmail'";
    }

    if (empty($conditions)) {
        return ['valid' => true];
    }

    $sql = "SELECT iVendorID, vName, vContactNum, vEmail 
            FROM vendor 
            WHERE (" . implode(' OR ', $conditions) . ") 
            AND iVendorID != $excludeVendorID 
            AND cStatus != 'X'";

    $res = sql_query($sql);

    while ($row = sql_fetch_assoc($res)) {
        if (!empty($vContactNum) && $row['vContactNum'] === $vContactNum) {
            return [
                'valid' => false,
                'message' => "Contact number already exists for vendor: " . $row['vName']
            ];
        }
        if (!empty($vEmail) && $row['vEmail'] === $vEmail) {
            return [
                'valid' => false,
                'message' => "Email already exists for vendor: " . $row['vName'] 
            ];
        }
    }

    return ['valid' => true];
}
switch ($mode) {

    // ===================== CASE 1: LIST =====================
    case 'LIST':
        $sql = "SELECT iVendorID, vName, vContactPerson, vContactNum, vEmail, cType, cStatus 
                FROM vendor 
                WHERE cStatus = 'A' 
                ORDER BY iRank DESC";
        $res = sql_query($sql);

        $rowData = [];
        while ($row = sql_fetch_assoc($res)) {
            $vendorID = intval($row['iVendorID']);

            // Get availability areas and names in one query using JOIN
            $areaSql = "SELECT vaa.iAreaID, ga.vName 
                        FROM vendor_area_assoc vaa 
                        LEFT JOIN gen_area ga ON vaa.iAreaID = ga.iAreaID AND ga.cStatus = 'A'
                        WHERE vaa.iVendorID = $vendorID 
                        ORDER BY ga.iRank";
            $areaRes = sql_query($areaSql);

            $availabilityAreas = [];
            $availabilityNames = [];
            while ($areaRow = sql_fetch_assoc($areaRes)) {
                $availabilityAreas[] = intval($areaRow['iAreaID']);
                if (!empty($areaRow['vName'])) {
                    $availabilityNames[] = $areaRow['vName'];
                }
            }

            $rowData[] = [
                'id' => $vendorID,
                'companyName' => $row['vName'] ?? '',
                'fullName' => $row['vContactPerson'] ?? '',
                'mobileNumber' => $row['vContactNum'] ?? '',
                'email' => $row['vEmail'] ?? '',
                'serviceOff' => $row['cType'] ?? '',
                'availabilityID' => $availabilityAreas,
                'availability' => $availabilityNames
            ];
        }

        echo json_encode([
            "statusCode" => 200,
            "message" => "Vendor list fetched successfully",
            "data" => [
                "rowData" => $rowData,
                "availableOpt" => $availableOpt,
                "serviceOpt" => $serviceOpt
            ]
        ]);
        break;
    // ===================== CASE 2: ONLOAD =====================
    case 'ONLOAD':
        // Return only the required arrays for form initialization
        echo json_encode([
            "data" => [
                "stateOpt" => $stateOpt,
                "serviceOpt" => $serviceOpt,
                "availableOpt" => $availableOpt
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE 3: VENDOR_DETAILS =====================
    case 'VENDOR_DETAILS':
        $id = isset($request['iVendorID']) ? intval($request['iVendorID']) : (isset($_REQUEST['iVendorID']) ? intval($_REQUEST['iVendorID']) : 0);
        if ($id <= 0) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Invalid Vendor ID"
            ]);
            exit;
        }

        // Fetch vendor details
        $sql = "SELECT iVendorID, vName, cType, vPanNo, vContactPerson, vContactNum, vEmail, 
                       cTDSApplicable, fTDSperc, vGSTIN, iStateCode, vBankAcctNum, vBankIFSC, 
                       vDetails, iRank, cStatus, vAddress
                FROM vendor 
                WHERE iVendorID = $id";
        $res = sql_query($sql);

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "statusCode" => 404,
                "message" => "Vendor not found"
            ]);
            exit;
        }

        $vendor = sql_fetch_assoc($res);

        // Fetch availability areas for this vendor
        $areaSql = "SELECT iAreaID FROM vendor_area_assoc WHERE iVendorID = $id";
        $areaRes = sql_query($areaSql);

        $availabilityAreas = [];
        while ($areaRow = sql_fetch_assoc($areaRes)) {
            $availabilityAreas[] = intval($areaRow['iAreaID']);
        }

        // // Fetch active vehicles for this vendor
        // $vehicleSql = "SELECT iVehicleID, vRnum as vVehicleNo 
        //                FROM vehicle 
        //                WHERE iVendorID = $id AND cStatus = 'A'
        //                ORDER BY iVehicleID DESC";
        // $vehicleRes = sql_query($vehicleSql);

        // $vehicleArr = [];
        // while ($v = sql_fetch_assoc($vehicleRes)) {
        //     $vehicleArr[] = $v;
        // }

        // Map database fields to form field names
        $vendorData = [
            'iVendorID' => intval($vendor['iVendorID']),
            'comName' => $vendor['vName'] ?? '',
            'perName' => $vendor['vContactPerson'] ?? '',
            'perConNum' => $vendor['vContactNum'] ?? '',
            'email' => $vendor['vEmail'] ?? '',
            'comAdd' => $vendor['vAddress'] ?? '',
            'state' => intval($vendor['iStateCode'] ?? 0),
            'remarks' => $vendor['vDetails'] ?? '',
            'panNo' => $vendor['vPanNo'] ?? '',
            'gstNo' => $vendor['vGSTIN'] ?? '',
            'tdsApp' => $vendor['cTDSApplicable'] ?? 'N',
            'tdsPercentage' => floatval($vendor['fTDSperc'] ?? 0),
            'serviceOff' => $vendor['cType'] ?? '',
            'availability' => $availabilityAreas,
            'bankAccNo' => $vendor['vBankAcctNum'] ?? '',
            'bankIfscCode' => $vendor['vBankIFSC'] ?? '',
            'cStatus' => $vendor['cStatus'] ?? 'A'
            //'vehicles' => $vehicleArr
        ];

        echo json_encode([
            "statusCode" => 200,
            "message" => "Vendor details fetched successfully",
            "data" => [
                "vendor" => $vendorData,
                "stateOpt" => $stateOpt,
                "serviceOpt" => $serviceOpt,
                "availableOpt" => $availableOpt
            ]
        ]);
        break;



    // ===================== CASE 4: UPDATE_VENDOR =====================
    case 'UPDATE_VENDOR':
        // Map form data to database fields
       
        
        $id = intval($request['iVendorID'] ?? 0);
        $vName = db_input($request['comName'] ?? '');
        $vContactPerson = db_input($request['perName'] ?? '');
        $vContactNum = db_input($request['perConNum'] ?? '');
        $vEmail = db_input($request['email'] ?? '');
        $vAddress = db_input($request['comAdd'] ?? '');
        $iStateCode = intval($request['state'] ?? 0);
        $vDetails = db_input($request['remarks'] ?? '');
        $vPanNo = db_input($request['panNo'] ?? '');
        $vGSTIN = db_input($request['gstNo'] ?? '');
        $cTDSApplicable = db_input($request['tdsApp'] ?? 'N');
        $fTDSperc = floatval($request['tdsPercentage'] ?? 0);
        $cType = db_input($request['serviceOff'] ?? '');
        $availability = $request['availability'] ?? []; // Handle as array
        $vBankAcctNum = db_input($request['bankAccNo'] ?? '');
        $vBankIFSC = db_input($request['bankIfscCode'] ?? '');
        $cStatus = db_input($request['cStatus'] ?? 'A');
        if ($id <= 0) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Vendor ID is required for update"
            ]);
            exit;
        }

        // Basic validation
        if (empty($vName)) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Company name is required"
            ]);
            exit;
        }

        if (empty($vContactPerson)) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Contact person name is required"
            ]);
            exit;
        }

        // Validate duplicates
        $validation = validateVendorData($vContactNum, $vEmail, $id);
        if (!$validation['valid']) {
            echo json_encode([
                "statusCode" => 409,
                "message" => $validation['message']
            ]);
            exit;
        }

        // Update vendor details
        $sql = "UPDATE vendor SET 
                    vName = '$vName',
                    cType = '$cType',
                    vPanNo = '$vPanNo',
                    vContactPerson = '$vContactPerson',
                    vContactNum = '$vContactNum',
                    vEmail = '$vEmail',
                    cTDSApplicable = '$cTDSApplicable',
                    fTDSperc = $fTDSperc,
                    vGSTIN = '$vGSTIN',
                    iStateCode = $iStateCode,
                    vBankAcctNum = '$vBankAcctNum',
                    vBankIFSC = '$vBankIFSC',
                    vDetails = '$vDetails',
                    cStatus = '$cStatus',
                    vAddress = '$vAddress'
                WHERE iVendorID = $id AND cStatus != 'X'";

        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            // Update availability areas - first delete existing associations
            $deleteAreaSql = "DELETE FROM vendor_area_assoc WHERE iVendorID = $id";
            sql_query($deleteAreaSql);

            // Insert new area associations
            if (is_array($availability) && !empty($availability)) {
                foreach ($availability as $areaId) {
                    $areaId = intval($areaId);
                    if ($areaId > 0) {
                        $areaSql = "INSERT INTO vendor_area_assoc (iVendorID, iAreaID) VALUES ($id, $areaId)";
                        sql_query($areaSql);
                    }
                }
            }

            echo json_encode([
                "statusCode" => 200,
                "message" => "Vendor updated successfully"
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            echo json_encode([
                "statusCode" => 404,
                "message" => "Vendor not found or no changes made"
            ]);
        } else {
            echo json_encode([
                "statusCode" => 500,
                "message" => "Failed to update vendor"
            ]);
        }
        break;

    // ===================== CASE 5: ADD_VENDOR =====================
    case 'ADD_VENDOR':
        // Map form data to database fields
        $vName = db_input($request['comName'] ?? '');
        $vContactPerson = db_input($request['perName'] ?? '');
        $vContactNum = db_input($request['perConNum'] ?? '');
        $vEmail = db_input($request['email'] ?? '');
        $vAddress = db_input($request['comAdd'] ?? '');
        $iStateCode = intval($request['state'] ?? 0);
        $vDetails = db_input($request['remarks'] ?? '');
        $vPanNo = db_input($request['panNo'] ?? '');
        $vGSTIN = db_input($request['gstNo'] ?? '');
        $cTDSApplicable = db_input($request['tdsApp'] ?? 'N');
        $fTDSperc = floatval($request['tdsPercentage'] ?? 0);
        $cType = db_input($request['serviceOff'] ?? '');
        $availability = $request['availability'] ?? []; // Handle as array
        $vBankAcctNum = db_input($request['bankAccNo'] ?? '');
        $vBankIFSC = db_input($request['bankIfscCode'] ?? '');
        $cStatus = 'A'; // Default active status

        // Basic validation
        if (empty($vName)) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Company name is required"
            ]);
            exit;
        }

        if (empty($vContactPerson)) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Contact person name is required"
            ]);
            exit;
        }

        // Validate duplicates
        $validation = validateVendorData($vContactNum, $vEmail, 0);
        if (!$validation['valid']) {
            echo json_encode([
                "statusCode" => 409,
                "message" => $validation['message']
            ]);
            exit;
        }

        $iVendorID = NextID('iVendorID', 'vendor');
        $sql = "INSERT INTO vendor (iVendorID, vName, cType, vPanNo, vContactPerson, vContactNum, vEmail,
                    cTDSApplicable, fTDSperc, vGSTIN, iStateCode, vBankAcctNum, vBankIFSC,
                    vDetails, iRank, cStatus, vAddress
                ) VALUES ($iVendorID, '$vName', '$cType', '$vPanNo', '$vContactPerson', '$vContactNum', '$vEmail',
                    '$cTDSApplicable', $fTDSperc, '$vGSTIN', $iStateCode, '$vBankAcctNum', '$vBankIFSC',
                    '$vDetails', $iVendorID, '$cStatus', '$vAddress')";

        if (sql_query($sql)) {
            // Handle availability areas array - insert multiple area associations
            if (is_array($availability) && !empty($availability)) {
                foreach ($availability as $areaId) {
                    $areaId = intval($areaId);
                    if ($areaId > 0) {
                        $areaSql = "INSERT INTO vendor_area_assoc (iVendorID, iAreaID) VALUES ($iVendorID, $areaId)";
                        sql_query($areaSql);
                    }
                }
            }

            echo json_encode([
                "statusCode" => 200,
                "message" => "Vendor added successfully",
                "data" => ["iVendorID" => $iVendorID]
            ]);
        } else {
            echo json_encode([
                "statusCode" => 500,
                "message" => "Failed to add vendor"
            ]);
        }
        break;


    // ===================== CASE 6: DELETE =====================
    case 'DELETE':
        $id = intval($request['iVendorID'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Vendor ID is required for deletion"
            ]);
            exit;
        }

        // Update cStatus to 'X' instead of actual deletion
        $sql = "UPDATE vendor SET cStatus = 'X' WHERE iVendorID = $id AND cStatus != 'X'";
        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            echo json_encode([
                "statusCode" => 200,
                "message" => "Vendor deleted successfully"
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            echo json_encode([
                "statusCode" => 404,
                "message" => "Vendor not found or already deleted"
            ]);
        } else {
            echo json_encode([
                "statusCode" => 500,
                "message" => "Failed to delete vendor"
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
