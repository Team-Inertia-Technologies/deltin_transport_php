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

switch ($mode) {

    // ===================== CASE 1: LIST =====================
    case 'LIST':
        $sql = "SELECT 
                    iDepartmentID,
                    vName,
                    vDescription,
                    cStatus
                FROM department 
                WHERE cStatus IN ('A', 'I')
                ORDER BY vName ASC";

        $res = sql_query($sql);
        $departmentList = [];

        while ($row = sql_fetch_assoc($res)) {
            $departmentList[] = [
                'id' => (int) $row['iDepartmentID'],
                'name' => db_output2($row['vName']),
                'description' => db_output2($row['vDescription'] ?? ''),
                'status' => $row['cStatus']
            ];
        }

        echo json_encode([
            "data" => [
                "departments" => $departmentList
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE 2: ADD =====================
    case 'ADD':
        $vName = db_input($_REQUEST['name'] ?? '');
        $vDescription = db_input($_REQUEST['description'] ?? '');

        // Validate required fields
        if (empty($vName)) {
            echo json_encode([
                "error" => [
                    "message" => "Department name is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Check for duplicate name
        $checkSql = "SELECT iDepartmentID FROM department WHERE vName = '" . db_input($vName) . "' AND cStatus != 'X'";
        $checkRes = sql_query($checkSql);

        if (sql_num_rows($checkRes) > 0) {
            echo json_encode([
                "error" => [
                    "message" => "Department name already exists"
                ],
                "statusCode" => 409
            ]);
            exit;
        }

        // Create new department record
        $iDepartmentID = NextID('iDepartmentID', 'department');
        $cStatus = 'A';
        $dtRegistered = NOW;

        $sql = "INSERT INTO department (iDepartmentID, vName, vDescription, dtRegistered, cStatus) 
                VALUES ($iDepartmentID, '" . db_input($vName) . "', '" . db_input($vDescription) . "', '" . db_input($dtRegistered) . "', '" . db_input($cStatus) . "')";

        if (sql_query($sql)) {
            echo json_encode([
                "data" => [
                    "id" => $iDepartmentID,
                    "message" => "Department added successfully"
                ],
                "statusCode" => 201
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to add department"
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
?>