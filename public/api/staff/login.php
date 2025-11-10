<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true); // Decode as associative array
$_REQUEST = array_merge($_REQUEST, $request ?? []); // Merge with $_REQUEST
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

    // Check if user exists in staff table with vMobile and cStatus
    $sql = "SELECT vMobile, cStatus FROM staff WHERE vMobile = '$mob' AND cStatus = 'A'";
    $res = sql_query($sql);

    if (sql_num_rows($res) > 0) {
        // User exists - Generate and send OTP
        $OtpID = NextID('iOTPID', 'otp');
        $dtTo = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $otp = GenerateRandomCode('4', 'vOTP', 'otp');
        // $otp = "1234";

        // Deactivate previous OTPs for this mobile
        sql_query("UPDATE otp SET cUsed='X' WHERE vPhone='$mob'");

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

        // Insert OTP into otp table for staff
        $code = '+91';
        sql_query("INSERT INTO otp(iOTPID,dtAdded,vCode,cAdded_RefType,iAdded_UserID,cType,iUserID,vOTP,vPhone,dtFrom,dtTo,cUsed) VALUES ('$OtpID','$TIME','$code','S','0','A','0','$otp','$mob','$TIME','$dtTo','N')", "Insert OTP for staff login");

        // Send SMS (commented out for now)
        $message = urlencode('Your OTP for staff login is: ' . $otp);
        $message = urlencode('Use code ' . $otp . ' to verify your login for Deltin Transport. This OTP is valid for 5 minutes.');
        $templateid = '1707176249288519068';
        $to = $mob;
        if (strlen($to) == 10)
            $to = '91' . $to;
        SendSmsCurl2($templateid, $to, $message);
      SendWhatsappMessage2($to, $otp);
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

    if (empty($mobile) || empty($OTP)) {

        echo json_encode([
            "error" => [
                "message" => "Mobile number and OTP are required"
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    // Check if OTP exists and is valid in otp table for staff
    $otp_query = "SELECT iOTPID FROM otp WHERE vOTP='$OTP' AND vPhone='$mobile' AND cAdded_RefType='S' AND cUsed!='X' AND '$TIME' < dtTo";
    $otp_result = sql_query($otp_query, "Check if OTP exists for staff");

    if (sql_num_rows($otp_result)) {
        [$iOTPID] = sql_fetch_row($otp_result);

        // Deactivate the OTP
        sql_query("UPDATE otp SET cUsed='X' WHERE iOTPID='$iOTPID'");

        // Check if user exists in staff table
        $staff_query = "SELECT iStaffID, vName, vMobile FROM staff WHERE vMobile='$mobile' AND cStatus='A'";
        $staff_result = sql_query($staff_query, "Get staff details");

        if (sql_num_rows($staff_result)) {
            [$staffId, $firstName, $staffMobile] = sql_fetch_row($staff_result);

            $staffName = $firstName;

            $USER_DATA = [
                'id' => $staffId,
                'name' => $staffName,
                'mobile' => $staffMobile,
                'token' => EncodeParam($staffId)
            ];

            // Log the signin
            sql_query("INSERT INTO st_log_signin (dDate, cRefType, iRefID, dtEntry, vIPAddress, vBrowser, cStatus) VALUES ('" . TODAY . "', 'S', '$staffId', '" . NOW . "', '" . ($_SERVER['REMOTE_ADDR'] ?? '') . "', '" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "', 'A')", "Log staff signin");

            $q = "update staff set dtLastLogin='" . NOW . "', cActive='Y' where iStaffID=$staffId";
            $r = sql_query($q, 'AUTH.78');
            echo json_encode([
                'statusCode' => 200,
                'data' => $USER_DATA,
                'message' => 'Login successful'
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
    $staff_check = "SELECT vMobile FROM staff WHERE vMobile = '$mobile' AND cStatus = 'A'";
    $staff_res = sql_query($staff_check);

    if (sql_num_rows($staff_res) > 0) {
        // Generate new OTP
        $OtpID = NextID('iOTPID', 'otp');
        $dtTo = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $otp = GenerateRandomCode('4', 'vOTP', 'otp');
        // $otp = "1234";

        // Deactivate previous OTPs
        sql_query("UPDATE otp SET cUsed='X' WHERE vPhone='$mobile'");

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
        sql_query("INSERT INTO otp(iOTPID,dtAdded,vCode,cAdded_RefType,iAdded_UserID,cType,iUserID,vOTP,vPhone,dtFrom,dtTo,cUsed) VALUES ('$OtpID','$TIME','$code','S','0','A','0','$otp','$mobile','$TIME','$dtTo','N')", "Resend OTP for staff");

        // Send SMS (commented out for now)
        $message = urlencode('Your OTP for staff login is: ' . $otp);
        $message = urlencode('Use code ' . $otp . ' to verify your login for Deltin Transport. This OTP is valid for 5 minutes.');
        $templateid = '1707176249288519068';
        $to = $mob;
        if (strlen($to) == 10)
            $to = '91' . $to;
        SendSmsCurl2($templateid, $to, $message);
              SendWhatsappMessage2($to, $otp);

        echo json_encode([
            'statusCode' => 200,
            'message' => 'OTP resent successfully to your mobile'
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