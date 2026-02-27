<?php
//ini_set('display_errors', 1);

include "../../includes/common_api.php";

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

switch ($mode) {



    // ===================== CASE: DASHBOARD_COMPONENTS =====================	
    case 'DASHBOARD_COMPONENTS':

        $NOW = NOW;
        $VEH_CAT = GetXArrFromYID("SELECT iVCatID, vName from vehicle_category where cStatus='A' AND cType IN ('F','B') ORDER BY iRank", "3");
        $TODAY = date('Y-m-d');
        $TOTAL_VEHICLE_COUNT = GetXFromYID("select count(*) from vehicle where cStatus = 'A' and cServiceType IN ('F','B')");
        $TOTAL_DRIVER_COUNT = GetXFromYID("select count(*) from driver where cStatus = 'A'");

        $AVAILABLE_VEHICLE_COUNT = GetXFromYID("select count(iVehicleID) from vehicle where iVehicleID NOT IN (select iVehicleID from fleet_booking where cType NOT IN ('C','N') and iVehicleID IS NOT NULL) and cServiceType IN ('F','B')");
        //$AVAILABLE_DRIVER_COUNT = GetXFromYID("select count(*) from driver where iDriverID NOT IN (select iDriverID from fleet_booking where cType NOT IN ('C','N') and iDriverID IS NOT NULL)");
        $AVAILABLE_DRIVER_COUNT = GetXFromYID("select count(*) from driver where dtLoggedIn IS NOT NULL and cStatus = 'A'");

        $refreshRequestStreamTime = GetXFromYID("select vValue from sys_settings where vCode = 'REQSTREAM_PING_DURATION'");
        $refreshVehicleComponentTime = GetXFromYID("select vValue from sys_settings where vCode = 'VEHICLECOMPONENT_PING_DURATION'");
        $refreshActivityTimelineTime = GetXFromYID("select vValue from sys_settings where vCode = 'ACTIVITYTIMELINE_PING_DURATION'");
        $overtimelimit = GetXFromYID("SELECT COUNT(*) FROM driver WHERE dtLoggedIn IS NOT NULL AND TIMESTAMPDIFF(HOUR, dtLoggedIn, NOW()) > 8");

        $bookedForOpt = [['id' => 0, 'name' => 'Choose']];
        foreach ($FLEET_BOOKING_FOR as $id => $name) {
            $bookedForOpt[] = ['id' => $id, 'name' => $name];
        }

        $requestTypeArr = [['id' => 0, 'name' => 'All']];
        foreach ($REQUEST_TYPE_ARR as $id => $name) {
            $requestTypeArr[] = ['id' => $id, 'name' => $name];
        }

        $vehiCatOpt = [['id' => 0, 'name' => 'Choose']];
        foreach ($VEH_CAT as $id => $name) {
            $vehiCatOpt[] = ['id' => intval($id), 'name' => $name];
        }

        $vehiTypeArr = [['id' => 0, 'name' => 'All']];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $name) {
            $vehiTypeArr[] = ['id' => $id, 'name' => $name];
        }

        $vehiStatusArr = [['id' => 0, 'name' => 'All']];
        foreach ($VEHICLE_STATUS_ARR as $id => $name) {
            $vehiStatusArr[] = ['id' => $id, 'name' => $name];
        }

        $vehiStatusLocArr = [['id' => 0, 'name' => 'All']];
        foreach ($VEHICLE_STATUS_ARR2 as $id => $name) {
            $vehiStatusLocArr[] = ['id' => $id, 'name' => $name];
        }
        $ql = "select iFleet_LocationID, vName, vLat,vLong from fleet_location order by vName";
        $rl = sql_query($ql, "supervisor_dashboard.77");
        $LOCATION_ARR = [['ID' => 0, 'NAME' => 'All', "LAT" => '', "LONG" => '']];
        if (sql_num_rows($rl)) {
            while ($lrow = sql_fetch_assoc($rl)) {
                $LOCATION_ARR[] = array("ID" => $lrow['iFleet_LocationID'], "NAME" => $lrow['vName'], "LAT" => $lrow['vLat'], "LONG" => $lrow['vLong']);
            }
        }

        $fleetRate = sql_query("
    SELECT fr.iFleet_RateID,
        fr.iFleet_StationID, CONCAT(lf.vName, ' to ', lt.vName) AS vRouteName
    FROM fleet_ratechart fr
    LEFT JOIN fleet_location lf 
        ON lf.iFleet_LocationID = fr.iFleet_LocationID_From
    LEFT JOIN fleet_location lt 
        ON lt.iFleet_LocationID = fr.iFleet_LocationID_To
    WHERE fr.cStatus = 'A'
    AND '$NOW' BETWEEN fr.dtApplicable_From AND fr.dtApplicable_To
    ORDER BY fr.iFleet_StationID, fr.iRank
");
        $fleetRateArr = [];

        while ($row = sql_fetch_assoc($fleetRate)) {
            $fleetRateArr[$row['iFleet_StationID']][] = [
                'fleet_RateID' => $row['iFleet_RateID'],
                'routeName'     => $row['vRouteName']
            ];
        }
        $stationArr = [['id' => 0, 'name' => 'Choose', 'routes' => []]];

        $FLEET_STATION = GetXArrFromYID(
            "SELECT iFlt_StationID, vName FROM fleet_station WHERE cStatus='A' ORDER BY iRank",
            "3"
        );

        foreach ($FLEET_STATION as $id => $name) {
            $stationArr[] = [
                'id' => $id,
                'name' => $name,
                'routes' => isset($fleetRateArr[$id]) ? $fleetRateArr[$id] : []
            ];
        }        

        $optArr = [
            "requestTypeArr" => $requestTypeArr,
            "bookedForArr" => $bookedForOpt,
            "vehiCatArr" => $vehiCatOpt,
            "vehiTypeArr" => $vehiTypeArr,
            "driverTypeArr" => $vehiTypeArr,
            "vehiStatusArr" => $vehiStatusArr,
            "vehiStatusLocArr" => $vehiStatusLocArr,
            "stationArr" => $stationArr,
            "locationArr" => $LOCATION_ARR,
            "refreshRequestStreamTime" => (int) $refreshRequestStreamTime,
            "refreshVehicleComponentTime" => (int) $refreshVehicleComponentTime,
            "refreshActivityTimelineTime" => (int) $refreshActivityTimelineTime
        ];


        echo json_encode([
            "data" => [
                "totalDriver" => $TOTAL_DRIVER_COUNT,
                "avaiDriver" => $AVAILABLE_DRIVER_COUNT,
                "totalVehi" => $TOTAL_VEHICLE_COUNT,
                "avaiVehi" => $AVAILABLE_VEHICLE_COUNT,
                "optArrs" => $optArr,
                "overtimelimit" => intval($overtimelimit)
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: DASHBOARD_COMPONENTS END =====================		

    // ===================== CASE: REQUEST_STREAM =====================
    case 'REQUEST_STREAM':

        $cond = "";
        $currentDate = date('Y-m-d');
        $searchtxt = $_REQUEST['searchtxt'] ?? '';
        $type = $_REQUEST['type'] ?? '';
        $bookedFor = $_REQUEST['bookedFor'] ?? '';
        $fromTime = $currentDate . " " . $_REQUEST['fromTime'] . ":00" ?? $currentDate . " " . date('Y-m-d H:00:s');
        $toTime = $currentDate . " " . $_REQUEST['toTime'] . ":59" ?? $currentDate . " " . date('Y-m-d H:00:s', strtotime('+4 hours'));



        if (!empty($searchtxt)) {
            $cond .= " and ((vName like '%$searchtxt%') or (vMobileNo like '%$searchtxt%') or (vPickUpLocation like '%$searchtxt%') or (vDropLocation like '%$searchtxt%'))";
        }

        if (!empty($bookedFor)) {
            $cond .= " and cBookingFor = '$bookedFor'";
        }

        if (!empty($type)) {
            if ($type == 'D') {
                //$cond .= " and (NOW() > vPickUpTime - INTERVAL 2 HOUR) and (iDriverID = 0 or iVehicleID = 0)";
                $cond .= " and vPickUpTime IS NOT NULL AND vPickUpTime <> '' AND vPickUpTime < NOW() AND cType = 'N'";
            }
            if ($type == 'U') {
                $cond .= " and (iDriverID = '0' or iVehicleID = '0')";
            }
            if ($type == 'A') {
                $cond .= " and (iDriverID <> '0' and iVehicleID <> '0')";
            }
        }

        if (!empty($fromTime) || !empty($toTime)) {
            if (!empty($fromTime)) {
                $cond .= " and vPickUpTime >= '$fromTime'";
            }

            if (!empty($toTime)) {
                $cond .= " and vPickUpTime <= '$toTime'";
            }
        }

        $FLEET_STAFF_ARR = GetXArrFromYID("select iFStaffID, vName from fleet_staff order by vName", 3);
        $FLEET_CATEGORY_ARR = GetXArrFromYID("select iFleet_BkCatID, vName from fleet_bookingcategory order by vName", 3);
        $PROPERTY_ARR = GetXArrFromYID("select iPropertyID, vName from property order by iRank", 3);
        $VEHICLE_CAT_ARR = GetXArrFromYID("select iVCatID, vName from vehicle_category order by iRank", 3);
        $TRAVEL_PURPOSE_ARR = GetXArrFromYID("select iFleet_TrvPurID, vName from fleet_travelpurpose order by iRank", 3);
        $TRAVEL_TYPE_ARR = GetXArrFromYID("select iFleet_TrvTypeID, vName from fleet_traveltype order by iRank", 3);
        $DRIVER_ARR = array();
        $qd = "select iDriverID, vName from driver order by vName";
        $rd = sql_query($qd, "supervisor_dashboard.38");
        if (sql_num_rows($rd)) {
            while ($drow = sql_fetch_assoc($rd)) {
                $DRIVER_ARR[$drow['iDriverID']] = array("ID" => $drow['iDriverID'], "NAME" => $drow['vName']);
            }
        }
        $VEHICLE_ARR = array();
        $qv = "select iVehicleID, vRnum, iCatID, iType from vehicle order by vName";
        $rv = sql_query($qv, "supervisor_dashboard.38");
        if (sql_num_rows($rv)) {
            while ($vrow = sql_fetch_assoc($rv)) {
                $VEHICLE_ARR[$vrow['iVehicleID']] = array("ID" => $vrow['iVehicleID'], "REG" => $vrow['vRnum'], "CAT" => $vrow['iCatID'], "TYPE" => $vrow['iType']);
            }
        }

        // Fetch booking data
        $bookingSql = "select iFleet_BookingID, vName, vMobileNo, cBookingFor, vPickUpLocation, vDropLocation, vPickUpTime, iPax, iBaggage, iBookedBy, vBookedBy, iPropertyID, iVehicleCatID, iFleet_TrvPurID, iFleet_TrvTypeID, cDisposal, iVehicleID, iDriverID, vInstructions, iFleet_BKCatID, cType, vComments, iFleet_StationID, iFleet_RateID from fleet_booking where 1 $cond and cType NOT IN ('C','S','G','P','R') and cStatus <> 'C' order by (iDriverID IS NULL OR iDriverID = 0) DESC, (iVehicleID IS NULL OR iVehicleID = 0) DESC, vPickupTime ASC";
        //echo $bookingSql."<br>";
        $bookingRes = sql_query($bookingSql);

        $rowData = [];
        while ($row = sql_fetch_assoc($bookingRes)) {
            $bg_driver_assignment = "rgb(255, 227, 227)";
            $color_driver_assignment = "rgb(227, 71, 80)";
            if (!empty($row['iDriverID'])) {
                $bg_driver_assignment = "rgb(219, 255, 209)";
                $color_driver_assignment = "rgb(0, 161, 75)";
            }

            $bg_vehicle_assignment = "rgb(255, 227, 227)";
            $color_vehicle_assignment = "rgb(227, 71, 80)";
            if (!empty($row['iVehicleID'])) {
                $bg_vehicle_assignment = "rgb(219, 255, 209)";
                $color_vehicle_assignment = "rgb(0, 161, 75)";
            }
            $border = "rgb(255, 87, 51)";

            $type_status = 'U';

            if (!empty($row['iDriverID']) || !(empty($row['iVehicleID']))) {
                $border = "rgb(255, 152, 0)";
                $type_status = 'U';
            }

            if (!empty($row['iDriverID']) && !(empty($row['iVehicleID']))) {
                $border = "rgb(76, 175, 80)";
                $type_status = 'A';
            }

            if (empty($row['iDriverID']) && empty($row['iVehicleID'])) {
                $border = "rgb(255, 87, 51)";
                $type_status = 'A';
            }
            $is_staff = false;
            $is_guest = false;
            if ($row['cBookingFor'] == 'S') {
                $is_staff = true;
            }

            if ($row['cBookingFor'] == 'G') {
                $is_guest = true;
            }

            $is_disposal = false;
            if ($row['cDisposal'] == 'Y') {
                $is_disposal = true;
            }
            $currentTime = date('Y-m-d H:i:s');
            if (!empty($row['vPickUpTime']) && strtotime($row['vPickUpTime']) < strtotime($currentTime) && $row['cType'] == 'N') {
                $type_status = 'D';
            }
            $bookedByName = db_output2($FLEET_STAFF_ARR[$row['iBookedBy']] ?? '');
            if ($row['iBookedBy'] == '0') {
                $bookedByName = db_output2($row['vBookedBy'] ?? '');
            }

            $dt = strtotime($row['vPickUpTime']);

            if (date('Y-m-d', $dt) === date('Y-m-d')) {
                $dateTime = date('g:i A', $dt);
            } else {
                $dateTime = date('d M g:i A', $dt);
            }

            $rowData[] = [
                'id' => intval($row['iFleet_BookingID']),
                'passengerName' => db_output2($row['vName'] ?? ''),
                'mobNum' => db_output2($row['vMobileNo'] ?? ''),
                'staff' => $is_staff,
                'guest' => $is_guest,
                'from' => db_output2($row['vPickUpLocation'] ?? ''),
                'to' => db_output2($row['vDropLocation'] ?? ''),
                'time' => $dateTime ?? '',
                'typeStatus' => '',
                'type' => $type_status,
                'pax' => strval($row['iPax'] ?? '0'),
                'bags' => strval($row['iBaggage'] ?? '0'),
                'bookedByName' => $bookedByName,
                'bookingCat' => db_output2($FLEET_CATEGORY_ARR[$row['iFleet_BKCatID']] ?? ''),
                'bookedFor' => db_output2($row['cBookingFor'] ?? ''),
                'property' => db_output2($PROPERTY_ARR[$row['iPropertyID']] ?? ''),
                'vehicle_category' => $row['iVehicleCatID'] ?? '',
                'travelPurpose' => db_output2($TRAVEL_PURPOSE_ARR[$row['iFleet_TrvPurID']] ?? ''),
                'travelType' => db_output2($TRAVEL_TYPE_ARR[$row['iFleet_TrvTypeID']] ?? ''),
                'isDisposal' => $is_disposal,
                'instruction' => db_output2($row['vInstructions'] ?? ''),
                'comment' => db_output2($row['vComments'] ?? ''),
                'driverAssigned' => (isset($row['iDriverID']) && !empty($row['iDriverID'])) ? true : false,
                'driverName' => (isset($row['iDriverID']) && !empty($row['iDriverID'])) ? $DRIVER_ARR[$row['iDriverID']]['NAME'] : "",
                'vehicleAssigned' => (isset($row['iVehicleID']) && !empty($row['iVehicleID'])) ? true : false,
                'vehicle' => (isset($row['iVehicleID']) && !empty($row['iVehicleID'])) ? db_output2($VEHICLE_CAT_ARR[$row['iVehicleCatID']] . " " . $VEHICLE_ARR[$row['iVehicleID']]['REG']) : "",
                'vehicleType' => (isset($row['iVehicleID']) && !empty($row['iVehicleID'])) ? $VEHICLE_ARR[$row['iVehicleID']]['TYPE'] : 0,
                'borderColor' => $border,
                'fleetRateId' => (int) $row['iFleet_RateID'],
                'fleetStationId' => (int) $row['iFleet_StationID']
            ];
        }
        echo json_encode([
            "data" => [
                "requestsArr" => $rowData
            ],
            "statusCode" => 200
        ]);
        break;
    // ===================== CASE: REQUEST_STREAM END =====================		

    // ===================== CASE: ASSIGN_API ======================		
    case 'ASSIGN_API':

        $cond = "";

        //$searchtxt = $_REQUEST['searchtxt'] ?? '';
        //$type = $_REQUEST['type'] ?? '';
        //$bookedFor = $_REQUEST['bookedFor'] ?? '';
        //$pickup = $_REQUEST['pickup'] ?? '';
        //$drop = $_REQUEST['drop'] ?? '';
        $id = $_REQUEST['id'] ?? '';

        $VEHI_TYPE_ARR = array();

        $q = "select * from vehicle where 1";
        $r = sql_query($q, "supervisor_dashboard.238");

        if (sql_num_rows($r)) {

            while ($vrow = sql_fetch_assoc($r)) {

                $VEHI_TYPE_ARR[$vrow['iVehicleID']] = array("TYPE" => $vrow['iType']);
            }
        }

        if (!empty($id)) {
            $cond .= " and iFleet_BookingID = $id";
        }

        /*if(!empty($searchtxt)){
            $cond .= " and ((vName like '%$searchtxt%') or (vMobileNo like '%$searchtxt%'))";
        }

        if(!empty($bookedFor)){
            $cond .= " and cBookedFor = '$bookedFor'";
        }

        if(!empty($type)){
            if($type == 'D'){
                $cond .= " and (NOW() > trip_datetime - INTERVAL 2 HOUR) and (iDriverID = 0 or iVehicleID = 0)";
            }
            if($type == 'U'){
                $cond .= " and (iDriverID = '0' or iVehicleID = '0')";
            }
            if($type == 'A'){
                $cond .= " and (iDriverID <> '0' and iVehicleID <> '0')";
            }			
        }	

        if(!empty($pickup)){
            $cond .= " and (vPickUpLocation like '%$searchtxt%')";			
        }

        if(!empty($drop)){
            $cond .= " and (vDropLocation like '%$searchtxt%')";
        }*/

        $bookingSql = "select iFleet_BookingID, iVehicleCatID, vPickUpLocation, vDropLocation, vLatLong_From, vLatLong_To, vInstructions, vRemarks, tReturnTime, iVehicleID from fleet_booking where 1 $cond order by vPickupTime ASC";
        $bookingRes = sql_query($bookingSql);

        $rowData = [];
        while ($row = sql_fetch_assoc($bookingRes)) {
            if (isset($row['vLatLong_From']) && !empty($row['vLatLong_From'])) {
                $from_latlong_arr = explode(",", $row['vLatLong_From']);
            } else {
                $from_latlong_arr[0] = '0';
                $from_latlong_arr[1] = '0';
            }
            if (isset($row['vLatLong_To']) && !empty($row['vLatLong_To'])) {
                $to_latlong_arr = explode(",", $row['vLatLong_To']);
            } else {
                $to_latlong_arr[0] = '0';
                $to_latlong_arr[1] = '0';
            }

            $dt = strtotime($row['tReturnTime']);

            if (date('Y-m-d', $dt) === date('Y-m-d')) {
                $dateTime = date('g:i A', $dt);
            } else {
                $dateTime = date('d M g:i A', $dt);
            }

            $rowData[] = [
                'requestId' => intval($row['iFleet_BookingID']),
                'vehiCatId' => $row['iVehicleCatID'] ?? 0,
                'vehiTypeId' => $VEHI_TYPE_ARR[$row['iVehicleID']]['TYPE'] ?? 0,
                'pickUpLoc' => array('lat' => $from_latlong_arr[0], 'log' => $from_latlong_arr[1], 'loc' => $row['vPickUpLocation']),
                'pickUpLoc' => array('lat' => $to_latlong_arr[0], 'log' => $to_latlong_arr[1], 'loc' => $row['vDropLocation']),
                'returnTime' => $dateTime,
                'remark1' => db_output2($row['vInstructions'] ?? ''),
                'remark2' => db_output2($row['vRemarks'] ?? ''),
            ];
        }
        echo json_encode([
            "data" => [
                "rowData" => $rowData
            ],
            "statusCode" => 200
        ]);
        break;
    // ===================== CASE: ASSIGN_API END =====================	


    // ===================== CASE: VEHICLE_COMPOENT ======================		
    case 'VEHICLE_COMPONENT':

        $cond = "";

        $currentDate = date('Y-m-d');
        $searchtxt = $_REQUEST['searchtxt'] ?? '';
        $vehitype = $_REQUEST['vehiType'] ?? '';
        $drivertype = $_REQUEST['driverType'] ?? '';
        $category = $_REQUEST['category'] ?? '';
        $status = $_REQUEST['status'] ?? '';
        $from = $currentDate . " " . $_REQUEST['fromTime'] . ":00" ?? date('Y-m-d H:00:s');
        $to = $currentDate . " " . $_REQUEST['toTime'] . ":59" ?? date('Y-m-d H:00:s', strtotime('+4 hours'));
        //$from = date("H:i:s", strtotime($_REQUEST['from'])) ?? '';
        //$to = date("H:i:s", strtotime($_REQUEST['to'])) ?? '';

        $vehicleCategorySql = "SELECT iVCatID, vName, iCapacity FROM vehicle_category WHERE cType IN ('F') AND cStatus = 'A' ORDER BY vName";
        $vehicleCategoryRes = sql_query($vehicleCategorySql);

        $vehicleCategories = [];
        while ($categoryRow = sql_fetch_assoc($vehicleCategoryRes)) {
            $vehicleCategories[] = [
                'id' => intval($categoryRow['iVCatID']),
                'name' => db_output2($categoryRow['vName']),
                'capacity' => intval($categoryRow['iCapacity'])
            ];
        }

        $VEHI_TYPE_ARR = array();

        $q = "select * from vehicle where 1";
        $r = sql_query($q, "supervisor_dashboard.238");

        if (sql_num_rows($r)) {

            while ($vrow = sql_fetch_assoc($r)) {

                $VEHI_TYPE_ARR[$vrow['iVehicleID']] = array("TYPE" => $vrow['iType']);
            }
        }

        if (!empty($searchtxt)) {
            $cond .= " and ((vc.vName like '%$searchtxt%') or (d.vMobileNo like '%$searchtxt%') or (d.vName like '%$searchtxt%') or (v.vRnum like '%$searchtxt%'))";
        }

        if (!empty($category)) {
            $cond .= " and vc.iVCatID = '$category'";
        }

        if (!empty($vehitype)) {

            $cond .= " and v.iType = '$vehitype'";
        }

        if (!empty($drivertype)) {

            $cond .= " and d.iType = '$drivertype'";
        }

        if (!empty($status)) {
            $cond .= " and fb.cType = '$status'";
        }

        $vehicleData = GetVehicle_BasedOnSearch2($vehitype, $category, 'Y', $from, $to, $status);

        $vehicles = [];
        $currentlyAssigned = [];
        $availableVehicles = [];

foreach ($vehicleData as $vehicleID => $vehData) {

    /* =========================
       RESET ALL VARIABLES FIRST
    ========================== */

    $lastAssignedTime   = null;
    $lastAssigned       = false;
    $assignedVeh        = false;

    $nextTripDateTime   = null;
    $nextBookingId      = null;
    $nextBookingStatus  = null;

    $prevTripDateTime   = null;
    $prevBookingId      = null;
    $prevBookingStatus  = null;

    $driverStatus       = false;

    /* =========================
       KEYWORD FILTER
    ========================== */

    if (!empty($searchtxt)) {
        $keywordMatch = false;

        if (
            stripos($vehData['NUM'], $searchtxt) !== false ||
            stripos($vehData['NAME'], $searchtxt) !== false
        ) {
            $keywordMatch = true;
        }

        if (!$keywordMatch) {
            continue;
        }
    }

    /* =========================
       CHECK IF VEHICLE ASSIGNED
    ========================== */

    $tripAssignmentSql = "
        SELECT iVehicleID 
        FROM fleet_booking 
        WHERE iVehicleID = " . (int)$vehicleID . " 
        AND cStatus = 'A'
    ";

    $tripAssignmentRes = sql_query($tripAssignmentSql);

    if (sql_num_rows($tripAssignmentRes) > 0) {
        $assignedVeh = true;
    }

    /* =========================
       BOOKING LOGIC
    ========================== */

    if (!empty($vehData['BOOKINGS'])) {

        $now = time();
        $bookings = $vehData['BOOKINGS'];

        /* ===== NEXT / FUTURE TRIP ===== */

        $futureBookings = array_filter($bookings, function ($b) use ($now) {
            return strtotime($b['PICKUP_TIME']) >= $now;
        });

        if (!empty($futureBookings)) {

            usort($futureBookings, function ($a, $b) {
                return strtotime($a['PICKUP_TIME']) <=> strtotime($b['PICKUP_TIME']);
            });

            $nextTrip = $futureBookings[0];
            $nextTripTime = $nextTrip['PICKUP_TIME'];

            $nextTripDateTime =
                (date('Y-m-d', strtotime($nextTripTime)) === date('Y-m-d'))
                ? date('g:i A', strtotime($nextTripTime))
                : date('d M g:i A', strtotime($nextTripTime));

            $nextBookingId     = $nextTrip['ID'];
            $nextBookingStatus = $nextTrip['STATUS'];
        }

        /* ===== PREVIOUS TRIP ===== */

        $pastBookings = array_filter($bookings, function ($b) use ($now) {
            return strtotime($b['PICKUP_TIME']) < $now;
        });

        if (!empty($pastBookings)) {

            usort($pastBookings, function ($a, $b) {
                return strtotime($b['PICKUP_TIME']) <=> strtotime($a['PICKUP_TIME']);
            });

            $prevTrip = $pastBookings[0];
            $prevTripTime = $prevTrip['PICKUP_TIME'];

            $prevTripDateTime =
                (date('Y-m-d', strtotime($prevTripTime)) === date('Y-m-d'))
                ? date('g:i A', strtotime($prevTripTime))
                : date('d M g:i A', strtotime($prevTripTime));

            $prevBookingId     = $prevTrip['ID'];
            $prevBookingStatus = $prevTrip['STATUS'];
        }
    }

    /* =========================
       DRIVER LOGIN STATUS
    ========================== */

    if (!empty($vehData['DRIVER_ID'])) {

        $driverLoggedIn = GetXFromYID(
            "SELECT dtLoggedIn 
             FROM driver 
             WHERE iDriverID = " . (int)$vehData['DRIVER_ID'] . " 
             AND dtLoggedIn IS NOT NULL"
        );

        if (!empty($driverLoggedIn)) {
            $driverStatus = true;
        }
    }

    /* =========================
       FORMAT FINAL OUTPUT
    ========================== */

    $vehicleDataFormatted = [
        'id'              => (int)$vehicleID,
        'regNo'           => db_output2($vehData['NUM']),
        'vehicletype'     => (int)$vehData['TYPE_ID'],
        'categoryId'      => (int)$vehData['CAT_ID'],
        'categoryName'    => db_output2(
                                GetXFromYID("SELECT vName 
                                             FROM vehicle_category 
                                             WHERE iVCatID = " . (int)$vehData['CAT_ID'])
                            ),
        'capacity'        => (int)GetXFromYID(
                                "SELECT iCapacity 
                                 FROM vehicle_category 
                                 WHERE iVCatID = " . (int)$vehData['CAT_ID']
                            ),
        'lastAssigned'    => $lastAssigned,
        'lastAssignedTime'=> $lastAssignedTime,
        'alreadyAssigned' => $assignedVeh,
        'driverID'        => (int)($vehData['DRIVER_ID'] ?? 0),
        'driverName'      => db_output2($vehData['DRIVER_NAME'] ?? ''),
        'driverMobile'    => db_output2($vehData['DRIVER_NUM'] ?? ''),
        'driverType'      => $vehData['DRIVER_TYPE'] ?? '',
        'nextTripTime'    => $nextTripDateTime ?? '',
        'prevTripTime'    => $prevTripDateTime ?? '',
        'bookingId'       => $nextBookingId ? (int)$nextBookingId : 0,
        'disposal'        => false,
        'status'          => !empty($nextBookingStatus) ? $nextBookingStatus : 'A',
        'driverStatus'    => $driverStatus,
    ];

    /* =========================
       SEPARATE ASSIGNED FIRST
    ========================== */

    if ($assignedVeh) {
        $currentlyAssigned[] = $vehicleDataFormatted;
    } else {
        $availableVehicles[] = $vehicleDataFormatted;
    }
}

        // Merge arrays with currently assigned vehicles first
        $rowData = array_merge($currentlyAssigned, $availableVehicles);

        //$bookingSql = "SELECT fb.iFleet_BookingID AS iFleet_BookingID, v.vRnum AS vRnum, v.iType AS iType, vc.iVCatID AS iVCatID, vc.vName AS vCatName, vc.iCapacity AS iCapacity, CASE WHEN MAX(last_fb.vPickupTime) IS NOT NULL THEN TRUE ELSE FALSE END AS lastAssigned, MAX(last_fb.vPickupTime) AS lastAssignedTime, CASE WHEN fb.iFleet_BookingID IS NOT NULL THEN TRUE ELSE FALSE END AS alreadyAssigned, fb.iDriverID AS driverID, d.vName AS driverName, d.vMobileNum AS driverMobile, d.iType as driverType, MIN(next_fb.vPickupTime) AS nextTripTime, fb.cDisposal AS disposal FROM vehicle v JOIN vehicle_category vc ON vc.iVCatID = v.iCatID LEFT JOIN fleet_booking fb ON fb.iVehicleID = v.iVehicleID AND fb.cStatus = 'A' LEFT JOIN driver d ON d.iDriverID = fb.iDriverID LEFT JOIN fleet_booking last_fb ON last_fb.iVehicleID = v.iVehicleID AND last_fb.vPickupTime < NOW() AND last_fb.cStatus = 'A' LEFT JOIN fleet_booking next_fb ON next_fb.iVehicleID = v.iVehicleID AND next_fb.vPickupTime > NOW() AND next_fb.cStatus = 'A' where 1 $cond GROUP BY fb.iVehicleID ORDER BY v.vRnum";
        /*$bookingSql = "SELECT fb.iVehicleID, fb.iFleet_BookingID AS iFleet_BookingID, v.vRnum AS vRnum, v.iType AS iType, vc.iVCatID AS iVCatID, vc.vName AS vCatName, vc.iCapacity AS iCapacity, CASE WHEN MAX(last_fb.vPickupTime) IS NOT NULL THEN TRUE ELSE FALSE END AS lastAssigned, MAX(last_fb.vPickupTime) AS lastAssignedTime, CASE WHEN fb.iFleet_BookingID IS NOT NULL THEN TRUE ELSE FALSE END AS alreadyAssigned, fb.iDriverID AS driverID, d.vName AS driverName, d.vMobileNum AS driverMobile, d.iType AS driverType, MIN(next_fb.vPickupTime) AS nextTripTime, fb.cDisposal AS disposal, latest_fb.cType AS latestBookingType FROM vehicle v JOIN vehicle_category vc ON vc.iVCatID = v.iCatID LEFT JOIN fleet_booking fb ON fb.iVehicleID = v.iVehicleID AND fb.cStatus = 'A' LEFT JOIN driver d ON d.iDriverID = fb.iDriverID LEFT JOIN fleet_booking last_fb ON last_fb.iVehicleID = v.iVehicleID AND last_fb.vPickupTime < NOW() AND last_fb.cStatus = 'A' LEFT JOIN fleet_booking next_fb ON next_fb.iVehicleID = v.iVehicleID AND next_fb.vPickupTime > NOW() AND next_fb.cStatus = 'A' LEFT JOIN ( SELECT fb1.iVehicleID, fb1.cType, fb1.vPickupTime FROM fleet_booking fb1 INNER JOIN ( SELECT iVehicleID, MAX(vPickupTime) AS maxPickup FROM fleet_booking WHERE cStatus = 'A' GROUP BY iVehicleID ) fb2 ON fb1.iVehicleID = fb2.iVehicleID AND fb1.vPickupTime = fb2.maxPickup ) latest_fb ON latest_fb.iVehicleID = v.iVehicleID WHERE 1 and fb.iVehicleID <> 0 $cond GROUP BY fb.iVehicleID ORDER BY v.vRnum";
        $bookingRes = sql_query($bookingSql);		

        $rowData = [];
        while ($row = sql_fetch_assoc($bookingRes)) {



            $rowData[] = [
                'id' => intval($row['iVehicleID']),
                'regNo' => $row['vRnum'] ?? "",
                'vehicleType' => $row['iType'] ?? 0,
                'vehiStatus' => $row['latestBookingType'] ?? 0,
                'categoryId' => $row['iVCatID'] ?? 0,
                'categoryName' => $row['vCatName'],
                'capacity' => $row['iCapacity'],
                'lastAssigned' => (!empty($row['lastAssignedTime']))?true:false,
                'lastAssignedTime' => $row['lastAssignedTime'] ?? '',
                'alreadyAssigned' => (!empty($row['alreadyAssigned']))?true:false,
                'driverID' => $row['driverID'] ?? 0,
                'driverName' => db_output2($row['driverName'] ?? ''),
                'driverMobile' => db_output2($row['driverMobile'] ?? ''),
                'driverType' => db_output2($row['driverType'] ?? ''),
                'nextTripTime' => $row['nextTripTime'] ?? '',
                'disposal' => ($row['disposal'] == 'Y')?true:false,
            ];
        }*/
        echo json_encode([
            "data" => [
                "requestsArr" => $rowData
            ],
            "statusCode" => 200
        ]);
        break;
    // ===================== CASE: VEHICLE_COMPOENT END =====================		

    // ===================== CASE: VEHICLE_DETAILS ======================	

    case 'VEHICLE_DETAILS':

        $vehiId = $_REQUEST['vehiId'] ?? '0';

        $q0 = "SELECT v.iVehicleID, v.vRnum, v.cStatus, vc.vName AS vehicleType, v.iCatID, v.iType FROM vehicle v JOIN vehicle_category vc ON vc.iVCatID = v.iCatID WHERE v.iVehicleID = $vehiId";
        $r0 = sql_query($q0, "supervisor_dashboard.392");

        if (sql_num_rows($r0)) {

            list($iVehicleID, $vRnum, $cStatus, $vehicleType, $iCatID, $iType) = sql_fetch_row($r0);

            $driversArr = array();

            $q1 = "SELECT d.iDriverID, d.vName, d.vMobileNum, COUNT(fb.iFleet_BookingID) AS totalTrips FROM driver d LEFT JOIN fleet_booking fb ON fb.iDriverID = d.iDriverID AND fb.iVehicleID = $vehiId GROUP BY d.iDriverID";
            $r1 = sql_query($q1, "supervisor_dashboard.399");

            if (sql_num_rows($r1)) {

                while ($row1 = sql_fetch_assoc($r1)) {

                    $driversArr[] = [
                        'id' => $row1['iDriverID'],
                        'name' => $row1['vName'],
                        'mob' => $row1['vMobileNum'],
                        'trips' => $row1['totalTrips']
                    ];
                }
            }
            $tripsArr = array();
            //$q2 = "SELECT fb.iFleet_BookingID, fb.vPickupLocation, fb.vDropLocation, fb.vName, vc.vName AS vehicleType, vc.iCapacity, fb.vPickupTime, fb.vDropTime, cBookingFor FROM fleet_booking fb JOIN vehicle_category vc ON vc.iVCatID = fb.iVehicleCatID WHERE fb.iVehicleID = $vehiId ORDER BY fb.vPickupTime DESC";
            //$r2 = sql_query($q2, "supervisor_dashboard");
            $currentPickupTime = date('Y-m-d H:i:s');
            $qPrev = "
				SELECT fb.iFleet_BookingID, fb.vPickupLocation, fb.vDropLocation, fb.vName,
					   vc.vName AS vehicleType, vc.iCapacity, fb.vPickupTime, fb.vDropTime, fb.cBookingFor
				FROM fleet_booking fb
				JOIN vehicle_category vc ON vc.iVCatID = fb.iVehicleCatID
				WHERE fb.iVehicleID = $vehiId
				  AND fb.vPickupTime < '$currentPickupTime'
				ORDER BY fb.vPickupTime DESC
				LIMIT 1
				";

            $qNext = "
				SELECT fb.iFleet_BookingID, fb.vPickupLocation, fb.vDropLocation, fb.vName,
					   vc.vName AS vehicleType, vc.iCapacity, fb.vPickupTime, fb.vDropTime, fb.cBookingFor
				FROM fleet_booking fb
				JOIN vehicle_category vc ON vc.iVCatID = fb.iVehicleCatID
				WHERE fb.iVehicleID = $vehiId
				  AND fb.vPickupTime > '$currentPickupTime' and fb.cType <> 'C'
				ORDER BY fb.vPickupTime ASC
				LIMIT 1
				";
            foreach ([$qPrev, $qNext] as $query) {
                $res = sql_query($query, "supervisor_dashboard");
                if ($row = sql_fetch_assoc($res)) {

                    $dtpick = strtotime($row['vPickupTime']);

                    if (date('Y-m-d', $dtpick) === date('Y-m-d')) {
                        $dateTimePick = date('g:i A', $dtpick);
                    } else {
                        $dateTimePick = date('d M g:i A', $dtpick);
                    }

                    $dateTimeDrop = "N/A";

                    if(!empty($row['vDropTime'])){

                    $dtdrop = strtotime($row['vDropTime']);

                    if (date('Y-m-d', $dtdrop) === date('Y-m-d')) {
                        $dateTimeDrop = date('g:i A', $dtdrop);
                    } else {
                        $dateTimeDrop = date('d M g:i A', $dtdrop);
                    }

                    }

                    $tripsArr[] = [
                        'title' => '',
                        'from' => $row['vPickupLocation'],
                        'to' => $row['vDropLocation'],
                        'name' => $row['vName'],
                        'type' => $row['cBookingFor'],
                        'capacity' => $row['iCapacity'],
                        'fromTime' => $dateTimePick,
                        'toTime' => $dateTimeDrop,
                    ];
                }
            }

            //HISTORY
            global $FL_LOG_STATUS_ARR;
            $LOG_DATA_ARR = array();

            $DRIVER_ARR = array();
            $qd = "select iDriverID, vName from driver order by vName";
            $rd = sql_query($qd, "supervisor_dashboard.38");
            if (sql_num_rows($rd)) {
                while ($drow = sql_fetch_assoc($rd)) {
                    $DRIVER_ARR[$drow['iDriverID']] = array("ID" => $drow['iDriverID'], "NAME" => $drow['vName']);
                }
            }
            $PAUSE_REASON_ARR = GetXArrFromYID("SELECT iReasonID,vName FROM pause_reasons", "3");
            $q = "select bl.iFleet_BookingID, bl.cRefType, bl.vRefName, bl.dtAdded, fb.vName, fb.iDriverID, bl.iPauseTypeID, bl.vNotes from fleet_booking_log bl join fleet_booking fb on bl.iFleet_BookingID = fb.iFleet_BookingID where fb.iVehicleID = $vehiId order by bl.dtAdded DESC";
            $r = sql_query($q, "");

            if (sql_num_rows($r)) {
                while ($row = sql_fetch_assoc($r)) {
                    $driverName = db_output2($DRIVER_ARR[$row['iDriverID']]['NAME']);
                    //$LOG_DATA_ARR[] = array("ID"=>$row['iFleet_BookingID'], "DATETIME"=>$row['dtAdded'], "STATUS"=>$FL_LOG_STATUS_ARR[$row['cRefType']], "NOTES"=>$row['vRefName'], "GUEST"=>$row['vName'], "DRIVER"=>$DRIVER_ARR[$row['iDriverID']]['NAME'] ?? '');
                    $stageStatus = $row['cRefType'];
                    $passengerName = db_output2($row['vName']);
                    $description = '';
                    switch ($stageStatus) {
                        case 'S':
                            $description = "$driverName started the trip to pick up $passengerName";
                            break;
                        case 'G':
                            $description = "$driverName picked up $passengerName";
                            break;
                        case 'P':
                            // For pause entries, get pause reason and notes from fleet_booking_log
                            $pauseReason = '';
                            $pauseNotes = '';

                            // Get pause reason from current log entry if iPauseTypeID exists
                            if (!empty($row['iPauseTypeID']) && intval($row['iPauseTypeID']) > 0) {
                                // $pauseReasonSql = "SELECT vName FROM pause_reasons WHERE iReasonID = " . intval($logRow['iPauseTypeID']) . " AND cStatus = 'A' LIMIT 1";
                                // $pauseReasonRes = sql_query($pauseReasonSql);
                                // if (sql_num_rows($pauseReasonRes) > 0) {
                                //     $pauseReasonRow = sql_fetch_assoc($pauseReasonRes);
                                //     $pauseReason = db_output2($pauseReasonRow['vName'] ?? '');
                                // }
                                $pauseReasonRes = isset($PAUSE_REASON_ARR[$row['iPauseTypeID']]) ? db_output2($PAUSE_REASON_ARR[$row['iPauseTypeID']]) : '';
                            }

                            // Get pause notes from current log entry
                            if (!empty($row['vNotes'])) {
                                $pauseNotes = db_output2($row['vNotes']);
                            }

                            if (!empty($pauseReasonRes)) {
                                $description = "$driverName paused the trip due to $pauseReasonRes";
                                if (!empty($pauseNotes)) {
                                    $description .= " ($pauseNotes)";
                                }
                            } else {
                                $description = "$driverName paused the trip";
                                if (!empty($pauseNotes)) {
                                    $description .= " ($pauseNotes)";
                                }
                            }
                            break;
                        case 'R':
                            $description = "$driverName resumed back the trip";
                            break;
                        case 'C':
                            $description = "$driverName dropped $passengerName";
                            break;
                        default:
                            $description = "$driverName performed $stageName";
                            break;
                    }

                    $dt = strtotime($row['dtAdded']);

                    if (date('Y-m-d', $dt) === date('Y-m-d')) {
                        $dateTime = date('g:i A', $dt);
                    } else {
                        $dateTime = date('d M g:i A', $dt);
                    }


                    $LOG_DATA_ARR[] = array("code" => $row['cRefType'], "status" => $FL_LOG_STATUS_ARR[$row['cRefType']], "message" => $description, "dateTime" => $dateTime);
                }
            }

            /*$q1 = "select pl.iFleet_BookingID, pl.iPauseTypeID, pl.vNotes, pl.dtPauseTime, fb.vName, pl.iDriverID from trip_pause_log pl join fleet_booking fb on pl.iFleet_BookingID = fb.iFleet_BookingID where 1 and fb.iVehicleID = $vehiId order by pl.dtPauseTime DESC";
            $r1 = sql_query($q1, "");

            if(sql_num_rows($r1)){
                while($row1 = sql_fetch_assoc($r1)){
                    //$LOG_DATA_ARR[] = array("ID"=>$row1['iFleet_BookingID'], "DATETIME"=>$row1['dtPauseTime'], "STATUS"=>$PAUSE_TYPE_ARR[$row1['iPauseTypeID']], "NOTES"=>$row1['vNotes'], "GUEST"=>$row1['vName'], "DRIVER"=>$DRIVER_ARR[$row1['iDriverID']]['NAME'] ?? '');
                    //$LOG_DATA_ARR[] = array("code"=>$row1['iPauseTypeID'], "status"=>$PAUSE_TYPE_ARR[$row1['iPauseTypeID']], "message"=>$row1['vNotes'], "dateTime"=>date('d/m/Y H:i:s', strtotime($row1['dtPauseTime'])));
                }
            }*/

            usort($LOG_DATA_ARR, function ($a, $b) {
                return strtotime($b['DATETIME']) <=> strtotime($a['DATETIME']);
            });
            //HISTORY

            $data = array();

            $data['vehiName'] = $vehicleType;
            $data['regNo'] = $vRnum;
            $data['type'] = $iType;
            $data['status'] = $cStatus;
            $data['driversArr'] = $driversArr;
            $data['tripsArr'] = $tripsArr;
            $data['vehiHistoryArr'] = $LOG_DATA_ARR;
        }


        echo json_encode([
            "data" => $data,
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: VEHICLE_DETAILS END ======================	

    // ===================== CASE: ASSIGN_DRIVER =====================

    case 'ASSIGN_DRIVER':

        $driverId = $_REQUEST['driverId'] ?? '0';


        echo json_encode([
            "data" => [
                "rowData" => $LOG_DATA_ARR
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: ASSIGN_DRIVER END ======================	

    // ===================== CASE: ACTIVITY_TIMELINE ======================		
    case 'ACTIVITY_TIMELINE':

        global $FL_LOG_STATUS_ARR;
        $currentDate = date('Y-m-d');
        $user_level = GetXFromYID("select iLevel from users where iUserID = $user_id");
        $timeline_limit = (int) GetXFromYID("select vValue from sys_settings where vCode = 'ACTIVITY_TIMELINE_DATA_LIMIT'");

        if ($timeline_limit <= 0) {
            $timeline_limit = 50; // sensible default
        }

        $LEVEL_MODULE_ASSOC_ARR = array();
        $ql = "select * from module_level_assoc where iLevelD = $user_level";
        $rl = sql_query($ql, "supervisor_dashboard.768");
        if (sql_num_rows($rl)) {
            while ($rowl = sql_fetch_assoc($rl)) {
                $LEVEL_MODULE_ASSOC_ARR[] = $rowl['iModuleID'];
            }
        }
        //$from = $currentDate." ".$_REQUEST['fromTime'].":00" ?? date('Y-m-d H:00:s');
        //$to = $currentDate." ".$_REQUEST['toTime'].":59" ?? date('Y-m-d H:00:s', strtotime('+4 hours'));

        $from = '';
        $to = '';
        $bookingFor = [];
        $cond = "";

        if (!empty($from) || !empty($to)) {
            if (!empty($from)) {
                $cond .= " and bl.dtAdded >= '$from'";
            }

            if (!empty($to)) {
                $cond .= " and bl.dtAdded <= '$to'";
            }
        }

        if (in_array(34, $LEVEL_MODULE_ASSOC_ARR)) {
            $cond .= " and fb.iAdded_UserID = $user_id";
        }

        if (in_array(37, $LEVEL_MODULE_ASSOC_ARR)) {
            $bookingFor[] = "'S'";
        }



        if (in_array(38, $LEVEL_MODULE_ASSOC_ARR)) {
            $bookingFor[] = "'G'";
        }

        if (!empty($bookingFor)) {
            $cond .= " AND fb.cBookingFor IN (" . implode(',', $bookingFor) . ")";
        }



        $LOG_DATA_ARR = array();

        $DRIVER_ARR = array();
        $qd = "select iDriverID, vName from driver order by vName";
        $rd = sql_query($qd, "supervisor_dashboard.38");
        if (sql_num_rows($rd)) {
            while ($drow = sql_fetch_assoc($rd)) {
                $DRIVER_ARR[$drow['iDriverID']] = array("ID" => $drow['iDriverID'], "NAME" => $drow['vName']);
            }
        }
        $PAUSE_TYPE_ARR = GetXArrFromYID("select iReasonID, vName from pause_reasons where cStatus = 'A'", 3);
        $q = "select bl.iFleet_BookingID, bl.cRefType, bl.vRefName, bl.dtAdded, fb.vName, fb.iDriverID, bl.iPauseTypeID, bl.vNotes from fleet_booking_log bl join fleet_booking fb on bl.iFleet_BookingID = fb.iFleet_BookingID where 1 $cond order by bl.dtAdded DESC limit $timeline_limit";
        $r = sql_query($q, "");

        if (sql_num_rows($r)) {
            while ($row = sql_fetch_assoc($r)) {
                //$LOG_DATA_ARR[] = array("ID"=>$row['iFleet_BookingID'], "DATETIME"=>$row['dtAdded'], "STATUS"=>$FL_LOG_STATUS_ARR[$row['cRefType']], "NOTES"=>$row['vRefName'], "GUEST"=>$row['vName'], "DRIVER"=>$DRIVER_ARR[$row['iDriverID']]['NAME'] ?? '');

                $driverName = db_output2($DRIVER_ARR[$row['iDriverID']]['NAME']);
                //$LOG_DATA_ARR[] = array("ID"=>$row['iFleet_BookingID'], "DATETIME"=>$row['dtAdded'], "STATUS"=>$FL_LOG_STATUS_ARR[$row['cRefType']], "NOTES"=>$row['vRefName'], "GUEST"=>$row['vName'], "DRIVER"=>$DRIVER_ARR[$row['iDriverID']]['NAME'] ?? '');
                $stageStatus = $row['cRefType'];
                $bookingId = $row['iFleet_BookingID'];
                $passengerName = db_output2($row['vName']);
                $description = '';
                switch ($stageStatus) {
                    case 'S':
                        $description = "$driverName started the trip to pick up $passengerName";
                        break;
                    case 'G':
                        $description = "$driverName picked up $passengerName";
                        break;
                    case 'P':
                        // For pause entries, get pause reason and notes from fleet_booking_log
                        $pauseReason = '';
                        $pauseNotes = '';

                        // Get pause reason from current log entry if iPauseTypeID exists
                        if (!empty($row['iPauseTypeID']) && intval($row['iPauseTypeID']) > 0) {
                            // $pauseReasonSql = "SELECT vName FROM pause_reasons WHERE iReasonID = " . intval($logRow['iPauseTypeID']) . " AND cStatus = 'A' LIMIT 1";
                            // $pauseReasonRes = sql_query($pauseReasonSql);
                            // if (sql_num_rows($pauseReasonRes) > 0) {
                            //     $pauseReasonRow = sql_fetch_assoc($pauseReasonRes);
                            //     $pauseReason = db_output2($pauseReasonRow['vName'] ?? '');
                            // }
                            $pauseReasonRes = isset($PAUSE_REASON_ARR[$row['iPauseTypeID']]) ? db_output2($PAUSE_REASON_ARR[$row['iPauseTypeID']]) : '';
                        }

                        // Get pause notes from current log entry
                        if (!empty($row['vNotes'])) {
                            $pauseNotes = db_output2($row['vNotes']);
                        }

                        if (!empty($pauseReasonRes)) {
                            $description = "$driverName paused the trip due to $pauseReasonRes";
                            if (!empty($pauseNotes)) {
                                $description .= " ($pauseNotes)";
                            }
                        } else {
                            $description = "$driverName paused the trip";
                            if (!empty($pauseNotes)) {
                                $description .= " ($pauseNotes)";
                            }
                        }
                        break;
                    case 'R':
                        $description = "$driverName resumed back the trip";
                        break;
                    case 'C':
                        $description = "$driverName dropped $passengerName";
                        break;
                    default:
                        $description = "$driverName performed $stageName";
                        break;
                }

                $dt = strtotime($row['dtAdded']);

                if (date('Y-m-d', $dt) === date('Y-m-d')) {
                    $dateTime = date('g:i A', $dt);
                } else {
                    $dateTime = date('d M g:i A', $dt);
                }

                $LOG_DATA_ARR[] = array("code" => $row['cRefType'], "status" => $FL_LOG_STATUS_ARR[$row['cRefType']], "message" => $description, "dateTime" => $dateTime, "bookingId" => (int)$bookingId);
            }
        }

        /*$q1 = "select pl.iFleet_BookingID, pl.iPauseTypeID, pl.vNotes, pl.dtPauseTime, fb.vName, pl.iDriverID from trip_pause_log pl join fleet_booking fb on pl.iFleet_BookingID = fb.iFleet_BookingID where 1 order by pl.dtPauseTime DESC";
        $r1 = sql_query($q1, "");

        if(sql_num_rows($r1)){
            while($row1 = sql_fetch_assoc($r1)){
                //$LOG_DATA_ARR[] = array("ID"=>$row1['iFleet_BookingID'], "DATETIME"=>$row1['dtPauseTime'], "STATUS"=>$PAUSE_TYPE_ARR[$row1['iPauseTypeID']], "NOTES"=>$row1['vNotes'], "GUEST"=>$row1['vName'], "DRIVER"=>$DRIVER_ARR[$row1['iDriverID']]['NAME'] ?? '');
                //$LOG_DATA_ARR[] = array("code"=>$row1['iPauseTypeID'], "status"=>$PAUSE_TYPE_ARR[$row1['iPauseTypeID']], "message"=>$row1['vNotes'], "dateTime"=>date('d/m/Y H:i:s', strtotime($row1['dtPauseTime'])));
            }
        }*/

        /*         usort($LOG_DATA_ARR, function ($a, $b) {
            return strtotime($b['DATETIME']) <=> strtotime($a['DATETIME']);
        }); */

        echo json_encode([
            "data" => [
                "issues" => 0,
                "activityArr" => $LOG_DATA_ARR
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: ACTIVITY_TIMELINE END =====================			

    // ===================== CASE: ADD =====================
    case 'ADD_BOOKING':

        // sanitize and collect inputs
        $cBookingFor = db_input($_REQUEST['bookedFor'] ?? '');
        $iBookedBy = db_input($_REQUEST['bookedBy'] ?? '');
        $iFleet_TrvPurID = intval($_REQUEST['travelPurpose'] ?? 0);
        $iFleet_TrvTypeID = intval($_REQUEST['travelType'] ?? 0);
        $iFleet_BKCatID = intval($_REQUEST['bookingCat'] ?? 0);
        $iPropertyID = intval($_REQUEST['property'] ?? 0);

        $vName = db_input($_REQUEST['name'] ?? '');
        $vMobileNo = db_input($_REQUEST['mob'] ?? '');
        $iPax = intval($_REQUEST['pax'] ?? 0);
        $iBaggage = intval($_REQUEST['baggage'] ?? 0);

        // Handle new location format with lat/lng
        $pickUpLocData = $_REQUEST['pickUpLoc'] ?? [];
        $dropLocData = $_REQUEST['dropLoc'] ?? [];

        // Extract location name and coordinates
        $vPickUpLocation = db_input($pickUpLocData['loc'] ?? '');
        $vDropLocation = db_input($dropLocData['loc'] ?? '');

        // Extract and format lat/lng coordinates
        $vLatLong_From = '';
        if (!empty($pickUpLocData['lat']) && !empty($pickUpLocData['lng'])) {
            $vLatLong_From = $pickUpLocData['lat'] . ',' . $pickUpLocData['lng'];
        }

        $vLatLong_To = '';
        if (!empty($dropLocData['lat']) && !empty($dropLocData['lng'])) {
            $vLatLong_To = $dropLocData['lat'] . ',' . $dropLocData['lng'];
        }

        $vPickUpTime = db_input($_REQUEST['pickUpDateTime'] ?? null);
        $vPickUpTime = (isset($_REQUEST['pickUpDateTime']) && !empty($_REQUEST['pickUpDateTime'])) ? $_REQUEST['pickUpDateTime'] : NULL;

        $iVehicleCatID = intval($_REQUEST['vehiCat'] ?? 0);
        $vInstructions = db_input($_REQUEST['intruc'] ?? '');

        // $tripType = intval($_REQUEST['tripType'] ?? 0);
        // $cDisposal = ($tripType == 3) ? 'Y' : 'N';
        //    $cDisposal = ($_REQUEST['disposal'] === true || $_REQUEST['disposal'] === 'true') ? 'Y' : 'N';
        $cDisposal = isset($_REQUEST['disposal']) ? $_REQUEST['disposal'] : 'N';
        $vReturnTime = !empty($_REQUEST['returnTime']) ? db_input($_REQUEST['returnTime']) : null;

        $iGuestID = intval($_REQUEST['guestID'] ?? 0);
        $iFStaffID = intval($_REQUEST['staffID'] ?? 0);

        // Validate required inputs
        if (empty($vName) || empty($vMobileNo) || empty($vPickUpTime)) {
            echo json_encode([
                "error" => [
                    "message" => "Required fields missing"
                ],
                "statusCode" => 401
            ]);
            exit;
        }

        // Handle guest creation if both guestID and staffID are 0
        if ($iGuestID == 0 && $iFStaffID == 0) {
            $guestCheckSql = "SELECT iGuestID FROM guest WHERE vName = '" . db_input($vName) . "' AND vMobileNo = '" . db_input($vMobileNo) . "' AND cStatus = 'A'";
            $guestCheckRes = sql_query($guestCheckSql);

            if (sql_num_rows($guestCheckRes) > 0) {
                $guestRow = sql_fetch_assoc($guestCheckRes);
                $iGuestID = intval($guestRow['iGuestID']);
            } else {
                // generate guest PK and insert
                $guest_id = NextID('iGuestID', 'guest');
                $guestInsertSql = "INSERT INTO guest (iGuestID, vName, vMobileNo, dtCreated, cStatus)
                                   VALUES ($guest_id, '" . db_input($vName) . "', '" . db_input($vMobileNo) . "', NOW(), 'A')";
                $okGuest = sql_query($guestInsertSql);
                if (!$okGuest) {
                    echo json_encode([
                        "error" => [
                            "message" => "Failed to create guest record"
                        ],
                        "statusCode" => 500
                    ]);
                    exit;
                }
                $iGuestID = $guest_id;
            }
        }

        // Common columns (include PK as first column)
        $cols = "iFleet_BookingID,iBookedBy, cBookingFor, iFleet_TrvPurID, iFleet_TrvTypeID, iPropertyID,
                 iFleet_BKCatID, vInstructions, vName, vMobileNo, iGuestID, iFStaffID,
                 iPax, iBaggage, vPickUpLocation, vPickUpTime,
                 vDropLocation, vLatLong_From, vLatLong_To, iVehicleCatID, cDisposal, tReturnTime, dtAdded,iAdded_UserID,cStatus";

        // Create OUTBOUND booking
        $iFleet_BookingID1 = NextID('iFleet_BookingID', 'fleet_booking');
        $dtAdded = NOW;
        // handle possible NULL for vReturnTime
        $vReturnTimeVal = (!empty($vReturnTime)) ? "'$vReturnTime'" : "NULL";

        $sql1 = "
        INSERT INTO fleet_booking ($cols)
        VALUES (
            $iFleet_BookingID1,$iBookedBy, '" . db_input($cBookingFor) . "', $iFleet_TrvPurID, $iFleet_TrvTypeID, $iPropertyID,
            $iFleet_BKCatID, '" . db_input($vInstructions) . "', '" . db_input($vName) . "', '" . db_input($vMobileNo) . "', $iGuestID, $iFStaffID,
            $iPax, $iBaggage, '" . db_input($vPickUpLocation) . "', '" . db_input($vPickUpTime) . "',
            '" . db_input($vDropLocation) . "', '" . db_input($vLatLong_From) . "', '" . db_input($vLatLong_To) . "', $iVehicleCatID, '" . db_input($cDisposal) . "', $vReturnTimeVal, '" . db_input($dtAdded) . "',$user_id,'A'
        )";

        $ok1 = sql_query($sql1);
        if (!$ok1) {
            echo json_encode([
                "error" => [
                    "message" => "Failed to add booking"
                ],
                "statusCode" => 500
            ]);
            exit;
        }

        $responseIds = [$iFleet_BookingID1];


        echo json_encode([
            "data" => [
                "message" => "Booking added successfully",
                "bookingIds" => $responseIds
            ],
            "statusCode" => 200
        ]);

        break;
    // ===================== CASE: EDIT_BOOKING (no pairing) =====================
    case 'EDIT_BOOKING':
        $iFleet_BookingID = intval($_REQUEST['bookingId'] ?? 0);

        if ($iFleet_BookingID <= 0) {
            echo json_encode([
                "error" => ["message" => "bookingId missing or invalid"],
                "statusCode" => 400
            ]);
            exit;
        }

        $bookingSql = "SELECT * FROM fleet_booking WHERE iFleet_BookingID = $iFleet_BookingID AND cStatus = 'A' LIMIT 1";
        $bookingRes = sql_query($bookingSql);
        $STAFF_DEPT_ARR = GetXArrFromYID("SELECT iDepartmentID, iFStaffID  from fleet_staff where cStatus='A'", "3");

        if (sql_num_rows($bookingRes) == 0) {
            echo json_encode([
                "error" => ["message" => "Booking not found"],
                "statusCode" => 404
            ]);
            exit;
        }

        $booking = sql_fetch_assoc($bookingRes);

        // Parse lat/lng coordinates from database
        $pickUpLatLng = explode(',', $booking['vLatLong_From'] ?? '');
        $dropLatLng = explode(',', $booking['vLatLong_To'] ?? '');

        $response = [
            "bookingId" => intval($booking['iFleet_BookingID']),
            "bookedBy" => intval($booking['iBookedBy']),
            "bookedFor" => $booking['cBookingFor'],
            "travelPurpose" => intval($booking['iFleet_TrvPurID']),
            "travelType" => intval($booking['iFleet_TrvTypeID']),
            "bookingCat" => intval($booking['iFleet_BKCatID']),
            "property" => intval($booking['iPropertyID']),
            "name" => $booking['vName'],
            "mob" => $booking['vMobileNo'],
            "pax" => intval($booking['iPax']),
            "baggage" => intval($booking['iBaggage']),
            "pickUpLoc" => [
                'lat' => isset($pickUpLatLng[0]) && !empty(trim($pickUpLatLng[0])) ? floatval(trim($pickUpLatLng[0])) : null,
                'lng' => isset($pickUpLatLng[1]) && !empty(trim($pickUpLatLng[1])) ? floatval(trim($pickUpLatLng[1])) : null,
                'loc' => db_output2($booking['vPickUpLocation'])
            ],
            "dropLoc" => [
                'lat' => isset($dropLatLng[0]) && !empty(trim($dropLatLng[0])) ? floatval(trim($dropLatLng[0])) : null,
                'lng' => isset($dropLatLng[1]) && !empty(trim($dropLatLng[1])) ? floatval(trim($dropLatLng[1])) : null,
                'loc' => db_output2($booking['vDropLocation'])
            ],
            "pickUpDateTime" => $booking['vPickUpTime'],
            "returnTime" => ($booking['tReturnTime'] ?? null),
            "vehiCat" => intval($booking['iVehicleCatID']),
            "intruc" => $booking['vInstructions'],
            "guestID" => intval($booking['iGuestID']),
            "staffID" => intval($booking['iFStaffID']),
            "staff_dept" => isset($STAFF_DEPT_ARR[intval($booking['iFStaffID'])]) ? $STAFF_DEPT_ARR[intval($booking['iFStaffID'])] : 0,
            //"disposal"       => ($booking['cDisposal'] ?? 'N') === 'Y' ? true : false,
            "disposal" => $booking['cDisposal'] ?? 'N',
            "dtAdded" => $booking['dtAdded'],
            "addedUserId" => intval($booking['iAdded_UserID']),
        ];

        // Option arrays for form rendering (minimal set; extend if needed)
        // $BOOKING_CAT = GetXArrFromYID("SELECT iFleet_BkCatID, vName from fleet_bookingcategory where cStatus='A' ORDER BY vName", "3");
        // $TRAVEL_PURPOSE = GetXArrFromYID("SELECT iFleet_TrvPurID, vName from fleet_travelpurpose where cStatus='A' ORDER BY iRank", "3");
        // $VEH_CAT = GetXArrFromYID("SELECT iVCatID, vName from vehicle_category where cStatus='A' AND cType IN ('B','F') ORDER BY iRank", "3");
        // $PROPERTY_ARR = GetXArrFromYID("SELECT iPropertyID, vName from property where cStatus='A' ORDER BY vName", "3");
        // $STAFF_ARR = sql_query("SELECT iFStaffID, vName, iDepartmentID from fleet_staff where cStatus='A' ORDER BY vName");
        // $GUEST_ARR = sql_query("SELECT iGuestID, vName, vMobileNo from guest where cStatus='A' ORDER BY vName");

        // $bookingCatOpt = [['id' => 0, 'name' => 'Choose']];
        // foreach ($BOOKING_CAT as $id => $name) {
        //     $bookingCatOpt[] = ['id' => intval($id), 'name' => $name];
        // }

        // $travelPurposeOpt = [];
        // foreach ($TRAVEL_PURPOSE as $id => $name) {
        //     $travelPurposeOpt[] = ['id' => intval($id), 'name' => $name];
        // }

        // $propertyOpt = [['id' => 0, 'name' => 'Choose']];
        // foreach ($PROPERTY_ARR as $id => $name) {
        //     $propertyOpt[] = ['id' => intval($id), 'name' => $name];
        // }

        // $vehiCatOpt = [['id' => 0, 'name' => 'Choose']];
        // foreach ($VEH_CAT as $id => $name) {
        //     $vehiCatOpt[] = ['id' => intval($id), 'name' => $name];
        // }

        // $staffOpt = [];
        // while ($row = sql_fetch_assoc($STAFF_ARR)) {
        //     $staffOpt[] = [
        //         'id' => intval($row['iFStaffID']),
        //         'name' => $row['vName'],
        //         'departmentId' => intval($row['iDepartmentID'])
        //     ];
        // }

        // $guestOpts = [];
        // while ($row = sql_fetch_assoc($GUEST_ARR)) {
        //     $guestOpts[] = [
        //         'id' => intval($row['iGuestID']),
        //         'name' => $row['vName'],
        //         'mobile' => $row['vMobileNo']
        //     ];
        // }

        echo json_encode([
            "data" => [
                "booking" => $response
                // "bookingCatOpt" => $bookingCatOpt,
                // "travelPurposeOpt" => $travelPurposeOpt,
                // "propertyOpt" => $propertyOpt,
                // "vehiCatOpt" => $vehiCatOpt,
                // "staffOpt" => $staffOpt,
                // "guestOpts" => $guestOpts
            ],
            "statusCode" => 200
        ]);
        break;


    // ===================== CASE: UPDATE_BOOKING  =====================
    case 'UPDATE_BOOKING':
        $iFleet_BookingID = intval($_REQUEST['bookingId'] ?? 0);
        if ($iFleet_BookingID <= 0) {
            echo json_encode([
                "error" => ["message" => "bookingId missing or invalid"],
                "statusCode" => 400
            ]);
            exit;
        }

        // Collect & sanitize inputs (same names as ADD_BOOKING)
        $cBookingFor = db_input($_REQUEST['bookedFor'] ?? '');
        $iBookedBy = db_input($_REQUEST['bookedBy'] ?? '');
        $iFleet_TrvPurID = intval($_REQUEST['travelPurpose'] ?? 0);
        $iFleet_TrvTypeID = intval($_REQUEST['travelType'] ?? 0);
        $iFleet_BKCatID = intval($_REQUEST['bookingCat'] ?? 0);
        $iPropertyID = intval($_REQUEST['property'] ?? 0);

        $vName = db_input($_REQUEST['name'] ?? '');
        $vMobileNo = db_input($_REQUEST['mob'] ?? '');
        $iPax = intval($_REQUEST['pax'] ?? 0);
        $iBaggage = intval($_REQUEST['baggage'] ?? 0);

        // Handle new location format with lat/lng
        $pickUpLocData = $_REQUEST['pickUpLoc'] ?? [];
        $dropLocData = $_REQUEST['dropLoc'] ?? [];

        // Extract location name and coordinates
        $vPickUpLocation = db_input($pickUpLocData['loc'] ?? '');
        $vDropLocation = db_input($dropLocData['loc'] ?? '');

        // Extract and format lat/lng coordinates
        $vLatLong_From = '';
        if (!empty($pickUpLocData['lat']) && !empty($pickUpLocData['lng'])) {
            $vLatLong_From = $pickUpLocData['lat'] . ',' . $pickUpLocData['lng'];
        }

        $vLatLong_To = '';
        if (!empty($dropLocData['lat']) && !empty($dropLocData['lng'])) {
            $vLatLong_To = $dropLocData['lat'] . ',' . $dropLocData['lng'];
        }

        $vPickUpTime = db_input($_REQUEST['pickUpDateTime'] ?? null);

        $iVehicleCatID = intval($_REQUEST['vehiCat'] ?? 0);
        $vInstructions = db_input($_REQUEST['intruc'] ?? '');

        // $tripType = intval($_REQUEST['tripType'] ?? 0);
        // $cDisposal = ($tripType == 3) ? 'Y' : 'N';
        // $cDisposal = ($_REQUEST['disposal'] === true || $_REQUEST['disposal'] === 'true') ? 'Y' : 'N';
        $cDisposal = isset($_REQUEST['disposal']) ? $_REQUEST['disposal'] : 'N';
        $vReturnTime = !empty($_REQUEST['returnTime']) ? db_input($_REQUEST['returnTime']) : null;

        $iGuestID = intval($_REQUEST['guestID'] ?? 0);
        $iFStaffID = intval($_REQUEST['staffID'] ?? 0);

        // Basic required fields check 
        if (empty($vName) || empty($vMobileNo) || empty($vPickUpTime)) {
            echo json_encode([
                "error" => ["message" => "Required fields missing"],
                "statusCode" => 401
            ]);
            exit;
        }

        // Confirm booking exists and is active
        $checkSql = "SELECT * FROM fleet_booking WHERE iFleet_BookingID = $iFleet_BookingID AND cStatus = 'A' LIMIT 1";
        $checkRes = sql_query($checkSql);
        if (sql_num_rows($checkRes) == 0) {
            echo json_encode([
                "error" => ["message" => "Booking not found"],
                "statusCode" => 404
            ]);
            exit;
        }

        $vReturnTimeVal = (!empty($vReturnTime)) ? "'" . $vReturnTime . "'" : "NULL";
        $dtNow = date('Y-m-d H:i:s');

        $updateSql = "
            UPDATE fleet_booking SET
                iBookedBy = " . intval($iBookedBy) . ",
                cBookingFor = '" . db_input($cBookingFor) . "',
                iFleet_TrvPurID = " . intval($iFleet_TrvPurID) . ",
                iFleet_TrvTypeID = " . intval($iFleet_TrvTypeID) . ",
                iPropertyID = " . intval($iPropertyID) . ",
                iFleet_BKCatID = " . intval($iFleet_BKCatID) . ",
                vInstructions = '" . db_input($vInstructions) . "',
                vName = '" . db_input($vName) . "',
                vMobileNo = '" . db_input($vMobileNo) . "',
                iGuestID = " . intval($iGuestID) . ",
                iFStaffID = " . intval($iFStaffID) . ",
                iPax = " . intval($iPax) . ",
                iBaggage = " . intval($iBaggage) . ",
                vPickUpLocation = '" . db_input($vPickUpLocation) . "',
                vPickUpTime = '" . db_input($vPickUpTime) . "',
                vDropLocation = '" . db_input($vDropLocation) . "',
                vLatLong_From = '" . db_input($vLatLong_From) . "',
                vLatLong_To = '" . db_input($vLatLong_To) . "',
                iVehicleCatID = " . intval($iVehicleCatID) . ",
                cDisposal = '" . db_input($cDisposal) . "',
                tReturnTime = " . $vReturnTimeVal . ",
                dtUpdated = '" . db_input($dtNow) . "',
                iUpdated_UserID = " . intval($user_id) . "
            WHERE iFleet_BookingID = " . intval($iFleet_BookingID) . " AND cStatus = 'A'
        ";

        $okUpdate = sql_query($updateSql);
        if (!$okUpdate) {
            echo json_encode([
                "error" => ["message" => "Failed to update booking"],
                "statusCode" => 500
            ]);
            exit;
        }

        echo json_encode([
            "data" => [
                "message" => "Booking updated successfully",
                "bookingId" => $iFleet_BookingID
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: VIEW =====================
    case 'VIEW':
        $iFleet_BookingID = intval($_REQUEST['bookingId'] ?? 0);

        if ($iFleet_BookingID <= 0) {
            echo json_encode([
                "error" => ["message" => "bookingId missing or invalid"],
                "statusCode" => 400
            ]);
            exit;
        }
        $tripStatusArr = [];
        foreach ($FLEET_TRIP_STATUS as $id => $name) {
            $tripStatusArr[] = ['id' => $id, 'name' => $name];
        }

        // Fetch detailed booking information with all related data including vehicle and driver assignment
        $viewSql = "
            SELECT 
                fb.iFleet_BookingID,
                fb.vName,
                fb.vMobileNo,
                fb.cBookingFor,
                fb.vPickUpLocation,
                fb.vDropLocation,
                fb.vPickUpTime,
                fb.vInstructions,
                fb.vRemarks,
                fb.iPax,
                fb.iBaggage,
                fb.iVehicleID,
                fb.iDriverID,
                fb.cType as currentStatus,
                fbc.vName as bookingCategoryName,
                p.vName as propertyName,
                d.vName as departmentName,
                s.vName as bookedByName,
                vc.vName as vehicleCategoryName,
                ftp.vName as travelPurposeName,
                ftt.vName as travelTypeName,
                v.vRnum as assignedVehicleRegNo,
                vcat.vName as assignedVehicleCategoryName,
                dr.vName as assignedDriverName,
                dr.vMobileNum as assignedDriverMobile
            FROM fleet_booking fb
            LEFT JOIN fleet_bookingcategory fbc ON fb.iFleet_BKCatID = fbc.iFleet_BkCatID
            LEFT JOIN property p ON fb.iPropertyID = p.iPropertyID
            LEFT JOIN fleet_staff fs ON fb.iFStaffID = fs.iFStaffID
            LEFT JOIN department d ON fs.iDepartmentID = d.iDepartmentID
            LEFT JOIN fleet_staff s ON fb.iBookedBy = s.iFStaffID
            LEFT JOIN vehicle_category vc ON fb.iVehicleCatID = vc.iVCatID
            LEFT JOIN fleet_travelpurpose ftp ON fb.iFleet_TrvPurID = ftp.iFleet_TrvPurID
            LEFT JOIN fleet_traveltype ftt ON fb.iFleet_TrvTypeID = ftt.iFleet_TrvTypeID
            LEFT JOIN vehicle v ON fb.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
            LEFT JOIN vehicle_category vcat ON v.iCatID = vcat.iVCatID AND vcat.cStatus = 'A'
            LEFT JOIN driver dr ON fb.iDriverID = dr.iDriverID AND dr.cStatus = 'A'
            WHERE fb.iFleet_BookingID = $iFleet_BookingID AND fb.cStatus = 'A'
            LIMIT 1
        ";

        $viewRes = sql_query($viewSql);

        if (sql_num_rows($viewRes) == 0) {
            echo json_encode([
                "error" => ["message" => "Booking not found"],
                "statusCode" => 404
            ]);
            exit;
        }

        $booking = sql_fetch_assoc($viewRes);

        // Use passenger details directly from fleet_booking table
        $passengerName = $booking['vName'];
        $passengerMobile = $booking['vMobileNo'];
        // $guestStaffType = ($booking['cBookingFor'] === 'S') ? 'Staff' : 'Guest';
        $guestStaffType = $booking['cBookingFor'];

        // Format date time for display
        $pickupDateTime = '';
        if (!empty($booking['vPickUpTime'])) {
            $pickupDateTime = date('d-m-Y H:i', strtotime($booking['vPickUpTime']));
        }

        // Fetch vehicle history for this booking
        $vehicleHistorySql = "
           SELECT vc.vName as vehicleCategory, v.vRnum as regNo, fb.vPickUpTime as travelDateTime FROM fleet_booking fb LEFT JOIN vehicle v ON fb.iVehicleID = v.iVehicleID LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID WHERE fb.iFleet_BookingID = $iFleet_BookingID AND fb.cStatus = 'A' ORDER BY fb.vPickUpTime DESC;
        ";
        $vehicleHistoryRes = sql_query($vehicleHistorySql);
        $vehicleHistory = [];

        while ($historyRow = sql_fetch_assoc($vehicleHistoryRes)) {
            $travelDateTime = '';
            if (!empty($historyRow['travelDateTime'])) {
                $travelDateTime = date('d-m-Y', strtotime($historyRow['travelDateTime']));
            }

            $vehicleHistory[] = [
                'vehicleCategory' => $historyRow['vehicleCategory'] ?? 'N/A',
                'regNo' => $historyRow['regNo'] ?? 'N/A',
                'date' => $travelDateTime
            ];
        }

        // Check if vehicle and driver are assigned
        $isVehicleAssigned = !empty($booking['iVehicleID']) && intval($booking['iVehicleID']) > 0;
        $isDriverAssigned = !empty($booking['iDriverID']) && intval($booking['iDriverID']) > 0;

        $requestDetails = [
            'bookingId' => intval($booking['iFleet_BookingID']),
            'passengerName' => $passengerName,
            'mobile' => $passengerMobile,
            'guestStaffType' => $guestStaffType,
            'bookingCategory' => $booking['bookingCategoryName'] ?? 'N/A',
            'propertyValue' => $booking['propertyName'] ?? 'N/A',
            'pickupFrom' => $booking['vPickUpLocation'] ?? '',
            'dropTo' => $booking['vDropLocation'] ?? '',
            'dateTime' => $pickupDateTime,
            'instructions' => db_output2($booking['vInstructions']) ?? '',
            'remarks' => $booking['vRemarks'] ?? '',
            'passengers' => intval($booking['iPax'] ?? 0),
            'baggage' => intval($booking['iBaggage'] ?? 0),
            'bookedBy' => $booking['bookedByName'] ?? 'N/A',
            'vehicleCategory' => $booking['vehicleCategoryName'] ?? 'N/A',
            'travelPurpose' => $booking['travelPurposeName'] ?? 'N/A',
            'travelType' => $booking['travelTypeName'] ?? 'N/A',
            'departmentName' => $booking['departmentName'] ?? '',
            'isVehicleAssigned' => $isVehicleAssigned,
            'isDriverAssigned' => $isDriverAssigned,
            'assignedVehicle' => $isVehicleAssigned ? [
                'id' => intval($booking['iVehicleID']),
                'regNo' => db_output2($booking['assignedVehicleRegNo'] ?? ''),
                'categoryName' => db_output2($booking['assignedVehicleCategoryName'] ?? '')
            ] : null,
            'assignedDriver' => $isDriverAssigned ? [
                'id' => intval($booking['iDriverID']),
                'name' => db_output2($booking['assignedDriverName'] ?? ''),
                'mobile' => db_output2($booking['assignedDriverMobile'] ?? '')
            ] : null,
            'tripStatus' => isset($booking['currentStatus']) ? $booking['currentStatus'] : 'N'
            // 'tripStatus' => isset($FLEET_TRIP_STATUS[$booking['currentStatus']]) ? $FLEET_TRIP_STATUS[$booking['currentStatus']] : 'Not Started'
            // "status" => $booking['currentStatus']
        ];
        echo json_encode([
            "data" => [
                "requestDetails" => $requestDetails,
                "vehicleHistory" => $vehicleHistory,
                "tripStatusOpts" => $tripStatusArr
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: SEARCH_VEHICLE =====================
    case 'SEARCH_VEHICLE':
        $keyword = db_input($_REQUEST['keyword'] ?? '');
        $categoryID = intval($_REQUEST['categoryID'] ?? 0);
        $typeID = intval($_REQUEST['typeID'] ?? 0);
        $iFleet_BookingID = intval($_REQUEST['bookingId'] ?? 0);
        // Get vehicle categories array
        $vehicleCategorySql = "SELECT iVCatID, vName, iCapacity FROM vehicle_category WHERE cStatus = 'A' ORDER BY vName";
        $vehicleCategoryRes = sql_query($vehicleCategorySql);

        $vehicleTypeOpt = [['id' => 0, 'name' => 'All']];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $name) {
            $vehicleTypeOpt[] = ['id' => intval($id), 'name' => $name];
        }
        $tripStatusOpts = [['id' => 0, 'name' => 'All']];
        foreach ($FLEET_TRIP_STATUS as $id => $name) {
            $tripStatusOpts[] = ['id' => intval($id), 'name' => $name];
        }
        $vehicleCategories = [];
        while ($categoryRow = sql_fetch_assoc($vehicleCategoryRes)) {
            $vehicleCategories[] = [
                'id' => intval($categoryRow['iVCatID']),
                'name' => db_output2($categoryRow['vName']),
                'capacity' => intval($categoryRow['iCapacity'])
            ];
        }

        $whereConditions = ["v.cStatus = 'A'"];

        // Add keyword search (search in vehicle registration number and category name)
        if (!empty($keyword)) {
            $whereConditions[] = "(UPPER(v.vRnum) LIKE UPPER('%" . db_input($keyword) . "%') OR UPPER(vc.vName) LIKE UPPER('%" . db_input($keyword) . "%'))";
        }

        if ($categoryID > 0) {
            $whereConditions[] = "v.iCatID = $categoryID";
        }

        if ($typeID > 0) {
            $whereConditions[] = "v.iType = $typeID";
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get vehicles array with category, registration number, and assignment status
        $vehicleSql = "SELECT v.iVehicleID, v.vRnum, v.iCatID, v.iType as vehicletype, 
                              vc.vName as categoryName, vc.iCapacity,
                              dva.iDriverID, dva.dtAssigned_From, dva.dtAssigned_To,
                              d.vName as driverName, d.vMobileNum as driverMobile
                       FROM vehicle v
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       LEFT JOIN driver_vehicle_assoc dva ON v.iVehicleID = dva.iVehicleID 
                                 AND dva.cStatus = 'A' AND dva.dtAssigned_To IS NULL
                       LEFT JOIN driver d ON dva.iDriverID = d.iDriverID AND d.cStatus = 'A'
                       WHERE $whereClause 
                       ORDER BY v.vRnum";
        $vehicleRes = sql_query($vehicleSql);

        $vehicles = [];
        while ($vehicleRow = sql_fetch_assoc($vehicleRes)) {
            $vehicleID = intval($vehicleRow['iVehicleID']);
            $isAssigned = !empty($vehicleRow['iDriverID']);

            // Check if this vehicle is already assigned to the current trip/booking
            $assignedVeh = false;
            $tripAssignmentSql = "SELECT iVehicleID FROM fleet_booking 
                                 WHERE iFleet_BookingID = $iFleet_BookingID 
                                 AND iVehicleID = $vehicleID 
                                 AND cStatus = 'A'";
            $tripAssignmentRes = sql_query($tripAssignmentSql);
            if (sql_num_rows($tripAssignmentRes) > 0) {
                $assignedVeh = true;
            }

            // Get the last time this vehicle was assigned (most recent booking)
            $lastAssignedSql = "SELECT vPickUpTime FROM fleet_booking 
                               WHERE iVehicleID = $vehicleID 
                               AND cStatus = 'A' 
                               AND vPickUpTime <= NOW() 
                               ORDER BY vPickUpTime DESC 
                               LIMIT 1";
            $lastAssignedRes = sql_query($lastAssignedSql);
            $lastAssignedTime = null;
            $lastAssigned = false;
            if (sql_num_rows($lastAssignedRes) > 0) {
                $lastAssignedRow = sql_fetch_assoc($lastAssignedRes);
                $lastAssignedTime = $lastAssignedRow['vPickUpTime'];
                $lastAssigned = true;
            }

            // Get next trip time for this vehicle
            $nextTripSql = "SELECT vPickUpTime
                           FROM fleet_booking 
                           WHERE iVehicleID = $vehicleID 
                           AND cStatus = 'A' 
                           AND vPickUpTime > NOW() 
                           ORDER BY vPickUpTime ASC 
                           LIMIT 1";
            $nextTripRes = sql_query($nextTripSql);

            $nextTripTime = null;
            $nextTripLocation = '';
            $nextTripDestination = '';

            if (sql_num_rows($nextTripRes) > 0) {
                $nextTripRow = sql_fetch_assoc($nextTripRes);
                $nextTripTime = $nextTripRow['vPickUpTime'];
                // $nextTripLocation = db_output2($nextTripRow['vPickUpLocation'] ?? '');
                // $nextTripDestination = db_output2($nextTripRow['vDropLocation'] ?? '');
            }

            $vehicles[] = [
                'id' => $vehicleID,
                'regNo' => db_output2($vehicleRow['vRnum']),
                'vehicletype' => intval($vehicleRow['vehicletype']),
                'categoryId' => intval($vehicleRow['iCatID']),
                'categoryName' => db_output2($vehicleRow['categoryName'] ?? ''),
                'capacity' => intval($vehicleRow['iCapacity'] ?? 0),
                'lastAssigned' => $lastAssigned,
                'lastAssignedTime' => $lastAssignedTime, // Last time this vehicle was assigned
                'alreadyAssigned' => $assignedVeh, // Whether this vehicle is currently assigned to this booking
                'driverID' => $isAssigned ? db_output2($vehicleRow['iDriverID']) : 0,
                'driverName' => $isAssigned ? db_output2($vehicleRow['driverName']) : '',
                'driverMobile' => $isAssigned ? db_output2($vehicleRow['driverMobile']) : '',
                //  'assignedFrom' => $isAssigned ? $vehicleRow['dtAssigned_From'] : null,
                'nextTripTime' => $nextTripTime,
                "disposal" => false,
                "status" => 'A' 
                // 'nextTripLocation' => $nextTripLocation,
                // 'nextTripDestination' => $nextTripDestination
            ];
        }

        echo json_encode([
            "data" => [
                "vehicleCategories" => $vehicleCategories,
                "vehicles" => $vehicles,
                "vehicleTypeOpt" => $vehicleTypeOpt,
                "tripStatusOpts" => $tripStatusOpts
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: ASSIGN_REQUEST_VEHICLE =====================
    case 'ASSIGN_REQUEST_VEHICLE':
        $iFleet_BookingID = intval($_REQUEST['bookingId'] ?? 0);
        $iVehicleID = intval($_REQUEST['vehicleId'] ?? 0);
        $iDriverID = intval($_REQUEST['driverId'] ?? 0);
        $vRemarks = db_input($_REQUEST['remarks'] ?? '');

        // Validate required inputs
        if ($iFleet_BookingID <= 0) {
            echo json_encode([
                "error" => ["message" => "Booking ID is required"],
                "statusCode" => 400
            ]);
            exit;
        }

        if ($iVehicleID <= 0) {
            echo json_encode([
                "error" => ["message" => "Vehicle ID is required"],
                "statusCode" => 400
            ]);
            exit;
        }

        if ($iDriverID <= 0) {
            echo json_encode([
                "error" => ["message" => "Driver ID is required"],
                "statusCode" => 400
            ]);
            exit;
        }

        // Check if booking exists and is active
        $bookingCheckSql = "SELECT iFleet_BookingID, vName, vPickUpTime FROM fleet_booking 
                           WHERE iFleet_BookingID = $iFleet_BookingID AND cStatus = 'A' LIMIT 1";
        $bookingCheckRes = sql_query($bookingCheckSql);

        if (sql_num_rows($bookingCheckRes) == 0) {
            echo json_encode([
                "error" => ["message" => "Booking not found or inactive"],
                "statusCode" => 404
            ]);
            exit;
        }

        $bookingData = sql_fetch_assoc($bookingCheckRes);

        // Check if vehicle exists and is active
        $vehicleCheckSql = "SELECT v.iVehicleID, v.vRnum, vc.vName as categoryName 
                           FROM vehicle v 
                           LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID 
                           WHERE v.iVehicleID = $iVehicleID AND v.cStatus = 'A' LIMIT 1";
        $vehicleCheckRes = sql_query($vehicleCheckSql);

        if (sql_num_rows($vehicleCheckRes) == 0) {
            echo json_encode([
                "error" => ["message" => "Vehicle not found or inactive"],
                "statusCode" => 404
            ]);
            exit;
        }

        $vehicleData = sql_fetch_assoc($vehicleCheckRes);

        // Check if driver exists and is active
        $driverCheckSql = "SELECT iDriverID, vName, vMobileNum FROM driver 
                          WHERE iDriverID = $iDriverID AND cStatus = 'A' LIMIT 1";
        $driverCheckRes = sql_query($driverCheckSql);

        if (sql_num_rows($driverCheckRes) == 0) {
            echo json_encode([
                "error" => ["message" => "Driver not found or inactive"],
                "statusCode" => 404
            ]);
            exit;
        }

        $driverData = sql_fetch_assoc($driverCheckRes);

        // Check if vehicle is already assigned to another booking at the same time
        $conflictCheckSql = "SELECT iFleet_BookingID, vName FROM fleet_booking 
                            WHERE iVehicleID = $iVehicleID 
                            AND iFleet_BookingID != $iFleet_BookingID 
                            AND cStatus = 'A' 
                            AND vPickUpTime = '" . db_input($bookingData['vPickUpTime']) . "'";
        $conflictCheckRes = sql_query($conflictCheckSql);

        if (sql_num_rows($conflictCheckRes) > 0) {
            $conflictData = sql_fetch_assoc($conflictCheckRes);
            echo json_encode([
                "error" => [
                    "message" => "Vehicle is already assigned to another booking at the same time",
                    "conflictBooking" => [
                        "bookingId" => intval($conflictData['iFleet_BookingID']),
                        "passengerName" => db_output2($conflictData['vName'])
                    ]
                ],
                "statusCode" => 409
            ]);
            exit;
        }

        // Check if driver is already assigned to another booking at the same time
        $driverConflictSql = "SELECT fb.iFleet_BookingID, fb.vName FROM fleet_booking fb
                             WHERE fb.iDriverID = $iDriverID 
                             AND fb.iFleet_BookingID != $iFleet_BookingID 
                             AND fb.cStatus = 'A' 
                             AND fb.vPickUpTime = '" . db_input($bookingData['vPickUpTime']) . "'";
        $driverConflictRes = sql_query($driverConflictSql);

        if (sql_num_rows($driverConflictRes) > 0) {
            $driverConflictData = sql_fetch_assoc($driverConflictRes);
            echo json_encode([
                "error" => [
                    "message" => "Driver is already assigned to another booking at the same time",
                    "conflictBooking" => [
                        "bookingId" => intval($driverConflictData['iFleet_BookingID']),
                        "passengerName" => db_output2($driverConflictData['vName'])
                    ]
                ],
                "statusCode" => 409
            ]);
            exit;
        }

        // Update the booking with vehicle and driver assignment
        $dtNow = NOW;
        $updateSql = "UPDATE fleet_booking SET 
                     iVehicleID = $iVehicleID,
                     iDriverID = $iDriverID,
                     vRemarks = '" . db_input($vRemarks) . "',
                     dtUpdated = '" . db_input($dtNow) . "',
                     iUpdated_UserID = $user_id
                     WHERE iFleet_BookingID = $iFleet_BookingID AND cStatus = 'A'";

        $updateResult = sql_query($updateSql);

        if (!$updateResult) {
            echo json_encode([
                "error" => ["message" => "Failed to assign vehicle and driver to booking"],
                "statusCode" => 500
            ]);
            exit;
        }

        // $assocCheckSql = "SELECT iDVAssocID FROM driver_vehicle_assoc 
        //                  WHERE iDriverID = $iDriverID 
        //                  AND iVehicleID = $iVehicleID 
        //                  AND cStatus = 'A' 
        //                  AND dtAssigned_To IS NULL";
        // $assocCheckRes = sql_query($assocCheckSql);

        // if (sql_num_rows($assocCheckRes) == 0) {
        //     // Create new driver-vehicle association
        //     $iDVAssocID = NextID('iDVAssocID', 'driver_vehicle_assoc');
        //     $assocInsertSql = "INSERT INTO driver_vehicle_assoc 
        //                       (iDVAssocID, iDriverID, iVehicleID, dtAssigned_From, dtAdded, iAdded_UserID, cStatus)
        //                       VALUES 
        //                       ($iDVAssocID, $iDriverID, $iVehicleID, '" . db_input($dtNow) . "', '" . db_input($dtNow) . "', $user_id, 'A')";

        //     $assocResult = sql_query($assocInsertSql);
        //     if (!$assocResult) {
        //         error_log("Warning: Failed to create driver-vehicle association for Driver ID: $iDriverID, Vehicle ID: $iVehicleID");
        //     }
        // }

        echo json_encode([
            "data" => [
                "message" => "Vehicle and driver assigned successfully",
                // "assignment" => [
                //     "bookingId" => $iFleet_BookingID,
                //     "passengerName" => db_output2($bookingData['vName']),
                //     "vehicle" => [
                //         "id" => $iVehicleID,
                //         "regNo" => db_output2($vehicleData['vRnum']),
                //         "category" => db_output2($vehicleData['categoryName'])
                //     ],
                //     "driver" => [
                //         "id" => $iDriverID,
                //         "name" => db_output2($driverData['vName']),
                //         "mobile" => db_output2($driverData['vMobileNum'])
                //     ],
                //     "remarks" => db_output2($vRemarks),
                //     "assignedAt" => $dtNow
                // ]
            ],
            "statusCode" => 200
        ]);
        break;

    case 'VEHICLE_CURRENT_LOCATION':

        $vehiType = $_REQUEST['vehiType'] ?? 0;
        $vehiCat = $_REQUEST['vehiCat'] ?? 0;
        $status = $_REQUEST['status'] ?? '';
        $keyword = isset($_REQUEST['keyword']) ? db_input($_REQUEST['keyword']) : '';
        $CATEGORY_ARR = GetXArrFromYID("select iVCatID,vName from vehicle_category ", "3");
        $filters = [];

        if ($vehiType > 0) {
            $filters[] = "v.vType = '" . $vehiType . "'";
        }

        if ($vehiCat > 0) {
            $filters[] = "v.iCatID = '" . intval($vehiCat) . "'";
        }
        if ($status) {
            if ($status == 'A') {
                $filters[] = "d.cAvailable  = 'Y'";
            } else {
                $filters[] = "d.cAvailable  = 'N'";
            }
        }
        if ($keyword != '') {
            $kw = trim($keyword);

            $filters[] = "(
        v.vRnum LIKE '%$kw%' OR
        RIGHT(v.vRnum,4) LIKE '%$kw%' OR
        d.vName LIKE '%$kw%' OR
        d.vMobileNum LIKE '%$kw%' OR
        vc.vName LIKE '%$kw%'
    )";
        }


        $filterSQL = '';
        if (!empty($filters)) {
            $filterSQL = ' AND ' . implode(' AND ', $filters);
        }

        $sql = "
        SELECT 
            d.iVehicleID, d.vLat, d.vLong, d.dtPinned,d.cAvailable AS status,
            d.vName as driverName, d.vMobileNum AS driverMobile, v.vRnum AS vehicleRegNo, v.iCatID as catID
        FROM driver d
        INNER JOIN (
            SELECT iVehicleID, MAX(dtPinned) AS lastPinned FROM driver
            WHERE cStatus = 'A'
              AND iVehicleID > 0
              AND vLat IS NOT NULL AND vLat != ''
              AND vLong IS NOT NULL AND vLong != ''
              AND dtPinned IS NOT NULL
            GROUP BY iVehicleID
        ) latest 
            ON latest.iVehicleID = d.iVehicleID 
           AND latest.lastPinned = d.dtPinned
        LEFT JOIN vehicle v ON v.iVehicleID = d.iVehicleID
LEFT JOIN vehicle_category vc ON vc.iVCatID = v.iCatID
        WHERE d.cStatus = 'A'
          AND d.iVehicleID > 0 AND d.vLat IS NOT NULL AND d.vLat != '' AND d.vLong IS NOT NULL AND d.vLong != ''
          AND d.dtPinned IS NOT NULL
          $filterSQL
        ORDER BY d.iVehicleID ASC
    ";

        $res = sql_query($sql);

        $rowData = [];
        while ($row = sql_fetch_assoc($res)) {
            if ($row['status'] == 'N') {
                $status = 'U';
            }
             elseif ($row['status'] == 'I') {
                $status = 'I';
            } else {
                $status = 'A';
            }
            $rowData[] = [
                "iVehicleID" => intval($row['iVehicleID']),
                "driverName" => db_output2($row['driverName']),
                "driverMobile" => db_output2($row['driverMobile']),
                "vLat" => $row['vLat'],
                "vLong" => $row['vLong'],
                "vehicleRegNo" => $row['vehicleRegNo'],
                "catID" => intval($row['catID']),
                "catName" => $CATEGORY_ARR[$row['catID']] ?? '',
                "status"       => $status
            ];
        }

        echo json_encode([
            "data" => [
                "rowData" => $rowData
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
