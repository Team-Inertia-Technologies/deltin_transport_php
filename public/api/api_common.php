<?php

/**
 * API Include
 **/
function api_include($path, $exclude = array())
{
	$files = array_map(function ($val) {
		return realpath($val);
	}, array_diff(glob("$path/*.php"), array_map(function ($val) use ($path) {
		return "$path/$val";
	}, $exclude)));
	foreach ($files as $file) if (is_file($file)) include_once($file);
}

/**
 * API Numeric Input
 **/
function api_in_get_numeric($var_name, $def = 0)
{
	$var = isset($_GET[$var_name]) ? $_GET[$var_name] : $def;
	return (!empty($var) && is_numeric($var)) ? $var : $def;
}

/**
 * API String Input
 **/
function api_in_get_str($var_name, $def = '')
{
	$var = isset($_GET[$var_name]) ? $_GET[$var_name] : $def;
	$var = trim($var);
	return (!empty($var)) ? $var : $def;
}

/**
 * API Numeric Input
 **/
function api_in_post_numeric($var_name, $def = 0)
{
	$var = isset($_POST[$var_name]) ? $_POST[$var_name] : $def;
	return (!empty($var) && is_numeric($var)) ? $var : $def;
}

/**
 * API String Input
 **/
function api_in_post_str($var_name, $def = '')
{
	$var = isset($_POST[$var_name]) ? $_POST[$var_name] : $def;
	$var = trim($var);
	return (!empty($var)) ? $var : $def;
}

/**
 * API TimeStamp
 **/
function api_timestamp_str($DateTime = null)
{
	$timestamp = strtotime(isset($DateTime) ? $DateTime : null);
	if (!isset($DateTime)) {
		$DateTime = date(DateTimeInterface::RFC3339_EXTENDED);
	} else {
		if ($timestamp !== false) {
			$DateTime = date(DateTimeInterface::RFC3339_EXTENDED, $timestamp);
		} else {
			$DateTime = null;
		}
	}
	return $DateTime;
}

/**
 * API Send HTTP Header Status
 **/
function api_sendHTTPstatus($num)
{
	$http = array(
		100 => 'HTTP/1.1 100 Continue',
		101 => 'HTTP/1.1 101 Switching Protocols',
		200 => 'HTTP/1.1 200 OK',
		201 => 'HTTP/1.1 201 Created',
		202 => 'HTTP/1.1 202 Accepted',
		203 => 'HTTP/1.1 203 Non-Authoritative Information',
		204 => 'HTTP/1.1 204 No Content',
		205 => 'HTTP/1.1 205 Reset Content',
		206 => 'HTTP/1.1 206 Partial Content',
		300 => 'HTTP/1.1 300 Multiple Choices',
		301 => 'HTTP/1.1 301 Moved Permanently',
		302 => 'HTTP/1.1 302 Found',
		303 => 'HTTP/1.1 303 See Other',
		304 => 'HTTP/1.1 304 Not Modified',
		305 => 'HTTP/1.1 305 Use Proxy',
		307 => 'HTTP/1.1 307 Temporary Redirect',
		400 => 'HTTP/1.1 400 Bad Request',
		401 => 'HTTP/1.1 401 Unauthorized',
		402 => 'HTTP/1.1 402 Payment Required',
		403 => 'HTTP/1.1 403 Forbidden',
		404 => 'HTTP/1.1 404 Not Found',
		405 => 'HTTP/1.1 405 Method Not Allowed',
		406 => 'HTTP/1.1 406 Not Acceptable',
		407 => 'HTTP/1.1 407 Proxy Authentication Required',
		408 => 'HTTP/1.1 408 Request Time-out',
		409 => 'HTTP/1.1 409 Conflict',
		410 => 'HTTP/1.1 410 Gone',
		411 => 'HTTP/1.1 411 Length Required',
		412 => 'HTTP/1.1 412 Precondition Failed',
		413 => 'HTTP/1.1 413 Request Entity Too Large',
		414 => 'HTTP/1.1 414 Request-URI Too Large',
		415 => 'HTTP/1.1 415 Unsupported Media Type',
		416 => 'HTTP/1.1 416 Requested Range Not Satisfiable',
		417 => 'HTTP/1.1 417 Expectation Failed',
		500 => 'HTTP/1.1 500 Internal Server Error',
		501 => 'HTTP/1.1 501 Not Implemented',
		502 => 'HTTP/1.1 502 Bad Gateway',
		503 => 'HTTP/1.1 503 Service Unavailable',
		504 => 'HTTP/1.1 504 Gateway Time-out',
		505 => 'HTTP/1.1 505 HTTP Version Not Supported',
	);

	header($http[$num]);
}

/**
 * API JSON Pretty Print Response
 **/
function api_json_pretty_print_response($response, $first_decode = false)
{
	return json_encode($first_decode ? json_decode($response) : $response, JSON_PRETTY_PRINT);
}

/**
 * API Clean Date
 **/
function api_cleanDate($date)
{
	$date = preg_replace('/[^-\/\d]/i', '', $date);
	/* $date = preg_match("/(\d{2}[-\/]\d{2}[-\/]\d{4}|\d{4}[-\/]\d{2}[-\/]\d{2})/i", $date, $match)
			?date("d/m/Y",strtotime($match[0])):$date; */
	$date = date("d/m/Y", strtotime($date));
	return $date;
}

/**
 * API Create Dir
 **/
function api_createDir($dir, $perm = 0777)
{
	if (file_exists($dir)) {
		if (!is_writable($dir)) chmod($dir, $perm);
	} else {
		mkdir($dir, $perm, true);
	}
}

/**
 * API Save File from Base64
 **/
function SaveFileBase64($fileBase64, $folderPath, $fileName)
{
	$NOW3 = NOW3;

	$image_parts = explode(";base64,", $fileBase64);
	$image_type_aux = explode("image/", $image_parts[0]);
	$image_type = $image_type_aux[1];

	$image_base64 = base64_decode($image_parts[1]);
	$fileName = "{$fileName}_{$NOW3}.{$image_type}";

	$file = $folderPath . $fileName;
	file_put_contents($file, $image_base64);

	return $fileName;
}

function verifyPropAssoc($user_id, $property_id)
{
	$user_id = intval($user_id);
	$property_id = intval($property_id);
	$cnt = GetXFromYID("SELECT COUNT(*) FROM users_property_assoc WHERE iUserID={$user_id} AND iPropertyID={$property_id} ");
	return (!empty($cnt) && $cnt > 0) ? true : false;
}

function verifyTokenSup()
{
	$token_sup = isset($_POST['token_sup']) ? strtoupper($_POST['token_sup']) : '';
	$user = GetDataFromQuery("SELECT * FROM users WHERE vToken='{$token_sup}' AND vToken!='' AND vToken IS NOT NULL LIMIT 1");

	if (!empty($user[0])) {
		$user = $user[0];

		$user_sup_id = intval($user->iUserID);
		$user_sup_data = $user;
		$user_sup_property_id = GetIDString("SELECT iPropertyID FROM users_property_assoc WHERE iUserID={$user_sup_data->iUserID}");

		return [
			'verified' => true,
			'user_sup_id' => $user_sup_id,
			'user_sup_data' => $user_sup_data,
			'user_sup_property_id' => $user_sup_property_id
		];
	} else {
		$errorCode = 404;
		api_sendHTTPstatus($errorCode);
		return [
			'verified' => false,
			'error' => [
				"description" => "TokenVerify: Supervisor User not found",
				"force_logout" => true
			],
			"StatusCode" => $errorCode
		];
	}
}

function FetchSessionDate($property_id,$counter_id=0)
{
	$arr = array('PROPERTY_SESSION_DATE'=>'', 'PROPERTY_SESSION_STATUS'=>'', 'COUNTER_SESSION_ID'=>'', 'COUNTER_SESSION_DATE'=>'', 'COUNTER_SESSION_STATUS'=>'');

	$_pq = 'select dSessionDate, cStatus from property_vesselclosing where iPropertyID='.$property_id.' order by dSessionDate desc limit 1';
	$_pr = sql_query($_pq,'');
	if(sql_num_rows($_pr))
		list($PROPERTY_SESSION_DATE,$PROPERTY_SESSION_STATUS) = sql_fetch_row($_pr);
	else
	{
		$PROPERTY_START_SESSIONDATE = TODAY;
		$_pq = 'select iCompanyID, dStart from gen_property where iPropertyID='.$property_id;
		$_pr = sql_query($_pq,'');
		if(sql_num_rows($_pr))
		{
			list($PORPERTY_COMPANY_ID,$PROPERTY_START_DATE) = sql_fetch_row($_pr);
			if(!empty($PROPERTY_START_DATE) && $PROPERTY_START_DATE!='0000-00-00')
				$PROPERTY_START_SESSIONDATE = $PROPERTY_START_DATE;
		}

		LockTable('property_vesselclosing');
		$iPropertyVesselID = NextID('iPropertyVesselID','property_vesselclosing');
		sql_query("INSERT INTO property_vesselclosing values ('$iPropertyVesselID', '$PORPERTY_COMPANY_ID', '$property_id', '".NOW."', '$PROPERTY_START_SESSIONDATE', NULL, NULL, 0, NULL, 0, 'N', NULL, 'A')");
		UnlockTable();

		$PROPERTY_SESSION_DATE = $PROPERTY_START_SESSIONDATE;
		$PROPERTY_SESSION_STATUS = 'A';
	}

	$COUNTER_SESSION_ID = $COUNTER_SESSION_DATE = $COUNTER_SESSION_STATUS = '';
	if(!empty($counter_id))
	{
		$_cq = 'select iSessCID, dSessionDate, cStatus from session_closing where iPropertyID='.$property_id.' and iCounterID='.$counter_id.' order by dSessionDate desc limit 1'; // and dSessionDate>="'.$PROPERTY_SESSION_DATE.'"
		$_cr = sql_query($_cq,'');
		if(sql_num_rows($_cr))
			list($COUNTER_SESSION_ID,$COUNTER_SESSION_DATE,$COUNTER_SESSION_STATUS) = sql_fetch_row($_cr);
		else
		{
			LockTable('session_closing');
			$iSessCID = NextID('iSessCID','session_closing');
			sql_query("INSERT INTO session_closing values ('$iSessCID', '".NOW."', '$property_id', '$PROPERTY_SESSION_DATE', '$counter_id', 0, 0, 0, 0, 0, '', '".NOW."', NULL, 0, NULL, 0, NULL, 'A')");
			UnlockTable();

			$COUNTER_SESSION_ID = $iSessCID;
			$COUNTER_SESSION_DATE = $PROPERTY_START_SESSIONDATE;
			$COUNTER_SESSION_STATUS = 'A';
		}
	}

	$arr = array('PROPERTY_SESSION_DATE'=>$PROPERTY_SESSION_DATE, 'PROPERTY_SESSION_STATUS'=>$PROPERTY_SESSION_STATUS, 'COUNTER_SESSION_ID'=>$COUNTER_SESSION_ID, 'COUNTER_SESSION_DATE'=>$COUNTER_SESSION_DATE, 'COUNTER_SESSION_STATUS'=>$COUNTER_SESSION_STATUS); 

	return $arr;
}
