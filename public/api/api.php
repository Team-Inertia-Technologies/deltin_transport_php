<?php
header("Access-Control-Allow-Origin: *");// CORS Allow

// Cache control
header("Expires: " . gmdate("D, d M Y H:i:s", 1) . " GMT"); // Date in the past
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // HTTP/1.1
header("Pragma: no-cache"); // HTTP/1.0

require_once("api_includes.php");

$request_uri = explode("api.php",$_SERVER['REQUEST_URI']);
$request_uri = explode("?",isset($request_uri[1])?$request_uri[1]:'');
$request_uri = array_values(array_filter($request_uri));

//$route = !empty($_GET['route']) ? $_GET['route'] : null;
$route = isset($request_uri[0])?$request_uri[0]:'';
$ret = array();

$route_type = $route_sub = '';

if (preg_match('/^\/(?<type>\w+)/i', $route, $matches))
{
	$route_type = "/".$matches['type'];
	$route_sub = str_replace($route_type, '', $route);
}

switch (strtolower($route_type))
{
	case '/kyc':
		require_once(DOCROOT."api/kyc/kyc.php");
		$ret = (new \kyc)->main(0, 0);
	break;

	default:
		$errorCode = 404;
		api_sendHTTPstatus($errorCode);
		$ret = array(
			"error"=>array(
				"description"=>"Route '$route' not found",
			),
			"StatusCode"=>$errorCode,
		);
	break;
}

// JSON
header('Content-Type: application/json; charset=utf-8');
echo json_encode($ret,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
exit;
?>
