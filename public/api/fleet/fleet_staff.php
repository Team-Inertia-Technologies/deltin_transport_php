<?php
//ini_set('display_errors', 1);
include "../../includes/common_api.php";
header('Content-Type: application/json');

$postdata = file_get_contents("php://input");
$request  = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);

$mode  = $_REQUEST['mode'] ?? '';
$Token = $_REQUEST['token'] ?? '';

$user_id = intval(DecodeParam($Token));
$sess_user_id = $user_id;
$NOW = NOW;

/* ---------- USER VALIDATION ---------- */
$res = sql_query("SELECT iUserID FROM users WHERE iUserID=$user_id AND cStatus='A'");
if (sql_num_rows($res) == 0) {
    echo json_encode(["statusCode" => 401, "message" => "User not found or inactive"]);
    exit;
}

switch ($mode) {

    /* ================= ADD STAFF ================= */
    case 'ADD_STAFF':

        $vCode         = db_input($_REQUEST['vCode']);
        $vName         = db_input($_REQUEST['vName']);
        $vMobile       = db_input($_REQUEST['vMobile']);
        $iDepartmentID = intval($_REQUEST['iDepartmentID']);
        $isUser        = ($_REQUEST['isUser'] ?? 'N') === 'Y';
        $username     = db_input($_REQUEST['username'] ?? '');
        $password     = db_input($_REQUEST['password'] ?? '');
        $level        = intval($_REQUEST['level'] ?? 0);
        $reportingTo  = intval($_REQUEST['reportingTo'] ?? 0);

        /* ---- DUPLICATE MOBILE CHECK ---- */
        $dup = sql_query("SELECT 1 FROM fleet_staff WHERE vMobile='$vMobile' AND cStatus!='X'");
        if (sql_num_rows($dup) > 0) {
            echo json_encode(["statusCode" => 409, "message" => "Mobile number already exists"]);
            exit;
        }

        $iFStaffID = NextID('iFStaffID', 'fleet_staff');

        sql_query("
            INSERT INTO fleet_staff
            (iFStaffID,vCode,vName,vMobile,iDepartmentID,iUserID,dtRegistered,cStatus)
            VALUES ($iFStaffID,'$vCode','$vName','$vMobile',$iDepartmentID,0,'$NOW','D')
        ");

        if ($isUser) {
            $iUserID = NextID('iUserID', 'users_temp');

            sql_query("
                INSERT INTO users_temp
                (iUserID,vName,vUName,vPassword,vPhone,iDepartmentID,iReportingID,iLevel,
                 cStatus,cAction,dtCreated,iCreated_UserID)
                VALUES
                ($iUserID,'$vName','$username','$password','$vMobile',
                 $iDepartmentID,$reportingTo,$level,'D','AWA','$NOW',$sess_user_id)
            ");

            sql_query("UPDATE fleet_staff SET iUserID=$iUserID WHERE iFStaffID=$iFStaffID");
        }

        echo json_encode([
            "statusCode" => 200,
            "message" => "Staff added successfully",
            "data" => ["iFStaffID" => $iFStaffID]
        ]);
        exit;

        /* ================= EDIT ================= */
    case 'EDIT':

        $iFStaffID = intval($_REQUEST['iFStaffID']);

        $res = sql_query("
            SELECT 
                iFStaffID AS staffID,
                vCode AS staffCode,
                vName AS staffName,
                vMobile AS mobile,
                iDepartmentID AS departmentID,
                iUserID AS userID,
                cStatus AS status
            FROM fleet_staff
            WHERE iFStaffID=$iFStaffID AND cStatus!='X'
        ");

        if (sql_num_rows($res) == 0) {
            echo json_encode(["statusCode" => 400, "message" => "Invalid Staff ID"]);
            exit;
        }

        $data = sql_fetch_assoc($res);
        $data['isUser'] = ($data['userID'] > 0) ? 'Y' : 'N';

        /* fetch user data if exists */
        if ($data['userID'] > 0) {
            $u = sql_fetch_assoc(
                sql_query("
                    SELECT vUName AS username,
                           vPassword AS password,
                           iLevel AS level,
                           iReportingID AS reportingTo
                    FROM users_temp
                    WHERE iUserID={$data['userID']}
                ")
            );
            $data = array_merge($data, $u ?: []);
        }

        echo json_encode(["statusCode" => 200, "data" => $data]);
        exit;

        /* ================= UPDATE STAFF ================= */
    case 'UPDATE_STAFF':

        $iFStaffID     = intval($_REQUEST['iFStaffID']);
        $vCode         = db_input($_REQUEST['vCode']);
        $vName         = db_input($_REQUEST['vName']);
        $vMobile       = db_input($_REQUEST['vMobile']);
        $iDepartmentID = intval($_REQUEST['iDepartmentID']);
        $isUser        = ($_REQUEST['isUser'] ?? 'N') === 'Y';

        $username     = db_input($_REQUEST['username'] ?? '');
        $password     = db_input($_REQUEST['password'] ?? '');
        $level        = intval($_REQUEST['level'] ?? 0);
        $reportingTo  = intval($_REQUEST['reportingTo'] ?? 0);

        /* ---- DUPLICATE MOBILE CHECK ---- */
        $dup = sql_query("
            SELECT 1 FROM fleet_staff 
            WHERE vMobile='$vMobile' AND iFStaffID!=$iFStaffID AND cStatus!='X'
        ");
        if (sql_num_rows($dup) > 0) {
            echo json_encode(["statusCode" => 409, "message" => "Mobile number already exists"]);
            exit;
        }

        sql_query("
            UPDATE fleet_staff
            SET vCode='$vCode',
                vName='$vName',
                vMobile='$vMobile',
                iDepartmentID=$iDepartmentID
            WHERE iFStaffID=$iFStaffID
        ");

        $staff = sql_fetch_assoc(
            sql_query("SELECT iUserID FROM fleet_staff WHERE iFStaffID=$iFStaffID")
        );

        if ($isUser && intval($staff['iUserID']) > 0) {
            sql_query("
                UPDATE users_temp
                SET vName='$vName',
                    vUName='$username',
                    vPassword='$password',
                    vPhone='$vMobile',
                    iDepartmentID=$iDepartmentID,
                    iReportingID=$reportingTo,
                    iLevel=$level,
                    cStatus='D',
                    cAction='AWA'
                WHERE iUserID={$staff['iUserID']}
            ");
        }

        echo json_encode(["statusCode" => 200, "message" => "Staff updated successfully"]);
        exit;

        /* ================= LIST ================= */
    case 'LIST':

        $res = sql_query("
            SELECT
                iFStaffID AS staffID,
                vCode AS staffCode,
                vName AS staffName,
                vMobile AS mobile,
                iDepartmentID AS departmentID,
                cStatus AS status,
                IF(iUserID>0,'Y','N') AS isUser
            FROM fleet_staff
            WHERE cStatus!='X'
            ORDER BY vName ASC
        ");

        $data = [];
        while ($row = sql_fetch_assoc($res)) {
            $data[] = $row;
        }

        echo json_encode(["statusCode" => 200, "data" => $data]);
        exit;

    case 'ONLOAD_LIST':

        $departmentQuery = "SELECT iDepartmentID, vName FROM department WHERE cStatus = 'A' ORDER BY vName";
        $departmentResult = sql_query($departmentQuery);

        $departments = [
            ["id" => 0, "name" => "Choose"]
        ];
        while ($row = sql_fetch_assoc($departmentResult)) {
            $departments[] = [
                "id" => (int) $row['iDepartmentID'],
                "name" => db_output2($row['vName'])
            ];
        }
        $levelsQuery = " SELECT iLevelD, vName FROM levels WHERE cStatus = 'A' ORDER BY iRank";
        $levelsResult = sql_query($levelsQuery);


        $levels = [
            ["id" => 0, "name" => "Choose"]
        ];

        while ($row = sql_fetch_assoc($levelsResult)) {
            $levels[] = [
                "id" => (int) $row['iLevelD'],
                "name" => db_output2($row['vName'])
            ];
        }

        $userQuery = " SELECT iUserID, vName FROM users WHERE cStatus = 'A' ORDER BY vName";
        $userresult = sql_query($userQuery);


        $users = [
            ["id" => 0, "name" => "Choose"]
        ];

        while ($row = sql_fetch_assoc($userresult)) {
            $users[] = [
                "id" => (int) $row['iUserID'],
                "name" => db_output2($row['vName']),
            ];
        }

        $data = [
            "departments" => $departments,
            "levels" => $levels,
             "users" => $users

        ];
        echo json_encode(["statusCode" => 200, "data" => $data]);
        exit;

    default:
        echo json_encode(["statusCode" => 400, "message" => "Invalid mode"]);
        exit;
}
