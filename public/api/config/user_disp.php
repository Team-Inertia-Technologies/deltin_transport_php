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

    echo json_encode([
        'statusCode' => 200,
        'message' => 'Users fetched successfully',
        'data' => $users,
        'properties' => $PROPERTY_ARR
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'statusCode' => 500,
        'message' => 'Internal Server Error',
        'error' => $e->getMessage()
    ]);
    exit;
}
?>
