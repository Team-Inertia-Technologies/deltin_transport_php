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

switch ($mode) {

    // ===================== CASE 1: LIST =====================
    case 'LIST':
    default:
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

            echo json_encode([
                "data" => [
                    "message" => "Users Fetched Successfully",
                    "users" => $users,
                    "properties" => $PROPERTY_ARR
                ],
                "statusCode" => 200
            ]);

        } catch (Exception $e) {
            echo json_encode([
                "error" => [
                    "message" => "Internal Server Error"
                ],
                "statusCode" => 500
            ]);
        }
        break;
}