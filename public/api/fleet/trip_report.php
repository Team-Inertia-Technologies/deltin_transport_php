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
        $rateFinalised = strtoupper(trim((string)($_REQUEST['rateFinalised'] ?? '')));

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
        $rateFinalisedOpt = [
            ['id' => 'all', 'name' => 'All'],
            ['id' => 'Y', 'name' => 'Y'],
            ['id' => 'N', 'name' => 'N'],
        ];
        $optArr = [
            'vendorOpt' => $vendorOpt,
            'vehicleOpt' => $vehicleOpt,
            'driverOpt' => $driverOpt,
            'locationOpt' => $LOCATION_ARR,
            'bookedByOpt' => $bookedByOpt,
            'sourceTypeOpt' => $sourceTypeOpt,
            'rateFinalisedOpt' => $rateFinalisedOpt
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

        if ($rateFinalised === 'Y') {
            $where .= " AND fb.cRateFinalised = 'Y'";
        } elseif ($rateFinalised === 'N') {
            $where .= " AND IFNULL(fb.cRateFinalised, 'N') <> 'Y'";
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
            fb.fRate,
            fb.fTotal,
            fb.cDisposal,
            fb.iVenChartType,
            fb.iRateChartLocID,
            fb.dtFromTime,
            fb.dtToTime,
            fb.iStartKm,
            fb.iEndKm,
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
        $rows = [];
        $bookingIds = [];
        while ($row = sql_fetch_assoc($res)) {
            $rows[] = $row;
            $bookingIds[] = intval($row['iFleet_BookingID']);
        }
        $logTimes = trip_report_log_times($bookingIds);
        $ratesByBooking = trip_report_rates_by_booking($bookingIds);
        $kindNames = trip_report_kind_names(array_keys(trip_report_optional_kinds()));
        $rowData = [];

        foreach ($rows as $row) {

            $bookingID = intval($row['iFleet_BookingID']);
            $start = $logTimes[$bookingID]['start'] ?? '';
            $end = $logTimes[$bookingID]['end'] ?? '';
            $duration = '';

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
            $active = $ratesByBooking[$bookingID] ?? ['lines' => [], 'total' => 0];
            $charges = trip_report_charge_lines($active['lines'], $kindNames);
            $finalRate = $active['lines'] === [] ? trip_report_final_rate($row) : $active['total'];
            $isDisposal = trip_report_is_disposal($row['cDisposal'] ?? 'N');
            $startTime = $isDisposal ? ($row['dtFromTime'] ?? '') : ($row['vPickUpTime'] ?? '');
            $endTime = $isDisposal ? ($row['dtToTime'] ?? '') : '';

            $rowData[] = [
                'id' => $bookingID,
                'bookingCode' => (!empty($row['vBookingCode'])) ? db_output2($row['vBookingCode']) : 'N/A',
                'fullName' => db_output2($row['vName']),
                'phone' => maskMobileNumber($row['vMobileNo']),
                'pickupDate' => date('d-m-Y', strtotime($row['vPickUpTime'])),
                'pickupTime' => date('h:i a', strtotime($row['vPickUpTime'])),
                'location' => db_output2($row['vPickUpLocation']),
                'destination' => db_output2($row['vDropLocation']),
                'startTime' => trip_report_display_time($startTime),
                'endTime' => trip_report_display_time($endTime),
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
                "disposal" => $isDisposal ? 'Y' : 'N',
                "chartType" => intval($row['iVenChartType'] ?? 0),
                "rateChartLocID" => intval($row['iRateChartLocID'] ?? 0),
                "rateFinalised" => (strtoupper(trim((string)($row['cRateFinalised'] ?? 'N'))) === 'Y') ? 'Y' : 'N',
                "finalRate" => $finalRate,
                "total" => $active['total'],
                "charges" => $charges,
                "startKm" => intval($row['iStartKm'] ?? 0),
                "endKm" => intval($row['iEndKm'] ?? 0)

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

    case 'SAVE_RATES':
        if (array_key_exists('trips', $_REQUEST) || array_key_exists('bookingIds', $_REQUEST)) {
            echo json_encode([
                "error" => ["message" => "Send one bookingId per request"],
                "statusCode" => 400
            ]);
            exit;
        }

        $bookingId = trip_report_one_booking_id();
        if ($bookingId <= 0) {
            echo json_encode([
                "error" => ["message" => "bookingId is required"],
                "statusCode" => 400
            ]);
            exit;
        }

        $booking = trip_report_booking($bookingId, $user_id);
        if ($booking === null) {
            echo json_encode([
                "error" => ["message" => "Booking not found"],
                "statusCode" => 404
            ]);
            exit;
        }

        $rawLines = $_REQUEST['charges'] ?? ($_REQUEST['lines'] ?? null);
        if (!is_array($rawLines)) {
            echo json_encode([
                "error" => ["message" => "charges must be a list"],
                "statusCode" => 400
            ]);
            exit;
        }

        sql_query("START TRANSACTION");
        $changed = false;
        foreach ($rawLines as $rawLine) {
            if (!is_array($rawLine)) {
                sql_query("ROLLBACK");
                echo json_encode([
                    "error" => ["message" => "Each charge must be an object"],
                    "statusCode" => 400
                ]);
                exit;
            }
            $kindId = intval($rawLine['rateKindId'] ?? ($rawLine['iRateKindID'] ?? 0));
            $amount = $rawLine['amount'] ?? ($rawLine['fAmount'] ?? null);
            $revised = fleetReviseBookingRate($bookingId, $kindId, $amount, $user_id);
            if (!empty($revised['error'])) {
                sql_query("ROLLBACK");
                echo json_encode([
                    "error" => ["message" => $revised['error']],
                    "statusCode" => 400
                ]);
                exit;
            }
            if (!empty($revised['changed'])) {
                $changed = true;
            }
        }

        if ($changed) {
            fleetSyncBookingRateTotal($bookingId);
        }
        sql_query("COMMIT");

        $saved = trip_report_booking($bookingId, $user_id);
        $payload = trip_report_rate_payload($saved ?: $booking);
        $payload = array_merge(['message' => 'Rates saved'], $payload);
        echo json_encode([
            "data" => $payload,
            "statusCode" => 200
        ]);
        break;

    case 'FINALISE_RATES':
        $bookingIds = trip_report_booking_ids();
        if ($bookingIds === []) {
            echo json_encode([
                "error" => ["message" => "bookingIds are required"],
                "statusCode" => 400
            ]);
            exit;
        }

        $finalised = [];
        $failed = [];
        foreach ($bookingIds as $bookingId) {
            $booking = trip_report_booking($bookingId, $user_id);
            if ($booking === null) {
                $failed[] = ["id" => $bookingId, "message" => "Booking not found"];
                continue;
            }
            sql_query("UPDATE fleet_booking SET cRateFinalised = 'Y' WHERE iFleet_BookingID = $bookingId");
            $finalised[] = $bookingId;
        }

        if ($finalised === []) {
            echo json_encode([
                "error" => ["message" => "Booking not found"],
                "failed" => $failed,
                "statusCode" => 404
            ]);
            exit;
        }

        echo json_encode([
            "data" => [
                "message" => "Rates finalised",
                "bookingIds" => $finalised,
                "rateFinalised" => "Y",
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
    $raw = $_REQUEST['bookingIds'] ?? ($_REQUEST['ids'] ?? []);
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

function trip_report_one_booking_id()
{
    $raw = $_REQUEST['bookingId'] ?? ($_REQUEST['id'] ?? null);
    if (is_array($raw)) {
        echo json_encode([
            "error" => ["message" => "Send one bookingId per request"],
            "statusCode" => 400
        ]);
        exit;
    }
    return intval($raw);
}

function trip_report_booking($bookingId, $userId)
{
    $bookingId = intval($bookingId);
    $userId = intval($userId);
    if ($bookingId <= 0) {
        return null;
    }
    $res = sql_query("SELECT iFleet_BookingID, vBookingCode, vName, iVendorID, iFleet_StationID, cRateFinalised,
                             cDisposal, iVenChartType, iRateChartLocID, iStartKm, iEndKm, fRate, fTotal
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

function trip_report_is_disposal($value)
{
    $flag = strtoupper(trim((string)$value));
    return in_array($flag, ['Y', '1', 'YES', 'TRUE'], true);
}

function trip_report_final_rate($row)
{
    $stored = $row['fTotal'] ?? null;
    if ($stored === null || $stored === '') {
        $stored = $row['fRate'] ?? 0;
    }
    return (float)$stored;
}

function trip_report_optional_kinds()
{
    return [
        4 => 'Night Charge',
        5 => 'Parking Charge'
    ];
}

function trip_report_kind_names($kindIds)
{
    $kindIds = array_values(array_unique(array_map('intval', $kindIds)));
    $kindIds = array_filter($kindIds, function ($id) {
        return $id > 0;
    });
    if ($kindIds === []) {
        return [];
    }
    $names = [];
    $res = sql_query("SELECT iRateKindID, vName FROM fleet_rate_kind WHERE iRateKindID IN (" . implode(',', $kindIds) . ") AND cStatus = 'A'");
    if ($res) {
        while ($row = sql_fetch_assoc($res)) {
            $names[intval($row['iRateKindID'])] = db_output2($row['vName']);
        }
    }
    return $names;
}

function trip_report_display_time($value)
{
    $parsed = fleetTripDateTime($value);
    if ($parsed === null || $parsed === false) {
        return '';
    }
    return date('d-m-Y H:i', strtotime($parsed));
}

function trip_report_log_times($bookingIds)
{
    $times = [];
    $bookingIds = array_values(array_filter(array_map('intval', $bookingIds)));
    if ($bookingIds === []) {
        return $times;
    }
    $res = sql_query("SELECT iFleet_BookingID, cRefType, dtAdded
                      FROM fleet_booking_log
                      WHERE iFleet_BookingID IN (" . implode(',', $bookingIds) . ")
                        AND cRefType IN ('S', 'C')
                      ORDER BY dtAdded ASC");
    if (!$res) {
        return $times;
    }
    while ($row = sql_fetch_assoc($res)) {
        $bookingId = intval($row['iFleet_BookingID']);
        if (!isset($times[$bookingId])) {
            $times[$bookingId] = ['start' => '', 'end' => ''];
        }
        if ($row['cRefType'] === 'S' && $times[$bookingId]['start'] === '') {
            $times[$bookingId]['start'] = $row['dtAdded'];
        }
        if ($row['cRefType'] === 'C') {
            $times[$bookingId]['end'] = $row['dtAdded'];
        }
    }
    return $times;
}

function trip_report_rates_by_booking($bookingIds)
{
    $byBooking = [];
    $bookingIds = array_values(array_filter(array_map('intval', $bookingIds)));
    if ($bookingIds === []) {
        return $byBooking;
    }
    $res = sql_query("SELECT r.iBkRateID, r.iFleet_BookingID, r.iRateKindID, k.vName AS kindName, r.fAmount
                      FROM fleet_booking_rate r
                      LEFT JOIN fleet_rate_kind k ON k.iRateKindID = r.iRateKindID
                      WHERE r.iFleet_BookingID IN (" . implode(',', $bookingIds) . ")
                        AND r.cStatus = 'A'
                      ORDER BY r.iBkRateID");
    $latest = [];
    if ($res) {
        while ($line = sql_fetch_assoc($res)) {
            $bookingId = intval($line['iFleet_BookingID']);
            $kindId = intval($line['iRateKindID']);
            $latest[$bookingId][$kindId] = [
                'iRateKindID' => $kindId,
                'kindName' => $line['kindName'],
                'fAmount' => (float)$line['fAmount']
            ];
        }
    }
    foreach ($latest as $bookingId => $kinds) {
        $lines = array_values($kinds);
        $total = 0;
        foreach ($lines as $line) {
            $total += $line['fAmount'];
        }
        $byBooking[$bookingId] = [
            'lines' => $lines,
            'total' => (float)number_format($total, 2, '.', '')
        ];
    }
    return $byBooking;
}

function trip_report_charge_lines($lines, $kindNames = null)
{
    $charges = [];
    $seen = [];
    foreach ($lines as $line) {
        $kindId = intval($line['iRateKindID']);
        $seen[$kindId] = true;
        $charges[] = [
            'rateKindId' => $kindId,
            'kindName' => $line['kindName'],
            'amount' => (float)$line['fAmount']
        ];
    }
    $optional = trip_report_optional_kinds();
    $names = is_array($kindNames) ? $kindNames : trip_report_kind_names(array_keys($optional));
    foreach ($optional as $kindId => $fallback) {
        if (isset($seen[$kindId])) {
            continue;
        }
        $charges[] = [
            'rateKindId' => $kindId,
            'kindName' => ($names[$kindId] ?? '') !== '' ? $names[$kindId] : $fallback,
            'amount' => 0
        ];
    }
    return $charges;
}

function trip_report_trip_fields($booking)
{
    $disposal = trip_report_is_disposal($booking['cDisposal'] ?? 'N') ? 'Y' : 'N';
    $fields = [
        'disposal' => $disposal,
        'chartType' => intval($booking['iVenChartType'] ?? 0)
    ];
    $locId = intval($booking['iRateChartLocID'] ?? 0);
    if ($locId > 0) {
        $fields['rateChartLocID'] = $locId;
    }
    if ($disposal === 'Y') {
        $fields['startKm'] = intval($booking['iStartKm'] ?? 0);
        $fields['endKm'] = intval($booking['iEndKm'] ?? 0);
    }
    return $fields;
}

function trip_report_rate_payload($booking)
{
    $bookingId = intval($booking['iFleet_BookingID']);
    $active = fleetActiveBookingRates($bookingId);
    $charges = trip_report_charge_lines($active['lines']);
    $finalRate = $active['lines'] === [] ? trip_report_final_rate($booking) : $active['total'];
    $payload = array_merge([
        'bookingId' => $bookingId,
        'bookingCode' => (!empty($booking['vBookingCode'])) ? db_output2($booking['vBookingCode']) : 'N/A',
        'fullName' => db_output2($booking['vName'] ?? ''),
        'rateFinalised' => (strtoupper(trim((string)($booking['cRateFinalised'] ?? 'N'))) === 'Y') ? 'Y' : 'N',
        'finalRate' => $finalRate,
        'charges' => $charges,
        'lines' => $charges,
        'total' => $active['total']
    ], trip_report_trip_fields($booking));
    return $payload;
}
