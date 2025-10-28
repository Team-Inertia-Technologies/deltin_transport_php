<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include "../../includes/common_api.php";

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'statusCode' => 405,
            'message' => 'Method Not Allowed'
        ]);
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
        http_response_code(400);
        echo json_encode([
            'statusCode' => 400,
            'message' => 'Invalid or missing Level ID'
        ]);
        exit;
    }

    if ($action === 'fetch') {
        // Fetch modules & assigned ones
        $modulesQuery = "SELECT iModuleID, vName, cType, iParentID 
                         FROM module 
                         WHERE cStatus='A' 
                         ORDER BY cType, iRank, vName";
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

        echo json_encode([
            'statusCode' => 200,
            'message' => 'Modules fetched successfully',
            'data' => [
                'modules' => $modulesArr,
                'assigned' => $assignedArr
            ]
        ]);
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

        echo json_encode([
            'statusCode' => 200,
            'message' => 'Modules successfully updated',
            'data' => [
                'level_id' => $levelId,
                'modules_updated' => $countInserted
            ]
        ]);
        exit;
    }

    // Invalid action
    http_response_code(400);
    echo json_encode([
        'statusCode' => 400,
        'message' => 'Invalid or missing action'
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

