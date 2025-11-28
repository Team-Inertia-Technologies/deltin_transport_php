<?php
include "../../includes/common_api.php";
require_once(__DIR__ . '/../api_common.php');
require_once(__DIR__ . '/../../includes/libs/google_client/vendor/autoload.php');

use Google\Client as Google_Client;

function sendStaffVehicleNotification(
    string $deviceToken,
    string $vehicleNumber,
    array $details = [],
    array $serviceAccountConfig = null,
    int $timeoutCurl = 10
) {
    // Validate
    if (empty($deviceToken) || empty($vehicleNumber)) {
        return "Missing deviceToken or vehicleNumber";
    }

    // Build service account configuration from environment variables
    if (empty($serviceAccountConfig)) {
        // Check if all required Firebase constants are defined
        if (!defined('FIREBASE_PROJECT_ID') || !defined('FIREBASE_PRIVATE_KEY') || !defined('FIREBASE_CLIENT_EMAIL')) {
            return "Firebase credentials not configured in environment variables";
        }

        $serviceAccountConfig = [
            'type' => defined('FIREBASE_TYPE') ? FIREBASE_TYPE : 'service_account',
            'project_id' => FIREBASE_PROJECT_ID,
            'private_key_id' => defined('FIREBASE_PRIVATE_KEY_ID') ? FIREBASE_PRIVATE_KEY_ID : '',
            'private_key' => FIREBASE_PRIVATE_KEY,
            'client_email' => FIREBASE_CLIENT_EMAIL,
            'client_id' => defined('FIREBASE_CLIENT_ID') ? FIREBASE_CLIENT_ID : '',
            'auth_uri' => defined('FIREBASE_AUTH_URI') ? FIREBASE_AUTH_URI : 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => defined('FIREBASE_TOKEN_URI') ? FIREBASE_TOKEN_URI : 'https://oauth2.googleapis.com/token',
            'auth_provider_x509_cert_url' => defined('FIREBASE_AUTH_PROVIDER_CERT_URL') ? FIREBASE_AUTH_PROVIDER_CERT_URL : 'https://www.googleapis.com/oauth2/v1/certs',
            'client_x509_cert_url' => defined('FIREBASE_CLIENT_CERT_URL') ? FIREBASE_CLIENT_CERT_URL : '',
            'universe_domain' => defined('FIREBASE_UNIVERSE_DOMAIN') ? FIREBASE_UNIVERSE_DOMAIN : 'googleapis.com'
        ];
    }

    // Get project ID
    if (empty($serviceAccountConfig['project_id'])) {
        return "project_id not found in service account configuration";
    }
    $projectId = $serviceAccountConfig['project_id'];
    $fcmUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    // Initialize Google Client and request an OAuth2 access token
    try {
        $client = new Google_Client();
        $client->setAuthConfig($serviceAccountConfig);

        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

        $tokenArray = $client->fetchAccessTokenWithAssertion();
        if (isset($tokenArray['error'])) {
            $err = is_array($tokenArray['error']) ? json_encode($tokenArray) : $tokenArray['error'];
            return "Error fetching access token: " . $err;
        }
        if (empty($tokenArray['access_token'])) {
            return "No access_token returned from service account fetch";
        }
        $accessToken = $tokenArray['access_token'];
    } catch (Throwable $e) {
        return "Google Client error: " . $e->getMessage();
    }

    // Build title/body
    $title = "Vehicle Assignment: " . $vehicleNumber;
    $bodyParts = ["Vehicle $vehicleNumber has been assigned"];
    if (!empty($details['driver_name'])) {
        $bodyParts[] = "Driver: " . $details['driver_name'];
    }
    if (!empty($details['route'])) {
        $bodyParts[] = "Rou te: " . $details['route'];
    }
    if (!empty($details['departure_time'])) {
        $bodyParts[] = "Departure: " . $details['departure_time'];
    }
    $body = implode(" | ", $bodyParts);

    // Prepare data payload (stringify everything)
    $dataPayload = [
        'type' => 'vehicle_assignment',
        'vehicle_number' => (string)$vehicleNumber,
        'timestamp' => date('Y-m-d H:i:s'),
        'url' => 'https://staff-stage.deltin.com/home'
    ];
    foreach ($details as $k => $v) {
        // convert arrays to JSON string
        $dataPayload[$k] = is_scalar($v) ? (string)$v : json_encode($v);
    }

    // Build message payload for HTTP v1 (message object)
    $payload = [
        'message' => [
            'token' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body
            ],
            'data' => $dataPayload,
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default'
                ]
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                    'apns-push-type' => 'alert'
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $title,
                            'body' => $body
                        ],
                        'sound' => 'default',
                        'badge' => 1
                    ]
                ]
            ]
        ]
    ];

    // Send via cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fcmUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json; charset=utf-8',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutCurl);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrNo = curl_errno($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErrNo) {
        return "cURL error ({$curlErrNo}): {$curlErr}";
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        // success
        return true;
    }

    // Return response message on failure
    return "FCM HTTP {$httpCode} - Response: " . substr($response ?? '', 0, 2000);
}

function sendBulkStaffVehicleNotification(
    array $deviceTokens,
    string $vehicleNumber,
    array $details = [],
    int $maxRetries = 2,
    int $initialBackoffMs = 200
) {
    $results = [
        'total' => count($deviceTokens),
        'success' => 0,
        'failed' => 0,
        'errors' => [] // token => last error message
    ];

    foreach ($deviceTokens as $token) {
        $attempt = 0;
        $succeeded = false;
        $lastError = null;

        while ($attempt <= $maxRetries && !$succeeded) {
            $attempt++;
            $res = sendStaffVehicleNotification($token, $vehicleNumber, $details);

            if ($res === true) {
                $succeeded = true;
                $results['success']++;
                break;
            } else {
                // $res contains error string
                $lastError = $res;
                // small exponential backoff before retrying
                if ($attempt <= $maxRetries) {
                    $backoff = intval($initialBackoffMs * (2 ** ($attempt - 1)));
                    usleep($backoff * 1000); // convert ms -> microseconds
                }
            }
        }

        if (!$succeeded) {
            $results['failed']++;
            $results['errors'][$token] = $lastError ?? 'unknown error';
        }

        // gentle delay to reduce immediate rate limiting
        usleep(80000); // 80ms
    }

    return $results;
}

function getStaffTokensByTripId($tripId) {
    $tokens = [];
    $tripId = intval($tripId);
    if ($tripId <= 0) {
        return $tokens;
    }

    $query = "
        SELECT DISTINCT m.vDeviceToken
        FROM staff m
        INNER JOIN st_request ts ON m.iStaffID = ts.iStaffID
        WHERE ts.iTrReqID  = $tripId
          AND m.vDeviceToken IS NOT NULL
          AND m.vDeviceToken != ''
          AND m.cActive = 'Y'
    ";

    $result = sql_query($query);
    if ($result) {
        while ($row = sql_fetch_assoc($result)) {
            $token = trim($row['vDeviceToken'] ?? '');
            if ($token !== '') {
                $tokens[] = $token;
            }
        }
    }
    return $tokens;
}

function notifyTripStaffVehicleAssignment($tripId, $vehicleNumber, $additionalDetails = []) {
    $staffTokens = getStaffTokensByTripId($tripId);
    if (empty($staffTokens)) {
        return [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'message' => "No active staff tokens found for trip ID {$tripId}"
        ];
    }

    // Include trip id in details
    $details = array_merge(['trip_id' => (string)$tripId], $additionalDetails);

    // For large lists you might want to chunk and/or use topics or FCM batch APIs.
    $results = sendBulkStaffVehicleNotification($staffTokens, $vehicleNumber, $details);

    // Friendly message
    $results['message'] = "{$results['success']} successful, {$results['failed']} failed";

    return $results;
}

$res=notifyTripStaffVehicleAssignment(58, 'DL1AB1234', [
    'driver_name' => 'John Doe',
    'route' => 'Route 5',
    'departure_time' => '08:30 AM'
]);
// $deviceToken = $_GET['device_token'] ?? '';
// $vehicleNumber = "GA-09-AB-1234";

// $details = [
//     "driver_name" => "Yogesh",
//     "route" => "Panaji → Vasco",
//     "departure_time" => "2025-11-27 09:30 AM"
// ];

// $res = sendStaffVehicleNotification(
//     $deviceToken,
//     $vehicleNumber,
//     $details
// );
echo json_encode($res);
exit;

?>