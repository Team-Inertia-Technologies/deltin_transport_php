<?php
$NO_REDIRECT = 1;
include "api_includes.php";

/*function GetVehicle_BasedOnSearch2($txttype=0,$txtcatid=0,$show_currentstatus='N',$txtfrom_time='',$txtto_time='',$cmbstatus='')
{
	$arr = $arr2 = array();
	$cond = '';
	if(!empty($txttype)) $cond .= ' and v.iType='.$txttype;
	if(!empty($txtcatid)) $cond .= ' and v.iCatID='.$txtcatid;

	$q = 'select v.iVehicleID, v.vName, v.vRnum, v.iCatID, v.iVendorID, v.iType, v.iSeats, d.iDriverID, d.iVendorID as D_VENDORID, d.vName as D_NAME, d.vMobileNum, d.vEmpCode, d.iType as D_TYPE from vehicle as v left outer join driver as d on v.iVehicleID=d.iVehicleID and d.cStatus!="X" where v.cServiceType IN ("B","F") and v.cStatus!="X"'.$cond;
	$r = sql_query($q,'');
	if(sql_num_rows($r))
	{
		while(list($iVehicleID,$vName,$vRnum,$iCatID,$iVendorID,$iType,$iSeats,$iDriverID,$D_VENDORID,$D_NAME,$vMobileNum,$vEmpCode,$iDriverType) = sql_fetch_row($r))
		{
			if(!isset($arr[$iVehicleID])) $arr[$iVehicleID] = array();
			$arr[$iVehicleID] = array('NAME'=>$vName, 'NUM'=>$vRnum, 'CAT_ID'=>$iCatID, 'VENDOR_ID'=>$iVendorID, 'TYPE_ID'=>$iType, 'SEATS'=>$iSeats, 'DRIVER_ID'=>$iDriverID, 'VENDOR_ID2'=>$D_VENDORID, 'DRIVER_NAME'=>$D_NAME, 'DRIVER_NUM'=>$vMobileNum, 'DRIVER_EMPCODE'=>$vEmpCode, 'DRIVER_TYPE'=>$iDriverType, 'BOOKINGS'=>array());

			if(!isset($arr2[$iVehicleID]))
				$arr2[$iVehicleID] = $iVehicleID;
		}
	}

	if($show_currentstatus=='Y' && !empty($arr) && count($arr))
	{
		if(empty($txtfrom_time)) $txtfrom_time = NOW;
		if(empty($txtto_time)) $txtto_time = DateTimeAdd($txtfrom_time,0,0,0,1,0,0,'Y-m-d H:i:s');
		
		$cond2 = '';
		if(!empty($cmbstatus)) $cond2 .= ' and cType="'.$cmbstatus.'"';
		
		$q2 = 'select iFleet_BookingID, iFleet_LocationID_From, iFleet_LocationID_To, vPickUpLocation, vPickUpTime, vDropLocation, vDropTime, iDriverID, iVehicleID, cDisposal, cType from fleet_booking where iVehicleID IN ('.implode(',',array_keys($arr)).') and cStatus NOT IN ("X") and vPickUpTime < "'.$txtto_time.'" and vDropTime > "'.$txtfrom_time.'"'.$cond2.' order by iVehicleID, vPickUpTime';
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
			$q3 = 'select iFleet_BookingID, iFleet_LocationID_From, iFleet_LocationID_To, vPickUpLocation, vPickUpTime, vDropLocation, vDropTime, iDriverID, iVehicleID, cDisposal, cType from ( select *, ROW_NUMBER() OVER ( PARTITION BY iVehicleID ORDER BY vPickUpTime ) AS rn FROM fleet_booking WHERE iVehicleID IN ('.implode(',',array_keys($arr2)).') AND cStatus NOT IN ("X") AND vPickUpTime > "'.$txtfrom_time.'"'.$cond2.' ) t WHERE rn = 1';
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
}*/
$vehitype = "";
$txtcatid = "";
$current_status = "Y";
$from = "";
$to = "";
$status = "";
if(isset($_GET)){

DFA($_GET);

$vehitype = (isset($_GET['vehitype']))?$_GET['vehitype']:"";
$txtcatid = (isset($_GET['txtcatid']))?$_GET['txtcatid']:"";
$current_status = (isset($_GET['current_status']))?$_GET['current_status']:"Y";
$from = (isset($_GET['from']))?$_GET['from']:"";
$to = (isset($_GET['to']))?$_GET['to']:"";
$status = (isset($_GET['status']))?$_GET['status']:"";

}


$RESPONSE = GetVehicle_BasedOnSearch2($vehitype,$txtcatid,'Y',$from,$to,$status);
DFA($RESPONSE);
exit;
?>