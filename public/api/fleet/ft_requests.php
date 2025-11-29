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

    // ===================== CASE: LIST =====================
    case 'LIST':
        // $FLEET_BOOKED_BY = GetXArrFromYID("SELECT iUserID, vName from users where cStatus='A' ORDER BY vName", "3");
        $BOOKING_CAT = GetXArrFromYID("SELECT iFleet_BkCatID, vName from fleet_bookingcategory where cStatus='A' ORDER BY vName", "3");
        $TRAVEL_PURPOSE = GetXArrFromYID("SELECT iFleet_TrvPurID, vName from fleet_travelpurpose where cStatus='A' ORDER BY iRank", "3");
        $TRAVEL_TYPE = sql_query("SELECT iFleet_TrvTypeID, iFleet_TrvPurID, vName from fleet_traveltype where cStatus='A' ORDER BY iRank", "TRAVEL_TYPE");
        $PROPERTY_ARR = GetXArrFromYID("SELECT iPropertyID, vName from property where cStatus='A' ORDER BY vName", "3");
        $MAX_PAX = GetXFromYID("SELECT vValue from sys_settings where vCode = 'FT_BK_MAX_PAX'");
        $MAX_BAG = GetXFromYID("SELECT vValue from sys_settings where vCode = 'FT_BK_MAX_BAG'");
        $VEH_CAT = GetXArrFromYID("SELECT iVCatID, vName from vehicle_category where cStatus='A' AND cType IN ('B','F') ORDER BY iRank", "3");
        // $STAFF_CAT = GetXArrFromYID("SELECT iVCatID, vName from vehicle_category where cStatus='A' AND cType IN ('B','F') ORDER BY iRank", "3");
        $STAFF_DEPT = GetXArrFromYID("SELECT iDepartmentID, vName from department where cStatus='A' ORDER BY vName", "3");
        $STAFF_ARR = sql_query("SELECT iFStaffID, vName, iDepartmentID from fleet_staff where cStatus='A' ORDER BY vName");
        $GUEST_ARR = sql_query("SELECT iGuestID, vName, vMobileNo from guest where cStatus='A' ORDER BY vName");

        $bookedForOpt = [['id' => 0, 'name' => 'Choose']];
        foreach ($FLEET_BOOKING_FOR as $id => $name) {
            $bookedForOpt[] = ['id' => $id, 'name' => $name];
        }
        // $bookedByOpt = [['id' => 0, 'name' => 'Choose']];
        // foreach ($FLEET_BOOKED_BY as $id => $name) {
        //     $bookedByOpt[] = ['id' => $id, 'name' => $name];
        // }
        $bookingCatOpt = [['id' => 0, 'name' => 'Choose']];
        foreach ($BOOKING_CAT as $id => $name) {
            $bookingCatOpt[] = ['id' => intval($id), 'name' => $name];
        }
        // $travelPurposeOpt  = [['id' => 0, 'name' => 'Choose']];
        // foreach ($TRAVEL_PURPOSE as $id => $name) {
        //     $travelPurposeOpt[] = ['id' => intval($id), 'name' => $name];
        // }
        // $travelTypeOpt   = [['id' => 0, 'name' => 'Choose']];
        // while ($row = sql_fetch_assoc($TRAVEL_TYPE)) {
        //     $travelTypeOpt[] = ['id' => intval($row['iFleet_TrvTypeID']), 'name' => $row['vName'], 'purposeId' => intval($row['iFleet_TrvPurID'])];
        // }
        // Build merged Travel Purpose & Type array
        $travelPurposeTypeOpt = [];

        // First initialize purpose groups
        foreach ($TRAVEL_PURPOSE as $id => $name) {
            $travelPurposeTypeOpt[$id] = [
                'id' => intval($id),
                'name' => $name,
                'types' => []
            ];
        }

        while ($row = sql_fetch_assoc($TRAVEL_TYPE)) {
            $purposeId = intval($row['iFleet_TrvPurID']);
            if (isset($travelPurposeTypeOpt[$purposeId])) {
                $travelPurposeTypeOpt[$purposeId]['types'][] = [
                    'id' => intval($row['iFleet_TrvTypeID']),
                    'name' => $row['vName']
                ];
            }
        }

        // Reset array keys (remove gaps)
        $travelPurposeTypeOpt = array_values($travelPurposeTypeOpt);

        $propertyOpt = [['id' => 0, 'name' => 'Choose']];
        foreach ($PROPERTY_ARR as $id => $name) {
            $propertyOpt[] = ['id' => intval($id), 'name' => $name];
        }
        // Extract numeric values (assuming $MAX_PAX and $MAX_BAG return associative array)
        // $maxPaxValue = $MAX_PAX;
        // $maxBagValue = $MAX_BAG;

        // Create arrays 0 to max values (or at least 0 if no limit)
        $paxOpt = ($MAX_PAX > 0) ? range(0, intval($MAX_PAX)) : [0];
        $baggageOpt = ($MAX_BAG > 0) ? range(0, intval($MAX_BAG)) : [0];

        $vehiCatOpt = [['id' => 0, 'name' => 'Choose']];
        foreach ($VEH_CAT as $id => $name) {
            $vehiCatOpt[] = ['id' => intval($id), 'name' => $name];
        }
        $tripTypeArr = [];
        foreach ($FLEET_TRAVEL_TYPE as $id => $name) {
            $tripTypeArr[] = ['id' => intval($id), 'name' => $name];
        }
        $staffDeptOpt = [['id' => 0, 'name' => 'Choose']];
        foreach ($STAFF_DEPT as $id => $name) {
            $staffDeptOpt[] = ['id' => intval($id), 'name' => $name];
        }
        $staffOpt = [];
        while ($row = sql_fetch_assoc($STAFF_ARR)) {
            $staffOpt[] = [
                'id' => intval($row['iFStaffID']),
                'name' => $row['vName'],
                'departmentId' => intval($row['iDepartmentID'])
            ];
        }
        $guestOpts = [];
        while ($row = sql_fetch_assoc($GUEST_ARR)) {
            $guestOpts[] = [
                'id' => intval($row['iGuestID']),
                'name' => $row['vName'],
                'mobile' => $row['vMobileNo']
            ];
        }
        $optArr = [
            "bookedForOpt" => $bookedForOpt,
            "bookedByOpt" => $bookedByOpt,
            "bookingCatOpt" => $bookingCatOpt,
            "travelPurposeOpt" => $travelPurposeTypeOpt,
            "travelTypeOpt" => $travelTypeOpt,
            "propertyOpt" => $propertyOpt,
            "paxOpt" => $paxOpt,
            "baggageOpt" => $baggageOpt,
            "pickUpLocOpt" => $pickUpLocOpt,
            "vehiCatOpt" => $vehiCatOpt,
            "tripTypeArr" => $tripTypeArr,
            "staffDeptOpt" => $staffDeptOpt,
            "staffOpt" => $staffOpt,
            "guestOpts" => $guestOpts
        ];


        echo json_encode([
            "data" => [
                "rowData" => $rowData,
                "optArr" => $optArr
            ],
            "statusCode" => 200
        ]);
        break;

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

        $vPickUpLocation = db_input($_REQUEST['pickUpLoc'] ?? '');
        $vDropLocation = db_input($_REQUEST['dropLoc'] ?? '');
        $vPickUpTime = db_input($_REQUEST['pickUpDateTime'] ?? null);

        $iVehicleCatID = intval($_REQUEST['vehiCat'] ?? 0);
        $vInstructions = db_input($_REQUEST['intruc'] ?? '');

        $tripType = intval($_REQUEST['tripType'] ?? 0);
        $cDisposal = ($tripType == 3) ? 'Y' : 'N';
        $vReturnTime = db_input($_REQUEST['returnTime'] ?? null);

        $iGuestID = intval($_REQUEST['guestID'] ?? 0);
        $iStaffID = intval($_REQUEST['staffID'] ?? 0);

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
        if ($iGuestID == 0 && $iStaffID == 0) {
            $guestCheckSql = "SELECT iGuestID FROM guest WHERE vName = '$vName' AND vMobileNo = '$vMobileNo' AND cStatus = 'A'";
            $guestCheckRes = sql_query($guestCheckSql);

            if (sql_num_rows($guestCheckRes) > 0) {
                $guestRow = sql_fetch_assoc($guestCheckRes);
                $iGuestID = intval($guestRow['iGuestID']);
            } else {
                // generate guest PK and insert
                $guest_id = NextID('iGuestID', 'guest');
                $guestInsertSql = "INSERT INTO guest (iGuestID, vName, vMobileNo, dtCreated, cStatus)
                                   VALUES ($guest_id, '$vName', '$vMobileNo', NOW(), 'A')";
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
                 vDropLocation, iVehicleCatID, cDisposal,dtAdded,iAdded_UserID,cStatus";

        // Create OUTBOUND booking
        $iFleet_BookingID1 = NextID('iFleet_BookingID', 'fleet_booking');
        $dtAdded = NOW;
        // handle possible NULL for vReturnTime
        $vReturnTimeVal = (!empty($vReturnTime)) ? "'$vReturnTime'" : "NULL";

        $sql1 = "
        INSERT INTO fleet_booking ($cols)
        VALUES (
            $iFleet_BookingID1,$iBookedBy, '$cBookingFor', $iFleet_TrvPurID, $iFleet_TrvTypeID, $iPropertyID,
            $iFleet_BKCatID, '$vInstructions', '$vName', '$vMobileNo', $iGuestID, $iStaffID,
            $iPax, $iBaggage, '$vPickUpLocation', '$vPickUpTime',
            '$vDropLocation', $iVehicleCatID, '$cDisposal','$dtAdded',$user_id,'A'
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

        // Round trip case → create RETURN booking
        if ($tripType == 2 && !empty($vReturnTime)) {
            $iFleet_BookingID2 = NextID('iFleet_BookingID', 'fleet_booking');

            $sql2 = "
            INSERT INTO fleet_booking ($cols)
            VALUES (
                $iFleet_BookingID2, $iBookedBy,'$cBookingFor', $iFleet_TrvPurID, $iFleet_TrvTypeID, $iPropertyID,
                $iFleet_BKCatID, '$vInstructions', '$vName', '$vMobileNo', $iGuestID, $iStaffID,
                $iPax, $iBaggage, '$vDropLocation',
                '$vPickUpLocation', $iVehicleCatID, '$cDisposal','$dtAdded',$user_id,'A'
            )";

            $ok2 = sql_query($sql2);
            if ($ok2) {
                $responseIds[] = $iFleet_BookingID2;
            }
            // if return insert fails we still return outbound id (you can change behavior if needed)
        }

        echo json_encode([
            "data" => [
                "message" => "Booking added successfully",
                "bookingIds" => $responseIds
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
