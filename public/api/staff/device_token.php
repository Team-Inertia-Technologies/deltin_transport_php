<?php
    // ini_set('display_errors', 1);

    include "../../includes/common_api.php";
    //include "../api_common.php";
    header('Content-Type: application/json');
    $postdata = file_get_contents("php://input");

    $request = json_decode($postdata, true);
    $_REQUEST = array_merge($_REQUEST, $request ?? []);
    $mode = $_REQUEST['mode'] ?? '';
    $Token = $_REQUEST['token'] ?? '';
    $user_id = intval(DecodeParam($Token));
    $staffCheckSql = "SELECT iStaffID FROM staff WHERE iStaffID = $user_id AND cStatus = 'A'";
    $staffCheckRes = sql_query($staffCheckSql);

    if (sql_num_rows($staffCheckRes) == 0) {
        echo json_encode([
            "error" => [
                "message" => "User not found or inactive"
            ],
            "statusCode" => 401
        ]);
        exit;
    }
    switch ($mode) {

        // ===================== CASE: ADD_ONLOAD =====================
        case 'UPDATES_TOKEN':
            $deviceToken = $_REQUEST['device_token'] ?? '';
          //  $deviceToken = $request->device_token;

            if (!$deviceToken) {
                $output = array(
                    'statusCode' => 400,
                       "data" => [
                    'message' => 'Missing Device Token'
                       ]
                );
            } else {
                $updateQuery = "UPDATE staff SET vDeviceToken = '" . db_input($deviceToken) . "' WHERE iStaffID = $user_id";
                if (sql_query($updateQuery)) {
                    $output = array(
                        'statusCode' => 200,
                        "data" => [
                            'message' => 'Success!'
                        ]
                    );
                } else {

                    $output = array(
                        'statusCode' => 500,
                        "error" => [
                            'message' => 'Failed to save token'
                        ]
                    );
                }
            }
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
