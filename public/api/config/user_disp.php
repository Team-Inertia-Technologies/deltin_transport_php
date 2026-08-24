<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");
$request  = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);
$token = $_REQUEST['token'] ?? null;
if (!$token) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => ["message" => "Token missing"],
        "statuscode" => 401
    ]);
    exit;
}
$levelID  = $_REQUEST['level'] ?? null;
$propertyID = $_REQUEST['property'] ?? null;
$statusID = $_REQUEST['status'] ?? null;
$keywords = $_REQUEST['keywords'] ?? null;
$sess_user_id = intval(DecodeParam($token));
if (!$sess_user_id) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => ["message" => "Invalid token"],
        "statuscode" => 401
    ]);
    exit;
}

try {
    // if (!$sess_user_id) {
    //     throw new Exception("Invalid session");
    // }

    // ---------------------------------------------------
    // Get logged-in user's LEVEL RANK
    // ---------------------------------------------------
    $rankQuery = "
        SELECT l.iRank
        FROM users u
        JOIN levels l ON u.iLevel = l.iLevelD
        WHERE u.iUserID = $sess_user_id
    ";
    // echo $rankQuery;
    $rankRes = sql_query($rankQuery);
    list($loggedInRank) = sql_fetch_row($rankRes);
    $loggedInRank = intval($loggedInRank);

    // ---------------------------------------------------
    // Build PROPERTY MAP
    // ---------------------------------------------------
    $PROPERTY_ARR = [];
    $query = "
        SELECT up.iUserID, p.vShortCode
        FROM users_property_assoc up
        JOIN property p ON up.iPropertyID = p.iPropertyID
        ORDER BY p.iPropertyID
    ";
    $res = sql_query($query);
    while (list($u_id, $p_code) = sql_fetch_row($res)) {
        $PROPERTY_ARR[$u_id][] = $p_code;
    }

    $PROPERTY_LIST = [];
    $propertyQuery = "
        SELECT iPropertyID, vName
        FROM property
        WHERE cStatus = 'A'
        ORDER BY iPropertyID
    ";
    $propertyRes = sql_query($propertyQuery);
    while ($row = sql_fetch_assoc($propertyRes)) {
       $PROPERTY_LIST[] = [
        "id" => (int)$row['iPropertyID'],
        "name" => $row['vName']
       ];
    }

    // ---------------------------------------------------
    // Base Conditions
    // ---------------------------------------------------
    $cond = "
        WHERE  cStatus != 'X'
          AND iLevel IN (
              SELECT iLevelD
              FROM levels
              WHERE iRank > $loggedInRank
          )
    ";

    // ---------------------------------------------------
    // Additional Filters
    // ---------------------------------------------------
    if (!is_null($levelID) && $levelID >= 0) {
        $cond .= " AND iLevel = " . intval($levelID);
    }

    if (!is_null($propertyID) && $propertyID >= 0) {
        $cond .= " AND iPropertyID = " . intval($propertyID);
    }

    if (!is_null($statusID) && $statusID !== "") {
        $cond .= " AND cStatus = '" . db_input2($statusID) . "'";
    }

    if (!is_null($keywords) && trim($keywords) !== "") {
        $kw = db_input2(trim($keywords));
        $cond .= " AND (
            vName LIKE '%$kw%'
            OR vUName LIKE '%$kw%'
            OR vPhone LIKE '%$kw%'
        )";
    }

    // ---------------------------------------------------
    // Fetch Levels (ONLY BELOW LOGGED-IN USER)
    // ---------------------------------------------------
    $levels = [];
    $lvlQuery = "
        SELECT iLevelD, vName
        FROM levels
        WHERE cStatus = 'A'
          AND iRank > $loggedInRank
        ORDER BY iRank
    ";
    $lvlRes = sql_query($lvlQuery);
    while ($row = sql_fetch_assoc($lvlRes)) {
        $levels[] = [
            "id"   => (int)$row['iLevelD'],
            "name" => $row['vName']
        ];
    }

    $stations = [];
    $stationQuery = "
        SELECT iFlt_StationID, vName
        FROM fleet_station
        WHERE cStatus = 'A'
        ORDER BY iFlt_StationID";
    $stationRes = sql_query($stationQuery);
    while ($row = sql_fetch_assoc($stationRes)) {
        $stations[] = [
            "id"   => (int)$row['iFlt_StationID'],
            "name" => $row['vName']
        ];
    }

    // ---------------------------------------------------
    // Status List
    // ---------------------------------------------------
    $Status = [];
    $STATUS_ARR = [
        "A" => "Active",
        "I" => "Inactive",
        "P" => "Sent For Approval",
        "U" => "Pending For Activation"
    ];
    foreach ($STATUS_ARR as $key => $value) {
        $Status[] = [
            "id"   => $key,
            "name" => $value
        ];
    }

    // ---------------------------------------------------
    // Departments
    // ---------------------------------------------------
    $DEPT = [];
    $DEPT_ARR = GetXArrFromYID(
        "SELECT iDepartmentID, vName FROM department WHERE cStatus='A' ORDER BY vName ASC",
        "3"
    );
    foreach ($DEPT_ARR as $dept_id => $dept_name) {
        $DEPT[] = [
            "id"   => (int)$dept_id,
            "name" => $dept_name
        ];
    }

    // ---------------------------------------------------
    // Fetch Users
    // ---------------------------------------------------
    $sql = "SELECT * FROM users_temp $cond ORDER BY vName ASC";
    $result = sql_query($sql);

    $users = [];
    while ($row = sql_fetch_assoc($result)) {
        $row['levelname'] = GetXFromYID(
            "SELECT vName FROM levels WHERE iLevelD = " . intval($row['iLevel'])
        );
        $row['propertyName'] = GetXFromYID(
            "SELECT vShortCode FROM property WHERE iPropertyID = " . intval($row['iPropertyID'])
        );
        $users[] = $row;
    }

    $staffsql = "SELECT iFStaffID, vName FROM fleet_staff WHERE cStatus='A' ORDER BY iFStaffID ASC";
    $staffresult = sql_query($staffsql);
    $staff = [];
    while ($row = sql_fetch_assoc($staffresult)) {
        $staff[] = [
            "id"   => (int)$row['iFStaffID'],
            "name" => $row['vName']
        ];
    }

    $vendorsql = "SELECT iVendorID, vName FROM vendor WHERE cStatus='A' ORDER BY iVendorID ASC";
    $vendorresult = sql_query($vendorsql);
    $vendor = [];
    while ($row = sql_fetch_assoc($vendorresult)) {
        $vendor[] = [
            "id"   => (int)$row['iVendorID'],
            "name" => $row['vName']
        ];
    }



    // ---------------------------------------------------
    // Response
    // ---------------------------------------------------
    $response = [
        "data" => [
            "message"     => "Users Fetched Successfully",
            "users"       => $users,
            "properties" => $PROPERTY_LIST,
            "levels"      => $levels,
            "status"      => $Status,
            "staff"       => $staff,
            "vendor"      => $vendor,
            "stations"    => $stations,
            "departments" => $DEPT,
            "typeArr" => [
                [
                    "id" => "S",
                    "name" => "Staff"
                ],
                [
                    "id" => "V",
                    "name" => "Vendor"
                ]
            ]
        ],
        "statuscode" => 200
    ];

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;

} catch (Exception $e) {

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        "error" => [
            "message" => "Internal Server Error"
        ],
        "statuscode" => 500
    ]);
    exit;
}
?>
