<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);
$levelID  = $_REQUEST['level'] ?? null;
$statusID = $_REQUEST['status'] ?? null;
$keywords = $_REQUEST['keywords'] ?? null;
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
    
    $cond = "WHERE cRefType='A' AND cStatus!='X'";
    $cond2 = "";
    if (!is_null($levelID) && $levelID >= 0) {
        $cond2 .= " AND iLevel = " . intval($levelID);
    }

    if (!is_null($statusID) && $statusID !== "") {
        $cond2 .= " AND cStatus = '" . db_input2($statusID) . "'";
    }

    if (!is_null($keywords) && trim($keywords) !== "") {
        $keywords_escaped = db_input2(trim($keywords));
        $cond2 .= " AND (vName LIKE '%" . $keywords_escaped . "%' OR vUName LIKE '%" . $keywords_escaped . "%' OR vPhone LIKE '%" . $keywords_escaped . "%')";
    }

    $USER_LEVEL_ARR = [];
    $LEVELS = array(
        '0' => 'Super Admin',
        '1' => 'Admin',
        '2' => 'HOD',
        '3' => 'National Head',
        '4' => 'Loyalty Coordinator',
        '5' => 'VIP Services',
        '6' => 'Jr. RM',
        '7' => 'Members',
        '8' => 'Sr. RM',
        '9' => 'Caller',
        '10' => 'Campaign Manager',
        '11' => 'Supervisor',
        '12' => 'Staff',
        '13' => 'Delights'
    );
    
    foreach ($LEVELS as $id => $name) {
        $USER_LEVEL_ARR[] = [
            "id" => (int)$id,
            "name" => $name
        ];
    }

    $Status = [];
    $STATUS_ARR = array("A" => "Active", "I" => "Inactive", "P" => "Sent For Approval", "U" => "Pending For Activation");
    foreach ($STATUS_ARR as $key => $value) {
        $Status[] = [
            "id" => $key,
            "name" => $value
        ];
    }
    
    // Fetch users
    $sql = "SELECT * FROM users_temp $cond $cond2 ORDER BY vName ASC";
    // echo $sql;
    // exit;
    $result = sql_query($sql);

    $users = [];
    while ($row = sql_fetch_assoc($result)) {
        $users[] = $row;
    }
    $response = array (
        "data" => array (
            "message" => "Users Fetched Successfully",
            "users" => $users,
            "properties" => $PROPERTY_ARR,
            "levels" => $USER_LEVEL_ARR,
            "status" => $Status
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
