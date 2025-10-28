<?php
$NO_REDIRECT = 1;
include "includes/common_api.php";

$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

$APPRequestID = $request->APP_REQUEST_ID;
$RMID = $request->RMID;
$MEMBERID = $request->MEMBERID;
$CTYPE = $request->C_TYPE;
$custname = $request->CUST_NAME;
$custmobile = $request->CUST_MOBILE;
$IS_FLEXIBLE = $request->IS_FLEXIBLE;
$city_from = $request->CITY_FROM;
$city_to = $request->CITY_TO;



$today = TODAY;

$q = "UPDATE app_flight_request SET iRMID='$RMID',iCustID='$MEMBERID',vCustName='$custname',vCustMobile='$custmobile',cType='$CTYPE',cFlexibleDates='$IS_FLEXIBLE',vFrom='$city_from',vTo='$city_to',dtUpdated='$today' WHERE iAppRequestID='$APPRequestID' ";
$r = sql_query($q);
if (sql_affected_rows()) {
        $output = array('statusCode' => 200, 'message' => 'Request updated', 'data' => array());
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($output);
        exit;
} else {
        $output = array('statusCode' => 400, 'message' => 'some error occured !!.');
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode($output);
        exit;
}
?>