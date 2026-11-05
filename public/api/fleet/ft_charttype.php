<?php
include "../../includes/common_api.php";
include "../api_common.php";
header('Content-Type: application/json');

$postdata = file_get_contents("php://input");
$request = json_decode($postdata, true);
$_REQUEST = array_merge($_REQUEST, $request ?? []);
$mode = $_REQUEST['mode'] ?? '';
$Token = $_REQUEST['token'] ?? '';
$user_id = intval(DecodeParam($Token));
$userCheckRes = sql_query("SELECT iUserID FROM users WHERE iUserID = $user_id AND cStatus = 'A'");

if (sql_num_rows($userCheckRes) == 0) {
    charttype_fail("User not found or inactive", 401);
}

switch ($mode) {

    case 'VIEW':
        $types = [];
        $res = sql_query("SELECT iVenChartType, vName, cStatus
                          FROM ft_ratechart_type
                          WHERE cStatus IN ('A', 'I')
                          ORDER BY vName");
        while ($row = sql_fetch_assoc($res)) {
            $types[] = charttype_row($row);
        }

        echo json_encode([
            "data" => ["chartTypes" => $types],
            "statusCode" => 200
        ]);
        break;

    case 'EDIT':
        $typeId = intval($_REQUEST['chartType'] ?? 0);
        if ($typeId <= 0) {
            charttype_fail("chartType is required", 400);
        }
        $res = sql_query("SELECT iVenChartType, vName, cStatus
                          FROM ft_ratechart_type
                          WHERE iVenChartType = $typeId AND cStatus IN ('A', 'I')");
        if (sql_num_rows($res) == 0) {
            charttype_fail("Chart type not found", 404);
        }

        echo json_encode([
            "data" => ["chartType" => charttype_row(sql_fetch_assoc($res))],
            "statusCode" => 200
        ]);
        break;

    case 'ADD':
        $parsed = charttype_input(0);
        sql_query("START TRANSACTION");
        $typeId = NextID('iVenChartType', 'ft_ratechart_type');
        $nameSql = db_input($parsed['name']);
        $statusSql = db_input($parsed['status']);
        sql_query("INSERT INTO ft_ratechart_type (iVenChartType, vName, iRank, cStatus)
                   VALUES ($typeId, '$nameSql', 1, '$statusSql')");
        charttype_save_locations($typeId, $parsed['locations']);
        sql_query("COMMIT");

        $saved = sql_query("SELECT iVenChartType, vName, cStatus FROM ft_ratechart_type WHERE iVenChartType = $typeId");
        echo json_encode([
            "data" => [
                "message" => "Chart type added successfully",
                "chartType" => charttype_row(sql_fetch_assoc($saved))
            ],
            "statusCode" => 200
        ]);
        break;

    case 'UPDATE':
        $typeId = intval($_REQUEST['chartType'] ?? 0);
        if ($typeId <= 0) {
            charttype_fail("chartType is required", 400);
        }
        $existing = sql_query("SELECT iVenChartType FROM ft_ratechart_type WHERE iVenChartType = $typeId AND cStatus IN ('A', 'I')");
        if (sql_num_rows($existing) == 0) {
            charttype_fail("Chart type not found", 404);
        }
        $parsed = charttype_input($typeId);
        $nameSql = db_input($parsed['name']);
        $statusSql = db_input($parsed['status']);

        sql_query("START TRANSACTION");
        sql_query("UPDATE ft_ratechart_type
                   SET vName = '$nameSql', iRank = 1, cStatus = '$statusSql'
                   WHERE iVenChartType = $typeId");
        charttype_save_locations($typeId, $parsed['locations']);
        sql_query("COMMIT");

        $saved = sql_query("SELECT iVenChartType, vName, cStatus FROM ft_ratechart_type WHERE iVenChartType = $typeId");
        echo json_encode([
            "data" => [
                "message" => "Chart type updated successfully",
                "chartType" => charttype_row(sql_fetch_assoc($saved))
            ],
            "statusCode" => 200
        ]);
        break;

    default:
        charttype_fail("mode must be VIEW, EDIT, ADD, or UPDATE", 400);
}

function charttype_fail($message, $code) {
    echo json_encode([
        "error" => ["message" => $message],
        "statusCode" => $code
    ]);
    exit;
}

function charttype_row($row) {
    $typeId = intval($row['iVenChartType']);
    return [
        'chartType' => $typeId,
        'name' => db_output2($row['vName']),
        'status' => $row['cStatus'],
        'locations' => charttype_locations($typeId)
    ];
}

function charttype_locations($typeId) {
    $typeId = intval($typeId);
    $locations = [];
    $res = sql_query("SELECT iRateChartLocID, vName, cStatus
                      FROM ft_ratechart_location
                      WHERE iVenChartType = $typeId AND cStatus IN ('A', 'I')
                      ORDER BY vName");
    while ($row = sql_fetch_assoc($res)) {
        $locations[] = [
            'locationId' => intval($row['iRateChartLocID']),
            'name' => db_output2($row['vName']),
            'status' => $row['cStatus']
        ];
    }
    return $locations;
}

function charttype_input($typeId) {
    $name = trim((string)($_REQUEST['name'] ?? ''));
    if ($name === '' || strlen($name) > 255) {
        charttype_fail("name is required and must be 255 characters or less", 400);
    }
    $nameSql = db_input($name);
    $dup = sql_query("SELECT iVenChartType FROM ft_ratechart_type
                      WHERE cStatus IN ('A', 'I')
                      AND LOWER(vName) = LOWER('$nameSql')
                      AND iVenChartType <> " . intval($typeId) . "
                      LIMIT 1");
    if (sql_num_rows($dup) > 0) {
        charttype_fail("A chart type with this name already exists", 400);
    }

    $status = 'A';
    if (array_key_exists('status', $_REQUEST) && $_REQUEST['status'] !== null && $_REQUEST['status'] !== '') {
        $status = strtoupper(trim((string)$_REQUEST['status']));
        if ($status !== 'A' && $status !== 'I') {
            charttype_fail("status must be A or I", 400);
        }
    }

    $rawLocations = $_REQUEST['locations'] ?? null;
    if (!is_array($rawLocations) || $rawLocations === []) {
        charttype_fail("locations are required", 400);
    }

    $known = [];
    if ($typeId > 0) {
        $knownRes = sql_query("SELECT iRateChartLocID FROM ft_ratechart_location
                               WHERE iVenChartType = " . intval($typeId) . " AND cStatus IN ('A', 'I')");
        while ($row = sql_fetch_assoc($knownRes)) {
            $known[intval($row['iRateChartLocID'])] = true;
        }
    }

    $locations = [];
    $seenIds = [];
    $seenNames = [];
    foreach ($rawLocations as $row) {
        if (!is_array($row)) {
            charttype_fail("Each location must be an object", 400);
        }
        $locName = trim((string)($row['name'] ?? ''));
        if ($locName === '' || strlen($locName) > 255) {
            charttype_fail("Location name is required and must be 255 characters or less", 400);
        }
        $nameKey = strtolower($locName);
        if (isset($seenNames[$nameKey])) {
            charttype_fail("Location names must be unique for this chart type", 400);
        }
        $seenNames[$nameKey] = true;

        $locationId = intval($row['locationId'] ?? ($row['id'] ?? 0));
        if ($locationId > 0) {
            if ($typeId <= 0 || !isset($known[$locationId])) {
                charttype_fail("locationId is invalid", 400);
            }
            if (isset($seenIds[$locationId])) {
                charttype_fail("locationId is repeated", 400);
            }
            $seenIds[$locationId] = true;
        }

        $locStatus = 'A';
        if (array_key_exists('status', $row) && $row['status'] !== null && $row['status'] !== '') {
            $locStatus = strtoupper(trim((string)$row['status']));
            if ($locStatus !== 'A' && $locStatus !== 'I') {
                charttype_fail("Location status must be A or I", 400);
            }
        }

        $locations[] = [
            'locationId' => $locationId,
            'name' => $locName,
            'status' => $locStatus
        ];
    }

    return [
        'name' => $name,
        'status' => $status,
        'locations' => $locations
    ];
}

function charttype_save_locations($typeId, $locations) {
    $typeId = intval($typeId);
    $kept = [];
    foreach ($locations as $row) {
        $nameSql = db_input($row['name']);
        $statusSql = db_input($row['status']);
        $locationId = intval($row['locationId']);
        if ($locationId > 0) {
            sql_query("UPDATE ft_ratechart_location
                       SET vName = '$nameSql', iRank = 1, cStatus = '$statusSql'
                       WHERE iRateChartLocID = $locationId AND iVenChartType = $typeId");
        } else {
            $locationId = NextID('iRateChartLocID', 'ft_ratechart_location');
            sql_query("INSERT INTO ft_ratechart_location
                          (iRateChartLocID, vName, iVenChartType, iRank, cStatus)
                       VALUES ($locationId, '$nameSql', $typeId, 1, '$statusSql')");
        }
        $kept[] = $locationId;
    }

    $keepSql = implode(',', $kept);
    sql_query("UPDATE ft_ratechart_location
               SET cStatus = 'X'
               WHERE iVenChartType = $typeId AND cStatus IN ('A', 'I') AND iRateChartLocID NOT IN ($keepSql)");
}
