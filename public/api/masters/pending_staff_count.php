<?php
    // ini_set('display_errors', 1);

    include "../../includes/common_api.php";
    include "../api_common.php";
    header('Content-Type: application/json');
    $postdata = file_get_contents("php://input");

    $request = json_decode($postdata, true);
    $_REQUEST = array_merge($_REQUEST, $request ?? []);
    $mode = $_REQUEST['mode'] ?? '';
    $Token = $_REQUEST['token'] ?? '';
    $user_id = intval(DecodeParam($Token));

    switch ($mode) {

        // ===================== CASE: ADD_ONLOAD =====================
        case 'PENDING_STAFF_COUNT':
           $pendingStaffCount = 0;
               $pendingStaffCount = (int) GetXFromYID("SELECT COUNT(*) FROM staff WHERE cStatus = 'P'");
                    $output = array(
                        'statusCode' => 200,
                        "data" => [
                            'pendingStaffCount' => $pendingStaffCount
                        ]
                    );
            
            echo json_encode($output);
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
