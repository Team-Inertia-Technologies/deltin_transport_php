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
$NOW=NOW;
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
        $STAFF_ARR = sql_query("SELECT iFStaffID, vName, iDepartmentID, iUserID from fleet_staff where cStatus='A' ORDER BY vName");
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
            // Check if staff member is logged in user
            $staffUserID = intval($row['iUserID'] ?? 0);
            $isLoggedin = false;
            
            if ($staffUserID > 0 && $staffUserID == $user_id) {
                $isLoggedin = true;
            }
            
            $staffOpt[] = [
                'id' => intval($row['iFStaffID']),
                'name' => $row['vName'],
                'departmentId' => intval($row['iDepartmentID']),
                'isLoggedin' => $isLoggedin
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
                fb.iDriverID,
                fb.iVehicleID,
                s.vName as bookedByName,
                p.vName as propertyName,
                vc.vName as vehicleCatName,
                d.vName as driverName,
                d.vMobileNum as driverPhone,
                d.iType as driverType,
                v.vRnum as vehicleRegNo,
                vcat.vName as assignedVehicleCategoryName
            FROM fleet_booking fb
            LEFT JOIN fleet_staff s ON fb.iBookedBy = s.iFStaffID
            LEFT JOIN property p ON fb.iPropertyID = p.iPropertyID
            LEFT JOIN vehicle_category vc ON fb.iVehicleCatID = vc.iVCatID
            LEFT JOIN driver d ON fb.iDriverID = d.iDriverID AND d.cStatus = 'A'
            LEFT JOIN vehicle v ON fb.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
            LEFT JOIN vehicle_category vcat ON v.iCatID = vcat.iVCatID AND vcat.cStatus = 'A'
            WHERE fb.cStatus = 'A'
            ORDER BY fb.vPickUpTime DESC
        ";
        $bookingRes = sql_query($bookingSql);
        
        $rowData = [];
        while ($row = sql_fetch_assoc($bookingRes)) {
            // Get driver type name from VEHICLE_DRIVER_TYPE array
            $driverTypeID = intval($row['driverType'] ?? 0);
            $driverTypeName = isset($VEHICLE_DRIVER_TYPE[$driverTypeID]) ? $VEHICLE_DRIVER_TYPE[$driverTypeID] : '';
            
            // Format vehicle details
            $vehicleDetails = '';
            if (!empty($row['vehicleRegNo'])) {
                $vehicleDetails = db_output2($row['vehicleRegNo']);
                if (!empty($row['assignedVehicleCategoryName'])) {
                    $vehicleDetails .= ' (' . db_output2($row['assignedVehicleCategoryName']) . ')';
                }
            }
            
            $rowData[] = [
                'id' => intval($row['iFleet_BookingID']),
                'fullName' => db_output2($row['vName'] ?? ''),
                'phone' => db_output2($row['vMobileNo'] ?? ''),
                'from' => strtolower($row['cBookingFor'] ?? ''),
                'location' => db_output2($row['vPickUpLocation'] ?? ''),
                'destination' => db_output2($row['vDropLocation'] ?? ''),
'pickupTime' => !empty($row['vPickUpTime']) ? date('d/m/Y', strtotime($row['vPickUpTime'])) : '',
                'typeStatus' => '',
                'paxs' => strval($row['iPax'] ?? '0'),
                'bags' => strval($row['iBaggage'] ?? '0'),
                'bookedBy' => db_output2($row['bookedByName'] ?? ''),
                'pickupByName' => db_output2($row['driverName'] ?? ''),
                'pickupByPhone' => db_output2($row['driverPhone'] ?? ''),
                'pickupByType' => $driverTypeName,
                'vehicleDetails' => $vehicleDetails,
                'vehicleType' => db_output2($row['vehicleCatName'] ?? ''),
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
         $vPickUpTime = (isset($_REQUEST['pickUpDateTime']) && !empty($_REQUEST['pickUpDateTime'])) ? $_REQUEST['pickUpDateTime'] :NULL;

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
                                   VALUES ($guest_id, '" . db_input($vName) . "', '" . db_input($vMobileNo) . "', '$NOW', 'A')";
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
        $STAFF_DEPT_ARR= GetXArrFromYID("SELECT iDepartmentID, iFStaffID  from fleet_staff where cStatus='A'","3");

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
            "pickUpLoc"      => [
                'lat' => isset($pickUpLatLng[0]) && !empty(trim($pickUpLatLng[0])) ? floatval(trim($pickUpLatLng[0])) : null,
                'lng' => isset($pickUpLatLng[1]) && !empty(trim($pickUpLatLng[1])) ? floatval(trim($pickUpLatLng[1])) : null,
                'loc' => db_output2($booking['vPickUpLocation'])
            ],
            "dropLoc"        => [
                'lat' => isset($dropLatLng[0]) && !empty(trim($dropLatLng[0])) ? floatval(trim($dropLatLng[0])) : null,
                'lng' => isset($dropLatLng[1]) && !empty(trim($dropLatLng[1])) ? floatval(trim($dropLatLng[1])) : null,
                'loc' => db_output2($booking['vDropLocation'])
            ],
            "pickUpDateTime" => $booking['vPickUpTime'],
            "returnTime"     => ($booking['tReturnTime'] ?? null),
            "vehiCat"        => intval($booking['iVehicleCatID']),
            "intruc"         => $booking['vInstructions'],
            "guestID"        => intval($booking['iGuestID']),
            "staffID"        => intval($booking['iFStaffID']),
            "staff_dept"        => isset($STAFF_DEPT_ARR[intval($booking['iFStaffID'])]) ? $STAFF_DEPT_ARR[intval($booking['iFStaffID'])]:0,
            //"disposal"       => ($booking['cDisposal'] ?? 'N') === 'Y' ? true : false,
            "disposal"       => $booking['cDisposal'] ?? 'N',
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
            $tripStatusArr[] = ['id' =>$id, 'name' => $name];
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
                "tripStatusOpts"=>$tripStatusArr
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: SEARCH_VEHICLE =====================
    case 'SEARCH_VEHICLE':
        $keyword = db_input($_REQUEST['keyword'] ?? '');
        $categoryID = intval($_REQUEST['categoryID'] ?? 0);
        $typeID = intval($_REQUEST['typeID'] ?? 0);
        $iFleet_BookingID= intval($_REQUEST['bookingId'] ?? 0);
        // Get vehicle categories array
        $vehicleCategorySql = "SELECT iVCatID, vName, iCapacity FROM vehicle_category WHERE cStatus = 'A' ORDER BY vName";
        $vehicleCategoryRes = sql_query($vehicleCategorySql);
        
        $vehicleTypeOpt = [['id' => 0, 'name' => 'All']];
        foreach ($VEHICLE_DRIVER_TYPE as $id => $name) {
            $vehicleTypeOpt[] = ['id' => intval($id), 'name' => $name];
        }
         
        foreach ($FLEET_TRIP_STATUS as $id => $name) {
            $tripStatusOpts[] = ['id' => $id, 'name' => $name];
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
                               AND vPickUpTime <= '$NOW' 
                               ORDER BY vPickUpTime DESC 
                               LIMIT 1";
            $lastAssignedRes = sql_query($lastAssignedSql);
            $lastAssignedTime = null;
            $lastAssigned=false;
            if (sql_num_rows($lastAssignedRes) > 0) {
                $lastAssignedRow = sql_fetch_assoc($lastAssignedRes);
                $lastAssignedTime = $lastAssignedRow['vPickUpTime'];
                $lastAssigned=true;
            }
            
            // Get next trip time for this vehicle
            $nextTripSql = "SELECT vPickUpTime
                           FROM fleet_booking 
                           WHERE iVehicleID = $vehicleID 
                           AND cStatus = 'A' 
                           AND vPickUpTime > '$NOW' 
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
                "disposal"=>false,
                "status"=>'A', //static
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




    // ===================== CASE: SEND_TRIP_DATA =====================
    case 'SEND_TRIP_DATA':
        $iFleet_BookingID = intval($_REQUEST['bookingId'] ?? 0);

        if ($iFleet_BookingID <= 0) {
            echo json_encode([
                "error" => ["message" => "bookingId missing or invalid"],
                "statusCode" => 400
            ]);
            exit;
        }

        // Fetch comprehensive trip data for sending
        $tripDataSql = "
            SELECT 
                fb.iFleet_BookingID,
                fb.vName as passengerName,
                fb.vMobileNo as passengerMobile,
                fb.cBookingFor,
                fb.vPickUpLocation,
                fb.vDropLocation,
                fb.vPickUpTime,
                fb.tReturnTime,
                fb.vInstructions,
                fb.vRemarks,
                fb.iPax,
                fb.iBaggage,
                fb.cType as tripStatus,
                fbc.vName as bookingCategoryName,
                p.vName as propertyName,
                s.vName as bookedByName,
                s.vMobile as bookedByMobile,
                vc.vName as vehicleCategoryName,
                v.vRnum as vehicleRegNo,
                vcat.vName as assignedVehicleCategoryName,
                dr.vName as driverName,
                dr.vMobileNum as driverMobile,
                dr.iType as driverType,
                g.vName as guestName,
                g.vMobileNo as guestMobile,
                fs.vName as staffName,
                d.vName as departmentName
            FROM fleet_booking fb
            LEFT JOIN fleet_bookingcategory fbc ON fb.iFleet_BKCatID = fbc.iFleet_BkCatID
            LEFT JOIN property p ON fb.iPropertyID = p.iPropertyID
            LEFT JOIN fleet_staff s ON fb.iBookedBy = s.iFStaffID
            LEFT JOIN vehicle_category vc ON fb.iVehicleCatID = vc.iVCatID
            LEFT JOIN vehicle v ON fb.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
            LEFT JOIN vehicle_category vcat ON v.iCatID = vcat.iVCatID AND vcat.cStatus = 'A'
            LEFT JOIN driver dr ON fb.iDriverID = dr.iDriverID AND dr.cStatus = 'A'
            LEFT JOIN guest g ON fb.iGuestID = g.iGuestID AND g.cStatus = 'A'
            LEFT JOIN fleet_staff fs ON fb.iFStaffID = fs.iFStaffID AND fs.cStatus = 'A'
            LEFT JOIN department d ON fs.iDepartmentID = d.iDepartmentID AND d.cStatus = 'A'
            WHERE fb.iFleet_BookingID = $iFleet_BookingID AND fb.cStatus = 'A'
            LIMIT 1
        ";

        $tripDataRes = sql_query($tripDataSql);

        if (sql_num_rows($tripDataRes) == 0) {
            echo json_encode([
                "error" => ["message" => "Trip data not found"],
                "statusCode" => 404
            ]);
            exit;
        }

        $tripData = sql_fetch_assoc($tripDataRes);

        // Fetch booking log data with trip stages including pause reasons
        $bookingLogSql = "
            SELECT 
                bl.iLogID,
                bl.iFleet_BookingID,
                bl.cRefType,
                bl.dtAdded,
                bl.iUserID as driverID,
                d.vName as driverName,
                d.vMobileNum as driverMobile,
                tpl.iPauseTypeID,
                tpl.vNotes as pauseNotes,
                tpl.dtPauseTime,
                pr.vName as pauseReason
            FROM booking_log bl
            LEFT JOIN driver d ON bl.iUserID = d.iDriverID AND d.cStatus = 'A'
            LEFT JOIN trip_pause_log tpl ON bl.iFleet_BookingID = tpl.iFleet_BookingID 
                AND bl.iUserID = tpl.iDriverID 
                AND bl.cRefType = 'P'
                AND ABS(TIMESTAMPDIFF(SECOND, bl.dtAdded, tpl.dtPauseTime)) <= 60
            LEFT JOIN pause_reasons pr ON tpl.iPauseTypeID = pr.iReasonID AND pr.cStatus = 'A'
            WHERE bl.iFleet_BookingID = $iFleet_BookingID
            ORDER BY bl.dtAdded ASC
        ";

        $bookingLogRes = sql_query($bookingLogSql);
        $tripStages = [];
        
        while ($logRow = sql_fetch_assoc($bookingLogRes)) {
            $stageStatus = $logRow['cRefType'];
            $stageName = isset($FL_LOG_STATUS_ARR[$stageStatus]) ? $FL_LOG_STATUS_ARR[$stageStatus] : 'Unknown Stage';
            $driverName = db_output2($logRow['driverName'] ?? '');
            $passengerName = db_output2($tripData['passengerName'] ?? '');
            
            // Create descriptive message starting with driver name (like in the image)
            $description = '';
            switch ($stageStatus) {
                case 'S':
                    $description = "$driverName started the trip";
                    break;
                case 'G':
                    $description = "$driverName picked up $passengerName";
                    break;
                case 'P':
                    // Include pause reason if available
                    $pauseReason = db_output2($logRow['pauseReason'] ?? '');
                    $pauseNotes = db_output2($logRow['pauseNotes'] ?? '');
                    
                    if (!empty($pauseReason)) {
                        $description = "$driverName paused the trip due to $pauseReason";
                        if (!empty($pauseNotes)) {
                            $description .= " ($pauseNotes)";
                        }
                    } else {
                        $description = "$driverName paused the trip";
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
            
            $tripStages[] = [
                'logId' => intval($logRow['iLogID']),
                'stageCode' => $stageStatus,
                'stageName' => $stageName,
                'description' => $description,
                'dateTime' => !empty($logRow['dtAdded']) ? date('d/m/Y H:i', strtotime($logRow['dtAdded'])) : '',
                'timestamp' => $logRow['dtAdded'],
                'driverID' => intval($logRow['driverID'] ?? 0),
                'driverName' => $driverName,
                'driverMobile' => db_output2($logRow['driverMobile'] ?? ''),
                'passengerName' => $passengerName,
                'pauseReason' => db_output2($logRow['pauseReason'] ?? ''),
                'pauseNotes' => db_output2($logRow['pauseNotes'] ?? ''),
                'pauseTypeID' => intval($logRow['iPauseTypeID'] ?? 0)
            ];
        }

        // Calculate simple pause count for display
        $pauseCount = 0;
        foreach ($tripStages as $stage) {
            if ($stage['stageCode'] === 'P') {
                $pauseCount++;
            }
        }



        // Format the trip completion data similar to the image structure
        $completedTripData = [
            'tripStatus' => 'Completed Trip',
            'fullName' => db_output2($tripData['passengerName'] ?? ''),
            'guestStaffType' => $tripData['cBookingFor'] === 'G' ? 'Guest' : 'Staff',
            'passengerMobile' => db_output2($tripData['passengerMobile'] ?? ''),
            'bags' => sprintf('%02d', intval($tripData['iBaggage'] ?? 0)),
            'pax' => sprintf('%02d', intval($tripData['iPax'] ?? 0)),
            'bookedBy' => db_output2($tripData['bookedByName'] ?? ''),
            'category' => db_output2($tripData['bookingCategoryName'] ?? ''),
            'pickupDateTime' => !empty($tripData['vPickUpTime']) ? date('d/m/Y, H:i', strtotime($tripData['vPickUpTime'])) : '',
            'dropDateTime' => !empty($tripData['tReturnTime']) ? date('d/m/Y, H:i', strtotime($tripData['tReturnTime'])) : '',
            'pickupFrom' => db_output2($tripData['vPickUpLocation'] ?? ''),
            'dropTo' => db_output2($tripData['vDropLocation'] ?? ''),
            'pickedupBy' => [
                'name' => db_output2($tripData['driverName'] ?? ''),
                'mobile' => db_output2($tripData['driverMobile'] ?? ''),
                'type' => isset($VEHICLE_DRIVER_TYPE[intval($tripData['driverType'] ?? 0)]) ? 
                         $VEHICLE_DRIVER_TYPE[intval($tripData['driverType'])] : 'Driver'
            ],
            'vehicle' => [
                'type' => db_output2($tripData['assignedVehicleCategoryName'] ?? $tripData['vehicleCategoryName'] ?? ''),
                'regNo' => db_output2($tripData['vehicleRegNo'] ?? ''),
                'fullDetails' => db_output2($tripData['vehicleRegNo'] ?? '') . ' | ' . 
                               db_output2($tripData['assignedVehicleCategoryName'] ?? $tripData['vehicleCategoryName'] ?? '')
            ],
            'tripPaused' => sprintf('%02d', $pauseCount),
            'remarks' => db_output2($tripData['vRemarks'] ?? 'N/A'),
            'instructions' => db_output2($tripData['vInstructions'] ?? 'N/A'),
            'bookingId' => intval($tripData['iFleet_BookingID']),
            'propertyName' => db_output2($tripData['propertyName'] ?? ''),
            'departmentName' => db_output2($tripData['departmentName'] ?? '')
        ];

        echo json_encode([
            "data" => [
                "tripData" => $completedTripData,
                "tripStages" => $tripStages
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
