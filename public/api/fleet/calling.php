<?php

include "../../includes/common_api.php";

header('Content-Type: application/json');

function triggerExotelExoMLCall($fromNumber, $tonumber)
{
    $api_key   = 'ab4f6f769ee189fa5e4d57b79789de3b987fab33a413819f';
    $api_token = '5f1f43db51a120f9027c32fbecc3de88410a92a0396c9da0';
    $sid       = 'deltacorp1';
    $callerId  = '07314852425';

    $url = "https://api.exotel.com/v1/Accounts/$sid/Calls/connect.json";

    // $data = [
    //     'From'     => $fromNumber,
    //     'To'       => $tonumber,
    //     'CallerId' => $callerId,
    //     'Record'   => 'true'
    // ];
    $data = [
    'From'     => $callerId,      // Exotel virtual number
    'To'       => $fromNumber,    // Customer
    'CallTo'   => $tonumber,      // Driver
    'Record'   => 'true'
];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, "$api_key:$api_token");

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return [
            'status'  => 'error',
            'message' => curl_error($ch)
        ];
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($http_code === 200 && isset($decoded['Call']['Sid'])) {
        return [
            'status'      => 'success',
            'call_sid'    => $decoded['Call']['Sid'],
            'call_status' => $decoded['Call']['Status']
        ];
    }

    return [
        'status'  => 'error',
        'message' => $decoded['RestException']['Message'] ?? 'Unknown error'
    ];
}

$customer_number = $_GET['CallFrom'] ?? '';
$NOW = NOW;

// normalize number
$customer_number = preg_replace('/[^0-9]/', '', $customer_number);

if(strlen($customer_number) == 12 && substr($customer_number,0,2) == '91'){
    $customer_number = substr($customer_number,2);
}

$sql = "SELECT d.vMobileNum as driver_phone 
        FROM fleet_booking fb
        LEFT JOIN driver d ON fb.iDriverID = d.iDriverID AND d.cStatus = 'A'
        WHERE fb.vMobileNo = '$customer_number'
        AND fb.cStatus NOT IN ('X','C')
        AND d.vMobileNum IS NOT NULL
        ORDER BY 
            CASE 
                WHEN fb.cType IN ('S', 'G', 'P', 'R') THEN 1
                WHEN fb.cType = 'N' AND fb.vPickUpTime >= '$NOW' THEN 2
                WHEN fb.cType = 'N' AND fb.vPickUpTime < '$NOW' 
                     AND TIMESTAMPDIFF(HOUR, fb.vPickUpTime, '$NOW') <= 2 THEN 3
                ELSE 4
            END,
            ABS(TIMESTAMPDIFF(MINUTE, '$NOW', fb.vPickUpTime)) ASC
        LIMIT 1";

$result = sql_query($sql);

if($row = $result->fetch_assoc()){

    $driver_number = $row['driver_phone'];

    // // Exotel format
    // $driver_number = '0'.$driver_number;
    // $customer_number = '0'.$customer_number;
    $driver_number = '+91'.$driver_number;
$customer_number = '+91'.$customer_number;

    // trigger call
    $callResponse = triggerExotelExoMLCall($customer_number, $driver_number);

    echo json_encode([
        "customer_number" => $customer_number,
        "driver_number"   => $driver_number,
        "call_response"   => $callResponse
    ]);

}else{

    http_response_code(404);

    echo json_encode([
        "error" => "No driver found"
    ]);
}