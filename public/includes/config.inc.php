<?php
if (empty($_ENV["DOCROOT"]))
{
	/**
	 * Please Quotes(") around the values
	 * Eg: APP_NAME="Site Name"
	**/ 
	$_ENV = parse_ini_file(realpath(dirname(__FILE__)."/../../credentials.env"));
	$_ENV = !empty($_ENV)?$_ENV:array();

	if (!empty($_ENV["APP_URL"])) $_ENV["APP_URL"] = str_replace('localhost', $_SERVER['SERVER_NAME'], $_ENV["APP_URL"]);
	if (!empty($_ENV["DOCROOT"])) $_ENV["DOCROOT"] = (realpath(dirname(__FILE__) . "/../") . "/");
}

if (
	// Website
	!isset($_ENV["APP_URL"]) || empty($_ENV["APP_URL"]) || 
	!isset($_ENV["DOCROOT"]) || empty($_ENV["DOCROOT"]) || 
	!isset($_ENV["APP_NAME"]) || empty($_ENV["APP_NAME"]) || 

	// Database
	!isset($_ENV["DB_HOST"]) || empty($_ENV["DB_HOST"]) || 
	!isset($_ENV["DB_USERNAME"]) || 
	!isset($_ENV["DB_PASSWORD"]) || 
	!isset($_ENV["DB_NAME"]) || empty($_ENV["DB_NAME"]) || 

	// Object Storage
	!isset($_ENV["USE_OBJECT_STORAGE"]) || empty($_ENV["USE_OBJECT_STORAGE"]) || 
		!isset($_ENV["SET_STORAGE"]) || empty($_ENV["SET_STORAGE"]) || 
	!isset($_ENV["AWS_S3_CDN_ENDPOINT"]) || empty($_ENV["AWS_S3_CDN_ENDPOINT"]) || 
	!isset($_ENV["AWS_S3_ENDPOINT"]) || empty($_ENV["AWS_S3_ENDPOINT"]) || 
	!isset($_ENV["AWS_S3_REGION"]) || empty($_ENV["AWS_S3_REGION"]) || 
	!isset($_ENV["AWS_S3_KEY"]) || empty($_ENV["AWS_S3_KEY"]) || 
	!isset($_ENV["AWS_S3_SECRET"]) || empty($_ENV["AWS_S3_SECRET"]) || 
	!isset($_ENV["AWS_S3_BUCKET"]) || empty($_ENV["AWS_S3_BUCKET"]) || 
	!isset($_ENV["AWS_S3_BUCKET_PUT"]) || empty($_ENV["AWS_S3_BUCKET_PUT"]) || 
	!isset($_ENV["AWS_S3_BUCKET_GET"]) || empty($_ENV["AWS_S3_BUCKET_GET"]) || 
	!isset($_ENV["AWS_S3_DIR"]) || 

	// Always keep false
	false
) {
	echo "Error: Set the environment variables and try again.";
	exit;
}

define('SITE_ADDRESS', $_ENV["APP_URL"]);
define('SITE_ADDRESS2', $_ENV["APP_URL"]);
define('DOCROOT', $_ENV["DOCROOT"]);
define('DOCROOT2', $_ENV["DOCROOT2"]);
define('SITE_NAME', $_ENV["APP_NAME"]);

define('DB_HOST', $_ENV["DB_HOST"]);
define('DB_USERNAME', $_ENV["DB_USERNAME"]);
define('DB_PASSWORD', $_ENV["DB_PASSWORD"]);
define('DB_NAME', $_ENV["DB_NAME"]);

define('USE_OBJECT_STORAGE', $_ENV["USE_OBJECT_STORAGE"]);
define('SET_STORAGE', $_ENV["SET_STORAGE"]);
define('AWS_S3_ENDPOINT', $_ENV["AWS_S3_ENDPOINT"]);
define('AWS_S3_CDN_ENDPOINT', $_ENV["AWS_S3_CDN_ENDPOINT"]);
define('AWS_S3_REGION', $_ENV["AWS_S3_REGION"]);
define('AWS_S3_KEY', $_ENV["AWS_S3_KEY"]);
define('AWS_S3_SECRET', $_ENV["AWS_S3_SECRET"]);
define('AWS_S3_BUCKET', $_ENV["AWS_S3_BUCKET"]);
define('AWS_S3_BUCKET_PUT', $_ENV["AWS_S3_BUCKET_PUT"]);
define('AWS_S3_BUCKET_GET', $_ENV["AWS_S3_BUCKET_GET"]);
define('AWS_S3_DIR', $_ENV["AWS_S3_DIR"]);

// Firebase Configuration
if (isset($_ENV["FIREBASE_TYPE"]) && !empty($_ENV["FIREBASE_TYPE"])) {
    define('FIREBASE_TYPE', $_ENV["FIREBASE_TYPE"]);
}
if (isset($_ENV["FIREBASE_PROJECT_ID"]) && !empty($_ENV["FIREBASE_PROJECT_ID"])) {
    define('FIREBASE_PROJECT_ID', $_ENV["FIREBASE_PROJECT_ID"]);
}
if (isset($_ENV["FIREBASE_PRIVATE_KEY_ID"]) && !empty($_ENV["FIREBASE_PRIVATE_KEY_ID"])) {
    define('FIREBASE_PRIVATE_KEY_ID', $_ENV["FIREBASE_PRIVATE_KEY_ID"]);
}
if (isset($_ENV["FIREBASE_PRIVATE_KEY"]) && !empty($_ENV["FIREBASE_PRIVATE_KEY"])) {
    define('FIREBASE_PRIVATE_KEY', $_ENV["FIREBASE_PRIVATE_KEY"]);
}
if (isset($_ENV["FIREBASE_CLIENT_EMAIL"]) && !empty($_ENV["FIREBASE_CLIENT_EMAIL"])) {
    define('FIREBASE_CLIENT_EMAIL', $_ENV["FIREBASE_CLIENT_EMAIL"]);
}
if (isset($_ENV["FIREBASE_CLIENT_ID"]) && !empty($_ENV["FIREBASE_CLIENT_ID"])) {
    define('FIREBASE_CLIENT_ID', $_ENV["FIREBASE_CLIENT_ID"]);
}
if (isset($_ENV["FIREBASE_AUTH_URI"]) && !empty($_ENV["FIREBASE_AUTH_URI"])) {
    define('FIREBASE_AUTH_URI', $_ENV["FIREBASE_AUTH_URI"]);
}
if (isset($_ENV["FIREBASE_TOKEN_URI"]) && !empty($_ENV["FIREBASE_TOKEN_URI"])) {
    define('FIREBASE_TOKEN_URI', $_ENV["FIREBASE_TOKEN_URI"]);
}
if (isset($_ENV["FIREBASE_AUTH_PROVIDER_CERT_URL"]) && !empty($_ENV["FIREBASE_AUTH_PROVIDER_CERT_URL"])) {
    define('FIREBASE_AUTH_PROVIDER_CERT_URL', $_ENV["FIREBASE_AUTH_PROVIDER_CERT_URL"]);
}
if (isset($_ENV["FIREBASE_CLIENT_CERT_URL"]) && !empty($_ENV["FIREBASE_CLIENT_CERT_URL"])) {
    define('FIREBASE_CLIENT_CERT_URL', $_ENV["FIREBASE_CLIENT_CERT_URL"]);
}
if (isset($_ENV["FIREBASE_UNIVERSE_DOMAIN"]) && !empty($_ENV["FIREBASE_UNIVERSE_DOMAIN"])) {
    define('FIREBASE_UNIVERSE_DOMAIN', $_ENV["FIREBASE_UNIVERSE_DOMAIN"]);
}

// Firebase Configuration - deltintransportapp (Account 2)
if (isset($_ENV["FIREBASE2_TYPE"]) && !empty($_ENV["FIREBASE2_TYPE"])) {
    define('FIREBASE2_TYPE', $_ENV["FIREBASE2_TYPE"]);
}
if (isset($_ENV["FIREBASE2_PROJECT_ID"]) && !empty($_ENV["FIREBASE2_PROJECT_ID"])) {
    define('FIREBASE2_PROJECT_ID', $_ENV["FIREBASE2_PROJECT_ID"]);
}
if (isset($_ENV["FIREBASE2_PRIVATE_KEY_ID"]) && !empty($_ENV["FIREBASE2_PRIVATE_KEY_ID"])) {
    define('FIREBASE2_PRIVATE_KEY_ID', $_ENV["FIREBASE2_PRIVATE_KEY_ID"]);
}
if (isset($_ENV["FIREBASE2_PRIVATE_KEY"]) && !empty($_ENV["FIREBASE2_PRIVATE_KEY"])) {
    define('FIREBASE2_PRIVATE_KEY', $_ENV["FIREBASE2_PRIVATE_KEY"]);
}
if (isset($_ENV["FIREBASE2_CLIENT_EMAIL"]) && !empty($_ENV["FIREBASE2_CLIENT_EMAIL"])) {
    define('FIREBASE2_CLIENT_EMAIL', $_ENV["FIREBASE2_CLIENT_EMAIL"]);
}
if (isset($_ENV["FIREBASE2_CLIENT_ID"]) && !empty($_ENV["FIREBASE2_CLIENT_ID"])) {
    define('FIREBASE2_CLIENT_ID', $_ENV["FIREBASE2_CLIENT_ID"]);
}
if (isset($_ENV["FIREBASE2_AUTH_URI"]) && !empty($_ENV["FIREBASE2_AUTH_URI"])) {
    define('FIREBASE2_AUTH_URI', $_ENV["FIREBASE2_AUTH_URI"]);
}
if (isset($_ENV["FIREBASE2_TOKEN_URI"]) && !empty($_ENV["FIREBASE2_TOKEN_URI"])) {
    define('FIREBASE2_TOKEN_URI', $_ENV["FIREBASE2_TOKEN_URI"]);
}
if (isset($_ENV["FIREBASE2_AUTH_PROVIDER_CERT_URL"]) && !empty($_ENV["FIREBASE2_AUTH_PROVIDER_CERT_URL"])) {
    define('FIREBASE2_AUTH_PROVIDER_CERT_URL', $_ENV["FIREBASE2_AUTH_PROVIDER_CERT_URL"]);
}
if (isset($_ENV["FIREBASE2_CLIENT_CERT_URL"]) && !empty($_ENV["FIREBASE2_CLIENT_CERT_URL"])) {
    define('FIREBASE2_CLIENT_CERT_URL', $_ENV["FIREBASE2_CLIENT_CERT_URL"]);
}
if (isset($_ENV["FIREBASE2_UNIVERSE_DOMAIN"]) && !empty($_ENV["FIREBASE2_UNIVERSE_DOMAIN"])) {
    define('FIREBASE2_UNIVERSE_DOMAIN', $_ENV["FIREBASE2_UNIVERSE_DOMAIN"]);
}
if (isset($_ENV["HEIGIT_DISTACE_API_KEY"]) && !empty($_ENV["HEIGIT_DISTACE_API_KEY"])) {
    define('HEIGIT_DISTACE_API_KEY', $_ENV["HEIGIT_DISTACE_API_KEY"]);
}
if (isset($_ENV["STAFF_APP_URL"]) && !empty($_ENV["STAFF_APP_URL"])) {
    define('STAFF_APP_URL', $_ENV["STAFF_APP_URL"]);
}


?>
