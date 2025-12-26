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
include "fleet_log.inc.php"; // fleet logging functions
//include "../api/api_common.php";
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

/**
 * Generic function to toggle status of any record
 * @param int $id - Record ID
 * @param string $tableName - Table name
 * @param string $idColumn - Primary key column name
 * @param string $statusColumn - Status column name (default: 'cStatus')
 * @param string $nameColumn - Name column for logging (optional)
 * @param string $logCode - Log code for tracking (optional)
 * @param int $userId - User ID for logging
 * @return array - Response array with status and message
 */
function toggleStatus($id, $tableName, $idColumn, $statusColumn = 'cStatus', $nameColumn = '', $logCode = '', $userId = 0) {
    // Validate inputs
    if ($id <= 0) {
        return [
            "error" => [
                "message" => "Invalid ID provided"
            ],
            "statusCode" => 400
        ];
    }

    if (empty($tableName) || empty($idColumn)) {
        return [
            "error" => [
                "message" => "Table name and ID column are required"
            ],
            "statusCode" => 400
        ];
    }

    // Get current status
    $selectColumns = $statusColumn;
    if (!empty($nameColumn)) {
        $selectColumns .= ", $nameColumn";
    }
    
    $currentSql = "SELECT $selectColumns FROM $tableName WHERE $idColumn = $id AND $statusColumn IN ('A', 'I')";
    $currentRes = sql_query($currentSql);

    if (sql_num_rows($currentRes) == 0) {
        return [
            "error" => [
                "message" => "Record not found"
            ],
            "statusCode" => 404
        ];
    }

    $currentRow = sql_fetch_assoc($currentRes);
    $newStatus = $currentRow[$statusColumn] == 'A' ? 'I' : 'A';

    // Update status
    $sql = "UPDATE $tableName SET $statusColumn = '$newStatus' WHERE $idColumn = $id";
    $result = sql_query($sql);

    if ($result && sql_affected_rows() > 0) {
        // Log the status change if logging parameters provided
        if (!empty($logCode) && $userId > 0 && !empty($nameColumn)) {
            $recordName = $currentRow[$nameColumn] ?? '';
            LogMasterEdit($id, $logCode, 'U', $recordName, 'Status changed to ' . ($newStatus == 'A' ? 'Active' : 'Inactive'), $userId);
        }

        return [
            "statusCode" => 200,
          
            "data" => [
                  "message" => "Status updated successfully",
                "newStatus" => $newStatus,
                "statusText" => $newStatus == 'A' ? 'Active' : 'Inactive'
            ]
        ];
    } else {
        return [
            "error" => [
                "message" => "Failed to update status"
            ],
            "statusCode" => 500
        ];
    }
}
