<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";
include "../api_common.php";
header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);
$mode = $_REQUEST['mode'] ?? '';
$Token = $_REQUEST['token'] ?? '';
$user_id = intval(DecodeParam($Token));
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
$NOW = NOW;
switch ($mode) {

    case 'LIST':

        $fromDate = $_REQUEST['fromDateTime'] ?? '';
        $toDate = $_REQUEST['toDateTime'] ?? '';
        $vehicleId = isset($_REQUEST['vehicleId']) ? intval($_REQUEST['vehicleId'] ?? 0) : 0 ;
        $driverId =  isset($_REQUEST['driverId']) ?  intval($_REQUEST['driverId'] ?? 0) : 0 ;

        $vehicleOpt = [['id' => 0, 'name' => 'All']];
        $res = sql_query("SELECT iVehicleID, vRnum FROM vehicle WHERE cStatus='A' ORDER BY vRnum");
        while ($r = sql_fetch_assoc($res)) {
            $vehicleOpt[] = ['id' => intval($r['iVehicleID']), 'name' => db_output2($r['vRnum'])];
        }

        $driverOpt = [['id' => 0, 'name' => 'All']];
        $res = sql_query("SELECT iDriverID, vName FROM driver WHERE cStatus='A' ORDER BY vName");
        while ($r = sql_fetch_assoc($res)) {
            $driverOpt[] = ['id' => intval($r['iDriverID']), 'name' => db_output2($r['vName'])];
        }
        $ql = "select iFleet_LocationID, vName from fleet_location order by vName";
        $rl = sql_query($ql, "supervisor_dashboard.77");
        $LOCATION_ARR = [['ID' => 0, 'NAME' => 'All']];
        if (sql_num_rows($rl)) {
            while ($lrow = sql_fetch_assoc($rl)) {
                $LOCATION_ARR[] = array("ID" => $lrow['iFleet_LocationID'], "NAME" => $lrow['vName']);
            }
        }
        $optArr = [
            'vehicleOpt' => $vehicleOpt,
            'driverOpt' => $driverOpt,
            'locationOpt' => $LOCATION_ARR
        ];

        $where = "fb.cStatus NOT IN ('X', 'C') AND fb.cType != 'C'";

        if (checkUserModuleAccess($user_id, 'AIRPORT_TRANSFER_REQ')) {
            $where .= " AND fb.iFleet_TrvPurID = 2";
        }

        if (!empty($fromDate)) {
            $where .= " AND DATE(fb.vPickUpTime) >= '" . db_input($fromDate) . "'";
        }

        if (!empty($toDate)) {
            $where .= " AND DATE(fb.vPickUpTime) <= '" . db_input($toDate) . "'";
        }
        if ($vehicleId > 0) {
            $where .= " AND fb.iVehicleID = $vehicleId";
        }

        if ($driverId > 0) {
            $where .= " AND fb.iDriverID = $driverId";
        }


        $sql = "
        SELECT
            fb.iFleet_BookingID,
            fb.vName,
            fb.vMobileNo,
            fb.vPickUpLocation,
            fb.vDropLocation,
            fb.vPickUpTime,
            fb.cType AS tripStatus,
            fb.cBookingFor as bookedFor,
            dr.vName AS driverName,
            dr.vMobileNum AS driverMobile,
            dr.iType AS driverType,
            v.vRnum AS vehicleRegNo,
            vcat.vName AS vehicleCategory,
             ftt.vName as travelTypeName,
             usr.vName as createdBy
        FROM fleet_booking fb
           LEFT JOIN vehicle v ON fb.iVehicleID = v.iVehicleID AND v.cStatus='A'
        LEFT JOIN driver dr ON fb.iDriverID = dr.iDriverID AND dr.cStatus='A'
        LEFT JOIN vehicle_category vcat ON v.iCatID = vcat.iVCatID
         LEFT JOIN fleet_traveltype ftt ON fb.iFleet_TrvTypeID = ftt.iFleet_TrvTypeID
           LEFT JOIN users usr ON usr.iUserID = fb.iAdded_UserID
        WHERE $where
        ORDER BY fb.vPickUpTime ASC
    ";

        $res = sql_query($sql);
        $rowData = [];

        while ($row = sql_fetch_assoc($res)) {

            $bookingID = intval($row['iFleet_BookingID']);
            $vehicle = '';
            if (!empty($row['vehicleRegNo'])) {
                $vehicle = db_output2($row['vehicleRegNo']);
                if (!empty($row['vehicleCategory'])) {
                    $vehicle .= ' (' . db_output2($row['vehicleCategory']) . ')';
                }
            }


            $driverType = $VEHICLE_DRIVER_TYPE[intval($row['driverType'] ?? 0)] ?? '';

            $rowData[] = [
                'id' => $bookingID,
                'fullName' => db_output2($row['vName']),
                'phone' => db_output2($row['vMobileNo']),
                'pickupDate' => date('d-m-Y', strtotime($row['vPickUpTime'])),
                'pickupTime' => date('h:i a', strtotime($row['vPickUpTime'])),
                'location' => db_output2($row['vPickUpLocation']),
                'destination' => db_output2($row['vDropLocation']),

                'vehicle' => $vehicle,
                'driver' => [
                    'name' => db_output2($row['driverName'] ?? ''),
                    'phone' => db_output2($row['driverMobile'] ?? ''),
                    'type' => $driverType
                ],
                'tripStatus' => $row['tripStatus'],
                'tripStatusText' => isset($FLEET_TRIP_STATUS[$row['tripStatus']]) ? $FLEET_TRIP_STATUS[$row['tripStatus']] : 'Unknown',
                'bookedFor' => $row['bookedFor'],
                'travelTypeName' => $row['travelTypeName'],
                'createdBy' => $row['createdBy']
            ];
        }

        echo json_encode([
            'data' => [
                'rowData' => $rowData,
                'optArr' => $optArr
            ],
            'statusCode' => 200
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
