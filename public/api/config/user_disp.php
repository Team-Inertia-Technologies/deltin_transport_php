<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../../includes/common_api.php";

header('Content-Type: application/json');

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
    $response = array (
        "data" => array (
            "message" => "Users Fetched Successfully",
            "users" => $users,
            "properties" => $PROPERTY_ARR
        ),
        "statuscode" => 200
    );

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    $response = array (
        "error" => array(
            "message" => "Internal Server Error",
        ),
        "statuscode" => 500
    );

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
