<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";
date_default_timezone_set('Asia/Calcutta');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Expires: " . gmdate("D, d M Y H:i:s", 1) . " GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$postdata = file_get_contents("php://input");
$request = json_decode($postdata);

$token = trim($request->token);
$userid = DecodeParam($token);
$stars = isset($request->stars) ? intval($request->stars) : 0;
$dateID = isset($request->dateID) ? trim($request->dateID) : '';
$status = isset($request->status) ? trim($request->status) : '';

$dateFilter = "";
if ($dateID == 1) {
    $dateFilter = "AND fb.vPickUpTime >= CURDATE() AND fb.vPickUpTime < CURDATE() + INTERVAL 1 DAY";
} elseif ($dateID == 2) {
    $dateFilter = "AND fb.vPickUpTime >= CURDATE() - INTERVAL 1 DAY AND fb.vPickUpTime < CURDATE()";
} elseif ($dateID == 3) {
    $dateFilter = "AND fb.vPickUpTime >= CURDATE() - INTERVAL 7 DAY";
} elseif ($dateID == 4) {
    $dateFilter = "AND fb.vPickUpTime >= CURDATE() - INTERVAL 30 DAY";
} elseif ($dateID == 5) {
    $dateFilter = "";
}

if ($status) {
    $statusFilter = "AND fb.cType = '" . db_input($status) . "'";
} else {
    $statusFilter = "";
}

if (!$token) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => "Missing token."
        ]
    ]);
    exit;
}



// -------------------- VERIFY TOKEN --------------------
$q = "SELECT iDriverID, vName FROM driver WHERE iDriverID='$userid' AND cStatus='A'";
$r = sql_query($q, 'AUTH.LEAD');

if (!sql_num_rows($r)) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['statusCode' => 401, 'message' => 'Invalid Token.']);
    exit;
}

$user = sql_fetch_assoc($r);

// -------------------- FETCH DRIVER TRIPS --------------------
$driverID = intval($userid);

// SQL QUERY
$sql = "
SELECT 
    fb.iFleet_BookingID,
    fb.cBookingFor,
    fb.vPickUpTime,
    fb.vName AS guestName,
    fb.vMobileNo AS guestMobile,
    fb.iPax,
    fb.iBaggage,
	fb.cStatus,
    fb.cType,
	fb.fRate,
	fb.vDropTime,
    fb.vPickUpLocation AS fromLocation,
    fb.vDropLocation AS toLocation
FROM fleet_booking fb
WHERE 
    fb.cType IN ('P', 'C')
    {$dateFilter}
    {$statusFilter}
    AND (
        fb.iDriverID = '{$driverID}' 
    )
ORDER BY fb.vPickUpTime ASC
";

$res = sql_query($sql, "TRIPS.LIST");
if (!$res) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        "statusCode" => 400,
        "error" => [
            "message" => "Failed to fetch trips."
        ]
    ]);
    exit;
}

$trips = [];


while ($row = sql_fetch_assoc($res)) {

    $cType = isset($row['cType']) ? trim($row['cType']) : '';
    if ($cType === 'C') {
        $status = 'Completed';
    } elseif ($cType === 'P') {
        $status = 'Paused';
    } else {
        $status = '';
    }

    $trips[] = [
        "status" => $status,
        "name"   => $row["guestName"],
        "maskedmobile" => maskMobileNumber($row["guestMobile"]),
        "mobile" => $row["guestMobile"],
        "pax"    => intval($row["iPax"]),
        "bags"   => intval($row["iBaggage"]),
        "from"   => $row["fromLocation"],
        "to"     => $row["toLocation"],
        "pickupDatetime" => $row["vPickUpTime"],
        "dropDateTime"   => $row["vDropTime"],
        "ratings"       => intval($row["fRate"]),
        "type"           => $row["cBookingFor"] == 'G' ? 'Guest' : 'Staff',
    ];
}

if (empty($trips)) {
    http_response_code(200);
    header("Content-Type: application/json");
    echo json_encode([
        "statusCode" => 200,
        "message" => "No record found for this driver.",
        "data" => [
            "scheduleList" => [],
            "dateFilter" => [
                [
                    "id" => 1,
                    "lable" => "today"
                ],
                [
                    "id" => 2,
                    "lable" => "Yesterday"
                ],
                [
                    "id" => 3,
                    "lable" => "Last 7 days"
                ],
                [
                    "id" => 4,
                    "lable" => "Last 30 Days"
                ],
                [
                    "id" => 5,
                    "lable" => "All time"
                ]
            ],
            "starFilter" => [
                [
                    "id" => 0,
                    "lable" => "All Ratings"
                ],
                [
                    "id" => 1,
                    "lable" => "1 Star"
                ],
                [
                    "id" => 2,
                    "lable" => "2 Stars"
                ],
                [
                    "id" => 3,
                    "lable" => "3 Stars"
                ],
                [
                    "id" => 4,
                    "lable" => "4 Stars"
                ],
                [
                    "id" => 5,
                    "lable" => "5 Stars"
                ]
            ],
            "statusFilter" => [
                [
                    "id" => "P",
                    "lable" => "Paused"
                ],
                [
                    "id" => "C",
                    "lable" => "Completed"
                ]
            ]
        ]
    ]);
    exit;
}

$response = [
    "statusCode" => 200,
    "message" => "Data fetched successfully",
    "data" => [
        "scheduleList" => $trips,
        "dateFilter" => [
            [
                "id" => 1,
                "lable" => "today"
            ],
            [
                "id" => 2,
                "lable" => "Yesterday"
            ],
            [
                "id" => 3,
                "lable" => "Last 7 days"
            ],
            [
                "id" => 4,
                "lable" => "Last 30 Days"
            ],
            [
                "id" => 5,
                "lable" => "All time"
            ]
        ],
        "starFilter" => [
            [
                "id" => 0,
                "lable" => "All Ratings"
            ],
            [
                "id" => 1,
                "lable" => "1 Star"
            ],
            [
                "id" => 2,
                "lable" => "2 Stars"
            ],
            [
                "id" => 3,
                "lable" => "3 Stars"
            ],
            [
                "id" => 4,
                "lable" => "4 Stars"
            ],
            [
                "id" => 5,
                "lable" => "5 Stars"
            ]
        ],
        "statusFilter" => [
            [
                "id" => "P",
                "lable" => "Paused"
            ],
            [
                "id" => "C",
                "lable" => "Completed"
            ]
        ]
    ]
];


header("Content-Type: application/json");
echo json_encode($response);
exit;
