<?php
function GetConnected()
{
	$CON = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD) or die(mysqli_error() . "<strong>ERROR CODE : </strong> COM - 66");
	mysqli_select_db($CON, DB_NAME) or die("<strong>ERROR CODE : </strong> NEW_COM - 67");

	return $CON;
}

function NextID($f, $tbl, $base_num = '0', $cond = '')
{
	$cond_str = (trim($cond) != '') ? ' where ' . $cond : '';

	$query = "select max($f) from $tbl $cond_str";
	$result = sql_query($query, 'COM13');
	list($rid) = sql_fetch_row($result);

	if (!is_numeric($rid))
		$rid = 0;

	$rid++;

	if (!empty($base_num)) {
		$_id = GetXFromYID("select $base_num from sys_config");
		if ($rid < $_id)
			$rid = $_id;
	}

	return $rid;
}

function time_difference($time1, $time2)
{
    // Convert the string times to Unix timestamps
    $timestamp1 = strtotime($time1);
    $timestamp2 = strtotime($time2);

    // Calculate the difference in seconds
    $diff_seconds = abs($timestamp2 - $timestamp1);

    // Check the difference and return appropriate time unit
    if ($diff_seconds < 60) {
        return $diff_seconds . ' seconds';
    } elseif ($diff_seconds < 3600) {
        return floor($diff_seconds / 60) . ' minutes';
    } else {
        return floor($diff_seconds / 3600) . ' hours';
    }
}

function LogAttemptFront($property_id, $user_type, $user_name, $log_type, $fail_str, $login_area_str, $counter_id=0)
{
	$now = NOW;
	$session_id = session_id();
	$hostaddress = gethostbyaddr($_SERVER['REMOTE_ADDR']);
	$log_str = ($log_type=='S')? 'Login Successful': 'Login Failed';

	$q = "INSERT INTO log_signin_front (iPropertyID, iCounterID, cType, vUserName, vDateTime, vIPAddress, vSessionID, vState, vComment, vLoginArea, cStatus) VALUES ($property_id, '$counter_id', '$user_type', '$user_name', '$now', '$hostaddress', '$session_id', '$log_str', '$fail_str', '$login_area_str', '$log_type')";
	$r = sql_query($q, 'GNC.216');
}


function maskMobileNumber($mobileNumber)
{
	$maskedNumber = str_repeat('*', strlen($mobileNumber) - 4) . substr($mobileNumber, -4);
	return $maskedNumber;
}

function GetXFromYID($q)
{
	$str = false;
	$result = sql_query($q, 'COM25');

	if (sql_num_rows($result))
		list($str) = sql_fetch_row($result);

	return $str;
}

function GetXArrFromYID($q, $mode = "1")
{
	$arr = array();
	$r = sql_query($q, 'COM39');

	if (sql_num_rows($r)) {
		if ($mode == "2")
			for ($i = 0; list($x) = sql_fetch_row($r); $i++)
				$arr[$i] = $x;
		else if ($mode == "3")
			for ($i = 0; list($x, $y) = sql_fetch_row($r); $i++)
				$arr[$x] = $y;
		else if ($mode == "4")
			while ($a = sql_fetch_assoc($r))
				$arr[$a['I']] = $a;
		else
			while (list($x) = sql_fetch_row($r))
				$arr[$x] = $x;
	}

	return $arr;
}

function GetMaxRank($tbl, $cond = "", $fld = 'iRank')
{
	$cond = (strtoupper(trim($cond)) != "") ? " where " . $cond : "";

	$q = "select max($fld) from $tbl $cond";
	$r = sql_query($q, 'GEN.94');
	list($max) = sql_fetch_row($r);

	if ($max < 1)
		$max = 0;

	return ++$max;
}

function FillTreeData($selected, $ctr, $tp, $comp, $flds, $tbl, $cond, $fn = "", $class = "form-control")
{
	$display = ($tp == "COMBO" || $tp == "COMBO2") ? "" : "size=10";
	$cond = (strtoupper(trim($cond)) != 'N' && trim($cond) != '') ? " where " . $cond : "";
	$class_str = (trim($class) == "") ? "" : $class;

	$stat_fld = ($tp == "COMBO") ? ", 'A' " : ", cStatus";

	$q = "select " . $flds . ", iLevel " . $stat_fld . " from " . $tbl . $cond . " order by iRank, vName";
	$result = sql_query($q, 'GEN.112');
	$str = '<select name="' . $ctr . '" id="' . $ctr . '" class="' . $class_str . '" ' . $display . ' ' . $fn . '>' . "\n"; //

	if ($comp <> 'y' && $comp <> 'Y') {
		$str .= '<option value="0" selected> - select - </option>' . "\n";
	}

	while (list($id, $nm, $level, $stat) = sql_fetch_row($result)) {
		$stat_style = ($stat == "A" && $tp == "COMBO2") ? "" : ' style="background-color: #FFC5C5;"';
		$selected_str = (trim($selected) == trim($id)) ? "selected" : "";
		$space = GenerateSpace($level);
		$str .=  '<option value="' . $id . '" ' . $selected_str . '>' . $space . $nm . '</option>' . "\n";
	}

	$str .= '</select>' . "\n";
	return $str;
}

function sendFcmNotification2($deviceToken, $requestID, $requestType, $title, $body)
{
	// require_once realpath(dirname(__FILE__) . "/libs/google_client/vendor/autoload.php");

	$requestID = strval($requestID);
	// Initialize Google Client
	$client = new Google_Client();
	//$client->setAuthConfig('../deltin-one-firebase-adminsdk-fo0ep-80ce21da32.json');
	$client->setAuthConfig(DOCROOT . 'api/deltin-one-firebase-adminsdk-fo0ep-80ce21da32.json');

	$client->addScope('https://www.googleapis.com/auth/firebase.messaging');

	// Generate a fresh access token
	$token = $client->fetchAccessTokenWithAssertion()['access_token'];

	$payload = [
		'message' => [
			'token' => $deviceToken,
			// 'notification' => [
			//     'title' => "New Message from $name",
			//     'body' => $body, 
			// ],
			// 'notification' => [
			// 	'title' => '',
			// 	'body' => '',
			// ],
			'data' => [
				'type' => "requestDetails",
				'requestID' => $requestID,
				'requestype' => $requestType,
				'push' => 'true',
				'InApp' => 'true',
				'title' => $title,
				'body' => $body,
			],

			'android' => [
				'priority' => 'high',
			],
			   'apns' => [
                'headers' => [
                    'apns-priority' => '5',
                    //'apns-push-type' => 'alert', // required for iOS 13+
                    'apns-push-type' => 'background',
                ],
                'payload' => [
                    'aps' => [
                        // 'alert' => [
                        //     'title' => '',
                        //     'body' => '',
                        // ],
                        // 'sound' => 'default',
                        // 'badge' => 1,
                        'content-available' => 1, // important for background/killed
                    ],
                ],
            ],

		],
	];

	// Define the FCM URL for HTTP v1 API
	$fcmUrl = "https://fcm.googleapis.com/v1/projects/deltin-one/messages:send";

	// Use cURL to send the request
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $fcmUrl);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Authorization: Bearer ' . $token,
		'Content-Type: application/json',
	]);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

	// Execute the request
	$response = curl_exec($ch);
	$success = $response !== false;
	//var_dump($response);

	if (!$success) {
		error_log('cURL Error: ' . curl_error($ch));
	} else {
		error_log("Notification sent: " . $response);
	}

	// Close cURL
	curl_close($ch);

	return $success;
}
function sendFcmNotification3($deviceToken, $requestID, $requestType, $title, $body, $bigImage, $link, $version)
{
	// require_once realpath(dirname(__FILE__) . "/libs/google_client/vendor/autoload.php");

	$requestID = strval($requestID);
	// Initialize Google Client
	$client = new Google_Client();
	//$client->setAuthConfig('../deltin-one-firebase-adminsdk-fo0ep-80ce21da32.json');
	$client->setAuthConfig(DOCROOT . 'api/deltin-one-firebase-adminsdk-fo0ep-80ce21da32.json');

	$client->addScope('https://www.googleapis.com/auth/firebase.messaging');

	// Generate a fresh access token
	$token = $client->fetchAccessTokenWithAssertion()['access_token'];
	$version = intval($version);

	if($version >= 78){
		$payload = [
			'message' => [
				'token' => $deviceToken,
				'data' => [
					'type'       => "notification",
					'requestID'  => $requestID,
					'requestype' => $requestType,
					'push'       => 'true',
					'InApp'      => 'true',
					'title'      => $title,
					'body'       => $body,
					'large_icon' => $bigImage,
					'link'       => $link,
					'NewType'    => "true"
				],
				'notification' => [
					'title' => $title,
					'body'  => $body,
				],
				'android' => [
					'priority' => 'high',
					'notification' => [
						'image' => $bigImage,
						'sound' => 'diwali', 
					]
				],
				'apns' => [
					'headers' => [
						'apns-priority'   => '10',
						'apns-push-type'  => 'alert',
					],
					'payload' => [
						'aps' => [
							'alert' => [
								'title' => $title,
								'body'  => $body,
							],
							'badge'              => 1,
							'sound'              => 'diwali',
							'content-available'  => 1,
							'mutable-content'    => 1,
						],
					],
					'fcm_options' => [
						'image' => $bigImage, 
					],
				],
			],
		];
	} else {
		$payload = [
			'message' => [
				'token' => $deviceToken,
				// 'notification' => [
				//     'title' => "New Message from $name",
				//     'body' => $body, 
				// ],
				// 'notification' => [
				// 	'title' => '',
				// 	'body' => '',
				// ],
				'data' => [
					'type' => "notification",
					'requestID' => $requestID,
					'requestype' => $requestType,
					'push' => 'true',
					'InApp' => 'true',
					'title' => $title,
					'body' => $body,
					'large_icon' => $bigImage,
					'link' => $link,
					'NewType'=> "false"

				],

				'android' => [
					'priority' => 'high',
				],
				'apns' => [
					'headers' => [
						'apns-priority' => '5',
						//'apns-push-type' => 'alert', // required for iOS 13+
						'apns-push-type' => 'background',
					],
					'payload' => [
						'aps' => [
							// 'alert' => [
							//     'title' => '',
							//     'body' => '',
							// ],
							// 'sound' => 'default',
							// 'badge' => 1,
							'content-available' => 1, // important for background/killed
						],
					],
				],

			],
		];
	}
	// Define the FCM URL for HTTP v1 API
	$fcmUrl = "https://fcm.googleapis.com/v1/projects/deltin-one/messages:send";

	// Use cURL to send the request
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $fcmUrl);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'Authorization: Bearer ' . $token,
		'Content-Type: application/json',
	]);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

	// Execute the request
	$response = curl_exec($ch);
	$success = $response !== false;
	//var_dump($response);

	if (!$success) {
		error_log('cURL Error: ' . curl_error($ch));
	} else {
		error_log("Notification sent: " . $response);
	}

	// Close cURL
	curl_close($ch);

	return $success;
}

function createNotification($userID, $memberID, $deviceToken, $phoneNo, $refID, $refType, $requestType, $notificationText, $batchText, $status = 'A')
{
	// Lock the notify_batch table
	LockTable('notify_batch');
	$nbID = NextID('iNBID', 'notify_batch');
	$dtCreated = NOW;

	// Insert into notify_batch table
	$batchQuery = "INSERT INTO notify_batch(iNBID, iMemberID, iUserID, dtCreated, vText, vNotes) 
                   VALUES ('$nbID', '$memberID', '$userID', '$dtCreated', '$batchText', '$notificationText')";
	sql_query($batchQuery);
	UnlockTable();

	// Lock the notification table
	LockTable('notification');
	$notificationID = NextID('iNotificationID', 'notification');
	$dtSent = NOW;

	// Insert into notification table
	$notificationQuery = "INSERT INTO notification(iNotificationID, iMemberID, iNBID, vDeviceID, dtSent, vPhoneNo, iUserID, cUsertype, iRefID, cReftype, cRequestType, cStatus) 
                          VALUES ('$notificationID', '$memberID', '$nbID', '$deviceToken','$dtSent', '$phoneNo', '$userID', 'C', '$refID', '$refType', '$requestType', '$status')";
	sql_query($notificationQuery);
	UnlockTable();
}


function createNotification1($userID, $memberID, $deviceToken, $phoneNo, $refID, $refType, $requestType, $notificationText, $batchText, $status = 'A')
{
	// Lock the notify_batch table
	LockTable('notify_batch');
	$nbID = NextID('iNBID', 'notify_batch');
	$dtCreated = NOW;
	// Insert into notify_batch table
	$batchQuery = "INSERT INTO notify_batch(iNBID, iMemberID, iUserID, dtCreated, vText, vNotes) 
                   VALUES ('$nbID', '$memberID', '$userID', '$dtCreated', '$batchText', '$notificationText')";
	sql_query($batchQuery);
	UnlockTable();

	// Lock the notification table
	LockTable('notification');
	$notificationID = NextID('iNotificationID', 'notification');
	$dtSent = NOW;
	// Insert into notification table
	$notificationQuery = "INSERT INTO notification(iNotificationID, iMemberID, iNBID, vDeviceID, dtSent, vPhoneNo, iUserID, cUsertype, iRefID, cReftype, cRequestType, cStatus) 
                          VALUES ('$notificationID', '$memberID', '$nbID', '$deviceToken','$dtSent', '$phoneNo', '$userID', 'C', '$refID', '$refType', '$requestType', '$status')";
	sql_query($notificationQuery);
	UnlockTable();
}

// function FillTreeCombo($selected, $ctr, $tp, $comp, $cond, $fn="", $class="form-control")
function FillTreeCombo($selected, $ctr, $type, $comp, $values, $fn = "", $class = "form-control", $combo_type = "KEY_VALUE") //fill the values from an array
{
	$display = ($type <> "COMBO" && $type <> "COMBO2") ? "size=10" : "";

	$str = "<select name='$ctr' id='$ctr' class='$class' $display $fn>"; //  

	if (($comp <> "y") && ($comp <> "Y")) {
		if ($comp == '0')
			$str .= "<option value='0' selected> - select - </option>\n";
		elseif ($comp == '1')
			$str .= "<option value='0' selected> - main category - </option>\n";
		elseif ($comp == '2')
			$str .= "<option value='0' selected>MM</option>\n";
		elseif ($comp == '-1')
			$str .= "<option value='-1' selected> - select - </option>\n";
		else
			$str .= "<option value='0' selected> - select - </option>\n";
	}

	if ($combo_type == "KEY_VALUE") {
		foreach ($values as $key => $V) {
			$stat_style = ($V['status'] == 'A') ? "" : ' style="background-color: #FFC5C5;"';
			$select_str = ($selected == $key) ? "selected" : "";
			$space = GenerateSpace($V['level']);
			$str .=  '<option value="' . $key . '" ' . $select_str . $stat_style . '>' . $space . $V['text'] . '</option>' . "\n";
		}
	}

	$str .= "</select>";
	return $str;
}

function FillData($selected, $ctr, $tp, $comp, $flds, $tbl, $cond, $ord, $fn = "", $class = "form-control")
{
	$display = ($tp == "COMBO" || $tp == "COMBO2") ? "" : "size=10";
	$cond = (strtoupper(trim($cond)) != 'N' && trim($cond) != '') ? " where " . $cond : "";
	$class_str = (trim($class) == "") ? "" : $class;

	$stat_fld = ($tp == "COMBO") ? ", 'A' " : ", cStatus";

	$q = "select " . $flds . $stat_fld . " from " . $tbl . $cond . " order by " . $ord;
	$result = sql_query($q, 'GEN.141');
	$str = '<select name="' . $ctr . '" id="' . $ctr . '" class="' . $class_str . '" ' . $display . ' ' . $fn . '>' . "\n"; //

	if ($comp <> 'y' && $comp <> 'Y') {
		if ($comp == '0')
			$str .= '<option value="" selected> - Select - </option>' . "\n";
		else if ($comp == '-1')
			$str .= '<option value="" selected> - Select Hotel - </option>' . "\n";
		else if ($comp == '-2')
			$str .= '<option value="" selected> - Select RM - </option>' . "\n";
		elseif ($comp == '-6')
			$str .= '<option value="" selected> - Select - </option>' . "\n";
		else
			$str .= '<option value="0" selected> - Select - </option>' . "\n";
	}

	while (list($id, $nm, $stat) = sql_fetch_row($result)) {
		$stat_style = ($stat == "A" && $tp == "COMBO2") ? "" : ' style="background-color: #FFC5C5;"';
		$selected_str = (trim($selected) == trim($id)) ? "selected" : "";
		$str .=  '<option value="' . $id . '" ' . $selected_str . '>' . $nm . '</option>' . "\n";
	}

	$str .= '</select>' . "\n";
	return $str;
}

function FillCombo($selected, $ctr, $type, $comp, $values, $fn = "", $class = "form-control", $combo_type = "KEY_VALUE") //fill the values from an array
{
	$display = ($type <> "COMBO" && $type <> "COMBO2") ? "size=10" : "";

	$str = "<select name='$ctr' id='$ctr' class='$class' $display $fn>"; //  

	if (($comp <> "y") && ($comp <> "Y")) {
		if ($comp == '0')
			$str .= "<option value='' selected> - Select  - </option>\n";
		elseif ($comp == '-1')
			$str .= "<option value='' selected> - Select Rating  - </option>\n";
		elseif ($comp == '-2')
			$str .= "<option value='' selected> - Select Status  - </option>\n";
		elseif ($comp == '-3')
			$str .= "<option value='' selected> - Select Hotel  - </option>\n";
		elseif ($comp == '-4')
			$str .= "<option value='' selected> - Select Room  - </option>\n";
		elseif ($comp == '-5')
			$str .= "<option value='' selected> - Select RM  - </option>\n";
		elseif ($comp == '-6')
			$str .= "<option value='' selected> - Select Occupancy  - </option>\n";
		elseif ($comp == '-7')
			$str .= "<option value='' selected>Room Type</option>\n";
		elseif ($comp != '')
			$str .= "<option value='' selected> - " . $comp . " - </option>\n";
		else
			$str .= "<option value='0' selected> - select - </option>\n";
	}

	if ($combo_type == "KEY_VALUE") {
		if ($type == 'COMBO2') {
			foreach ($values as $key => $V) {
				$stat_style = ($V['status'] == 'A') ? "" : ' style="background-color: #FFC5C5;"';
				$select_str = ($selected == $key) ? "selected" : "";
				$str .=  '<option value="' . $key . '" ' . $select_str . $stat_style . '>' . $V['text'] . '</option>' . "\n";
			}
		} else {
			foreach ($values as $key_val => $var) {
				$ex = explode('~', $var);
				$stat_style = '';
				if (isset($ex[1]) && $ex[1] != 'A')
					$stat_style = ' style="background-color: #FFC5C5;"';

				$select_str = ($selected == $key_val) ? "selected" : "";
				$str .= '<option value="' . $key_val . '" ' . $select_str . $stat_style . '>' . $ex[0] . '</option>';
			}
		}
	} elseif ($combo_type == "KEY_IS_VALUE") {
		foreach ($values as $var) {
			$select_str = ($selected == $var) ? "selected" : "";
			$str .= "<option value='$var' $select_str> $var</option>";
		}
	} elseif ($combo_type == "SPLIT_FOR_KEY_VALUE") {
		foreach ($values as $var) {
			$v = explode("~", $var);
			$key = $v[0];
			$txt = $v[1];

			$select_str = ($selected == $key) ? "selected" : "";
			$str .= "<option value='$key' $select_str> $txt</option>";
		}
	} elseif ($combo_type == "SPLIT_FOR_OPTGROUP") {
		$last = '';
		$j = '1';
		foreach ($values as $varKEY => $varVALUE) {
			if ($last != $varVALUE['OPT_GROUP']) {
				if ($j != '1')
					$str .= '</optgroup>';

				$str .= '<optgroup label="' . $varVALUE['OPT_GROUP'] . '">';

				$last = $varVALUE['OPT_GROUP'];
				$j++;
			}

			$select_str = (isset($values[$selected]) && $selected == $varKEY) ? "selected" : "";
			$str .= "<option value='$varKEY' $select_str>" . $varVALUE['NAME'] . "</option>";
		}
		if ($j != '1')
			$str .= '</optgroup>';
	}

	$str .= "</select>";
	return $str;
}

function FillMultipleData($selected_arr, $ctr, $tp, $comp, $flds, $tbl, $cond, $ord, $fn = "")
{
	$display = (!empty($tp)) ? "size=" . $tp : "";
	$cond = (strtoupper(trim($cond)) != 'N') ? " where " . $cond : "";

	$q = "select " . $flds . " from " . $tbl . $cond . " order by " . $ord;
	$result = sql_query($q, 'COM190');

	$str = "<select name='" . $ctr . "[]' multiple='multiple' id='$ctr' class='multiselect-dropdown form-control' $display $fn>\n"; //  

	if ($comp <> 'y' && $comp <> 'Y') {
		if ($comp == '0')
			$str .= "<option value='0'> -- select -- </option>\n";
		else
			$str .= "<option value='0' selected> - select one - </option>\n";
	}

	while (list($id, $nm) = sql_fetch_row($result)) {
		$selected_str = (in_array($id, $selected_arr)) ? "selected" : "";
		$str .=  "<option value='$id' $selected_str>$nm</option>\n";
	}

	$str .= "</select>\n";
	return $str;
}

function FormatDate($date_val, $flag = "A")
{
	$dt = "";
	$date_val = !empty($date_val) ? trim($date_val) : "";

	if ($date_val != "" && $date_val != '0000-00-00' && $date_val != "0000-00-00 00:00:00") {
		$time_val = strtotime($date_val);

		if ($flag == "A")	$date_format = "d M";
		elseif ($flag == "B")	$date_format = "d M Y";
		elseif ($flag == "C")	$date_format = "d-m-Y h:i A";
		elseif ($flag == "D")	$date_format = "d M Y h:i A";
		elseif ($flag == "E")	$date_format = "H:i";
		elseif ($flag == "F")	$date_format = "d/m/y h:i a";
		elseif ($flag == "G")	$date_format = "d/m/Y";
		elseif ($flag == "H")	$date_format = "Y-m-d";
		elseif ($flag == "I")	$date_format = "D, F j";
		elseif ($flag == "J")	$date_format = "D, M j";
		elseif ($flag == "K")	$date_format = "d/m/y";
		elseif ($flag == "L")	$date_format = "M y";
		elseif ($flag == "M")	$date_format = "d M Y";
		elseif ($flag == "N")	$date_format = "d/m";
		elseif ($flag == "O")	$date_format = "d\<\b\\r\>M";
		elseif ($flag == "P")	$date_format = "d/m H:i";
		elseif ($flag == "Q")	$date_format = "d/M/y";
		elseif ($flag == "R")	$date_format = "m/y";
		elseif ($flag == "S")	$date_format = "Y-m";
		elseif ($flag == "T")	$date_format = "d M\<\b\\r\>D";
		elseif ($flag == "U")	$date_format = "dS F Y, H:i A";
		elseif ($flag == "V")	$date_format = "d-M-Y";
		elseif ($flag == "W")	$date_format = "d-M-Y H:i a";
		elseif ($flag == "X")	$date_format = "D, d M Y";
		elseif ($flag == "Y")	$date_format = "D, d M Y H:i a";
		elseif ($flag == "Z")	$date_format = "my";
		elseif ($flag == "1")	$date_format = "d-M-Y";
		elseif ($flag == "2")	$date_format = "d\<\s\u\p\>S\<\/\s\u\p\> F, Y";
		elseif ($flag == "3")	$date_format = "M d";
		elseif ($flag == "4")	$date_format = "h:i a";
		elseif ($flag == "5")	$date_format = "l, d F Y - H:i";
		elseif ($flag == "6")	$date_format = "dS M Y";
		elseif ($flag == "7")	$date_format = "g";
		elseif ($flag == "8")	$date_format = "i";
		elseif ($flag == "9")	$date_format = "Y";
		elseif ($flag == "10")	$date_format = "F d, Y H:i:s";
		elseif ($flag == "11")	$date_format = "d-m-Y";
		elseif ($flag == "12")	$date_format = "s";
		elseif ($flag == "13")	$date_format = "m";
		elseif ($flag == "14")	$date_format = "d.m.Y";
		elseif ($flag == "15")	$date_format = "d/m/y h:ia";
		elseif ($flag == "16") $date_format = "D, M d, H:i A";
		elseif ($flag == "17")	$date_format = "h:i";
		elseif ($flag == "18")	$date_format = "Y-m";
		elseif ($flag == "19")	$date_format = "F, d Y";
		elseif ($flag == "20")	$date_format = "F";
		elseif ($flag == "21")	$date_format = "M";
		elseif ($flag == "22")	$date_format = "m/d/Y";
		elseif ($flag == "23")	$date_format = "Ymd";
		elseif ($flag == "24")	$date_format = "d";
		elseif ($flag == "25")	$date_format = "Ym";
		elseif ($flag == "26")	$date_format = "d M Y h:i a";
		elseif ($flag == "27")	$date_format = "d/m/y h:ia";
		elseif ($flag == "28")	$date_format = "Y-m-d H:i:s";
		else $date_format = "d/m/y";

		$dt = date($date_format, $time_val);
	}

	return $dt;
}

function GetStatusImageString($mode, $status, $id, $ajax_flag = true)
{
	$str = "";
	if ($ajax_flag) {
		if ($status == "A") $str = '<button type="button" class="btn btn-success btn-icon btn-sm" onClick="ChangeStatus(this, \'' . $mode . '\',\'I\',\'' . $id . '\'); return false;"><i class="fa fa-check"></i></button>';
		else if ($status == 'P') $str = '<button class="btn btn-warning btn-icon btn-sm" onClick="ChangeStatus(this, \'' . $mode . '\',\'A\',\'' . $id . '\'); return false;">P</button>';
		else if ($status == 'X') $str = '<button class="btn btn-secondary btn-icon btn-sm" onClick="ChangeStatus(this, \'' . $mode . '\',\'A\',\'' . $id . '\'); return false;">E</button>';
		else $str = '<button class="btn btn-danger btn-icon btn-sm" onClick="ChangeStatus(this, \'' . $mode . '\',\'A\',\'' . $id . '\'); return false;"><i class="fa fa-times"></i></button>';
	} else {
		if ($status == "A") $str = '<span class="text-success"><i class="fa fa-check"></i></span>';
		else if ($status == 'P') $str = '<span class="text-warning">P</span>';
		else if ($status == 'X') $str = '<span class="text-secondary">E</span>';
		else $str = '<span class="text-danger"><i class="fa fa-times"></i></span>';
	}


	return $str;
}

function GetStatusImageString2($mode, $status, $id, $ajax_flag = true)
{
	$str = "";
	if($ajax_flag)
	{
		if($status=="A") $str = '<button type="button" class="btn btn-success btn-icon btn-sm" onClick="ChangeStatus(this, \''.$mode.'\',\'I\',\''.$id.'\'); return false;"><i class="fa fa-check"></i></button>';
		else if($status=='P') $str = '<button class="btn btn-warning btn-icon btn-sm" onClick="ChangeStatus(this, \''.$mode.'\',\'A\',\''.$id.'\'); return false;">P</button>';
		else if($status=='X') $str = '<button class="btn btn-secondary btn-icon btn-sm" onClick="ChangeStatus(this, \''.$mode.'\',\'A\',\''.$id.'\'); return false;">E</button>';
		else $str = '<button class="btn btn-danger btn-icon btn-sm" onClick="ChangeStatus(this, \''.$mode.'\',\'A\',\''.$id.'\'); return false;"><i class="fa fa-times"></i></button>';
	}
	else
	{
		if($status == "A") $str = '<span class="text-success">Approved</span>';
		else if($status == 'P') $str = '<span class="text-warning">Sent For Approval</span>';
		else if($status == 'D') $str = '<span class="text-warning">Draft</span>';
		else if($status == 'U') $str = '<span class="text-warning">Pending For Activation</span>';
		else if($status == 'I') $str = '<span class="text-danger">Inactive</span>';
		else if($status == 'X') $str = '<span class="text-danger">Deleted</span>';
		else $str = '<span class="text-danger"><i class="fa fa-times"></i></span>';
	}
	

	return $str;
}


function GetRMStatusImageString($mode, $status, $id, $ajax_flag = true)
{
	// $str = "";


	// if ($status == "A") $str = '<span class="text-success">A</i></span>';
	// else if ($status == 'R') $str = '<span class="text-secondary">R</span>';
	// else if ($status == 'X') $str = '<span class="text-secondary">E</span>';
	// else $str = '<span class="text-danger">I</span>';



	// return $str;
	$str = "";
	if ($ajax_flag) {
		if ($status == "A") $str = '<button type="button" class="btn btn-success btn-icon btn-sm" onClick="ChangeStatus(this, \'' . $mode . '\',\'I\',\'' . $id . '\'); return false;"><i class="fa fa-check"></i></button>';
		else if ($status == 'R') $str = '<button class="btn btn-warning btn-icon btn-sm" onClick="ChangeStatus(this, \'' . $mode . '\',\'A\',\'' . $id . '\'); return false;">R</button>';
		else if ($status == 'X') $str = '<button class="btn btn-secondary btn-icon btn-sm" onClick="ChangeStatus(this, \'' . $mode . '\',\'A\',\'' . $id . '\'); return false;">E</button>';
		else $str = '<button class="btn btn-danger btn-icon btn-sm" onClick="ChangeStatus(this, \'' . $mode . '\',\'A\',\'' . $id . '\'); return false;"><i class="fa fa-times"></i></button>';
	} else {
		if ($status == "A") $str = '<span class="text-success"><i class="fa fa-check"></i></span>';
		else if ($status == 'R') $str = '<span class="text-warning">P</span>';
		else if ($status == 'X') $str = '<span class="text-secondary">E</span>';
		else $str = '<span class="text-danger"><i class="fa fa-times"></i></span>';
	}


	return $str;
}


function GetYesNoImageString($mode, $status, $id, $ajax_flag = true)
{
	$str = "";
	if ($ajax_flag) {
		if ($status == "Y") $str = '<a onClick="ChangeYesNoStatus(this, \'' . $mode . '\',\'N\',\'' . $id . '\'); return false;">' . YES_IMG . '</a>';
		else $str = '<a onClick="ChangeYesNoStatus(this, \'' . $mode . '\',\'Y\',\'' . $id . '\'); return false;">' . NO_IMG . '</a>';
	} else {
		if ($status == "Y") $str = YES_IMG;
		else $str = NO_IMG;
	}

	return $str;
}

function GetFeaturedImageString($mode, $status, $id, $ajax_flag = true)
{
	$str = "";
	if ($ajax_flag) {

		if ($status == "Y") $str = '<a style="cursor:pointer;" onClick="ChangeFeatured(this, \'' . $mode . '\',\'N\',\'' . $id . '\'); return false;">' . FEATURED_IMG . '</a>';
		else $str = '<a style="cursor:pointer;" onClick="ChangeFeatured(this, \'' . $mode . '\',\'Y\',\'' . $id . '\'); return false;">' . UNFEATURED_IMG . '</a>';
	} else {
		if ($status == "Y") $str = FEATURED_IMG;
		else $str = UNFEATURED_IMG;
	}

	return $str;
}

function IsExistFile($file, $path)
{
	$file = trim($file);
	$path = trim($path);

	if (($file != "") && (strtoupper($file) != "NA")) {
		$f = $path . $file;
		//echo($f);
		//exit;
		if (file_exists($f))
			return 1;
	}

	return 0;
}

function DeleteFile($file, $path)
{
	$file = trim($file);
	$path = trim($path);

	if (($file != "") && (strtoupper($file) != "NA")) {
		$f = $path . $file;
		if (file_exists($f))
			unlink($f);
	}
}

function DisplayFormattedArray($arr)
{
	echo "<pre>";
	print_r($arr);
	echo "</pre>";
}

function NormalizeFilename($filename, $newname = "")
{
	$filename = trim($filename);
	$pos = strrpos($filename, ".");
	$str_nm = (trim($newname != "")) ? $newname : substr($filename, 0, $pos);
	$str_ext = substr($filename, $pos);

	$invalid_chars = array('`', '=', ' ', '\\', '[', ']', ';', '\'', ',', '/', '~', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '|', '{', '}', ':', '\"', '<', '>', '?');

	foreach ($invalid_chars as $I)
		$str_nm = str_replace($I, "-", $str_nm);

	$str_nm .= $str_ext;
	return $str_nm;
}

function ConvertFromYMDtoDMY($ymd_date, $tmode = false)
{
	$year = $mnth = $days = $hour = $mins = $secs = 0;

	if (trim($ymd_date) == "") return $ymd_date;
	elseif ($ymd_date == "0000-00-00 00:00:00" || $ymd_date == "0000-00-00") return "";

	if ($tmode) // time also included
	{
		$t_arr = explode(' ', $ymd_date);
		if (count($t_arr) < 2) return "";

		$ymd_date = $t_arr[0];
		$time_str = $t_arr[1];

		$tm_arr = explode(':', $time_str);
		if (count($tm_arr) < 3) return "";

		$hour = $tm_arr[0];
		$mins = $tm_arr[1];
		$secs = $tm_arr[2];
	}

	$dt_arr = explode('-', $ymd_date);
	if (count($dt_arr) < 3) return "";

	$year = $dt_arr[0];
	$mnth = $dt_arr[1];
	$days = $dt_arr[2];

	if ($tmode)
		$dmy_date = $days . "-" . $mnth . "-" . $year . " " . $hour . ":" . $mins . ":" . $secs;
	else
		$dmy_date = $days . "-" . $mnth . "-" . $year;

	return $dmy_date;
}

function ConvertFromDMYToYMD($dmy_date, $tmode = false)
{
	$year = $mnth = $days = $hour = $mins = $secs = 0;

	if (trim($dmy_date) == "") return $dmy_date;
	elseif ($dmy_date == "0000-00-00 00:00:00" || $dmy_date == "0000-00-00") return "";

	if ($tmode) // time also included
	{
		$t_arr = explode(' ', $dmy_date);
		if (count($t_arr) < 2) return "";

		$dmy_date = $t_arr[0];
		$time_str = $t_arr[1];

		$tm_arr = explode(':', $time_str);
		if (count($tm_arr) < 3) return "";

		$hour = $tm_arr[0];
		$mins = $tm_arr[1];
		$secs = $tm_arr[2];
	}

	$dt_arr = explode('-', $dmy_date);
	if (count($dt_arr) < 3) return "";

	$days = $dt_arr[0];
	$mnth = $dt_arr[1];
	$year = $dt_arr[2];

	if ($tmode)
		$ymd_date = $year . "-" . $mnth . "-" . $days . " " . $hour . ":" . $mins . ":" . $secs;
	else
		$ymd_date = $year . "-" . $mnth . "-" . $days;

	return $ymd_date;
}

function ParseStringForSQL($sqlstr)
{
	$tmp_str = trim($sqlstr);
	$tmp_str = stripslashes($tmp_str);
	$tmp_str = str_replace("'", "''", $tmp_str);
	$tmp_str = str_replace('\\', '\\\\', $tmp_str);
	return $tmp_str;
}

function CheckForXSS($string)
{
	$str = '';
	$x = array('onblur', 'onchange', 'onclick', 'ondblclick', 'onfocus', 'onkeydown', 'onkeypress', 'onkeyup', 'onmousedown', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onreset', 'onselect', 'onsubmit');
	$str = str_replace($x, "", $string);

	return $str;
}
function db_input($string)
{
    return addslashes(CheckForXSS($string)); // legacy-safe
}

function db_input2($string)
{
	$string = CheckForXSS($string);
	//return (get_magic_quotes_gpc())? htmlspecialchars(addslashes($string)): htmlspecialchars(addslashes($string));
	return htmlspecialchars(addslashes($string));
}

function db_output($string)
{
	$string = trim($string ?? '');
	return htmlspecialchars($string, ENT_QUOTES);
}

function db_output2($string)
{
	$string = trim($string ?? '');
	//if(!get_magic_quotes_gpc()) $string = stripslashes($string);
	$string = stripslashes($string);
	return htmlspecialchars_decode($string, ENT_QUOTES);
}

function Delay($number = 100)
{
	for ($i = 0; $i < $number; $i++);
}

function FormatNumber($number, $pad_len = 0, $mode = 'ind') // int: international; ind: indian
{
	$sign = ($number < 0) ? '-' : '';
	$number = abs($number);

	if ($mode == 'ind')
		$number = exp_to_dec($number);

	$dot = 	strrpos($number, ".");
	$int_buffer = array();

	if ($dot === false) {
		$int_part  = $number;
		$deci_part = "";
	} else {
		$int_part = substr($number, 0, $dot);
		$deci_part = substr($number, $dot + 1);
	}

	// echo '['.$mode . ' ' .$int_part.' . '.$deci_part.']<br>';

	if ($pad_len > 0) {
		if ($deci_part != '') {
			$deci_part_str = round('0.' . $deci_part, $pad_len);

			if ($deci_part_str >= 1) // decimals have rounded up to 1 => increment integer part...
				$int_part++;

			$deci_part = substr($deci_part_str, 2); // , $dot+1);
		}
	}

	if ($mode == 'ind') {
		$len = strlen($int_part);
		for ($i = $len - 1; $i >= 0; $i--)
			$int_buffer[$i] = substr($int_part, $i, 1);

		$i = 0;
		$int_part = "";
		foreach ($int_buffer as $digit) {
			$int_part = (($i == 3) || ($i == 5) || ($i == 7) || ($i == 9)) ? $digit . "," . $int_part : $int_part = $digit . $int_part;
			$i++;
		}
	} else if ($mode == 'int') {
		$int_part = number_format($int_part);
	}

	$number = $int_part;

	if ($pad_len > 0)
		$number .=  "." . str_pad($deci_part, $pad_len, "0");

	return $sign . $number;
}

// formats a floating point number string in decimal notation, supports signed floats, also supports non-standard formatting e.g. 0.2e+2 for 20
// e.g. '1.6E+6' to '1600000', '-4.566e-12' to '-0.000000000004566', '+34e+10' to '340000000000'
// Author: Bob
function exp_to_dec($float_str)
{
	// make sure its a standard php float string (i.e. change 0.2e+2 to 20)
	// php will automatically format floats decimally if they are within a certain range
	$float_str = (string)((float)($float_str));

	// if there is an E in the float string
	if (($pos = strpos(strtolower($float_str), 'e')) !== false) {
		// get either side of the E, e.g. 1.6E+6 => exp E+6, num 1.6
		$exp = substr($float_str, $pos + 1);
		$num = substr($float_str, 0, $pos);

		// strip off num sign, if there is one, and leave it off if its + (not required)
		if ((($num_sign = $num[0]) === '+') || ($num_sign === '-')) $num = substr($num, 1);
		else $num_sign = '';
		if ($num_sign === '+') $num_sign = '';

		// strip off exponential sign ('+' or '-' as in 'E+6') if there is one, otherwise throw error, e.g. E+6 => '+'
		if ((($exp_sign = $exp[0]) === '+') || ($exp_sign === '-')) $exp = substr($exp, 1);
		else trigger_error("Could not convert exponential notation to decimal notation: invalid float string '$float_str'", E_USER_ERROR);

		// get the number of decimal places to the right of the decimal point (or 0 if there is no dec point), e.g., 1.6 => 1
		$right_dec_places = (($dec_pos = strpos($num, '.')) === false) ? 0 : strlen(substr($num, $dec_pos + 1));
		// get the number of decimal places to the left of the decimal point (or the length of the entire num if there is no dec point), e.g. 1.6 => 1
		$left_dec_places = ($dec_pos === false) ? strlen($num) : strlen(substr($num, 0, $dec_pos));

		// work out number of zeros from exp, exp sign and dec places, e.g. exp 6, exp sign +, dec places 1 => num zeros 5
		if ($exp_sign === '+') $num_zeros = $exp - $right_dec_places;
		else $num_zeros = $exp - $left_dec_places;

		// build a string with $num_zeros zeros, e.g. '0' 5 times => '00000'
		$zeros = str_pad('', $num_zeros, '0');

		// strip decimal from num, e.g. 1.6 => 16
		if ($dec_pos !== false) $num = str_replace('.', '', $num);

		// if positive exponent, return like 1600000
		if ($exp_sign === '+') return $num_sign . $num . $zeros;
		// if negative exponent, return like 0.0000016
		else return $num_sign . '0.' . $zeros . $num;
	}
	// otherwise, assume already in decimal notation and return
	else return $float_str;
}

function DateDiff($date1, $date2, $mode = '1')
{
	list($yr1, $mnt1, $day1) = explode('-', $date1);
	$xx = gmmktime(0, 0, 0, $mnt1, $day1, $yr1);

	list($yr2, $mnt2, $day2) = explode('-', $date2);
	$xy = gmmktime(0, 0, 0, $mnt2, $day2, $yr2);

	$diff = $xy - $xx;
	$min = $diff / 60;
	$hr = $min / 60;
	$day = $hr / 24;

	if ($mode == '2') {
		$month_arr = array(1 => 31, 2 => 28, 3 => 31, 4 => 30, 5 => 31, 6 => 30, 7 => 31, 8 => 31, 9 => 30, 10 => 31, 11 => 30, 12 => 31);
		$y = $yr2 - $yr1;
		$m = $mnt2 - $mnt1;

		if ($m < 0) {
			$m = 12 + $m;
			$y -= 1;
		}

		$d = $day2 - $day1;

		if ($d < 0) {
			$mnt1 = ltrim($mnt1, '0');

			$_adj = $month_arr[$mnt1];
			if ($mnt1 == 2 && IsLeapYear($y1)) // if start month is Feb and is a Leap Year
				$_adj += 1;

			$d = $_adj + $d;
			$m -= 1;
		}

		$y_txt = ($y) ? ($y > 1) ? $y . " yr " : "1 yr " : "";
		$m_txt = ($m) ? ($m > 1) ? $m . " mnths " : "1 mnth " : "";
		$d_txt = ($d) ? ($d > 1) ? $d . " days " : "1 day " : "";

		if ($y_txt != '')
			$m_txt = "," . $m_txt;

		if ($m_txt != '')
			$d_txt = "," . $d_txt;


		$ret_val = $y_txt . $m_txt . $d_txt;
	} else
		$ret_val = $day;

	return $ret_val;
}

function IsLeapYear($yr)
{
	return ($yr % 4 == 0 && $yr % 100 != 0) ? true : false;
}

function DateTimeAdd($date, $dd = 0, $mm = 0, $yy = 0, $hh = 0, $nn = 0, $ss = 0, $format = "Y-m-d H:i:s")
{
	$d = date("Y-m-d H:i:s", strtotime($date));

	//echo $d . " ($dd, $mm, $yy) <br>";	
	$t_arr = explode(' ', $d);
	$date_str = $t_arr[0];
	$time_str = $t_arr[1];

	$tm_arr = explode(':', $time_str);
	$hour = $tm_arr[0];
	$mins = $tm_arr[1];
	$secs = $tm_arr[2];

	$dt_arr = explode('-', $date_str);
	$year = $dt_arr[0];
	$mnth = $dt_arr[1];
	$days = $dt_arr[2];


	//	echo "mktime($hour, $mins, $secs, ($mnth + $mm), ($days + $dd), ($year + $yy)) <br>";
	$t = mktime(($hour + $hh), ($mins + $nn), ($secs + $ss), ($mnth + $mm), ($days + $dd), ($year + $yy));

	if (empty($format)) $format = "Y-m-d H:i:s";
	$date = date($format, $t);

	return $date;
}

function NewlinesToBR($str, $replace_str = '<br />')
{
	return preg_replace("/(\r\n)+|(\n|\r)+/", $replace_str, $str);
}

function GetRelativeDateDesc($date)
{
	$str = 'Today';
	$d = DateDiff($date, TODAY);

	if ($d == 1) $str = 'yesterday';
	else if ($d > 1) $str = $d . ' days ago';
	else if ($d == -1) $str = 'tomorrow';
	else if ($d < -1) $str = abs($d) . ' days ahead';

	return $str;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////
function check_inject($sql_in)
{
	if (strstr($sql_in, '/*') || strstr($sql_in, '--') || stristr($sql_in, '<script>') || stristr($sql_in, '</script>'))
		return false;

	return true;
}

function shuffle_assoc(&$array)
{
	if (count($array) > 1) //$keys needs to be an array, no need to shuffle 1 item anyway
	{
		$keys = array_rand($array, count($array));

		foreach ($keys as $key)
			$new[$key] = $array[$key];

		$array = $new;
	}

	return true; //because it's a wannabe shuffle(), which returns true
}

function input_check_mailinj($value)
{
	# mail adress(ess) for reports...
	//$report_to = "noreply@goenchobalcao.com";

	# array holding strings to check...
	$suspicious_str = array("content-type:", "charset=", "mime-version:", "content-transfer-encoding:", "multipart/mixed", "bcc:");

	// remove added slashes from $value...
	$value = stripslashes($value);

	foreach ($suspicious_str as $suspect) {
		# checks if $value contains $suspect...
		if (eregi($suspect, strtolower($value))) {
			$ip = (empty($_SERVER['REMOTE_ADDR'])) ? 'empty' : $_SERVER['REMOTE_ADDR']; // replace this with your own get_ip function...
			$rf = (empty($_SERVER['HTTP_REFERER'])) ? 'empty' : $_SERVER['HTTP_REFERER'];
			$ua = (empty($_SERVER['HTTP_USER_AGENT'])) ? 'empty' : $_SERVER['HTTP_USER_AGENT'];
			$ru = (empty($_SERVER['REQUEST_URI'])) ? 'empty' : $_SERVER['REQUEST_URI'];
			$rm = (empty($_SERVER['REQUEST_METHOD'])) ? 'empty' : $_SERVER['REQUEST_METHOD'];

			die('Script processing cancelled: Your request contains text portions that are ' .
				'potentially harmful to this server. <em>Your input has not been sent!</em> Please use your ' .
				'browser\'s `back`-button to return to the previous page and try refreshing your input.</p>');
		}
	}
}

function CheckSPAM($string)
{
	/* $len=strlen($string);
	$tmp = "";

	for($i=0;$i<=$len;$i++)
	{
		$c=substr($string,$i,1);
		if( (ord($c)>=0 && ord($c)<=127) || ord($c)==156) $tmp .= $c;
		elseif(ord($c)==146)	$tmp .= chr(39);
		else	return 0;
	}

	return $tmp; */

	$len = strlen($string);

	for ($i = 0; $i <= $len; $i++) {
		$c = substr($string, $i, 1);
		if ((ord($c) >= 0 && ord($c) <= 127) || ord($c) == 156) {
		} else
			return false;
	}
	return true;
}

function GetFolderFileArr($DIR_UPLOAD, $DIR_PATH, $mode = 0)
{
	$image_arr = array();

	$dir_resource = opendir($DIR_UPLOAD);

	for ($i = 0; $file_name = readdir($dir_resource);)
		if (($file_name != ".") && ($file_name != "..") && (strtolower($file_name) != "thumbs.db") && file_exists($DIR_UPLOAD . $file_name))
			$image_arr[$i++] = $file_name;

	closedir($dir_resource);

	return $image_arr;
}

function EnsureValidMode($mode, $valid_modes, $default_mode)
{
	if (empty($mode) || !in_array($mode, $valid_modes))
		$mode = $default_mode;

	return $mode;
}

function Str2Arr($str)
{
	$arr = array();

	for ($i = 0; $i < strlen($str); $i++)
		$arr[$i] = substr($str, $i, 1);

	return $arr;
}

function GetFileName($filedir)
{
	$dir = opendir($filedir);

	while ($file_name = readdir($dir))
		if ($file_name != "." && $file_name != "..")
			return $file_name;
}

function ValidateNumber($num, $default = '0')
{
	if (!is_numeric($num))
		$num = $default;

	return $num;
}

function th($number, $flag = "")
{
	$suffix = "";

	$last_digit = substr($number, -1);

	if ($last_digit == "1")
		$suffix = "st";
	elseif ($last_digit == "2")
		$suffix = "nd";
	elseif ($last_digit == "3")
		$suffix = "rd";
	else
		$suffix = "th";

	if ($flag == "A")
		$suffix = "<sup>" . $suffix . "</sup>";

	return $number . $suffix;
}

function DownloadFile($PATH, $UPLOAD, $file_name)
{
	header("Pragma: public");
	header("Expires: 0");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");
	header("Content-Disposition: attachment; filename=" . basename($PATH . $file_name) . ";");
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: " . filesize($UPLOAD . $file_name));
	readfile($PATH . $file_name);
	exit();
}

function SetSessionInfo($str)
{
	global $sess_info, $lbl_display;
	$sess_info = $str;
	$lbl_display = ($sess_info != "") ? '' : 'none';
}

function WriteFile($file_name, $file_str)
{
	$handle = fopen(PRINT_UPLOAD . $file_name, 'w+');
	fwrite($handle, $file_str);
	fclose($handle);
}

function PrintMultiLine($str, $contd = 1, $limit = 40)
{
	$print_str = '';
	$str = trim($str);

	if ($str != '') {
		$len = strlen($str);
		$x_str = ($len > $limit) ? substr($str, 0, $limit) : $str;
		$print_str = $x_str . NEWLINE;


		if ($contd) {
			$limit -= 3;
			$contd_str = ' ..';
		} else
			$contd_str = '';

		if ($len > $limit)
			for ($a = $limit + 3; $a < $len; $a += $limit)
				$print_str .= $contd_str . substr($str, $a, $limit) . NEWLINE;
	}

	return $print_str;
}

function SearchFromMemory($flag, $disp_url)
{
	global $_SESSION;
	$url_str = $disp_url;

	if (isset($_SESSION[PROJ_SESSION_ID]->srch_ctrl_arr[$flag])) {
		$srch_ctrl_arr = $_SESSION[PROJ_SESSION_ID]->srch_ctrl_arr[$flag];
		$url_str = $disp_url . "?srch_mode=QUERY";

		foreach ($srch_ctrl_arr as $ctrl_nm => $ctrl_val) {
			if ($ctrl_nm == "srch_mode" || $ctrl_nm == "FORM")
				continue;

			$url_str .= "&" . $ctrl_nm . "=" . $ctrl_val;
		}
	}

	header("location: " . $url_str);
	exit;
}

function GenerateSQLInsert($tbl, $q)
{
	$str = '';

	$r = sql_query($q, 'COM.2687');
	for ($i = 1; $assoc = sql_fetch_assoc($r); $i++) {
		$str .= 'insert into ' . $tbl . ' values (';

		$fld_i = 0;
		foreach ($assoc as $val) {
			if ($fld_i++) $str .= ',';
			$str .= '"' . db_input($val) . '"';
		}

		$str .= ');' . NEWLINE;
	}

	return $str;
}

function DFA($arr)
{
	echo "<pre>";
	print_r($arr);
	echo "</pre>";
}

function EnsureValueUBound($val, $ubound = 9999)
{
	$val = floatval($val);

	if ($val > $ubound)
		$val = 0; // $ubound;

	return $val;
}

function TimeDiff($dt1, $dt2, $timestamp = false, $mode = 'm')
{
	if (!$timestamp) {
		$t1 = strtotime($dt1);
		$t2 = strtotime($dt2);
	} else {
		$t1 = $dt1;
		$t2 = $dt2;
	}

	$secs = $t1 - $t2;

	if ($mode == 's')
		$x = $secs;
	else if ($mode == 'm') {
		$mins = $secs / 60;
		$x = $mins;
	} else if ($mode == 'h') {
		$mins = $secs / 60;
		$hrs = $mins / 60;
		$x = $hrs;
	} else if ($mode == 'd') {
		$mins = $secs / 60;
		$hrs = $mins / 60;
		$day = $hrs / 24;
		$x = $day;
	}

	return $x;
}

function format_uptime($seconds)
{
	$secs = intval($seconds % 60);
	$mins = intval($seconds / 60 % 60);
	$hours = intval($seconds / 3600 % 24);
	$days = intval($seconds / 86400);
	$uptimeString = '';

	if ($days > 0) {
		$uptimeString .= $days;
		$uptimeString .= (($days == 1) ? " day" : " days");
	}

	if ($hours > 0) {
		$uptimeString .= (($days > 0) ? ", " : "") . $hours;
		$uptimeString .= (($hours == 1) ? " hour" : " hours");
	}

	if ($mins > 0) {
		$uptimeString .= (($days > 0 || $hours > 0) ? ", " : "") . $mins;
		$uptimeString .= (($mins == 1) ? " minute" : " minutes");
	}

	if ($secs > 0) {
		$uptimeString .= (($days > 0 || $hours > 0 || $mins > 0) ? ", " : "") . $secs;
		$uptimeString .= (($secs == 1) ? " second" : " seconds");
	}

	return $uptimeString;
}

function ClearFolder($filedir)
{
	$dir = opendir($filedir);

	while ($file_name = readdir($dir))
		if ($file_name != "." && $file_name != "..")
			unlink($filedir . $file_name);
}

function BackupDB($file_name)
{
	DeletePDF(BACKUP_UPLOAD);
	system('mysqldump -u' . DB_USERNAME . ' -p' . DB_PASSWORD . ' ' . DB_NAME . ' | gzip > ' . BACKUP_UPLOAD . $file_name, $done);
}

function CheckSqlTables(&$msg, $fast = true, $return_text = true)
{
	global $table_count;
	$is_corrupted = false;
	$msg = "";

	$q = "show tables";
	$r = sql_query($q, 'UTL_CD.77');
	$table_count = sql_num_rows($r);
	if ((!$r || $table_count <= 0) && $return_text)
		$msg = '<tr><td colspan="5" class="err">Could not iterate database tables</td></tr>';

	$checktype = "";
	if ($fast)
		$checktype = "FAST";

	for ($i = 1; list($table_name) = sql_fetch_row($r); $i++) {
		$q1 = "check table $table_name $checktype";
		$r1 = sql_query($q1, 'UTL_CD.92');

		if ((!$r1 || sql_num_rows($r1) <= 0) && $return_text) {
			$msg = '<tr><td colspan="5" class="err">Could not status for table ' . $table_name . '</td></tr>';
			continue;
		}

		# Seek to last row
		mysql_data_seek($r1, sql_num_rows($r1) - 1);
		$a = sql_fetch_assoc($r1);

		$chk_str = '&nbsp;';
		$css = '';
		if ($a['Msg_type'] != "status") {
			$css = 'red';
			$chk_str = '<input type="checkbox" name="chk[]" id="chk_' . $i . '" value="' . $table_name . '">';
			$is_corrupted = true;
		}

		if ($return_text) {
			$msg .= '<tr>';
			$msg .= '<td align="right" class="' . $css . '">' . $i . '.</td>';
			$msg .= '<td align="center" class="' . $css . '">' . $chk_str . '</td>';
			$msg .= '<td class="' . $css . '"><label for="chk_' . $i . '">' . $table_name . '</label></td>';
			$msg .= '<td align="center" class="' . $css . '">' . $a['Msg_type'] . '</td>';
			$msg .= '<td class="' . $css . '">' . $a['Msg_text'] . '</td>';
			$msg .= '</tr>';
		}
	}

	return $is_corrupted;
}

function ForceOut($err = false)
{
	$str = ($err === false) ? '' : '?err=' . $err;
	session_destroy(); // destroy all data in session
	header("location:index.php" . $str);
	exit;
}

function ForceOut2($err = false)
{
	$str = ($err === false) ? '' : '?err=' . $err;
	session_destroy(); // destroy all data in session
	header("location:lock_screen.php" . $str);
	exit;
}

function ForceOut3($err = false)
{
	$str = ($err === false) ? '' : '?err=' . $err;
	session_destroy(); // destroy all data in session
	header("location:authorise.php" . $str);
	exit;
}

function ForceOutFront($err = false, $return_url = 'register.php')
{
	if (strpos($return_url, "?")) $str = ''; //($err===false)? '': '&err='.$err;
	else $str = ''; //($err===false)? '': '?err='.$err;

	unset($_SESSION[PROJ_FRONT_SESSION_ID]);
	//session_destroy(); // destroy all data in session
	header("location:" . $return_url . $str);
	exit;
}

function Passwordify($password)
{
	$passarr = array();
	$passarr[0] = substr($password, 0, 8);
	$passarr[1] = substr($password, 8, 8);
	$passarr[2] = substr($password, 16, 8);
	$passarr[3] = substr($password, 24, 8);
	$passarr[4] = substr($password, 32, 8);
	$passarr[5] = substr($password, 40, 8);
	$passarr[6] = substr($password, 48, 8);
	$passarr[7] = substr($password, 56, 8);
	$passarr[8] = substr($password, 64, 4);

	$ptrarr = array();
	$ptrarr[0] = substr($passarr[8], 0, 1);
	$ptrarr[1] = substr($passarr[8], 1, 1);
	$ptrarr[2] = substr($passarr[8], 2, 1);
	$ptrarr[3] = substr($passarr[8], 3, 1);

	$k = 1;
	$genpass = array();
	foreach ($ptrarr as $key => $value) {
		$genpass[$value] = $passarr[$key + $k];
		$k++;
	}

	$password = $genpass[1] . $genpass[2] . $genpass[3] . $genpass[4];

	return $password;
}

function LogAttempt($user_name, $log_type, $fail_str)
{
	$now = NOW;
	$hostaddress = gethostbyaddr($_SERVER['REMOTE_ADDR']);
	$log_str = ($log_type == 'S') ? 'Login Successful' : 'Login Failed';

	$q = "insert into log_signin values('$user_name', '$now', '$hostaddress', '" . session_id() . "', '$log_str', '$fail_str', '$log_type')";
	$r = sql_query($q, 'GNC.216');
}

function ChangeStatus($url, $id, $status, $desc_str, $tbl, $pk_fld)
{
	$result = false;

	$q = 'update ' . $tbl . ' set cStatus=\'' . $status . '\' where ' . $pk_fld . '=' . $id;
	$r = sql_query($q, 'GEN.1085');

	if (true) // sql_affected_rows())
	{
		$rstr = ($status == 'A') ? $desc_str . " record has been activated" : $desc_str . " record has been blocked";
		$str = GetStatusImageString($url, $status, $id);
		$result = "1~$str~$rstr";
	}

	return $result;
}

function UpdateLog($file_name, $str)
{
	// $handle = fopen(PRINT_UPLOAD . $file_name, 'a');
	// fwrite($handle, $str);
	// fclose($handle);	// *	
}

function GetIDString($q)
{
	$arr = array();
	$arr[0] = 0;

	$r = sql_query($q, 'GEN.1494');
	while (list($val) = sql_fetch_row($r))
		$arr[$val] = $val;

	return implode(',', $arr);
}

function GetIDString2($q)
{
	$arr = array();

	$r = sql_query($q, 'GEN.1494');
	while (list($val) = sql_fetch_row($r))
		$arr[$val] = $val;

	return implode(', ', $arr);
}

function SetupCalendar($date_val, $txt_ctrl, $date_type = 'D', $clr_flag = true, $txt_flag = false)
{
	$btn_ctrl = 'btn' . substr($txt_ctrl, 3);
	$clr_ctrl = 'clr' . substr($txt_ctrl, 3);

	if ($txt_flag) {
		$btn_ctrl = $txt_ctrl;
	}

	if ($date_type == 'DT') // datetime...
	{
		$format = '%d-%m-%Y %H:%M:00';
		$showtime = 'true';
		$css = 'datetime';
	} else {
		$format = '%d-%m-%Y';
		$showtime = 'false';
		$css = 'date';
	}

	$str = '';
	$str .= '<input type="text" name="' . $txt_ctrl . '" id="' . $txt_ctrl . '" value="' . $date_val . '" class="' . $css . ' box" readonly />';

	if (!$txt_flag)
		$str .= '<input name="' . $btn_ctrl . '" type="button" id="' . $btn_ctrl . '" value="..." class="date box">';

	$str .= '<script type="text/javascript">';
	$str .= 'Calendar.setup({inputField:"' . $txt_ctrl . '",ifFormat:"' . $format . '",showsTime:' . $showtime . ',button:"' . $btn_ctrl . '",singleClick:true,step:2});';
	$str .= '</script>';

	if ($clr_flag && !$txt_flag)
		$str .= '<input type="button" name="' . $clr_ctrl . '" id="' . $clr_ctrl . '" value="!" class="date box" onClick="this.form.' . $txt_ctrl . '.value=\'\';">';

	return $str;
}

function ParseID($id)
{
	return (is_numeric($id) && !empty($id)) ? $id : '0';
}

function FillRadioData($selected, $ctrl, $q, $comp = 'Y', $fn_str = "")
{
	$str = '';

	$xtra_arr = array();
	if ($comp <> 'y' && $comp <> 'Y') {
		//		if($comp=='0')
		$xtra_arr['0'] = 'NA';
	}

	foreach ($xtra_arr as $key => $txt) {
		$ctrl_id = $ctrl . '_' . strtolower($key);
		$chk_str = ($key == $selected) ? 'checked' : '';
		$str .= '<input type="radio" name="' . $ctrl . '" id="' . $ctrl_id . '" value="' . $key . '" ' . $chk_str . ' ' . $fn_str . '><label class="label0" for="' . $ctrl_id . '">' . $txt . '</label>';
	}

	$r = sql_query($q, 'COM.1573');

	while (list($key, $txt) = sql_fetch_row($r)) {
		$ctrl_id = $ctrl . '_' . strtolower($key);
		$chk_str = ($key == $selected) ? 'checked' : '';
		$str .= '<input type="radio" name="' . $ctrl . '" id="' . $ctrl_id . '" value="' . $key . '" ' . $chk_str . ' ' . $fn_str . '><label class="label0" for="' . $ctrl_id . '">' . $txt . '</label>';
	}

	return $str;
}

function empty_date($dt)
{
	$dt = trim($dt);
	return (empty($dt) || $dt == '0000-00-00' || $dt == '0000-00-00 00:00:00') ? true : false;
}

function SetCode($x_name, $mode = 'A', $len = 3)
{
	$x_code = '';
	$x_name = trim($x_name);

	if ($mode == 'B') // acronym
	{
		$arr = explode(' ', $x_name); //.split(' ');
		for ($i = 0; ($i < count($arr) && $i < $len); $i++)
			$x_code .= substr($arr[$i], 0, 1);
	} else {
		$x_name_len = strlen($x_name);

		if ($x_name_len > 0)
			$x_code = ($x_name_len > $len) ? $x_name . substr(0, $len) : $x_name;
	}

	return strtoupper($x_code);
}

function CHK_ARR2Str($chk_arr)
{
	$str = '';

	if (count($chk_arr)) {
		foreach ($chk_arr as $x_str => $x_count)
			$str .= ', ' . $x_str;

		$str = substr($str, 2);
	}

	return $str;
}

function FillRadiosYN($is_selected, $ctrl, $yes_str = 'Yes', $no_str = 'No', $width = 90, $fn_str = '')
{

	$chk_str = ($is_selected) ? 'checked' : '';

	$str = '<div class="onoffswitch" style="width:' . $width . 'px;">';
	$str .= '<input type="checkbox" name="onoffswitch" class="onoffswitch-checkbox" id="myonoffswitch" ' . $chk_str . '>';
	$str .= '<label class="onoffswitch-label" for="myonoffswitch">';
	$str .= '<div class="onoffswitch-inner"><div class="onoffswitch-active">' . $yes_str . '</div><div class="onoffswitch-inactive">' . $no_str . '</div></div>';
	$str .= '<div class="onoffswitch-switch" style="right:' . ($width - 32) . 'px;"></div>';
	$str .= '</label>';
	$str .= '</div>';

	return $str;
}

function GetPriorityImg($priority)
{
	global $PRIORITY_ARR;
	if (!isset($PRIORITY_ARR[$priority])) $priority = '4';
	return '<img src="./images/icons/priority' . $priority . '.png" align="absmiddle" title="' . $PRIORITY_ARR[$priority] . '" />';
}

function FillTime($selected, $ctr, $comp = 'n', $fn = "", $class = "", $mode = "12") //fill the values from an array
{
	$str = "<select name='$ctr' id='$ctr' class='$class' $fn>"; //  

	if (strtolower($comp) != 'y')
		$str .= "<option value='00:00:00' selected>hh:mm</option>\n";

	if ($mode == "24") {
		for ($i = 0; $i < 24; $i++) {
			$hh = ($i < 10) ? '0' . $i : $i;

			for ($j = 0; $j < 60; $j = $j + 30) {
				$mm = ($j < 10) ? '0' . $j : $j;

				$var = $hh . ':' . $mm;
				$select_str = ($selected == $var) ? "selected" : "";
				$str .= '<option value="' . $var . ':00" ' . $select_str . '>' . $var . '</option>';
			}
		}
	} else {
		for ($a = 5; $a <= 22; $a++) {
			$suffix = ($a >= 12) ? 'pm' : 'am';
			$i = ($a > 12) ? $a - 12 : $a;
			$hh = ($i < 10) ? '0' . $i : $i;
			$hhx = ($a < 10) ? '0' . $a : $a;

			for ($j = 0; $j < 60; $j = $j + 30) {
				$mm = ($j < 10) ? '0' . $j : $j;

				$var = $hh . ':' . $mm;
				$varx = $hhx . ':' . $mm . ':00';
				$select_str = ($selected == $varx) ? "selected" : "";
				// $str .= "<option value='$var' $select_str>$var $suffix</option>";
				$str .= '<option value="' . $varx . '" ' . $select_str . '>' . $var . ' ' . $suffix . '</option>';
			}
		}
	}

	$str .= "</select>";
	return $str;
}

function FillCheckboxList($selected_arr, $ctrl, $value_arr, $mode = '1')
{
	$str = '';

	foreach ($value_arr as $key => $txt) {
		$key = strval($key);
		$ctrl_id = str_replace('[]', '', $ctrl) . '_' . strtolower($key);

		if ($mode == '2')
			$chk_str = (in_array($key, $selected_arr)) ? 'checked' : ''; // $chk_str = ($key == $selected)? 'checked': '';
		else
			$chk_str = (isset($selected_arr[$key])) ? 'checked' : '';

		$str .= '<input type="checkbox" name="' . $ctrl . '" id="' . $ctrl_id . '" value="' . $key . '" ' . $chk_str . '> <label for="' . $ctrl_id . '">' . $txt . '</label>&nbsp;&nbsp;';
	}

	return $str;
}

function NotFoundRow($colspan = 1, $msg = '')
{
	$colspan_str = ($colspan > 1) ? ' colspan="' . $colspan . '"' : '';
	return '<tr><td ' . $colspan_str . '>No Records Found...</td></tr>';
}

function PrintRow($arr, $width, $opt_pad_str = ' ', $newline_flag = true)
{
	$str = '';
	foreach ($width as $i => $w) {
		$space = $data = '';
		$pad_str = $opt_pad_str;
		$pad_type = STR_PAD_RIGHT;

		if (isset($arr[$i])) {
			if (is_array($arr[$i])) {
				$data = $arr[$i][0];

				if (isset($arr[$i][1]) && $arr[$i][1] == 'L') $pad_type = STR_PAD_LEFT;
				else if (isset($arr[$i][1]) && $arr[$i][1] == 'C') $pad_type = STR_PAD_BOTH;
				if (isset($arr[$i][2])) $pad_str = $arr[$i][2];
				if (isset($arr[$i][3])) $space = ' ';
			} else
				$data = $arr[$i];
		}

		$str .= $space . str_pad($data, $w, $pad_str, $pad_type);
	}

	if ($newline_flag)
		$str .= NEWLINE;

	return $str;
}

function GetAnniversaryDt($x_dt)
{
	return (!empty($x_dt) && $x_dt != '0000-00-00') ? date('M d', strtotime($x_dt)) : '';
}

function FillCheckboxGrid($div_id, $selected_arr, $ctrl_name, $data_arr)
{
	$str = '<div id="' . $div_id . '" style="background-color:#ffffff;border:1px solid #000000;position:absolute;height:200px;overflow:scroll;display:none;">';

	foreach ($data_arr as $key => $value) {
		$ctrl_id = $ctrl_name . '_' . $key;
		$_checked = (in_array($key, $selected_arr)) ? 'checked' : '';
		$str .= '<span class="search_ctrl" style="width:200px;">';
		$str .= '<input type="checkbox" name="' . $ctrl_name . '[]" id="' . $ctrl_id . '" value="' . $key . '" ' . $_checked . '/>';
		$str .= '<label for="' . $ctrl_id . '">' . $value . '</label></span> ';
	}
	$str .= '</div>';

	return $str;
}

function ParseStringForXML($val)
{
	$invalid_char_arr = array('%E2%80%99', '%E2%80%93', '%E2%80%A6');

	$val = trim($val);
	$val = strip_tags($val);								// remove html tags
	$val = urlencode($val);									// encode (makes it easier to find & replace chars)
	$val = str_replace($invalid_char_arr, '%27', $val);		// replace invalid chars
	$val = htmlentities(urldecode($val));					// decode back to txt and then handle special chars

	return $val;
}

function EncodeParam($param)
{
	$rand = rand();
	$crypt_str = md5($rand);
	// $crypt_str = substr($crypt_str,0,12);

	$len = strlen($param);
	$a = substr($crypt_str, 0, 1);
	$start = hexdec($a);

	$a = substr($crypt_str, 0, intval($start + 1));
	$b = substr($crypt_str, intval($start + 1 + $len + 1));
	$x =  $a . $len . $param . $b;

	return $x;
}

function DecodeParam($crypt_param)
{
	$a = substr($crypt_param, 0, 1);
	$start = hexdec($a);
	$len = intval(substr($crypt_param, $start + 1, 1));
	$param = substr($crypt_param, intval($start + 1 + 1), $len);
	return $param;
}

function FirstDateOfMonth($date) // as Y-m-d
{
	list($y, $m, $d) = explode('-', $date);
	return $y . '-' . $m . '-01';
}

function MonthDiff($date1, $date2)
{
	list($y1, $m1, $d1) = explode('-', $date1);
	list($y2, $m2, $d2) = explode('-', $date2);

	return (($y2 - $y1) * 12) + ($m2 - $m1) + 1;
}

function EnsureReportStartDate($dfrom, $time_flag = false, $dmy_flag = true)
{
	if ($dmy_flag) $dfrom = ConvertFromDMYToYMD($dfrom, $time_flag);
	$start_date = ($time_flag) ? START_DATE . ' 00:00:00' : START_DATE;
	if ($dfrom < $start_date) $dfrom = $start_date;
	return ($dmy_flag) ? ConvertFromYMDToDMY($dfrom, $time_flag) : $dfrom;
}

function MultiSort($a, $b)
{
	$args = explode('~', USORT_ORDER);

	$i = 0;
	$c = count($args);
	$cmp = 0;
	while ($cmp == 0 && $i < $c) {
		list($key, $is_asc) = explode(':', $args[$i]);

		$cmp = ($is_asc) ? strcmp($a[$key], $b[$key]) : strcmp($b[$key], $a[$key]);
		$i++;
	}

	return $cmp;
}

function GetFirstDayOfWeek($date, $is_ymd = true)
{
	$today_dayno = date("w", strtotime($date)); // wht day is it?
	$format = ($is_ymd) ? 'Y-m-d' : 'd-m-Y';
	return DateTimeAdd($date, (WEEK_START_DAY - $today_dayno), 0, 0, 0, 0, 0, $format); // get the 1st day of the given wk, adjust for offset
}

function GetQtrFromMonth($m = THIS_MONTH) // StartDateX
{
	$mn = $m - QTR_MONTH_OFFSET;
	$mn = AdjustMonthValues($mn);
	return ceil($mn / 3);
}

function AdjustMonthValues($mn)
{
	if ($mn < 0) $mn = 12 + $mn + 1;
	elseif ($mn == 0) $mn = 12;
	elseif ($mn > 12) $mn = $mn % 12;

	return $mn;
}

function SummarizeDataArr($arr)
{
	foreach ($arr as $ref => $A)
		echo $ref . ': ' . array_sum($A) . '<br />';
}

function ListDataArr($arr)
{
	foreach ($arr as $ref => $A)
		foreach ($A as $b_id => $b_val)
			if ($b_val != 0)
				echo $ref . ': ' . $b_id . ' = ' . $b_val . '<br />';
}

function ListCalcDataArrByBatch($arr)

{
	$a = array();

	foreach ($arr as $ref => $A)
		foreach ($A as $b_id => $b_val) {
			if (!isset($a[$b_id])) $a[$b_id] = 0;

			if ($b_val != 0)
				$a[$b_id] += $b_val;
		}

	foreach ($a as $id => $val)
		if ($val != 0)
			echo $id . ': ' . $val . '<br />';
}

function IsDate($date, $is_dmy = false)
{
	//echo $date.'<br/>';

	$x = false;

	$date = trim($date);
	if (!empty($date) && strpos($date, '-')) {
		$d = explode('-', $date);

		if (count($d) == 3)
			if ((!$is_dmy && checkdate($d[1], $d[2], $d[0])) || checkdate($d[1], $d[0], $d[2]))
				$x = true;
	}

	return $x;
}

function JustID(&$val, $default = 0) // $mode: INTEGER/ REAL
{
	JustNumeric($val, 'INTEGER');
}

function JustNumeric(&$val, $mode = 'REAL', $default = 0) // $mode: INTEGER/ REAL
{
	$val = trim($val);
	$val = ($mode == 'INTEGER') ? intval($val) : floatval($val);
	if (!is_numeric($val)) $val = $default;
}

function FormatDateForIMS($date_val)
{
	if (!empty($date_val)) {
		$dt = date("Y-m-d", strtotime($date_val));
		$y1 = date("Y", strtotime($date_val));
		$y2 = date("Y");
		$d = DateDiff($dt, TODAY);

		if ($d) {
			$date_format_str = "M j";

			if ($y1 < $y2)
				$date_format_str .= ", Y";
		} else
			$date_format_str = "H:i";

		return date($date_format_str, strtotime($date_val));
	} else
		return '-NA-';
}

function FormatDateForIMS2($date_val)
{
	$dt = date("Y-m-d", strtotime($date_val));
	$y1 = date("Y", strtotime($date_val));
	$y2 = date("Y");
	$d = DateDiff($dt, TODAY);

	if ($d) {
		$date_format_str = "M j";

		if ($y1 < $y2)
			$date_format_str .= ", Y";

		$date_format_str .= " H:i a";
	} else
		$date_format_str = "M j, H:i a";

	return date($date_format_str, strtotime($date_val));
}

function FormatDateForIMS3($date_val)
{
	if (!empty($date_val)) {
		$dt = date("Y-m-d", strtotime($date_val));
		$y1 = date("Y", strtotime($date_val));
		$y2 = date("Y");
		$d = DateDiff($dt, TODAY);

		if ($d) {
			$date_format_str = "M j";

			if ($y1 < $y2)
				$date_format_str .= ", Y";

			$dSTR = date($date_format_str, strtotime($date_val));
		} else {
			$minutes = round(abs(strtotime(CURRENTTIME) - strtotime(date('H:i:s', strtotime($date_val)))) / 60, 2);
			if ($minutes >= 5 && $minutes < 60) $date_format_str = "i";
			elseif ($minutes >= 60) $date_format_str = "H";
			else $date_format_str = '';

			if ($date_format_str == 'i') $dSTR = date($date_format_str, strtotime($date_val)) . ' mins';
			elseif ($date_format_str == 'H') $dSTR = date($date_format_str, strtotime($date_val)) . ' hours';
			else $dSTR = 'just now';

			//$dSTR .= ' '.date('H:i:s', strtotime($date_val)).' =>'.CURRENTTIME.' =>'.$minutes.' =>'.$date_format_str;
		}

		return $dSTR;
	} else
		return '-NA-';
}

function FormatDateForIMS4($date_val)
{
	$dt = date("Y-m-d", strtotime($date_val));
	$y1 = date("Y", strtotime($date_val));
	$y2 = date("Y");
	$d = DateDiff($dt, TODAY);

	if ($d) {
		$date_format_str = "M j, Y";
	} else
		$date_format_str = "M j, H:i a";

	return date($date_format_str, strtotime($date_val));
}

function MustString($val)
{
	return (trim($val) == '') ? 1 : 0;
}

function MustID($val)
{
	JustID($val);
	return ($val < 1) ? 1 : 0;
}

function MustNumeric($val, $mode = 'REAL', $min_value = 0, $max_value = 0)
{
	JustNumberic($val, $mode);
	return ($val < $min_value || $val > $max_value) ? 1 : 0;
}

function EnsureDateTimeDuration(&$dtstart, &$dtend, $min_diff = 30) // min_diff is expressed in minutes
{
	$start = strtotime($dtstart);
	$end = strtotime($dtend);

	$diff = (strtotime($dtend) - strtotime($dtstart)) / 60;

	if ($diff < $min_diff)
		$dtend = DateTimeAdd($dtstart, 0, 0, 0, 0, $min_diff, 0);
}

function GetPageHeader($page_orientation = 'P', $page_prefix = false)
{
	global $is_pdf;

	if ($is_pdf) {
		if ($page_prefix) $page_orientation .= '.' . $page_prefix;
		return '[PAGE_START][' . $page_orientation . ']';
	}

	return ''; // <div align="center">&nbsp;</div>';
}

function CalcSQMetersToSQFeet($val)
{
	return $val * 10.76391;
}

function IsValidFile($file_type, $extension, $type, $size = false, $max_file_size = false)
{
	global $IMG_TYPE, $DOC_TYPE, $IMG_FILE_TYPE, $DOC_FILE_TYPE;

	$str = false;

	if ($type == 'P') {
		if (in_array($extension, $IMG_TYPE))
			$str = true;
	} elseif ($type == 'D') {
		if (in_array($extension, $DOC_TYPE))
			$str = true;
	}

	return $str;
}

function GenerateDayDropDown($ctrl, $sDate = '', $fn = '')
{
	$str = '<select class="from-day inputbox" name="' . $ctrl . '" id="' . $ctrl . '"' . $fn . '>';
	for ($i = '1'; $i <= 31; $i++) {
		$j = str_pad($i, '2', '0', STR_PAD_LEFT);
		$selected = ($sDate == $j) ? ' selected="true"' : '';
		$str .= '<option value="' . $j . '"' . $selected . '> ' . $i . ' </option>';
	}
	$str .= '</select>';

	return $str;
}

function GenerateMonthDropDown($ctrl, $sDate = '', $fn = '')
{
	$mLeft = 12 - CURRENT_MONTH;
	$mGenerate = 24 +  $mLeft;
	$str = '<select class="from-month inputbox" name="' . $ctrl . '" id="' . $ctrl . '"' . $fn . '>';
	for ($i = '0'; $i <= $mGenerate; $i++) {
		$date = DateTimeAdd(TODAY, 0, $i, 0, 0, 0, 'Y-m-d');
		$value = date("Y", strtotime($date)) . '-' . date("m", strtotime($date));
		$name = date("F", strtotime($date)) . '&nbsp;' . date("Y", strtotime($date));

		$selected = ($sDate == $value) ? ' selected="true"' : '';

		$str .= '<option value="' . $value . '"' . $selected . '> ' . $name . ' </option>';
	}
	$str .= '</select>';

	return $str;
}

function FillMultiCombo($selected, $ctr, $type, $comp, $values, $fn = "", $class = "box", $combo_type = "KEY_VALUE") //fill the values from an array
{

	$display = ($type <> "COMBO") ? "size=10" : "";

	$str = "<select name='" . $ctr . "[]' id='$ctr' multiple='multiple' class='$class' $display $fn>"; //  

	if (($comp <> "y") && ($comp <> "Y")) {
		if ($comp == '0')
			$str .= "<option value='0' selected> - select - </option>\n";
		elseif ($comp == '-1')
			$str .= "<option value='0'> - Select Hotel - </option>\n";
		elseif ($comp == '2')
			$str .= "<option value='0' selected>MM</option>\n";
		elseif ($comp == '-1')
			$str .= "<option value=''>- Select TID -</option>\n";
		else
			$str .= "";
	}

	if ($combo_type == "KEY_VALUE") {
		foreach ($values as $key_val => $var) {
			$select_str = (isset($selected[$key_val]) && $selected[$key_val] == $key_val) ? "selected" : "";
			$str .= "<option value='$key_val' $select_str> $var</option>";
		}
	} elseif ($combo_type == "KEY_IS_VALUE") {
		foreach ($values as $var) {
			$select_str = (isset($selected[$var]) && $selected[$var] == $var) ? "selected" : "";
			$str .= "<option value='$var' $select_str> $var</option>";
		}
	} elseif ($combo_type == "SPLIT_FOR_KEY_VALUE") {
		foreach ($values as $var) {
			$v = explode("~", $var);
			$key = $v[0];
			$txt = $v[1];

			$select_str = (isset($selected[$key]) && $selected[$key] == $key) ? "selected" : "";
			$str .= "<option value='$key' $select_str> $txt</option>";
		}
	} elseif ($combo_type == "SPLIT_FOR_OPTGROUP") {
		$last = '';
		$j = '1';
		foreach ($values as $varKEY => $varVALUE) {
			if ($last != $varVALUE['OPT_GROUP']) {
				if ($j != '1')
					$str .= '</optgroup>';

				$str .= '<optgroup label="' . $varVALUE['OPT_GROUP'] . '">';

				$last = $varVALUE['OPT_GROUP'];
				$j++;
			}

			$select_str = (isset($selected[$varKEY]) && $selected[$varKEY] == $varKEY) ? "selected" : "";
			$str .= "<option value='$varKEY' $select_str>" . $varVALUE['NAME'] . "</option>";
		}
		if ($j != '1')
			$str .= '</optgroup>';
	}

	$str .= "</select>";
	return $str;
}

function SuggestCode()
{
	$arr = array();

	$len = rand(10, 12);

	// atleast 1 uppercase char
	$a_len = rand(1, $len - 3);
	// echo 'a_len: '.$a_len.'<br>';

	for ($i = 0; $i < $a_len; $i++)
		$arr[$i] = chr(rand(65, 90));

	$ctr = $i;

	// atleast 1 lowercase char
	$b_len = rand(1, $len - 2 - $a_len);
	// echo 'b_len: '.$b_len.'<br>';

	for (; $i < ($ctr + $b_len); $i++)
		$arr[$i] = chr(rand(97, 122));

	$ctr = $i;

	// atleast 1 number
	$c_len = rand(1, $len - 1 - $a_len - $b_len);
	// echo 'c_len: '.$c_len.'<br>';

	for (; $i < ($ctr + $c_len); $i++)
		$arr[$i] = rand(0, 9);

	// DFA($arr);
	shuffle($arr);
	// DFA($arr);

	$str = '';
	foreach ($arr as $a)
		$str .= $a;

	return $str;
}

function chop_words($str, $words = 20, $limit = 0, $suffix = ' ...')
{
	//string  $str --The input string
	//$words  The number of words to return, default 20, 0 to skip
	//$limit  Maximum length of the returned string 
	//string  $suffix  The string to append to the input if shortened.

	if ($limit) $limit -= strlen($suffix);

	for ($i = 0, $ix = 0; $i < $words; $i++)
		if (($is = strpos($str, ' ', $ix)) !== false) {
			if ($limit && $is + 1 > $limit)
				break;

			$ix = $is + 1;
		} else
			return $str;

	return substr($str, 0, $ix) . $suffix;
}

function FillRadios2($selected, $ctrl, $value_arr, $fn_str = '')
{
	$str = '';

	foreach ($value_arr as $key => $txt) {
		$ctrl_id = $ctrl . '_' . strtolower($key);
		$chk_str = ($key === $selected) ? 'checked' : '';

		$str .= '<div class="md-radio">';
		$str .= '<input type="radio" name="' . $ctrl . '" id="' . $ctrl_id . '" value="' . $key . '" class="md-radiobtn" ' . $chk_str . ' ' . $fn_str . ' /><label for="' . $ctrl_id . '"> <span></span> <span class="check"></span> <span class="box"></span>' . $txt . '</label>';
		$str .= '</div>';
	}

	return $str;
}

function GetAccessCountry()
{
	$str = '';

	$ip = $_SERVER['REMOTE_ADDR']; // the IP address to query
	$query = @unserialize(file_get_contents('http://ip-api.com/php/' . $ip));
	if ($query && $query['status'] == 'success') {
		$str = $query['countryCode'];
		if (isset($query['country']))
			$str .= '~' . $query['country'];
		if (isset($query['city']))
			$str .= '~' . $query['city'];
		if (isset($query['regionName']))
			$str .= '~' . $query['regionName'];
	}

	return $str;
}

function getBrowser()
{
	$u_agent = $_SERVER['HTTP_USER_AGENT'];
	$bname = 'Unknown';
	$platform = 'Unknown';
	$version = "";

	//First get the platform?
	if (preg_match('/linux/i', $u_agent)) {
		$platform = 'linux';
	} elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
		$platform = 'mac';
	} elseif (preg_match('/windows|win32/i', $u_agent)) {
		$platform = 'windows';
	}

	// Next get the name of the useragent yes separately and for good reason.
	if (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) {
		$bname = 'Internet Explorer';
		$ub = "MSIE";
	} elseif (preg_match('/Firefox/i', $u_agent)) {
		$bname = 'Mozilla Firefox';
		$ub = "Firefox";
	} elseif (preg_match('/Chrome/i', $u_agent)) {
		$bname = 'Google Chrome';
		$ub = "Chrome";
	} elseif (preg_match('/Safari/i', $u_agent)) {
		$bname = 'Apple Safari';
		$ub = "Safari";
	} elseif (preg_match('/Opera/i', $u_agent)) {
		$bname = 'Opera';
		$ub = "Opera";
	} elseif (preg_match('/Netscape/i', $u_agent)) {
		$bname = 'Netscape';
		$ub = "Netscape";
	} else
		$ub = '';

	// Finally get the correct version number.
	$known = array('Version', $ub, 'other');
	$pattern = '#(?<browser>' . join('|', $known) .
		')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
	if (!preg_match_all($pattern, $u_agent, $matches)) {
		// we have no matching number just continue
	}

	// See how many we have.
	$i = count($matches['browser']);
	if ($i != 1) {
		//we will have two since we are not using 'other' argument yet
		//see if version is before or after the name
		if (strripos($u_agent, "Version") < strripos($u_agent, $ub)) {
			$version = $matches['version'][0];
		} else {
			$version = $matches['version'][1];
		}
	} else {
		$version = $matches['version'][0];
	}

	// Check if we have a number.
	if ($version == null || $version == "") {
		$version = "?";
	}

	return array(
		'userAgent' => $u_agent,
		'name'      => $bname,
		'version'   => $version,
		'platform'  => $platform,
		'pattern'    => $pattern
	);
}

function GetUniqueIDs($arr)
{
	$x = array();

	if (count($arr))
		foreach ($arr as $a)
			if (is_array($a))
				$x = $x + $a;

	$x[0] = 0;

	return array_unique($x);
}

function GetTaxBreakUpForProduct($prod_amount, $tax_perc = '12', $tax_region = 'L')
{
	$str = '';

	$basePrice = (100 / (100 + $tax_perc)) * $prod_amount;
	$taxAMT = ($basePrice * $tax_perc) / 100;

	$sgst = $cgst = $igst = '0';
	if ($tax_region == 'L') {
		$halfTAXPERC = ($tax_perc / 2);
		$halfTAXAMT = ($taxAMT / 2);

		$sgst = $cgst = $halfTAXAMT;
	} else
		$igst = $taxAMT;

	$str = $basePrice . '~' . $sgst . '~' . $cgst . '~' . $igst;

	return $str;
}


function GetTaxForProduct($prod_amount, $tax_perc = '12', $tax_region = 'L')
{
	$str = '';

	$taxAMT = ($prod_amount * $tax_perc) / 100;

	$sgst = $cgst = $igst = '0';
	if ($tax_region == 'L') {
		$halfTAXPERC = ($tax_perc / 2);
		$halfTAXAMT = ($taxAMT / 2);

		$sgst = $cgst = $halfTAXAMT;
	} else
		$igst = $taxAMT;

	$str = $prod_amount . '~' . $sgst . '~' . $cgst . '~' . $igst;

	return $str;
}

function GetCESSTaxCalculation($prod_amount, $tax_perc, $type = 'I')
{
	if ($type == 'I') {
		$basePrice = (100 / (100 + $tax_perc)) * $prod_amount;
		$taxAMT = ($basePrice * $tax_perc) / 100;
	} else {
		$basePrice = $prod_amount;
		$taxAMT = ($prod_amount * $tax_perc) / 100;
	}

	$str = $basePrice . '~' . $taxAMT;

	return $str;
}

function GetLatLong($address)
{
	$address = str_replace(" ", "+", $address);

	//$json = file_get_contents("http://maps.google.com/maps/api/geocode/json?address=$address&sensor=false&region=$region");
	$json = file_get_contents("http://maps.google.com/maps/api/geocode/json?address=$address&sensor=false");
	$json = json_decode($json);

	$lat = $json->{'results'}[0]->{'geometry'}->{'location'}->{'lat'};
	$long = $json->{'results'}[0]->{'geometry'}->{'location'}->{'lng'};
	return $lat . ',' . $long;
}

function GenerateRandomCode($len, $fld, $tbl)
{
	$str = '';

	$arr = array();
	for ($i = 0; $i < $len; $i++)
		$arr[$i] = rand(0, 9);

	shuffle($arr);
	$str = '';
	foreach ($arr as $a)
		$str .= $a;

	if (!empty($fld) && !empty($tbl)) {
		$eXIST = GetXFromYID('select count(*) from ' . $tbl . ' where ' . $fld . '="' . $str . '"');
		if (!empty($eXIST) && $eXIST != '-1')
			$str = GenerateRandomCode($len, $fld, $tbl);
	}


	return $str;
}

function LogAdminUpdates($user_id, $mode, $table, $id)
{
	global $sess_user_level;
	$now = NOW;

	$q = "insert into log_adminsignin values ('$user_id', '$sess_user_level', '$now', '$mode', '$table', '$id')";
	$r = sql_query($q, 'LAU.216');
}

function TrimData($arr)
{
	foreach ($arr as $d_key => $d_val)
		$arr[$d_key] = trim($d_val);

	return $arr;
}

function LockTable($tbl, $mode = 'WRITE')
{
	$q = 'LOCK TABLE ' . $tbl . ' ' . $mode;
	$r = sql_query($q);
}

function UnlockTable()
{
	$q = 'UNLOCK TABLES';
	$r = sql_query($q);
}

function FillRadios($selected, $ctrl, $value_arr, $fn_str = '')
{
	$str = '';

	$str .= '<div class="kt-radio-inline">';
	foreach ($value_arr as $key => $txt) {
		$ctrl_id = $ctrl . '_' . strtolower($key);
		$chk_str = ($key === $selected) ? 'checked' : '';

		$str .= '<label class="kt-radio kt-radio--solid" for="' . $ctrl_id . '"><input type="radio" name="' . $ctrl . '" id="' . $ctrl_id . '" value="' . $key . '" ' . $chk_str . ' ' . $fn_str . ' /> ' . $txt . '<span></span></label> ';
	}

	$str .= '</div>';

	return $str;
}

function FillRadiosWithBr($selected, $ctrl, $value_arr, $fn_str = '')
{
	$str = '';

	$str .= '<div class="kt-radio-inline">';
	foreach ($value_arr as $key => $txt) {
		$ctrl_id = $ctrl . '_' . strtolower($key);
		$chk_str = ($key === $selected) ? 'checked' : '';

		$str .= '<label class="kt-radio kt-radio--solid" for="' . $ctrl_id . '"><input type="radio" name="' . $ctrl . '" id="' . $ctrl_id . '" value="' . $key . '" ' . $chk_str . ' ' . $fn_str . ' /> ' . $txt . '<span></span></label><br />';
	}

	$str .= '</div>';

	return $str;
}

function FormatAmount($number)
{
	$str = '';

	$a = array('1' => '10000000', '2' => '100000', '3' => '1000');
	$b = array('1' => 'Cr', '2' => 'L', '3' => 'K');

	$j = 0;
	foreach ($a as $key => $value) {
		if ($number >= $value) {
			$str = $number / $value;
			$str .= $b[$key];

			$j++;
			break;
		}
	}

	if ($j == 0)
		$str = $number;

	return $str;
}

function Get6MonthArr()
{
	$result = array();

	$Month_From = date("m", strtotime("-6 month"));
	$Year_From = date("Y", strtotime("-6 month"));
	$Month_To = date("m");
	$Year_To = date("Y");

	$tMonth = $Month_From;
	$tYear = $Year_From;

	while ($tYear <= $Year_To) {
		while (($tMonth <= 12 && $tYear < $Year_To) || ($tMonth <= $Month_To && $tYear == $Year_To)) {
			$result[] = FormatDate($tYear . '-' . str_pad($tMonth, 2, 0, STR_PAD_LEFT) . '-01', '18');
			/*$result[] = array(
				"month" => $tMonth,
				"year" => $tYear,
			);*/

			$tMonth++;
		}

		$tMonth = 1;
		$tYear++;
	}

	return $result;
}
function GetLinkedURLS($id)
{
	$URLS = array();
	$_uq = "select vUrl from menu_dat where cStatus='A' and iMenuID=" . $id;
	$_ur = sql_query($_uq, '');
	if (sql_num_rows($_ur)) {
		while (list($URL) = sql_fetch_row($_ur))
			array_push($URLS, $URL);
	}

	return $URLS;
}

function GetActiveLink($currentFILE, $ARR)
{
	$str = '';

	if (!empty($ARR) && count($ARR)) {
		if ($ARR['HREF'] == $currentFILE || in_array($currentFILE, $ARR['URLS']))
			$str = ' class="mm-active" id="scrollSideMenu"';
		else {
			if ($ARR['IS_SUB'] == 'Y' && !empty($ARR['MENU']) && count($ARR['MENU'])) {
				foreach ($ARR['MENU'] as $sKEY2 => $sVALUE2) {
					if ($sVALUE2['HREF'] == $currentFILE || in_array($currentFILE, $sVALUE2['URLS']))
						$str = ' class="mm-active" id="scrollSideMenu"';
				}
			}
		}
	}

	return $str;
}

function MultiDimensionalArraySort($array, $on, $order = SORT_ASC)
{

	$new_array = array();
	$sortable_array = array();

	if (count($array) > 0) {
		foreach ($array as $k => $v) {
			if (is_array($v)) {
				foreach ($v as $k2 => $v2) {
					if ($k2 == $on) {
						$sortable_array[$k] = $v2;
					}
				}
			} else {
				$sortable_array[$k] = $v;
			}
		}

		switch ($order) {
			case SORT_ASC:
				asort($sortable_array);
				break;
			case SORT_DESC:
				arsort($sortable_array);
				break;
		}

		foreach ($sortable_array as $k => $v) {
			$new_array[$k] = $array[$k];
		}
	}

	return $new_array;
}

function LastOnlineSeen($loginDate)
{
	$str = '';

	$date = FormatDate($loginDate, 'H');
	if ($date == TODAY) {
		$diff = abs(strtotime($loginDate) - strtotime(NOW));
		$years   = floor($diff / (365 * 60 * 60 * 24));
		$months  = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
		$days    = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24) / (60 * 60 * 24));
		$hours   = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24 - $days * 60 * 60 * 24) / (60 * 60));
		$minuts  = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24 - $days * 60 * 60 * 24 - $hours * 60 * 60) / 60);
		$seconds = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24 - $days * 60 * 60 * 24 - $hours * 60 * 60 - $minuts * 60));

		if (!empty($hours)) {
			if ($hours == '1') $str = $hours . ' hour ago';
			else $str = $hours . ' hours ago';
		} elseif (!empty($minuts)) {
			if ($minuts == '1') $str = $minuts . ' mins ago';
			else $str = $minuts . ' mins ago';
		} elseif (!empty($seconds)) {
			if ($seconds <= '30') $str = 'Now';
			else $str = $seconds . ' sec ago';
		} else
			$str = 'Now';
	} else
		$str = FormatDate($loginDate, 'B');

	return $str;
}

function LastOnlineSeen2($loginDate)
{
	$str = '';

	$date = FormatDate($loginDate, 'H');
	if ($date == TODAY) {
		$diff = abs(strtotime($loginDate) - strtotime(NOW));
		$years   = floor($diff / (365 * 60 * 60 * 24));
		$months  = floor(($diff - $years * 365 * 60 * 60 * 24) / (30 * 60 * 60 * 24));
		$days    = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24) / (60 * 60 * 24));
		$hours   = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24 - $days * 60 * 60 * 24) / (60 * 60));
		$minuts  = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24 - $days * 60 * 60 * 24 - $hours * 60 * 60) / 60);
		$seconds = floor(($diff - $years * 365 * 60 * 60 * 24 - $months * 30 * 60 * 60 * 24 - $days * 60 * 60 * 24 - $hours * 60 * 60 - $minuts * 60));

		if (!empty($hours)) {
			if ($hours == '1') $str = $hours . ' hour ago';
			else $str = $hours . ' hours ago';
		} elseif (!empty($minuts)) {
			if ($minuts == '1') $str = $minuts . ' mins ago';
			else $str = $minuts . ' mins ago';
		} elseif (!empty($seconds)) {
			if ($seconds <= '30') $str = 'Now';
			else $str = $seconds . ' sec ago';
		} else
			$str = 'Now';
	} else
		$str = FormatDate($loginDate, '26');

	return $str;
}

function SendWebPushrPushNotifications($title, $message, $target_url, $PIC, $sender_id)
{
	$end_point = 'https://api.webpushr.com/v1/notification/send/sid';
	$http_header = array(
		"Content-Type: Application/Json",
		"webpushrKey: c7f99b30703b86d0c9dd79685e2e69ea",
		"webpushrAuthToken: 21423"
	);

	/*$req_data = array(
		'title' 			=> "Notification title", //required
		'message' 		=> "Notification message", //required
		'target_url'	=> 'https://www.webpushr.com', //required
		'sid'		=> '36252' //required
	);*/

	$req_data = array(
		'title' 			=> $title, //required
		'message' 		=> $message, //required
		'target_url'	=> $target_url, //required
		'sid'		=> $sender_id, //required
		'icon'		=> 'https://pwa.thatlifestylecoach.com/assets/img/icon/192x192.png', //required
		'image'			=> $PIC,
	);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
	curl_setopt($ch, CURLOPT_URL, $end_point);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req_data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	//echo $response;
}

function SendWebPushrPushNotificationsToAll($title, $message, $target_url, $PIC)
{
	$end_point = 'https://api.webpushr.com/v1/notification/send/all';
	$http_header = array(
		"Content-Type: Application/Json",
		"webpushrKey: c7f99b30703b86d0c9dd79685e2e69ea",
		"webpushrAuthToken: 21423"
	);
	$req_data = array(
		'title' 		=> $title, //required
		'message' 		=> $message, //required
		'target_url'	=> $target_url, //required
		//'name'			=> 'Test campain',
		'icon'			=> 'https://pwa.thatlifestylecoach.com/assets/img/icon/192x192.png',
		'image'			=> $PIC,
		'auto_hide'		=> 1,
		//'expire_push'	=> '5m',
		//'send_at'		=> '2019-10-10 19:31 +5:30',
		/*'action_buttons'=> array(	
			array('title'=> 'Demo', 'url' => 'https://www.webpushr.com/demo'),
			array('title'=> 'Rates', 'url' => 'https://www.webpushr.com/pricing')
		)*/
	);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
	curl_setopt($ch, CURLOPT_URL, $end_point);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req_data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($ch);
	//echo $response;
}

function SendMail($from = '', $from_name = '', $to = '', $cc = '', $bcc = '', $reply_to = '', $subject = '', $content = '', $template = 'N', $image = '')
{
	require_once("class.phpmailer.php");

	$mail_body = '';
	if ($template == 'Y') $mail_body = file_get_contents(SITE_ADDRESS . 'mail_body.php');
	if (!empty($mail_body)) {
		$mail_body = str_replace('<IMAGE>', $image, $mail_body);
		$mail_body = str_replace('<CONTENT>', $content, $mail_body);
	} else
		$mail_body = $content;

	$Mail = new PHPMailer();
	$Mail->From = $from;
	$Mail->FromName = $from_name;
	$Mail->AddAddress($to);
	if (!empty($cc)) $Mail->AddCC($cc);
	if (!empty($bcc)) $Mail->AddBCC($bcc);
	if (!empty($reply_to)) $Mail->AddReplyTo($reply_to);

	$Mail->WordWrap = 50; // set word wrap
	$Mail->IsHTML(true);
	$Mail->MsgHTML($mail_body);
	$Mail->Subject = $subject;
	$Mail->Send();
}

function url_get_contents($Url)
{
	if (!function_exists('curl_init')) {
		die('CURL is not installed!');
	}
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $Url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$output = curl_exec($ch);
	curl_close($ch);
	return $output;
}

function SendMailReply($from = '', $from_name = '', $to = '', $cc = '', $bcc = '', $reply_to = '', $subject = '', $content = '', $subject_user = "", $reply_str = '', $template = 'N', $image = '', $file = "")
{
	require_once("class.phpmailer.php");

	$mail_body = '';
	if ($template == 'Y') $mail_body = url_get_contents(SITE_ADDRESS . 'mail_body.php');

	if (!empty($mail_body)) {
		$mail_body = str_replace('<IMAGE>', $image, $mail_body);
		$mail_body = str_replace('<CONTENT>', $content, $mail_body);
	} else
		$mail_body = $content;

	$Mail = new PHPMailer();
	$Mail->From = $from;
	$Mail->FromName = $from_name;
	$Mail->AddAddress($to);
	if (!empty($cc)) $Mail->AddCC($cc);
	if (!empty($bcc)) $Mail->AddBCC($bcc);
	if (!empty($reply_to)) $Mail->AddReplyTo($reply_to);

	$Mail->WordWrap = 50; // set word wrap
	$Mail->IsHTML(true);
	$Mail->MsgHTML($mail_body);
	$Mail->Subject = $subject;
	// $Mail->Send();

	/*if(isset($FILES['myfiles'])) 
	{
		$Mail->AddAttachment($FILES['myfiles']['tmp_name'],$FILES['myfiles']['name']);
	}		*/

	if (!empty($reply_str)) {
		$reply_content = "";
		if ($template == 'Y') {
			$reply_content = url_get_contents(SITE_ADDRESS . 'mail_body.php');
			$reply_content = str_replace('<IMAGE>', $image, $reply_content);
			$reply_content = str_replace('<CONTENT>', $reply_str, $reply_content);
		} else
			$reply_content = $reply_str;

		$AutoMail = new PHPMailer();
		$AutoMail->From = $from;
		$AutoMail->FromName = $from_name;
		$AutoMail->AddAddress($reply_to);
		$AutoMail->AddReplyTo($to);
		$AutoMail->WordWrap = 50;
		$AutoMail->IsHTML(true);
		$AutoMail->Subject = $subject_user;
		$AutoMail->MsgHTML($reply_content);
		$AutoMail->Send();
	}

	if ($Mail->Send()) {
		return 'OK';
	} else {
		return 'Mail Sending Failed';
	}
}

function Generate4DigitRandomCode()
{
	$arr = array();

	$len = 5;

	// atleast 1 uppercase char
	$a_len = rand(1, 1);
	// echo 'a_len: '.$a_len.'<br>';

	for ($i = 0; $i < $a_len; $i++)
		$arr[$i] = chr(rand(65, 90));

	$ctr = $i;

	// atleast 1 lowercase char
	$b_len = rand(2, 2);
	// echo 'b_len: '.$b_len.'<br>';

	for (; $i < ($ctr + $b_len); $i++)
		$arr[$i] = chr(rand(97, 122));

	$ctr = $i;

	// atleast 1 number
	$c_len = rand(1, 1);
	// echo 'c_len: '.$c_len.'<br>';

	for (; $i < ($ctr + $c_len); $i++)
		$arr[$i] = rand(0, 9);

	// DFA($arr);
	shuffle($arr);
	// DFA($arr);

	$str = '';
	foreach ($arr as $a)
		$str .= $a;

	return $str;
}

function enCodeParamSMS($param)
{
	global $ENC_CHARARR;
	$randomCode = Generate4DigitRandomCode();
	$code = '';

	$param = strval($param);
	for ($i = 0; $i < strlen($param); $i++)
		$code .= $ENC_CHARARR[$param[$i]];

	$str = $randomCode . $code;

	return $str;
}

function deCodeParamSMS($param)
{
	global $ENC_CHARARR;
	$p2 = substr($param, 4);

	$code = '';
	$p2 = strval($p2);
	for ($i = 0; $i < strlen($p2); $i++)
		$code .= array_search($p2[$i], $ENC_CHARARR);

	$str = $code;

	return $str;
}

function GetClientID($rm, $cid, $name, $contact)
{
	$str = $cond = '';
	global $sess_user_id;

	if (empty($cid)) $cid = 0;

	if (!empty($cid)) $cond .= ' and iRMContactID=' . $cid;
	if (!empty($contact)) $cond .= ' and vContact="' . $contact . '"';

	$exist = GetXfromYID('select iRMContactID from gen_rm_contacts where iRM_UserID=' . $rm . ' and LOWER(vName)="' . strtolower($name) . '"' . $cond);
	if (empty($exist) || $exist == '-1') {
		LockTable('gen_rm_contacts');
		$iRMContactID = NextID('iRMContactID', 'gen_rm_contacts');
		$q = "insert into gen_rm_contacts values ('$iRMContactID', '" . NOW . "', '$sess_user_id', '$rm', '$name', '$contact', '', NULL, NULL, '$iRMContactID', 'A')";
		$r = sql_query($q, '');
		UnlockTable();

		$desc_str = 'Newly Created: ' . db_input($q);
		//LogMasterEdit2($iRMContactID, 'RMC', 'I', $name, $desc_str, $sess_user_id);
		LogMasterEdit($iRMContactID, 'RMC', 'I', $name);


		$str = $iRMContactID;
	} else
		$str = $exist;

	return $str;
}

function GetHotelRoomActivityStatus($request_id, $option_id, $enquiry_id)
{
	$booking_add_url = 'bookings_add.php';
	$booking_url = 'bookings_view.php';

	global $HOTELENQUIRY_STATUS_ARR, $HOTELENQUIRY_COLOR_ARR;

	$_aq2 = 'select iRequestDatOptionID, iRequestDatID, iHotelID, iRoomTypeID from concrequestdat_options where iRequestID=' . $request_id . ' and iRequestDatOptionID=' . $option_id;
	$_ar2 = sql_query($_aq2, '');
	if (sql_num_rows($_ar2)) {
		list($iRequestDatOptionID, $iRequestDatID, $iHotelID, $iRoomTypeID) = sql_fetch_row($_ar2);
		$HOTELS_DET[$iRequestDatOptionID] = array('REQUEST_DATID' => $iRequestDatID, 'HOTEL_ID' => $iHotelID, 'ROOM_TYPEID' => $iRoomTypeID);
	}

	$aKEY = $HOTELS_DET[$option_id]['REQUEST_DATID'];
	$hID = $HOTELS_DET[$option_id]['HOTEL_ID'];
	$rtID = $HOTELS_DET[$option_id]['ROOM_TYPEID'];

	$ENQUIRY_ARR = array();

	$_eq = 'select iHotelEnqID, iHotelID, iRoomTypeID, iRequestDatOptionID, dtEnq, cReqSeen, dtReqSeen, iReqSeen_UserID, cAvailable, iAvailable_UserID, dtResponse, cResponseSeenBy, iResponseSeenBy_UserID, dtResponseSeen, cResponseSeenByRM, dtResponseSeenByRM, cBookReq, dtBookReq, iBookRequest_UserID, cBookReqSeen, dtBookReqSeen, iBookReqSeen_UserID, cBooked, dtBooked, iBooked_UserID, vBookingRef, cStatus from concrequest_hotelenq where iRequestID=' . $request_id . ' and iRequestDatOptionID=' . $option_id . ' and iHotelEnqID=' . $enquiry_id;
	$_er = sql_query($_eq, '');
	if (sql_num_rows($_er)) {
		list($iHotelEnqID, $iHotelID, $iRoomTypeID, $iRequestDatOptionID, $dtEnq, $cReqSeen, $dtReqSeen, $iReqSeen_UserID, $cAvailable, $iAvailable_UserID, $dtResponse, $cResponseSeenBy, $iResponseSeenBy_UserID, $dtResponseSeen, $cResponseSeenByRM, $dtResponseSeenByRM, $cBookReq, $dtBookReq, $iBookRequest_UserID, $cBookReqSeen, $dtBookReqSeen, $iBookReqSeen_UserID, $cBooked, $dtBooked, $iBooked_UserID, $vBookingRef, $cStatus) = sql_fetch_row($_er);

		$ENQUIRY_ARR[$iRequestDatOptionID] = array('ENQ_ID' => $iHotelEnqID, 'ENQ_HOTELID' => $iHotelID, 'ENQ_ROOMTYPEID' => $iRoomTypeID, 'ENQ_DATE' => $dtEnq, 'ENQ_REQSEEN' => $cReqSeen, 'ENQ_REQSEEN_DT' => $dtReqSeen, 'ENQ_REQSEEN_BY' => $iReqSeen_UserID, 'ENQ_AVAILABLE' => $cAvailable, 'ENQ_AVAILABLE_BY' => $iAvailable_UserID, 'ENQ_RESPONSE_DT' => $dtResponse, 'ENQ_RESPONSE_SEEN' => $cResponseSeenBy, 'ENQ_RESPONSE_SEENBY' => $iResponseSeenBy_UserID, 'ENQ_RESPONSE_SEENDT' => $dtResponseSeen, 'ENQ_RESPONSE_SEENBY_RM' => $cResponseSeenByRM, 'ENQ_RESPONSE_SEENDT_RM' => $dtResponseSeenByRM, 'ENQ_REQBOOK' => $cBookReq, 'ENQ_REQBOOK_DT' => $dtBookReq, 'ENQ_REQBOOK_BY' => $iBookRequest_UserID, 'ENQ_REQBOOK_SEEN' => $cBookReqSeen, 'ENQ_REQBOOK_SEENDT' => $dtBookReqSeen, 'ENQ_REQBOOK_SEENBY' => $iBookReqSeen_UserID, 'ENQ_BOOKED' => $cBooked, 'ENQ_BOOKED_DT' => $dtBooked, 'ENQ_BOOKED_BY' => $iBooked_UserID, 'ENQ_BOOK_RFFNUM' => $vBookingRef, 'ENQ_STATUS' => $cStatus);
	}

	$BOOKING_ARR = array();
	$_bq = 'select b.iBookingID, b.dtBooking, bd.iRequestDatOptionID, bd.iHotelID, bd.iRoomTypeID from concbooking as b join concbooking_dat as bd on b.iBookingID=bd.iBookingID where bd.iRequestDatOptionID=' . $option_id;
	$_br = sql_query($_bq, '');
	if (sql_num_rows($_br)) {
		list($bookingID, $bookingDate, $bookingRequestID, $bookingHotelID, $bookingRoomTypeID) = sql_fetch_row($_br);

		$BOOKING_ARR[$bookingRequestID] = array('BOOKING_ID' => $bookingID, 'BOOKING_DATE' => $bookingDate, 'BOOKING_HOTEL' => $bookingHotelID, 'BOOKING_ROOMTYPE' => $bookingRoomTypeID);
	}

	$lastestACTION = '<div class="ml-auto ml-1 pl-1 badge badge-' . $HOTELENQUIRY_COLOR_ARR['NA'] . '">' . $HOTELENQUIRY_STATUS_ARR['NA'] . '</div>';
	$REQUEST_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="SendReuestToHotel(\'' . $option_id . '\',\'' . $hID . '\',\'' . $rtID . '\',\'' . $aKEY . '\');"><i class="nav-link-icon pe-7s-paper-plane"> </i><span>Send Request</span></a>';
	$MARKAVAILABLE_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="SpecifyAvailability(\'0\',\'' . $option_id . '\')"><i class="nav-link-icon pe-7s-ticket"> </i><span>Specify Availability</span></a>';
	$RMCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="MarkConfirmedByRM(\'0\',\'' . $option_id . '\');"><i class="nav-link-icon pe-7s-ticket"> </i><span>Confirm by RM</span></a>';
	$HOTELCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="MarkConfirmedByHotel(\'0\',\'' . $option_id . '\');"><i class="nav-link-icon pe-7s-like"> </i><span>Confirm by Hotel</span></a>';
	$BOOKNOW_BUTTON = '<a href="' . $booking_add_url . '?req_id=' . $request_id . '&dat_id=' . $aKEY . '&id=' . $option_id . '" class="nav-link"><i class="nav-link-icon pe-7s-notebook"></i><span>Book</span></a>';

	if (!empty($ENQUIRY_ARR[$option_id]) && count($ENQUIRY_ARR[$option_id])) {
		$ENQ_ID = $ENQUIRY_ARR[$option_id]['ENQ_ID'];

		$lastestACTION = '<div class="ml-auto badge badge-' . $HOTELENQUIRY_COLOR_ARR['RS'] . '">' . $HOTELENQUIRY_STATUS_ARR['RS'] . '</div>';
		$REQUEST_BUTTON = '<a href="javascript:void(0);" class="nav-link text-success"><i class="nav-link-icon pe-7s-paper-plane"> </i><span>Request Sent</span><div class="ml-auto badge badge-pill badge-info">' . LastOnlineSeen($ENQUIRY_ARR[$option_id]['ENQ_DATE']) . '</div></a>';

		if ($ENQUIRY_ARR[$option_id]['ENQ_AVAILABLE'] == 'X') {
			$MARKAVAILABLE_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="SpecifyAvailability(\'' . $ENQ_ID . '\',\'' . $option_id . '\')"><i class="nav-link-icon pe-7s-ticket"> </i><span>Specify Availability</span></a>';
			$RMCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="MarkConfirmedByRM(\'' . $ENQ_ID . '\',\'' . $option_id . '\');"><i class="nav-link-icon pe-7s-ticket"> </i><span>Confirm by RM</span></a>';
			$HOTELCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="MarkConfirmedByHotel(\'' . $ENQ_ID . '\',\'' . $option_id . '\');"><i class="nav-link-icon pe-7s-like"> </i><span>Confirm by Hotel</span></a>';
		} else {
			$IS_AVAILABLE = ($ENQUIRY_ARR[$option_id]['ENQ_AVAILABLE'] == 'Y') ? 'AV' : 'NAV';
			$IS_AVAILABLE_CLASS = ($ENQUIRY_ARR[$option_id]['ENQ_AVAILABLE'] == 'Y') ? 'AV' : 'NAV';
			$IS_AVAILABLE_CLASS2 = ($ENQUIRY_ARR[$option_id]['ENQ_AVAILABLE'] == 'Y') ? 'success' : 'danger';

			$MARKAVAILABLE_BUTTON = '<a href="javascript:void(0);" class="nav-link text-' . $IS_AVAILABLE_CLASS2 . '" onClick="SpecifyAvailability(\'' . $ENQ_ID . '\',\'' . $option_id . '\')"><i class="nav-link-icon pe-7s-ticket"> </i><span>' . $HOTELENQUIRY_STATUS_ARR[$IS_AVAILABLE] . '</span><div class="ml-auto badge badge-pill badge-info">' . LastOnlineSeen($ENQUIRY_ARR[$option_id]['ENQ_RESPONSE_DT']) . '</div></a>';

			$lastestACTION = '<div class="ml-auto badge badge-' . $HOTELENQUIRY_COLOR_ARR[$IS_AVAILABLE_CLASS] . '">' . $HOTELENQUIRY_STATUS_ARR[$IS_AVAILABLE] . '</div>';
		}

		if ($ENQUIRY_ARR[$option_id]['ENQ_AVAILABLE'] != 'X') {
			if ($ENQUIRY_ARR[$option_id]['ENQ_AVAILABLE'] == 'N')
				$RMCONFIRM_BUTTON = $HOTELCONFIRM_BUTTON = $BOOKNOW_BUTTON = '';
			else {
				if ($ENQUIRY_ARR[$option_id]['ENQ_REQBOOK'] == 'Y') {
					$RMCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link text-success"><i class="nav-link-icon pe-7s-like2"> </i><span>RM Confirmed</span><div class="ml-auto badge badge-pill badge-info">' . LastOnlineSeen($ENQUIRY_ARR[$option_id]['ENQ_REQBOOK_DT']) . '</div></a>';

					$lastestACTION = '<div class="ml-auto badge badge-' . $HOTELENQUIRY_COLOR_ARR['RMC'] . '">' . $HOTELENQUIRY_STATUS_ARR['RMC'] . '</div>';
				} elseif ($ENQUIRY_ARR[$option_id]['ENQ_REQBOOK'] == 'N') {
					$RMCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="MarkConfirmedByRM(\'' . $ENQ_ID . '\',\'' . $option_id . '\');"><i class="nav-link-icon pe-7s-ticket"> </i><span>Confirm by RM</span></a>';
				}

				if ($ENQUIRY_ARR[$option_id]['ENQ_BOOKED'] == 'Y') {
					$HOTELCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link text-success"><i class="nav-link-icon pe-7s-like"> </i><span>Hotel Confirmed</span><div class="ml-auto badge badge-pill badge-info">' . LastOnlineSeen($ENQUIRY_ARR[$option_id]['ENQ_BOOKED_DT']) . '</div></a>';

					$lastestACTION = '<div class="ml-auto badge badge-' . $HOTELENQUIRY_COLOR_ARR['HC'] . '">' . $HOTELENQUIRY_STATUS_ARR['HC'] . '</div>';
				} elseif ($ENQUIRY_ARR[$option_id]['ENQ_BOOKED'] == 'N') {
					$HOTELCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="MarkConfirmedByHotel(\'' . $ENQ_ID . '\',\'' . $option_id . '\');"><i class="nav-link-icon pe-7s-like"> </i><span>Confirm by Hotel</span></a>';
				}
			}
		}
	}

	if (!empty($BOOKING_ARR[$option_id])) {
		$lastestACTION = '<div class="ml-auto badge badge-' . $HOTELENQUIRY_COLOR_ARR['BK'] . '">' . $HOTELENQUIRY_STATUS_ARR['BK'] . '</div>';
		$BOOKNOW_BUTTON = '<a href="' . $booking_url . '?id=' . $BOOKING_ARR[$option_id]['BOOKING_ID'] . '" class="nav-link text-success"><i class="nav-link-icon pe-7s-notebook"></i><span>Booked</span><div class="ml-auto badge badge-pill badge-info">' . LastOnlineSeen($BOOKING_ARR[$option_id]['BOOKING_DATE']) . '</div></a>';
	}

	$ACTIVITY_LINKS = '';
	$ACTIVITY_LINKS .= '<li class="nav-item-header nav-item">Activity</li>';
	$ACTIVITY_LINKS .= '<li class="nav-item">' . $REQUEST_BUTTON . '</li>';
	$ACTIVITY_LINKS .= '<li class="nav-item">' . $MARKAVAILABLE_BUTTON . '</li>';
	$ACTIVITY_LINKS .= '<li class="nav-item">' . $RMCONFIRM_BUTTON . '</li>';
	$ACTIVITY_LINKS .= '<li class="nav-item">' . $HOTELCONFIRM_BUTTON . '</li>';
	$ACTIVITY_LINKS .= '<li class="nav-item">' . $BOOKNOW_BUTTON . '</li>';

	$str = $lastestACTION . '~~**~~' . $ACTIVITY_LINKS;

	return $str;
}

function LogMasterEdit2($id, $flag, $mode, $name = '', $desc_str = '', $user_id = false)
{
	global $_SERVER, $sess_user_id, $sess_user_name;
	$sess_user_locid = '1';

	if ($desc_str != '') {
		$ip = $_SERVER['REMOTE_ADDR'];

		$lmid = NextID('iLMID', 'log_masters');
		$q = "insert into log_masters values ($lmid, $sess_user_locid, $sess_user_id, '" . db_input($sess_user_name) . "', '" . NOW . "', $id, '$flag', '" . db_input($name) . "', '" . db_input($desc_str) . "', '$mode', '$ip', 'A')";
		$r = sql_query($q, 'COM.1421');
	}
}

function GetHotelRoomCost($h_id, $rt_id, $optiondat_id, $guests, $rooms, $chkInDate, $chkOutDate, $ROOM_OCCUPANCY_ARR = array())
{
	$str = '';
	$GRAND_TOTAL = 0;
	global $OCCUPANCY_ARR3;

	$_rq = 'select iPax, iMaxPax from gen_roomtype where iHotelID=' . $h_id . ' and iRoomTypeID=' . $rt_id;
	$_rr = sql_query($_rq, '');
	list($PAX, $MAX_PAX) = sql_fetch_row($_rr);

	$TOTAL_GUEST = $EXTRA_ADULT = $EXTRA_CHILD = 0;
	$ROOM_ARR = array();
	for ($r = 1; $r <= $rooms; $r++) {
		$ROOM_ARR[$r] = array('GUEST' => 0, 'X_ADULT' => 0, 'X_CHILD' => 0);
	}

	if (empty($ROOM_OCCUPANCY_ARR)) {
		for ($g = 1; $g <= $guests; $g++) {
			foreach ($ROOM_ARR as $rKEY => $rVALUE) {
				if ($rVALUE['GUEST'] < $PAX) {
					$ROOM_ARR[$rKEY]['GUEST'] = $ROOM_ARR[$rKEY]['GUEST'] + 1;
					$ROOM_ARR =  MultiDimensionalArraySort($ROOM_ARR, 'GUEST', SORT_ASC);
					break;
				} else {
					$TOTAL_ROOM_PAX = $rVALUE['GUEST'] + $rVALUE['X_ADULT'];
					if ($TOTAL_ROOM_PAX < $MAX_PAX) {
						$ROOM_ARR[$rKEY]['X_ADULT'] = $ROOM_ARR[$rKEY]['X_ADULT'] + 1;
						$ROOM_ARR = MultiDimensionalArraySort($ROOM_ARR, 'X_ADULT', SORT_ASC);
						break;
					}
				}
			}
		}
	} else {
		foreach ($ROOM_OCCUPANCY_ARR as $ROOM_NO => $ROOM_GUESTCOUNT) {
			for ($g = 1; $g <= $ROOM_GUESTCOUNT; $g++) {
				foreach ($ROOM_ARR as $rKEY => $rVALUE) {
					if ($ROOM_NO != $rKEY)
						continue;

					if ($rVALUE['GUEST'] < $PAX) {
						$ROOM_ARR[$rKEY]['GUEST'] = $ROOM_ARR[$rKEY]['GUEST'] + 1;
						$ROOM_ARR =  MultiDimensionalArraySort($ROOM_ARR, 'GUEST', SORT_ASC);
						break;
					} else {
						$TOTAL_ROOM_PAX = $rVALUE['GUEST'] + $rVALUE['X_ADULT'];
						if ($TOTAL_ROOM_PAX < $MAX_PAX) {
							$ROOM_ARR[$rKEY]['X_ADULT'] = $ROOM_ARR[$rKEY]['X_ADULT'] + 1;
							$ROOM_ARR = MultiDimensionalArraySort($ROOM_ARR, 'X_ADULT', SORT_ASC);
							break;
						}
					}
				}
			}
		}
	}

	if (!empty($ROOM_ARR) && count($ROOM_ARR)) {
		ksort($ROOM_ARR);
		foreach ($ROOM_ARR as $rKEY2 => $rVALUE2) {
			$GUEST = $rVALUE2['GUEST'];
			$EXTRA_ADULT = $rVALUE2['X_ADULT'];
			$EXTRA_CHILD = $rVALUE2['X_CHILD'];

			$OCCUPANCY = $OCCUPANCY_ARR3[$rVALUE2['GUEST'] + $EXTRA_ADULT]; //($rVALUE2['GUEST']<$PAX)?'S':'D';

			$TARIFF_ARR = GetTariff($chkInDate, $chkOutDate, $h_id, $rt_id, $OCCUPANCY, $EXTRA_ADULT, $EXTRA_CHILD, 1);
			$TARIFF_ARR2 = GetTariff($chkInDate, $chkOutDate, $h_id, $rt_id, $OCCUPANCY, $EXTRA_ADULT, $EXTRA_CHILD, 2);

			$txtnumadults = $rVALUE2['GUEST'];
			$txtextraadults = $EXTRA_ADULT;
			$txtextrachild = $EXTRA_CHILD;

			if (isset($TARIFF_ARR['RATE'])) {
				$txtindv_rate = $txtindv_eprate = $txtindv_ecrate = array();
				$r = 1;

				foreach ($TARIFF_ARR['RATE'] as  $txtrate => $diff) {
					list($rate, $tID) = explode('~', $txtrate);
					$txtindv_rate[$r] = $rate; // - $txtdisc
					$r++;
				}

				if ($txtextraadults) {
					$r = 1;
					foreach ($TARIFF_ARR['EPRATE'] as  $txtrate => $diff) {
						list($rate, $tID) = explode('~', $txtrate);
						$txtindv_eprate[$r] = $rate * $txtextraadults;
						$r++;
					}
				}

				if ($txtextrachild) {
					$r = 1;
					foreach ($TARIFF_ARR['ECRATE'] as  $txtrate => $diff) {
						list($rate, $tID) = explode('~', $txtrate);
						$txtindv_ecrate[$r] = $rate * $txtextrachild;
						$r++;
					}
				}

				$j = 1;
				$total_room_val = 0;
				foreach ($TARIFF_ARR['RATE'] as  $txtrate => $diff) {
					list($rate, $tID) = explode('~', $txtrate);
					list($tariff_from, $tariff_to) = explode("~", $TARIFF_ARR2['RATE'][$txtrate]);

					$txteadultcharge = (isset($txtindv_eprate[$j])) ? $txtindv_eprate[$j] : 0;
					$txtecchildharge = (isset($txtindv_ecrate[$j])) ? $txtindv_ecrate[$j] : 0;

					$txtnewrate = $txtindv_rate[$j] + $txteadultcharge + $txtecchildharge;

					$total_room_val = ($txtnewrate * $diff);

					if ($rt_id != 0 && $rKEY2 != 0) {
						$txteadultcharge = ($txteadultcharge) ? ($txteadultcharge) / $txtextraadults : 0;
						$txtecchildharge = ($txtecchildharge) ? ($txtecchildharge) / $txtextrachild : 0;

						$txtid = NextID('iROTariffID', 'concrequestdat_options_tariff');
						$q = "insert into concrequestdat_options_tariff values ('$txtid', '$optiondat_id', '$h_id', '$rt_id', '$rKEY2', '$tID', '$OCCUPANCY', '$tariff_from', '$tariff_to', '$diff', '$txtnumadults', '$txtextraadults', '$txteadultcharge', '0', '$txtextrachild', '$txtecchildharge', '$rate', '$total_room_val', 'A')";
						$r = sql_query($q, 'RES_E.213');

						$GRAND_TOTAL += $total_room_val;
					}

					$j++;
				}  //exit;
			}
		}
	}

	$str = $GRAND_TOTAL;
	if (!empty($GRAND_TOTAL))
		sql_query("update concrequestdat_options set fCost='$GRAND_TOTAL' where iRequestDatOptionID='$optiondat_id'", "");

	return $str;
}

function GetTariff($chkInDate, $chkOutDate, $h_id, $rt_id, $occupancy, $extra_adult = 0, $extra_child = 0, $mode = 1)
{
	$tariff_rate_arr = array();

	$resdiff0 = DateDiff($chkInDate, $chkOutDate); //echo  $resdiff0;

	$q = 'select * from gen_tariff where dTo>="' . $chkInDate . '" and dFrom<="' . $chkOutDate . '" and iHotelID=' . $h_id . ' and iRoomTypeID=' . $rt_id . ' and cStatus="A" order by dFrom';
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		$j = 1;
		$i0 = 1;
		while ($obj = sql_fetch_object($r)) {
			$tariff_id = $obj->iTariffID;
			$from = ($j == 1) ? $chkInDate : $obj->dFrom;
			$to = (sql_num_rows($r) == $j) ? $chkOutDate : $obj->dTo;

			$resdiff = DateDiff($from, $to);
			for ($i = 0; $i <= $resdiff; $i++) {
				if ($i0 > $resdiff0) break;
				$i0++;

				//if($i0 > $resdiff0) break;
				//$i0++;  /*commented as it was giving error whn chkin=chout*/

				$x_date = DateTimeAdd($from, $i, 0, 0, 0, 0, 0, "Y-m-d");
				$day = FormatDate($x_date, "N");

				if ($occupancy == 'S')
					$rate = $obj->fRate_SO;
				elseif ($occupancy == 'D' || $occupancy == 'T')
					$rate = $obj->fRate_DO;

				$ep_rate = $obj->fRate_ExtraAdult;
				$ec_rate = $obj->fRate_ExtraChild;

				if ($mode == '1') {
					if ($rate) {
						if (isset($tariff_rate_arr['RATE'][$rate . '~' . $tariff_id]))
							$tariff_rate_arr['RATE'][$rate . '~' . $tariff_id] += 1;
						else
							$tariff_rate_arr['RATE'][$rate . '~' . $tariff_id] = 1;
					}

					if ($ep_rate) {
						if (isset($tariff_rate_arr['EPRATE'][$ep_rate . '~' . $tariff_id]))
							$tariff_rate_arr['EPRATE'][$ep_rate . '~' . $tariff_id] += 1;
						else
							$tariff_rate_arr['EPRATE'][$ep_rate . '~' . $tariff_id] = 1;
					}

					if ($ec_rate) {
						if (isset($tariff_rate_arr['ECRATE'][$ec_rate . '~' . $tariff_id]))
							$tariff_rate_arr['ECRATE'][$ec_rate . '~' . $tariff_id] += 1;
						else
							$tariff_rate_arr['ECRATE'][$ec_rate . '~' . $tariff_id] = 1;
					}
				} elseif ($mode == '2') {
					$tariff_arr = array();

					if (!isset($tariff_rate_arr['RATE'][$rate . '~' . $tariff_id])) {
						$tariff_rate_arr['RATE'][$rate . '~' . $tariff_id] = $x_date . '~' . $to;
						$tariff_arr[$tariff_id] = $tariff_id;
					} elseif ((isset($tariff_rate_arr['RATE'][$rate . '~' . $tariff_id])) && (isset($tariff_arr[$tariff_id]))) {

						list($t_from, $t_to) = explode("~", $tariff_rate_arr['RATE'][$rate . '~' . $tariff_id]);
						$next_tariff_date = date('Y-m-d H:i:s', strtotime('+1 day', strtotime($x_date)));
						$tariff_rate_arr['RATE'][$rate . '~' . $tariff_id] = $t_from . '~' . $next_tariff_date;
					}
				}
			}

			$j++;
		}
	}


	return $tariff_rate_arr;
}

function UpdateHotelRoomTypeBreakup($h_id, $rt_id, $optiondat_id, $guests, $rooms, $txtchkin, $txtchkout)
{
	$GRAND_TOTAL = 0;
	global $OCCUPANCY_ARR3;

	$_rq = 'select iPax, iMaxPax from gen_roomtype where iHotelID=' . $h_id . ' and iRoomTypeID=' . $rt_id;
	$_rr = sql_query($_rq, '');
	list($PAX, $MAX_PAX) = sql_fetch_row($_rr);

	$TOTAL_GUEST = $EXTRA_ADULT = $EXTRA_CHILD = 0;
	$ROOM_ARR = array();
	for ($r = 1; $r <= $rooms; $r++) {
		$ROOM_ARR[$r] = array('GUEST' => 0, 'X_ADULT' => 0, 'X_CHILD' => 0);
	}

	for ($g = 1; $g <= $guests; $g++) {
		foreach ($ROOM_ARR as $rKEY => $rVALUE) {
			if ($rVALUE['GUEST'] < $PAX) {
				$ROOM_ARR[$rKEY]['GUEST'] = $ROOM_ARR[$rKEY]['GUEST'] + 1;
				$ROOM_ARR =  MultiDimensionalArraySort($ROOM_ARR, 'GUEST', SORT_ASC);
				break;
			} else {
				$TOTAL_ROOM_PAX = $rVALUE['GUEST'] + $rVALUE['X_ADULT'];
				if ($TOTAL_ROOM_PAX < $MAX_PAX) {
					$ROOM_ARR[$rKEY]['X_ADULT'] = $ROOM_ARR[$rKEY]['X_ADULT'] + 1;
					$ROOM_ARR = MultiDimensionalArraySort($ROOM_ARR, 'X_ADULT', SORT_ASC);
					break;
				}
			}
		}
	}

	if (!empty($ROOM_ARR) && count($ROOM_ARR)) {
		ksort($ROOM_ARR);
		foreach ($ROOM_ARR as $rKEY2 => $rVALUE2) {
			$GUEST = $rVALUE2['GUEST'];
			$EXTRA_ADULT = $rVALUE2['X_ADULT'];
			$EXTRA_CHILD = $rVALUE2['X_CHILD'];

			$OCCUPANCY = $OCCUPANCY_ARR3[$rVALUE2['GUEST'] + $EXTRA_ADULT]; //($rVALUE2['GUEST']<$PAX)?'S':'D';

			$roomTariffExist = GetXFromYID('select count(*) from concrequestdat_options_tariff where iRequestDatOptionID=' . $optiondat_id . ' and iHotelID=' . $h_id . ' and iRoomTypeID=' . $rt_id . ' and iRoomID=' . $rKEY2 . ' and cStatus="A"');

			if (!empty($roomTariffExist) && $roomTariffExist != '-1') {
				$TARIFF_ARR = GetRoomTariff($h_id, $rt_id, $rKEY2, $optiondat_id, $OCCUPANCY, $EXTRA_ADULT, $EXTRA_CHILD, 1);
				$TARIFF_ARR2 = GetRoomTariff($h_id, $rt_id, $rKEY2, $optiondat_id, $OCCUPANCY, $EXTRA_ADULT, $EXTRA_CHILD, 2);
			} else {
				$TARIFF_ARR = GetTariff($txtchkin, $txtchkout, $h_id, $rt_id, $OCCUPANCY, $EXTRA_ADULT, $EXTRA_CHILD, 1);
				$TARIFF_ARR2 = GetTariff($txtchkin, $txtchkout, $h_id, $rt_id, $OCCUPANCY, $EXTRA_ADULT, $EXTRA_CHILD, 2);
			}

			sql_query('update concrequestdat_options_tariff set cStatus="X" where iRequestDatOptionID=' . $optiondat_id . ' and iHotelID=' . $h_id . ' and iRoomTypeID=' . $rt_id . ' and iRoomID=' . $rKEY2);

			$txtnumadults = $rVALUE2['GUEST'];
			$txtextraadults = $EXTRA_ADULT;
			$txtextrachild = $EXTRA_CHILD;

			if (isset($TARIFF_ARR['RATE'])) {
				$txtindv_rate = $txtindv_eprate = $txtindv_ecrate = array();
				$r = 1;

				foreach ($TARIFF_ARR['RATE'] as  $txtrate => $diff) {
					list($rate, $tID) = explode('~', $txtrate);
					$txtindv_rate[$r] = $rate; // - $txtdisc
					$r++;
				}

				if ($txtextraadults) {
					$r = 1;
					foreach ($TARIFF_ARR['EPRATE'] as  $txtrate => $diff) {
						list($rate, $tID) = explode('~', $txtrate);
						$txtindv_eprate[$r] = $rate * $txtextraadults;
						$r++;
					}
				}

				if ($txtextrachild) {
					$r = 1;
					foreach ($TARIFF_ARR['ECRATE'] as  $txtrate => $diff) {
						list($rate, $tID) = explode('~', $txtrate);
						$txtindv_ecrate[$r] = $rate * $txtextrachild;
						$r++;
					}
				}

				$j = 1;
				$total_room_val = 0;
				foreach ($TARIFF_ARR['RATE'] as  $txtrate => $diff) {
					list($rate, $tID) = explode('~', $txtrate);
					list($tariff_from, $tariff_to) = explode("~", $TARIFF_ARR2['RATE'][$txtrate]);

					$txteadultcharge = (isset($txtindv_eprate[$j])) ? $txtindv_eprate[$j] : 0;
					$txtecchildharge = (isset($txtindv_ecrate[$j])) ? $txtindv_ecrate[$j] : 0;

					$txtnewrate = $txtindv_rate[$j] + $txteadultcharge + $txtecchildharge;

					$total_room_val = ($txtnewrate * $diff);

					if ($rt_id != 0 && $rKEY2 != 0) {
						$txteadultcharge = ($txteadultcharge) ? ($txteadultcharge) / $txtextraadults : 0;
						$txtecchildharge = ($txtecchildharge) ? ($txtecchildharge) / $txtextrachild : 0;

						$txtid = NextID('iROTariffID', 'concrequestdat_options_tariff');
						$q = "insert into concrequestdat_options_tariff values ('$txtid', '$optiondat_id', '$h_id', '$rt_id', '$rKEY2', '$tID', '$OCCUPANCY', '$tariff_from', '$tariff_to', '$diff', '$txtnumadults', '$txtextraadults', '$txteadultcharge', '0', '$txtextrachild', '$txtecchildharge', '$rate', '$total_room_val', 'A')";
						$r = sql_query($q, 'RES_E.213');

						$GRAND_TOTAL += $total_room_val;
					}

					$j++;
				}  //exit;
			}
		}
	}

	$str = $GRAND_TOTAL;
	if (!empty($GRAND_TOTAL))
		sql_query("update concrequestdat_options set fCost='$GRAND_TOTAL' where iRequestDatOptionID='$optiondat_id'", "");
}

function GetRoomTariff($h_id, $rt_id, $room_id, $optiondat_id, $occupancy, $extra_adult = 0, $extra_child = 0, $mode = 1)
{
	$tariff_rate_arr = array();

	$q = 'select iTariffID, cRoomSize, dFrom, dTo, iNumOfDays, fExtaAdultCharges, fExtraChildCharges, fRoomRate from concrequestdat_options_tariff where iRequestDatOptionID=' . $optiondat_id . ' and iHotelID=' . $h_id . ' and iRoomTypeID=' . $rt_id . ' and iRoomID=' . $room_id . ' and cStatus="A" order by dFrom';
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		$j = 1;
		$i0 = 1;
		while (list($x_tariffid, $x_roomsize, $x_dfrom, $x_dto, $x_numdays, $x_xadultcharges, $x_xchildcharges, $x_rate) = sql_fetch_row($r)) {
			$fRate_SO = $fRate_DO = $fRate_ExtraAdult = $fRate_ExtraChild = 0;
			$_tq = 'select fRate_SO, fRate_DO, fRate_ExtraAdult, fRate_ExtraChild from gen_tariff where iTariffID=' . $x_tariffid;
			$_tr = sql_query($_tq, '');
			if (sql_num_rows($_tr))
				list($fRate_SO, $fRate_DO, $fRate_ExtraAdult, $fRate_ExtraChild) = sql_fetch_row($_tr);

			if ($x_roomsize != $occupancy) {
				if ($occupancy == 'S' && !empty($fRate_SO)) $x_rate = $fRate_SO;
				elseif ($occupancy == 'D' && !empty($fRate_DO)) $x_rate = $fRate_DO;
				elseif ($occupancy == 'T' && !empty($fRate_DO)) $x_rate = $fRate_DO;
			}

			if (empty($x_xadultcharges) && !empty($fRate_ExtraAdult)) $x_xadultcharges = $fRate_ExtraAdult;
			if (empty($x_xchildcharges) && !empty($fRate_ExtraChild)) $x_xchildcharges = $fRate_ExtraChild;

			if ($mode == '1') {
				if ($x_rate)
					$tariff_rate_arr['RATE'][$x_rate . '~' . $x_tariffid] = $x_numdays;

				if ($x_xadultcharges)
					$tariff_rate_arr['EPRATE'][$x_xadultcharges . '~' . $x_tariffid] = $x_numdays;

				if ($x_xchildcharges)
					$tariff_rate_arr['ECRATE'][$x_xchildcharges . '~' . $x_tariffid] = $x_numdays;
			} elseif ($mode == '2')
				$tariff_rate_arr['RATE'][$x_rate . '~' . $x_tariffid] = $x_dfrom . '~' . $x_dto;
		}
	}

	return $tariff_rate_arr;
}

function UpdateHotelRoomCost($request_id, $requestdat_id, $guests, $rooms)
{
	$_rq = 'select dCheckin, dCheckOut from concrequest where iRequestID=' . $request_id;
	$_rr = sql_query($_rq, '');
	list($txtchkin, $txtchkout) = sql_fetch_row($_rr);

	$q = 'select iRequestDatOptionID, iHotelID, iRoomTypeID from concrequestdat_options where iRequestID=' . $request_id . ' and iRequestDatID=' . $requestdat_id . ' order by iRequestDatOptionID';
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		while (list($optiondat_id, $h_id, $rt_id) = sql_fetch_row($r)) {
			$tariffExists = GetXFromYID('select count(*) from concrequestdat_options_tariff where iRequestDatOptionID=' . $optiondat_id . ' and iHotelID=' . $h_id . ' and iRoomTypeID=' . $rt_id . ' and cStatus="A"');

			$ROOMBREAKUP_DET = array();
			$_aq2 = 'select iRoomNo, iGuest from concrequestdat_rooms where iRequestID=' . $request_id . ' and iRequestDatID=' . $requestdat_id . ' and cStatus="A" order by iRoomNo';
			$_ar2 = sql_query($_aq2, '');
			if (sql_num_rows($_ar2)) {
				while (list($iRoomNo, $iGuest) = sql_fetch_row($_ar2))
					$ROOMBREAKUP_DET[$iRoomNo] = $iGuest;
			}

			if (empty($tariffExists) || $tariffExists == '-1')
				GetHotelRoomCost($h_id, $rt_id, $optiondat_id, $guests, $rooms, $txtchkin, $txtchkout, $ROOMBREAKUP_DET);
			else
				UpdateHotelRoomTypeBreakup($h_id, $rt_id, $optiondat_id, $guests, $rooms, $txtchkin, $txtchkout);
		}
	}
}

function GetHotelRoomTypeRateChart($h_id, $dCheckin, $dCheckOut)
{
	$arr = array();

	$cond = '';
	if (!empty($dCheckin) && IsDate($dCheckin))
		$cond .= " and dTo>='" . $dCheckin . "'";
	if (!empty($dCheckOut) && IsDate($dCheckOut))
		$cond .= " and dFrom<='" . $dCheckOut . "'";
	if (!empty($h_id))
		$cond .= " and iHotelID='$h_id'";

	$q = 'select iRoomTypeID, fRate_SO, fRate_DO, fRate_ExtraAdult, fRate_ExtraChild, dFrom, dTo from gen_tariff where cStatus="A"' . $cond . ' order by iRoomTypeID, dFrom';
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		while (list($iRoomTypeID, $fRate_SO, $fRate_DO, $fRate_ExtraAdult, $fRate_ExtraChild, $dFrom, $dTo) = sql_fetch_row($r)) {
			if (!isset($arr[$iRoomTypeID])) $arr[$iRoomTypeID] = array();

			$fromDATE = '';
			if ($dFrom <= $dCheckin) $fromDATE = FormatDate($dCheckin, 'B');
			else $fromDATE = FormatDate($dFrom, 'B');

			$toDATE = '';
			if ($dTo <= $dCheckOut) $toDATE = FormatDate($dTo, 'B');
			else $toDATE = FormatDate($dCheckOut, 'B');

			$arr[$iRoomTypeID][] = array('FROM' => $fromDATE, 'TO' => $toDATE, 'SINGLE' => $fRate_SO, 'DOUBLE' => $fRate_DO, 'X_ADULT' => $fRate_ExtraAdult, 'X_CHILD' => $fRate_ExtraChild);
		}
	}

	return $arr;
}

function LogRequests20210917($conc_session_id, $request_id, $id, $type, $mode, $desc_str = '', $user_id = false)
{
	global $_SERVER, $sess_user_id, $sess_user_name;
	$sess_user_locid = '1';

	if ($desc_str != '') {
		$ip = $_SERVER['REMOTE_ADDR'];

		$iLRID = NextID('iLRID', 'log_requests');
		$q = "insert into log_requests values ($iLRID, $sess_user_locid, $sess_user_id, '" . db_input($sess_user_name) . "', '" . NOW . "', '$conc_session_id', '$request_id', '$id', '$type', '$mode', '" . db_input($desc_str) . "', '$ip', 'A')";
		$r = sql_query($q, 'COM.1421');
	}
}

function LogRequests($conc_session_id, $request_id, $id, $type, $mode, $desc_str = '', $user_id = 0, $hotel_name = '', $roomtype_name = '')
{
	global $_SERVER, $sess_user_id, $sess_user_name, $REQ_TYPE_ARR, $BOOK_TYPE_ARR;
	$sess_user_locid = '1';

	$checkin_date = $checkout_date = '';
	if (in_array($type, $REQ_TYPE_ARR) && !empty($request_id)) {
		$_q = 'select dCheckin, dCheckOut from concrequest where iRequestID=' . $request_id;
		$_r = sql_query($_q, '');
		list($checkin_date, $checkout_date) = sql_fetch_row($_r);
	} elseif (in_array($type, $BOOK_TYPE_ARR) && !empty($request_id)) {
		$_q = 'select dCheckin, dCheckOut from concbooking where iBookingID=' . $request_id;
		$_r = sql_query($_q, '');
		list($checkin_date, $checkout_date) = sql_fetch_row($_r);
	}

	if (empty($checkin_date)) $checkin_date = 'NULL';
	else $checkin_date = "'" . $checkin_date . "'";

	if (empty($checkout_date)) $checkout_date = 'NULL';
	else $checkout_date = "'" . $checkout_date . "'";

	if ($desc_str != '') {
		$ip = $_SERVER['REMOTE_ADDR'];

		$iLRID = NextID('iLRID', 'log_requests');
		$q = "insert into log_requests values ($iLRID, $sess_user_locid, $sess_user_id, '" . db_input($sess_user_name) . "', '" . NOW . "', '$conc_session_id', '$request_id', '$id', '$type', '$mode', '" . db_input($desc_str) . "', '" . db_input($hotel_name) . "', '" . db_input($roomtype_name) . "', $checkin_date, $checkout_date, '$ip', 'A')";
		$r = sql_query($q, 'COM.1421');
	}
}

function GetRequestSummary($request_id, $session_id, $HOTEL_ARR, $ROOMTYPE_ARR)
{
	$str = '';
	global $HOTELENQUIRY_STATUS_ARR, $HOTELENQUIRY_COLOR_ARR;

	$_aq = 'select iRequestDatID, iNumPax, iNumRooms, iHotelRating, vDescription, cStatus from concrequestdat where iRequestID=' . $request_id . ' order by iRequestDatID';
	$_ar = sql_query($_aq, '');
	if (sql_num_rows($_ar)) {
		while (list($iRequestDatID, $iNumPax, $iNumRooms, $iHotelRating, $vDescription, $cStatus) = sql_fetch_row($_ar)) {
			$HOTELS_DET = array();
			$_aq2 = 'select iRequestDatOptionID, iHotelID, iRoomTypeID, fCost from concrequestdat_options where iRequestID=' . $request_id . ' and iRequestDatID=' . $iRequestDatID . ' order by iRequestDatOptionID';
			$_ar2 = sql_query($_aq2, '');
			if (sql_num_rows($_ar2)) {
				while (list($iRequestDatOptionID, $iHotelID, $iRoomTypeID, $fCost) = sql_fetch_row($_ar2))
					$HOTELS_DET[$iRequestDatOptionID] = array('HOTEL_ID' => $iHotelID, 'ROOM_TYPEID' => $iRoomTypeID, 'COST' => $fCost);
			}

			$ACCOMODATION_ARR[$iRequestDatID] = array('GUESTS' => $iNumPax, 'ROOMS' => $iNumRooms, 'RATINGS' => $iHotelRating, 'DESCRIPTIONS' => htmlspecialchars_decode($vDescription), 'STATUS' => $cStatus, 'HOTEL' => $HOTELS_DET);
		}
	}

	$ENQUIRY_ARR = array();
	$_eq = 'select iHotelEnqID, iHotelID, iRoomTypeID, iRequestDatOptionID, dtEnq, cReqSeen, dtReqSeen, iReqSeen_UserID, cAvailable, iAvailable_UserID, dtResponse, cResponseSeenBy, iResponseSeenBy_UserID, dtResponseSeen, cResponseSeenByRM, dtResponseSeenByRM, cBookReq, dtBookReq, iBookRequest_UserID, cBookReqSeen, dtBookReqSeen, iBookReqSeen_UserID, cBooked, dtBooked, iBooked_UserID, vBookingRef, cStatus from concrequest_hotelenq where iRequestID=' . $request_id;
	$_er = sql_query($_eq, '');
	if (sql_num_rows($_er)) {
		while (list($iHotelEnqID, $iHotelID, $iRoomTypeID, $iRequestDatOptionID, $dtEnq, $cReqSeen, $dtReqSeen, $iReqSeen_UserID, $cAvailable, $iAvailable_UserID, $dtResponse, $cResponseSeenBy, $iResponseSeenBy_UserID, $dtResponseSeen, $cResponseSeenByRM, $dtResponseSeenByRM, $cBookReq, $dtBookReq, $iBookRequest_UserID, $cBookReqSeen, $dtBookReqSeen, $iBookReqSeen_UserID, $cBooked, $dtBooked, $iBooked_UserID, $vBookingRef, $cStatus) = sql_fetch_row($_er)) {
			$ENQUIRY_ARR[$iRequestDatOptionID] = array('ENQ_ID' => $iHotelEnqID, 'ENQ_HOTELID' => $iHotelID, 'ENQ_ROOMTYPEID' => $iRoomTypeID, 'ENQ_DATE' => $dtEnq, 'ENQ_REQSEEN' => $cReqSeen, 'ENQ_REQSEEN_DT' => $dtReqSeen, 'ENQ_REQSEEN_BY' => $iReqSeen_UserID, 'ENQ_AVAILABLE' => $cAvailable, 'ENQ_AVAILABLE_BY' => $iAvailable_UserID, 'ENQ_RESPONSE_DT' => $dtResponse, 'ENQ_RESPONSE_SEEN' => $cResponseSeenBy, 'ENQ_RESPONSE_SEENBY' => $iResponseSeenBy_UserID, 'ENQ_RESPONSE_SEENDT' => $dtResponseSeen, 'ENQ_RESPONSE_SEENBY_RM' => $cResponseSeenByRM, 'ENQ_RESPONSE_SEENDT_RM' => $dtResponseSeenByRM, 'ENQ_REQBOOK' => $cBookReq, 'ENQ_REQBOOK_DT' => $dtBookReq, 'ENQ_REQBOOK_BY' => $iBookRequest_UserID, 'ENQ_REQBOOK_SEEN' => $cBookReqSeen, 'ENQ_REQBOOK_SEENDT' => $dtBookReqSeen, 'ENQ_REQBOOK_SEENBY' => $iBookReqSeen_UserID, 'ENQ_BOOKED' => $cBooked, 'ENQ_BOOKED_DT' => $dtBooked, 'ENQ_BOOKED_BY' => $iBooked_UserID, 'ENQ_BOOK_RFFNUM' => $vBookingRef, 'ENQ_STATUS' => $cStatus);
		}
	}

	$BOOKING_ARR = array();
	$_bq = 'select b.iBookingID, b.dtBooking, bd.iRequestDatOptionID, bd.iHotelID, bd.iRoomTypeID from concbooking as b join concbooking_dat as bd on b.iBookingID=bd.iBookingID where b.iConcSessionID=' . $session_id;
	$_br = sql_query($_bq, '');
	if (sql_num_rows($_br)) {
		while (list($bookingID, $bookingDate, $bookingRequestID, $bookingHotelID, $bookingRoomTypeID) = sql_fetch_row($_br)) {
			$BOOKING_ARR[$bookingRequestID] = array('BOOKING_ID' => $bookingID, 'BOOKING_DATE' => $bookingDate, 'BOOKING_HOTEL' => $bookingHotelID, 'BOOKING_ROOMTYPE' => $bookingRoomTypeID);
		}
	}

	if (!empty($ACCOMODATION_ARR) && count($ACCOMODATION_ARR)) {
		$a = '1';
		foreach ($ACCOMODATION_ARR as $datID => $datVALUE) {
			$str .= '<div>';
			$str .= '<p><strong>Accomodation ' . $a . ' | Guests: ' . $datVALUE['GUESTS'] . ' | Rooms: ' . $datVALUE['ROOMS'] . '</strong></p>';
			if (!empty($datVALUE['HOTEL']) && count($datVALUE['HOTEL'])) {
				foreach ($datVALUE['HOTEL'] as $hKEY => $hVALUE) {
					$str .= '<p>';
					$str .= 'Hotel: <u>' . $HOTEL_ARR[$hVALUE['HOTEL_ID']] . '</u> | RoomType: <u>' . $ROOMTYPE_ARR[$hVALUE['ROOM_TYPEID']] . '</u> | Cost: <u>Rs. ' . FormatNumber($hVALUE['COST'], 2) . '</u>';

					$lastestACTION = '<span class="btn-pill btn-transition btn btn-outline-' . $HOTELENQUIRY_COLOR_ARR['NA'] . '">' . $HOTELENQUIRY_STATUS_ARR['NA'] . '</span>';
					if (!empty($ENQUIRY_ARR[$hKEY]) && count($ENQUIRY_ARR[$hKEY])) {
						$ENQ_ID = $ENQUIRY_ARR[$hKEY]['ENQ_ID'];

						$lastestACTION = '<span class="btn-pill btn-transition btn btn-outline-' . $HOTELENQUIRY_COLOR_ARR['RS'] . '">' . $HOTELENQUIRY_STATUS_ARR['RS'] . '</span>';
						if ($ENQUIRY_ARR[$hKEY]['ENQ_AVAILABLE'] != 'X') {
							$IS_AVAILABLE = ($ENQUIRY_ARR[$hKEY]['ENQ_AVAILABLE'] == 'Y') ? 'AV' : 'NAV';
							$IS_AVAILABLE_CLASS = ($ENQUIRY_ARR[$hKEY]['ENQ_AVAILABLE'] == 'Y') ? 'AV' : 'NAV';
							$IS_AVAILABLE_CLASS2 = ($ENQUIRY_ARR[$hKEY]['ENQ_AVAILABLE'] == 'Y') ? 'success' : 'danger';

							$lastestACTION = '<span class="btn-pill btn-transition btn btn-outline-' . $HOTELENQUIRY_COLOR_ARR[$IS_AVAILABLE_CLASS] . '">' . $HOTELENQUIRY_STATUS_ARR[$IS_AVAILABLE] . '</span>';

							if ($ENQUIRY_ARR[$hKEY]['ENQ_AVAILABLE'] != 'N') {
								if ($ENQUIRY_ARR[$hKEY]['ENQ_REQBOOK'] == 'Y')
									$lastestACTION = '<span class="btn-pill btn-transition btn btn-outline-' . $HOTELENQUIRY_COLOR_ARR['RMC'] . '">' . $HOTELENQUIRY_STATUS_ARR['RMC'] . '</span>';

								if ($ENQUIRY_ARR[$hKEY]['ENQ_BOOKED'] == 'Y')
									$lastestACTION = '<span class="btn-pill btn-transition btn btn-outline-' . $HOTELENQUIRY_COLOR_ARR['HC'] . '">' . $HOTELENQUIRY_STATUS_ARR['HC'] . '</span>';
							}
						}
					}

					if (!empty($BOOKING_ARR[$hKEY]))
						$lastestACTION = '<span class="btn-pill btn-transition btn btn-outline-' . $HOTELENQUIRY_COLOR_ARR['BK'] . '">' . $HOTELENQUIRY_STATUS_ARR['BK'] . '</span>';

					$str .= ' | ' . $lastestACTION;

					$str .= '</p>';
				}
			}
			$str .= '</div>';
			$str .= '<div class="divider"></div>';

			$a++;
		}
	} else
		$str = 'No Records Found.';

	return $str;
}

function GetActivityLog($sess_user_level, $sess_user_loc = array())
{
	$arr = $ROOMTYPE_ARR = array();

	global $REQ_TYPE_ARR, $BOOK_TYPE_ARR, $USER_PROPERTY_STR;

	$LIMIT = '10';
	if ($sess_user_level == 0 || $sess_user_level == '2') {
		$_mq = 'select iLMID, dtDate, vDesc, cMode, vIP, iLocID, iUserID, vUserName, iRefID, cRefType, vRefName from log_masters order by dtDate desc limit 20';
		$_mr = sql_query($_mq, '');
		if (sql_num_rows($_mr)) {
			while (list($x_id, $x_dt, $x_desc, $x_mode, $x_ip, $l_id, $u_id, $u_name, $y_id, $y_type, $y_name) = sql_fetch_row($_mr)) {
				$y_type_str = '<div style="float:left;background-color:#eaeaea;border:1px solid #000;padding:0px 3px;margin:0px;">' . $y_type . '</div>&nbsp;';

				$url = '';
				if ($y_type == 'USR') $url = 'user_edit.php?mode=E&id=' . $y_id;
				elseif ($y_type == 'HTL') $url = 'hotels_edit.php?mode=E&id=' . $y_id;
				else if ($y_type == 'RTY') {
					if (!isset($ROOMTYPE_ARR[$y_id])) $ROOMTYPE_ARR[$y_id] = GetXFromYID('select iHotelID from gen_roomtype where iRoomTypeID=' . $y_id);
					$url = 'hotel_roomtypes_edit.php?mode=E&id=' . $y_id . '&hotel_id=' . $ROOMTYPE_ARR[$y_id];
				} else if ($y_type == 'TRF') $url = 'tariff_edit.php?mode=E&id=' . $y_id;

				$y_name_str = '<a href="' . $url . '" target="_blank">' . $y_name . '</a>';
				//$u_str = 'U/'.$l_id.'/'.$u_id.' @ '.$x_ip;
				$u_str = 'U/' . $u_id . ' @ ' . $x_ip;

				$arr[$x_id . '~LM'] = array('DATE2' => $x_dt, 'DATE' => FormatDate($x_dt, 'J'), 'DATE3' => LastOnlineSeen($x_dt), 'DATE4' => FormatDate($x_dt, '27'), 'TYPE' => stripslashes(htmlspecialchars_decode($y_type_str)), 'DESC' => htmlspecialchars_decode($y_name_str . ': ' . $x_desc), 'USER_STR' => $u_str, 'USERNAME' => $u_name);
			}

			$LIMIT = '5';
		}
	}

	$REQUEST_ID_ARR = $BOOKING_ID_ARR = array();
	if (!empty($USER_PROPERTY_STR)) {
		$REQUEST_ID_ARR = GetXArrFromYID('select distinct(iRequestID) from concrequest_property where iPropertyID IN (' . $USER_PROPERTY_STR . ')');
		$BOOKING_ID_ARR = GetXArrFromYID('select distinct(iBookingID) from concbooking_property where iPropertyID IN (' . $USER_PROPERTY_STR . ')');
	} else $cond = ' and iLRID=0';

	$_rq = 'select iLRID, dtDate, vDesc, cMode, vIP, iLocID, iUserID, vUserName, iRequestID, iRefID, cRefType, vHotelName, vRoomTypeName, dCheckIn, dCheckOut from log_requests order by dtDate desc limit 20'; //.$LIMIT;
	$_rr = sql_query($_rq, '');
	if (sql_num_rows($_rr)) {
		while (list($x_id, $x_dt, $x_desc, $x_mode, $x_ip, $l_id, $u_id, $u_name, $y_id, $y_refid, $y_reftype, $vHotelName, $vRoomTypeName, $dCheckIn, $dCheckOut) = sql_fetch_row($_rr)) {
			$DISPLAY = 'N';
			if (in_array($y_reftype, $REQ_TYPE_ARR)) {
				if (!empty($REQUEST_ID_ARR) && count($REQUEST_ID_ARR) && in_array($y_id, $REQUEST_ID_ARR))
					$DISPLAY = 'Y';
			} elseif (in_array($y_reftype, $BOOK_TYPE_ARR)) {
				if (!empty($BOOKING_ID_ARR) && count($BOOKING_ID_ARR) && in_array($y_id, $BOOKING_ID_ARR))
					$DISPLAY = 'Y';
			}

			if ($DISPLAY == 'N')
				continue;

			$url = '';
			if (in_array($y_reftype, $REQ_TYPE_ARR)) $url = 'requests_view.php?id=' . $y_id;
			elseif (in_array($y_reftype, $BOOK_TYPE_ARR)) $url = 'bookings_view.php?id=' . $y_id;

			if (in_array($y_reftype, $REQ_TYPE_ARR)) $y_name_str = '<a href="' . $url . '" target="_blank">Request</a>';
			elseif (in_array($y_reftype, $BOOK_TYPE_ARR)) $y_name_str = '<a href="' . $url . '" target="_blank">Booking</a>';
			//$u_str = 'U/'.$l_id.'/'.$u_id.' @ '.$x_ip;
			$u_str = 'U/' . $u_id . ' @ ' . $x_ip;

			$arr[$x_id . '~LR'] = array('DATE2' => $x_dt, 'DATE' => FormatDate($x_dt, 'J'), 'DATE3' => LastOnlineSeen($x_dt), 'DATE4' => FormatDate($x_dt, '27'), 'TYPE' => stripslashes(htmlspecialchars_decode($y_name_str)), 'DESC' => stripslashes(htmlspecialchars_decode($x_desc)), 'USER_STR' => $u_str, 'USERNAME' => $u_name, 'HOTEL' => stripslashes(htmlspecialchars_decode($vHotelName)), 'ROOMTYPE' => stripslashes(htmlspecialchars_decode($vRoomTypeName)), 'CHECKIN' => $dCheckIn, 'CHECKOUT' => $dCheckOut);
		}
	}

	if (!empty($arr)) $arr = MultiDimensionalArraySort($arr, 'DATE2', SORT_DESC);
	if (!empty($arr)) $arr = array_slice($arr, 0, 10);

	return $arr;
}

function GetCheckInCheckOutDetails20220412($date = '')
{
	$arr = array();
	global $USER_PROPERTY_STR;

	$cond = '';
	if (empty($USER_PROPERTY_STR))
		return $arr;
	else {
		$BOOKING_ID_ARR = GetXArrFromYID('select distinct(iBookingID) from concbooking_property where iPropertyID IN (' . $USER_PROPERTY_STR . ')');
		if (!empty($BOOKING_ID_ARR) && count($BOOKING_ID_ARR))
			$cond = ' and cb.iBookingID IN (' . implode(',', $BOOKING_ID_ARR) . ')';
		else
			$cond = ' and cb.iBookingID=0';
	}

	if (empty($date)) $date = TODAY;

	$q = 'select cb.iBookingID, cb.vBookingRefNum, cb.iRMContactID, cb.vClient, cb.vContactNum_Client, cb.dCheckin, cb.dCheckOut, cb2.iHotelID, cb2.iRoomTypeID, cb2.iGuests, cb2.iRooms, cb2.vNotes, cb2.cLateCheckOut, cb2.cEarlyCheckIn, cb2.cCheckedIn, cb2.cCheckedOut from concbooking as cb join concbooking_dat as cb2 on cb.iBookingID=cb2.iBookingID where cb2.cStatus="A" and (cb.dCheckin="' . $date . '" or cb.dCheckOut="' . $date . '")';
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		while (list($iBookingID, $vBookingRefNum, $iRMContactID, $vClient, $vContactNum_Client, $dCheckin, $dCheckOut, $iHotelID, $iRoomTypeID, $iGuests, $iRooms, $vNotes, $cLateCheckOut, $cEarlyCheckIn, $cCheckedIn, $cCheckedOut) = sql_fetch_row($r)) {
			if ($dCheckin == $date) {
				if (!isset($arr['CHECKIN'])) $arr['CHECKIN'] = array();
				$arr['CHECKIN'][] = array('ID' => $iBookingID, 'REF_NO' => $vBookingRefNum, 'HOTEL_ID' => $iHotelID, 'ROOMTYPE_ID' => $iRoomTypeID, 'CONTACT_ID' => $iRMContactID, 'CONTACT_NAME' => htmlspecialchars_decode($vClient), 'CONTACT_NO' => $vContactNum_Client, 'GUESTS' => $iGuests, 'ROOMS' => $iRooms, 'NOTES' => htmlspecialchars_decode($vNotes), 'EARLY_CHECKIN' => $cEarlyCheckIn, 'CHECKED_IN' => $cCheckedIn);
			} elseif ($dCheckOut == $date) {
				if (!isset($arr['CHECKOUT'])) $arr['CHECKOUT'] = array();
				$arr['CHECKOUT'][] = array('ID' => $iBookingID, 'REF_NO' => $vBookingRefNum, 'HOTEL_ID' => $iHotelID, 'ROOMTYPE_ID' => $iRoomTypeID, 'CONTACT_ID' => $iRMContactID, 'CONTACT_NAME' => htmlspecialchars_decode($vClient), 'CONTACT_NO' => $vContactNum_Client, 'GUESTS' => $iGuests, 'ROOMS' => $iRooms, 'NOTES' => htmlspecialchars_decode($vNotes), 'LATE_CHECKOUT' => $cLateCheckOut, 'CHECKED_OUT' => $cCheckedOut);
			}
		}
	}

	return $arr;
}

function GetCheckInCheckOutDetails($date = '')
{
	$arr = array();
	global $USER_PROPERTY_STR;

	$cond = '';
	if (empty($USER_PROPERTY_STR))
		return $arr;
	else {
		$BOOKING_ID_ARR = GetXArrFromYID('select distinct(iBookingID) from concbooking_property where iPropertyID IN (' . $USER_PROPERTY_STR . ')');
		if (!empty($BOOKING_ID_ARR) && count($BOOKING_ID_ARR)) {
			$cond = ' and cb.iBookingID IN (' . implode(',', $BOOKING_ID_ARR) . ')';
			$cond2 = ' and iBookingID IN (' . implode(',', $BOOKING_ID_ARR) . ')';
		} else {
			$cond = ' and cb.iBookingID=0';
			$cond2 = ' and iBookingID=0';
		}
	}

	if (empty($date)) $date = TODAY;

	$ROOM_DETAILS_ARR = array();
	$_rq = 'select iRoomID, iBookingID, iBookingDatID, iRoomNo, iGuest, cCheckedIn, cCheckedOut, cNoShow from concbooking_dat_rooms where cStatus!="X"' . $cond2 . ' order by iRoomNo';
	$_rr = sql_query($_rq, '');
	if (sql_num_rows($_rr)) {
		while (list($iRoomID, $iBookingID, $iBookingDatID, $iRoomNo, $iGuest, $cCheckedIn, $cCheckedOut, $cNoShow) = sql_fetch_row($_rr)) {
			$GUEST = stripslashes(GetXFromYID('select vName from concbooking_guests where iBookingID=' . $iBookingID . ' and iBookingDatID=' . $iBookingDatID . ' and iRoomNo=' . $iRoomNo . ' and cStatus="A" order by iGuestID limit 1'));
			$ROOM_DETAILS_ARR[$iBookingID][$iBookingDatID][] = array('ID' => $iRoomID, 'NO' => $iRoomNo, 'GUESTS' => $iGuest, 'CHECKED_IN' => $cCheckedIn, 'CHECKED_OUT' => $cCheckedOut, 'NO_SHOW' => $cNoShow, 'GUEST' => $GUEST);
		}
	}

	$q = 'select cb.iBookingID, cb.vBookingRefNum, cb.iRMContactID, cb.vClient, cb.vContactNum_Client, cb.dCheckin, cb.dCheckOut, cb2.iBookingDatID, cb2.iHotelID, cb2.iRoomTypeID, cb2.iGuests, cb2.iRooms, cb2.vNotes, cb2.cLateCheckOut, cb2.cEarlyCheckIn, cb2.cCheckedIn, cb2.cCheckedOut from concbooking as cb join concbooking_dat as cb2 on cb.iBookingID=cb2.iBookingID where cb2.cStatus="A" and (cb.dCheckin="' . $date . '" or cb.dCheckOut="' . $date . '")' . $cond . ' order by cb2.iHotelID';
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		while (list($iBookingID, $vBookingRefNum, $iRMContactID, $vClient, $vContactNum_Client, $dCheckin, $dCheckOut, $iBookingDatID, $iHotelID, $iRoomTypeID, $iGuests, $iRooms, $vNotes, $cLateCheckOut, $cEarlyCheckIn, $cCheckedIn, $cCheckedOut) = sql_fetch_row($r)) {
			if ($dCheckin == $date) {
				if (!isset($arr['CHECKIN'][$iHotelID][$iBookingID][$iBookingDatID])) $arr['CHECKIN'][$iHotelID][$iBookingID][$iBookingDatID] = array('ID' => $iBookingID, 'REF_NO' => $vBookingRefNum, 'HOTEL_ID' => $iHotelID, 'ROOMTYPE_ID' => $iRoomTypeID, 'CONTACT_ID' => $iRMContactID, 'CONTACT_NAME' => htmlspecialchars_decode($vClient), 'CONTACT_NO' => $vContactNum_Client, 'GUESTS' => $iGuests, 'ROOMS' => $iRooms, 'NOTES' => htmlspecialchars_decode($vNotes), 'EARLY_CHECKIN' => $cEarlyCheckIn, 'CHECKED_IN' => $cCheckedIn, 'ROOM_DETAILS' => array());

				if (isset($ROOM_DETAILS_ARR[$iBookingID][$iBookingDatID]))
					$arr['CHECKIN'][$iHotelID][$iBookingID][$iBookingDatID]['ROOM_DETAILS'] = $ROOM_DETAILS_ARR[$iBookingID][$iBookingDatID];
			} elseif ($dCheckOut == $date) {
				if (!isset($arr['CHECKOUT'][$iHotelID][$iBookingID][$iBookingDatID])) $arr['CHECKOUT'][$iHotelID][$iBookingID][$iBookingDatID] = array('ID' => $iBookingID, 'REF_NO' => $vBookingRefNum, 'HOTEL_ID' => $iHotelID, 'ROOMTYPE_ID' => $iRoomTypeID, 'CONTACT_ID' => $iRMContactID, 'CONTACT_NAME' => htmlspecialchars_decode($vClient), 'CONTACT_NO' => $vContactNum_Client, 'GUESTS' => $iGuests, 'ROOMS' => $iRooms, 'NOTES' => htmlspecialchars_decode($vNotes), 'LATE_CHECKOUT' => $cLateCheckOut, 'CHECKED_OUT' => $cCheckedOut, 'ROOM_DETAILS' => array());

				if (isset($ROOM_DETAILS_ARR[$iBookingID][$iBookingDatID]))
					$arr['CHECKOUT'][$iHotelID][$iBookingID][$iBookingDatID]['ROOM_DETAILS'] = $ROOM_DETAILS_ARR[$iBookingID][$iBookingDatID];
			}
		}
	}

	return $arr;
}

function ConvertRequestToBooking($sess_id, $request_id, $option_id, $enquiry_id)
{
	global $sess_user_name, $sess_user_id, $YES_ARR;

	$_rq = 'select iRequestDatID, iHotelID, iRoomTypeID, fCost from concrequestdat_options where iRequestID=' . $request_id . ' and iRequestDatOptionID=' . $option_id;
	$_rr = sql_query($_rq, '');
	list($iRequestDatID, $iHotelID, $iRoomTypeID, $fCost) = sql_fetch_row($_rr);

	$txtnewbooking_hotel = GetXFromYID('select vName from gen_hotel where iHotelID=' . $iHotelID);
	$txtnewbooking_roomtype = GetXFromYID('select vName from gen_roomtype where iRoomTypeID=' . $iRoomTypeID);

	$txtnewbooking_refno = GetXFromYID('select vBookingRef from concrequest_hotelenq where iHotelEnqID=' . $enquiry_id . ' and iRequestID=' . $request_id . ' and iRequestDatOptionID=' . $option_id);

	$_rq2 = 'select iNumPax, iNumRooms, vInclusions, vBillingInstructions, cEarlyCheckIn, cLateCheckOut from concrequestdat where iRequestID=' . $request_id . ' and iRequestDatID=' . $iRequestDatID;
	$_rr2 = sql_query($_rq2, '');
	list($iNumPax, $iNumRooms, $vInclusions, $vBillingInstructions, $cEarlyCheckIn, $cLateCheckOut) = sql_fetch_row($_rr2);

	if (!empty($vInclusions)) $vInclusions = stripslashes(htmlspecialchars_decode($vInclusions));
	if (!empty($vBillingInstructions)) $vBillingInstructions = stripslashes(htmlspecialchars_decode($vBillingInstructions));

	$_rq3 = 'select iRMContactID, vClient, vContactNum_Client, dCheckin, dCheckOut, iUserID_RM from concrequest where iRequestID=' . $request_id . ' and iConcSessionID=' . $sess_id;
	$_rr3 = sql_query($_rq3, '');
	list($iRMContactID, $vClient, $vContactNum_Client, $dCheckin, $dCheckOut, $iUserID_RM) = sql_fetch_row($_rr3);

	$REQUEST_PROPERTY = GetXArrFromYID('select iPropertyID from concrequest_property where iRequestID=' . $request_id);

	$ROOM_ARR = array();
	$_rqR2 = 'select iRoomNo, iGuest, vBillingInstructions from concrequestdat_rooms where iRequestID=' . $request_id . ' and iRequestDatID=' . $iRequestDatID . ' and cStatus="A" order by iRoomNo';
	$_rrR2 = sql_query($_rqR2, '');
	if (sql_num_rows($_rrR2)) {
		while (list($iRoomNo, $iGuest, $vBillingInstructions) = sql_fetch_row($_rrR2))
			$ROOM_ARR[$iRoomNo] = array('iGuest' => $iGuest, 'vBillingInstructions' => stripslashes(htmlspecialchars_decode($vBillingInstructions)));
	}

	$GUEST_ARR = array();
	$_rq4 = 'select iRoomNo, iGuestID, vName, vContactNum from concrequestdat_guests where iRequestID=' . $request_id . ' and iRequestDatID=' . $iRequestDatID . ' order by iGuestID';
	$_rr4 = sql_query($_rq4, '');
	if (sql_num_rows($_rr4)) {
		while (list($iRoomNo, $iGuestID, $vName, $vContactNum) = sql_fetch_row($_rr4))
			$GUEST_ARR[$iRoomNo][] = array('NAME' => htmlspecialchars_decode($vName), 'NO' => $vContactNum);
	}

	$bookingrefno_Exists = GetXFromYID('select iBookingID from concbooking where iHotelID=' . $iHotelID . ' and iConcSessionID=' . $sess_id . ' and vBookingRefNum="' . $txtnewbooking_refno . '"');
	if (empty($bookingrefno_Exists) || $bookingrefno_Exists == '-1') {
		$vClient = stripslashes(htmlspecialchars_decode($vClient));

		LockTable('concbooking');
		$conc_bookingid = NextID('iBookingID', 'concbooking');
		sql_query("insert into concbooking values ('$conc_bookingid', '" . NOW . "', '$sess_user_id', '$iUserID_RM', '" . $sess_id . "', '$iHotelID', '$txtnewbooking_refno', 'N', '0', '$iRMContactID', '$vClient', '$vContactNum_Client', '', '" . $dCheckin . "', '" . $dCheckOut . "', '0', '0', '', NULL, NULL, '', '', '', NULL, 0, '', '', NULL, NULL, '', 'A')", "");
		UnlockTable(); //*/

		if (!empty($REQUEST_PROPERTY) && count($REQUEST_PROPERTY)) {
			foreach ($REQUEST_PROPERTY as $property_id) {
				if (!empty($property_id))
					sql_query("insert into concbooking_property values ('$conc_bookingid', '$property_id')", "");
			}
		}

		//$desc_str = 'New Booking Created by '.$sess_user_name.'. Hotel: '.$txtnewbooking_hotel.', Booking No: '.$txtnewbooking_refno.', Checkin Date: '.$dCheckin.', Checkout Date: '.$dCheckOut.'.';
		$desc_str = 'New Booking Created.';
		LogRequests($sess_id, $conc_bookingid, 0, 'BO', 'I', $desc_str, $sess_user_id, $txtnewbooking_hotel, $txtnewbooking_roomtype);
	} else
		$conc_bookingid = $bookingrefno_Exists;

	if (empty($cEarlyCheckIn)) $cEarlyCheckIn = "N";
	if (empty($cLateCheckOut)) $cLateCheckOut = "N";

	LockTable('concbooking_dat');
	$conc_bookingdatid = NextID('iBookingDatID', 'concbooking_dat');
	sql_query("insert into concbooking_dat values ('$conc_bookingdatid', '$conc_bookingid', '" . $sess_id . "', '$option_id', '$sess_user_id', '$iHotelID', '$iRoomTypeID', '$iNumPax', '$iNumRooms', '', '" . $dCheckin . "', '" . $dCheckOut . "', '$cLateCheckOut', '$cEarlyCheckIn', '$vInclusions', '$vBillingInstructions', 'N', 'N', '$fCost', 'A')", "");
	UnlockTable(); //*/

	$desc_str = 'New RoomType details added for booking by ' . $sess_user_name . '. Room Type: ' . $txtnewbooking_roomtype . ', Guests: ' . $iNumPax . ', Rooms: ' . $iNumRooms . ', Early Checkin: ' . $YES_ARR[$cEarlyCheckIn] . ', Late CheckOut: ' . $YES_ARR[$cLateCheckOut] . '.';
	LogRequests($sess_id, $conc_bookingid, $conc_bookingdatid, 'BD', 'I', $desc_str, $sess_user_id);

	if (!empty($iNumRooms)) {
		for ($r = 1; $r <= $iNumRooms; $r++) {
			if (isset($GUEST_ARR[$r]) && !empty($GUEST_ARR[$r]) && count($GUEST_ARR[$r])) {
				foreach ($GUEST_ARR[$r] as $gKEY => $gVALUE) {
					$guestNAME = db_input2($gVALUE['NAME']);
					$guestNO = db_input2($gVALUE['NO']);

					$conc_guestid = NextID('iGuestID', 'concbooking_guests');
					sql_query("insert into concbooking_guests values ('$conc_guestid', '$conc_bookingid', '$conc_bookingdatid', '$sess_id', '$r', '$guestNAME', '$guestNO', '$conc_guestid', 'A')", "");

					$desc_str = 'New Guest details added. Guest: ' . $guestNAME . '.';
					LogRequests($sess_id, $conc_bookingid, $conc_bookingdatid, 'BG', 'I', $desc_str, $sess_user_id, $txtnewbooking_hotel, $txtnewbooking_roomtype);
				}
			}
		}
	}

	if (!empty($ROOM_ARR) && count($ROOM_ARR)) {
		foreach ($ROOM_ARR as $rKEY => $rVALUE) {
			$GUESTS = $rVALUE['iGuest'];
			$BILLING_INSTRUCTIONS = $rVALUE['vBillingInstructions'];

			LockTable('concbooking_dat_rooms');
			$conc_booking_roomid = NextID('iRoomID', 'concbooking_dat_rooms');
			sql_query("insert into concbooking_dat_rooms values ('$conc_booking_roomid', '$conc_bookingid', '$conc_bookingdatid', '$rKEY', '$GUESTS', '$BILLING_INSTRUCTIONS', 'N', 'N', 'N', 'A')", "");
			UnlockTable();
		}
	}

	$txttotalbooking_amount = GetXFromYID('select sum(fCost) from concbooking_dat where iBookingID=' . $conc_bookingid);
	sql_query("update concbooking set fEstimateAmt='$txttotalbooking_amount' where iBookingID='$conc_bookingid'", "");

	return $conc_bookingid;
}

function GetNewRequestDetails()
{
	$arr = array();
	global $USER_PROPERTY_STR;

	$cond = '';
	if (empty($USER_PROPERTY_STR))
		return $arr;
	else {
		$REQUEST_ID_ARR = GetXArrFromYID('select distinct(iRequestID) from concrequest_property where iPropertyID IN (' . $USER_PROPERTY_STR . ')');
		if (!empty($REQUEST_ID_ARR) && count($REQUEST_ID_ARR))
			$cond = ' and iRequestID IN (' . implode(',', $REQUEST_ID_ARR) . ')';
		else
			$cond = ' and iRequestID=0';
	}

	$q = 'select iRequestID, dtRequest, iUserID_RM, iRMContactID, vClient, vContactNum_Client, iUserID_request, vRequest, dCheckin, dCheckOut from concrequest where cStatus="D"' . $cond . ' order by dtRequest desc';
	$r = sql_query($q, '');
	$r2 = sql_query($q, '');
	if (sql_num_rows($r)) {
		$detSTR = '';
		for ($i = 1; $o = sql_fetch_object($r2); $i++) {
			$x_id2 =  $o->iRequestID;
			$detSTR .= $x_id2 . ',';
		}

		$DET_ARR = array();
		if (!empty($detSTR)) {
			$detSTR = substr($detSTR, 0, '-1');
			$_q = 'select iRequestID, iNumPax, iNumRooms, iHotelRating from concrequestdat where iRequestID IN (' . $detSTR . ')';
			$_r = sql_query($_q, '');
			if (sql_num_rows($_r)) {
				while (list($iRequestID, $iNumPax, $iNumRooms, $iHotelRating) = sql_fetch_row($_r)) {
					if (!isset($DET_ARR[$iRequestID]['GUESTS'])) $DET_ARR[$iRequestID]['GUESTS'] = 0;
					if (!isset($DET_ARR[$iRequestID]['ROOMS'])) $DET_ARR[$iRequestID]['ROOMS'] = 0;

					$DET_ARR[$iRequestID]['GUESTS'] = $DET_ARR[$iRequestID]['GUESTS'] + $iNumPax;
					$DET_ARR[$iRequestID]['ROOMS'] = $DET_ARR[$iRequestID]['ROOMS'] + $iNumRooms;
				}
			}
		}

		while (list($iRequestID, $dtRequest, $iUserID_RM, $iRMContactID, $vClient, $vContactNum_Client, $iUserID_request, $vRequest, $dCheckin, $dCheckOut) = sql_fetch_row($r)) {
			$GUESTS = $ROOMS = '-NA-';
			if (isset($DET_ARR[$iRequestID]) && !empty($DET_ARR[$iRequestID])) {
				$GUESTS = $DET_ARR[$iRequestID]['GUESTS'];
				$ROOMS = $DET_ARR[$iRequestID]['ROOMS'];
			}

			$arr[$iRequestID] = array('DATE' => $dtRequest, 'RM_ID' => $iUserID_RM, 'CONTACT_ID' => $iRMContactID, 'CONTACT_NAME' => stripslashes(htmlspecialchars_decode($vClient)), 'CONTACT_NO' => $vContactNum_Client, 'USER_ID' => $iUserID_request, 'REQUEST' => $vRequest, 'CHECKIN' => $dCheckin, 'CHECKOUT' => $dCheckOut, 'GUESTS' => $GUESTS, 'ROOMS' => $ROOMS);
		}
	}

	return $arr;
}

function SendRequestMail($request_id, $requestdat_id, $requestoption_id, $enq_id, $hotelID, $hotelName, $roomtypeName, $userID)
{
	$str = '';
	global $OFFICIAL_EMAILID, $OFFICIAL_NAME;

	$q = 'select vClient, dCheckin, dCheckOut, vRequest from concrequest where iRequestID=' . $request_id;
	$r = sql_query($q, '');
	list($vClient, $dCheckin, $dCheckOut, $vRequest) = sql_fetch_row($r);

	$q2 = 'select iNumPax, iNumRooms, vBillingInstructions from concrequestdat where iRequestDatID=' . $requestdat_id . ' and iRequestID=' . $request_id;
	$r2 = sql_query($q2, '');
	list($iNumPax, $iNumRooms, $vBillingInstructions) = sql_fetch_row($r2);

	$q3 = 'select vName, vReservationsEmail, vReservationsEmail_CC, vReservationsEmail_BCC from gen_hotel where iHotelID=' . $hotelID;
	$r3 = sql_query($q3, '');
	list($hotelName, $vReservationsEmail, $vReservationsEmail_CC, $vReservationsEmail_BCC) = sql_fetch_row($r3);

	$q4 = 'select vName, vEmail, vPhone, iLevel from users where iUserID=' . $userID;
	$r4 = sql_query($q4, '');
	list($userName, $userEmail, $userPhone, $userLevel) = sql_fetch_row($r4);

	$ROOMBREAKUP_DET = $ROOMBILLINGINSTRUCTIONS_DET = array();
	$q5 = 'select iRoomNo, iGuest, vBillingInstructions from concrequestdat_rooms where iRequestID=' . $request_id . ' and iRequestDatID=' . $requestdat_id . ' and cStatus="A" order by iRoomNo';
	$r5 = sql_query($q5, '');
	if (sql_num_rows($r5)) {
		while (list($iRoomNo, $iGuest, $vBillingInstructions) = sql_fetch_row($r5)) {
			$ROOMBREAKUP_DET[$iRoomNo] = $iGuest;

			if (empty($vBillingInstructions)) $vBillingInstructions = 'NA';
			$ROOMBILLINGINSTRUCTIONS_DET[$iRoomNo] = stripslashes(htmlspecialchars_decode($vBillingInstructions));
		}
	}

	$BILLINGINSTRUCTIONS_DET = '';
	if (!empty($ROOMBILLINGINSTRUCTIONS_DET) && count($ROOMBILLINGINSTRUCTIONS_DET)) {
		foreach ($ROOMBILLINGINSTRUCTIONS_DET as $ROOM_NO => $ROOM_BILLINGINSTRUCTIONS)
			$BILLINGINSTRUCTIONS_DET .= '<strong>Room ' . $ROOM_NO . '</strong>: ' . $ROOM_BILLINGINSTRUCTIONS . ' | ';
	}
	if (!empty($BILLINGINSTRUCTIONS_DET)) $BILLINGINSTRUCTIONS_DET = substr($BILLINGINSTRUCTIONS_DET, '0', '-3');
	else $BILLINGINSTRUCTIONS_DET = '-NA-';

	$code = enCodeParamSMS($enq_id);

	$MAILER_TEMPLATE = file_get_contents(SITE_ADDRESS . 'mailers/request.html');

	$MAILER_CONTENT = '';
	$MAILER_CONTENT = str_replace('<HOTEL_NAME>', $hotelName, $MAILER_TEMPLATE);

	$MAIL_BODY = '';
	//ROOMTYPE
	$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 8px; color:#000; font-family:Verdana; font-size:14px;">Room Type: </td><td style="width: 50%; padding-top: 8px; color:#000; font-family:Verdana;font-size:14px;line-height: 20px; width:100%;
		display: flex;"><span style="width:10px; display:inline-block; width:15px;">: </span>' . stripslashes(htmlspecialchars_decode($roomtypeName)) . '</td></tr>';
	//CHECKIN
	$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">Check-In</td><td style="width: 50%; padding-top: 10px; color:#000;font-family:Verdana;font-size:14px;line-height: 20px; width:100%; isplay: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . FormatDate($dCheckin, 'B') . '</td></tr>';
	//CHECKOUT
	$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">Check-Out</td><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;line-height: 20px; width:100%; display: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . FormatDate($dCheckOut, 'B') . '</td></tr>';
	//NO OF ROOMS
	$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">No of Rooms</td><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px; line-height: 20px; width:100%; display: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . $iNumRooms . '</td></tr>';
	//NO OF GUESTS
	$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">No of Guests</td><td style="width: 50%; padding-top: 10px; color:#000s; font-family:Verdana;font-size:14px; line-height: 20px; width:100%; display: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . $iNumPax . '</td></tr>';

	$MAILER_CONTENT = str_replace('<MAIL_BODY>', $MAIL_BODY, $MAILER_CONTENT);

	$CLICK_HERE = 'Please <a href="https://concierge.deltin.com/hotel/action.php?code=' . $code . '">click here</a> to specify availability.';
	$MAILER_CONTENT = str_replace('<CLICK_HERE>', $CLICK_HERE, $MAILER_CONTENT);

	if (isset($USER_LEVEL_ARR[$userLevel])) $userLevel = $USER_LEVEL_ARR[$userLevel] . ',<br>';
	else $userLevel = '';

	$userContact = '';
	if (!empty($userEmail)) $userContact .= 'Email: ' . $userEmail . ' &nbsp;|&nbsp;';
	if (!empty($userEmail)) $userContact .= 'Call: ' . $userPhone . ' &nbsp;|&nbsp;';
	if (!empty($userContact)) $userContact = substr($userContact, 0, '-14');

	$USER_DETAILS = '<span style="font-weight:600;">' . stripslashes(htmlspecialchars_decode($userName)) . '</span>,<br><span style="font-size:13px;">' . $userLevel . $userContact . '</span>';
	$MAILER_CONTENT = str_replace('<USER_DETAILS>', $USER_DETAILS, $MAILER_CONTENT);

	$SUBJECT = $roomtypeName . ', IN: ' . FormatDate($dCheckin, 'B') . ', Out: ' . FormatDate($dCheckOut, 'B') . ', Rooms: ' . $iNumRooms;

	require("_send_mail.php");
	Send_Zohomail("Deltin Concierge", "Deltin Concierge", $vReservationsEmail, "concierge@deltin.com", $vReservationsEmail_CC, $vReservationsEmail_BCC, $SUBJECT, $MAILER_CONTENT, "Thank you for your Request", "Thank you for your Request", "", ""); //$vReservationsEmail,$vReservationsEmail_CC, $vReservationsEmail_BCC
	//send_kenscio1($vReservationsEmail,$SUBJECT,$MAILER_CONTENT);
	//SendMail($OFFICIAL_EMAILID, $OFFICIAL_NAME, $vReservationsEmail, '', '', $userEmail, $SUBJECT, $MAILER_CONTENT);
	//send_mailgun($SUBJECT, $vReservationsEmail, $MAILER_CONTENT, '', $vReservationsEmail_CC, $vReservationsEmail_BCC, $userEmail, 'Deltin Concierge');

	return $str;
}

function SendBookRequestMail($request_id, $requestdat_id, $requestoption_id, $enq_id, $hotelID, $hotelName, $roomtypeName, $userID)
{
	$str = '';
	global $OFFICIAL_EMAILID, $OFFICIAL_NAME, $OCCUPANCY_ARR, $OCCUPANCY_ARR3, $USER_LEVEL_ARR;

	$q = 'select vClient, dCheckin, dCheckOut, vRequest from concrequest where iRequestID=' . $request_id;
	$r = sql_query($q, '');
	list($vClient, $dCheckin, $dCheckOut, $vRequest) = sql_fetch_row($r);

	$q2 = 'select iNumPax, iNumRooms, vBillingInstructions from concrequestdat where iRequestDatID=' . $requestdat_id . ' and iRequestID=' . $request_id;
	$r2 = sql_query($q2, '');
	list($iNumPax, $iNumRooms, $vBillingInstructions) = sql_fetch_row($r2);

	$q3 = 'select vName, vReservationsEmail, vReservationsEmail_CC, vReservationsEmail_BCC from gen_hotel where iHotelID=' . $hotelID;
	$r3 = sql_query($q3, '');
	list($hotelName, $vReservationsEmail, $vReservationsEmail_CC, $vReservationsEmail_BCC) = sql_fetch_row($r3);

	$q4 = 'select vName, vEmail, vPhone, iLevel from users where iUserID=' . $userID;
	$r4 = sql_query($q4, '');
	list($userName, $userEmail, $userPhone, $userLevel) = sql_fetch_row($r4);

	$code = enCodeParamSMS($enq_id);

	$GUESTLIST_DET = array();
	$_gq2 = 'select iRoomNo, vName, vContactNum from concrequestdat_guests where iRequestID=' . $request_id . ' and iRequestDatID=' . $requestdat_id . ' order by iGuestID';
	$_gr2 = sql_query($_gq2, '');
	if (sql_num_rows($_gr2)) {

		while (list($guestRoomNo, $guestName, $guestContactNo) = sql_fetch_row($_gr2)) {
			//if(!empty($guestContactNo)) $guestName .= ' ('.$guestContactNo.')';
			if (!isset($GUESTLIST_DET[$guestRoomNo])) $GUESTLIST_DET[$guestRoomNo] = array();

			array_push($GUESTLIST_DET[$guestRoomNo], htmlspecialchars_decode($guestName));
		}
	}

	$ROOMBREAKUP_DET = array();
	$_aq2 = 'select iRoomNo, iGuest, vBillingInstructions from concrequestdat_rooms where iRequestID=' . $request_id . ' and iRequestDatID=' . $requestdat_id . ' and cStatus="A" order by iRoomNo';
	$_ar2 = sql_query($_aq2, '');
	if (sql_num_rows($_ar2)) {
		while (list($iRoomNo, $iGuest, $vBillingInstructions) = sql_fetch_row($_ar2))
			$ROOMBREAKUP_DET[$iRoomNo] = array('iGuest' => $iGuest, 'vBillingInstructions' => stripslashes(htmlspecialchars_decode($vBillingInstructions)));
	}


	$MAILER_TEMPLATE = file_get_contents(SITE_ADDRESS . 'mailers/confirmation.html');

	$MAILER_CONTENT = '';
	$MAILER_CONTENT = str_replace('<HOTEL_NAME>', $hotelName, $MAILER_TEMPLATE);

	$MAIL_BODY = '';
	//ROOMTYPE
	$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 8px; color:#000; font-family:Verdana; font-size:14px;">Room Type: </td><td style="width: 50%; padding-top: 8px; color:#000; font-family:Verdana;font-size:14px;line-height: 20px; width:100%;
		display: flex;"><span style="width:10px; display:inline-block; width:15px;">: </span>' . stripslashes(htmlspecialchars_decode($roomtypeName)) . '</td></tr>';
	//CHECKIN
	$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">Check-In</td><td style="width: 50%; padding-top: 10px; color:#000;font-family:Verdana;font-size:14px;line-height: 20px; width:100%; isplay: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . FormatDate($dCheckin, 'B') . '</td></tr>';
	//CHECKOUT
	$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">Check-Out</td><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;line-height: 20px; width:100%; display: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . FormatDate($dCheckOut, 'B') . '</td></tr>';
	//NO OF ROOMS
	$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">No of Rooms</td><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px; line-height: 20px; width:100%; display: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . $iNumRooms . '</td></tr>';
	//NO OF GUESTS
	$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">No of Guests</td><td style="width: 50%; padding-top: 10px; color:#000s; font-family:Verdana;font-size:14px; line-height: 20px; width:100%; display: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . $iNumPax . '</td></tr>';

	if (!empty($ROOMBREAKUP_DET) && count($ROOMBREAKUP_DET)) {
		$i = '1';
		foreach ($ROOMBREAKUP_DET as $ROOM_NO => $ROOM_DET) {
			$ROOM_GUESTCOUNT = $ROOM_DET['iGuest'];
			$ROOM_BILLINGINSTRUCTIONS = $ROOM_DET['vBillingInstructions'];

			$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 27px; padding-bottom: 5px; color:#000; font-family:Verdana;font-size:14px; border-bottom:1px dotted #929292; line-height: 20px;">ROOM No: ' . $ROOM_NO . '</td><td style="width: 50%; padding-top: 27px;  padding-bottom: 5px;  color:#000; font-family:Verdana;font-size:14px; line-height: 20px; width:100%; border-bottom:1px dotted #929292;">Occupancy: ' . $OCCUPANCY_ARR[$OCCUPANCY_ARR3[$ROOM_GUESTCOUNT]] . '</td></tr>';

			$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; font">Billing Instructions</td><td style="width: 50%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; line-height: 20px; width:100%; display: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . $ROOM_BILLINGINSTRUCTIONS . '</td></tr>';

			if (isset($GUESTLIST_DET[$ROOM_NO]) && !empty($GUESTLIST_DET[$ROOM_NO]) && count($GUESTLIST_DET[$ROOM_NO])) {
				$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; font">Guest Names:</td><td style="width: 50%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; line-height: 20px; width:100%; display: flex;"></td></tr>';

				$j = '1';
				foreach ($GUESTLIST_DET[$ROOM_NO] as $gKEY => $gVALUE) {
					$MAIL_BODY .= '<tr><td colspan="2" style="width: 100%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; font">' . $j . '. ' . $gVALUE . '</td></tr>';

					$j++;
				}
			}
		}
	}

	$MAILER_CONTENT = str_replace('<MAIL_BODY>', $MAIL_BODY, $MAILER_CONTENT);

	$CLICK_HERE = 'Please <a href="https://concierge.deltin.com/hotel/action.php?code=' . $code . '">click here</a> to enter the Confirmation No.';
	$MAILER_CONTENT = str_replace('<CLICK_HERE>', $CLICK_HERE, $MAILER_CONTENT);

	if (isset($USER_LEVEL_ARR[$userLevel])) $userLevel = $USER_LEVEL_ARR[$userLevel] . ',<br>';
	else $userLevel = '';

	$userContact = '';
	if (!empty($userEmail)) $userContact .= 'Email: ' . $userEmail . ' &nbsp;|&nbsp;';
	if (!empty($userEmail)) $userContact .= 'Call: ' . $userPhone . ' &nbsp;|&nbsp;';
	if (!empty($userContact)) $userContact = substr($userContact, 0, '-14');

	$USER_DETAILS = '<span style="font-weight:600;">' . stripslashes(htmlspecialchars_decode($userName)) . '</span>,<br><span style="font-size:13px;">' . $userLevel . $userContact . '</span>';
	$MAILER_CONTENT = str_replace('<USER_DETAILS>', $USER_DETAILS, $MAILER_CONTENT);

	$SUBJECT = 'Confirmation of ' . $roomtypeName . ', IN: ' . FormatDate($dCheckin, 'B') . ', Out: ' . FormatDate($dCheckOut, 'B') . ', Rooms: ' . $iNumRooms;

	require("_send_mail.php");
	Send_Zohomail("Deltin Concierge", "Deltin Concierge", $vReservationsEmail, "concierge@deltin.com", $vReservationsEmail_CC, $vReservationsEmail_BCC, $SUBJECT, $MAILER_CONTENT, "Thank you for your Booking Request", "Thank you for your Booking Request", "", ""); //$vReservationsEmail,$vReservationsEmail_CC,$vReservationsEmail_BCC
	//send_kenscio1($vReservationsEmail,$SUBJECT,$MAILER_CONTENT);
	//SendMail($OFFICIAL_EMAILID, $OFFICIAL_NAME, $vReservationsEmail, '', '', $userEmail, $SUBJECT, $MAILER_CONTENT);
	//send_mailgun($SUBJECT, $vReservationsEmail, $MAILER_CONTENT, '', $vReservationsEmail_CC, $vReservationsEmail_BCC, $userEmail, 'Deltin Concierge');

	return $str;
}

function GetRMAttentionList($rm_id, $show = 'ALL', $xtras = array())
{
	$arr = $arr2 = array();

	$requestCond = $showHOTEL = $showROOMTYPE = '';
	if (!empty($xtras) && count($xtras)) {
		foreach ($xtras as $KEY => $VALUE) {
			if ($KEY == 'CHECKIN_DATE') $requestCond .= ' and dCheckin="' . $VALUE . '"';
			if ($KEY == 'CHECKOUT_DATE') $requestCond .= ' and dCheckOut="' . $VALUE . '"';
			if ($KEY == 'CLIENT_ID') $requestCond .= ' and iRMContactID="' . $VALUE . '"';
			if ($KEY == 'HOTEL_ID') $showHOTEL = $VALUE;
			if ($KEY == 'ROOMTYPE_ID') $showROOMTYPE = $VALUE;
		}
	}

	$reqSTR = GetIDString2('select iRequestID from concrequest where cStatus IN ("D","I","A") and iUserID_RM=' . $rm_id . $requestCond . " order by iRequestID DESC");
	if (empty($reqSTR) || $reqSTR == '-1') $reqSTR = 0;
	$REQUEST_ARR = array();

	$cond = '';
	if ($show != 'ALL')
		$cond = ' and he.cStatus IN ("' . $show . '")';

	$q = 'select r.iNumPax, r.iNumRooms, r.iRequestID, r.iRequestDatID, he.iHotelEnqID, he.iHotelID, he.iRoomTypeID, he.iRequestDatOptionID, he.iRequestDatID, he.iRequestID, he.dtEnq, he.cReqSeen, he.dtReqSeen, he.cAvailable, he.dtResponse, he.cResponseSeenByRM, he.dtResponseSeenByRM, he.cBookReq, he.dtBookReq, he.cBookReqSeen, he.dtBookReqSeen, he.cBooked, he.cStatus from concrequestdat as r left outer join concrequest_hotelenq as he on r.iRequestID=he.iRequestID and r.iRequestDatID=he.iRequestDatID where r.cStatus IN ("D","I","A") and he.cStatus!="HC" and r.iRequestID IN (' . $reqSTR . ')' . $cond . " order by r.iRequestID DESC";
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		while (list($iNumPax, $iNumRooms, $iRequestID, $iRequestDatID, $iHotelEnqID, $iHotelID, $iRoomTypeID, $iRequestDatOptionID, $iRequestDatID2, $iRequestID2, $dtEnq, $cReqSeen, $dtReqSeen, $cAvailable, $dtResponse, $cResponseSeenByRM, $dtResponseSeenByRM, $cBookReq, $dtBookReq, $cBookReqSeen, $dtBookReqSeen, $cBooked, $cStatus) = sql_fetch_row($r)) {
			if (!isset($arr2['REQUEST'])) $arr2['REQUEST'] = array();

			if (!isset($REQUEST_ARR[$iRequestID])) {
				$_q = 'select dtRequest, vClient, dCheckin, dCheckOut from concrequest where iRequestID=' . $iRequestID;
				$_r = sql_query($_q, '');
				list($dtRequest, $vClient, $dCheckin, $dCheckOut) = sql_fetch_row($_r);

				$REQUEST_ARR[$iRequestID] = array('dtRequest' => $dtRequest, 'vClient' => stripslashes(htmlspecialchars_decode($vClient)), 'dCheckin' => FormatDate($dCheckin, 'B'), 'dCheckOut' => FormatDate($dCheckOut, 'B'));
			}

			$vClient = $REQUEST_ARR[$iRequestID]['vClient'];
			$dCheckin = $REQUEST_ARR[$iRequestID]['dCheckin'];
			$dCheckOut = $REQUEST_ARR[$iRequestID]['dCheckOut'];
			$dtRequest = $REQUEST_ARR[$iRequestID]['dtRequest'];

			$MSG = '';
			if (!empty($iHotelEnqID)) {
				if ($cStatus == 'RS') {
					if ($cReqSeen == 'N') $DATE = $dtEnq;
					else $DATE = $dtReqSeen;

					$MSG = 'Vacancy Revert Overdue';
				} elseif ($cStatus == 'AV') {
					if ($cResponseSeenByRM == 'Y') $DATE = $dtResponseSeenByRM;
					else $DATE = $dtResponse;
					$MSG = 'Available. Awaiting your confirmation';
				} elseif ($cStatus == 'NAV') {
					if ($cResponseSeenByRM == 'Y') $DATE = $dtResponseSeenByRM;
					else $DATE = $dtResponse;
					$MSG = 'Vacancy not available';
				} elseif ($cStatus == 'RMC') {
					if ($cBookReqSeen == 'Y') $DATE = $dtBookReqSeen;
					else $DATE = $dtBookReq;
					$MSG = 'Awaiting hotel confirmation';
				}
			} else {
				$DATE = $dtRequest;
				$MSG = 'No Action Taken, Yet';

				$_q2 = 'select iHotelID, iRoomTypeID from concrequestdat_options where iRequestID=' . $iRequestID . ' and iRequestDatID=' . $iRequestDatID;
				$_r2 = sql_query($_q2, '');
				list($iHotelID, $iRoomTypeID) = sql_fetch_row($_r2);
			}

			if (!empty($HOTEL_ID) && $HOTEL_ID != $iHotelID)
				continue;
			if (!empty($ROOMTYPE_ID) && $ROOMTYPE_ID != $iRoomTypeID)
				continue;

			$arr2['REQUEST'][] = array('ENQ_ID' => $iHotelEnqID, 'HOTEL_ID' => $iHotelID, 'ROOMTYPE_ID' => $iRoomTypeID, 'OPTION_ID' => $iRequestDatOptionID, 'DAT_ID' => $iRequestDatID, 'REQ_ID' => $iRequestID, 'ENQ_DATE' => $dtEnq, 'CLIENT' => $vClient, 'CHECKIN' => $dCheckin, 'CHECKOUT' => $dCheckOut, 'DATE' => $DATE, 'MSG' => $MSG);
		}
	}

	if (!empty($arr2) && count($arr2)) {
		foreach ($arr2 as $KEY => $VALUE) {
			foreach ($VALUE as $KEY2 => $VALUE2) {
				if ($KEY == 'REQUEST')
					$arr[] = array('TYPE' => $KEY, 'ID' => $VALUE2['REQ_ID'], 'DAT_ID' => $VALUE2['DAT_ID'], 'HOTEL_ID' => $iHotelID, 'ROOMTYPE_ID' => $iRoomTypeID, 'CLIENT' => $VALUE2['CLIENT'], 'CHECKIN' => $VALUE2['CHECKIN'], 'CHECKOUT' => $VALUE2['CHECKOUT'], 'DATE' => $VALUE2['DATE'], 'MSG' => $VALUE2['MSG']);
			}
		}
	}

	return $arr;
}

function GetHotelAttentionList($hotel_id)
{
	$arr = $arr2 = array();

	//REQUEST
	$q = 'select r.iNumPax, r.iNumRooms, r.iRequestID, he.iHotelEnqID, he.iHotelID, he.iRoomTypeID, he.iRequestDatOptionID, he.iRequestDatID, he.iRequestID, he.dtEnq, he.cReqSeen, he.dtReqSeen, he.cAvailable, he.dtResponse, he.cResponseSeenByRM, he.dtResponseSeenByRM, he.cBookReq, he.dtBookReq, he.cBookReqSeen, he.dtBookReqSeen, he.cBooked, he.cStatus from concrequestdat as r join concrequest_hotelenq as he on r.iRequestID=he.iRequestID and r.iRequestDatID=he.iRequestDatID where r.cStatus IN ("D","I","A") and he.cStatus="RMC" and he.iHotelID=' . $hotel_id;
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		while (list($iNumPax, $iNumRooms, $iRequestID, $iHotelEnqID, $iHotelID, $iRoomTypeID, $iRequestDatOptionID, $iRequestDatID, $iRequestID2, $dtEnq, $cReqSeen, $dtReqSeen, $cAvailable, $dtResponse, $cResponseSeenByRM, $dtResponseSeenByRM, $cBookReq, $dtBookReq, $cBookReqSeen, $dtBookReqSeen, $cBooked, $cStatus) = sql_fetch_row($r)) {
			if (!isset($arr2['REQUEST'])) $arr2['REQUEST'] = array();

			if (!isset($REQUEST_ARR[$iRequestID])) {
				$_q = 'select dtRequest, vClient, dCheckin, dCheckOut from concrequest where iRequestID=' . $iRequestID;
				$_r = sql_query($_q, '');
				list($dtRequest, $vClient, $dCheckin, $dCheckOut) = sql_fetch_row($_r);

				$REQUEST_ARR[$iRequestID] = array('dtRequest' => $dtRequest, 'vClient' => stripslashes(htmlspecialchars_decode($vClient)), 'dCheckin' => FormatDate($dCheckin, 'B'), 'dCheckOut' => FormatDate($dCheckOut, 'B'));
			}

			$vClient = $REQUEST_ARR[$iRequestID]['vClient'];
			$dCheckin = $REQUEST_ARR[$iRequestID]['dCheckin'];
			$dCheckOut = $REQUEST_ARR[$iRequestID]['dCheckOut'];
			$dtRequest = $REQUEST_ARR[$iRequestID]['dtRequest'];

			if ($cBookReqSeen == 'Y') $DATE = $dtBookReqSeen;
			else $DATE = $dtBookReq;
			$MSG = 'Awaiting Confirmation';

			$arr2['REQUEST'][] = array('ENQ_ID' => $iHotelEnqID, 'HOTEL_ID' => $iHotelID, 'ROOMTYPE_ID' => $iRoomTypeID, 'OPTION_ID' => $iRequestDatOptionID, 'DAT_ID' => $iRequestDatID, 'REQ_ID' => $iRequestID, 'ENQ_DATE' => $dtEnq, 'CLIENT' => $vClient, 'CHECKIN' => $dCheckin, 'CHECKOUT' => $dCheckOut, 'DATE' => $DATE, 'MSG' => $MSG);
		}
	}

	//INVOICE
	$q = 'select i.iInvoiceID, i.vInvoiceNo, i.iBookingID from concbooking_invoice as i join concbooking as b on i.iBookingID=b.iBookingID where i.cStatus="I" and b.cStatus!="X" and b.iHotelID=' . $hotel_id;
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		while (list($iInvoiceID, $vInvoiceNo, $iBookingID) = sql_fetch_row($r)) {
			if (!isset($arr2['INVOICE'])) $arr2['INVOICE'] = array();

			$NOTE = $DATE = '';
			$q2 = 'select vNote, dtDate from concbooking_invoice_stage where iInvoiceID=' . $iInvoiceID . ' and cStatus="I" order by iInvoiceStageID desc limit 1';
			$r2 = sql_query($q2, '');
			if (sql_num_rows($r2))
				list($NOTE, $DATE) = sql_fetch_row($r2);

			$NOTE = stripslashes(htmlspecialchars_decode($NOTE));
			$MSG = 'Invoice Rejected.';
			if (!empty($NOTE))
				$MSG .= ' Reason: ' . $NOTE;

			$arr2['INVOICE'][] = array('BOOKING_ID' => $iBookingID, 'INVOICE_NO' => $vInvoiceNo, 'DATE' => $DATE, 'MSG' => $MSG);
		}
	}

	if (!empty($arr2) && count($arr2)) {
		foreach ($arr2 as $KEY => $VALUE) {
			foreach ($VALUE as $KEY2 => $VALUE2) {
				if ($KEY == 'REQUEST')
					$arr[] = array('TYPE' => $KEY, 'ID' => $VALUE2['ENQ_ID'], 'DAT_ID' => $VALUE2['DAT_ID'], 'CLIENT' => $VALUE2['CLIENT'], 'CHECKIN' => $VALUE2['CHECKIN'], 'CHECKOUT' => $VALUE2['CHECKOUT'], 'DATE' => $VALUE2['DATE'], 'MSG' => $VALUE2['MSG']);
				if ($KEY == 'INVOICE')
					$arr[] = array('TYPE' => $KEY, 'ID' => $VALUE2['BOOKING_ID'], 'DAT_ID' => $VALUE2['INVOICE_NO'], 'CLIENT' => '', 'CHECKIN' => '', 'CHECKOUT' => '', 'DATE' => $VALUE2['DATE'], 'MSG' => $VALUE2['MSG']);
			}
		}
	}

	return $arr;
}

function send_mailgun($subject, $email, $contents, $attachment, $cc = '', $bcc = '', $replyto = '', $site_title = '')
{
	// echo "$subject, $email, $contents, $attachment, $cc='', $site_title=''<br>";
	// return false;

	$config = array();
	$config['api_key'] = "key-f2525677d1ec1827833028d04fc6b073";
	$config['api_url'] = "https://api.mailgun.net/v3/mg.deltin.com/messages";

	$message = array();
	$message['from'] = $site_title . " <postmaster@mg.deltin.com>";
	$message['to'] = $email;
	if (!empty($cc))
		$message['cc'] = $cc;
	if (!empty($bcc))
		$message['bcc'] = $bcc;
	//$message['bcc'] = 'vernon@teaminertia.com'; // 'joey@teaminertia.com, vernon@teaminertia.com';
	$message['h:Reply-To'] = $replyto;
	$message['subject'] = $subject;
	$message['html'] = $contents;
	$message['o:tracking-opens'] = true;
	if (!empty($attachment))
		$message['attachment'] = '@' . $attachment;
	//$message['o:campaign'] = 'g3rw5';

	//	$ua = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.A.B.C Safari/525.13';

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $config['api_url']);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	// curl_setopt($ch, CURLOPT_USERPWD, "api:{$config['api_key']}");
	curl_setopt($ch, CURLOPT_USERPWD, "api:{$config['api_key']}");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	//	curl_setopt($ch, CURLOPT_USERAGENT, $ua);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
	$result = curl_exec($ch);
	curl_close($ch);

	return $result;
}

function UpdateAccomodationRoomRates($req_id)
{
	$q = 'select dCheckin, dCheckOut from concrequest where iRequestID=' . $req_id;
	$r = sql_query($q, '');
	list($dCheckin, $dCheckOut) = sql_fetch_row($r);

	$q2 = 'select iRequestDatID, iNumPax, iNumRooms from concrequestdat where cStatus NOT IN ("X","C","B") and iRequestID=' . $req_id;
	$r2 = sql_query($q2, '');
	if (sql_num_rows($r2)) {
		while (list($iRequestDatID, $iNumPax, $iNumRooms) = sql_fetch_row($r2)) {
			UpdateHotelRoomCost2($req_id, $iRequestDatID, $iNumPax, $iNumRooms);
		}
	}
}

function UpdateHotelRoomCost2($request_id, $requestdat_id, $guests, $rooms)
{
	$_rq = 'select dCheckin, dCheckOut from concrequest where iRequestID=' . $request_id;
	$_rr = sql_query($_rq, '');
	list($txtchkin, $txtchkout) = sql_fetch_row($_rr);

	$q = 'select iRequestDatOptionID, iHotelID, iRoomTypeID from concrequestdat_options where iRequestID=' . $request_id . ' and iRequestDatID=' . $requestdat_id . ' order by iRequestDatOptionID';
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		while (list($optiondat_id, $h_id, $rt_id) = sql_fetch_row($r)) {
			$tariffExists = GetXFromYID('select count(*) from concrequestdat_options_tariff where iRequestDatOptionID=' . $optiondat_id . ' and iHotelID=' . $h_id . ' and iRoomTypeID=' . $rt_id . ' and cStatus="A"');

			$ROOMBREAKUP_DET = array();
			$_aq2 = 'select iRoomNo, iGuest from concrequestdat_rooms where iRequestID=' . $request_id . ' and iRequestDatID=' . $requestdat_id . ' and cStatus="A" order by iRoomNo';
			$_ar2 = sql_query($_aq2, '');
			if (sql_num_rows($_ar2)) {
				while (list($iRoomNo, $iGuest) = sql_fetch_row($_ar2))
					$ROOMBREAKUP_DET[$iRoomNo] = $iGuest;
			}

			if (!empty($tariffExists) && $tariffExists != '-1')
				sql_query('update concrequestdat_options_tariff set cStatus="X" where iRequestDatOptionID=' . $optiondat_id . ' and iHotelID=' . $h_id . ' and iRoomTypeID=' . $rt_id . ' and cStatus="A"');

			GetHotelRoomCost($h_id, $rt_id, $optiondat_id, $guests, $rooms, $txtchkin, $txtchkout, $ROOMBREAKUP_DET);
		}
	}
}

function MaskDigits($str)
{
	$len = strlen($str);

	return substr($str, 0, 1) . str_repeat('*', $len - 4) . substr($str, $len - 3, 3);
}

function GetBookingPlayDetaisl($b_id)
{
	$str = '';
	global $CASINO_PLAY_COLOR_ARR2, $CASINO_PLAY_STATUS_ARR;

	$PROPERTY_ARR2 = GetXArrFromYID('select iPropertyID, vName from property', '3');
	$BOOKING_PLAY_ARR = array();
	$_bpq = 'select iCGPDID, dDate, iPropertyID, iGuestID, vGuest, cStatus from concbooking_guest_playdetails where cStatus!="X" and iBookingID=' . $b_id . ' order by dDate, iPropertyID';
	$_bpr = sql_query($_bpq, '');
	if (sql_num_rows($_bpr)) {
		while (list($iCGPDID, $dDate, $iPropertyID, $iGuestID, $vGuest, $cStatus) = sql_fetch_row($_bpr)) {
			if (!isset($BOOKING_PLAY_ARR[$dDate][$iPropertyID])) $BOOKING_PLAY_ARR[$dDate][$iPropertyID] = array();
			array_push($BOOKING_PLAY_ARR[$dDate][$iPropertyID], array('ID' => $iCGPDID, 'NAME' => stripslashes(htmlspecialchars_decode($vGuest)), 'STATUS' => $cStatus));
		}
	}

	if (!empty($BOOKING_PLAY_ARR) && count($BOOKING_PLAY_ARR)) {
		foreach ($BOOKING_PLAY_ARR as $playDATE => $playDET) {
			$str .= '<div id="PLAYDATE_' . $playDATE . '">';
			$str .= '<p class="mb-1"><strong><u>Date:</u></strong> ' . FormatDate($playDATE, 'B') . '</p>';

			foreach ($playDET as $pKEY => $pVALUE) {
				$str .= '<p class="mb-1"><strong><u>Property:</u></strong> ' . $PROPERTY_ARR2[$pKEY] . '</p>';

				$g = '1';
				foreach ($pVALUE as $pKEY2 => $pVALUE2) {

					$str .= '<p class="mb-3" id="PLAY_GUEST_' . $pVALUE2['ID'] . '">' . $g . '.&nbsp;' . $pVALUE2['NAME'] . '<span class="float-right ml-2 text-danger" style="cursor:pointer;" onClick="DeletePlayGuestDetails(\'' . $pVALUE2['ID'] . '\',\'' . $g . '\');"><i class="fa fa-trash"></i></span><span class="ml-2 float-right" style="cursor:pointer;" onClick="UpdatePlayGuestDetails(\'' . $pVALUE2['ID'] . '\',\'' . $g . '\');"><i class="fa fa-edit"></i></span><span class="ml-2 badge badge-' . $CASINO_PLAY_COLOR_ARR2[$pVALUE2['STATUS']] . ' float-right">' . $CASINO_PLAY_STATUS_ARR[$pVALUE2['STATUS']] . '</span></p>';
					$g++;
				}
			}

			$str .= '</div>';
			$str .= '<div class="pb-2 pt-0"><span class="badge badge-primary float-right" style="cursor:pointer;" onClick="AddPlayDetails(\'' . $playDATE . '\');" ><i class="fa fa-plus"></i> Add ' . FormatDate($playDATE, 'B') . ' Play Details</span></div>';
			$str .= '<div class="divider"></div>';
		}
	}

	return $str;
}

function GetBookingInvoiceDetails()
{
	$arr = array();
	global $USER_PROPERTY_STR;

	$cond = '';
	if (empty($USER_PROPERTY_STR))
		return $arr;
	else {
		$BOOKING_ID_ARR = GetXArrFromYID('select distinct(iBookingID) from concbooking_property where iPropertyID IN (' . $USER_PROPERTY_STR . ')');
		if (!empty($BOOKING_ID_ARR) && count($BOOKING_ID_ARR))
			$cond = ' and i.iBookingID IN (' . implode(',', $BOOKING_ID_ARR) . ')';
		else
			$cond = ' and i.iBookingID=0';
	}

	$q = 'select i.iInvoiceID, i.vInvoiceNo, i.iBookingID, i.cStatus from concbooking_invoice as i join concbooking as b on i.iBookingID=b.iBookingID where i.cStatus IN ("D","R") ' . $cond . ' and b.cStatus!="X"';
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		while (list($iInvoiceID, $vInvoiceNo, $iBookingID, $cStatus) = sql_fetch_row($r)) {
			$NOTE = $DATE = '';
			$q2 = 'select vNote, dtDate from concbooking_invoice_stage where iInvoiceID=' . $iInvoiceID . ' and cStatus="' . $cStatus . '" order by iInvoiceStageID desc limit 1';
			$r2 = sql_query($q2, '');
			if (sql_num_rows($r2))
				list($NOTE, $DATE) = sql_fetch_row($r2);

			$NOTE = stripslashes(htmlspecialchars_decode($NOTE));
			$MSG = ($cStatus == 'R') ? 'Invoice Rejected By Casino Manager.' : 'New Invoice Added.';
			if (!empty($NOTE))
				$MSG .= ($cStatus == 'R') ? ' Reason: ' . $NOTE : ' Note: ' . $NOTE;

			$arr[] = array('BOOKING_ID' => $iBookingID, 'INVOICE_NO' => $vInvoiceNo, 'DATE' => $DATE, 'MSG' => $MSG, 'STATUS' => $cStatus);
		}
	}

	return $arr;
}

function GetBlockBookingSummary($id)
{
	$str = '';

	$HOTEL_ARR = GetXArrFromYID('select iHotelID, vName from gen_hotel', '3');
	$_hq = 'select iHotelID, sum(iRoomNights), sum(iRoomNightsAllocated), sum(iRoomNightsConsumed) from concblock_dat where iBlockID=' . $id . ' group by iHotelID';
	$_hr = sql_query($_hq, '');
	if (sql_num_rows($_hr)) {
		while (list($iHotelID, $iRoomNights, $iRoomNightsAllocated, $iRoomNightsConsumed) = sql_fetch_row($_hr)) {
			$allocatedcolor = '';
			if ($iRoomNightsAllocated >= ($iRoomNights / 2)) $allocatedcolor = 'warning';
			elseif ($iRoomNightsAllocated < ($iRoomNights / 2)) $allocatedcolor = 'danger';
			elseif ($iRoomNightsAllocated = $iRoomNights) $allocatedcolor = 'success';

			$consumedcolor = '';
			if ($iRoomNightsConsumed >= ($iRoomNightsAllocated / 2)) $consumedcolor = 'warning';
			elseif ($iRoomNightsConsumed < ($iRoomNightsAllocated / 2)) $consumedcolor = 'danger';
			elseif ($iRoomNightsConsumed = $iRoomNightsAllocated) $consumedcolor = 'success';

			$DET = '';
			$DET .= 'Blocked: ' . $iRoomNights . ' | ';
			$DET .= 'Allocated: <span class="text-' . $allocatedcolor . '">' . $iRoomNightsAllocated . '</span> | ';
			$DET .= 'Consumed: <span class="text-' . $consumedcolor . '">' . $iRoomNightsConsumed . '</span> | ';
			$DET = substr($DET, 0, '-3');

			$str .= '<p><strong><u>' . $HOTEL_ARR[$iHotelID] . '</u></strong>: ' . $DET . '</p>';
		}
	}

	return $str;
}
function GetBlockBookingSummary2($id)
{
	$str = '';

	$HOTEL_ARR = GetXArrFromYID('select iHotelID, vName from gen_hotel', '3');
	$_hq = 'select iHotelID, sum(iRoomNights), sum(iRoomNightsAllocated), sum(iRoomNightsCosumed) from concblock_dat where iBlockID=' . $id . ' group by iHotelID';
	$_hr = sql_query($_hq, '');
	if (sql_num_rows($_hr)) {
		while (list($iHotelID, $iRoomNights, $iRoomNightsAllocated, $iRoomNightsCosumed) = sql_fetch_row($_hr)) {
			$NIGHTS = $iRoomNights;
			$ALLOCATED = $iRoomNightsAllocated;
			$CONSUMED = $iRoomNightsCosumed;

			$bg_allocatedcolor = '';
			if ($ALLOCATED >= ($NIGHTS / 2)) $bg_allocatedcolor = ' bg-warning';
			elseif ($ALLOCATED < ($NIGHTS / 2)) $bg_allocatedcolor = ' bg-danger';
			elseif ($ALLOCATED = $NIGHTS) $bg_allocatedcolor = ' bg-success';

			$bg_consumedcolor = '';
			if ($CONSUMED >= ($ALLOCATED / 2)) $bg_consumedcolor = ' bg-warning';
			elseif ($CONSUMED < ($ALLOCATED / 2)) $bg_consumedcolor = ' bg-danger';
			elseif ($CONSUMED = $ALLOCATED) $bg_consumedcolor = ' bg-success';

			$str .= '<div class="text-left">' . $HOTEL_ARR[$iHotelID] . '</div>';
			$str .= '<div class="mb-3 progress text-center">';
			$str .= '<div class="progress-bar" role="progressbar" aria-valuenow="33.33" aria-valuemin="0" aria-valuemax="100" style="width: 33.33%;">Blocked: ' . $NIGHTS . '</div>';
			$str .= '<div class="progress-bar' . $bg_allocatedcolor . '" role="progressbar" aria-valuenow="33.33" aria-valuemin="0" aria-valuemax="100" style="width: ' . $ALLOCATED . '%;">Allocated: ' . $ALLOCATED . '</div>';
			$str .= '<div class="progress-bar' . $bg_consumedcolor . '" role="progressbar" aria-valuenow="' . $CONSUMED . '" aria-valuemin="0" aria-valuemax="100" style="width: ' . $CONSUMED . '%;">Consumed: ' . $CONSUMED . '</div>';
			$str .= '</div>';
		}
	}

	return $str;
}

function GetBlockBookingDetails()
{
	$arr = array();
	global $USER_PROPERTY_STR;

	$cond = '';
	if (empty($USER_PROPERTY_STR))
		return $arr;
	else
		$cond = ' and iPropertyID IN (' . $USER_PROPERTY_STR . ')';

	$PROPERTY_ARR = GetXArrFromYID('select iPropertyID, vShortCode from property', '3');
	$q = 'select iBlockID, iPropertyID, vName, dCheckIn, dCheckOut, iRoomNights, iRoomNightsAllocated, iRoomNightsConsumed from concblock where cStatus="A" and dCheckIn>="' . TODAY . '" order by dCheckIn';
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		while (list($iBlockID, $iPropertyID, $vName, $dCheckIn, $dCheckOut, $iRoomNights, $iRoomNightsAllocated, $iRoomNightsConsumed) = sql_fetch_row($r)) {
			$arr[] = array('ID' => $iBlockID, 'PROPERTY' => $PROPERTY_ARR[$iPropertyID], 'NAME' => stripslashes(htmlspecialchars_decode($vName)), 'CHECKIN' => FormatDate($dCheckIn, 'B'), 'CHECKOUT' => FormatDate($dCheckOut, 'B'), 'ROOM_NIGHTS' => $iRoomNights, 'NIGHTS_ALLOCATED' => $iRoomNightsAllocated, 'NIGHTS_CONSUMED' => $iRoomNightsConsumed);
		}
	}

	return $arr;
}

function send_kenscio1($txtemail, $txtsubject, $txtcontent)
{
	$arr = array("sysid" => 'cepapi0061', "emailid" => $txtemail, "msgid" => '252132257', "subject" => $txtsubject, "body" => $txtcontent);

	$json = json_encode($arr);
	$curl = curl_init();

	curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://cepapi.kenscio.com/v2/sendmail',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS => $json,
		CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json',
			'Authorization: Basic ZGVsdGluMDAxOk00T052ZVQxSWZpYmdwdkg='
		),
	));

	$response = curl_exec($curl);

	curl_close($curl);
	return $response;
}

function SendBookindDetailstoHotelMail($booking_id, $userID)
{
	$str = '';
	global $OFFICIAL_EMAILID, $OFFICIAL_NAME, $OCCUPANCY_ARR, $OCCUPANCY_ARR3, $USER_LEVEL_ARR;

	$q = 'select iHotelID, vBookingRefNum, vClient from concbooking where iBookingID=' . $booking_id;
	$r = sql_query($q, '');
	list($hotelID, $vBookingRefNum, $vClient) = sql_fetch_row($r);

	$q2 = 'select vName, vReservationsEmail, vReservationsEmail_CC, vReservationsEmail_BCC from gen_hotel where iHotelID=' . $hotelID;
	$r2 = sql_query($q2, '');
	list($hotelName, $vReservationsEmail, $vReservationsEmail_CC, $vReservationsEmail_BCC) = sql_fetch_row($r2);

	$q3 = 'select vName, vEmail, vPhone, iLevel from users where iUserID=' . $userID;
	$r3 = sql_query($q3, '');
	list($userName, $userEmail, $userPhone, $userLevel) = sql_fetch_row($r3);

	$BOOKINGDAT_ARR = array();
	$q4 = 'select iBookingDatID, iRoomTypeID, iGuests, iRooms, vBillingInstructions, dCheckin, dCheckOut from concbooking_dat where iBookingID=' . $booking_id;
	$r4 = sql_query($q4, '');
	if (sql_num_rows($r4)) {
		while (list($iBookingDatID, $iRoomTypeID, $iGuests, $iRooms, $vBillingInstructions, $dCheckin, $dCheckOut) = sql_fetch_row($r4)) {
			$BOOKINGDAT_ARR[$iBookingDatID] = array('iRoomTypeID' => $iRoomTypeID, 'iGuests' => $iGuests, 'iRooms' => $iRooms, 'vBillingInstructions' => $vBillingInstructions, 'dCheckin' => $dCheckin, 'dCheckOut' => $dCheckOut);
		}
	}

	$ROOMBREAKUP_DET = array();
	$_aq2 = 'select iBookingDatID, iRoomNo, iGuest, vBillingInstructions from concbooking_dat_rooms where iBookingID=' . $booking_id . ' and cStatus="A" order by iRoomNo';
	$_ar2 = sql_query($_aq2, '');
	if (sql_num_rows($_ar2)) {
		while (list($iBookingDatID, $iRoomNo, $iGuest, $vBillingInstructions) = sql_fetch_row($_ar2)) {
			$ROOMBREAKUP_DET[$iBookingDatID][$iRoomNo] = array('iGuest' => $iGuest, 'vBillingInstructions' => stripslashes(htmlspecialchars_decode($vBillingInstructions)));
		}
	}

	$GUESTLIST_DET = array();
	$_gq2 = 'select iBookingDatID, iRoomNo, vName, vContactNum from concbooking_guests where cStatus="A" and iBookingID=' . $booking_id . ' order by iRoomNo, iGuestID';
	$_gr2 = sql_query($_gq2, '');
	if (sql_num_rows($_gr2)) {

		while (list($iBookingDatID, $guestRoomNo, $guestName, $guestContactNo) = sql_fetch_row($_gr2)) {
			if (!isset($GUESTLIST_DET[$iBookingDatID][$guestRoomNo])) $GUESTLIST_DET[$iBookingDatID][$guestRoomNo] = array();
			array_push($GUESTLIST_DET[$iBookingDatID][$guestRoomNo], htmlspecialchars_decode($guestName));
		}
	}

	$MAILER_TEMPLATE = file_get_contents(SITE_ADDRESS . 'mailers/booking.html');

	$MAILER_CONTENT = '';
	$MAILER_CONTENT = str_replace('<HOTEL_NAME>', $hotelName, $MAILER_TEMPLATE);


	//CONFIRMATION NO
	$MAIL_BODY = '';
	$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 8px; color:#000; font-family:Verdana; font-size:14px;">Confirmation No : </td><td style="width: 50%; padding-top: 8px; color:#000; font-family:Verdana;font-size:14px;line-height: 20px; width:100%;
		display: flex;"><span style="width:10px; display:inline-block; width:15px;">: </span>' . $vBookingRefNum . '</td></tr>';
	$MAIL_BODY .= '<tr><td colspan="2" style="width: 100%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; font">&nbsp;</td></tr>';

	if (!empty($BOOKINGDAT_ARR) && count($BOOKINGDAT_ARR)) {
		foreach ($BOOKINGDAT_ARR as $KEY => $VALUE) {
			$roomtypeName = GetXFromYID('select vName from gen_roomtype where iRoomTypeID=' . $VALUE['iRoomTypeID']);

			//ROOMTYPE
			$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 8px; color:#000; font-family:Verdana; font-size:14px;">Room Type: </td><td style="width: 50%; padding-top: 8px; color:#000; font-family:Verdana;font-size:14px;line-height: 20px; width:100%;
				display: flex;"><span style="width:10px; display:inline-block; width:15px;">: </span>' . stripslashes(htmlspecialchars_decode($roomtypeName)) . '</td></tr>';
			//CHECKIN
			$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">Check-In</td><td style="width: 50%; padding-top: 10px; color:#000;font-family:Verdana;font-size:14px;line-height: 20px; width:100%; isplay: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . FormatDate($VALUE['dCheckin'], 'B') . '</td></tr>';
			//CHECKOUT
			$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">Check-Out</td><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;line-height: 20px; width:100%; display: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . FormatDate($VALUE['dCheckOut'], 'B') . '</td></tr>';
			//NO OF ROOMS
			$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">No of Rooms</td><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px; line-height: 20px; width:100%; display: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . $VALUE['iRooms'] . '</td></tr>';
			//NO OF GUESTS
			$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 10px; color:#000; font-family:Verdana;font-size:14px;">No of Guests</td><td style="width: 50%; padding-top: 10px; color:#000s; font-family:Verdana;font-size:14px; line-height: 20px; width:100%; display: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . $VALUE['iGuests'] . '</td></tr>';

			if (isset($ROOMBREAKUP_DET[$KEY]) && !empty($ROOMBREAKUP_DET[$KEY]) && count($ROOMBREAKUP_DET[$KEY])) {
				$i = '1';
				foreach ($ROOMBREAKUP_DET[$KEY] as $ROOM_NO => $ROOM_DET) {
					$ROOM_GUESTCOUNT = $ROOM_DET['iGuest'];
					$ROOM_BILLINGINSTRUCTIONS = $ROOM_DET['vBillingInstructions'];

					$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 27px; padding-bottom: 5px; color:#000; font-family:Verdana;font-size:14px; border-bottom:1px dotted #929292; line-height: 20px;">ROOM No: ' . $ROOM_NO . '</td><td style="width: 50%; padding-top: 27px;  padding-bottom: 5px;  color:#000; font-family:Verdana;font-size:14px; line-height: 20px; width:100%; border-bottom:1px dotted #929292;">Occupancy: ' . $OCCUPANCY_ARR[$OCCUPANCY_ARR3[$ROOM_GUESTCOUNT]] . '</td></tr>';

					$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; font">Billing Instructions</td><td style="width: 50%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; line-height: 20px; width:100%; display: flex;"><span style="width:10px; display:inline-block; width:15px;">:</span>' . $ROOM_BILLINGINSTRUCTIONS . '</td></tr>';

					if (isset($GUESTLIST_DET[$KEY][$ROOM_NO]) && !empty($GUESTLIST_DET[$KEY][$ROOM_NO]) && count($GUESTLIST_DET[$KEY][$ROOM_NO])) {
						$MAIL_BODY .= '<tr><td style="width: 50%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; font">Guest Names:</td><td style="width: 50%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; line-height: 20px; width:100%; display: flex;"></td></tr>';

						$j = '1';
						foreach ($GUESTLIST_DET[$KEY][$ROOM_NO] as $gKEY => $gVALUE) {
							$MAIL_BODY .= '<tr><td colspan="2" style="width: 100%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; font">' . $j . '. ' . $gVALUE . '</td></tr>';

							$j++;
						}
					}
				}
			}

			$MAIL_BODY .= '<tr><td colspan="2" style="width: 100%; padding-top: 7px; color:#000; font-family:Verdana;font-size:12px; font">&nbsp;</td></tr>';
		}
	}


	$MAILER_CONTENT = str_replace('<MAIL_BODY>', $MAIL_BODY, $MAILER_CONTENT);

	$CLICK_HERE = '';
	$MAILER_CONTENT = str_replace('<CLICK_HERE>', $CLICK_HERE, $MAILER_CONTENT);

	if (isset($USER_LEVEL_ARR[$userLevel])) $userLevel = $USER_LEVEL_ARR[$userLevel] . ',<br>';
	else $userLevel = '';

	$userContact = '';
	if (!empty($userEmail)) $userContact .= 'Email: ' . $userEmail . ' &nbsp;|&nbsp;';
	if (!empty($userEmail)) $userContact .= 'Call: ' . $userPhone . ' &nbsp;|&nbsp;';
	if (!empty($userContact)) $userContact = substr($userContact, 0, '-14');

	$USER_DETAILS = '<span style="font-weight:600;">' . stripslashes(htmlspecialchars_decode($userName)) . '</span>,<br><span style="font-size:13px;">' . $userLevel . $userContact . '</span>';
	$MAILER_CONTENT = str_replace('<USER_DETAILS>', $USER_DETAILS, $MAILER_CONTENT);

	$SUBJECT = 'Update Booking No:' . $vBookingRefNum;

	//send_kenscio1($vReservationsEmail,$SUBJECT,$MAILER_CONTENT);
	require("_send_mail.php");
	Send_Zohomail("Deltin Concierge", "Deltin Concierge", $vReservationsEmail, "concierge@deltin.com", $vReservationsEmail_CC, $vReservationsEmail_BCC, $SUBJECT, $MAILER_CONTENT, "Thank you for your Booking Request Update", "Thank you for your Booking Request Update", "", "");	// $vReservationsEmail, $vReservationsEmail_CC, $vReservationsEmail_BCC
	//SendMail($OFFICIAL_EMAILID, $OFFICIAL_NAME, $vReservationsEmail, '', '', $userEmail, $SUBJECT, $MAILER_CONTENT);
	//send_mailgun($SUBJECT, $vReservationsEmail, $MAILER_CONTENT, '', $vReservationsEmail_CC, $vReservationsEmail_BCC, $userEmail, 'Deltin Concierge');

	return $str;
}

function GetBookingActivityLog($booking_id)
{
	$arr = $ROOMTYPE_ARR = $REQUEST_ID_ARR = $BOOKING_ID_ARR = array();

	global $BOOK_TYPE_ARR2, $BOOK_TYPE_DESC_ARR;
	$_rq = 'select iLRID, dtDate, vDesc, cMode, vIP, iLocID, iUserID, vUserName, iRequestID, iRefID, cRefType, vHotelName, vRoomTypeName, dCheckIn, dCheckOut from log_requests where iRequestID=' . $booking_id . ' and cRefType IN (' . implode(',', $BOOK_TYPE_ARR2) . ') order by dtDate desc'; //.$LIMIT;
	$_rr = sql_query($_rq, '');
	if (sql_num_rows($_rr)) {
		while (list($x_id, $x_dt, $x_desc, $x_mode, $x_ip, $l_id, $u_id, $u_name, $y_id, $y_refid, $y_reftype, $vHotelName, $vRoomTypeName, $dCheckIn, $dCheckOut) = sql_fetch_row($_rr)) {
			$y_name_str = $BOOK_TYPE_DESC_ARR[$y_reftype];
			$u_str = 'U/' . $u_id . ' @ ' . $x_ip;

			$arr[$x_id . '~LR'] = array('DATE2' => $x_dt, 'DATE' => FormatDate($x_dt, 'J'), 'DATE3' => LastOnlineSeen($x_dt), 'DATE4' => FormatDate($x_dt, '27'), 'TYPE' => stripslashes(htmlspecialchars_decode($y_name_str)), 'DESC' => stripslashes(htmlspecialchars_decode($x_desc)), 'USER_STR' => $u_str, 'USERNAME' => $u_name, 'HOTEL' => stripslashes(htmlspecialchars_decode($vHotelName)), 'ROOMTYPE' => stripslashes(htmlspecialchars_decode($vRoomTypeName)), 'CHECKIN' => $dCheckIn, 'CHECKOUT' => $dCheckOut);
		}
	}

	if (!empty($arr)) $arr = MultiDimensionalArraySort($arr, 'DATE2', SORT_DESC);
	if (!empty($arr)) $arr = array_slice($arr, 0, 10);

	return $arr;
}

function GetRequestActivityLog($request_id)
{
	$arr = $ROOMTYPE_ARR = $REQUEST_ID_ARR = $BOOKING_ID_ARR = array();

	global $REQ_TYPE_ARR2, $REQ_TYPE_DESC_ARR;
	$_rq = 'select iLRID, dtDate, vDesc, cMode, vIP, iLocID, iUserID, vUserName, iRequestID, iRefID, cRefType, vHotelName, vRoomTypeName, dCheckIn, dCheckOut from log_requests where iRequestID=' . $request_id . ' and cRefType IN (' . implode(',', $REQ_TYPE_ARR2) . ') order by iLRID desc'; //.$LIMIT;
	$_rr = sql_query($_rq, '');
	if (sql_num_rows($_rr)) {
		while (list($x_id, $x_dt, $x_desc, $x_mode, $x_ip, $l_id, $u_id, $u_name, $y_id, $y_refid, $y_reftype, $vHotelName, $vRoomTypeName, $dCheckIn, $dCheckOut) = sql_fetch_row($_rr)) {
			$y_name_str = $REQ_TYPE_DESC_ARR[$y_reftype];
			$u_str = 'U/' . $u_id . ' @ ' . $x_ip;

			$arr[$x_id . '~LR'] = array('DATE2' => $x_dt, 'DATE' => FormatDate($x_dt, 'J'), 'DATE3' => LastOnlineSeen($x_dt), 'DATE4' => FormatDate($x_dt, '27'), 'TYPE' => stripslashes(htmlspecialchars_decode($y_name_str)), 'DESC' => stripslashes(htmlspecialchars_decode($x_desc)), 'USER_STR' => $u_str, 'USERNAME' => $u_name, 'HOTEL' => stripslashes(htmlspecialchars_decode($vHotelName)), 'ROOMTYPE' => stripslashes(htmlspecialchars_decode($vRoomTypeName)), 'CHECKIN' => $dCheckIn, 'CHECKOUT' => $dCheckOut);
		}
	}

	if (!empty($arr)) $arr = MultiDimensionalArraySort($arr, 'DATE2', SORT_DESC);
	if (!empty($arr)) $arr = array_slice($arr, 0, 10);

	return $arr;
}


/**
 * Object Storage + File Storage + Store File Functions Start
 **/

function get_mime_content_type($source)
{
	$resource = fopen($source, "r");
	$mime_type = !empty($resource) ? mime_content_type($resource) : null;
	fclose($resource);
	return $mime_type;
}

// function ObjectStorage()
// {
// 	require_once realpath(dirname(__FILE__) . "/libs/aws/aws-autoloader.php");

// 	$S3OBJ = new Aws\S3\S3Client(array(
// 		"endpoint" => AWS_S3_ENDPOINT,
// 		"region" => AWS_S3_REGION,
// 		"credentials" => array(
// 			"key"		=> AWS_S3_KEY,
// 			"secret"	=> AWS_S3_SECRET,
// 		),
// 		"version" => "latest",
// 		"use_path_style_endpoint" => true,
// 	));

// 	return $S3OBJ;
// }
function ObjectStorage()
{
    require_once realpath(dirname(__FILE__) . "/libs/aws/aws-autoloader.php");

    $S3OBJ = new Aws\S3\S3Client([
        "region" => AWS_S3_REGION, // usually "blr1"
        "version" => "latest",
        "endpoint" => "https://blr1.digitaloceanspaces.com", // ✅ REMOVE BUCKET from endpoint
        "credentials" => [
            "key"    => AWS_S3_KEY,
            "secret" => AWS_S3_SECRET,
        ],
        "bucket_endpoint" => false,              // ✅ Must be false
        "use_path_style_endpoint" => false,      // ✅ Must be false
    ]);

    return $S3OBJ;
}

function ObjectStorageUpload($upload_path, $fileName, $sourceFile)
{
	global $S3OBJ;
	$fileName = $upload_path . (!empty($fileName) ? $fileName : time());

	$result = $S3OBJ->putObject(array(
		'Bucket'		=>	AWS_S3_BUCKET,
		'ACL'			=>	AWS_S3_BUCKET_PUT,
		'Key'			=>	$fileName,
		'SourceFile'	=>	$sourceFile,
		'ContentType'	=>	get_mime_content_type($sourceFile),
		//'ContentDisposition' => 'inline; filename=filename.jpg',
	));

	$response = $S3OBJ->doesObjectExist(AWS_S3_BUCKET, $fileName);

	if (!empty($response) && $response == '1') return 1;
	else return 0;
}

function ObjectStorageDelete($upload_path, $fileName)
{
	global $S3OBJ;
	$fileName = $upload_path . (!empty($fileName) ? $fileName : ' ');

	$response2 = $S3OBJ->doesObjectExist(AWS_S3_BUCKET, $fileName);

	if (!empty($response2) && $response2 == '1') {
		$response3 = $S3OBJ->deleteObject(array(
			'Bucket'	=>	AWS_S3_BUCKET,
			'Key'		=>	$fileName
		));
	}

	$response = $S3OBJ->doesObjectExist(AWS_S3_BUCKET, $fileName);

	if (!empty($response) && $response == '1') return 1;
	else return 0;
}

function ObjectStorageCopy($upload_path, $fileName, $upload_path_src, $fileNameSrc)
{
	global $S3OBJ;
	$fileName = $upload_path . (!empty($fileName) ? $fileName : time());
	$fileNameSrc = AWS_S3_BUCKET . '/' . AWS_S3_BUCKET . '/' . $upload_path_src . (!empty($fileNameSrc) ? $fileNameSrc : ' ');

	$result = $S3OBJ->copyObject([
		'Bucket'		=> AWS_S3_BUCKET,
		'Key'			=> $fileName,
		'CopySource'	=> $fileNameSrc,
	]);

	$response = $S3OBJ->doesObjectExist(AWS_S3_BUCKET, $fileName);

	if (!empty($response) && $response == '1') return 1;
	else return 0;
}

// function ObjectStoragePath($upload_path, $fileName)
// {
// 	global $S3OBJ;
// 	$downloadName = $fileName;
// 	$fileName = $upload_path . (!empty($fileName) ? $fileName : ' ');

// 	$docPATH = $docSIZE = $docTYPE = '';

// 	if (!empty($fileName) && $fileName != '-NA-') {
// 		$response2 = $S3OBJ->doesObjectExist(AWS_S3_BUCKET, $fileName);
// 		if (!empty($response2) && $response2 == '1') {
// 			if (AWS_S3_BUCKET_GET == 'private') {
// 				$command = $S3OBJ->getCommand('GetObject', array(
// 					'Bucket' => AWS_S3_BUCKET,
// 					'Key' => $fileName,
// 					'ResponseContentDisposition' => "inline; filename={$downloadName}",
// 				));
// 				$response = $S3OBJ->createPresignedRequest($command, '+6 days'); // Create a signed URL from the command object that will last for 6 days from the current time
// 				$docPATH = (string) $response->getUri();
// 			} else // public
// 			{
// 				$docPATH = (string) $S3OBJ->getObjectUrl(AWS_S3_BUCKET, $fileName);
// 			}

// 			$response = $S3OBJ->headObject(array(
// 				'Bucket'	=>	AWS_S3_BUCKET,
// 				'Key'		=>	$fileName,
// 			));
// 			$docSIZE = intval($response->get('ContentLength'));

// 			$docTYPE = substr($downloadName, strrpos($downloadName, '.') + 1);
// 		}
// 	}

// 	return array($docPATH, $docSIZE, $docTYPE);
// }
function ObjectStoragePath($upload_path,$fileName)
{
        global $S3OBJ;
        $downloadName = $fileName;
        $fileName = $upload_path.(!empty($fileName)?$fileName:' ');

        $docPATH = $docSIZE = $docTYPE = '';

        if(!empty($fileName) && $fileName!='-NA-')
        {
                $response2 = $S3OBJ->doesObjectExist(AWS_S3_BUCKET, $fileName);
                if(!empty($response2) && $response2=='1')
                {
                        if (AWS_S3_BUCKET_GET == 'private')
                        {
                                $command = $S3OBJ->getCommand('GetObject', array(
                                        'Bucket' => AWS_S3_BUCKET,
                                        'Key' => $fileName,
                                        'ResponseContentDisposition' => "inline; filename={$downloadName}",
                                ));
                                $response = $S3OBJ->createPresignedRequest($command,'+6 days'); // Create a signed URL from the command object that will last for 6 days from the current time
                                $docPATH = (string) $response->getUri();
                        }
                        else // public
                        {
                                $docPATH = (string) $S3OBJ->getObjectUrl(AWS_S3_BUCKET, $fileName);
                        }

                        $response = $S3OBJ->headObject(array(
                                'Bucket'        =>        AWS_S3_BUCKET,
                                'Key'                =>        $fileName,
                        ));
                        $docSIZE = intval($response->get('ContentLength'));

                        $docTYPE = substr($downloadName, strrpos($downloadName, '.') + 1);
                }
        }

        return array($docPATH,$docSIZE,$docTYPE);
}
function ObjectStorageExist($upload_path, $fileName)
{
	global $S3OBJ;
	$fileName = $upload_path . (!empty($fileName) ? $fileName : ' ');

	$response = $S3OBJ->doesObjectExist(AWS_S3_BUCKET, $fileName);

	if (!empty($response) && $response == '1') return true;
	else return false;
}


function FileUpload($upload_path, $fileName, $sourceFile)
{
	$fileName = $upload_path . (!empty($fileName) ? $fileName : time());

	$dir = opendir($upload_path);
	$response = copy($sourceFile, $fileName);
	closedir($dir);
	chmod($fileName, 0644);

	if (!empty($response) && $response) return 1;
	else return 0;
}

function FileDelete($upload_path, $fileName)
{
	$fileName = $upload_path . (!empty($fileName) ? $fileName : time());

	$response = false;
	if (file_exists($fileName)) {
		$response = unlink($fileName);
	}

	if (!empty($response) && $response) return 1;
	else return 0;
}

function FileCopy($upload_path, $fileName, $upload_path_src, $fileNameSrc)
{
	$fileName = $upload_path . (!empty($fileName) ? $fileName : time());
	$fileNameSrc = $upload_path_src . (!empty($fileNameSrc) ? $fileNameSrc : ' ');

	$dir = opendir($upload_path);
	$response = copy($fileNameSrc, $fileName);
	closedir($dir);
	chmod($fileName, 0644);

	if (!empty($response) && $response) return 1;
	else return 0;
}

function FilePath($upload_path, $fileName)
{
	$docPATH = $docSIZE = $docTYPE = '';
	$fileName = (!empty($fileName) ? $fileName : ' ');

	$docPATH = str_replace(DOCROOT, SITE_ADDRESS, $upload_path) . $fileName;
	$docSIZE = filesize($upload_path . $fileName);
	$docTYPE = substr($fileName, strrpos($fileName, '.') + 1);
	return array($docPATH, $docSIZE, $docTYPE);
}

function FileExist($upload_path, $fileName)
{
	$fileName = $upload_path . (!empty($fileName) ? $fileName : time());
	return file_exists($fileName);
}


function StoreFileUpload($upload_type, $fileName, $sourceFile)
{
	$ret = 0;
	$upload_path = 'uploads/' . UPLOAD_TYPE[$upload_type];
	if (defined('USE_OBJECT_STORAGE') && USE_OBJECT_STORAGE == 'Y') {
		$ret = ObjectStorageUpload(AWS_S3_DIR . $upload_path, $fileName, $sourceFile);
	} else {
		$ret = FileUpload(DOCROOT . $upload_path, $fileName, $sourceFile);
	}
	return $ret;
}

function StoreFileUploadData($upload_type, $fileName, $sourceData)
{
	$ret = 0;
	$tmp_file = tmpfile();
	fwrite($tmp_file, $sourceData);
	$ret = StoreFileUpload($upload_type, $fileName, stream_get_meta_data($tmp_file)['uri']);
	fclose($tmp_file);
	return $ret;
}

function StoreFileUploadUrl($upload_type, $fileName, $sourceUrl)
{
	$ret = 0;
	if (!empty($sourceUrl)) {
		$ret = StoreFileUploadData($upload_type, $fileName, file_get_contents($sourceUrl));
	}
	return $ret;
}

function StoreFileUploadBase64($upload_type, $fileName, $sourceBase64)
{
	$ret = 0;
	if (!empty($sourceBase64)) {
		list($filemeta, $filedata) = explode(",", $sourceBase64);
		$ret = StoreFileUploadData($upload_type, $fileName, base64_decode($filedata));
	}
	return $ret;
}

function StoreFileDelete($upload_type, $fileName)
{
	$ret = 0;
	$upload_path = 'uploads/' . UPLOAD_TYPE[$upload_type];
	if (defined('USE_OBJECT_STORAGE') && USE_OBJECT_STORAGE == 'Y') {
		$ret = ObjectStorageDelete(AWS_S3_DIR . $upload_path, $fileName);
	} else {
		$ret = FileDelete(DOCROOT . $upload_path, $fileName);
	}
	return $ret;
}

function StoreFileCopy($upload_type, $fileName, $upload_type_src, $fileNameSrc)
{
	$ret = 0;
	$upload_path = 'uploads/' . UPLOAD_TYPE[$upload_type];
	$upload_path_src = 'uploads/' . UPLOAD_TYPE[$upload_type_src];
	if (defined('USE_OBJECT_STORAGE') && USE_OBJECT_STORAGE == 'Y') {
		$ret = ObjectStorageCopy(AWS_S3_DIR . $upload_path, $fileName, AWS_S3_DIR . $upload_path_src, $fileNameSrc);
	} else {
		$ret = FileCopy(DOCROOT . $upload_path, $fileName, DOCROOT . $upload_path_src, $fileNameSrc);
	}
	return $ret;
}

function StoreFilePath($upload_type, $fileName)
{
	$ret = array();
	$upload_path = 'uploads/' . UPLOAD_TYPE[$upload_type];
	if (defined('USE_OBJECT_STORAGE') && USE_OBJECT_STORAGE == 'Y') {
		$ret = ObjectStoragePath(AWS_S3_DIR . $upload_path, $fileName);
	} else {
		$ret = FilePath(DOCROOT . $upload_path, $fileName);
	}
	return $ret;
}

function StoreFileExist($upload_type, $fileName)
{
	$ret = array();
	$upload_path = 'uploads/' . UPLOAD_TYPE[$upload_type];
	if (defined('USE_OBJECT_STORAGE') && USE_OBJECT_STORAGE == 'Y') {
		$ret = ObjectStorageExist(AWS_S3_DIR . $upload_path, $fileName);
	} else {
		$ret = FileExist(DOCROOT . $upload_path, $fileName);
	}
	return $ret;
}

/**
 * Object Storage + File Storage + Store File Functions End
 **/


function get_array_from_query($query)
{
	$ret = array();
	$dataArr = GetDataFromQuery($query);
	foreach ($dataArr as $row) $ret[] = (array) $row;
	return $ret;
}

function convert_ary_to_id_name($ary)
{
	return array_map(
		function ($k, $v) {
			return array(
				'id' => $k,
				'name' => $v,
			);
		},
		array_keys($ary),
		array_values($ary)
	);
}


/**
 * Request Logs
 *
 * @param string	$user_type		User type values=[CTR/ARM/ACM]
 * @param int		$user_id		User ID
 * @param string	$req_type		Request type values=[FLT/HOT/CAB/FPH]
 * @param int		$req_id			Request ID
 * @param string	$sql_mode		SQL mode values=[INSERT/UPDATE/DELETE]
 * @param string	$sql_desc		SQL description
 * @param string	$sql_query		SQL Query
 * 
 * @return int	Log ID
 **/
function log_request_changes($user_type, $user_id, $req_type, $req_id, $sql_mode, $sql_desc, $sql_query)
{
	$IP = $_SERVER['REMOTE_ADDR'];
	$NOW = NOW;
	$iLRID = NextID("iLRID", "log_requests");
	sql_query(
		"INSERT INTO log_requests (
			iLRID, cRef_UserType, iRef_UserID, dtEntry, 
			cRefType, iRefID, cMode, vDesc, vQuery, 
			vIP, cStatus
		) VALUES (
			{$iLRID}, '{$user_type}', {$user_id}, '{$NOW}', 
			'{$req_type}', {$req_id}, '{$sql_mode}', '{$sql_desc}', '{$sql_query}', 
			'{$IP}', 'A'
		)"
	);
	return $iLRID;
}

function callAPI($method, $url, $data, $http_header = array())
{
	$method = strtoupper($method);
	$curl = curl_init();

	// METHODS
	switch ($method) {
		case "POST":
			curl_setopt($curl, CURLOPT_POST, 1);
			if (!empty($data)) curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			break;

		case "GET":
			if (!empty($data)) $url = sprintf("%s?%s", $url, http_build_query($data));
			break;

		default:
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
			if (!empty($data)) curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			break;
	}

	// OPTIONS
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_HTTPHEADER, $http_header);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

	// EXECUTE
	$result = curl_exec($curl);
	curl_close($curl);

	/* error_log(print_r(array(
		'method'=>$method,
		'url'=>$url,
		'data'=>$data,
		'http_header'=>$http_header,
		'result'=>$result,
	),true)); */

	return $result;
}

function DecodeBase64File($fileBase64data)
{
	list($filemeta, $filedata) = explode(",", !empty($fileBase64data) ? $fileBase64data : ":/;,");
	list($filetype, $fileext) = explode("/", explode(";", explode(":", $filemeta)[1])[0]);
	return array(base64_decode($filedata), $filetype, $fileext);
}



function FillCombo2022($name, $selected, $dataArr, $sel_name, $class = "form-control", $change = "", $readonly = '')
{
	$str = '';
	$str .= '<select name="' . $name . '" id="' . $name . '" class="' . $class . '" onchange="' . $change . '" ' . $readonly . '>';
	$str .= '<option value="0">-Select ' . $sel_name . '-</option>';
	foreach ($dataArr as $id => $name) {
		$sel = '';
		if ($id == $selected) {
			$sel = 'selected';
		}
		$str .= '<option value="' . $id . '" ' . $sel . ' >' . $name . '</option>';
	}
	$str .= '</select>';

	return $str;
}

function GetVehicle_BasedOnSearch($txtpickup_time,$txtpickup_location,$txttype=0,$txtcatid=0)
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

function GetVehicle_BasedOnSearch2($txttype=0,$txtcatid=0,$show_currentstatus='N',$txtfrom_time='',$txtto_time='')
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
		
		$q2 = 'select iFleet_BookingID, iFleet_LocationID_From, iFleet_LocationID_To, vPickUpLocation, vPickUpTime, vDropLocation, vDropTime, iDriverID, iVehicleID, cDisposal, cType from fleet_booking where iVehicleID IN ('.implode(',',array_keys($arr)).') and cStatus NOT IN ("X") and vPickUpTime < "'.$txtto_time.'" and vDropTime > "'.$txtfrom_time.'" order by iVehicleID, vPickUpTime';
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
			$q3 = 'select iFleet_BookingID, iFleet_LocationID_From, iFleet_LocationID_To, vPickUpLocation, vPickUpTime, vDropLocation, vDropTime, iDriverID, iVehicleID, cDisposal, cType from ( select *, ROW_NUMBER() OVER ( PARTITION BY iVehicleID ORDER BY vPickUpTime ) AS rn FROM fleet_booking WHERE iVehicleID IN ('.implode(',',array_keys($arr2)).') AND cStatus NOT IN ("X") AND vPickUpTime > "'.$txtfrom_time.'" ) t WHERE rn = 1';
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
?>