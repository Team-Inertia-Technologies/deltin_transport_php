<?php

ini_set('display_errors', 1);

include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");
// $id=1;
// $mode = 'LIST';
//$Token=EncodeParam($id);

$request = json_decode($postdata);
$mode = $request->mode;
$Token = $request->token;
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
$AREA_ARR = GetXArrFromYID("SELECT iAreaID, vName FROM gen_area where cStatus='A'","3");
// Function to validate vendor data and check duplicates
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
        $sql = "SELECT iVendorID, vName, vContactPerson, vContactNum, vEmail, cType, iAreaID, cStatus 
                FROM vendor 
                WHERE cStatus = 'A' 
                ORDER BY iRank DESC";
        $res = sql_query($sql);

        $rowData = [];
        while ($row = sql_fetch_assoc($res)) {
            $rowData[] = [
                'id' => intval($row['iVendorID']),
                'companyName' => $row['vName'] ?? '',
                'fullName' => $row['vContactPerson'] ?? '',
                'mobileNumber' => $row['vContactNum'] ?? '',
                'email' => $row['vEmail'] ?? '',
                'serviceOff' => $row['cType'] ?? '',
                'availability' => $row['iAreaID']
            ];
        }

        echo json_encode([
            "status" => 200,
            "message" => "Vendor list fetched successfully",
            "data" => [
                "rowData" => $rowData,
                "AREA_ARR" => $AREA_ARR
            ]
        ]);
        break;


    // ===================== CASE 2: VENDOR_DETAILS =====================
    case 'VENDOR_DETAILS':
        $id = isset($_REQUEST['iVendorID']) ? intval($_REQUEST['iVendorID']) : 0;
        if ($id <= 0) {
            echo json_encode([
                "status" => 400,
                "message" => "Invalid Vendor ID"
            ]);
            exit;
        }

        // Fetch vendor details
        $sql = "SELECT iVendorID, vName, cType, vPanNo, vContactPerson, vContactNum, vEmail, 
                       cTDSApplicable, fTDSperc, vGSTIN, iStateCode, vBankAcctNum, vBankIFSC, 
                       vDetails, iRank, cStatus, vContactNum, fRate 
                FROM vendor 
                WHERE iVendorID = $id";
        $res = sql_query($sql);

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "status" => 404,
                "message" => "Vendor not found"
            ]);
            exit;
        }

        $vendor = sql_fetch_assoc($res);

        // Fetch active vehicles for this vendor
        $vehicleSql = "SELECT iVehicleID, vRnum as vVehicleNo 
                       FROM vehicle 
                       WHERE iVendorID = $id AND cStatus = 'A'
                       ORDER BY iVehicleID DESC";
        $vehicleRes = sql_query($vehicleSql);

        $vehicleArr = [];
        while ($v = sql_fetch_assoc($vehicleRes)) {
            $vehicleArr[] = $v;
        }

        // Attach vehicles to vendor data
        $vendor['vehicles'] = $vehicleArr;

        echo json_encode([
            "status" => 200,
            "message" => "Vendor details fetched successfully",
            "data" => $vendor
        ]);
        break;



    // ===================== CASE 3: UPDATE_VENDOR =====================
    case 'UPDATE_VENDOR':
        $id = intval($_REQUEST['iVendorID'] ?? 0);
        $vName = db_input($_REQUEST['vName'] ?? '');
        $cType = db_input($_REQUEST['cType'] ?? '');
        $vPanNo = db_input($_REQUEST['vPanNo'] ?? '');
        $vContactPerson = db_input($_REQUEST['vContactPerson'] ?? '');
        $vContactNum = db_input($_REQUEST['vContactNum'] ?? '');
        $vEmail = db_input($_REQUEST['vEmail'] ?? '');
        $cTDSApplicable = db_input($_REQUEST['cTDSApplicable'] ?? 'N');
        $fTDSperc = floatval($_REQUEST['fTDSperc'] ?? 0);
        $vGSTIN = db_input($_REQUEST['vGSTIN'] ?? '');
        $iStateCode = intval($_REQUEST['iStateCode'] ?? 0);
        $vBankAcctNum = db_input($_REQUEST['vBankAcctNum'] ?? '');
        $vBankIFSC = db_input($_REQUEST['vBankIFSC'] ?? '');
        $vDetails = db_input($_REQUEST['vDetails'] ?? '');
        $cStatus = db_input($_REQUEST['cStatus'] ?? 'A');
        $vContactNum = db_input($_REQUEST['vContactNum'] ?? '');
        $fRate = floatval($_REQUEST['fRate'] ?? 0);

        if ($id <= 0) {
            echo json_encode([
                "status" => 400,
                "message" => "Vendor ID is required for update"
            ]);
            exit;
        }

        // Single query to validate duplicates
        $validation = validateVendorData($vContactNum, $vEmail, $id);
        if (!$validation['valid']) {
            echo json_encode([
                "status" => 409,
                "message" => $validation['message']
            ]);
            exit;
        }

        // Check if vendor exists and update in single operation
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
                    vContactNum = '$vContactNum',
                    fRate = $fRate
                WHERE iVendorID = $id AND cStatus != 'X'";

        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            echo json_encode([
                "status" => 200,
                "message" => "Vendor updated successfully"
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            echo json_encode([
                "status" => 404,
                "message" => "Vendor not found or no changes made"
            ]);
        } else {
            echo json_encode([
                "status" => 500,
                "message" => "Failed to update vendor"
            ]);
        }
        break;

    // ===================== CASE 4: ADD_VENDOR =====================
    case 'ADD_VENDOR':
        $vName = db_input($_REQUEST['vName'] ?? '');
        $cType = db_input($_REQUEST['cType'] ?? '');
        $vPanNo = db_input($_REQUEST['vPanNo'] ?? '');
        $vContactPerson = db_input($_REQUEST['vContactPerson'] ?? '');
        $vContactNum = db_input($_REQUEST['vContactNum'] ?? '');
        $vEmail = db_input($_REQUEST['vEmail'] ?? '');
        $cTDSApplicable = db_input($_REQUEST['cTDSApplicable'] ?? 'N');
        $fTDSperc = floatval($_REQUEST['fTDSperc'] ?? 0);
        $vGSTIN = db_input($_REQUEST['vGSTIN'] ?? '');
        $iStateCode = intval($_REQUEST['iStateCode'] ?? 0);
        $vBankAcctNum = db_input($_REQUEST['vBankAcctNum'] ?? '');
        $vBankIFSC = db_input($_REQUEST['vBankIFSC'] ?? '');
        $vDetails = db_input($_REQUEST['vDetails'] ?? '');
        $cStatus = db_input($_REQUEST['cStatus'] ?? 'A');
        $vContactNum = db_input($_REQUEST['vContactNum'] ?? '');
        $fRate = floatval($_REQUEST['fRate'] ?? 0);

        // Basic validation
        if (empty($vName)) {
            echo json_encode([
                "status" => 400,
                "message" => "Vendor name is required"
            ]);
            exit;
        }

        // Single query to validate duplicates
        $validation = validateVendorData($vContactNum, $vEmail, 0);
        if (!$validation['valid']) {
            echo json_encode([
                "status" => 409,
                "message" => $validation['message']
            ]);
            exit;
        }

        $iVendorID = NextID('iVendorID', 'vendor');
        $sql = "INSERT INTO vendor (iVendorID, vName, cType, vPanNo, vContactPerson, vContactNum, vEmail,
                    cTDSApplicable, fTDSperc, vGSTIN, iStateCode, vBankAcctNum, vBankIFSC,
                    vDetails, iRank, cStatus, vContactNum, fRate
                ) VALUES ($iVendorID, '$vName', '$cType', '$vPanNo', '$vContactPerson', '$vContactNum', '$vEmail',
                    '$cTDSApplicable', $fTDSperc, '$vGSTIN', $iStateCode, '$vBankAcctNum', '$vBankIFSC',
                    '$vDetails', $iVendorID, '$cStatus', '$vContactNum', $fRate)";

        if (sql_query($sql)) {
            echo json_encode([
                "status" => 200,
                "message" => "Vendor added successfully",
                "data" => ["iVendorID" => $iVendorID]
            ]);
        } else {
            echo json_encode([
                "status" => 500,
                "message" => "Failed to add vendor"
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
