<?php
include "config.inc.php"; // db configurations
include "define.inc.php"; // # defines
include "generic.inc.php"; // # common functions
include "common.inc.php"; // # project specific functions
include "userdat.php"; // #
include "sql.inc.php"; // # sql functions
include "custom.php"; // # sql functions
// include "dynamic.inc.php";
include "dynamic_api.inc.php"; // # sql functions
include "common.master.php";

//include_once DOCROOT.'includes/libs/google_client/vendor/autoload.php';

// Set CORS headers for all API requests
header('Content-Type: application/json; charset=utf-8');

// Allow all origins for API requests
header("Access-Control-Allow-Origin: *");

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, Cache-Control, X-File-Name, sec-ch-ua, sec-ch-ua-mobile, sec-ch-ua-platform, User-Agent, Referer");
// Note: Cannot use credentials with wildcard origin
header("Access-Control-Max-Age: 86400"); // Cache preflight for 24 hours
header("Referrer-Policy: strict-origin-when-cross-origin");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

sql_query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
