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
function aditiHttpPost($url, $payload = null, $headers = [])
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $httpHeaders = $headers;
    if ($payload !== null) {
        $httpHeaders[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    } else {
        // POST with empty body (token generation via query params)
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    }
    if (!empty($httpHeaders)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return [
            'ok' => false,
            'httpCode' => $httpCode,
            'error' => $curlErr,
            'data' => null,
            'raw' => $response
        ];
    }

    $decoded = json_decode($response, true);
    return [
        'ok' => true,
        'httpCode' => $httpCode,
        'error' => null,
        'raw' => $response,
        'data' => is_array($decoded) ? $decoded : null
    ];
}

/**
 * Pull token string from Aditi generateAccessToken response shapes:
 * - {"token":"..."}
 * - {"result":1,"data":{"token":"..."}}
 */
function extractAditiAccessToken($responseData)
{
    if (!is_array($responseData)) {
        return '';
    }

    if (!empty($responseData['token']) && is_string($responseData['token'])) {
        return trim($responseData['token']);
    }

    if (!empty($responseData['data']['token']) && is_string($responseData['data']['token'])) {
        return trim($responseData['data']['token']);
    }

    return '';
}

/**
 * Persist token into sys_settings.STAFF_TRACKING_TOKEN
 */
function saveAditiTrackingToken($token)
{
    $token = trim((string) $token);
    if ($token === '') {
        return false;
    }

    // Avoid CheckForXSS/db_input altering the long hex token; escape quotes only
    $safeToken = addslashes($token);
    $exists = GetXFromYID("SELECT iSettingID FROM sys_settings WHERE vCode='STAFF_TRACKING_TOKEN'");

    if ($exists) {
        $ok = sql_query("UPDATE sys_settings SET vValue = '$safeToken' WHERE vCode = 'STAFF_TRACKING_TOKEN'");
    } else {
        $nextId = NextID('iSettingID', 'sys_settings');
        $ok = sql_query("INSERT INTO sys_settings (iSettingID, cType, vCode, cData, vValue, cStatus, iGroupID, vDesc, cDisplay)
                         VALUES ($nextId, 'D', 'STAFF_TRACKING_TOKEN', 'V', '$safeToken', 'A', 0, 'Tracking system token', 'Y')");
    }

    return (bool) $ok;
}

/**
 * Generate Aditi access token and store in STAFF_TRACKING_TOKEN
 */
function generateAditiTrackingToken($baseUrl, $username, $password)
{
    // Working format (verified): JSON body
    $result = aditiHttpPost($baseUrl . '?token=generateAccessToken', [
        'username' => $username,
        'password' => $password
    ]);

    $newToken = '';
    if ($result['ok']) {
        $newToken = extractAditiAccessToken($result['data']);
    }

    if ($newToken === '') {
        $data = is_array($result['data']) ? $result['data'] : [];
        $msg = $data['message'] ?? ($data['Message'] ?? ($result['error'] ?: 'Token generation failed'));
        return [
            'success' => false,
            'token' => '',
            'message' => $msg
        ];
    }

    if (!saveAditiTrackingToken($newToken)) {
        return [
            'success' => false,
            'token' => '',
            'message' => 'Token generated but failed to save in STAFF_TRACKING_TOKEN'
        ];
    }

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
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $number));
}

/**
 * Extract VehicleData list from Aditi response
 */
function extractAditiVehicleData($data)
{
    if (!is_array($data)) {
        return [];
    }

    $vehicleData = $data['root']['VehicleData'] ?? null;
    if ($vehicleData === null) {
        return [];
    }

    // Single object vs list
    if (isset($vehicleData['Vehicle_No']) || isset($vehicleData['Location'])) {
        return [$vehicleData];
    }

    return is_array($vehicleData) ? $vehicleData : [];
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

    if (!is_array($data)) {
        return [
            'success' => false,
            'message' => 'Invalid live data response',
            'vehicles' => []
        ];
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

    return [
        'success' => true,
        'message' => 'OK',
        'vehicles' => extractAditiVehicleData($data)
    ];
}

/**
 * Build UI vehicle rows from request + optional Aditi live map
 */
function buildTrackingVehicles($vehicleMap, $liveByNumber)
{
    $outputVehicles = [];

    foreach ($vehicleMap as $norm => $meta) {
        $liveVeh = $liveByNumber[$norm] ?? null;
        $type = $meta['type'];
        $number = $meta['number'];
        $label = $type !== '' ? ($number . ' | ' . $type) : $number;

        $location = '';
        $altitude = '';
        $isLive = false;

        if (is_array($liveVeh)) {
            $location = trim((string) ($liveVeh['Location'] ?? ''));
            $altitude = trim((string) ($liveVeh['Altitude'] ?? ''));
            $isLive = ($location !== '');
        }

        $outputVehicles[] = [
            "id" => $meta['id'],
            "number" => $number,
            "type" => $type,
            "label" => $label,
            "live" => $isLive,
            "status" => $isLive ? 'LIVE' : '',
            "location" => $location,
            "altitude" => $altitude
        ];
    }

    return $outputVehicles;
}

/**
 * Persist live location JSON onto st_trip_vehicle_assoc for a trip+vehicle
 */
function saveTripVehicleLocationCache($tripId, $vehicleId, $liveVeh)
{
    $tripId = intval($tripId);
    $vehicleId = intval($vehicleId);
    if ($tripId <= 0 || $vehicleId <= 0 || !is_array($liveVeh)) {
        return false;
    }

    $json = json_encode($liveVeh);
    if ($json === false) {
        return false;
    }

    $safeLoc = sql_real_escape($json);
    $nowDt = date('Y-m-d H:i:s');

    return (bool) sql_query(
        "UPDATE st_trip_vehicle_assoc
         SET vCurrentLoc = '$safeLoc',
             dtLocLastUpdated = '$nowDt'
         WHERE iTripID = $tripId
           AND iVehicleID = $vehicleId
           AND cStatus IN ('A', 'C')"
    );
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

        // Build vehicle map from request
        $vehicleMap = [];
        $vehicleNos = [];
        $vehicleIds = [];

        foreach ($vehiclesInput as $v) {
            if (!is_array($v)) {
                continue;
            }
            $id = intval($v['id'] ?? 0);
            $number = trim((string) ($v['number'] ?? ''));
            $type = trim((string) ($v['type'] ?? ''));
            if ($number === '') {
                continue;
            }
            $norm = normalizeVehicleNo($number);
            $vehicleMap[$norm] = [
                'id' => $id,
                'number' => $number,
                'type' => $type
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

        // Fill type from vehicle master when missing
        if (!empty($vehicleIds)) {
            $idsStr = implode(',', array_map('intval', $vehicleIds));
            $catSql = "SELECT v.iVehicleID, v.vRnum, vc.vName as categoryName
                       FROM vehicle v
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       WHERE v.iVehicleID IN ($idsStr)";
            $catRes = sql_query($catSql);
            while ($catRow = sql_fetch_assoc($catRes)) {
                $norm = normalizeVehicleNo($catRow['vRnum']);
                if (isset($vehicleMap[$norm]) && $vehicleMap[$norm]['type'] === '') {
                    $vehicleMap[$norm]['type'] = db_output2($catRow['categoryName'] ?? '');
                }
                if (isset($vehicleMap[$norm]) && $vehicleMap[$norm]['id'] <= 0) {
                    $vehicleMap[$norm]['id'] = (int) $catRow['iVehicleID'];
                }
            }
        }

        if (!$inWindow) {
            echo json_encode([
                "data" => [
                    "trackingAvailable" => false,
                    "tripId" => $tripId,
                    "message" => "Tracking available from " . $preMinutes . " min before to " . $postMinutes . " min after trip time",
                    "vehicles" => buildTrackingVehicles($vehicleMap, [])
                ],
                "statusCode" => 200
            ]);
            exit;
        }

        // Load cached locations from st_trip_vehicle_assoc
        $LOC_CACHE_TTL_SEC = 10;
        $assocByNorm = [];
        $assocSql = "SELECT tva.iTVAID, tva.iVehicleID, tva.vCurrentLoc, tva.dtLocLastUpdated, v.vRnum
                     FROM st_trip_vehicle_assoc tva
                     INNER JOIN vehicle v ON tva.iVehicleID = v.iVehicleID
                     WHERE tva.iTripID = $tripId
                       AND tva.cStatus IN ('A', 'C')
                       AND tva.iVehicleID > 0";
        $assocRes = sql_query($assocSql);
        while ($assocRow = sql_fetch_assoc($assocRes)) {
            $norm = normalizeVehicleNo($assocRow['vRnum']);
            if ($norm === '') {
                continue;
            }
            $assocByNorm[$norm] = $assocRow;
            // Fill missing vehicle id from assoc
            if (isset($vehicleMap[$norm]) && $vehicleMap[$norm]['id'] <= 0) {
                $vehicleMap[$norm]['id'] = (int) $assocRow['iVehicleID'];
            }
        }

        $liveByNumber = [];
        $staleVehicleNos = [];

        foreach ($vehicleMap as $norm => $meta) {
            $assoc = $assocByNorm[$norm] ?? null;
            $usedCache = false;

            if ($assoc && !empty($assoc['vCurrentLoc']) && !empty($assoc['dtLocLastUpdated'])) {
                $updatedTs = strtotime($assoc['dtLocLastUpdated']);
                if ($updatedTs !== false) {
                    $ageSec = $nowTs - $updatedTs;
                    if ($ageSec >= 0 && $ageSec < $LOC_CACHE_TTL_SEC) {
                        $cached = json_decode($assoc['vCurrentLoc'], true);
                        if (is_array($cached)) {
                            $liveByNumber[$norm] = $cached;
                            $usedCache = true;
                        }
                    }
                }
            }

            if (!$usedCache) {
                // Strip spaces before sending to external tracking API
                $staleVehicleNos[] = preg_replace('/\s+/', '', $meta['number']);
            }
        }

        $liveMessage = '';
        if (!empty($staleVehicleNos)) {
            $liveResult = fetchAditiLiveData(
                $ADITI_BASE_URL,
                $ADITI_PROJECT_ID,
                $ADITI_USERNAME,
                $ADITI_PASSWORD,
                implode(',', $staleVehicleNos)
            );

            if ($liveResult['success']) {
                foreach ($liveResult['vehicles'] as $liveVeh) {
                    $liveNo = normalizeVehicleNo($liveVeh['Vehicle_No'] ?? '');
                    if ($liveNo === '' || !isset($vehicleMap[$liveNo])) {
                        continue;
                    }
                    $liveByNumber[$liveNo] = $liveVeh;
                    $vehId = (int) ($vehicleMap[$liveNo]['id'] ?? 0);
                    if ($vehId <= 0 && isset($assocByNorm[$liveNo])) {
                        $vehId = (int) $assocByNorm[$liveNo]['iVehicleID'];
                    }
                    if ($vehId > 0) {
                        saveTripVehicleLocationCache($tripId, $vehId, $liveVeh);
                    }
                }
            } else {
                $liveMessage = $liveResult['message'] ?? 'Live data unavailable';
            }
        }

        echo json_encode([
            "data" => [
                "trackingAvailable" => true,
                "tripId" => $tripId,
                "message" => $liveMessage,
                "refreshIntervalSec" => 60,
                "vehicles" => buildTrackingVehicles($vehicleMap, $liveByNumber)
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
