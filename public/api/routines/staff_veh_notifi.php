<?php
include "../../includes/common_api.php";
require_once(__DIR__ . '/../api_common.php');
require_once(__DIR__ . '/../../includes/libs/google_client/vendor/autoload.php');

use Google\Client as Google_Client;

function sendStaffVehicleNotification(
    string $deviceToken,
    string $vehicleNumber,
    array $details = [],
    string $serviceAccountPath = null,
    int $timeoutCurl = 10
) {
    // Validate
    if (empty($deviceToken) || empty($vehicleNumber)) {
        return "Missing deviceToken or vehicleNumber";
    }

    // Resolve service account path (default relative to this file)
    if (empty($serviceAccountPath)) {
        $serviceAccountPath = __DIR__ . '/../deltintransport-7d1c0-firebase-adminsdk-fbsvc-df872c15d3.json';
    }

    if (!file_exists($serviceAccountPath) || !is_readable($serviceAccountPath)) {
        return "Service account JSON not found or unreadable at: $serviceAccountPath";
    }

    // Read project_id from JSON to build FCM URL
    $credJson = json_decode(file_get_contents($serviceAccountPath), true);
    if (empty($credJson['project_id'])) {
        return "project_id not found in service account JSON";
    }
    $projectId = $credJson['project_id'];
    $fcmUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    // Initialize Google Client and request an OAuth2 access token
    try {
        $client = new Google_Client();
        $client->setAuthConfig($serviceAccountPath);

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
        $bodyParts[] = "Route: " . $details['route'];
    }
    if (!empty($details['departure_time'])) {
        $bodyParts[] = "Departure: " . $details['departure_time'];
    }
    $body = implode(" | ", $bodyParts);

    // Prepare data payload (stringify everything)
    $dataPayload = [
        'type' => 'vehicle_assignment',
        'vehicle_number' => (string)$vehicleNumber,
        'timestamp' => date('Y-m-d H:i:s')
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
        WHERE ts.iTripID = " . $tripId . "
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
echo json_encode($res);
exit;

?>