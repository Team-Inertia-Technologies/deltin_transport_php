<?php
$NO_REDIRECT = 1;
include "api_includes.php";

function GetVehicle_BasedOnSearch2($txtpickup_time,$txtpickup_location,$txttype=0,$txtcatid=0)
{
	$arr = array();
	$cond = '';
	if(!empty($txttype)) $cond .= ' and v.iType='.$txttype;
	if(!empty($txtcatid)) $cond .= ' and v.iCatID='.$txtcatid;

	$q = 'select DISTINCT(v.iVehicleID), v.vName, v.vRnum, v.iCatID, v.iVendorID, v.iType, v.iSeats from vehicle as v left join fleet_booking as b on v.iVehicleID=f.iVehicleID and f.cStatus!="X" and ("'.$txtpickup_time.'" between f.vPickUpTime and f.vDropTime or (f.iFleet_LocationID_To='.$txtpickup_location.' and ABS(TIMESTAMPDIFF(MINUTE,f.vDropTime,"'.$txtpickup_time.'")) <= 15)) where v.cServiceType IN ("B","F") and v.cStatus="A"'.$cond;
	$r = sql_query($q,'');
	if(sql_num_rows($r))
	{
		while(list($iVehicleID,$vName,$vRnum,$iCatID,$iVendorID,$iType,$iSeats) = sql_fetch_row($r))
		{
			if(!isset($arr[$iVehicleID])) $arr[$iVehicleID] = array();
			$arr[$iVehicleID] = array('NAME'=>$vName, 'NUM'=>$vRnum, 'CAT_ID'=>$iCatID, 'VENDOR_ID'=>$iVendorID, 'TYPE_ID'=>$iType, 'SEATS'=>$iSeats);
		}
	}

	return $arr;
	
	
	/*SELECT DISTINCT
		v.iVehicleID,
		v.vName,
		v.vRnum,
		v.iCatID,
		v.iType,
		v.cStatus
	FROM vehicle v
	LEFT JOIN fleet_booking fb
		ON fb.iVehicleID = v.iVehicleID
		AND fb.cStatus IN ('A', 'C') -- Active / Confirmed bookings
		AND (
			-- Booking overlaps pickup time
			:set_pickup_time BETWEEN fb.vPickUpTime AND fb.vDropTime
			OR
			-- Vehicle drops at pickup location within ±15 minutes
			(
				fb.iFleet_LocationID_To = :set_pickup_location
				AND ABS(TIMESTAMPDIFF(
					MINUTE,
					fb.vDropTime,
					:set_pickup_time
				)) <= 15
			)
		)
	WHERE
		v.cStatus = COALESCE(:set_vehicle_status, v.cStatus)
		AND v.iType   = COALESCE(:set_vehicle_type, v.iType)
		AND v.iCatID  = COALESCE(:set_vehicle_category, v.iCatID)
	
		-- Exclude vehicles with conflicting bookings
		AND fb.iFleet_BookingID IS NULL;*/
}

if(isset($_GET['sohel']))
{
	$GET = $_GET;
	foreach($GET as $KEY=>$VALUE)
		${$KEY} = $VALUE;
}
else
{
	$txtpickup_time = '2025-12-24 17:00:00';
	$txtpickup_location = '1';
	$txttype = $txtcatid = 0;
}

$RESPONSE = GetVehicle_BasedOnSearch2($txtpickup_time,$txtpickup_location,$txttype,$txtcatid);
DFA($RESPONSE);
exit;
?>