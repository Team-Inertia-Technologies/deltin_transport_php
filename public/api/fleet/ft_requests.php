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

    // ===================== CASE: LIST =====================
    case 'LIST':
        // $FLEET_BOOKED_BY = GetXArrFromYID("SELECT iUserID, vName from users where cStatus='A' ORDER BY vName", "3");
        $BOOKING_CAT = GetXArrFromYID("SELECT iFleet_BkCatID, vName from fleet_bookingcategory where cStatus='A' ORDER BY iRank", "3");
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
        $paxOpt = ($MAX_PAX > 0) ? range(1, intval($MAX_PAX)) : [0];
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
          // "bookedByOpt" => $bookedByOpt,
            "bookingCatOpt" => $bookingCatOpt,
            "travelPurposeOpt" => $travelPurposeTypeOpt,
            "propertyOpt" => $propertyOpt,
            "paxOpt" => $paxOpt,
            "baggageOpt" => $baggageOpt,
            "vehiCatOpt" => $vehiCatOpt,
            "tripTypeArr" => $tripTypeArr,
            "staffDeptOpt" => $staffDeptOpt,
            "staffOpt" => $staffOpt,
            "guestOpts" => $guestOpts
        ];

        // Fetch booking data
        $bookingSql = "
            SELECT 
                fb.iFleet_BookingID,
                fb.vName,
                fb.vMobileNo,
                fb.cBookingFor,
                fb.vPickUpLocation,
                fb.vDropLocation,
                fb.vPickUpTime,
                fb.iPax,
                fb.iBaggage,
                fb.iBookedBy,
                s.vName as bookedByName,
                p.vName as propertyName,
                vc.vName as vehicleCatName
            FROM fleet_booking fb
            LEFT JOIN fleet_staff s ON fb.iBookedBy = s.iFStaffID
            LEFT JOIN property p ON fb.iPropertyID = p.iPropertyID
            LEFT JOIN vehicle_category vc ON fb.iVehicleCatID = vc.iVCatID
            WHERE fb.cStatus = 'A'
            ORDER BY fb.vPickUpTime DESC
        ";
        $bookingRes = sql_query($bookingSql);
        
        $rowData = [];
        while ($row = sql_fetch_assoc($bookingRes)) {
            $rowData[] = [
                'id' => intval($row['iFleet_BookingID']),
                'fullName' => $row['vName'] ?? '',
                'phone' => $row['vMobileNo'] ?? '',
                'from' => strtolower($row['cBookingFor'] ?? ''),
                'location' => db_output2($row['vPickUpLocation'] ?? ''),
                'destination' => db_output2($row['vDropLocation'] ?? ''),
                'pickupTime' => $row['vPickUpTime'] ?? '',
                'typeStatus' => '',
                'paxs' => strval($row['iPax'] ?? '0'),
                'bags' => strval($row['iBaggage'] ?? '0'),
                'bookedBy' => db_output2($row['bookedByName'] ?? ''),
                'pickupByName' => '',
                'pickupByPhone' => '',
                'pickupByType' => '',
                'vehicleDetails' => '',
                'vehicleType' => $row['vehicleCatName'] ?? '',
            ];
        }
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
         $vPickUpTime = (isset($_REQUEST['pickUpDateTime']) && !empty($_REQUEST['pickUpDateTime'])) ? $_REQUEST['pickUpDateTime'] :NULL;

        $iVehicleCatID = intval($_REQUEST['vehiCat'] ?? 0);
        $vInstructions = db_input($_REQUEST['intruc'] ?? '');

        // $tripType = intval($_REQUEST['tripType'] ?? 0);
        // $cDisposal = ($tripType == 3) ? 'Y' : 'N';
        $cDisposal = intval($_REQUEST['disposal'] ?? 'N');
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
                 vDropLocation, iVehicleCatID, cDisposal, tReturnTime, dtAdded,iAdded_UserID,cStatus";

        // Create OUTBOUND booking
        $iFleet_BookingID1 = NextID('iFleet_BookingID', 'fleet_booking');
        $dtAdded = NOW;
        // handle possible NULL for vReturnTime
        $vReturnTimeVal = (!empty($vReturnTime)) ? "'$vReturnTime'" : "NULL";

        $sql1 = "
        INSERT INTO fleet_booking ($cols)
        VALUES (
            $iFleet_BookingID1,$iBookedBy, '$cBookingFor', $iFleet_TrvPurID, $iFleet_TrvTypeID, $iPropertyID,
            $iFleet_BKCatID, '$vInstructions', '$vName', '$vMobileNo', $iGuestID, $iFStaffID,
            $iPax, $iBaggage, '$vPickUpLocation', '$vPickUpTime',
            '$vDropLocation', $iVehicleCatID, '$cDisposal', $vReturnTimeVal, '$dtAdded',$user_id,'A'
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
        $STAFF_DEPT_ARR= GetXArrFromYID("SELECT iDepartmentID, iFStaffID  from fleet_staff where cStatus='A'","3");

        if (sql_num_rows($bookingRes) == 0) {
            echo json_encode([
                "error" => ["message" => "Booking not found"],
                "statusCode" => 404
            ]);
            exit;
        }

        $booking = sql_fetch_assoc($bookingRes);

        // Map DB columns to front-end keys (same as ADD inputs)
        $response = [
            "bookingId"      => intval($booking['iFleet_BookingID']),
            "bookedBy"       => intval($booking['iBookedBy']),
            "bookedFor"      => $booking['cBookingFor'],
            "travelPurpose"  => intval($booking['iFleet_TrvPurID']),
            "travelType"     => intval($booking['iFleet_TrvTypeID']),
            "bookingCat"     => intval($booking['iFleet_BKCatID']),
            "property"       => intval($booking['iPropertyID']),
            "name"           => $booking['vName'],
            "mob"            => $booking['vMobileNo'],
            "pax"            => intval($booking['iPax']),
            "baggage"        => intval($booking['iBaggage']),
            "pickUpLoc"      => db_output2($booking['vPickUpLocation']),
            "dropLoc"        => db_output2($booking['vDropLocation']),
            "pickUpDateTime" => $booking['vPickUpTime'],
            "returnTime"     => ($booking['tReturnTime'] ?? null),
            "vehiCat"        => intval($booking['iVehicleCatID']),
            "intruc"         => $booking['vInstructions'],
            "guestID"        => intval($booking['iGuestID']),
            "staffID"        => intval($booking['iFStaffID']),
            "staff_dept"        => isset($STAFF_DEPT_ARR[intval($booking['iFStaffID'])]) ? $STAFF_DEPT_ARR[intval($booking['iFStaffID'])]:0,
            "disposal"       => ($booking['cDisposal'] ?? 'N'),
            "dtAdded"        => $booking['dtAdded'],
            "addedUserId"    => intval($booking['iAdded_UserID']),
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

        $vPickUpLocation = db_input($_REQUEST['pickUpLoc'] ?? '');
        $vDropLocation = db_input($_REQUEST['dropLoc'] ?? '');
        $vPickUpTime = db_input($_REQUEST['pickUpDateTime'] ?? null);

        $iVehicleCatID = intval($_REQUEST['vehiCat'] ?? 0);
        $vInstructions = db_input($_REQUEST['intruc'] ?? '');

        // $tripType = intval($_REQUEST['tripType'] ?? 0);
        // $cDisposal = ($tripType == 3) ? 'Y' : 'N';
         $cDisposal = intval($_REQUEST['disposal'] ?? 'N');
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
                cBookingFor = '" . $cBookingFor . "',
                iFleet_TrvPurID = " . intval($iFleet_TrvPurID) . ",
                iFleet_TrvTypeID = " . intval($iFleet_TrvTypeID) . ",
                iPropertyID = " . intval($iPropertyID) . ",
                iFleet_BKCatID = " . intval($iFleet_BKCatID) . ",
                vInstructions = '" . $vInstructions . "',
                vName = '" . $vName . "',
                vMobileNo = '" . $vMobileNo . "',
                iGuestID = " . intval($iGuestID) . ",
                iFStaffID = " . intval($iFStaffID) . ",
                iPax = " . intval($iPax) . ",
                iBaggage = " . intval($iBaggage) . ",
                vPickUpLocation = '" . $vPickUpLocation . "',
                vPickUpTime = '" . $vPickUpTime . "',
                vDropLocation = '" . $vDropLocation . "',
                iVehicleCatID = " . intval($iVehicleCatID) . ",
                cDisposal = '" . $cDisposal . "',
                tReturnTime = " . $vReturnTimeVal . ",
                dtUpdated = '" . $dtNow . "',
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

        // Fetch detailed booking information with all related data
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
                fbc.vName as bookingCategoryName,
                p.vName as propertyName,
                d.vName as departmentName,
                s.vName as bookedByName,
                vc.vName as vehicleCategoryName,
                ftp.vName as travelPurposeName,
                ftt.vName as travelTypeName
            FROM fleet_booking fb
            LEFT JOIN fleet_bookingcategory fbc ON fb.iFleet_BKCatID = fbc.iFleet_BkCatID
            LEFT JOIN property p ON fb.iPropertyID = p.iPropertyID
            LEFT JOIN fleet_staff fs ON fb.iFStaffID = fs.iFStaffID
            LEFT JOIN department d ON fs.iDepartmentID = d.iDepartmentID
            LEFT JOIN fleet_staff s ON fb.iBookedBy = s.iFStaffID
            LEFT JOIN vehicle_category vc ON fb.iVehicleCatID = vc.iVCatID
            LEFT JOIN fleet_travelpurpose ftp ON fb.iFleet_TrvPurID = ftp.iFleet_TrvPurID
            LEFT JOIN fleet_traveltype ftt ON fb.iFleet_TrvTypeID = ftt.iFleet_TrvTypeID
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
        ];

        echo json_encode([
            "data" => [
                "requestDetails" => $requestDetails,
                "vehicleHistory" => $vehicleHistory
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: VEHICLE_DETAILS =====================
    case 'VEHICLE_DETAILS':
        // Get filter parameters
        $keyword = db_input($_REQUEST['keyword'] ?? '');
        $categoryID = intval($_REQUEST['categoryID'] ?? 0);
        $typeID = intval($_REQUEST['typeID'] ?? 0);
        
        // Get vehicle categories array
        $vehicleCategorySql = "SELECT iVCatID, vName, iCapacity FROM vehicle_category WHERE cStatus = 'A' ORDER BY vName";
        $vehicleCategoryRes = sql_query($vehicleCategorySql);
        
        $vehicleTypeOpt = [['id' => 0, 'name' => 'All']];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $name) {
            $vehicleTypeOpt[] = ['id' => intval($id), 'name' => $name];
        }
          $tripStatusOpts = [['id' => 0, 'name' => 'All']];
        foreach ($TRIP_STATUS as $id => $name) {
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

        // Build WHERE conditions for filtering
        $whereConditions = ["v.cStatus = 'A'"];
        
        // Add keyword search (search in vehicle registration number and category name)
        if (!empty($keyword)) {
            $whereConditions[] = "(UPPER(v.vRnum) LIKE UPPER('%$keyword%') OR UPPER(vc.vName) LIKE UPPER('%$keyword%'))";
        }
        
        // Add category filter
        if ($categoryID > 0) {
            $whereConditions[] = "v.iCatID = $categoryID";
        }
        
        // Add type filter
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
                'isAssigned' => $isAssigned,
                'driverName' => $isAssigned ? db_output2($vehicleRow['driverName']) : '',
                'driverMobile' => $isAssigned ? db_output2($vehicleRow['driverMobile']) : '',
              //  'assignedFrom' => $isAssigned ? $vehicleRow['dtAssigned_From'] : null,
                'nextTripTime' => $nextTripTime
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
