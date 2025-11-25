<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);
$mode = $_REQUEST['mode'] ?? '';

switch ($mode) {

    // ===================== CASE : GET_VEHICLE_CODE =====================
    case 'GET_VEHICLE_CODE':
        $id = intval($_REQUEST['iVehicleID'] ?? 0);

        if ($id <= 0) {
            echo json_encode([
                "error" => [
                    "message" => "Vehicle ID is required for deletion"
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $vehCode = enCodeParamSMS($id);
        echo json_encode([
            "statusCode" => 200,
            "data" => [
                "message" => "Vehicle code fetched successfully",
                "vehicleCode" => $vehCode
            ]
        ]);
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
