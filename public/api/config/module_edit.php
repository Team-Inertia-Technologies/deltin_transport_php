<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../../includes/common_api.php";

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response = array(
            "error" => array(
                "message" => "Method not Allowed",
            ),
            "statusCode" => 405,
        );
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Get data from POST form
    $levelId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $modules = isset($_POST['cmbmodules']) ? $_POST['cmbmodules'] : [];

    // If cmbmodules is a JSON string or comma-separated string, decode it
    if (is_string($modules)) {
        $decoded = json_decode($modules, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $modules = $decoded;
        } else {
            $modules = explode(',', $modules);
        }
    }

    if ($levelId <= 0) {
        $response = array(
            "error" => array(
                "message" => "Invalid or missing Level ID",
            ),
            "statusCode" => 400,
        );
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if ($action === 'fetch') {
        $modulesQuery = "
            SELECT iModuleID, vName, cType, iParentID
            FROM module
            WHERE cStatus='A'
            ORDER BY cType, iRank, vName
        ";
        $resModules = sql_query($modulesQuery);
    
        $modulesArr = [];
        while ($row = sql_fetch_assoc($resModules)) {
            $modulesArr[] = $row;
        }
        $assignedQuery = "SELECT iModuleID FROM module_level_assoc WHERE iLevelD = $levelId";
        $resAssigned = sql_query($assignedQuery);
    
        $assignedArr = [];
        while ($row = sql_fetch_assoc($resAssigned)) {
            $assignedArr[] = $row['iModuleID'];
        }
    
        $finalModules = [];
        $lookup = [];
        foreach ($modulesArr as $mod) {
            $mod['children'] = [];
            $lookup[$mod['iModuleID']] = $mod;
        }
    
        foreach ($lookup as $id => &$mod) {
            if ($mod['iParentID'] == 0) {
                $finalModules[] = &$mod;
            } else {
                if (isset($lookup[$mod['iParentID']])) {
                    $lookup[$mod['iParentID']]['children'][] = &$mod;
                }
            }
        }
    
        $response = [
            "data" => [
                "message" => "Modules fetched successfully",
                "modules" => $finalModules,
                "assigned" => $assignedArr
            ],
            "statusCode" => 200
        ];
    
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    

    if ($action === 'update') {
        // Clear existing mappings
        sql_query("DELETE FROM module_level_assoc WHERE iLevelD = $levelId");

        $countInserted = 0;
        if (!empty($modules)) {
            foreach ($modules as $modID) {
                $modID = (int)$modID;
                if ($modID > 0) {
                    $type = GetXFromYID("SELECT cType FROM module WHERE iModuleID=$modID");
                    $insertQuery = "INSERT INTO module_level_assoc (iLevelD, iModuleID, cType)
                                    VALUES ($levelId, $modID, '$type')";
                    sql_query($insertQuery, 'module_assoc_update');
                    $countInserted++;
                }
            }
        }

        $response = array(
            "data" => array(
                "message" => "Successfully fetched the levels",
                "levels" => array(
                    array(
                        "id" => $levelId,
                        "name" => $levelName,
                        "status" => $levelStatus
                    )
                )
            ),
            "statusCode" => 200
        );


        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if ($action === 'Add') {
        $txtid = NextID("iLevelD", "levels");
        $txtname = db_input($_POST['txtname']);
        $txtdesc = isset($_POST['txtdesc']) ? db_input($_POST['txtdesc']) : '';
        $txtrank = GetMaxRank('levels');
        $rdstatus = 'A';
    
        $q = "INSERT INTO levels (iLevelD, vName, vDesc, cStatus,iRank) 
                VALUES ($txtid, '$txtname', '$txtdesc', '$rdstatus', '$txtrank')";
        $r = sql_query($q, "tax_edit.52");
    
    
        sql_query("delete from module_level_assoc where iLevelD=$txtid");
    
        $cmbmodules = isset($_POST['cmbmodules']) ? $_POST['cmbmodules'] : array();
    
        if (!empty($cmbmodules)) {
            foreach ($cmbmodules as $key => $value) {
                $CTYPE = GetXFromYID("SELECT cType FROM module WHERE iModuleID=$value");
                $q = "INSERT INTO module_level_assoc (iLevelD, iModuleID, cType) VALUES ($txtid, $value, '$CTYPE')";
                $r = sql_query($q, 'CL_E.115');
            }
        }
    
        $response = array(
            "data" => array(
                "message" => "Level added successfully",
            ),
            "statusCode" => 200
        );
    }

    if ($action === 'addfetch') {
        $modulesQuery = "
            SELECT iModuleID, vName, cType, iParentID
            FROM module
            WHERE cStatus='A'
            ORDER BY cType, iRank, vName
        ";
        $resModules = sql_query($modulesQuery);
    
        $modulesArr = [];
        while ($row = sql_fetch_assoc($resModules)) {
            $modulesArr[] = $row;
        }
        // $assignedQuery = "SELECT iModuleID FROM module_level_assoc WHERE iLevelD = $levelId";
        // $resAssigned = sql_query($assignedQuery);
    
        // $assignedArr = [];
        // while ($row = sql_fetch_assoc($resAssigned)) {
        //     $assignedArr[] = $row['iModuleID'];
        // }
    
        $finalModules = [];
        $lookup = [];
        foreach ($modulesArr as $mod) {
            $mod['children'] = [];
            $lookup[$mod['iModuleID']] = $mod;
        }
    
        foreach ($lookup as $id => &$mod) {
            if ($mod['iParentID'] == 0) {
                $finalModules[] = &$mod;
            } else {
                if (isset($lookup[$mod['iParentID']])) {
                    $lookup[$mod['iParentID']]['children'][] = &$mod;
                }
            }
        }
    
        $response = [
            "data" => [
                "message" => "Modules fetched successfully",
                "modules" => $finalModules,
                "assigned" => $assignedArr
            ],
            "statusCode" => 200
        ];
    
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    $response = array(
        "error" => array(
            "message" => "Invalid or missing action"
        ),
        "statusCode" => 400,
    );
    // Invalid action
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    $response = array(
        "error" => array(
            "message" => "Internal Server Error",
        ),
        "statusCode" => 500,
    );
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
