<?php
include "../../includes/common_api.php";
require_once(__DIR__ . '/../api_common.php');
require_once(__DIR__ . '/../../includes/libs/google_client/vendor/autoload.php');

use Google\Client as Google_Client;

function sendSimpleNotification(
    string $deviceToken,
    string $title,
    string $body,
    array $dataPayload = [],
    int $timeoutCurl = 10
) {
    if (empty($deviceToken)) {
        return "Missing deviceToken";
    }

    // Firebase Service Account configs must be defined in constants
    if (!defined('FIREBASE_PROJECT_ID') || !defined('FIREBASE_PRIVATE_KEY') || !defined('FIREBASE_CLIENT_EMAIL')) {
        return "Firebase credentials not configured";
    }

    $serviceAccountConfig = [
        'type' => 'service_account',
        'project_id' => FIREBASE_PROJECT_ID,
        'private_key' => FIREBASE_PRIVATE_KEY,
        'client_email' => FIREBASE_CLIENT_EMAIL,
        'token_uri' => 'https://oauth2.googleapis.com/token'
    ];

    $projectId = FIREBASE_PROJECT_ID;
    $fcmUrl = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    try {
        $client = new Google_Client();
        $client->setAuthConfig($serviceAccountConfig);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $tokenArray = $client->fetchAccessTokenWithAssertion();

        if (empty($tokenArray['access_token'])) {
            return "Failed to fetch access token";
        }
        $accessToken = $tokenArray['access_token'];
    } catch (Throwable $e) {
        return "Google Client error: " . $e->getMessage();
    }

    // Ensure all data is stringified
    $safeData = [];
    foreach ($dataPayload as $k => $v) {
        $safeData[$k] = is_scalar($v) ? (string)$v : json_encode($v);
    }

    $payload = [
        "message" => [
            "token" => $deviceToken,
            "notification" => [
                "title" => $title,
                "body"  => $body
            ],
            "data" => $safeData,
            "android" => [
                "priority" => "high",
                "notification" => ["sound" => "default"]
            ],
            "apns" => [
                "headers" => [
                    "apns-priority" => "10",
                    "apns-push-type" => "alert"
                ],
                "payload" => [
                    "aps" => [
                        "alert" => ["title" => $title, "body" => $body],
                        "sound" => "default",
                        "badge" => 1
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fcmUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $accessToken,
        "Content-Type: application/json; charset=utf-8"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutCurl);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return "cURL error: " . $curlErr;
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    return "FCM HTTP {$httpCode} - Response: " . $response;
}

// ================== TEST CALL ==================
$token = isset($_GET['token']) ? $_GET['token'] : '';
$res = sendSimpleNotification(
    $token,
    "Test Notification",
    "This is a sample push.",
    ["extra_data" => "1234"]
);
echo json_encode($res);
exit;

?>
