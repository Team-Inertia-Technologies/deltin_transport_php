<?php

include "../../includes/common_api.php";

$customer_number = $_POST['From'];
$NOW=NOW;
// Find most relevant booking for this customer - prioritize active trips, then upcoming bookings
$sql = "SELECT d.vMobileNum as driver_phone 
        FROM fleet_booking fb
        LEFT JOIN driver d ON fb.iDriverID = d.iDriverID AND d.cStatus = 'A'
        WHERE fb.vMobileNo = '$customer_number'
        AND fb.cStatus != 'X'
        AND fb.cStatus != 'C'
        AND d.vMobileNum IS NOT NULL
        ORDER BY 
            CASE 
                WHEN fb.cType IN ('S', 'G', 'P', 'R') THEN 1  -- Active trips first
                WHEN fb.cType = 'N' AND fb.vPickUpTime >= '$NOW' THEN 2  -- Upcoming bookings
                ELSE 3
            END,
            ABS(TIMESTAMPDIFF(MINUTE, '$NOW', fb.vPickUpTime)) ASC  -- Closest to pickup time
        LIMIT 1";

$result = sql_query($sql);

if($row = $result->fetch_assoc()){
    $driver_number = $row['driver_phone'];
    connectCustomerToDriver($customer_number, $driver_number);
}