<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../../includes/common_api.php";

header('Content-Type: application/json');

// Handle POST requests and parse JSON input
$postdata = file_get_contents("php://input");
$request = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);

// Token validation like other API files
$Token = $_REQUEST['token'] ?? '';
$user_id = intval(DecodeParam($Token));

if (empty($Token)) {
    echo json_encode([
        "error" => [
            "message" => "Token is required"
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

try {
    // Build property map
    $PROPERTY_ARR = [];
    $query = "
        SELECT up.iUserID, p.vShortCode
        FROM users_property_assoc AS up
        JOIN property AS p ON up.iPropertyID = p.iPropertyID
        ORDER BY p.iPropertyID
    ";
    $res = sql_query($query);
    while (list($u_id, $p_code) = sql_fetch_row($res)) {
        $PROPERTY_ARR[$u_id][] = $p_code;
    }

    // Build condition
    $cond = "WHERE cRefType='A' AND cStatus!='X'";
    
    // Fetch users
    $sql = "SELECT * FROM users $cond ORDER BY vName ASC";
    $result = sql_query($sql);

    $users = [];
    while ($row = sql_fetch_assoc($result)) {
        $users[] = $row;
    }
    $response = [
        "data" => [
            "message" => "Users Fetched Successfully",
            "users" => $users,
            "properties" => $PROPERTY_ARR
        ],
        "statusCode" => 200
    ];

    http_response_code(200);
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    $response = [
        "error" => [
            "message" => "Internal Server Error"
        ],
        "statusCode" => 500
    ];

    http_response_code(500);
    echo json_encode($response);
    exit;
}
