<?php

include "../../includes/common_api.php";

header('Content-Type: application/json');

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

    // Exotel format
    $driver_number = '0'.$driver_number;

    echo json_encode([
        "Number" => $driver_number
    ]);

}else{

    http_response_code(404);

    echo json_encode([
        "error" => "No driver found"
    ]);
}