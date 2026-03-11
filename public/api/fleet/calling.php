<?php

include "../../includes/common_api.php";

header('Content-Type: application/json');

/**
 * Exotel Calls/connect.json
 *
 * HOW IT WORKS:
 *   1. Exotel calls `From`  (customer) first
 *   2. Once customer picks up, Exotel calls `To` (driver)
 *   3. Both are bridged together
 *   4. `CallerId` is what BOTH legs see as the calling number (your ExoPhone)
 *
 * COMMON MISTAKE: Passing +91 format causes Exotel to silently drop
 * the second leg — call rings then cuts, To not shown in dashboard.
 */
function triggerExotelCall(string $customerNumber, string $driverNumber): array
{
    $api_key   = 'ab4f6f769ee189fa5e4d57b79789de3b987fab33a413819f';
    $api_token = '5f1f43db51a120f9027c32fbecc3de88410a92a0396c9da0';
    $sid       = 'deltacorp1';
    $callerId  = '07314852425'; // Your ExoPhone in 0XXXXXXXXXX format

    $url = "https://api.exotel.com/v1/Accounts/{$sid}/Calls/connect.json";

    // From      = called FIRST (customer)
    // To        = called SECOND after From picks up (driver)
    // CallerId  = ExoPhone — shown to both parties as caller ID
    // MUST use 0XXXXXXXXXX format — NOT +91
    $data = [
        'From'     => $customerNumber,
        'To'       => $driverNumber,
        'CallerId' => $callerId,
        'Record'   => 'true',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, "{$api_key}:{$api_token}");
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_errno($ch);
    $curl_msg  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        return ['status' => 'error', 'message' => 'cURL: ' . $curl_msg];
    }

    $decoded = json_decode($response, true);
    error_log("[Exotel] HTTP {$http_code} | From={$customerNumber} | To={$driverNumber} | " . $response);

    if ($http_code === 200 && isset($decoded['Call']['Sid'])) {
        return [
            'status'      => 'success',
            'call_sid'    => $decoded['Call']['Sid'],
            'call_status' => $decoded['Call']['Status'],
        ];
    }

    return [
        'status'       => 'error',
        'http_code'    => $http_code,
        'message'      => $decoded['RestException']['Message'] ?? 'Unknown Exotel error',
        'raw_response' => $decoded,
    ];
}

/**
 * Normalise any Indian number to Exotel's expected 0XXXXXXXXXX format.
 * Handles: +919812345678 / 919812345678 / 9812345678 / 09812345678
 */
function toExotelFormat(string $number): string
{
    $number = preg_replace('/[^0-9]/', '', $number); // digits only

    if (strlen($number) === 12 && substr($number, 0, 2) === '91') {
        $number = substr($number, 2); // strip country code
    }

    $number = ltrim($number, '0'); // strip any leading 0

    if (strlen($number) !== 10) {
        return ''; // invalid
    }

    return '0' . $number; // Exotel format: 0XXXXXXXXXX
}

// ── Read CallFrom sent by Exotel ─────────────────────────────────────────────
$raw_caller = $_GET['CallFrom'] ?? '';

if (empty($raw_caller)) {
    http_response_code(400);
    echo json_encode(['error' => 'CallFrom is required']);
    exit;
}

$customer_number = toExotelFormat($raw_caller);

if (empty($customer_number)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid customer number format: ' . $raw_caller]);
    exit;
}

// Pure 10-digit for DB lookup (DB may store with or without leading 0)
$customer_10 = ltrim(preg_replace('/[^0-9]/', '', $customer_number), '0');

// ── DB: find active booking + driver for this customer ───────────────────────
$NOW = NOW;

$sql = "SELECT d.vMobileNum AS driver_phone
        FROM fleet_booking fb
        LEFT JOIN driver d ON fb.iDriverID = d.iDriverID AND d.cStatus = 'A'
        WHERE (
            fb.vMobileNo = '{$customer_10}'
            OR fb.vMobileNo = '0{$customer_10}'
        )
        AND fb.cStatus NOT IN ('X', 'C')
        AND d.vMobileNum IS NOT NULL
        ORDER BY
            CASE
                WHEN fb.cType IN ('S', 'G', 'P', 'R') THEN 1
                WHEN fb.cType = 'N' AND fb.vPickUpTime >= '{$NOW}' THEN 2
                WHEN fb.cType = 'N' AND fb.vPickUpTime < '{$NOW}'
                     AND TIMESTAMPDIFF(HOUR, fb.vPickUpTime, '{$NOW}') <= 2 THEN 3
                ELSE 4
            END,
            ABS(TIMESTAMPDIFF(MINUTE, '{$NOW}', fb.vPickUpTime)) ASC
        LIMIT 1";

$result = sql_query($sql);

if ($row = $result->fetch_assoc()) {

    $driver_number = toExotelFormat($row['driver_phone']);

    if (empty($driver_number)) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid driver number in DB: ' . $row['driver_phone']]);
        exit;
    }

    $callResponse = triggerExotelCall($customer_number, $driver_number);

    echo json_encode([
        'customer_number' => $customer_number,
        'driver_number'   => $driver_number,
        'call_response'   => $callResponse,
    ]);

} else {

    http_response_code(404);
    echo json_encode([
        'error'            => 'No active booking / driver found',
        'looked_up_number' => $customer_10,
    ]);
}