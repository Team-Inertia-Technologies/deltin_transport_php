<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);
$mode = $_REQUEST['mode'] ?? '';
$TIME = NOW;
if ($mode == 'REGISTER_ONLOAD') {
    $staff = sql_query("SELECT vMobile, vCode FROM staff WHERE cStatus = 'A'");

    $routeStopsQuery = "SELECT r.iRouteID, r.vName as routeName, s.iStopID, s.vName as stopName 
                        FROM st_route r 
                        LEFT JOIN st_route_stops s ON r.iRouteID = s.iRouteID 
                        WHERE r.cStatus = 'A' AND s.cStatus ='A'
                        ORDER BY r.iRouteID, s.iStopID";
    $routeStopsResult = sql_query($routeStopsQuery);

    // $PHONE_ARR = [];
    $CODE_ARR = [];
    $routes = [];
    $currentRouteId = null;
    $currentRoute = null;

    // Process staff data
    while ($row = sql_fetch_assoc($staff)) {
        //  $PHONE_ARR[] = $row['vMobile'];
        $CODE_ARR[] = db_output2($row['vCode']);
    }

    // Process routes and stops data
    while ($row = sql_fetch_assoc($routeStopsResult)) {
        if ($currentRouteId !== $row['iRouteID']) {
            // Save previous route if exists
            if ($currentRoute !== null) {
                $routes[] = $currentRoute;
            }

            // Start new route
            $currentRouteId = $row['iRouteID'];
            $currentRoute = [
                "id" => (int) $row['iRouteID'],
                "name" => db_output2($row['routeName']),
                "pickups" => []
            ];
        }

        // Add stop to current route if stop exists
        if ($row['iStopID'] !== null) {
            $currentRoute["pickups"][] = [
                "id" => (int) $row['iStopID'],
                "name" => db_output2($row['stopName'])
            ];
        }
    }

    // Add the last route if exists
    if ($currentRoute !== null) {
        $routes[] = $currentRoute;
    }

    echo json_encode([
        "data" => [
            //  "mobileArr" => $PHONE_ARR,
            "codeArr" => $CODE_ARR,
            "routes" => $routes
        ],
        "statusCode" => 200
    ]);

    exit;
}

// ===================== CASE: ADD_STAFF =====================
else if ($mode == 'ADD_STAFF') {

    $vCode = db_input($request['code'] ?? '');
    $vName = db_input($request['name'] ?? '');
    $vMobile = db_input($request['mobile'] ?? '');
    $iRouteID = db_input($request['routeid'] ?? 0);
    $iStopID = db_input($request['stopid'] ?? 0);

    if (empty($vCode)) {
        echo json_encode([
            "error" => [
                "message" => "Staff code is required"
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    if (empty($vName)) {
        echo json_encode([
            "error" => [
                "message" => "Staff name is required"
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    if (empty($vMobile)) {
        echo json_encode([
            "error" => [
                "message" => "Mobile number is required"
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    // Check for duplicate vCode or vMobile
    $checkSql = "SELECT iStaffID, vCode, vMobile FROM staff WHERE (vCode = '$vCode' OR vMobile = '$vMobile') AND cStatus != 'X'";
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

    // Store registration data in session/temp table and send OTP instead of direct insert
    $OtpID = NextID('iOTPID', 'otp');
    $dtTo = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    $otp = GenerateRandomCode('4', 'vOTP', 'otp');

    // Deactivate previous OTPs for this mobile
    sql_query("UPDATE otp SET cUsed='X' WHERE vPhone='$vMobile'");

    // Check OTP limit
    $NUMBER_OF_ATTEMPTS = GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode='OTP_ATTEMPTS'");
    $RESTRICT_TIME_HOURS = GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode='OTP_RESTRICT_TIME_HOURS'");

    $otpCount = checkOTPLimit($vMobile, $RESTRICT_TIME_HOURS);

    if ($otpCount >= $NUMBER_OF_ATTEMPTS) {
        echo json_encode([
            "error" => [
                "message" => "You have exceeded the maximum number of OTP attempts. Please try again after some time."
            ],
            "statusCode" => 400
        ]);
        exit;
    }

    // Insert OTP for registration
    $code = '+91';
    sql_query("INSERT INTO otp(iOTPID,dtAdded,vCode,cAdded_RefType,iAdded_UserID,cType,iUserID,vOTP,vPhone,dtFrom,dtTo,cUsed) VALUES ('$OtpID','$TIME','$code','S','0','R','0','$otp','$vMobile','$TIME','$dtTo','N')", "Insert OTP for staff registration");

    // Send SMS
    $message = urlencode('Use code ' . $otp . ' to complete your registration for Deltin Transport. This OTP is valid for 5 minutes.');
    $templateid = '1707176249288519068';
    $to = $vMobile;
    if (strlen($to) == 10)
        $to = '91' . $to;
    SendSmsCurl2($templateid, $to, $message);
    echo $response;
    exit;

    echo json_encode([
        "statusCode" => 200,
        "message" => "OTP sent to your mobile number for registration verification",
        "data" => [
            "mobile" => db_output2($vMobile)
        ]
    ]);

    exit;
} else {
    echo json_encode([
        "error" => [
            "message" => "Invalid mode parameter"
        ],
        "statusCode" => 400
    ]);
    exit;
}

function checkOTPLimit($mobile, $HOURS)
{
    date_default_timezone_set('Asia/Calcutta');
    $HoursAgo = date('Y-m-d H:i:s', strtotime('-' . $HOURS . ' hours'));
    $OTP_COUNT = GetXFromYID("SELECT COUNT(*) as otp_count FROM otp WHERE vPhone ='$mobile' AND dtAdded >'$HoursAgo' ");
    return $OTP_COUNT;
}
