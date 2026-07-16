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

// Aditi / Uffizio tracking endpoints (stage)
$ADITI_BASE_URL = 'https://13.126.244.90/webservice';
$ADITI_PROJECT_ID = 37;
$ADITI_USERNAME = 'welesleyphilip@deltin.com';
$ADITI_PASSWORD = 'welesley@123';

/**
 * Call Aditi webservice via cURL
 */
function aditiHttpPost($url, $payload, $headers = [])
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $httpHeaders = array_merge(['Content-Type: application/json'], $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return [
            'ok' => false,
            'httpCode' => $httpCode,
            'error' => $curlErr,
            'data' => null
        ];
    }

    $decoded = json_decode($response, true);
    return [
        'ok' => true,
        'httpCode' => $httpCode,
        'error' => null,
        'raw' => $response,
        'data' => $decoded
    ];
}

/**
 * Generate Aditi access token and store in STAFF_TRACKING_TOKEN
 */
function generateAditiTrackingToken($baseUrl, $username, $password)
{
    $url = $baseUrl . '?token=generateAccessToken';
    $result = aditiHttpPost($url, [
        'username' => $username,
        'password' => $password
    ]);

    if (!$result['ok'] || empty($result['data'])) {
        return [
            'success' => false,
            'token' => '',
            'message' => $result['error'] ?: 'Failed to generate tracking token'
        ];
    }

    $data = $result['data'];
    $newToken = $data['token'] ?? '';

    if (empty($newToken)) {
        $msg = $data['message'] ?? ($data['Message'] ?? 'Token generation failed');
        return [
            'success' => false,
            'token' => '',
            'message' => $msg
        ];
    }

    sql_query("UPDATE sys_settings SET vValue = '" . db_input($newToken) . "' WHERE vCode = 'STAFF_TRACKING_TOKEN'");

    return [
        'success' => true,
        'token' => $newToken,
        'message' => 'OK'
    ];
}

/**
 * Get stored token; generate if empty
 */
function getAditiTrackingToken($baseUrl, $username, $password, $forceRefresh = false)
{
    $storedToken = GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode='STAFF_TRACKING_TOKEN'");
    $storedToken = trim((string) $storedToken);

    if ($forceRefresh || $storedToken === '') {
        return generateAditiTrackingToken($baseUrl, $username, $password);
    }

    return [
        'success' => true,
        'token' => $storedToken,
        'message' => 'OK'
    ];
}

/**
 * Whether live-data response means token must be refreshed
 */
function isAditiTokenExpired($data)
{
    if (!is_array($data)) {
        return false;
    }

    $message = strtolower(trim((string) ($data['message'] ?? ($data['Message'] ?? ''))));
    if ($message === '') {
        return false;
    }

    return (
        strpos($message, 'refresh token expired') !== false
        || strpos($message, 'invalid token') !== false
    );
}

/**
 * Normalize vehicle number for matching
 */
function normalizeVehicleNo($number)
{
    return strtoupper(preg_replace('/\s+/', '', (string) $number));
}

/**
 * Fetch live vehicle data from Aditi (retries once on expired token)
 */
function fetchAditiLiveData($baseUrl, $projectId, $username, $password, $vehicleNosCsv)
{
    $tokenResult = getAditiTrackingToken($baseUrl, $username, $password, false);
    if (!$tokenResult['success']) {
        return [
            'success' => false,
            'message' => $tokenResult['message'],
            'vehicles' => []
        ];
    }

    $authToken = $tokenResult['token'];
    $url = $baseUrl . '?token=getTokenBaseLiveData&ProjectId=' . intval($projectId);
    $payload = ['vehicle_nos' => $vehicleNosCsv];

    $result = aditiHttpPost($url, $payload, ['auth-code: ' . $authToken]);

    if (!$result['ok']) {
        return [
            'success' => false,
            'message' => $result['error'] ?: 'Live data request failed',
            'vehicles' => []
        ];
    }

    $data = $result['data'];

    // Token expired / invalid → regenerate and retry once
    if (isAditiTokenExpired($data)) {
        $tokenResult = getAditiTrackingToken($baseUrl, $username, $password, true);
        if (!$tokenResult['success']) {
            return [
                'success' => false,
                'message' => $tokenResult['message'],
                'vehicles' => []
            ];
        }

        $authToken = $tokenResult['token'];
        $result = aditiHttpPost($url, $payload, ['auth-code: ' . $authToken]);
        if (!$result['ok']) {
            return [
                'success' => false,
                'message' => $result['error'] ?: 'Live data request failed',
                'vehicles' => []
            ];
        }
        $data = $result['data'];

        if (isAditiTokenExpired($data)) {
            return [
                'success' => false,
                'message' => $data['message'] ?? 'Tracking token expired',
                'vehicles' => []
            ];
        }
    }

    // Rate limit / other Aditi errors
    if (isset($data['root']['error'])) {
        return [
            'success' => false,
            'message' => $data['root']['error'],
            'vehicles' => []
        ];
    }

    if (isset($data['result']) && intval($data['result']) === 0) {
        return [
            'success' => false,
            'message' => $data['message'] ?? ($data['Message'] ?? 'No live data found'),
            'vehicles' => []
        ];
    }

    $vehicleData = $data['root']['VehicleData'] ?? [];
    if (!is_array($vehicleData)) {
        $vehicleData = [];
    }

    return [
        'success' => true,
        'message' => 'OK',
        'vehicles' => $vehicleData
    ];
}

switch ($mode) {

    // ===================== CASE: LIVE =====================
    case 'LIVE':
        $tripId = intval($_REQUEST['tripId'] ?? 0);
        $vehiclesInput = $_REQUEST['vehicles'] ?? [];

        if ($tripId <= 0) {
            echo json_encode([
                "error" => ["message" => "Missing or invalid tripId"],
                "statusCode" => 400
            ]);
            exit;
        }

        if (!is_array($vehiclesInput) || count($vehiclesInput) === 0) {
            echo json_encode([
                "error" => ["message" => "vehicles array is required"],
                "statusCode" => 400
            ]);
            exit;
        }

        // Ensure this staff has an active request on the trip
        $reqSql = "SELECT r.iTrReqID, t.dtTrip
                   FROM st_request r
                   INNER JOIN st_trips t ON r.iTripID = t.iTripID
                   WHERE r.iStaffID = $user_id
                   AND r.iTripID = $tripId
                   AND r.cStatus = 'A'
                   AND t.cStatus IN ('A', 'C')
                   LIMIT 1";
        $reqRes = sql_query($reqSql);

        if (sql_num_rows($reqRes) == 0) {
            echo json_encode([
                "error" => ["message" => "Trip request not found"],
                "statusCode" => 404
            ]);
            exit;
        }

        $reqRow = sql_fetch_assoc($reqRes);
        $tripDateTime = $reqRow['dtTrip'];
        $tripTs = strtotime($tripDateTime);
        $nowTs = time();

        $preMinutes = intval(GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode='STAFF_TRACKING_TIME_WINDOW_PRE'"));
        $postMinutes = intval(GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode='STAFF_TRACKING_TIME_WINDOW_POST'"));
        if ($preMinutes <= 0) {
            $preMinutes = 30;
        }
        if ($postMinutes <= 0) {
            $postMinutes = 60;
        }

        $windowStart = $tripTs - ($preMinutes * 60);
        $windowEnd = $tripTs + ($postMinutes * 60);
        $inWindow = ($nowTs >= $windowStart && $nowTs <= $windowEnd);

        if (!$inWindow) {
            echo json_encode([
                "data" => [
                    "trackingAvailable" => false,
                    "tripId" => $tripId,
                    "message" => "Tracking available from " . $preMinutes . " min before to " . $postMinutes . " min after trip time",
                    "window" => [
                        "preMinutes" => $preMinutes,
                        "postMinutes" => $postMinutes,
                        "tripTime" => date('Y-m-d H:i:s', $tripTs),
                        "startsAt" => date('Y-m-d H:i:s', $windowStart),
                        "endsAt" => date('Y-m-d H:i:s', $windowEnd)
                    ],
                    "vehicles" => []
                ],
                "statusCode" => 200
            ]);
            exit;
        }

        // Build vehicle map from request + local category names
        $vehicleMap = []; // normalized number => {id, number, type}
        $vehicleNos = [];
        $vehicleIds = [];

        foreach ($vehiclesInput as $v) {
            $id = intval($v['id'] ?? 0);
            $number = trim((string) ($v['number'] ?? ''));
            if ($number === '') {
                continue;
            }
            $norm = normalizeVehicleNo($number);
            $vehicleMap[$norm] = [
                'id' => $id,
                'number' => $number,
                'type' => ''
            ];
            $vehicleNos[] = $number;
            if ($id > 0) {
                $vehicleIds[] = $id;
            }
        }

        if (empty($vehicleNos)) {
            echo json_encode([
                "error" => ["message" => "No valid vehicle numbers provided"],
                "statusCode" => 400
            ]);
            exit;
        }

        // Enrich with category from our vehicle master
        if (!empty($vehicleIds)) {
            $idsStr = implode(',', array_map('intval', $vehicleIds));
            $catSql = "SELECT v.iVehicleID, v.vRnum, vc.vName as categoryName
                       FROM vehicle v
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       WHERE v.iVehicleID IN ($idsStr)";
            $catRes = sql_query($catSql);
            while ($catRow = sql_fetch_assoc($catRes)) {
                $norm = normalizeVehicleNo($catRow['vRnum']);
                if (isset($vehicleMap[$norm])) {
                    $vehicleMap[$norm]['type'] = db_output2($catRow['categoryName'] ?? '');
                    $vehicleMap[$norm]['id'] = (int) $catRow['iVehicleID'];
                }
            }
        } else {
            // Fallback lookup by registration number
            $quoted = [];
            foreach ($vehicleNos as $num) {
                $quoted[] = "'" . db_input($num) . "'";
            }
            $catSql = "SELECT v.iVehicleID, v.vRnum, vc.vName as categoryName
                       FROM vehicle v
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       WHERE v.vRnum IN (" . implode(',', $quoted) . ")";
            $catRes = sql_query($catSql);
            while ($catRow = sql_fetch_assoc($catRes)) {
                $norm = normalizeVehicleNo($catRow['vRnum']);
                if (isset($vehicleMap[$norm])) {
                    $vehicleMap[$norm]['type'] = db_output2($catRow['categoryName'] ?? '');
                    $vehicleMap[$norm]['id'] = (int) $catRow['iVehicleID'];
                }
            }
        }

        $liveResult = fetchAditiLiveData(
            $ADITI_BASE_URL,
            $ADITI_PROJECT_ID,
            $ADITI_USERNAME,
            $ADITI_PASSWORD,
            implode(',', $vehicleNos)
        );

        if (!$liveResult['success']) {
            echo json_encode([
                "data" => [
                    "trackingAvailable" => true,
                    "tripId" => $tripId,
                    "message" => $liveResult['message'],
                    "vehicles" => []
                ],
                "statusCode" => 200
            ]);
            exit;
        }

        // Index Aditi vehicles by normalized number
        $liveByNumber = [];
        foreach ($liveResult['vehicles'] as $liveVeh) {
            $liveNo = normalizeVehicleNo($liveVeh['Vehicle_No'] ?? '');
            if ($liveNo !== '') {
                $liveByNumber[$liveNo] = $liveVeh;
            }
        }

        $outputVehicles = [];
        foreach ($vehicleMap as $norm => $meta) {
            $liveVeh = $liveByNumber[$norm] ?? null;
            $type = $meta['type'];
            if ($type === '' && !empty($liveVeh['Vehicletype'])) {
                $type = $liveVeh['Vehicletype'];
            }

            $number = $meta['number'];
            $label = $type !== '' ? ($number . ' | ' . $type) : $number;

            if ($liveVeh === null) {
                $outputVehicles[] = [
                    "id" => $meta['id'],
                    "number" => $number,
                    "type" => $type,
                    "label" => $label,
                    "live" => false,
                    "status" => "",
                    "location" => "",
                    "trackingText" => "Location unavailable",
                    "lat" => null,
                    "lng" => null,
                    "speed" => null,
                    "poi" => ""
                ];
                continue;
            }

            $location = trim((string) ($liveVeh['Location'] ?? ''));
            $poi = trim((string) ($liveVeh['POI'] ?? ''));
            if ($poi === '--') {
                $poi = '';
            }

            // Prefer POI when it looks like a landmark; else full Location
            $displayLocation = $location;
            if ($poi !== '' && $poi !== $location) {
                // Keep location as primary (matches UI address-style text)
                $displayLocation = $location !== '' ? $location : $poi;
            }

            $gpsOn = strtoupper(trim((string) ($liveVeh['GPS'] ?? ''))) === 'ON';
            $hasCoords = !empty($liveVeh['Latitude']) && !empty($liveVeh['Longitude']);
            $isLive = $gpsOn || $hasCoords || $displayLocation !== '';

            $outputVehicles[] = [
                "id" => $meta['id'],
                "number" => $number,
                "type" => $type,
                "label" => $label,
                "live" => $isLive,
                "status" => $isLive ? 'LIVE' : (string) ($liveVeh['Status'] ?? ''),
                "location" => $displayLocation,
                "trackingText" => $displayLocation !== '' ? $displayLocation : 'Location unavailable',
                "lat" => isset($liveVeh['Latitude']) ? (float) $liveVeh['Latitude'] : null,
                "lng" => isset($liveVeh['Longitude']) ? (float) $liveVeh['Longitude'] : null,
                "speed" => isset($liveVeh['Speed']) ? $liveVeh['Speed'] : null,
                "poi" => $poi,
                "vehicleStatus" => $liveVeh['Status'] ?? ''
            ];
        }

        echo json_encode([
            "data" => [
                "trackingAvailable" => true,
                "tripId" => $tripId,
                "refreshIntervalSec" => 60,
                "window" => [
                    "preMinutes" => $preMinutes,
                    "postMinutes" => $postMinutes,
                    "tripTime" => date('Y-m-d H:i:s', $tripTs),
                    "startsAt" => date('Y-m-d H:i:s', $windowStart),
                    "endsAt" => date('Y-m-d H:i:s', $windowEnd)
                ],
                "vehicles" => $outputVehicles
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
