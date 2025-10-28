<?php

require '../includes/libs/google_client/vendor/autoload.php';

use Google\Client;
use GuzzleHttp\Client as GuzzleClient;

// // Initialize Google Client
// $client = new Google_Client();
// $client->setAuthConfig('deltin-one-firebase-adminsdk-fo0ep-80ce21da32.json');
// $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

// // Your FCM project details
// $projectId = 'deltin-one';

// Your notification sending function
function sendFcmNotification($deviceToken, $title, $body)
{

    // Initialize Google Client
    $client = new Google_Client();
    $client->setAuthConfig('deltin-one-firebase-adminsdk-fo0ep-80ce21da32.json');
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

    // Your FCM project details
    $projectId = 'deltin-one';


    // Generate a fresh access token
    $token = $client->fetchAccessTokenWithAssertion()['access_token'];

    // Prepare the notification payload
    $payload = [
        'message' => [
            'token' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'android' => [
                'priority' => 'high',
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => '10',
                ],
            ],
        ],
    ];

    // Define the FCM URL for HTTP v1 API
    $fcmUrl = "https://fcm.googleapis.com/v1/projects/deltin-one/messages:send";

    // Use cURL to send the request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fcmUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    // Execute the request
    $response = curl_exec($ch);
    if ($response === false) {
        echo 'cURL Error: ' . curl_error($ch);
    } else {
        echo "Notification sent: " . $response;
    }

    // Close cURL
    curl_close($ch);
}

// // Example usage
$deviceToken = 'dXdwjrp6RMOaLuaLYm5ZFB:APA91bHRq1pAehqCo6Xx0sYG5CH11YUPhAPTct4ddUypN0tDLucMOj59YDI_TKdXqK4LLB0MHcmNX1_eVFs-iVuez2Tg66MtCOdyS8hJ-T6Lyom9EZgCok8';

$title = 'Hello!';
$body = 'This is a test notification using FCM HTTP v1 API';
sendFcmNotification($deviceToken, $title, $body);
