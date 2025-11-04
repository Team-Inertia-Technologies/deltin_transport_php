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

switch ($mode) {

    // ===================== CASE 1: LIST =====================
    case 'LIST':
    $sql = "SELECT iRouteID, vName, vDestination FROM st_route WHERE cStatus = 'A' ORDER BY iRank";
    $res = sql_query($sql);

    $rowData = [];
    $routesOpt = [];

    while ($row = sql_fetch_assoc($res)) {
        // For the main route list
        $rowData[] = [
            "id" => (int)$row['iRouteID'],
            "route" => $row['vName'],
            "destination" => $row['vDestination']
        ];

        // For dropdown options
        $routesOpt[] = [
            "id" => (int)$row['iRouteID'],
            "name" => $row['vName']
        ];
    }

    echo json_encode([
        "statusCode" => 200,
        "message" => "Route list fetched successfully",
        "data" => [
            "rowData" => $rowData,
            "routesOpt" => $routesOpt
        ]
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