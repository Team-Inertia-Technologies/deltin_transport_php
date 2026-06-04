<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []); 
$mode = $_REQUEST['mode'] ?? '';
$NUMBER_OF_ATTEMPTS = GetXFromYID("SELECT  vValue FROM sys_settings WHERE vCode='OTP_ATTEMPTS'");
$RESTRICT_TIME_HOURS = GetXFromYID("SELECT  vValue FROM sys_settings WHERE vCode='OTP_RESTRICT_TIME_HOURS'");

$TIME = NOW;
if ($mode == 'LOGIN') {
    $mob = db_input($_REQUEST['mob'] ?? '');

    if (empty($mob)) {

        echo json_encode([
            "error" => [
                "message" => "Mobile number is required"
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    $sql = "SELECT vMobile, cStatus FROM staff WHERE vMobile = '" . db_input($mob) . "' AND cStatus = 'A'";
    $res = sql_query($sql);

    if (sql_num_rows($res) > 0) {
        // User exists - Generate and send OTP
        $OtpID = NextID('iOTPID', 'otp');
        $dtTo = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $otp = GenerateRandomCode('4', 'vOTP', 'otp');
        // $otp = "1234";

        // Deactivate previous OTPs for this mobile
        sql_query("UPDATE otp SET cUsed='X' WHERE vPhone='" . db_input($mob) . "'");

        // Check OTP limit
        $otpCount = checkOTPLimit($mob, $RESTRICT_TIME_HOURS);



        if ($otpCount >= $NUMBER_OF_ATTEMPTS) {


            echo json_encode([
                "error" => [
                    "message" => "You have exceeded the maximum number of OTP attempts. Please try again after some time."
                ],
                "statusCode" => 400
            ]);
            exit;
        }

        $code = '+91';
        sql_query("INSERT INTO otp(iOTPID,dtAdded,vCode,cAdded_RefType,iAdded_UserID,cType,iUserID,vOTP,vPhone,dtFrom,dtTo,cUsed) VALUES ('" . db_input($OtpID) . "','" . db_input($TIME) . "','" . db_input($code) . "','S','0','A','0','" . db_input($otp) . "','" . db_input($mob) . "','" . db_input($TIME) . "','" . db_input($dtTo) . "','N')", "Insert OTP for staff login");

        // Send SMS (commented out for now)
        $message = urlencode('Your OTP for staff login is: ' . $otp);
        $message = urlencode('Use code ' . $otp . ' to verify your login for Deltin Transport. This OTP is valid for 5 minutes.');
        $templateid = '1707176249288519068';
        $to = $mob;
        if (strlen($to) == 10)
            $to = '91' . $to;
        $sms_response = SendSmsCurl2($templateid, $to, $message);
        
     // SendWhatsappMessage2($to, $otp);
        echo json_encode([
            "data" => [
                "userAvai" => true
            ],
            "statusCode" => 200,
            "message" => "OTP sent to your mobile number"
        ]);
    } else {
        // User doesn't exist - show register screen
        echo json_encode([
            "data" => [
                "userAvai" => false
            ],
            "statusCode" => 200
        ]);
    }

    exit;
} elseif ($mode == 'VERIFY_OTP') {
    $mobile = db_input($_REQUEST['mobile'] ?? '');
    $OTP = db_input($_REQUEST['otp'] ?? '');
    $newUser = $_REQUEST['newUser'] ?? false;

    if (empty($mobile) || empty($OTP)) {

        echo json_encode([
            "error" => [
                "message" => "Mobile number and OTP are required"
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    // Check if OTP exists and is valid in otp table for staff (both login 'A' and registration 'R' types)
    $otp_query = "SELECT iOTPID FROM otp WHERE vOTP='" . db_input($OTP) . "' AND vPhone='" . db_input($mobile) . "' AND cAdded_RefType='S' AND cUsed!='X' AND '" . db_input($TIME) . "' < dtTo";
    $otp_result = sql_query($otp_query, "Check if OTP exists for staff");

    if (sql_num_rows($otp_result)) {
        [$iOTPID] = sql_fetch_row($otp_result);

        // Deactivate the OTP
        sql_query("UPDATE otp SET cUsed='X' WHERE iOTPID='" . db_input($iOTPID) . "'");
        if ($newUser) {
            // Get registration data from request
            $vCode = db_input($_REQUEST['code'] ?? '');
            $vName = db_input($_REQUEST['name'] ?? '');
            $vMobile = db_input($_REQUEST['mobile'] ?? '');
            $iRouteID = db_input($_REQUEST['routeid'] ?? 0);
            $iStopID = db_input($_REQUEST['stopid'] ?? 0);

            // Validate required fields
            if (empty($vCode)) {
                echo json_encode([
                    "error" => [
                        "message" => "Staff code is required for registration"
                    ],
                    "statusCode" => 400
                ]);
                exit;
            }

            if (empty($vName)) {
                echo json_encode([
                    "error" => [
                        "message" => "Staff name is required for registration"
                    ],
                    "statusCode" => 400
                ]);
                exit;
            }

            // Check for duplicate vCode or vMobile
            $checkSql = "SELECT iStaffID, vCode, vMobile FROM staff WHERE (vCode = '" . db_input($vCode) . "' OR vMobile = '" . db_input($vMobile) . "') AND cStatus != 'X'";
            $checkRes = sql_query($checkSql);

            if (sql_num_rows($checkRes) > 0) {
                $existingRow = sql_fetch_assoc($checkRes);
                if ($existingRow['vCode'] === $vCode) {
                    echo json_encode([
                        "error" => [
                            "message" => "Staff code already exists"
                        ],
                        "statusCode" => 409
                    ]);
                    exit;
                }
                if ($existingRow['vMobile'] === $vMobile) {
                    echo json_encode([
                        "error" => [
                            "message" => "Mobile number already exists"
                        ],
                        "statusCode" => 409
                    ]);
                    exit;
                }
            }

            // Create new staff record
            $iStaffID = NextID('iStaffID', 'staff');
            $cStatus = 'P';
            $dtRegistered = NOW;

            $sql = "INSERT INTO staff (iStaffID, vCode, vName, vMobile, iRouteID, iStopID, dtRegistered, cStatus) 
                    VALUES ($iStaffID, '" . db_input($vCode) . "', '" . db_input($vName) . "', '" . db_input($vMobile) . "', $iRouteID, $iStopID, '" . db_input($dtRegistered) . "', '" . db_input($cStatus) . "')";

            if (sql_query($sql)) {
                $USER_DATA = [
                    'id' => $iStaffID,
                    'name' => db_output2($vName),
                    'mobile' => db_output2($vMobile),
                    'token' => EncodeParam($iStaffID),
                      'message' => 'Registration completed and login successful'
                ];

                // Log the signin
                sql_query("INSERT INTO st_log_signin (dDate, cRefType, iRefID, dtEntry, vIPAddress, vBrowser, cStatus) VALUES ('" . TODAY . "', 'S', '$iStaffID', '" . NOW . "', '" . ($_SERVER['REMOTE_ADDR'] ?? '') . "', '" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "', 'A')", "Log staff signin");

                $q = "update staff set dtLastLogin='" . NOW . "', cActive='Y' where iStaffID=$iStaffID";
                $r = sql_query($q, 'AUTH.78');

                echo json_encode([
                    'statusCode' => 200,
                    'data' => $USER_DATA,
                      
                ]);
                exit;
            } else {
                echo json_encode([
                    "error" => [
                        "message" => "Failed to complete registration"
                    ],
                    "statusCode" => 500
                ]);
                exit;
            }
        } else {
            // This is a login OTP or existing user verification
            // Check if user exists in staff table
            $staff_query = "SELECT iStaffID, vName, vMobile FROM staff WHERE vMobile='" . db_input($mobile) . "' AND cStatus='A'";
            $staff_result = sql_query($staff_query, "Get staff details");

            if (sql_num_rows($staff_result)) {
                [$staffId, $firstName, $staffMobile] = sql_fetch_row($staff_result);

                $staffName = $firstName;

                $USER_DATA = [
                    'id' => $staffId,
                    'name' => db_output2($staffName),
                    'mobile' => db_output2($staffMobile),
                    'token' => EncodeParam($staffId),
                       'message' => 'Login successful'
                ];

                // Log the signin
                sql_query("INSERT INTO st_log_signin (dDate, cRefType, iRefID, dtEntry, vIPAddress, vBrowser, cStatus) VALUES ('" . TODAY . "', 'S', '$staffId', '" . NOW . "', '" . ($_SERVER['REMOTE_ADDR'] ?? '') . "', '" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "', 'A')", "Log staff signin");

                $q = "update staff set dtLastLogin='" . NOW . "', cActive='Y' where iStaffID=$staffId";
                $r = sql_query($q, 'AUTH.78');
                echo json_encode([
                    'statusCode' => 200,
                    'data' => $USER_DATA,
                      
                ]);
                exit;
            } else {
                echo json_encode([
                    "error" => [
                        "message" => "Staff member not found or inactive."
                    ],
                    "statusCode" => 404
                ]);
                exit;
            }
        }
    } else {

        echo json_encode([
            "error" => [
                "message" => "Invalid or expired OTP. Please request a new OTP."
            ],
            "statusCode" => 400
        ]);
        exit;
    }
} elseif ($mode == 'RESEND_OTP') {
    $mobile = db_input($_REQUEST['mobile'] ?? '');

    if (empty($mobile)) {

        echo json_encode([
            "error" => [
                "message" => "Mobile number is required"
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    // Check if staff exists
    $staff_check = "SELECT vMobile FROM staff WHERE vMobile = '" . db_input($mobile) . "' AND cStatus = 'A'";
    $staff_res = sql_query($staff_check);

    if (sql_num_rows($staff_res) > 0) {
        // Generate new OTP
        $OtpID = NextID('iOTPID', 'otp');
        $dtTo = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $otp = GenerateRandomCode('4', 'vOTP', 'otp');
        // $otp = "1234";

        // Deactivate previous OTPs
        sql_query("UPDATE otp SET cUsed='X' WHERE vPhone='" . db_input($mobile) . "'");

        // Check OTP limit
        $otpCount = checkOTPLimit($mobile, $RESTRICT_TIME_HOURS);

        // Test mobile bypass
        if ($mobile == '1234567890') {
            $otpCount = 0;
            $otp = '1234';
        }

        if ($otpCount >= $NUMBER_OF_ATTEMPTS) {

            echo json_encode([
                "error" => [
                    "message" => "You have exceeded the maximum number of OTP attempts. Please try again after some time."
                ],
                "statusCode" => 403
            ]);
            exit;
        }

        // Insert new OTP for staff
        $code = '+91';
        sql_query("INSERT INTO otp(iOTPID,dtAdded,vCode,cAdded_RefType,iAdded_UserID,cType,iUserID,vOTP,vPhone,dtFrom,dtTo,cUsed) VALUES ('" . db_input($OtpID) . "','" . db_input($TIME) . "','" . db_input($code) . "','S','0','A','0','" . db_input($otp) . "','" . db_input($mobile) . "','" . db_input($TIME) . "','" . db_input($dtTo) . "','N')", "Resend OTP for staff");

        // Send SMS (commented out for now)
        $message = urlencode('Your OTP for staff login is: ' . $otp);
        $message = urlencode('Use code ' . $otp . ' to verify your login for Deltin Transport. This OTP is valid for 5 minutes.');
        $templateid = '1707176249288519068';
        $to = $mobile;
        if (strlen($to) == 10)
            $to = '91' . $to;
        $sms_response = SendSmsCurl2($templateid, $to, $message);
        
        // Log SMS response for debugging
        error_log("SMS Response for resend: " . $sms_response . " | Mobile: " . $to . " | OTP: " . $otp);
        
             // SendWhatsappMessage2($to, $otp);

        echo json_encode([
            'statusCode' => 200,
                "data" => [
            'message' => 'OTP resent successfully to your mobile'
                ]
        ]);
        exit;
    } else {

        echo json_encode([
            "error" => [
                "message" => "Staff member not found"
            ],
            "statusCode" => 404
        ]);
        exit;
    }
}

function checkOTPLimit($mobile, $HOURS)
{
    date_default_timezone_set('Asia/Calcutta');
    $HoursAgo = date('Y-m-d H:i:s', strtotime('-' . $HOURS . ' hours'));
    $OTP_COUNT = GetXFromYID("SELECT COUNT(*) as otp_count FROM otp WHERE vPhone ='$mobile' AND dtAdded >'$HoursAgo' ");
    return $OTP_COUNT;
}