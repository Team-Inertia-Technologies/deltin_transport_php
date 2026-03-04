<?php
/*
 * Staff Vehicle Notification Cron Job
 * 
 * Workflow:
 * 1. When cron runs, first dump all notifications to st_notification table with status 'Q' (Queued)
 * 2. Then process all queued notifications by calling Firebase
 * 3. Update notification status based on Firebase response ('A' for success, 'F' for failure)
 * 4. Mark trips as completed when all notifications are processed
 * 
 * Required st_notification table columns:
 * - vTitle: Notification title
 * - vMessage: Notification message
 * - vErrorMsg: Error message if Firebase fails
 * - dtProcessed: Timestamp when notification was processed
 */
include "../../includes/common_api.php";
require_once(__DIR__ . '/../api_common.php');
require_once(__DIR__ . '/../../includes/libs/google_client/vendor/autoload.php');

use Google\Client as Google_Client;

function sendStaffVehicleNotification(
    string $deviceToken,
    string $vehicleNumber,
    array $details = [],
    ?array $serviceAccountConfig = null,
    int $timeoutCurl = 10
) {
    // Validate
    if (empty($deviceToken) || empty($vehicleNumber)) {
        return "Missing deviceToken or vehicleNumber";
    }

    if (empty($serviceAccountConfig)) {
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

    if (!empty($details['route'])) {
        $bodyParts[] = "Route: " . $details['route'];
    }
    if (!empty($details['departure_time'])) {
        $bodyParts[] = "Departure: " . $details['departure_time'];
    }
    $body = implode(" | ", $bodyParts);

    $dataPayload = [
        'type' => 'vehicle_assignment',
        'vehicle_number' => (string)$vehicleNumber,
        'timestamp' => date('Y-m-d H:i:s'),
        'url' => STAFF_APP_URL . '/home'
    ];
    foreach ($details as $k => $v) {
        $dataPayload[$k] = is_scalar($v) ? (string)$v : json_encode($v);
    }

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
function getStaffByToken($token)
{
    $token = db_input($token);
    $sql = "SELECT iStaffID, vMobile AS phone FROM staff WHERE vDeviceToken = '$token' LIMIT 1";
    $res = sql_query($sql);
    return sql_fetch_assoc($res) ?: null;
}

function getStaffTokensByTripId($tripId)
{
    $tokens = [];
    $tripId = intval($tripId);
    if ($tripId <= 0) {
        return $tokens;
    }

    $query = "
        SELECT DISTINCT s.vDeviceToken 
        FROM staff s 
        INNER JOIN st_request req ON s.iStaffID = req.iStaffID 
        WHERE req.iTripID = $tripId 
        AND s.vDeviceToken IS NOT NULL 
        AND s.vDeviceToken != ''
        AND s.cStatus = 'A'
        AND req.cStatus = 'A'";

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
function notifyTripStaffVehicleAssignment($tripId, $vehicleNumber, $additionalDetails = [])
{
    $staffTokens = getStaffTokensByTripId($tripId);
    if (empty($staffTokens)) {
        return [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'message' => "No active staff tokens found for trip ID {$tripId}"
        ];
    }
    $details = array_merge(['trip_id' => (string)$tripId], $additionalDetails);
    $results = sendBulkStaffVehicleNotification($staffTokens, $vehicleNumber, $details);
    $results['message'] = "{$results['success']} successful, {$results['failed']} failed";

    return $results;
}


function sendVehicleAssignedNotification($trip)
{
    $tripId = intval($trip['iTripID']);

    // LOCK trip immediately (prevent double cron)
   $result = sql_query("
    UPDATE st_trips 
    SET cNotified = 'P' 
    WHERE iTripID = $tripId 
    AND cNotified = 'N'
");

if (!$result || sql_affected_rows() == 0) {
    return;
}

    $vehicleNumber = $trip['vRnum'];
    $departureTime = date('d M Y H:i', strtotime($trip['dtTrip']));
    $route = $trip['route_name'] ?? '';

    $tokens = getStaffTokensByTripId($tripId);

    if (!empty($tokens)) {
        // First, dump all notifications to st_notification table with status 'Q'
        foreach ($tokens as $token) {
            $staff = getStaffByToken($token);
            $staffId = $staff['iStaffID'] ?? 0;
            $phone = $staff['phone'] ?? '';
            $now = NOW;

            // Create notification record with status 'Q' (Queued)
            sql_query("
                INSERT INTO st_notification 
                (iUserID, vDeviceToken, dtSent, vPhoneNo, iRefID, cRefType, cStatus, vTitle, vMessage)
                VALUES 
                ($staffId, '$token', '$now', '$phone', $tripId, 'T', 'Q', 
                'Vehicle Assignment: $vehicleNumber', 
                'Vehicle $vehicleNumber assigned | Route: $route | Departure: $departureTime')
            ");
        }

        // Mark trip as notifications queued
        sql_query("
            UPDATE st_trips 
            SET cNotified = 'Q' 
            WHERE iTripID = $tripId
        ");
    }
}

function processQueuedNotifications()
{
    // Get all queued notifications
    $sql = "
        SELECT 
            n.iNotificationID,
            n.iUserID,
            n.vDeviceToken,
            n.vPhoneNo,
            n.iRefID as iTripID,
            n.vTitle,
            n.vMessage,
            t.dtTrip,
            v.vRnum,
            r.vName AS route_name
        FROM st_notification n
        INNER JOIN st_trips t ON n.iRefID = t.iTripID
         INNER JOIN st_trip_vehicle_assoc tva ON t.iTripID = tva.iTripID AND tva.cStatus = 'A'
        LEFT JOIN st_route r ON t.iRouteID = r.iRouteID AND r.cStatus = 'A'
         INNER JOIN vehicle v ON tva.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
        WHERE n.cStatus = 'Q' 
        AND n.cRefType = 'T'
        ORDER BY n.dtSent ASC
    ";

    $result = sql_query($sql);
    $processedCount = 0;

    while ($notification = sql_fetch_assoc($result)) {
        $notificationId = $notification['iNotificationID'];
        $token = $notification['vDeviceToken'];
        $tripId = $notification['iTripID'];
        $vehicleNumber = $notification['vRnum'];
        
        // Prepare trip details
        $departureTime = date('d M Y H:i', strtotime($notification['dtTrip']));
        $route = $notification['route_name'] ?? '';
        
        $details = [
            'route' => $route,
            'departure_time' => $departureTime,
            'trip_id' => $tripId
        ];

        // Send Firebase notification
        $firebaseResult = sendStaffVehicleNotification($token, $vehicleNumber, $details);
        
        // Update notification status based on Firebase result
        $status = ($firebaseResult === true) ? 'A' : 'F';
        $errorMsg = ($firebaseResult === true) ? '' : substr($firebaseResult, 0, 500);
        
        sql_query("
            UPDATE st_notification 
            SET cStatus = '$status', 
                vErrorMsg = '" . db_input($errorMsg) . "',
                dtProcessed = '" . NOW . "'
            WHERE iNotificationID = $notificationId
        ");
        
        $processedCount++;
    }

    // Update trips that have all notifications processed
    sql_query("
        UPDATE st_trips t
        SET cNotified = 'Y'
        WHERE cNotified = 'Q'
        AND NOT EXISTS (
            SELECT 1 FROM st_notification n 
            WHERE n.iRefID = t.iTripID 
            AND n.cRefType = 'T' 
            AND n.cStatus = 'Q'
        )
    ");

    return $processedCount;
}

function getUpcomingTrips()
{
    $now = date('Y-m-d H:i:s');
    $notifi_offset = intval(GetXFromYID("SELECT vValue FROM sys_settings WHERE vCode = 'STAFF_NOTIFICATION_OFFSET'")) ?? 0;

    $twoHoursLater = date('Y-m-d H:i:s', strtotime("+{$notifi_offset} hours"));
    $today = date('Y-m-d');

    $sql = "
        SELECT 
            t.iTripID,
            t.dtTrip,
            t.cNotified,
            tva.iVehicleID,
            v.vRnum,
            r.vName AS route_name
        FROM st_trips t
        INNER JOIN st_trip_vehicle_assoc tva ON t.iTripID = tva.iTripID AND tva.cStatus = 'A'
        INNER JOIN vehicle v ON tva.iVehicleID = v.iVehicleID AND v.cStatus = 'A'
        LEFT JOIN st_route r ON t.iRouteID = r.iRouteID AND r.cStatus = 'A'
        WHERE DATE(t.dtTrip) = '$today'
        AND t.dtTrip BETWEEN '$now' AND '$twoHoursLater'
        AND t.cNotified = 'N'
        AND t.cStatus = 'A'
        AND v.vRnum IS NOT NULL
        AND EXISTS (
            SELECT 1 FROM st_request req 
            WHERE req.iTripID = t.iTripID 
            AND req.cStatus = 'A'
        )
        ORDER BY t.dtTrip ASC
    ";

    $result = sql_query($sql);
    $trips = [];

    while ($row = sql_fetch_assoc($result)) {
        $trips[] = $row;
    }

    return $trips;
}

// First, queue notifications for upcoming trips
$trips = getUpcomingTrips();
$queuedTrips = 0;

foreach ($trips as $trip) {
    sendVehicleAssignedNotification($trip);
    $queuedTrips++;
}

// Then, process queued notifications (send to Firebase)
$processedNotifications = processQueuedNotifications();

echo json_encode([
    "status" => "Cron Executed",
    "queued_trips" => $queuedTrips,
    "processed_notifications" => $processedNotifications,
    "message" => "Queued $queuedTrips trips, processed $processedNotifications notifications"
]);
exit;
