<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true); // Decode as associative array
$_REQUEST = array_merge($_REQUEST, $request ?? []); // Merge with $_REQUEST
$mode = $_REQUEST['mode'] ?? '';

if ($mode == 'LOGIN') {
    $mob = db_input($_REQUEST['mob'] ?? '');
    
    if (empty($mob)) {
        echo json_encode([
            "data" => null,
            "statusCode" => 400,
            "message" => "Mobile number is required"
        ]);
        exit;
    }
    
    // Check if user exists in staff table with vMobile and cStatus
    $sql = "SELECT vMobile, cStatus FROM staff WHERE vMobile = '$mob' AND cStatus = 'A'";
    $res = sql_query($sql);
    
    if (sql_num_rows($res) > 0) {
        // User exists - show OTP screen
        echo json_encode([
            "data" => [
                "userAvai" => true
            ],
            "statusCode" => 200
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
}

elseif ($mode == 'VERIFY_OTP') {
    $STATUS_CODE = 200;
    $OTP = db_input2($request->otp);
    $q_1 = "select vMobile,iVisitorID from appuser where vMobile='$mobile' and cStatus='A' ";
    $r_1 = sql_query($q_1, "ERR1");


    $_q_1 = "select iOTPID from otp where vOTP='$OTP' and vPhone='$mobile' and cAdded_RefType='L' and cUsed!='X' and '$TIME'<dtTo ";
    $_r_1 = sql_query($_q_1, "Check if otp exst");
    if (sql_num_rows($_r_1)) {
        list($iOTPID) = sql_fetch_row($_r_1);
        sql_query("update otp set cUsed='X' where iOTPID='$iOTPID' "); //Deactivate previous OTP

        $q = "select iMemberID, vFirstName, vLastname from member where vMobile='$mobile' and cStatus='A' ";
        $r = sql_query($q);
        $txtname = $txtuserid = $level = '';

        if (sql_num_rows($r)) {
            list($txtuserid, $txtfname, $txtlname) = sql_fetch_row($r);

            $txtname = $txtfname;
            if (!empty($txtlname)) $txtname .= ' ' . $txtlname;

            $USER_DATA['name'] = $txtname;
            $USER_DATA['id'] = $txtuserid;
            $MEMBERSHIP_DETAILS = GetDataFromCOND("member", " and iMemberID='$txtuserid' and cStatus='A' ");
            $USER_DATA['level'] = $MEMBERSHIP_DETAILS[0]->iMemLevel;
            $USER_DATA['mem_Type'] = $MEMBERSHIP_DETAILS[0]->iMemType;
            $USER_DATA['token'] = EncodeParam($txtuserid);
            sql_query("insert into log_signin (dDate, cRefType, iRefID, dtEntry, vIPAddress, vBrowser, cStatus) values ('" . TODAY . "', 'M', '$txtuserid', '" . NOW . "', '', '', 'A')", "");
            $output = array('statusCode' => 200, 'data' => $USER_DATA, 'message' => 'Your OTP is verified. Click OK to continue.');
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode($output);
            exit;
        } elseif (sql_num_rows($r_1)) {
            list($vMobile, $iVisitorID) = sql_fetch_row($r_1);
            $q_1 = "select iVisitorID,vName from appuser where vMobile='$mobile' and cStatus='A' ";
            $r_1 = sql_query($q_1, "ERR1");
            if (sql_num_rows($r_1)) {
                list($iVisitorID, $name) = sql_fetch_row($r_1);
                $USER_DATA['id'] = $iVisitorID;
                $USER_DATA['name'] = $name;
                $USER_DATA['level'] = 0;
                $USER_DATA['mem_Type'] = 0;
                $USER_DATA['token'] = EncodeParam($iVisitorID);
            }
            $output = array('statusCode' => 302, 'data' => $USER_DATA, 'message' => 'Your OTP is verified. Click OK to continue.');
            http_response_code(302);
            header('Content-Type: application/json');
            echo json_encode($output);
            exit;
        } else {
            $USER_DATA['id'] = 0;
            $USER_DATA['name'] = '';
            $USER_DATA['level'] = 0;
            $USER_DATA['mem_Type'] = 0;
            $USER_DATA['token'] = EncodeParam(0);
            $output = array('statusCode' => 300, 'data' => $USER_DATA, 'message' => 'Your OTP is verified. Click OK to continue.');
            http_response_code(300);
            header('Content-Type: application/json');
            echo json_encode($output);
            exit;
        }
    } else {
        $output = array('statusCode' => 400, 'message' => 'Your OTP has expired. Please request a new OTP to continue.');
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode($output);
        exit;
    }
} elseif ($mode == 'RESEND_OTP') {
    date_default_timezone_set('Asia/Calcutta');


    $q_1 = "select vMobile,iVisitorID from appuser where vMobile='$mobile' and cStatus='A' ";
    $r_1 = sql_query($q_1, "ERR1");

    $q = "select vMobile from member where vMobile='$mobile' and cStatus='A' ";
    $r = sql_query($q, "ERR1");
    if (sql_num_rows($r)) {
        $OtpID = NextID('iOTPID', 'otp');
        $dtTo = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $otp = GenerateRandomCode('4', 'vOTP', 'otp');
        sql_query("update otp set cUsed='X' where vPhone='$mobile' "); //Deactivate previous OTP


        $otpCount = checkOTPLimit($mobile, $RESTRICT_TIME_HOURS);

        if ($mobile == '1234567890') {
            $otpCount = 0;
            $otp = '1234';
        }

        if ($otpCount >= $NUMBER_OF_ATTEMPTS) {
            $output = array('statusCode' => 403, 'message' => 'You have exceeded the maximum number of OTP attempts. Please try again after some time.');
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode($output);
            exit;
        }

        sql_query("INSERT INTO otp(iOTPID,dtAdded,vCode,cAdded_RefType,iAdded_UserID,cType,iUserID,vOTP,vPhone,dtFrom,dtTo,cUsed)  VALUES ('$OtpID','$TIME','$code','L','0','A','0','$otp','$mobile','$TIME','$dtTo','N')", "Insert into Otp table");
        //SMS
        //SMS
        $message = urlencode('Dear Guest, To access your account on DeltinOne, please use ' . $otp . ' as your one-time password (OTP). Best regards, Deltin wPYrBplEnt1');
        $templateid = '1307175128414225156';
        $to = $mobile;
        if (strlen($to) == 10) $to = '91' . $to;
        $status = SendSmsCurl2($templateid, $to, $message);
        SendWhatsappMessage2($to, $otp);
        $output = array('statusCode' => 200, 'message' => 'OTP successfuly sent to your mobile');
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($output);
        exit;
    } elseif (sql_num_rows($r_1)) { //NON MEMBER
        list($vMobile, $iVisitorID) = sql_fetch_row($r_1);
        $OtpID = NextID('iOTPID', 'otp');
        $dtTo = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $otp = GenerateRandomCode('4', 'vOTP', 'otp');
        sql_query("update otp set cUsed='X' where vPhone='$mobile' "); //Deactivate previous OTP

        $otpCount = checkOTPLimit($mobile, $RESTRICT_TIME_HOURS);
        if ($otpCount >= $NUMBER_OF_ATTEMPTS) {
            $output = array('statusCode' => 403, 'message' => 'You have exceeded the maximum number of OTP attempts. Please try again after some time.');
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode($output);
            exit;
        }

        sql_query("INSERT INTO otp(iOTPID,dtAdded,vCode,cAdded_RefType,iAdded_UserID,cType,iUserID,vOTP,vPhone,dtFrom,dtTo,cUsed)  VALUES ('$OtpID','$TIME','$code','L','0','A','0','$otp','$mobile','$TIME','$dtTo','N')", "Insert into Otp table");

        $message = urlencode('Dear Guest, To access your account on DeltinOne, please use ' . $otp . ' as your one-time password (OTP). Best regards, Deltin wPYrBplEnt1');
        $templateid = '1307175128414225156';
        $to = $mobile;
        if (strlen($to) == 10) $to = '91' . $to;
        $status = SendSmsCurl2($templateid, $to, $message);
        SendWhatsappMessage2($to, $otp);
        //SMS
        $output = array('statusCode' => 201, 'message' => 'OTP successfuly sent to your mobile');
        http_response_code(201);
        header('Content-Type: application/json');
        echo json_encode($output);
        exit;
    } else {
        ///GUEST USER
        $OtpID = NextID('iOTPID', 'otp');
        $dtTo = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        $otp = GenerateRandomCode('4', 'vOTP', 'otp');
        sql_query("update otp set cUsed='X' where vPhone='$mobile' "); //Deactivate previous OTP

        $otpCount = checkOTPLimit($mobile, $RESTRICT_TIME_HOURS);
        if ($otpCount >= $NUMBER_OF_ATTEMPTS) {
            $output = array('statusCode' => 403, 'message' => 'You have exceeded the maximum number of OTP attempts. Please try again after some time.');
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode($output);
            exit;
        }

        sql_query("INSERT INTO otp(iOTPID,dtAdded,vCode,cAdded_RefType,iAdded_UserID,cType,iUserID,vOTP,vPhone,dtFrom,dtTo,cUsed)  VALUES ('$OtpID','$TIME','$code','L','0','A','0','$otp','$mobile','$TIME','$dtTo','N')", "Insert into Otp table");

        $message = urlencode('Dear Guest, To access your account on DeltinOne, please use ' . $otp . ' as your one-time password (OTP). Best regards, Deltin wPYrBplEnt1');
        $templateid = '1307175128414225156';
        $to = $mobile;
        if (strlen($to) == 10) $to = '91' . $to;
        $status = SendSmsCurl2($templateid, $to, $message);
        SendWhatsappMessage2($to, $otp);
        //SMS
        $output = array('statusCode' => 202, 'message' => 'OTP successfuly sent to your mobile');
        http_response_code(202);
        header('Content-Type: application/json');
        echo json_encode($output);
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