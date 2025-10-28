<?php
if(isset($logged) && !empty($logged))
{
	$LINK_ARR = array();
	array_push($LINK_ARR,'action.php');
	array_push($LINK_ARR,'home.php');
	array_push($LINK_ARR,'newrequests_list.php');
	array_push($LINK_ARR,'inprocess_list.php');
	array_push($LINK_ARR,'confirmed_list.php');
	array_push($LINK_ARR,'cancelled_list.php');
	array_push($LINK_ARR,'requests_view.php');
	array_push($LINK_ARR,'bookings_view.php');
	array_push($LINK_ARR,'_get_invoiceform.php');
	array_push($LINK_ARR,'invoice_list.php');
	array_push($LINK_ARR,'invoice_add.php');

	array_push($LINK_ARR,'ajax.inc.php');
	array_push($LINK_ARR,'logout.php');
	array_push($LINK_ARR,'test.php');
	
	if(!empty($LINK_ARR) && count($LINK_ARR))
	{
		if(!in_array(basename($_SERVER["SCRIPT_NAME"]),$LINK_ARR))
		{
			header('location:home.php');
			exit;
		}
	}
}
?>