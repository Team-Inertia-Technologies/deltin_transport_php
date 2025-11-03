<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true); // Decode as associative array
$_REQUEST = array_merge($_REQUEST, $request ?? []); // Merge with $_REQUEST
$mode = $_REQUEST['mode'] ?? '';
$TIME = NOW;
if ($mode == 'REGISTER_ONLOAD') {
    $staff = sql_query("SELECT vMobile, vCode FROM staff WHERE cStatus = 'A'");

    $PHONE_ARR = [];
    $CODE_ARR = [];

    while ($row = sql_fetch_assoc($staff)) {
        $PHONE_ARR[] = $row['vMobile'];
        $CODE_ARR[] = $row['vCode'];
    }

    echo json_encode([
        "data" => [
            "mobileArr" => $PHONE_ARR,
            "codeArr" => $CODE_ARR,
        ],
        "statusCode" => 200
    ]);

    exit;
}

// ===================== CASE: ADD_STAFF =====================
if ($mode == 'ADD_STAFF') {
    // Get form data from request
    $vCode = db_input($request['code'] ?? '');
    $vName = db_input($request['name'] ?? '');
    $vMobile = db_input($request['mobile'] ?? '');
    $cStatus = 'A'; // Default status 
    $dtRegistered = NOW; 
    // Basic validation
    if (empty($vCode)) {
        echo json_encode([
            "error" => [
                "message" => "Staff code is required"
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    if (empty($vName)) {
        echo json_encode([
            "error" => [
                "message" => "Staff name is required"
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    if (empty($vMobile)) {
        echo json_encode([
            "error" => [
                "message" => "Mobile number is required"
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    // Check for duplicate vCode or vMobile
    $checkSql = "SELECT iStaffID, vCode, vMobile FROM staff WHERE (vCode = '$vCode' OR vMobile = '$vMobile') AND cStatus != 'X'";
    $checkRes = sql_query($checkSql);

    if (sql_num_rows($checkRes) > 0) {
        $existingRow = sql_fetch_assoc($checkRes);
        if ($existingRow['vCode'] === $vCode) {
            echo json_encode([
                "error" => [
                    "message" => "Staff code already exists"
                ],
                "statusCode" => 409
            ]);
            exit;
        }
        if ($existingRow['vMobile'] === $vMobile) {
            echo json_encode([
                "error" => [
                    "message" => "Mobile number already exists"
                ],
                "statusCode" => 409
            ]);
            exit;
        }
    }

    // Get next staff ID
    $iStaffID = NextID('iStaffID', 'staff');

    // Insert new staff record
    $sql = "INSERT INTO staff (iStaffID, vCode, vName, vMobile, dtRegistered, cStatus) 
            VALUES ($iStaffID, '$vCode', '$vName', '$vMobile', '$dtRegistered', '$cStatus')";

    if (sql_query($sql)) {
        echo json_encode([
            "statusCode" => 200,
            "message" => "Staff registered successfully",
            "data" => [
                "id" => $iStaffID,
                "code" => $vCode,
                "name" => $vName,
                "mobile" => $vMobile
            ]
        ]);
    } else {
        echo json_encode([
            "error" => [
                "message" => "Failed to register staff"
            ],
            "statusCode" => 500
        ]);
    }

    exit;
}
