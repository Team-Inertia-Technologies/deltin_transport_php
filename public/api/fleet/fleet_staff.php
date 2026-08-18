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
        $password = htmlspecialchars_decode($_REQUEST['password'] ?? '');
        // $level        = intval($_REQUEST['level'] ?? 0);
        $level        = 7;
        $reportingTo  = intval($_REQUEST['reportingTo'] ?? 0);

        /* ---- DUPLICATE MOBILE CHECK ---- */
        $dup = sql_query("SELECT 1 FROM fleet_staff WHERE vMobile='$vMobile' AND cStatus!='X'");
        if (sql_num_rows($dup) > 0) {
            echo json_encode(["statusCode" => 409, 
               "data" => [  
                "vMobile" => $vMobile,
                "message" => "Mobile number already exists"
               ]
            ]);
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
                (iUserID,vName,vUName,vPassword,vPhone,iDepartmentID,iReportingID,iLevel,cRefType,
                 cStatus,cAction,dtCreated,iCreated_UserID)
                VALUES
                ($iUserID,'$vName','$username','$password','$vMobile',
                 $iDepartmentID,$reportingTo,$level,'A','D','AWA','$NOW',$sess_user_id)
            ");

            sql_query("UPDATE fleet_staff SET iUserID=$iUserID WHERE iFStaffID=$iFStaffID");
        }

        echo json_encode([
            "statusCode" => 200,
            "data" => [
                "iFStaffID" => $iFStaffID,
                "message" => "Staff added successfully"
            ]
        ]);
        exit;

        /* ================= EDIT ================= */
    case 'EDIT':

        $iFStaffID = intval($_REQUEST['iFStaffID']);

        $res = sql_query("SELECT iFStaffID,vCode,vName,vMobile,iDepartmentID,iUserID,cStatus FROM fleet_staff WHERE iFStaffID = $iFStaffID AND cStatus != 'X'");

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "statusCode" => 400,
                "message" => "Invalid Staff ID"
            ]);
            exit;
        }


        $userInfo = sql_fetch_assoc($res);

        $userInfo['isUser'] = ($userInfo['iUserID'] > 0) ? 'Y' : 'N';

        if ($userInfo['iUserID'] > 0) {
            $uRes = sql_query("
        SELECT vUName AS username,iLevel AS level,iReportingID AS reportingTo
        FROM users_temp WHERE iUserID = {$userInfo['iUserID']}
    ");

            if ($u = sql_fetch_assoc($uRes)) {
                $userInfo = array_merge($userInfo, $u);
            }
        }

        $departments = [
            ["id" => 0, "name" => "Choose"]
        ];

        $departmentResult = sql_query("SELECT iDepartmentID, vName FROM department WHERE cStatus = 'A' ORDER BY vName");

        while ($row = sql_fetch_assoc($departmentResult)) {
            $departments[] = [
                "id" => (int)$row['iDepartmentID'],
                "name" => db_output2($row['vName'])
            ];
        }

        $levels = [
            ["id" => 0, "name" => "Choose"]
        ];

        $levelsResult = sql_query("SELECT iLevelD, vName FROM levels WHERE cStatus = 'A' ORDER BY iRank");

        while ($row = sql_fetch_assoc($levelsResult)) {
            $levels[] = [
                "id" => (int)$row['iLevelD'],
                "name" => db_output2($row['vName'])
            ];
        }

        $users = [
            ["id" => 0, "name" => "Choose"]
        ];

        $userResult = sql_query("SELECT iUserID, vName FROM users  WHERE cStatus = 'A'  ORDER BY vName");

        while ($row = sql_fetch_assoc($userResult)) {
            $users[] = [
                "id" => (int)$row['iUserID'],
                "name" => db_output2($row['vName'])
            ];
        }

        echo json_encode([
            "statusCode" => 200,
            "data" => [
                "userInfo"    => $userInfo,
                "departments" => $departments,
                "levels"      => $levels,
                "users"       => $users
            ]
        ]);
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
        $level        = 7;
        $reportingTo  = intval($_REQUEST['reportingTo'] ?? 0);


        $dup = sql_query("
        SELECT 1 FROM fleet_staff 
        WHERE vMobile='$vMobile' 
          AND iFStaffID!=$iFStaffID 
          AND cStatus!='X'
    ");
        if (sql_num_rows($dup) > 0) {
            echo json_encode([
                "statusCode" => 409,
                "message" => "Mobile number already exists"
            ]);
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

        if ($isUser) {

            $password = '';
            if (!empty($_REQUEST['password'])) {
                $password = htmlspecialchars_decode(db_input($_REQUEST['password']));
            }

            /* ===== USER ALREADY EXISTS → UPDATE ===== */
            if (intval($staff['iUserID']) > 0) {

                $updateFields = [];
                $updateFields[] = "vName='$vName'";
                $updateFields[] = "vUName='$username'";
                $updateFields[] = "vPhone='$vMobile'";
                $updateFields[] = "iDepartmentID=$iDepartmentID";
                $updateFields[] = "iReportingID=$reportingTo";
                $updateFields[] = "iLevel=$level";
                $updateFields[] = "cStatus='D'";
                $updateFields[] = "cAction='AWA'";

                if ($password !== '') {
                    $updateFields[] = "vPassword='$password'";
                }

                sql_query("
            UPDATE users_temp
            SET " . implode(',', $updateFields) . "
            WHERE iUserID={$staff['iUserID']}
        ");
            }
            /* ===== USER DOES NOT EXIST → INSERT ===== */ else {

                sql_query("
            INSERT INTO users_temp SET
                vName='$vName',
                vUName='$username',
                vPhone='$vMobile',
                vPassword='$password',
                iDepartmentID=$iDepartmentID,
                iReportingID=$reportingTo,
                iLevel=$level,
                cStatus='D',
                cAction='AWA',
                dtAdded=NOW()
        ");

                $newUserID = sql_insert_id();

                /* LINK USER TO STAFF */
                sql_query("
            UPDATE fleet_staff
            SET iUserID=$newUserID
            WHERE iFStaffID=$iFStaffID
        ");
            }
        }

        echo json_encode([
            "statusCode" => 200,
            "data" => [
                "iFStaffID" => $iFStaffID,
                "message" => "Staff updated successfully"
            ]
        ]);
        exit;


        /* ================= LIST ================= */
    case 'LIST':

        $res = sql_query("
        SELECT
            fs.iFStaffID AS staffID,
            fs.vCode AS staffCode,
            fs.vName AS staffName,
            fs.vMobile AS mobile,
            fs.iDepartmentID AS departmentID,
            d.vName AS deptname,
            fs.cStatus AS status,
            IF(fs.iUserID > 0, 'Y', 'N') AS isUser
        FROM fleet_staff fs
        LEFT JOIN department d 
            ON d.iDepartmentID = fs.iDepartmentID
           AND d.cStatus = 'A'
        WHERE fs.cStatus != 'X'
        ORDER BY fs.vName ASC
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
