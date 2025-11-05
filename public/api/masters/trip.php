<?php
ini_set('display_errors', 1);

include "../../includes/common_api.php";

header('Content-Type: application/json');
$postdata = file_get_contents("php://input");

$request = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);
$mode = $_REQUEST['mode'] ?? '';
$Token = $_REQUEST['token'] ?? '';
$user_id = intval(DecodeParam($Token));
$userCheckSql = "SELECT iUserID FROM users WHERE iUserID = $user_id AND cStatus = 'A'";
$userCheckRes = sql_query($userCheckSql);

if (sql_num_rows($userCheckRes) == 0) {
    echo json_encode([
        "error" => [
            "message" => "User not found or inactive"
        ],
        "statusCode" => 401
    ]);
    exit;
}

switch ($mode) {

    // ===================== CASE 1: LIST =====================
    case 'LIST':
        $fromDate = $_REQUEST['fromDate'] ?? '';
        $toDate = $_REQUEST['toDate'] ?? '';
        $routeID = intval($_REQUEST['routeID'] ?? 0);

        // Build WHERE conditions
        $whereConditions = ["t.cStatus = 'A'"];

        if (!empty($fromDate)) {
            $whereConditions[] = "DATE(t.dtTrip) >= '$fromDate'";
        }

        if (!empty($toDate)) {
            $whereConditions[] = "DATE(t.dtTrip) <= '$toDate'";
        }

        if ($routeID > 0) {
            $whereConditions[] = "t.iRouteID = $routeID";
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Main query to get trip data
        $sql = "SELECT 
                    t.iTripID as id,
                    t.dtTrip,
                    r.vName as route,
                    r.vDestination as destination,
                    t.iCapacity,
                    t.iAvaialed as availed,
                    t.iRequested as pax,
                    t.iVehicleID
                FROM st_trips t
                LEFT JOIN st_route r ON t.iRouteID = r.iRouteID
                WHERE $whereClause
                ORDER BY t.dtTrip DESC";

        $res = sql_query($sql);
        $rowData = [];

        while ($row = sql_fetch_assoc($res)) {
            // Format date and time
            $dateTime = date('d/m/Y H:i', strtotime($row['dtTrip']));

            // Build vehicle details string
            $vehicleDetail = '';
            if (!empty($row['iVehicleID'])) {
                $vehicleIDs = explode(',', $row['iVehicleID']);
                $vehicleDetails = [];

                foreach ($vehicleIDs as $vehicleID) {
                    $vehicleID = trim($vehicleID);
                    if (!empty($vehicleID)) {
                        $vehicleSql = "SELECT v.vRnum, vc.iCapacity 
                                      FROM vehicle v
                                      LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                                      WHERE v.iVehicleID = $vehicleID AND v.cStatus = 'A'";
                        $vehicleRes = sql_query($vehicleSql);
                        if ($vehicleRow = sql_fetch_assoc($vehicleRes)) {
                            $capacity = $vehicleRow['iCapacity'] ?? 0;
                            $vehicleDetails[] = $vehicleRow['vRnum'] . ' (' . $capacity . ')';
                        }
                    }
                }
                $vehicleDetail = implode(' , ', $vehicleDetails);
            }

            $rowData[] = [
                "id" => (int) $row['id'],
                "dateTime" => $dateTime,
                "route" => $row['route'] ?? '',
                "destination" => $row['destination'] ?? '',
                "vehicleDetail" => $vehicleDetail,
                "pax" => (int) ($row['pax'] ?? 0),
                "availed" => (int) ($row['availed'] ?? 0)
            ];
        }

        // Get routes for dropdown
        $routesSql = "SELECT iRouteID, vName FROM st_route WHERE cStatus = 'A' ORDER BY iRank";
        $routesRes = sql_query($routesSql);

        $routesOpt = [
            ["id" => 0, "name" => "All"]
        ];

        while ($routeRow = sql_fetch_assoc($routesRes)) {
            $routesOpt[] = [
                "id" => (int) $routeRow['iRouteID'],
                "name" => $routeRow['vName']
            ];
        }

        echo json_encode([
            "data" => [
                "rowData" => $rowData,
                "routesOpt" => $routesOpt,
                "fromDate" => $fromDate,
                "toDate" => $toDate,
                "routeID" => $routeID
            ],
            "statusCode" => 200
        ]);
        break;
        
    // ===================== CASE ADD_ONLOAD =====================
    case 'ADD_ONLOAD':
        // Get vehicle options
        $vehicleSql = "SELECT v.iVehicleID, v.vRnum, vc.iCapacity 
                      FROM vehicle v
                      LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                      WHERE v.cStatus = 'A'
                      ORDER BY v.vRnum";
        $vehicleRes = sql_query($vehicleSql);
        
        $vehiOpt = [
            ["id" => 0, "name" => "Choose"]
        ];
        
        while ($vehicleRow = sql_fetch_assoc($vehicleRes)) {
            $capacity = $vehicleRow['iCapacity'] ?? 0;
            $vehiOpt[] = [
                "id" => (int) $vehicleRow['iVehicleID'],
                "name" => $vehicleRow['vRnum'] . ' (' . $capacity . ')'
            ];
        }

        // Get mode options (transportation modes)
        $modeOpt = [
            ["id" => 0, "name" => "Choose"],
            ["id" => 1, "name" => "Bus"]
        ];

        // Get vendor options (vehicle owners/drivers)
        $vendorSql = "SELECT DISTINCT vOwner FROM vehicle WHERE cStatus = 'A' AND vOwner IS NOT NULL AND vOwner != ''";
        $vendorRes = sql_query($vendorSql);
        
        $vendorOpt = [
            ["id" => 0, "name" => "Choose"]
        ];
        
        $vendorId = 1;
        while ($vendorRow = sql_fetch_assoc($vendorRes)) {
            $vendorOpt[] = [
                "id" => $vendorId++,
                "name" => $vendorRow['vOwner']
            ];
        }

        // Get table array with vehicle details and drivers
        $tableArrSql = "SELECT v.iVehicleID, v.vRnum, vc.iCapacity, v.vOwner
                       FROM vehicle v
                       LEFT JOIN vehicle_category vc ON v.iCatID = vc.iVCatID AND vc.cStatus = 'A'
                       WHERE v.cStatus = 'A'
                       ORDER BY v.vRnum";
        $tableArrRes = sql_query($tableArrSql);
        
        $tableArr = [];
        
        while ($tableRow = sql_fetch_assoc($tableArrRes)) {
            // Get drivers for this vehicle (assuming there's a driver table or field)
            $driversSql = "SELECT vOwner as drName FROM vehicle WHERE iVehicleID = " . $tableRow['iVehicleID'];
            $driversRes = sql_query($driversSql);
            
            $vhDriver = [];
            $driverId = 1;
            while ($driverRow = sql_fetch_assoc($driversRes)) {
                if (!empty($driverRow['drName'])) {
                    $vhDriver[] = [
                        "id" => $driverId++,
                        "drName" => $driverRow['drName'],
                        "active" => "A"
                    ];
                }
            }
            
            // If no drivers found, add a default one
            if (empty($vhDriver) && !empty($tableRow['vOwner'])) {
                $vhDriver[] = [
                    "id" => 1,
                    "drName" => $tableRow['vOwner'],
                    "active" => "A"
                ];
            }

            $tableArr[] = [
                "id" => (int) $tableRow['iVehicleID'],
                "vhNum" => $tableRow['vRnum'] ?? '',
                "vhCap" => (int) ($tableRow['iCapacity'] ?? 0),
                "vhOwner" => $tableRow['vOwner'] ?? '',
                "vhDriver" => $vhDriver
            ];
        }

        echo json_encode([
            "data" => [
                "vehiOpt" => $vehiOpt,
                "modeOpt" => $modeOpt,
                "vendorOpt" => $vendorOpt,
                "tableArr" => $tableArr
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== DEFAULT =====================
    default:
        echo json_encode([
            "error" => [
                "message" => "Invalid mode parameter"
            ],
            "statusCode" => 400
        ]);
        break;
}