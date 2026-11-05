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
    disposal_fail("User not found or inactive", 401);
}

switch ($mode) {

    case 'VIEW':
        $typeFilter = '';
        if (array_key_exists('chartType', $_REQUEST) && $_REQUEST['chartType'] !== null && $_REQUEST['chartType'] !== '') {
            $filterType = intval($_REQUEST['chartType']);
            if ($filterType <= 0) {
                disposal_fail("chartType is invalid", 400);
            }
            $typeFilter = " AND c.iVenChartType = $filterType";
        }

        $charts = [];
        $res = sql_query("SELECT c.iRateChartID, c.iVehicleCategoryID, vc.vName AS catName,
                                 c.vChartName, c.iVenChartType, t.vName AS typeName,
                                 c.dEffectiveFrom, c.dEffectiveTo, c.cStatus,
                                 k.fIncludedKM, k.fAdditionalKMRate,
                                 (SELECT h.fRate FROM disposal_hourly_rate h
                                  WHERE h.iRateChartID = c.iRateChartID AND h.iHourFrom = 1
                                  ORDER BY h.iHourlyRateID LIMIT 1) AS firstHourRate
                          FROM disposal_rate_chart c
                          LEFT JOIN vehicle_category vc ON vc.iVCatID = c.iVehicleCategoryID
                          LEFT JOIN ft_ratechart_type t ON t.iVenChartType = c.iVenChartType
                          LEFT JOIN disposal_km_rate k ON k.iRateChartID = c.iRateChartID
                          WHERE c.cStatus IN ('A', 'I') $typeFilter
                          ORDER BY c.dEffectiveFrom DESC, c.iRateChartID DESC");
        while ($row = sql_fetch_assoc($res)) {
            $charts[] = disposal_chart_summary($row);
        }

        echo json_encode([
            "data" => [
                "charts" => $charts,
                "chartTypes" => disposal_chart_types(),
                "vehicleCategories" => disposal_categories()
            ],
            "statusCode" => 200
        ]);
        break;

    case 'EDIT':
        $chartId = intval($_REQUEST['rateChartId'] ?? ($_REQUEST['id'] ?? 0));
        if ($chartId <= 0) {
            disposal_fail("rateChartId is required", 400);
        }
        $chart = disposal_chart($chartId);
        if ($chart === null) {
            disposal_fail("Rate chart not found", 404);
        }

        echo json_encode([
            "data" => [
                "chart" => $chart,
                "chartTypes" => disposal_chart_types(),
                "vehicleCategories" => disposal_categories()
            ],
            "statusCode" => 200
        ]);
        break;

    case 'ADD':
        $parsed = disposal_input(0);
        sql_query("START TRANSACTION");
        $chartId = NextID('iRateChartID', 'disposal_rate_chart');
        $nameSql = db_input($parsed['chartName']);
        $toSql = $parsed['toDate'] === null ? "NULL" : "'" . db_input($parsed['toDate']) . "'";
        $nowSql = db_input(NOW);
        sql_query("INSERT INTO disposal_rate_chart
                      (iRateChartID, iVehicleCategoryID, vChartName, iVenChartType, dEffectiveFrom, dEffectiveTo, dtCreated, cStatus)
                   VALUES ($chartId, {$parsed['vehicleCategoryId']}, '$nameSql', {$parsed['chartType']}, '{$parsed['fromDate']}', $toSql, '$nowSql', 'A')");
        disposal_replace_children($chartId, $parsed);
        sql_query("COMMIT");

        echo json_encode([
            "data" => [
                "message" => "Disposal rate chart added successfully",
                "chart" => disposal_chart($chartId)
            ],
            "statusCode" => 200
        ]);
        break;

    case 'UPDATE':
        $chartId = intval($_REQUEST['rateChartId'] ?? ($_REQUEST['id'] ?? 0));
        if ($chartId <= 0) {
            disposal_fail("rateChartId is required", 400);
        }
        $existing = sql_query("SELECT iRateChartID, cStatus FROM disposal_rate_chart WHERE iRateChartID = $chartId AND cStatus IN ('A', 'I')");
        if (sql_num_rows($existing) == 0) {
            disposal_fail("Rate chart not found", 404);
        }
        $parsed = disposal_input($chartId);
        $nameSql = db_input($parsed['chartName']);
        $toSql = $parsed['toDate'] === null ? "NULL" : "'" . db_input($parsed['toDate']) . "'";
        $statusSql = db_input($parsed['status']);
        $nowSql = db_input(NOW);

        sql_query("START TRANSACTION");
        sql_query("UPDATE disposal_rate_chart
                   SET iVehicleCategoryID = {$parsed['vehicleCategoryId']},
                       vChartName = '$nameSql',
                       iVenChartType = {$parsed['chartType']},
                       dEffectiveFrom = '{$parsed['fromDate']}',
                       dEffectiveTo = $toSql,
                       cStatus = '$statusSql',
                       dtUpdated = '$nowSql'
                   WHERE iRateChartID = $chartId");
        disposal_replace_children($chartId, $parsed);
        sql_query("COMMIT");

        echo json_encode([
            "data" => [
                "message" => "Disposal rate chart updated successfully",
                "chart" => disposal_chart($chartId)
            ],
            "statusCode" => 200
        ]);
        break;

    default:
        disposal_fail("mode must be VIEW, EDIT, ADD, or UPDATE", 400);
}

function disposal_fail($message, $code) {
    echo json_encode([
        "error" => ["message" => $message],
        "statusCode" => $code
    ]);
    exit;
}

function disposal_date_out($value) {
    $raw = trim((string)($value ?? ''));
    if ($raw === '' || substr($raw, 0, 10) === '0000-00-00') {
        return null;
    }
    return substr($raw, 0, 10);
}

function disposal_number($value) {
    if ($value === null || $value === '') {
        return null;
    }
    $number = (float)$value;
    return (abs($number - round($number)) < 0.00001) ? (int)round($number) : $number;
}

function disposal_chart_types() {
    $types = [];
    $res = sql_query("SELECT iVenChartType, vName FROM ft_ratechart_type WHERE cStatus = 'A' ORDER BY iRank, vName");
    while ($row = sql_fetch_assoc($res)) {
        $typeId = intval($row['iVenChartType']);
        $name = db_output2($row['vName']);
        if ($typeId <= 0 || $name === '') {
            continue;
        }
        $types[] = ['id' => $typeId, 'name' => $name];
    }
    return $types;
}

function disposal_chart_summary($row) {
    $chartType = intval($row['iVenChartType'] ?? 0);
    return [
        'rateChartId' => intval($row['iRateChartID']),
        'vehicleCategoryId' => intval($row['iVehicleCategoryID']),
        'vehicleCategoryName' => db_output2($row['catName'] ?? ''),
        'chartName' => db_output2($row['vChartName']),
        'chartType' => $chartType > 0 ? $chartType : null,
        'chartTypeName' => db_output2($row['typeName'] ?? ''),
        'fromDate' => disposal_date_out($row['dEffectiveFrom']),
        'toDate' => disposal_date_out($row['dEffectiveTo']),
        'status' => $row['cStatus'],
        'firstHourRate' => disposal_number($row['firstHourRate']),
        'includedKm' => disposal_number($row['fIncludedKM']),
        'additionalKmRate' => disposal_number($row['fAdditionalKMRate'])
    ];
}

function disposal_categories() {
    $categories = [];
    $res = sql_query("SELECT iVCatID, vName FROM vehicle_category WHERE cStatus = 'A' AND cRateChartApplicable = 'Y' ORDER BY iRank, vName");
    while ($row = sql_fetch_assoc($res)) {
        $catId = intval($row['iVCatID']);
        $name = db_output2($row['vName']);
        if ($catId <= 0 || $name === '') {
            continue;
        }
        $categories[] = ['id' => $catId, 'name' => $name];
    }
    return $categories;
}

function disposal_chart($chartId) {
    $chartId = intval($chartId);
    $res = sql_query("SELECT c.iRateChartID, c.iVehicleCategoryID, vc.vName AS catName,
                             c.vChartName, c.iVenChartType, t.vName AS typeName,
                             c.dEffectiveFrom, c.dEffectiveTo,
                             c.dtCreated, c.dtUpdated, c.cStatus,
                             k.fIncludedKM, k.fAdditionalKMRate,
                             (SELECT h.fRate FROM disposal_hourly_rate h
                              WHERE h.iRateChartID = c.iRateChartID AND h.iHourFrom = 1
                              ORDER BY h.iHourlyRateID LIMIT 1) AS firstHourRate
                      FROM disposal_rate_chart c
                      LEFT JOIN vehicle_category vc ON vc.iVCatID = c.iVehicleCategoryID
                      LEFT JOIN ft_ratechart_type t ON t.iVenChartType = c.iVenChartType
                      LEFT JOIN disposal_km_rate k ON k.iRateChartID = c.iRateChartID
                      WHERE c.iRateChartID = $chartId AND c.cStatus IN ('A', 'I')");
    if (sql_num_rows($res) == 0) {
        return null;
    }
    $row = sql_fetch_assoc($res);

    $hourly = [];
    $hourRes = sql_query("SELECT iHourlyRateID, iHourFrom, iHourTo, fRate
                          FROM disposal_hourly_rate
                          WHERE iRateChartID = $chartId
                          ORDER BY iHourFrom, iHourTo");
    while ($hour = sql_fetch_assoc($hourRes)) {
        $hourly[] = [
            'hourlyRateId' => intval($hour['iHourlyRateID']),
            'hourFrom' => intval($hour['iHourFrom']),
            'hourTo' => intval($hour['iHourTo']),
            'rate' => disposal_number($hour['fRate'])
        ];
    }

    $surcharges = [];
    $surRes = sql_query("SELECT iSurchargeID, vSurchargeName, tTimeFrom, tTimeTo, fSurchargeAmount, cStatus
                         FROM disposal_time_surcharge
                         WHERE iRateChartID = $chartId AND cStatus IN ('A', 'I')
                         ORDER BY tTimeFrom, iSurchargeID");
    while ($sur = sql_fetch_assoc($surRes)) {
        $surcharges[] = [
            'surchargeId' => intval($sur['iSurchargeID']),
            'name' => db_output2($sur['vSurchargeName']),
            'timeFrom' => substr((string)$sur['tTimeFrom'], 0, 8),
            'timeTo' => substr((string)$sur['tTimeTo'], 0, 8),
            'amount' => disposal_number($sur['fSurchargeAmount']),
            'status' => $sur['cStatus']
        ];
    }

    $summary = disposal_chart_summary($row);
    $summary['createdAt'] = $row['dtCreated'];
    $summary['updatedAt'] = $row['dtUpdated'];
    $summary['hourlyRates'] = $hourly;
    $summary['surcharges'] = $surcharges;
    return $summary;
}

function disposal_input($chartId) {
    $vehicleCategoryId = intval($_REQUEST['vehicleCategoryId'] ?? 0);
    $catRes = sql_query("SELECT iVCatID FROM vehicle_category WHERE iVCatID = $vehicleCategoryId AND cStatus = 'A' AND cRateChartApplicable = 'Y'");
    if ($vehicleCategoryId <= 0 || sql_num_rows($catRes) == 0) {
        disposal_fail("vehicleCategoryId is invalid", 400);
    }

    $chartName = trim((string)($_REQUEST['chartName'] ?? ''));
    if ($chartName === '' || strlen($chartName) > 150) {
        disposal_fail("chartName is required and must be 150 characters or less", 400);
    }

    $chartType = intval($_REQUEST['chartType'] ?? 0);
    $typeRes = sql_query("SELECT iVenChartType FROM ft_ratechart_type WHERE iVenChartType = $chartType AND cStatus = 'A'");
    if ($chartType <= 0 || sql_num_rows($typeRes) == 0) {
        disposal_fail("chartType is invalid", 400);
    }

    $fromDate = disposal_date_in($_REQUEST['fromDate'] ?? '');
    if ($fromDate === null) {
        disposal_fail("fromDate is required in YYYY-MM-DD format", 400);
    }
    $toDate = null;
    if (array_key_exists('toDate', $_REQUEST) && $_REQUEST['toDate'] !== null && trim((string)$_REQUEST['toDate']) !== '') {
        $toDate = disposal_date_in($_REQUEST['toDate']);
        if ($toDate === null) {
            disposal_fail("toDate must be YYYY-MM-DD", 400);
        }
        if ($fromDate > $toDate) {
            disposal_fail("fromDate cannot be after toDate", 400);
        }
    }

    $status = 'A';
    if ($chartId > 0 && array_key_exists('status', $_REQUEST) && $_REQUEST['status'] !== null && $_REQUEST['status'] !== '') {
        $status = strtoupper(trim((string)$_REQUEST['status']));
        if ($status !== 'A' && $status !== 'I') {
            disposal_fail("status must be A or I", 400);
        }
    }

    $hourlyRaw = $_REQUEST['hourlyRates'] ?? null;
    if (!is_array($hourlyRaw) || $hourlyRaw === []) {
        disposal_fail("hourlyRates are required", 400);
    }
    $hourly = [];
    foreach ($hourlyRaw as $row) {
        if (!is_array($row)) {
            disposal_fail("Each hourly rate must be an object", 400);
        }
        $hourFrom = intval($row['hourFrom'] ?? 0);
        $hourTo = intval($row['hourTo'] ?? 0);
        if ($hourFrom < 1 || $hourFrom > 24 || $hourTo < 1 || $hourTo > 24 || $hourFrom > $hourTo) {
            disposal_fail("hourFrom and hourTo must be from 1 to 24, and hourFrom cannot be after hourTo", 400);
        }
        $rate = disposal_money($row['rate'] ?? null);
        if ($rate === null) {
            disposal_fail("Each hourly rate must be a number of 0 or more", 400);
        }
        $hourly[] = ['hourFrom' => $hourFrom, 'hourTo' => $hourTo, 'rate' => $rate];
    }
    usort($hourly, function ($a, $b) {
        return $a['hourFrom'] <=> $b['hourFrom'];
    });
    $prevTo = 0;
    foreach ($hourly as $row) {
        if ($row['hourFrom'] <= $prevTo) {
            disposal_fail("Hourly ranges cannot overlap", 400);
        }
        $prevTo = $row['hourTo'];
    }

    $includedKm = disposal_money($_REQUEST['includedKm'] ?? null);
    $additionalKmRate = disposal_money($_REQUEST['additionalKmRate'] ?? null);
    if ($includedKm === null || $additionalKmRate === null) {
        disposal_fail("includedKm and additionalKmRate are required and must be 0 or more", 400);
    }

    $surchargeRaw = $_REQUEST['surcharges'] ?? [];
    if (!is_array($surchargeRaw)) {
        disposal_fail("surcharges must be a list", 400);
    }
    $surcharges = [];
    foreach ($surchargeRaw as $row) {
        if (!is_array($row)) {
            disposal_fail("Each surcharge must be an object", 400);
        }
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '' || strlen($name) > 100) {
            disposal_fail("Surcharge name is required and must be 100 characters or less", 400);
        }
        $timeFrom = disposal_time_in($row['timeFrom'] ?? '');
        $timeTo = disposal_time_in($row['timeTo'] ?? '');
        if ($timeFrom === null || $timeTo === null) {
            disposal_fail("Surcharge timeFrom and timeTo must be HH:MM or HH:MM:SS", 400);
        }
        if ($timeFrom === $timeTo) {
            disposal_fail("Surcharge timeFrom and timeTo cannot be the same", 400);
        }
        $amount = disposal_money($row['amount'] ?? null);
        if ($amount === null) {
            disposal_fail("Surcharge amount must be a number of 0 or more", 400);
        }
        $surStatus = 'A';
        if (array_key_exists('status', $row) && $row['status'] !== null && $row['status'] !== '') {
            $surStatus = strtoupper(trim((string)$row['status']));
            if ($surStatus !== 'A' && $surStatus !== 'I') {
                disposal_fail("Surcharge status must be A or I", 400);
            }
        }
        $surcharges[] = [
            'name' => $name,
            'timeFrom' => $timeFrom,
            'timeTo' => $timeTo,
            'amount' => $amount,
            'status' => $surStatus
        ];
    }

    if ($status === 'A') {
        $toBound = $toDate === null ? '9999-12-31' : $toDate;
        $nameSql = db_input($chartName);
        $overlap = sql_query("SELECT iRateChartID FROM disposal_rate_chart
                              WHERE cStatus = 'A'
                              AND iVenChartType = $chartType
                              AND iVehicleCategoryID = $vehicleCategoryId
                              AND vChartName = '$nameSql'
                              AND iRateChartID <> " . intval($chartId) . "
                              AND dEffectiveFrom <= '$toBound'
                              AND (dEffectiveTo IS NULL OR dEffectiveTo = '0000-00-00' OR dEffectiveTo >= '$fromDate')
                              LIMIT 1");
        if (sql_num_rows($overlap) > 0) {
            disposal_fail("An active chart with this name, vehicle, and chart type already covers these dates", 400);
        }
    }

    return [
        'vehicleCategoryId' => $vehicleCategoryId,
        'chartName' => $chartName,
        'chartType' => $chartType,
        'fromDate' => $fromDate,
        'toDate' => $toDate,
        'status' => $status,
        'hourly' => $hourly,
        'includedKm' => $includedKm,
        'additionalKmRate' => $additionalKmRate,
        'surcharges' => $surcharges
    ];
}

function disposal_date_in($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('!Y-m-d', $raw);
    if (!$dt || $dt->format('Y-m-d') !== $raw) {
        return null;
    }
    return $raw;
}

function disposal_time_in($value) {
    $raw = trim((string)$value);
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $match)) {
        return null;
    }
    $hour = intval($match[1]);
    $minute = intval($match[2]);
    $second = isset($match[3]) ? intval($match[3]) : 0;
    if ($hour > 23 || $minute > 59 || $second > 59) {
        return null;
    }
    return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

function disposal_money($value) {
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }
    $number = (float)$value;
    if ($number < 0) {
        return null;
    }
    return number_format($number, 2, '.', '');
}

function disposal_replace_children($chartId, $parsed) {
    $chartId = intval($chartId);
    sql_query("DELETE FROM disposal_hourly_rate WHERE iRateChartID = $chartId");
    sql_query("DELETE FROM disposal_km_rate WHERE iRateChartID = $chartId");
    sql_query("DELETE FROM disposal_time_surcharge WHERE iRateChartID = $chartId");

    foreach ($parsed['hourly'] as $row) {
        $id = NextID('iHourlyRateID', 'disposal_hourly_rate');
        sql_query("INSERT INTO disposal_hourly_rate (iHourlyRateID, iRateChartID, iHourFrom, iHourTo, fRate)
                   VALUES ($id, $chartId, {$row['hourFrom']}, {$row['hourTo']}, {$row['rate']})");
    }

    $kmId = NextID('iKMRateID', 'disposal_km_rate');
    sql_query("INSERT INTO disposal_km_rate (iKMRateID, iRateChartID, fIncludedKM, fAdditionalKMRate)
               VALUES ($kmId, $chartId, {$parsed['includedKm']}, {$parsed['additionalKmRate']})");

    foreach ($parsed['surcharges'] as $row) {
        $id = NextID('iSurchargeID', 'disposal_time_surcharge');
        $name = db_input($row['name']);
        $timeFrom = db_input($row['timeFrom']);
        $timeTo = db_input($row['timeTo']);
        $status = db_input($row['status']);
        sql_query("INSERT INTO disposal_time_surcharge
                      (iSurchargeID, iRateChartID, vSurchargeName, tTimeFrom, tTimeTo, fSurchargeAmount, cStatus)
                   VALUES ($id, $chartId, '$name', '$timeFrom', '$timeTo', {$row['amount']}, '$status')");
    }
}
