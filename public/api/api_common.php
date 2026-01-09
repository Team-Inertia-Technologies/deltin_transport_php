<?php

/**
 * API Include
 **/
function api_include($path, $exclude = array())
{
    $files = array_map(function ($val) {
        return realpath($val);
    }, array_diff(glob("$path/*.php"), array_map(function ($val) use ($path) {
        return "$path/$val";
    }, $exclude)));
    foreach ($files as $file)
        if (is_file($file))
            include_once($file);
}

/**
 * API Numeric Input
 **/
function api_in_get_numeric($var_name, $def = 0)
{
    $var = isset($_GET[$var_name]) ? $_GET[$var_name] : $def;
    return (!empty($var) && is_numeric($var)) ? $var : $def;
}

/**
 * API String Input
 **/
function api_in_get_str($var_name, $def = '')
{
    $var = isset($_GET[$var_name]) ? $_GET[$var_name] : $def;
    $var = trim($var);
    return (!empty($var)) ? $var : $def;
}

/**
 * API Numeric Input
 **/
function api_in_post_numeric($var_name, $def = 0)
{
    $var = isset($_POST[$var_name]) ? $_POST[$var_name] : $def;
    return (!empty($var) && is_numeric($var)) ? $var : $def;
}

/**
 * API String Input
 **/
function api_in_post_str($var_name, $def = '')
{
    $var = isset($_POST[$var_name]) ? $_POST[$var_name] : $def;
    $var = trim($var);
    return (!empty($var)) ? $var : $def;
}

/**
 * API TimeStamp
 **/
function api_timestamp_str($DateTime = null)
{
    $timestamp = strtotime(isset($DateTime) ? $DateTime : null);
    if (!isset($DateTime)) {
        $DateTime = date(DateTimeInterface::RFC3339_EXTENDED);
    } else {
        if ($timestamp !== false) {
            $DateTime = date(DateTimeInterface::RFC3339_EXTENDED, $timestamp);
        } else {
            $DateTime = null;
        }
    }
    return $DateTime;
}

/**
 * API Send HTTP Header Status
 **/
function api_sendHTTPstatus($num)
{
    $http = array(
        100 => 'HTTP/1.1 100 Continue',
        101 => 'HTTP/1.1 101 Switching Protocols',
        200 => 'HTTP/1.1 200 OK',
        201 => 'HTTP/1.1 201 Created',
        202 => 'HTTP/1.1 202 Accepted',
        203 => 'HTTP/1.1 203 Non-Authoritative Information',
        204 => 'HTTP/1.1 204 No Content',
        205 => 'HTTP/1.1 205 Reset Content',
        206 => 'HTTP/1.1 206 Partial Content',
        300 => 'HTTP/1.1 300 Multiple Choices',
        301 => 'HTTP/1.1 301 Moved Permanently',
        302 => 'HTTP/1.1 302 Found',
        303 => 'HTTP/1.1 303 See Other',
        304 => 'HTTP/1.1 304 Not Modified',
        305 => 'HTTP/1.1 305 Use Proxy',
        307 => 'HTTP/1.1 307 Temporary Redirect',
        400 => 'HTTP/1.1 400 Bad Request',
        401 => 'HTTP/1.1 401 Unauthorized',
        402 => 'HTTP/1.1 402 Payment Required',
        403 => 'HTTP/1.1 403 Forbidden',
        404 => 'HTTP/1.1 404 Not Found',
        405 => 'HTTP/1.1 405 Method Not Allowed',
        406 => 'HTTP/1.1 406 Not Acceptable',
        407 => 'HTTP/1.1 407 Proxy Authentication Required',
        408 => 'HTTP/1.1 408 Request Time-out',
        409 => 'HTTP/1.1 409 Conflict',
        410 => 'HTTP/1.1 410 Gone',
        411 => 'HTTP/1.1 411 Length Required',
        412 => 'HTTP/1.1 412 Precondition Failed',
        413 => 'HTTP/1.1 413 Request Entity Too Large',
        414 => 'HTTP/1.1 414 Request-URI Too Large',
        415 => 'HTTP/1.1 415 Unsupported Media Type',
        416 => 'HTTP/1.1 416 Requested Range Not Satisfiable',
        417 => 'HTTP/1.1 417 Expectation Failed',
        500 => 'HTTP/1.1 500 Internal Server Error',
        501 => 'HTTP/1.1 501 Not Implemented',
        502 => 'HTTP/1.1 502 Bad Gateway',
        503 => 'HTTP/1.1 503 Service Unavailable',
        504 => 'HTTP/1.1 504 Gateway Time-out',
        505 => 'HTTP/1.1 505 HTTP Version Not Supported',
    );

    header($http[$num]);
}

/**
 * API JSON Pretty Print Response
 **/
function api_json_pretty_print_response($response, $first_decode = false)
{
    return json_encode($first_decode ? json_decode($response) : $response, JSON_PRETTY_PRINT);
}

/**
 * API Clean Date
 **/
function api_cleanDate($date)
{
    $date = preg_replace('/[^-\/\d]/i', '', $date);
    /* $date = preg_match("/(\d{2}[-\/]\d{2}[-\/]\d{4}|\d{4}[-\/]\d{2}[-\/]\d{2})/i", $date, $match)
            ?date("d/m/Y",strtotime($match[0])):$date; */
    $date = date("d/m/Y", strtotime($date));
    return $date;
}

/**
 * API Create Dir
 **/
function api_createDir($dir, $perm = 0777)
{
    if (file_exists($dir)) {
        if (!is_writable($dir))
            chmod($dir, $perm);
    } else {
        mkdir($dir, $perm, true);
    }
}

/**
 * API Save File from Base64
 **/
function SaveFileBase64($fileBase64, $folderPath, $fileName)
{
    $NOW3 = NOW3;

    $image_parts = explode(";base64,", $fileBase64);
    $image_type_aux = explode("image/", $image_parts[0]);
    $image_type = $image_type_aux[1];

    $image_base64 = base64_decode($image_parts[1]);
    $fileName = "{$fileName}_{$NOW3}.{$image_type}";

    $file = $folderPath . $fileName;
    file_put_contents($file, $image_base64);

    return $fileName;
}

function verifyPropAssoc($user_id, $property_id)
{
    $user_id = intval($user_id);
    $property_id = intval($property_id);
    $cnt = GetXFromYID("SELECT COUNT(*) FROM users_property_assoc WHERE iUserID={$user_id} AND iPropertyID={$property_id} ");
    return (!empty($cnt) && $cnt > 0) ? true : false;
}

function verifyTokenSup()
{
    $token_sup = isset($_POST['token_sup']) ? strtoupper($_POST['token_sup']) : '';
    $user = GetDataFromQuery("SELECT * FROM users WHERE vToken='{$token_sup}' AND vToken!='' AND vToken IS NOT NULL LIMIT 1");

    if (!empty($user[0])) {
        $user = $user[0];

        $user_sup_id = intval($user->iUserID);
        $user_sup_data = $user;
        $user_sup_property_id = GetIDString("SELECT iPropertyID FROM users_property_assoc WHERE iUserID={$user_sup_data->iUserID}");

        return [
            'verified' => true,
            'user_sup_id' => $user_sup_id,
            'user_sup_data' => $user_sup_data,
            'user_sup_property_id' => $user_sup_property_id
        ];
    } else {
        $errorCode = 404;
        api_sendHTTPstatus($errorCode);
        return [
            'verified' => false,
            'error' => [
                "description" => "TokenVerify: Supervisor User not found",
                "force_logout" => true
            ],
            "StatusCode" => $errorCode
        ];
    }
}

function FetchSessionDate($property_id, $counter_id = 0)
{
    $arr = array('PROPERTY_SESSION_DATE' => '', 'PROPERTY_SESSION_STATUS' => '', 'COUNTER_SESSION_ID' => '', 'COUNTER_SESSION_DATE' => '', 'COUNTER_SESSION_STATUS' => '');

    $_pq = 'select dSessionDate, cStatus from property_vesselclosing where iPropertyID=' . $property_id . ' order by dSessionDate desc limit 1';
    $_pr = sql_query($_pq, '');
    if (sql_num_rows($_pr))
        list($PROPERTY_SESSION_DATE, $PROPERTY_SESSION_STATUS) = sql_fetch_row($_pr);
    else {
        $PROPERTY_START_SESSIONDATE = TODAY;
        $_pq = 'select iCompanyID, dStart from gen_property where iPropertyID=' . $property_id;
        $_pr = sql_query($_pq, '');
        if (sql_num_rows($_pr)) {
            list($PORPERTY_COMPANY_ID, $PROPERTY_START_DATE) = sql_fetch_row($_pr);
            if (!empty($PROPERTY_START_DATE) && $PROPERTY_START_DATE != '0000-00-00')
                $PROPERTY_START_SESSIONDATE = $PROPERTY_START_DATE;
        }

        LockTable('property_vesselclosing');
        $iPropertyVesselID = NextID('iPropertyVesselID', 'property_vesselclosing');
        sql_query("INSERT INTO property_vesselclosing values ('$iPropertyVesselID', '$PORPERTY_COMPANY_ID', '$property_id', '" . NOW . "', '$PROPERTY_START_SESSIONDATE', NULL, NULL, 0, NULL, 0, 'N', NULL, 'A')");
        UnlockTable();

        $PROPERTY_SESSION_DATE = $PROPERTY_START_SESSIONDATE;
        $PROPERTY_SESSION_STATUS = 'A';
    }

    $COUNTER_SESSION_ID = $COUNTER_SESSION_DATE = $COUNTER_SESSION_STATUS = '';
    if (!empty($counter_id)) {
        $_cq = 'select iSessCID, dSessionDate, cStatus from session_closing where iPropertyID=' . $property_id . ' and iCounterID=' . $counter_id . ' order by dSessionDate desc limit 1'; // and dSessionDate>="'.$PROPERTY_SESSION_DATE.'"
        $_cr = sql_query($_cq, '');
        if (sql_num_rows($_cr))
            list($COUNTER_SESSION_ID, $COUNTER_SESSION_DATE, $COUNTER_SESSION_STATUS) = sql_fetch_row($_cr);
        else {
            LockTable('session_closing');
            $iSessCID = NextID('iSessCID', 'session_closing');
            sql_query("INSERT INTO session_closing values ('$iSessCID', '" . NOW . "', '$property_id', '$PROPERTY_SESSION_DATE', '$counter_id', 0, 0, 0, 0, 0, '', '" . NOW . "', NULL, 0, NULL, 0, NULL, 'A')");
            UnlockTable();

            $COUNTER_SESSION_ID = $iSessCID;
            $COUNTER_SESSION_DATE = $PROPERTY_START_SESSIONDATE;
            $COUNTER_SESSION_STATUS = 'A';
        }
    }

    $arr = array('PROPERTY_SESSION_DATE' => $PROPERTY_SESSION_DATE, 'PROPERTY_SESSION_STATUS' => $PROPERTY_SESSION_STATUS, 'COUNTER_SESSION_ID' => $COUNTER_SESSION_ID, 'COUNTER_SESSION_DATE' => $COUNTER_SESSION_DATE, 'COUNTER_SESSION_STATUS' => $COUNTER_SESSION_STATUS);

    return $arr;
}
// function generateVehicleCode($vehicleId) {
//     // Map digits 0-9 to letters A-J
//     $map = ['A','B','C','D','E','F','G','H','I','J'];

//     // Convert vehicle ID last digit to mapped ASCII letter
//     $lastDigit = substr((string)$vehicleId, -1);
//     $mappedLetter = $map[$lastDigit];

//     // First 3 random alphabets
//     $first3 = '';
//     for ($i = 0; $i < 3; $i++) {
//         $first3 .= chr(rand(65, 90)); // A-Z
//     }

//     // Last 3 random digits
//     $last3 = str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);

//     return $first3 . $mappedLetter . $last3;
// }

// function getTimeWindowMinutes()
// {
//     $sql = "SELECT vValue FROM sys_settings WHERE vCode = 'QR_CODE_SCAN_WINDOW' LIMIT 1";
//     $res = sql_query($sql);

//     if (sql_num_rows($res) == 0) {
//         return 30; // fallback default
//     }

//     $row = sql_fetch_assoc($res);
//     return intval($row['vValue']);
// }

function checkVehicleAvailability($vehicleId, $datetime)
{
    $vehicleId = intval($vehicleId);
    $datetime = db_input($datetime);

    if ($vehicleId == 0 || empty($datetime)) {
        return [
            "error" => ["message" => "Missing vehicleId or datetime"],
            "statusCode" => 400
        ];
    }

    // Check vehicle exists
    $vehSql = "SELECT iVehicleID 
               FROM vehicle 
               WHERE iVehicleID = $vehicleId AND cStatus = 'A'";
    $vehRes = sql_query($vehSql);

    if (sql_num_rows($vehRes) == 0) {
        return [
            "error" => ["message" => "Vehicle not found"],
            "statusCode" => 400
        ];
    }

    // Convert datetime to timestamp
    $reqTimestamp = strtotime($datetime);

    // Get window minutes
    //$window = getTimeWindowMinutes();
    $window_pre = intval(GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode = 'QR_SCAN_WINDOW_PRE'"));
    $window_post = intval(GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode = 'QR_SCAN_WINDOW_POST'"));


    $minWindow = date('Y-m-d H:i:s', strtotime("-{$window_pre} minutes", $reqTimestamp));
    $maxWindow = date('Y-m-d H:i:s', strtotime("+{$window_post} minutes", $reqTimestamp));

    // Check vehicle availability using association table
    $tripSql = "SELECT t.iTripID, t.dtTrip 
                FROM st_trips t
                INNER JOIN st_trip_vehicle_assoc tva ON t.iTripID = tva.iTripID
                WHERE tva.iVehicleID = $vehicleId 
                AND tva.cStatus = 'A'
                AND t.cStatus = 'A'
                AND t.dtTrip BETWEEN '$minWindow' AND '$maxWindow'";

    $tripRes = sql_query($tripSql);

    if (sql_num_rows($tripRes) == 0) {
        return [
            "error" => ["message" => "No trip found"],
            "statusCode" => 400
        ];
    }

    $tripRow = sql_fetch_assoc($tripRes);

    return [
        "data" => [
            "message" => "Vehicle available",
            "tripId" => $tripRow['iTripID'],
            "tripTime" => $tripRow['dtTrip']
        ],
        "statusCode" => 200
    ];
}

function checkStaffRequest($staffId, $datetime)
{
    $staffId = intval($staffId);
    $datetime = db_input($datetime);

    if ($staffId == 0 || empty($datetime)) {
        return [
            "error" => ["message" => "Missing staffId or datetime"],
            "statusCode" => 400
        ];
    }

    // Convert incoming datetime to timestamp
    $reqTimestamp = strtotime($datetime);

    // Dynamic window (in minutes)
    $window_pre = intval(GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode = 'QR_SCAN_WINDOW_PRE'"));
    $window_post = intval(GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode = 'QR_SCAN_WINDOW_POST'"));
    $TODAY = TODAY;

    // Fetch all active requests for staff for today
    $staffSql = "
        SELECT dPickup, tPickup
        FROM st_request
        WHERE iStaffID = $staffId
          AND cStatus = 'A'
          AND dPickup = '$TODAY'
    ";

    $staffRes = sql_query($staffSql);

    while ($row = sql_fetch_assoc($staffRes)) {

        // Build existing datetime timestamp
        $existingTimestamp = strtotime($row['dPickup'] . ' ' . $row['tPickup']);

        // Calculate min & max time window (based on EXISTING)
        $minWindowTs = $existingTimestamp - ($window_pre * 60);
        $maxWindowTs = $existingTimestamp + ($window_post * 60);

        // // Debug (remove later)
        // echo date('Y-m-d H:i:s', $reqTimestamp) . "\n";
        // echo date('Y-m-d H:i:s', $minWindowTs) . "\n";
        // echo date('Y-m-d H:i:s', $maxWindowTs) . "\n";
// exit;
        // Compare TIMESTAMPS, NOT STRINGS

        if ($reqTimestamp >= $minWindowTs && $reqTimestamp <= $maxWindowTs) {
            return [
                "data" => [
                    "message" => "Valid — staff has a request"
                ],
                "statusCode" => 200
            ];
        }
    }

    return [
        "error" => [
            "message" => "No request found"
        ],
        "statusCode" => 400
    ];
}

/**
 * Log QR Scan Errors to st_log_qrscan table
 * @param int $staffId - Staff ID
 * @param string $description - Error description
 * @param string $status - Status (default 'E' for Error)
 * @return bool - True if logged successfully, false otherwise
 */
function logQRScanError($staffId, $description, $status = 'E')
{
    $staffId = intval($staffId);
    $description = db_input($description);
    $status = db_input($status);

    // Get client IP address
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    $ip = db_input($ip);

    $now = NOW;

    // Insert log entry
    LockTable('st_log_qrscan');
    $logId = NextID('iLogQRSID', 'st_log_qrscan');
    $insertSql = "INSERT INTO st_log_qrscan (iLogQRSID, iStaffID, vDesc, dtAdded, vIP, cStatus) 
                  VALUES ($logId, $staffId, '$description', '$now', '$ip', '$status')";
    $result = sql_query($insertSql);
    UnlockTable();

    return $result ? true : false;
}

/**
 * Check if user has access to a specific module
 * @param int $user_id - User ID
 * @param string $module_code - Module code to check access for
 * @return bool - True if user has access, false otherwise
 */
function checkUserModuleAccess($user_id, $module_code)
{
    $user_id = intval($user_id);
    $module_code = db_input($module_code);

    if ($user_id <= 0 || empty($module_code)) {
        return false;
    }

    // Get user level first
    $user_level_query = "SELECT iLevel FROM users WHERE iUserID = $user_id AND cStatus = 'A'";
    $user_level_result = sql_query($user_level_query);

    if (sql_num_rows($user_level_result) == 0) {
        return false;
    }

    $user_row = sql_fetch_assoc($user_level_result);
    $user_level = intval($user_row['iLevel']);

    // Check if user has access to the module based on their level
    $access_query = "SELECT COUNT(*) as access_count 
                     FROM module as m 
                     JOIN module_level_assoc as ma ON m.iModuleID = ma.iModuleID 
                     WHERE ma.iLevelD = $user_level 
                     AND m.vCode = '$module_code' 
                     AND m.cStatus = 'A' 
                     AND ma.cType = 'FL'";

    $access_result = sql_query($access_query);

    if (sql_num_rows($access_result) > 0) {
        $access_row = sql_fetch_assoc($access_result);
        return intval($access_row['access_count']) > 0;
    }

    return false;
}

/**
 * Check if a trip already exists for the same route, date and time
 * @param int $routeID - Route ID
 * @param string $tripDateTime - Trip date and time (Y-m-d H:i:s format)
 * @return array - Array with duplicate check result
 */
function checkDuplicateTrip($routeID, $tripDateTime)
{
    $routeID = intval($routeID);
    $tripDateTime = db_input($tripDateTime);

    if ($routeID <= 0 || empty($tripDateTime)) {
        return [
            'duplicate_exists' => false,
            'existing_trips' => [],
            'message' => 'Invalid parameters'
        ];
    }

    // Find existing trips for the same route, date and time
    $duplicateCheckSql = "SELECT iTripID, dtTrip 
                         FROM st_trips 
                         WHERE iRouteID = $routeID 
                         AND dtTrip = '$tripDateTime' 
                         AND cStatus != 'X'";

    $duplicateRes = sql_query($duplicateCheckSql);

    if (sql_num_rows($duplicateRes) == 0) {
        return [
            'duplicate_exists' => false,
            'existing_trips' => [],
            'message' => 'No duplicate trips found'
        ];
    }

    $existingTrips = [];

    while ($row = sql_fetch_assoc($duplicateRes)) {
        $existingTrips[] = [
            'tripID' => intval($row['iTripID']),
            'tripDateTime' => $row['dtTrip']
        ];
    }

    return [
        'duplicate_exists' => true,
        'existing_trips' => $existingTrips,
        'message' => 'Trip already exists for this route, date and time'
    ];
}

// function sendFcmNotification($deviceToken, $name, $pic, $body, $RM_ID, $senderID)
// {
//     // Initialize Google Client
//     $client = new Google_Client();
//     $client->setAuthConfig('../deltin-one-firebase-adminsdk-fo0ep-80ce21da32.json');
//     $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

//     // Generate a fresh access token
//     $token = $client->fetchAccessTokenWithAssertion()['access_token'];

//     // Fetch unread messages between sender and receiver
//        $unreadMessagesQuery = "
//     SELECT iChatID, vText 
//     FROM chats 
//     WHERE iFrom_RefID = $RM_ID 
//       AND iTo_RefID = $senderID 
//       AND cFrom_RefType = 'R' 
//       AND cTo_RefType = 'M' 
//       AND cRead = 'N' 
//     ORDER BY dtSent DESC 
//     LIMIT 4";

//     $result = sql_query($unreadMessagesQuery);
//     $messages = [];

//     if ($result) {
//         while ($row = sql_fetch_assoc($result)) {
//             $messages[] = !empty($row['vText']) ? db_output2($row['vText']) : '';
//         }
//     }

//     // $messagesText = implode("\n", $messages);
//     $messages = array_reverse($messages);
//     $pic = !empty($pic) ? $pic : '';
//     $payload = [
//         'message' => [
//             'token' => $deviceToken,

//             'data' => [
//                 'type' => "chat",
//                 'senderName' => "$name",
//                 'senderID' => "$RM_ID",
//                 'profilePic' => "$pic",
//                 'messages' => json_encode($messages),

//             ],
// 'android' => [
//                 'priority' => 'high',
//             ],
//             'apns' => [
//                 'headers' => [
//                     'apns-priority' => '5',
//                     'apns-push-type' => 'background',
//                 ],
//                 'payload' => [
//                     'aps' => [
//                         'content-available' => 1, // important for background/killed
//                     ],
//                 ],
//             ],
//     ];

//     // Define the FCM URL for HTTP v1 API
//     $fcmUrl = "https://fcm.googleapis.com/v1/projects/deltin-one/messages:send";

//     // Use cURL to send the request
//     $ch = curl_init();
//     curl_setopt($ch, CURLOPT_URL, $fcmUrl);
//     curl_setopt($ch, CURLOPT_POST, 1);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_HTTPHEADER, [
//         'Authorization: Bearer ' . $token,
//         'Content-Type: application/json',
//     ]);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

//     // Execute the request
//     $response = curl_exec($ch);
//     $success = $response !== false;

//     if (!$success) {
//         error_log('cURL Error: ' . curl_error($ch));
//     } else {
//         error_log("Notification sent: " . $response);
//     }

//     // Close cURL
//     curl_close($ch);

//     return $success;
// }