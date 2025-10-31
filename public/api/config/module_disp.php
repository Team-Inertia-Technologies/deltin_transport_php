<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";

$levels = array();

$query = "SELECT iLevelD, vName FROM levels WHERE cStatus='A' ORDER BY vName DESC";
$result = sql_query($query);

if (sql_num_rows($result)) {
	while ($data = sql_fetch_assoc($result)) {
		$levels[$data['iLevelD']] = $data['vName'];
	}

	$response = array(
		"data" => array(
			"message" => "Successfully fetched the levels",
			"levels" => $levels,
		),
		"statusCode" => 200,
	);
	http_response_code(200);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
} else {
	$response = array(
		"error" => array(
			"message" => "No Levels Found",
		),
		"statusCode" => 400,
	);
	http_response_code(400);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
}
?>