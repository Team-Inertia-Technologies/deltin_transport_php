<?php
$NO_REDIRECT = $NO_PRELOAD = 1;
header('Content-Type: application/json');

/**
 * Normalise any Indian number to E.164 format (+91XXXXXXXXXX)
 * Handles: +919812345678 / 919812345678 / 9812345678 / 09812345678
 */
function toE164(string $number): string
{
    $number = preg_replace('/[^0-9]/', '', $number); // digits only

    if (strlen($number) === 12 && substr($number, 0, 2) === '91') {
        $number = substr($number, 2); // strip country code
    }

    $number = ltrim($number, '0'); // strip leading 0

    if (strlen($number) !== 10) {
        return ''; // invalid
    }

    return '+91' . $number; // E.164 format: +91XXXXXXXXXX
}

// ── Read CallFrom sent by Exotel ─────────────────────────────────────────────
$raw_caller = $_GET['CallFrom'] ?? '';

if (empty($raw_caller)) {
    http_response_code(400);
    echo json_encode(['error' => 'CallFrom is required']);
    exit;
}

$customer_e164 = toE164($raw_caller);

if (empty($customer_e164)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid customer number format: ' . $raw_caller]);
    exit;
}

// Pure 10-digit for DB lookup
$customer_10 = substr($customer_e164, 3);
require_once "../../includes/common_api.php";
$NOW = NOW;

$sql = "SELECT
            CASE WHEN d.cComViaVendor = 'Y' THEN v.vContactNum ELSE d.vMobileNum END AS driver_phone
        FROM fleet_booking fb
        LEFT JOIN driver d ON fb.iDriverID = d.iDriverID AND d.cStatus = 'A'
        LEFT JOIN vendor v ON d.iVendorID = v.iVendorID AND v.cStatus = 'A'
        WHERE (
            fb.vMobileNo = '{$customer_10}'
            OR fb.vMobileNo = '0{$customer_10}'
        )
        AND fb.cStatus NOT IN ('X', 'C')
        AND fb.cType != 'C'
        AND (
            (d.cComViaVendor = 'N' AND d.vMobileNum IS NOT NULL)
            OR (d.cComViaVendor = 'Y' AND v.vContactNum IS NOT NULL)
        )
   ORDER BY
    CASE
        WHEN fb.cType IN ('N','S','G','P','R') 
             AND fb.vPickUpTime >= '{$NOW}' THEN 1

        WHEN fb.cType = 'N' 
             AND fb.vPickUpTime < '{$NOW}' 
             AND TIMESTAMPDIFF(HOUR, fb.vPickUpTime, '{$NOW}') <= 2 THEN 2

        WHEN fb.cType IN ('N','S','G','P','R') 
             AND fb.vPickUpTime < '{$NOW}' THEN 3

        ELSE 4
    END,
    ABS(TIMESTAMPDIFF(MINUTE, '{$NOW}', fb.vPickUpTime)) ASC
        LIMIT 1";

$result = sql_query($sql);

if ($row = $result->fetch_assoc()) {

    $driver_e164 = toE164($row['driver_phone']);

    if (empty($driver_e164)) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid driver number in DB: ' . $row['driver_phone']]);
        exit;
    }
    echo json_encode([
        "destination" => [
            "numbers" => [$driver_e164]
        ],
        "record"                    => true,
        "recording_channels"        => "dual",
        "max_ringing_duration"      => 45,
        "max_conversation_duration" => 3600,
        "music_on_hold" => [
            "type" => "operator_tone"
        ]
    ]);

} else {
    http_response_code(404);
    echo json_encode([
        'error'            => 'No active booking / driver found',
        'looked_up_number' => $customer_10,
    ]);
}