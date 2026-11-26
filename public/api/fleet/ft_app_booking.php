<?php
function pushFleetBookingToApp($iFleet_BookingID)
{
    $iFleet_BookingID = intval($iFleet_BookingID);
    if ($iFleet_BookingID < 1) {
        return array('ok' => false, 'message' => 'Booking id missing');
    }
    if (!defined('DELTIN_APP_URL') || DELTIN_APP_URL === '') {
        return array('ok' => false, 'message' => 'App URL is not configured');
    }

    $res = sql_query("SELECT * FROM fleet_booking WHERE iFleet_BookingID = $iFleet_BookingID AND cStatus <> 'X' LIMIT 1");
    if (!$res || sql_num_rows($res) < 1) {
        return array('ok' => false, 'message' => 'Booking not found');
    }
    $row = sql_fetch_assoc($res);
    $appReqID = intval($row['iAppReqID'] ?? 0);
    if ($appReqID < 1) {
        return array('ok' => true, 'message' => 'Not an app request');
    }

    $pickup = trim((string)($row['vPickUpTime'] ?? ''));
    $pickupDate = '';
    $pickupTime = '';
    $pickupTs = $pickup !== '' ? strtotime($pickup) : false;
    if ($pickupTs) {
        $pickupDate = date('Y-m-d', $pickupTs);
        $pickupTime = date('H:i:s', $pickupTs);
    }

    $vehicleId = intval($row['iVehicleID'] ?? 0);
    $driverId = intval($row['iDriverID'] ?? 0);
    $vehicleNo = '';
    $driverName = '';
    $driverMobile = '';
    if ($vehicleId > 0) {
        $vehicleRes = sql_query("SELECT vRnum FROM vehicle WHERE iVehicleID = $vehicleId LIMIT 1");
        if ($vehicleRes && sql_num_rows($vehicleRes) > 0) {
            $vehicleRow = sql_fetch_assoc($vehicleRes);
            $vehicleNo = (string)($vehicleRow['vRnum'] ?? '');
        }
    }
    if ($driverId > 0) {
        $driverRes = sql_query("SELECT vName, vMobileNum FROM driver WHERE iDriverID = $driverId LIMIT 1");
        if ($driverRes && sql_num_rows($driverRes) > 0) {
            $driverRow = sql_fetch_assoc($driverRes);
            $driverName = (string)($driverRow['vName'] ?? '');
            $driverMobile = (string)($driverRow['vMobileNum'] ?? '');
        }
    }

    $payload = array(
        'mode' => 'UPDATE_FROM_VIP',
        'appReqID' => $appReqID,
        'bookingId' => intval($row['iFleet_BookingID']),
        'bookingCode' => (string)($row['vBookingCode'] ?? ''),
        'from' => (string)($row['vPickUpLocation'] ?? ''),
        'to' => (string)($row['vDropLocation'] ?? ''),
        'latlong_from' => (string)($row['vLatLong_From'] ?? ''),
        'latlong_to' => (string)($row['vLatLong_To'] ?? ''),
        'dPickUpDate' => $pickupDate,
        'tPickUpTime' => $pickupTime,
        'pickUpDateTime' => $pickup,
        'pax' => intval($row['iPax'] ?? 0),
        'bookingCat' => intval($row['iFleet_BKCatID'] ?? 0),
        'travelPurpose' => intval($row['iFleet_TrvPurID'] ?? 0),
        'travelType' => intval($row['iFleet_TrvTypeID'] ?? 0),
        'vehiCat' => intval($row['iVehicleCatID'] ?? 0),
        'disposal' => (string)($row['cDisposal'] ?? 'N'),
        'returnTime' => (string)($row['tReturnTime'] ?? ''),
        'landMark' => (string)($row['vLandmark'] ?? ''),
        'intruc' => (string)($row['vInstructions'] ?? ''),
        'vVehicleNum' => $vehicleNo,
        'vDriverName' => $driverName,
        'vDriverNum' => $driverMobile,
    );
    $raw = callAPI('POST', DELTIN_APP_URL . 'api/rm/cab_request.php', json_encode($payload), array('Content-Type: application/json'));
    $decoded = json_decode((string)$raw, true);
    if (!is_array($decoded) || intval($decoded['statusCode'] ?? 0) !== 200) {
        $message = 'App did not accept the update.';
        if (is_array($decoded) && !empty($decoded['message'])) {
            $message = (string)$decoded['message'];
        }
        return array('ok' => false, 'message' => $message);
    }
    return array('ok' => true, 'message' => 'App request updated');
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== basename(__FILE__)) {
    return;
}

include "../../includes/common_api.php";
include "../api_common.php";
header('Content-Type: application/json');

$postdata = file_get_contents("php://input");
$request = json_decode($postdata, true);
if (!is_array($request)) {
    $request = [];
}
$_REQUEST = array_merge($_REQUEST, $request);
$mode = strtoupper(trim((string)($request['mode'] ?? '')));

if ($mode !== 'ADD') {
    echo json_encode([
        "error" => ["message" => "Not a valid mode"],
        "statusCode" => 400
    ]);
    exit;
}

$item = $request;
$iBookedBy = 0;
$vBookedBy = trim((string)($item['vBookedBy'] ?? ($item['bookedByName'] ?? '')));
$iFleet_TrvPurID = intval($item['travelPurpose'] ?? 0);
$iFleet_TrvTypeID = intval($item['travelType'] ?? 0);
$iFleet_BKCatID = intval($item['bookingCat'] ?? 0);
$iPropertyID = intval($item['property'] ?? 0);
$vName = trim((string)($item['name'] ?? ''));
$vMobileNo = trim((string)($item['mob'] ?? ''));
$iPax = intval($item['pax'] ?? 0);
$iBaggage = intval($item['baggage'] ?? 0);
$iAppReqID = intval($item['appReqID'] ?? 0);
$iVehicleCatID = intval($item['vehiCat'] ?? 0);
$vInstructions = trim((string)($item['intruc'] ?? ''));
$vRemarks = trim((string)($item['remarks'] ?? ''));
$vLandmark = trim((string)($item['landMark'] ?? ''));
$cDisposal = strtoupper(trim((string)($item['disposal'] ?? 'N'))) === 'Y' ? 'Y' : 'N';
$dPickUpDate = trim((string)($item['dPickUpDate'] ?? ''));
$tPickUpTime = trim((string)($item['tPickUpTime'] ?? ''));
if (strlen($tPickUpTime) === 5) {
    $tPickUpTime .= ':00';
}
$vPickUpTime = trim($dPickUpDate . ' ' . $tPickUpTime);
if ($vPickUpTime === '' || $dPickUpDate === '') {
    $vPickUpTime = trim((string)($item['pickUpDateTime'] ?? ''));
}
$vReturnTime = trim((string)($item['returnTime'] ?? ''));
if ($vReturnTime === '0000-00-00 00:00:00') {
    $vReturnTime = '';
}

$pickUpLocData = is_array($item['pickUpLoc'] ?? null) ? $item['pickUpLoc'] : [];
$dropLocData = is_array($item['dropLoc'] ?? null) ? $item['dropLoc'] : [];
$vPickUpLocation = trim((string)($pickUpLocData['loc'] ?? ''));
$vDropLocation = trim((string)($dropLocData['loc'] ?? ''));
$vLatLong_From = '';
if (($pickUpLocData['lat'] ?? '') !== '' && ($pickUpLocData['lng'] ?? '') !== '') {
    $vLatLong_From = $pickUpLocData['lat'] . ',' . $pickUpLocData['lng'];
}
$vLatLong_To = '';
if (($dropLocData['lat'] ?? '') !== '' && ($dropLocData['lng'] ?? '') !== '') {
    $vLatLong_To = $dropLocData['lat'] . ',' . $dropLocData['lng'];
}
if (array_key_exists('latlong_from', $item)) {
    $vLatLong_From = trim((string)$item['latlong_from']);
}
if (array_key_exists('latlong_to', $item)) {
    $vLatLong_To = trim((string)$item['latlong_to']);
}

if ($iAppReqID < 1 || $vName === '' || $vMobileNo === '' || $vPickUpTime === '') {
    echo json_encode([
        "error" => ["message" => "Required fields missing"],
        "statusCode" => 400
    ]);
    exit;
}

$existingRes = sql_query("SELECT iFleet_BookingID, vBookingCode, iGuestID FROM fleet_booking WHERE iAppReqID = $iAppReqID AND cStatus <> 'X' ORDER BY iFleet_BookingID DESC LIMIT 1");
if ($existingRes && sql_num_rows($existingRes) > 0) {
    $existingRow = sql_fetch_assoc($existingRes);
    echo json_encode([
        "data" => [
            "message" => "Booking already added",
            "bookingId" => intval($existingRow['iFleet_BookingID']),
            "bookingCode" => $existingRow['vBookingCode'] ?? '',
            "guestID" => intval($existingRow['iGuestID'] ?? 0)
        ],
        "statusCode" => 200
    ]);
    exit;
}

$trip = fleetTripValues($item, $cDisposal);
if (empty($trip['ok'])) {
    echo json_encode([
        "error" => ["message" => $trip['message'] ?? 'Invalid trip details'],
        "statusCode" => intval($trip['statusCode'] ?? 400)
    ]);
    exit;
}

$mobileDigits = preg_replace('/\D+/', '', $vMobileNo);
$mobileLast10 = substr($mobileDigits, -10);
$mobileMatch = "vMobileNo = '" . db_input($vMobileNo) . "'";
if (strlen($mobileLast10) === 10) {
    $mobileMatch .= " OR RIGHT(REPLACE(REPLACE(REPLACE(IFNULL(vMobileNo,''),'+',''),' ',''),'-',''), 10) = '" . db_input($mobileLast10) . "'";
}
$guestCheckRes = sql_query("SELECT iGuestID FROM guest WHERE cStatus = 'A' AND ($mobileMatch) ORDER BY iGuestID DESC LIMIT 1");
if ($guestCheckRes && sql_num_rows($guestCheckRes) > 0) {
    $guestRow = sql_fetch_assoc($guestCheckRes);
    $iGuestID = intval($guestRow['iGuestID']);
} else {
    $iGuestID = NextID('iGuestID', 'guest');
    $okGuest = sql_query("INSERT INTO guest (iGuestID, vName, vMobileNo, dtCreated, cStatus)
                          VALUES ($iGuestID, '" . db_input($vName) . "', '" . db_input($vMobileNo) . "', '" . NOW . "', 'A')");
    if (!$okGuest) {
        echo json_encode([
            "error" => ["message" => "Failed to create guest record"],
            "statusCode" => 500
        ]);
        exit;
    }
}

$iFleet_BookingID = NextID('iFleet_BookingID', 'fleet_booking');
$vBookingCode = GenerateFleetBookingCode($iFleet_BookingID);
$dtAdded = NOW;
$vReturnTimeVal = ($vReturnTime !== '') ? "'" . db_input($vReturnTime) . "'" : "NULL";
$fromSql = fleetSqlDateTime($trip['fromTime']);
$toSql = fleetSqlDateTime($trip['toTime']);
$iVenChartType = intval($trip['chartType']);
$iRateChartLocID = intval($trip['locId']);
$iStartKm = intval($trip['startKm']);
$iEndKm = intval($trip['endKm']);
$user_id = 0;

$sql = "INSERT INTO fleet_booking (
            iFleet_BookingID, vBookingCode, iBookedBy, vBookedBy, cBookingFor, iFleet_TrvPurID, iFleet_TrvTypeID, iPropertyID,
            iFleet_BKCatID, vInstructions, vRemarks, cReturnTrip, vName, vMobileNo, iGuestID, iFStaffID,
            iPax, iBaggage, vPickUpLocation, vPickUpTime, iOriginal_Kms,
            vDropLocation, vLatLong_From, vLatLong_To, vLandmark, iVehicleCatID, cDisposal, tReturnTime,
            iVendorID, iFleet_StationID, iVenChartType, iRateChartLocID, dtFromTime, dtToTime, iStartKm, iEndKm,
            dtAdded, iAdded_UserID, cStatus, iAppReqID
        ) VALUES (
            $iFleet_BookingID, '" . db_input($vBookingCode) . "', $iBookedBy, '" . db_input($vBookedBy) . "', 'G', $iFleet_TrvPurID, $iFleet_TrvTypeID, $iPropertyID,
            $iFleet_BKCatID, '" . db_input($vInstructions) . "', '" . db_input($vRemarks) . "', 'N', '" . db_input($vName) . "', '" . db_input($vMobileNo) . "', $iGuestID, 0,
            $iPax, $iBaggage, '" . db_input($vPickUpLocation) . "', '" . db_input($vPickUpTime) . "', 0,
            '" . db_input($vDropLocation) . "', '" . db_input($vLatLong_From) . "', '" . db_input($vLatLong_To) . "', '" . db_input($vLandmark) . "', $iVehicleCatID, '$cDisposal', $vReturnTimeVal,
            0, 0, $iVenChartType, $iRateChartLocID, $fromSql, $toSql, $iStartKm, $iEndKm,
            '" . db_input($dtAdded) . "', $user_id, 'A', $iAppReqID
        )";
$ok = sql_query($sql);
if (!$ok) {
    echo json_encode([
        "error" => ["message" => "Failed to add booking"],
        "statusCode" => 500
    ]);
    exit;
}

$date = date('d/m/Y', strtotime($vPickUpTime));
$pickup_time = date('H:i', strtotime($vPickUpTime));
SendConfirmationMessage($vMobileNo, $vName, $vPickUpLocation, $date, $vDropLocation, $pickup_time);
sql_query("INSERT INTO fleet_communication (cType, vCode, vMobile, cMode, dtCreated, iUserAdded) VALUES ('C', '+91', '" . db_input($vMobileNo) . "', 'WA', '$dtAdded', $user_id)");
LogBookingCreated($iFleet_BookingID, $vName, $user_id);
queueFleetBookingDatText($iFleet_BookingID, $user_id, 'created the booking');

echo json_encode([
    "data" => [
        "message" => "Booking added successfully",
        "bookingId" => $iFleet_BookingID,
        "bookingCode" => $vBookingCode,
        "guestID" => $iGuestID
    ],
    "statusCode" => 200
]);
exit;
