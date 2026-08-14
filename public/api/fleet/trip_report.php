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
        $vendorId = intval($_REQUEST['vendorId'] ?? 0);
        $vehicleId = intval($_REQUEST['vehicleId'] ?? 0);
        $driverId = intval($_REQUEST['driverId'] ?? 0);

        $vendorOpt = [['id' => 0, 'name' => 'All']];
        $res = sql_query("SELECT iVendorID, vName FROM vendor WHERE cStatus='A' ORDER BY vName");
        while ($r = sql_fetch_assoc($res)) {
            $vendorOpt[] = ['id' => intval($r['iVendorID']), 'name' => db_output2($r['vName'])];
        }


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
            'vendorOpt' => $vendorOpt,
            'vehicleOpt' => $vehicleOpt,
            'driverOpt' => $driverOpt,
            'locationOpt' => $LOCATION_ARR
        ];


        $where = "fb.cStatus NOT IN ('X', 'C') AND fb.cType='C'";

        if (!empty($fromDate)) {
            $where .= " AND DATE(fb.vPickUpTime) >= '" . db_input($fromDate) . "'";
        }

        if (!empty($toDate)) {
            $where .= " AND DATE(fb.vPickUpTime) <= '" . db_input($toDate) . "'";
        }

        if ($vendorId > 0) {
            $where .= " AND v.iVendorID = $vendorId";
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
            fb.vBookingCode,
            fb.iBookedBy,
            fb.vBookedBy,
             fb.iOriginal_Kms as calculatedKms,
            fb.iActual_Kms as ratechartKms,
            ven.vName AS vendorName,
            dr.vName AS driverName,
            dr.vMobileNum AS driverMobile,
            dr.iType AS driverType,
            v.vRnum AS vehicleRegNo,
            vcat.vName AS vehicleCategory,
             ftt.vName as travelTypeName,
            s.vName as bookedByName
        FROM fleet_booking fb
        LEFT JOIN vehicle v ON fb.iVehicleID = v.iVehicleID 
        LEFT JOIN vendor ven ON v.iVendorID = ven.iVendorID
        LEFT JOIN driver dr ON fb.iDriverID = dr.iDriverID
        LEFT JOIN vehicle_category vcat ON v.iCatID = vcat.iVCatID
        LEFT JOIN fleet_traveltype ftt ON fb.iFleet_TrvTypeID = ftt.iFleet_TrvTypeID
        LEFT JOIN fleet_staff s ON fb.iBookedBy = s.iFStaffID
        WHERE $where
        ORDER BY fb.vPickUpTime ASC
    ";

        $res = sql_query($sql);
        $rowData = [];

        while ($row = sql_fetch_assoc($res)) {

            $bookingID = intval($row['iFleet_BookingID']);

            /* ===== Duration: S → C ===== */

            $start = '';
            $end = '';
            $duration = '';

            $r = sql_query("SELECT dtAdded FROM fleet_booking_log
                         WHERE iFleet_BookingID=$bookingID AND cRefType='S'
                         ORDER BY dtAdded ASC LIMIT 1");
            if (sql_num_rows($r)) {
                $start = sql_fetch_assoc($r)['dtAdded'];
            }

            $r = sql_query("SELECT dtAdded FROM fleet_booking_log
                         WHERE iFleet_BookingID=$bookingID AND cRefType='C'
                         ORDER BY dtAdded DESC LIMIT 1");
            if (sql_num_rows($r)) {
                $end = sql_fetch_assoc($r)['dtAdded'];
            }

            if ($start && $end) {
                $diff = strtotime($end) - strtotime($start);
                if ($diff > 0) {
                    $h = floor($diff / 3600);
                    $m = floor(($diff % 3600) / 60);
                    $duration = sprintf('%02dh %02dm', $h, $m);
                }
            }

            $vehicle = '';
            if (!empty($row['vehicleRegNo'])) {
                $vehicle = db_output2($row['vehicleRegNo']);
                if (!empty($row['vehicleCategory'])) {
                    $vehicle .= ' (' . db_output2($row['vehicleCategory']) . ')';
                }
            }


            $driverType = $VEHICLE_DRIVER_TYPE[intval($row['driverType'] ?? 0)] ?? '';

            if (intval($row['iBookedBy']) > 0) {
                $bookedByName = db_output2($row['bookedByName'] ?? '');
            } else {
                $bookedByName = db_output2($row['vBookedBy'] ?? '');
            }

            $rowData[] = [
                'id' => $bookingID,
                'bookingCode' => (!empty($row['vBookingCode'])) ? db_output2($row['vBookingCode']) : 'N/A',
                'fullName' => db_output2($row['vName']),
                'phone' => db_output2($row['vMobileNo']),
                'pickupDate' => date('d-m-Y', strtotime($row['vPickUpTime'])),
                'pickupTime' => date('h:i a', strtotime($row['vPickUpTime'])),
                'location' => db_output2($row['vPickUpLocation']),
                'destination' => db_output2($row['vDropLocation']),
                'totalDuration' => $duration,
                'vendor' => db_output2($row['vendorName'] ?? ''),
                'vehicle' => $vehicle,
                'driver' => [
                    'name' => db_output2($row['driverName'] ?? ''),
                    'phone' => db_output2($row['driverMobile'] ?? ''),
                    'type' => $driverType
                ],
                'tripStatus' => $row['tripStatus'],
                'bookedFor' => $row['bookedFor'],
                'bookedBy' => $bookedByName,
                'travelTypeName' => $row['travelTypeName'],
                "calculatedKms" =>$row['calculatedKms'],
                "ratechartKms" => $row['ratechartKms']

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
