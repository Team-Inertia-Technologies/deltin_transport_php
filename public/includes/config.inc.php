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
define('DOCROOT', $_ENV["DOCROOT"]);
define('SITE_NAME', $_ENV["APP_NAME"]);

define('DB_HOST', $_ENV["DB_HOST"]);
define('DB_USERNAME', $_ENV["DB_USERNAME"]);
define('DB_PASSWORD', $_ENV["DB_PASSWORD"]);
define('DB_NAME', $_ENV["DB_NAME"]);

define('USE_OBJECT_STORAGE', $_ENV["USE_OBJECT_STORAGE"]);
define('AWS_S3_ENDPOINT', $_ENV["AWS_S3_ENDPOINT"]);
define('AWS_S3_REGION', $_ENV["AWS_S3_REGION"]);
define('AWS_S3_KEY', $_ENV["AWS_S3_KEY"]);
define('AWS_S3_SECRET', $_ENV["AWS_S3_SECRET"]);
define('AWS_S3_BUCKET', $_ENV["AWS_S3_BUCKET"]);
define('AWS_S3_BUCKET_PUT', $_ENV["AWS_S3_BUCKET_PUT"]);
define('AWS_S3_BUCKET_GET', $_ENV["AWS_S3_BUCKET_GET"]);
define('AWS_S3_DIR', $_ENV["AWS_S3_DIR"]);

//var_dump(get_defined_constants(true)['user']);exit;
?>