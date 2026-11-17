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
        $sourceType = strtolower(trim($_REQUEST['sourceType'] ?? ''));

        $loggedInVendorID = getVendorIDForUser($user_id);
        $vendorUserStations = [];
        if ($loggedInVendorID > 0) {
            $vendorUserStations = getVendorUserStations($user_id);
        }

        $vendorWhere = ($loggedInVendorID > 0) ? " AND iVendorID = $loggedInVendorID" : '';

        $vendorOpt = [['id' => 0, 'name' => 'All']];
        $res = sql_query("SELECT iVendorID, vName FROM vendor WHERE cStatus='A'$vendorWhere ORDER BY vName");
        while ($r = sql_fetch_assoc($res)) {
            $vendorOpt[] = ['id' => intval($r['iVendorID']), 'name' => db_output2($r['vName'])];
        }

        $vehicleOpt = [['id' => 0, 'name' => 'All']];
        $res = sql_query("SELECT iVehicleID, vRnum FROM vehicle WHERE cStatus='A'$vendorWhere ORDER BY vRnum");
        while ($r = sql_fetch_assoc($res)) {
            $vehicleOpt[] = ['id' => intval($r['iVehicleID']), 'name' => db_output2($r['vRnum'])];
        }

        $driverOpt = [['id' => 0, 'name' => 'All']];
        $res = sql_query("SELECT iDriverID, vName FROM driver WHERE cStatus='A'$vendorWhere ORDER BY vName");
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


        $bookedByOpt = [];
        $seenBookedBy = [];
        $bookedByRes = sql_query("SELECT DISTINCT TRIM(vBookedBy) AS vBookedBy FROM fleet_booking WHERE vBookedBy IS NOT NULL AND TRIM(vBookedBy) != '' ORDER BY TRIM(vBookedBy)");
        while ($bbRow = sql_fetch_assoc($bookedByRes)) {
            $name = db_output2($bbRow['vBookedBy']);
            $key = strtolower($name);
            if ($name === '' || isset($seenBookedBy[$key])) {
                continue;
            }
            $seenBookedBy[$key] = true;
            $bookedByOpt[] = $name;
        }

        $sourceTypeOpt = [
            ['id' => 'all', 'name' => 'All'],
            ['id' => 'self', 'name' => 'Self'],
            ['id' => 'vendor', 'name' => 'Vendor'],
        ];
        $optArr = [
            'vendorOpt' => $vendorOpt,
            'vehicleOpt' => $vehicleOpt,
            'driverOpt' => $driverOpt,
            'locationOpt' => $LOCATION_ARR,
            'bookedByOpt' => $bookedByOpt,
            'sourceTypeOpt' => $sourceTypeOpt
        ];


        $where = "fb.cStatus NOT IN ('X', 'C') AND fb.cType='C'";

        if ($loggedInVendorID > 0) {
            $where .= " AND fb.iVendorID = $loggedInVendorID";
            if (!empty($vendorUserStations)) {
                $where .= " AND fb.iFleet_StationID IN (" . implode(',', array_map('intval', $vendorUserStations)) . ")";
            }
        }

        if (checkUserModuleAccess($user_id, 'AIRPORT_TRANSFER_REQ')) {
            $where .= " AND fb.iFleet_TrvPurID = 2";
        }

        if (!empty($fromDate)) {
            $where .= " AND DATE(fb.vPickUpTime) >= '" . db_input($fromDate) . "'";
        }

        if (!empty($toDate)) {
            $where .= " AND DATE(fb.vPickUpTime) <= '" . db_input($toDate) . "'";
        }

        if ($sourceType === 'vendor') {
            $where .= " AND IFNULL(fb.iVendorID, 0) > 0";
        } elseif ($sourceType === 'self') {
            $where .= " AND IFNULL(fb.iVendorID, 0) = 0";
        }

        if ($loggedInVendorID <= 0 && $vendorId > 0) {
            $where .= " AND fb.iVendorID = $vendorId";
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
            fb.cRateFinalised,
            ven.vName AS vendorName,
            dr.vName AS driverName,
            dr.vMobileNum AS driverMobile,
            dr.iType AS driverType,
            v.vRnum AS vehicleRegNo,
            vcat.vName AS vehicleCategory,
             ftt.vName as travelTypeName,
            s.vName as bookedByName,
            p.vName as propertyName,
            fbc.vName as bookingCategoryName
        FROM fleet_booking fb
        LEFT JOIN vehicle v ON fb.iVehicleID = v.iVehicleID 
        LEFT JOIN vendor ven ON fb.iVendorID = ven.iVendorID
        LEFT JOIN driver dr ON fb.iDriverID = dr.iDriverID
        LEFT JOIN vehicle_category vcat ON v.iCatID = vcat.iVCatID
        LEFT JOIN fleet_traveltype ftt ON fb.iFleet_TrvTypeID = ftt.iFleet_TrvTypeID
        LEFT JOIN fleet_staff s ON fb.iBookedBy = s.iFStaffID
        LEFT JOIN property p ON fb.iPropertyID = p.iPropertyID
        LEFT JOIN fleet_bookingcategory fbc ON fb.iFleet_BKCatID = fbc.iFleet_BkCatID
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

            $bookedByName = 'other';
            if (intval($row['iBookedBy']) > 0) {
                $bookedByName = db_output2($row['bookedByName'] ?? '');
            }
            $instructionsBy = db_output2($row['vBookedBy'] ?? '');

            $rowData[] = [
                'id' => $bookingID,
                'bookingCode' => (!empty($row['vBookingCode'])) ? db_output2($row['vBookingCode']) : 'N/A',
                'fullName' => db_output2($row['vName']),
                'phone' => maskMobileNumber($row['vMobileNo']),
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
                'tripType' => isset($FLEET_TRIP_STATUS[$row['tripStatus']]) ? $FLEET_TRIP_STATUS[$row['tripStatus']] : '',
                'bookedFor' => $row['bookedFor'],
                'bookedBy' => $bookedByName,
                'instructionsBy' => $instructionsBy,
                'property' => db_output2($row['propertyName'] ?? ''),
                'bookingCategory' => db_output2($row['bookingCategoryName'] ?? ''),
                'travelTypeName' => $row['travelTypeName'],
                "calculatedKms" =>$row['calculatedKms'],
                "ratechartKms" => $row['ratechartKms'],
                "rateFinalised" => (strtoupper(trim((string)($row['cRateFinalised'] ?? 'N'))) === 'Y') ? 'Y' : 'N'

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

    case 'RATE_DETAILS':
        $bookingIds = trip_report_booking_ids();
        if ($bookingIds === []) {
            echo json_encode([
                "error" => ["message" => "bookingIds are required"],
                "statusCode" => 400
            ]);
            exit;
        }

        $trips = [];
        $failed = [];
        foreach ($bookingIds as $bookingId) {
            $booking = trip_report_booking($bookingId, $user_id);
            if ($booking === null) {
                $failed[] = ["id" => $bookingId, "message" => "Booking not found"];
                continue;
            }
            $trips[] = trip_report_rate_payload($booking);
        }

        echo json_encode([
            "data" => [
                "trips" => $trips,
                "failed" => $failed
            ],
            "statusCode" => 200
        ]);
        break;

    case 'SAVE_RATES':
        $rawTrips = $_REQUEST['trips'] ?? null;
        if (!is_array($rawTrips)) {
            $oneId = intval($_REQUEST['bookingId'] ?? ($_REQUEST['id'] ?? 0));
            $rawTrips = $oneId > 0 ? [[
                'bookingId' => $oneId,
                'lines' => $_REQUEST['lines'] ?? [],
                'rateFinalised' => $_REQUEST['rateFinalised'] ?? null
            ]] : null;
        }
        if (!is_array($rawTrips) || $rawTrips === []) {
            echo json_encode([
                "error" => ["message" => "trips are required"],
                "statusCode" => 400
            ]);
            exit;
        }

        $trips = [];
        $failed = [];
        foreach ($rawTrips as $rawTrip) {
            if (!is_array($rawTrip)) {
                $failed[] = ["id" => 0, "message" => "Each trip must be an object"];
                continue;
            }
            $bookingId = intval($rawTrip['bookingId'] ?? ($rawTrip['id'] ?? 0));
            $booking = trip_report_booking($bookingId, $user_id);
            if ($booking === null) {
                $failed[] = ["id" => $bookingId, "message" => "Booking not found"];
                continue;
            }

            $rawLines = $rawTrip['lines'] ?? [];
            if (!is_array($rawLines)) {
                $failed[] = ["id" => $bookingId, "message" => "lines must be a list"];
                continue;
            }

            $rateFinalised = null;
            if (array_key_exists('rateFinalised', $rawTrip) && $rawTrip['rateFinalised'] !== null && $rawTrip['rateFinalised'] !== '') {
                $rateFinalised = strtoupper(trim((string)$rawTrip['rateFinalised']));
                if ($rateFinalised !== 'Y' && $rateFinalised !== 'N') {
                    $failed[] = ["id" => $bookingId, "message" => "rateFinalised must be Y or N"];
                    continue;
                }
            }

            sql_query("START TRANSACTION");
            $lineError = '';
            $changed = false;
            foreach ($rawLines as $rawLine) {
                if (!is_array($rawLine)) {
                    $lineError = "Each rate line must be an object";
                    break;
                }
                $kindId = intval($rawLine['rateKindId'] ?? ($rawLine['iRateKindID'] ?? 0));
                $amount = $rawLine['amount'] ?? ($rawLine['fAmount'] ?? null);
                $revised = fleetReviseBookingRate($bookingId, $kindId, $amount, $user_id);
                if (!empty($revised['error'])) {
                    $lineError = $revised['error'];
                    break;
                }
                if (!empty($revised['changed'])) {
                    $changed = true;
                }
            }
            if ($lineError !== '') {
                sql_query("ROLLBACK");
                $failed[] = ["id" => $bookingId, "message" => $lineError];
                continue;
            }

            if ($changed) {
                fleetSyncBookingRateTotal($bookingId);
            }
            if ($rateFinalised === null && $changed) {
                $rateFinalised = 'Y';
            }
            if ($rateFinalised !== null) {
                sql_query("UPDATE fleet_booking SET cRateFinalised = '$rateFinalised' WHERE iFleet_BookingID = $bookingId");
                $booking['cRateFinalised'] = $rateFinalised;
            }
            sql_query("COMMIT");

            $saved = trip_report_booking($bookingId, $user_id);
            $trips[] = trip_report_rate_payload($saved ?: $booking);
        }

        echo json_encode([
            "data" => [
                "message" => "Rates saved",
                "trips" => $trips,
                "failed" => $failed
            ],
            "statusCode" => 200
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

function trip_report_booking_ids()
{
    $raw = $_REQUEST['bookingIds'] ?? ($_REQUEST['ids'] ?? ($_REQUEST['bookingId'] ?? []));
    if (!is_array($raw)) {
        $raw = [$raw];
    }
    $ids = [];
    foreach ($raw as $id) {
        $id = intval($id);
        if ($id > 0 && !in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }
    return $ids;
}

function trip_report_booking($bookingId, $userId)
{
    $bookingId = intval($bookingId);
    $userId = intval($userId);
    if ($bookingId <= 0) {
        return null;
    }
    $res = sql_query("SELECT iFleet_BookingID, vBookingCode, vName, iVendorID, iFleet_StationID, cRateFinalised
                      FROM fleet_booking
                      WHERE iFleet_BookingID = $bookingId AND cStatus NOT IN ('X', 'C') AND cType = 'C'
                      LIMIT 1");
    if (!$res || sql_num_rows($res) == 0) {
        return null;
    }
    $booking = sql_fetch_assoc($res);
    $vendorId = getVendorIDForUser($userId);
    if ($vendorId > 0) {
        if (intval($booking['iVendorID']) !== $vendorId) {
            return null;
        }
        $stations = getVendorUserStations($userId);
        if (!empty($stations) && !in_array(intval($booking['iFleet_StationID']), array_map('intval', $stations), true)) {
            return null;
        }
    }
    return $booking;
}

function trip_report_rate_payload($booking)
{
    $bookingId = intval($booking['iFleet_BookingID']);
    $active = fleetActiveBookingRates($bookingId);
    $lines = [];
    foreach ($active['lines'] as $line) {
        $lines[] = [
            'rateKindId' => intval($line['iRateKindID']),
            'kindName' => $line['kindName'],
            'amount' => (float)$line['fAmount']
        ];
    }
    return [
        'bookingId' => $bookingId,
        'bookingCode' => (!empty($booking['vBookingCode'])) ? db_output2($booking['vBookingCode']) : 'N/A',
        'fullName' => db_output2($booking['vName'] ?? ''),
        'rateFinalised' => (strtoupper(trim((string)($booking['cRateFinalised'] ?? 'N'))) === 'Y') ? 'Y' : 'N',
        'lines' => $lines,
        'total' => $active['total']
    ];
}
