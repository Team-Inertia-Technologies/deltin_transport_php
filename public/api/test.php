<?php
$NO_REDIRECT = 1;
include "api_includes.php";


function SendConfirmationMessage2($to, $name, $from_palce, $date)
{
    $ch = curl_init();

    // Set the URL
    curl_setopt($ch, CURLOPT_URL, 'https://in1-ccaaspro.ozonetel.com/whatsApp_API/v1/WhatsAppSendOzone/reply');

    // Set the request method to POST
    curl_setopt($ch, CURLOPT_POST, 1);

    // Set the headers
    $headers = [
        'apikey: KK402649e9b17ee14c0ec4469cf34882af',
        'Content-Type: application/json',
        'Cookie: PHPSESSID=ff77edb2060d5af54ef0af9941b3f3a9'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Set the POST data
    $data = [
        'recipient' => [
            'id' => $to
        ],
        'kookoo_id' => 'OZNTLWA:918806660117',
        'type' => 'template',
        'template' => [
            'name' => 'transport_confirmation_pending',
            'language' => 'en_US',
            'parameters' => [
                '1' => $name,
                '2' => $from_palce,
                '3' => $date
            ]

        ]
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    // Return the response as a string instead of outputting it
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the cURL request
    $response = curl_exec($ch);

    //Check for cURL errors
    if (curl_errno($ch)) {
        echo 'cURL error: ' . curl_error($ch);
    }

    // Close the cURL session
    curl_close($ch);

    return  $response;
}

$to = $_GET['to'];
$name = $_GET['name'];
$from_palce = $_GET['from_palce'];
$date = $_GET['date'];

$response = SendConfirmationMessage2($to, $name, $from_palce, $date);
DFA($response);
exit;

function GetVehicle_BasedOnSearch2_($txttype=0,$txtcatid=0,$show_currentstatus='N',$txtfrom_time='',$txtto_time='')
{
	$arr = $arr2 = array();
	$cond = '';
	if(!empty($txttype)) $cond .= ' and v.iType='.$txttype;
	if(!empty($txtcatid)) $cond .= ' and v.iCatID='.$txtcatid;

	$q = 'select v.iVehicleID, v.vName, v.vRnum, v.iCatID, v.iVendorID, v.iType, v.iSeats, d.iDriverID, d.iVendorID as D_VENDORID, d.vName as D_NAME, d.vMobileNum, d.vEmpCode from vehicle as v left outer join driver as d on v.iVehicleID=d.iVehicleID and d.cStatus!="X" where v.cStatus!="X"'.$cond;
	$r = sql_query($q,'');
	if(sql_num_rows($r))
	{
		while(list($iVehicleID,$vName,$vRnum,$iCatID,$iVendorID,$iType,$iSeats,$iDriverID,$D_VENDORID,$D_NAME,$vMobileNum,$vEmpCode) = sql_fetch_row($r))
		{
			if(!isset($arr[$iVehicleID])) $arr[$iVehicleID] = array();
			$arr[$iVehicleID] = array('NAME'=>$vName, 'NUM'=>$vRnum, 'CAT_ID'=>$iCatID, 'VENDOR_ID'=>$iVendorID, 'TYPE_ID'=>$iType, 'SEATS'=>$iSeats, 'DRIVER_ID'=>$iDriverID, 'VENDOR_ID2'=>$D_VENDORID, 'DRIVER_NAME'=>$D_NAME, 'DRIVER_NUM'=>$vMobileNum, 'DRIVER_EMPCODE'=>$vEmpCode, 'BOOKINGS'=>array());

			if(!isset($arr2[$iVehicleID]))
				$arr2[$iVehicleID] = $iVehicleID;
		}
	}

	if($show_currentstatus=='Y' && !empty($arr) && count($arr))
	{
		if(empty($txtfrom_time)) $txtfrom_time = NOW;
		if(empty($txtto_time)) $txtto_time = DateTimeAdd($txtfrom_time,0,0,0,1,0,0,'Y-m-d H:i:s');
		
		$q2 = 'select iFleet_BookingID, iFleet_LocationID_From, iFleet_LocationID_To, vPickUpLocation, vPickUpTime, vDropLocation, vDropTime, iDriverID, iVehicleID, cDisposal, cStatus from fleet_booking where iVehicleID IN ('.implode(',',array_keys($arr)).') and cStatus NOT IN ("X") and vPickUpTime < "'.$txtto_time.'" and vDropTime > "'.$txtfrom_time.'" order by iVehicleID, vPickUpTime';
		$r2 = sql_query($q2,'');
		if(sql_num_rows($r2))
		{
			while(list($iFleet_BookingID, $iFleet_LocationID_From, $iFleet_LocationID_To, $vPickUpLocation, $vPickUpTime, $vDropLocation, $vDropTime, $iDriverID, $iVehicleID, $cDisposal, $cStatus) = sql_fetch_row($r2))
			{
				if(isset($arr[$iVehicleID]))
				{
					array_push($arr[$iVehicleID]['BOOKINGS'],array('ID'=>$iFleet_BookingID, 'LOCATION_ID_FROM'=>$iFleet_LocationID_From, 'LOCATION_ID_TO'=>$iFleet_LocationID_To, 'PICKUP_LOCATION'=>$vPickUpLocation, 'PICKUP_TIME'=>$vPickUpTime, 'DROP_LOCATION'=>$vDropLocation, 'DROP_TIME'=>$vDropTime, 'DRIVER_ID'=>$iDriverID, 'DISPOSAL'=>$cDisposal, 'STATUS'=>$cStatus));
					
					unset($arr2[$iVehicleID]);
				}
			}
		}
		
		if(!empty($arr2) && count($arr2))
		{
			$q3 = 'select iFleet_BookingID, iFleet_LocationID_From, iFleet_LocationID_To, vPickUpLocation, vPickUpTime, vDropLocation, vDropTime, iDriverID, iVehicleID, cDisposal, cStatus from ( select *, ROW_NUMBER() OVER ( PARTITION BY iVehicleID ORDER BY vPickUpTime ) AS rn FROM fleet_booking WHERE iVehicleID IN ('.implode(',',array_keys($arr2)).') AND cStatus NOT IN ("X") AND vPickUpTime > "'.$txtfrom_time.'" ) t WHERE rn = 1';
			$r3 = sql_query($q3,'');
			if(sql_num_rows($r3))
			{
				while(list($iFleet_BookingID, $iFleet_LocationID_From, $iFleet_LocationID_To, $vPickUpLocation, $vPickUpTime, $vDropLocation, $vDropTime, $iDriverID, $iVehicleID, $cDisposal, $cStatus) = sql_fetch_row($r3))
				{
					if(isset($arr[$iVehicleID]))
					{
						array_push($arr[$iVehicleID]['BOOKINGS'],array('ID'=>$iFleet_BookingID, 'LOCATION_ID_FROM'=>$iFleet_LocationID_From, 'LOCATION_ID_TO'=>$iFleet_LocationID_To, 'PICKUP_LOCATION'=>$vPickUpLocation, 'PICKUP_TIME'=>$vPickUpTime, 'DROP_LOCATION'=>$vDropLocation, 'DROP_TIME'=>$vDropTime, 'DRIVER_ID'=>$iDriverID, 'DISPOSAL'=>$cDisposal, 'STATUS'=>$cStatus));
					}
				}
			}
		}
	}

	return $arr;
}

if(isset($_GET['sohel']))
{
	$GET = $_GET;
	foreach($GET as $KEY=>$VALUE)
		${$KEY} = $VALUE;
}
else
{
	$txtpickup_time = '2026-02-05 10:33:00';
	$txtpickup_location = '1';
	$txttype = $txtcatid = 0;
}

$RESPONSE = GetVehicle_BasedOnSearch2($txttype,$txtcatid,'Y','','','');
DFA($RESPONSE);
exit;
?>