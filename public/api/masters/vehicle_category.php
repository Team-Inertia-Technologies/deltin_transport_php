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

// Function to validate vehicle category name
function validateCategoryName($vName, $excludeCategoryID = 0)
{
    if (empty($vName)) {
        return [
            'valid' => false,
            'message' => "Category name is required"
        ];
    }

    $sql = "SELECT iVCatID, vName FROM vehicle_category 
            WHERE vName = '$vName' AND iVCatID != $excludeCategoryID AND cStatus = 'A'";

    $res = sql_query($sql);

    if (sql_num_rows($res) > 0) {
        return [
            'valid' => false,
            'message' => "Category name already exists"
        ];
    }

    return ['valid' => true];
}

switch ($mode) {

    // ===================== CASE 1: ONLOAD =====================
    case 'ONLOAD':
        echo json_encode([
            "statusCode" => 200,
            "message" => "Vehicle category form loaded successfully",
            "data" => [
                'statusOptions' => [
                    ['id' => 'A', 'title' => 'Active'],
                    ['id' => 'I', 'title' => 'Inactive']
                ]
            ]
        ]);
        break;

    // ===================== CASE 2: LIST =====================
    case 'LIST':
        $sql = "SELECT iVCatID, iCapacity, vName, iRank, cStatus 
                FROM vehicle_category 
                WHERE cStatus IN ('A', 'I') 
                ORDER BY iRank ASC, vName ASC";
        $res = sql_query($sql);

        $rowData = [];
        while ($row = sql_fetch_assoc($res)) {
            $category = [
                'id' => intval($row['iVCatID']),
                'categoryName' => db_output2($row['vName']),
                'capacity' => intval($row['iCapacity']),
             //   'rank' => intval($row['iRank']),
                'status' => $row['cStatus'],
               // 'statusText' => $row['cStatus'] == 'A' ? 'Active' : 'Inactive'
            ];
            $rowData[] = $category;
        }

        echo json_encode([
            "statusCode" => 200,
            "message" => "Vehicle category list fetched successfully",
            "data" => [
                "rowData" => $rowData
            ]
        ]);
        break;

    // ===================== CASE 3: CATEGORY_DETAILS =====================
    case 'CATEGORY_DETAILS':
        $id = isset($_REQUEST['iVCatID']) ? intval($_REQUEST['iVCatID']) : 0;
        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Invalid Category ID"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $sql = "SELECT iVCatID, iCapacity, vName, iRank, cStatus 
                FROM vehicle_category 
                WHERE iVCatID = $id";
        $res = sql_query($sql);

        if (sql_num_rows($res) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle category not found"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $row = sql_fetch_assoc($res);

        echo json_encode([
            "statusCode" => 200,
            "message" => "Vehicle category details fetched successfully",
            "data" => [
                'categoryData' => [
                    'iVCatID' => intval($row['iVCatID']),
                    'categoryName' => db_output2($row['vName']),
                    'capacity' => intval($row['iCapacity']),
                    'rank' => intval($row['iRank']),
                    'status' => $row['cStatus']
                ],
                'statusOptions' => [
                    ['id' => 'A', 'title' => 'Active'],
                    ['id' => 'I', 'title' => 'Inactive']
                ]
            ]
        ]);
        break;

    // ===================== CASE 4: UPDATE_CATEGORY =====================
    case 'UPDATE_CATEGORY':
        $id = intval($_REQUEST['iVCatID'] ?? 0);
        $categoryName = db_input($_REQUEST['categoryName'] ?? '');
        $capacity = intval($_REQUEST['capacity'] ?? 0);
       // $rank = intval($_REQUEST['rank'] ?? 0);
       $rank =1;
      //  $status = db_input($_REQUEST['status'] ?? 'A');

        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Category ID is required for update"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate category name
        $validation = validateCategoryName($categoryName, $id);
        if (!$validation['valid']) {
            echo json_encode([
                "error" => [
                    "message" => $validation['message']
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate capacity
        if ($capacity < 0) {
            echo json_encode([
                "error" => [
                    "message" => "Capacity must be a positive number"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // // Validate status
        // if (!in_array($status, ['A', 'I'])) {
        //     echo json_encode([
        //         "error" => [
        //             "message" => "Invalid status. Must be (Active) or (Inactive)"
        //         ],
        //         "statusCode" => 400
        //     ]);
        //     exit;
        // }

        $sql = "UPDATE vehicle_category SET 
                    vName = '$categoryName',
                    iCapacity = $capacity
                WHERE iVCatID = $id";

        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            // Log the update operation
            LogMasterEdit($id, 'VCT', 'U', $categoryName, '', $user_id);

            echo json_encode([
                "data" => [
                    "message" => "Vehicle category updated successfully"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            echo json_encode([
                "data" => [
                    "message" => "No changes made to vehicle category"
                ],
                "token" => $Token,
                "statusCode" => 200
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to update vehicle category"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE 5: ADD_CATEGORY =====================
    case 'ADD_CATEGORY':
        $categoryName = db_input($_REQUEST['categoryName'] ?? '');
        $capacity = intval($_REQUEST['capacity'] ?? 0);
        $rank = 1;
        $status = 'A';

        // Validate category name
        $validation = validateCategoryName($categoryName, 0);
        if (!$validation['valid']) {
            echo json_encode([
                "error" => [
                    "message" => $validation['message']
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate capacity
        if ($capacity < 0) {
            echo json_encode([
                "error" => [
                    "message" => "Capacity must be a positive number"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Validate status
        // if (!in_array($status, ['A', 'I'])) {
        //     echo json_encode([
        //         "error" => [
        //             "message" => "Invalid status. Must be 'A' (Active) or 'I' (Inactive)"
        //         ],
        //         "statusCode" => 400
        //     ]);
        //     exit;
        // }

        $iVCatID = NextID('iVCatID', 'vehicle_category');

        $sql = "INSERT INTO vehicle_category (iVCatID, iCapacity, vName, iRank, cStatus) 
                VALUES ($iVCatID, $capacity, '$categoryName', $rank, '$status')";

        if (sql_query($sql)) {
            // Log the add operation
            LogMasterEdit($iVCatID, 'VCT', 'I', $categoryName, '', $user_id);

            echo json_encode([
                "statusCode" => 200,
                "message" => "Vehicle category added successfully",
                "data" => ["iVCatID" => $iVCatID]
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to add vehicle category"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE 6: DELETE_CATEGORY =====================
    case 'DELETE_CATEGORY':
        $id = intval($_REQUEST['iVCatID'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Category ID is required for deletion"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Check if category is being used by any vehicles
        $checkSql = "SELECT COUNT(*) as count FROM vehicle WHERE iCatID = $id AND cStatus = 'A'";
        $checkRes = sql_query($checkSql);
        $checkRow = sql_fetch_assoc($checkRes);

        if ($checkRow['count'] > 0) {
            echo json_encode([
                "error" => [
                    "message" => "Cannot delete category. It is being used by " . $checkRow['count'] . " vehicle(s)"
                ],
                "statusCode" => 409
            ]);
            exit;
        }

        // Update cStatus to 'X' instead of actual deletion
        $sql = "UPDATE vehicle_category SET cStatus = 'X' WHERE iVCatID = $id AND cStatus != 'X'";
        $result = sql_query($sql);

        if ($result && sql_affected_rows() > 0) {
            // Log the delete operation
            LogMasterEdit($id, 'VCT', 'D', '', '', $user_id);

            echo json_encode([
                "statusCode" => 200,
                "message" => "Vehicle category deleted successfully"
            ]);
        } else if ($result && sql_affected_rows() == 0) {
            echo json_encode([
                "statusCode" => 200,
                "message" => "Vehicle category not found or already deleted"
            ]);
        } else {
            echo json_encode([
                "error" => [
                    "message" => "Failed to delete vehicle category"
                ],
                "statusCode" => 500
            ]);
        }
        break;

    // ===================== CASE 7: TOGGLE_STATUS =====================
    case 'TOGGLE_STATUS':
        $id = intval($_REQUEST['iVCatID'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Category ID is required"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        // Check current status first to see if we're deactivating
        $currentSql = "SELECT cStatus FROM vehicle_category WHERE iVCatID = $id AND cStatus IN ('A', 'I')";
        $currentRes = sql_query($currentSql);
        
        if (sql_num_rows($currentRes) == 0) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle category not found"
                ],
                "statusCode" => 404
            ]);
            exit;
        }

        $currentRow = sql_fetch_assoc($currentRes);
        $newStatus = $currentRow['cStatus'] == 'A' ? 'I' : 'A';

        // If deactivating, check if category is being used by active vehicles
        if ($newStatus == 'I') {
            $checkSql = "SELECT COUNT(*) as count FROM vehicle WHERE iCatID = $id AND cStatus = 'A'";
            $checkRes = sql_query($checkSql);
            $checkRow = sql_fetch_assoc($checkRes);

            if ($checkRow['count'] > 0) {
                echo json_encode([
                    "error" => [
                        "message" => "Cannot deactivate category. It is being used by " . $checkRow['count'] . " active vehicle(s)"
                    ],
                    "statusCode" => 409
                ]);
                exit;
            }
        }

        // Use the reusable toggle function
        $result = toggleStatus($id, 'vehicle_category', 'iVCatID', 'cStatus', 'vName', 'VCT', $user_id);
        echo json_encode($result);
        break;

    // ===================== DEFAULT =====================
    default:
        echo json_encode([
            "error" => [
                "message" => "Invalid mode parameter"
            ],
            "statusCode" => 400
        ]);
        break;
}