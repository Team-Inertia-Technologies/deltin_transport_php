<?php
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

include "../../includes/common_api.php";
include "../api_common.php";
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

$FT_RATECHART_TYPE = [];
$typeRes = sql_query("SELECT iVenChartType, vName FROM ft_ratechart_type WHERE cStatus = 'A' ORDER BY iRank, vName");
while ($row = sql_fetch_assoc($typeRes)) {
    $typeId = intval($row['iVenChartType']);
    if ($typeId <= 0) {
        continue;
    }
    $FT_RATECHART_TYPE[$typeId] = db_output2($row['vName']);
}

function ft_rate_date($value) {
    $raw = trim((string)($value ?? ''));
    if ($raw === '' || substr($raw, 0, 10) === '0000-00-00') {
        return null;
    }
    return substr($raw, 0, 10);
}

function ft_rate_number($rateVal) {
    if ($rateVal === null || $rateVal === '') {
        return null;
    }
    $rateNum = (float)$rateVal;
    return (abs($rateNum - round($rateNum)) < 0.00001) ? (int)round($rateNum) : $rateNum;
}

function ft_rate_chart_sql() {
    global $FT_RATECHART_TYPE;
    $chart = trim((string)($_REQUEST['chart'] ?? ($_REQUEST['chartId'] ?? '')));
    if ($chart === '' || !isset($FT_RATECHART_TYPE[$chart])) {
        echo json_encode([
            "error" => ["message" => "chart must be one of: " . implode(', ', array_keys($FT_RATECHART_TYPE))],
            "statusCode" => 400
        ]);
        exit;
    }
    return intval($chart);
}

function ft_rate_entries($chartSql) {
    $res = sql_query("SELECT dEffectiveFrom, dEffectiveTo
                      FROM ft_ratechart_location_dat
                      WHERE cStatus = 'A' AND iVenChartType = $chartSql");
    $ranges = [];
    while ($row = sql_fetch_assoc($res)) {
        $fromDate = ft_rate_date($row['dEffectiveFrom']);
        $toDate = ft_rate_date($row['dEffectiveTo']);
        $key = ($fromDate ?? '') . '|' . ($toDate ?? '');
        if (!isset($ranges[$key])) {
            $ranges[$key] = [
                'fromDate' => $fromDate,
                'toDate' => $toDate
            ];
        }
    }
    ksort($ranges);
    $entries = [];
    $entryId = 1;
    foreach ($ranges as $range) {
        $entries[] = [
            'entryId' => $entryId,
            'fromDate' => $range['fromDate'],
            'toDate' => $range['toDate']
        ];
        $entryId++;
    }
    return array_reverse($entries);
}

function ft_rate_catalog($chartSql) {
    $vehiCatArr = [];
    $knownCats = [];
    $catRes = sql_query("SELECT iVCatID, vName FROM vehicle_category WHERE cStatus = 'A' AND cRateChartApplicable = 'Y' ORDER BY iRank, vName");
    while ($row = sql_fetch_assoc($catRes)) {
        $catId = intval($row['iVCatID']);
        $catName = db_output2($row['vName']);
        if ($catId <= 0 || $catName === '') {
            continue;
        }
        $vehiCatArr[] = ['id' => $catId, 'name' => $catName];
        $knownCats[$catId] = true;
    }

    $extraCatRes = sql_query("SELECT DISTINCT d.iVCatID, vc.vName AS catName
                              FROM ft_ratechart_location_dat d
                              LEFT JOIN vehicle_category vc ON vc.iVCatID = d.iVCatID AND vc.cStatus = 'A' AND vc.cRateChartApplicable = 'Y'
                              WHERE d.cStatus = 'A' AND d.iVenChartType = $chartSql AND d.iRateChartLocID > 0 AND d.iVCatID > 0");
    while ($row = sql_fetch_assoc($extraCatRes)) {
        $catId = intval($row['iVCatID']);
        if ($catId <= 0 || isset($knownCats[$catId])) {
            continue;
        }
        $catName = db_output2($row['catName'] ?? '');
        if ($catName === '') {
            continue;
        }
        $vehiCatArr[] = ['id' => $catId, 'name' => $catName];
        $knownCats[$catId] = true;
    }

    $locArr = [];
    $locRes = sql_query("SELECT iRateChartLocID, vName FROM ft_ratechart_location WHERE cStatus = 'A' AND iVenChartType = $chartSql ORDER BY iRank, vName");
    while ($row = sql_fetch_assoc($locRes)) {
        $locId = intval($row['iRateChartLocID']);
        if ($locId <= 0) {
            continue;
        }
        $locArr[] = ['id' => $locId, 'name' => db_output2($row['vName'])];
    }

    return [$vehiCatArr, $locArr];
}

function ft_rate_group_key($fromRaw, $toRaw) {
    $fromDate = ft_rate_date($fromRaw);
    $toDate = ft_rate_date($toRaw);
    return [($fromDate ?? '') . '|' . ($toDate ?? ''), $fromDate, $toDate];
}

function ft_rate_headers($chartSql) {
    $chartSql = intval($chartSql);
    $res = sql_query("SELECT iVCatID, fRate, dEffectiveFrom, dEffectiveTo
                      FROM ft_ratechart_location_dat
                      WHERE cStatus = 'A' AND iVenChartType = $chartSql AND iRateChartLocID = 0
                      ORDER BY iRCLDat_ID");
    $buckets = [];
    while ($row = sql_fetch_assoc($res)) {
        list($key, $fromDate, $toDate) = ft_rate_group_key($row['dEffectiveFrom'], $row['dEffectiveTo']);
        if (!isset($buckets[$key])) {
            $buckets[$key] = ['fromDate' => $fromDate, 'toDate' => $toDate, 'rows' => []];
        }
        $buckets[$key]['rows'][] = $row;
    }

    $headers = [];
    foreach ($buckets as $key => $bucket) {
        $night = [];
        $blankCat = null;
        $blankCount = 0;
        foreach ($bucket['rows'] as $row) {
            $catId = intval($row['iVCatID']);
            if ($catId <= 0) {
                continue;
            }
            $charge = $row['fRate'];
            if ($charge !== null && $charge !== '') {
                $night[$catId] = ft_rate_number($charge);
            } else {
                $blankCat = $catId;
                $blankCount++;
            }
        }
        $vehiCatId = null;
        if ($blankCount > 0 && $night === []) {
            $vehiCatId = $blankCat;
        } elseif ($blankCount === 1) {
            $vehiCatId = $blankCat;
        } elseif (count($bucket['rows']) === 1) {
            $vehiCatId = intval($bucket['rows'][0]['iVCatID']);
        }
        ksort($night);
        $nightCharges = [];
        foreach ($night as $catId => $amount) {
            $nightCharges[] = ['vehiCatId' => $catId, 'nightCharge' => $amount];
        }
        $headers[$key] = [
            'fromDate' => $bucket['fromDate'],
            'toDate' => $bucket['toDate'],
            'vehiCatId' => $vehiCatId,
            'nightCharges' => $nightCharges
        ];
    }
    return $headers;
}

function ft_rate_merge_headers(&$groups, $chartSql, $knownCats) {
    foreach (ft_rate_headers($chartSql) as $groupKey => $header) {
        $vehiCatId = $header['vehiCatId'];
        if ($vehiCatId !== null && !isset($knownCats[$vehiCatId])) {
            $vehiCatId = null;
        }
        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'fromDate' => $header['fromDate'],
                'toDate' => $header['toDate'],
                'vehiCatId' => $vehiCatId,
                'nightCharges' => $header['nightCharges'],
                'cells' => []
            ];
            continue;
        }
        $groups[$groupKey]['vehiCatId'] = $vehiCatId;
        $groups[$groupKey]['nightCharges'] = $header['nightCharges'];
    }
}

function ft_rate_parse_nights($catById) {
    if (!array_key_exists('nightCharges', $_REQUEST) || $_REQUEST['nightCharges'] === null) {
        return [];
    }
    $raw = $_REQUEST['nightCharges'];
    if (!is_array($raw)) {
        echo json_encode([
            "error" => ["message" => "nightCharges must be a list"],
            "statusCode" => 400
        ]);
        exit;
    }
    $nightCharges = [];
    $seenCat = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            echo json_encode([
                "error" => ["message" => "Each night charge must be an object"],
                "statusCode" => 400
            ]);
            exit;
        }
        $catId = intval($row['vehiCatId'] ?? 0);
        if ($catId <= 0 || !isset($catById[$catId])) {
            echo json_encode([
                "error" => ["message" => "Night charge vehiCatId is invalid"],
                "statusCode" => 400
            ]);
            exit;
        }
        if (isset($seenCat[$catId])) {
            echo json_encode([
                "error" => ["message" => "Night charge for vehicle category $catId is repeated"],
                "statusCode" => 400
            ]);
            exit;
        }
        $seenCat[$catId] = true;
        $value = $row['nightCharge'] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $parsed = null;
        } elseif (!is_numeric($value) || (float)$value < 0) {
            echo json_encode([
                "error" => ["message" => "Night charge for vehicle category $catId must be 0 or more"],
                "statusCode" => 400
            ]);
            exit;
        } else {
            $parsed = ft_rate_number($value);
        }
        $nightCharges[] = ['catId' => $catId, 'charge' => $parsed];
    }
    return $nightCharges;
}

function ft_rate_save_nights($chartSql, $fromSql, $toSql, $nightCharges, $vehiCatId) {
    foreach ($nightCharges as $night) {
        $catId = intval($night['catId']);
        $existing = sql_query("SELECT iRCLDat_ID FROM ft_ratechart_location_dat
                               WHERE iVenChartType = $chartSql AND iRateChartLocID = 0 AND iVCatID = $catId
                               AND dEffectiveFrom = '$fromSql' AND dEffectiveTo = '$toSql'
                               ORDER BY (cStatus = 'A') DESC, iRCLDat_ID DESC LIMIT 1");
        if ($night['charge'] === null) {
            if (sql_num_rows($existing) > 0) {
                $existingRow = sql_fetch_assoc($existing);
                $datId = intval($existingRow['iRCLDat_ID']);
                if (intval($vehiCatId) === $catId) {
                    sql_query("UPDATE ft_ratechart_location_dat SET fRate = NULL, cStatus = 'A' WHERE iRCLDat_ID = $datId");
                } else {
                    sql_query("UPDATE ft_ratechart_location_dat SET cStatus = 'X', fRate = NULL WHERE iRCLDat_ID = $datId");
                }
            }
            continue;
        }
        $chargeSql = $night['charge'];
        if (sql_num_rows($existing) > 0) {
            $existingRow = sql_fetch_assoc($existing);
            $datId = intval($existingRow['iRCLDat_ID']);
            sql_query("UPDATE ft_ratechart_location_dat
                       SET fRate = $chargeSql, cStatus = 'A'
                       WHERE iRCLDat_ID = $datId");
            continue;
        }
        $datId = NextID('iRCLDat_ID', 'ft_ratechart_location_dat');
        sql_query("INSERT INTO ft_ratechart_location_dat
                      (iRCLDat_ID, iRateChartLocID, iVenChartType, iVCatID, fRate, dEffectiveFrom, dEffectiveTo, cStatus)
                   VALUES ($datId, 0, $chartSql, $catId, $chargeSql, '$fromSql', '$toSql', 'A')");
    }
}

switch ($mode) {

    // ===================== CASE: LIST =====================
    case 'LIST':
        $chartArr = [];
        foreach ($FT_RATECHART_TYPE as $id => $name) {
            $chartArr[] = ['id' => $id, 'name' => $name];
        }

        echo json_encode([
            "data" => [
                "chartArr" => $chartArr
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: ONLOAD =====================
    case 'ONLOAD':
        $chartSql = ft_rate_chart_sql();

        $chartArr = [];
        foreach ($FT_RATECHART_TYPE as $id => $name) {
            $chartArr[] = ['id' => $id, 'name' => $name];
        }
        list($vehiCatArr, $locArr) = ft_rate_catalog($chartSql);

        echo json_encode([
            "data" => [
                "chartArr" => $chartArr,
                "vehiCatArr" => $vehiCatArr,
                "locArr" => $locArr,
                "entries" => ft_rate_entries($chartSql)
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: VIEW =====================
    case 'VIEW':
        $chartSql = ft_rate_chart_sql();
        $entryId = intval($_REQUEST['entryId'] ?? 0);
        if ($entryId <= 0) {
            echo json_encode([
                "error" => ["message" => "entryId is required"],
                "statusCode" => 400
            ]);
            exit;
        }

        $entry = null;
        foreach (ft_rate_entries($chartSql) as $row) {
            if (intval($row['entryId']) === $entryId) {
                $entry = $row;
                break;
            }
        }
        if ($entry === null) {
            echo json_encode([
                "error" => ["message" => "Entry not found"],
                "statusCode" => 404
            ]);
            exit;
        }

        list($vehiCatArr, $locArr) = ft_rate_catalog($chartSql);
        $fromDate = $entry['fromDate'];
        $toDate = $entry['toDate'];
        if ($fromDate === null) {
            $fromCond = "(d.dEffectiveFrom IS NULL OR d.dEffectiveFrom = '' OR d.dEffectiveFrom = '0000-00-00')";
        } else {
            $fromCond = "DATE(d.dEffectiveFrom) = '" . db_input($fromDate) . "'";
        }
        if ($toDate === null) {
            $toCond = "(d.dEffectiveTo IS NULL OR d.dEffectiveTo = '' OR d.dEffectiveTo = '0000-00-00')";
        } else {
            $toCond = "DATE(d.dEffectiveTo) = '" . db_input($toDate) . "'";
        }

        $cells = [];
        $rateRes = sql_query("SELECT d.iRateChartLocID, d.iVCatID, d.fRate
                              FROM ft_ratechart_location_dat d
                              INNER JOIN ft_ratechart_location l ON l.iRateChartLocID = d.iRateChartLocID AND l.cStatus = 'A' AND l.iVenChartType = $chartSql
                              WHERE d.cStatus = 'A' AND d.iVenChartType = $chartSql AND d.iRateChartLocID > 0
                              AND $fromCond AND $toCond");
        while ($row = sql_fetch_assoc($rateRes)) {
            $locId = intval($row['iRateChartLocID']);
            $catId = intval($row['iVCatID']);
            if ($locId <= 0 || $catId <= 0) {
                continue;
            }
            $cells[$locId][$catId] = ft_rate_number($row['fRate']);
        }

        $rates = [];
        foreach ($locArr as $loc) {
            $rateRow = ['LOCATION' => $loc['id']];
            foreach ($vehiCatArr as $cat) {
                $rateRow[(string)$cat['id']] = $cells[$loc['id']][$cat['id']] ?? null;
            }
            $rates[] = $rateRow;
        }

        $headerKey = ($fromDate ?? '') . '|' . ($toDate ?? '');
        $headers = ft_rate_headers($chartSql);

        echo json_encode([
            "data" => [
                "entryId" => $entry['entryId'],
                "fromDate" => $fromDate,
                "toDate" => $toDate,
                "nightCharges" => $headers[$headerKey]['nightCharges'] ?? [],
                "rates" => $rates
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: ADD =====================
    case 'ADD':
        $chartSql = ft_rate_chart_sql();
        $rawEntryId = $_REQUEST['entryId'] ?? null;
        if ($rawEntryId !== null && $rawEntryId !== '' && intval($rawEntryId) > 0) {
            echo json_encode([
                "error" => ["message" => "entryId must be null for ADD"],
                "statusCode" => 400
            ]);
            exit;
        }

        $fromDate = trim((string)($_REQUEST['fromDate'] ?? ''));
        $toDate = trim((string)($_REQUEST['toDate'] ?? ''));
        $fromDt = DateTime::createFromFormat('!Y-m-d', $fromDate);
        $toDt = DateTime::createFromFormat('!Y-m-d', $toDate);
        if (!$fromDt || $fromDt->format('Y-m-d') !== $fromDate || !$toDt || $toDt->format('Y-m-d') !== $toDate) {
            echo json_encode([
                "error" => ["message" => "fromDate and toDate are required in YYYY-MM-DD format"],
                "statusCode" => 400
            ]);
            exit;
        }
        if ($fromDate > $toDate) {
            echo json_encode([
                "error" => ["message" => "fromDate cannot be after toDate"],
                "statusCode" => 400
            ]);
            exit;
        }

        $vehiCatId = 0;
        if (array_key_exists('vehiCatId', $_REQUEST) && $_REQUEST['vehiCatId'] !== null && $_REQUEST['vehiCatId'] !== '') {
            $vehiCatId = intval($_REQUEST['vehiCatId']);
            $catCheck = sql_query("SELECT iVCatID FROM vehicle_category WHERE iVCatID = $vehiCatId AND cStatus = 'A' AND cRateChartApplicable = 'Y'");
            if ($vehiCatId <= 0 || sql_num_rows($catCheck) == 0) {
                echo json_encode([
                    "error" => ["message" => "vehiCatId is invalid"],
                    "statusCode" => 400
                ]);
                exit;
            }
        }

        $catById = [];
        $catRes = sql_query("SELECT iVCatID FROM vehicle_category WHERE cStatus = 'A' AND cRateChartApplicable = 'Y'");
        while ($row = sql_fetch_assoc($catRes)) {
            $catId = intval($row['iVCatID']);
            if ($catId <= 0) {
                continue;
            }
            $catById[$catId] = true;
        }

        $locIds = [];
        $locRes = sql_query("SELECT iRateChartLocID FROM ft_ratechart_location WHERE cStatus = 'A' AND iVenChartType = $chartSql");
        while ($row = sql_fetch_assoc($locRes)) {
            $locIds[intval($row['iRateChartLocID'])] = true;
        }

        $nightCharges = ft_rate_parse_nights($catById);
        $rates = $_REQUEST['rates'] ?? null;
        $hasRates = is_array($rates) && $rates !== [];
        if (!$hasRates && $nightCharges === []) {
            echo json_encode([
                "error" => ["message" => "rates are required"],
                "statusCode" => 400
            ]);
            exit;
        }

        $writes = [];
        $seenLoc = [];
        foreach (($hasRates ? $rates : []) as $rateRow) {
            if (!is_array($rateRow)) {
                echo json_encode([
                    "error" => ["message" => "Each rate row must be an object"],
                    "statusCode" => 400
                ]);
                exit;
            }
            $locId = intval($rateRow['LOCATION'] ?? ($rateRow['location'] ?? 0));
            if ($locId <= 0 || !isset($locIds[$locId])) {
                echo json_encode([
                    "error" => ["message" => "LOCATION $locId is not on this chart"],
                    "statusCode" => 400
                ]);
                exit;
            }
            if (isset($seenLoc[$locId])) {
                echo json_encode([
                    "error" => ["message" => "LOCATION $locId is repeated"],
                    "statusCode" => 400
                ]);
                exit;
            }
            $seenLoc[$locId] = true;

            foreach ($rateRow as $key => $value) {
                if (strtoupper((string)$key) === 'LOCATION') {
                    continue;
                }
                $catId = intval($key);
                if ((string)$catId !== trim((string)$key) || !isset($catById[$catId])) {
                    echo json_encode([
                        "error" => ["message" => "Unknown vehicle category: $key"],
                        "statusCode" => 400
                    ]);
                    exit;
                }
                if ($value === null || (is_string($value) && trim($value) === '')) {
                    $parsed = null;
                } elseif (!is_numeric($value) || (float)$value < 0) {
                    echo json_encode([
                        "error" => ["message" => "Invalid rate for $key"],
                        "statusCode" => 400
                    ]);
                    exit;
                } else {
                    $rateNum = (float)$value;
                    $parsed = (abs($rateNum - round($rateNum)) < 0.00001) ? (int)round($rateNum) : $rateNum;
                }
                $writes[] = [
                    'locId' => $locId,
                    'catId' => $catId,
                    'rate' => $parsed
                ];
            }
        }
        $hasRate = false;
        foreach ($writes as $write) {
            if ($write['rate'] !== null) {
                $hasRate = true;
                break;
            }
        }
        if ($hasRates && !$hasRate) {
            echo json_encode([
                "error" => ["message" => "At least one vehicle rate is required"],
                "statusCode" => 400
            ]);
            exit;
        }

        $fromSql = db_input($fromDate);
        $toSql = db_input($toDate);
        $exactRes = sql_query("SELECT iRCLDat_ID FROM ft_ratechart_location_dat
                               WHERE cStatus = 'A' AND iVenChartType = $chartSql
                               AND dEffectiveFrom = '$fromSql' AND dEffectiveTo = '$toSql'
                               LIMIT 1");
        $exactEntry = sql_num_rows($exactRes) > 0;
        if ($exactEntry && ($nightCharges === [] || $hasRates)) {
            echo json_encode([
                "error" => ["message" => "Date range overlaps an existing rate chart entry"],
                "statusCode" => 400
            ]);
            exit;
        }
        if (!$exactEntry) {
            $overlapRes = sql_query("SELECT iRCLDat_ID FROM ft_ratechart_location_dat
                                     WHERE cStatus = 'A' AND iVenChartType = $chartSql
                                     AND dEffectiveFrom <= '$toSql'
                                     AND (dEffectiveTo IS NULL OR dEffectiveTo = '0000-00-00' OR dEffectiveTo >= '$fromSql')
                                     LIMIT 1");
            if (sql_num_rows($overlapRes) > 0) {
                echo json_encode([
                    "error" => ["message" => "Date range overlaps an existing rate chart entry"],
                    "statusCode" => 400
                ]);
                exit;
            }
        }

        if (!$exactEntry) {
        foreach ($writes as $write) {
            $locId = intval($write['locId']);
            $catId = intval($write['catId']);
            $existing = sql_query("SELECT iRCLDat_ID FROM ft_ratechart_location_dat
                                   WHERE iVenChartType = $chartSql AND iRateChartLocID = $locId AND iVCatID = $catId
                                   AND dEffectiveFrom = '$fromSql' AND dEffectiveTo = '$toSql'
                                   ORDER BY (cStatus = 'A') DESC, iRCLDat_ID DESC LIMIT 1");
            if ($write['rate'] === null) {
                if (sql_num_rows($existing) > 0) {
                    $existingRow = sql_fetch_assoc($existing);
                    $datId = intval($existingRow['iRCLDat_ID']);
                    sql_query("UPDATE ft_ratechart_location_dat SET cStatus = 'X', fRate = NULL WHERE iRCLDat_ID = $datId");
                }
                continue;
            }
            if (sql_num_rows($existing) > 0) {
                $existingRow = sql_fetch_assoc($existing);
                $datId = intval($existingRow['iRCLDat_ID']);
                sql_query("UPDATE ft_ratechart_location_dat SET fRate = " . $write['rate'] . ", cStatus = 'A' WHERE iRCLDat_ID = $datId");
                continue;
            }
            $datId = NextID('iRCLDat_ID', 'ft_ratechart_location_dat');
            sql_query("INSERT INTO ft_ratechart_location_dat
                          (iRCLDat_ID, iRateChartLocID, iVenChartType, iVCatID, fRate, dEffectiveFrom, dEffectiveTo, cStatus)
                       VALUES ($datId, $locId, $chartSql, $catId, " . $write['rate'] . ", '$fromSql', '$toSql', 'A')");
        }

        if ($vehiCatId > 0) {
            $marker = sql_query("SELECT iRCLDat_ID FROM ft_ratechart_location_dat
                                 WHERE iVenChartType = $chartSql AND iRateChartLocID = 0 AND iVCatID = $vehiCatId
                                 AND dEffectiveFrom = '$fromSql' AND dEffectiveTo = '$toSql'
                                 ORDER BY (cStatus = 'A') DESC, iRCLDat_ID DESC LIMIT 1");
            if (sql_num_rows($marker) == 0) {
                $marker = sql_query("SELECT iRCLDat_ID FROM ft_ratechart_location_dat
                                     WHERE iVenChartType = $chartSql AND iRateChartLocID = 0
                                     AND (fRate IS NULL)
                                     AND dEffectiveFrom = '$fromSql' AND dEffectiveTo = '$toSql'
                                     ORDER BY (cStatus = 'A') DESC, iRCLDat_ID DESC LIMIT 1");
            }
            if (sql_num_rows($marker) > 0) {
                $markerRow = sql_fetch_assoc($marker);
                $datId = intval($markerRow['iRCLDat_ID']);
                sql_query("UPDATE ft_ratechart_location_dat SET iVCatID = $vehiCatId, fRate = NULL, cStatus = 'A' WHERE iRCLDat_ID = $datId");
            } else {
                $datId = NextID('iRCLDat_ID', 'ft_ratechart_location_dat');
                sql_query("INSERT INTO ft_ratechart_location_dat
                              (iRCLDat_ID, iRateChartLocID, iVenChartType, iVCatID, fRate, dEffectiveFrom, dEffectiveTo, cStatus)
                           VALUES ($datId, 0, $chartSql, $vehiCatId, NULL, '$fromSql', '$toSql', 'A')");
            }
        }
        }

        ft_rate_save_nights($chartSql, $fromSql, $toSql, $nightCharges, $vehiCatId);

        $newEntryId = 0;
        foreach (ft_rate_entries($chartSql) as $entry) {
            if ($entry['fromDate'] === $fromDate && $entry['toDate'] === $toDate) {
                $newEntryId = $entry['entryId'];
                break;
            }
        }

        echo json_encode([
            "data" => [
                "message" => $exactEntry ? "Night charges saved" : "Rate chart entry added successfully",
                "entryId" => $newEntryId
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: EDIT =====================
    case 'EDIT':
        $chartSql = ft_rate_chart_sql();
        $entryId = intval($_REQUEST['entryId'] ?? 0);
        if ($entryId <= 0) {
            echo json_encode([
                "error" => ["message" => "entryId is required"],
                "statusCode" => 400
            ]);
            exit;
        }

        $chartArr = [];
        foreach ($FT_RATECHART_TYPE as $id => $name) {
            $chartArr[] = ['id' => $id, 'name' => $name];
        }

        $vehiCatArr = [];
        $catRes = sql_query("SELECT iVCatID, vName FROM vehicle_category WHERE cStatus = 'A' AND cRateChartApplicable = 'Y' ORDER BY iRank, vName");
        while ($row = sql_fetch_assoc($catRes)) {
            $catId = intval($row['iVCatID']);
            $catName = db_output2($row['vName']);
            if ($catId <= 0 || $catName === '') {
                continue;
            }
            $vehiCatArr[] = ['id' => $catId, 'name' => $catName];
        }

        $locArr = [];
        $locRes = sql_query("SELECT iRateChartLocID, vName FROM ft_ratechart_location WHERE cStatus = 'A' AND iVenChartType = $chartSql ORDER BY iRank, vName");
        while ($row = sql_fetch_assoc($locRes)) {
            $locId = intval($row['iRateChartLocID']);
            if ($locId <= 0) {
                continue;
            }
            $locArr[] = ['id' => $locId, 'name' => db_output2($row['vName'])];
        }

        $knownCats = [];
        foreach ($vehiCatArr as $cat) {
            $knownCats[$cat['id']] = true;
        }

        $rateRes = sql_query("SELECT d.iRateChartLocID, d.iVCatID, d.fRate, d.dEffectiveFrom, d.dEffectiveTo, vc.vName AS catName
                              FROM ft_ratechart_location_dat d
                              INNER JOIN ft_ratechart_location l ON l.iRateChartLocID = d.iRateChartLocID AND l.cStatus = 'A' AND l.iVenChartType = $chartSql
                              LEFT JOIN vehicle_category vc ON vc.iVCatID = d.iVCatID AND vc.cStatus = 'A' AND vc.cRateChartApplicable = 'Y'
                              WHERE d.cStatus = 'A' AND d.iVenChartType = $chartSql AND d.iRateChartLocID > 0
                              ORDER BY d.dEffectiveFrom, d.dEffectiveTo, l.iRank, d.iVCatID");
        $groups = [];
        while ($row = sql_fetch_assoc($rateRes)) {
            $fromDate = substr(trim((string)$row['dEffectiveFrom']), 0, 10);
            $toRaw = trim((string)($row['dEffectiveTo'] ?? ''));
            $toDate = ($toRaw === '' || substr($toRaw, 0, 10) === '0000-00-00') ? null : substr($toRaw, 0, 10);
            $groupKey = $fromDate . '|' . ($toDate ?? '');
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                    'vehiCatId' => null,
                    'nightCharges' => [],
                    'cells' => []
                ];
            }
            $locId = intval($row['iRateChartLocID']);
            $catId = intval($row['iVCatID']);
            if ($locId <= 0 || $catId <= 0) {
                continue;
            }
            $rateVal = $row['fRate'];
            if ($rateVal === null || $rateVal === '') {
                $rateOut = null;
            } else {
                $rateNum = (float)$rateVal;
                $rateOut = (abs($rateNum - round($rateNum)) < 0.00001) ? (int)round($rateNum) : $rateNum;
            }
            $groups[$groupKey]['cells'][$locId][$catId] = $rateOut;
            if (!isset($knownCats[$catId])) {
                $catName = db_output2($row['catName'] ?? '');
                if ($catName !== '') {
                    $vehiCatArr[] = ['id' => $catId, 'name' => $catName];
                    $knownCats[$catId] = true;
                }
            }
        }

        ft_rate_merge_headers($groups, $chartSql, $knownCats);

        ksort($groups);
        $entries = [];
        $serial = 1;
        foreach ($groups as $group) {
            $rates = [];
            foreach ($locArr as $loc) {
                $rateRow = ['LOCATION' => $loc['id']];
                foreach ($vehiCatArr as $cat) {
                    $rateRow[(string)$cat['id']] = $group['cells'][$loc['id']][$cat['id']] ?? null;
                }
                $rates[] = $rateRow;
            }
            $entries[] = [
                'entryId' => $serial,
                'fromDate' => $group['fromDate'],
                'toDate' => $group['toDate'],
                'vehiCatId' => $group['vehiCatId'],
                'nightCharges' => $group['nightCharges'] ?? [],
                'rates' => $rates
            ];
            $serial++;
        }

        $entry = null;
        foreach ($entries as $row) {
            if (intval($row['entryId']) === $entryId) {
                $entry = $row;
                break;
            }
        }
        if ($entry === null) {
            echo json_encode([
                "error" => ["message" => "Entry not found"],
                "statusCode" => 404
            ]);
            exit;
        }

        echo json_encode([
            "data" => [
                "entry" => $entry,
                "chartArr" => $chartArr,
                "vehiCatArr" => $vehiCatArr,
                "locArr" => $locArr
            ],
            "statusCode" => 200
        ]);
        break;

    // ===================== CASE: UPDATE_RATE =====================
    case 'UPDATE_RATE':
        $chartSql = ft_rate_chart_sql();
        $locId = intval($_REQUEST['locId'] ?? 0);
        $catId = intval($_REQUEST['vehiCatId'] ?? 0);
        if ($locId <= 0) {
            echo json_encode([
                "error" => ["message" => "locId is required"],
                "statusCode" => 400
            ]);
            exit;
        }
        if ($catId <= 0) {
            echo json_encode([
                "error" => ["message" => "vehiCatId is required"],
                "statusCode" => 400
            ]);
            exit;
        }

        $locCheck = sql_query("SELECT iRateChartLocID FROM ft_ratechart_location WHERE iRateChartLocID = $locId AND cStatus = 'A' AND iVenChartType = $chartSql");
        if (sql_num_rows($locCheck) == 0) {
            echo json_encode([
                "error" => ["message" => "locId is not on this chart"],
                "statusCode" => 400
            ]);
            exit;
        }

        $catRes = sql_query("SELECT iVCatID FROM vehicle_category WHERE iVCatID = $catId AND cStatus = 'A' AND cRateChartApplicable = 'Y' LIMIT 1");
        if (sql_num_rows($catRes) == 0) {
            echo json_encode([
                "error" => ["message" => "vehiCatId is invalid"],
                "statusCode" => 400
            ]);
            exit;
        }

        $updatedRate = $_REQUEST['updatedRate'] ?? null;
        if ($updatedRate === null || $updatedRate === '' || !is_numeric($updatedRate) || (float)$updatedRate < 0) {
            echo json_encode([
                "error" => ["message" => "updatedRate must be a number"],
                "statusCode" => 400
            ]);
            exit;
        }
        $updatedNum = (float)$updatedRate;
        $updatedSql = (abs($updatedNum - round($updatedNum)) < 0.00001) ? (int)round($updatedNum) : $updatedNum;

        $filterByRate = array_key_exists('rate', $_REQUEST) && $_REQUEST['rate'] !== null && $_REQUEST['rate'] !== '';
        $oldRate = null;
        if ($filterByRate) {
            if (!is_numeric($_REQUEST['rate']) || (float)$_REQUEST['rate'] < 0) {
                echo json_encode([
                    "error" => ["message" => "rate must be a number"],
                    "statusCode" => 400
                ]);
                exit;
            }
            $oldRate = (float)$_REQUEST['rate'];
        }

        $fromDate = '';
        $toDate = '';
        if (array_key_exists('fromDate', $_REQUEST) && $_REQUEST['fromDate'] !== null && $_REQUEST['fromDate'] !== '') {
            $fromDate = trim((string)$_REQUEST['fromDate']);
            $fromDt = DateTime::createFromFormat('!Y-m-d', $fromDate);
            if (!$fromDt || $fromDt->format('Y-m-d') !== $fromDate) {
                echo json_encode([
                    "error" => ["message" => "fromDate must be YYYY-MM-DD"],
                    "statusCode" => 400
                ]);
                exit;
            }
        }
        if (array_key_exists('toDate', $_REQUEST) && $_REQUEST['toDate'] !== null && $_REQUEST['toDate'] !== '') {
            $toDate = trim((string)$_REQUEST['toDate']);
            $toDt = DateTime::createFromFormat('!Y-m-d', $toDate);
            if (!$toDt || $toDt->format('Y-m-d') !== $toDate) {
                echo json_encode([
                    "error" => ["message" => "toDate must be YYYY-MM-DD"],
                    "statusCode" => 400
                ]);
                exit;
            }
        }

        $sql = "SELECT iRCLDat_ID, fRate, dEffectiveFrom, dEffectiveTo FROM ft_ratechart_location_dat
                WHERE cStatus = 'A' AND iVenChartType = $chartSql
                AND iRateChartLocID = $locId AND iVCatID = $catId";
        if ($fromDate !== '') {
            $sql .= " AND dEffectiveFrom = '" . db_input($fromDate) . "'";
        }
        if ($toDate !== '') {
            $sql .= " AND dEffectiveTo = '" . db_input($toDate) . "'";
        }
        $res = sql_query($sql);
        $matches = [];
        while ($row = sql_fetch_assoc($res)) {
            if ($filterByRate && abs((float)$row['fRate'] - $oldRate) >= 0.001) {
                continue;
            }
            $matches[] = $row;
        }
        if (count($matches) === 0) {
            echo json_encode([
                "error" => ["message" => "Rate not found"],
                "statusCode" => 404
            ]);
            exit;
        }
        if (count($matches) > 1) {
            echo json_encode([
                "error" => ["message" => "More than one rate matches. Send fromDate and toDate"],
                "statusCode" => 400
            ]);
            exit;
        }

        $oldRow = $matches[0];
        $datId = intval($oldRow['iRCLDat_ID']);
        $fromSql = db_input($oldRow['dEffectiveFrom']);
        $toRaw = $oldRow['dEffectiveTo'];
        if ($toRaw === null || $toRaw === '' || $toRaw === '0000-00-00') {
            $toSql = "NULL";
        } else {
            $toSql = "'" . db_input($toRaw) . "'";
        }

        sql_query("UPDATE ft_ratechart_location_dat SET cStatus = 'X' WHERE iRCLDat_ID = $datId AND cStatus = 'A'");
        $newId = NextID('iRCLDat_ID', 'ft_ratechart_location_dat');
        sql_query("INSERT INTO ft_ratechart_location_dat
                      (iRCLDat_ID, iRateChartLocID, iVenChartType, iVCatID, fRate, dEffectiveFrom, dEffectiveTo, cStatus)
                   VALUES ($newId, $locId, $chartSql, $catId, $updatedSql, '$fromSql', $toSql, 'A')");

        $chartArr = [];
        foreach ($FT_RATECHART_TYPE as $id => $name) {
            $chartArr[] = ['id' => $id, 'name' => $name];
        }

        $vehiCatArr = [];
        $catRes = sql_query("SELECT iVCatID, vName FROM vehicle_category WHERE cStatus = 'A' AND cRateChartApplicable = 'Y' ORDER BY iRank, vName");
        while ($row = sql_fetch_assoc($catRes)) {
            $catId = intval($row['iVCatID']);
            $catName = db_output2($row['vName']);
            if ($catId <= 0 || $catName === '') {
                continue;
            }
            $vehiCatArr[] = ['id' => $catId, 'name' => $catName];
        }

        $locArr = [];
        $locRes = sql_query("SELECT iRateChartLocID, vName FROM ft_ratechart_location WHERE cStatus = 'A' AND iVenChartType = $chartSql ORDER BY iRank, vName");
        while ($row = sql_fetch_assoc($locRes)) {
            $locId = intval($row['iRateChartLocID']);
            if ($locId <= 0) {
                continue;
            }
            $locArr[] = ['id' => $locId, 'name' => db_output2($row['vName'])];
        }

        $knownCats = [];
        foreach ($vehiCatArr as $cat) {
            $knownCats[$cat['id']] = true;
        }

        $rateRes = sql_query("SELECT d.iRateChartLocID, d.iVCatID, d.fRate, d.dEffectiveFrom, d.dEffectiveTo, vc.vName AS catName
                              FROM ft_ratechart_location_dat d
                              INNER JOIN ft_ratechart_location l ON l.iRateChartLocID = d.iRateChartLocID AND l.cStatus = 'A' AND l.iVenChartType = $chartSql
                              LEFT JOIN vehicle_category vc ON vc.iVCatID = d.iVCatID AND vc.cStatus = 'A' AND vc.cRateChartApplicable = 'Y'
                              WHERE d.cStatus = 'A' AND d.iVenChartType = $chartSql AND d.iRateChartLocID > 0
                              ORDER BY d.dEffectiveFrom, d.dEffectiveTo, l.iRank, d.iVCatID");
        $groups = [];
        while ($row = sql_fetch_assoc($rateRes)) {
            $fromDate = substr(trim((string)$row['dEffectiveFrom']), 0, 10);
            $toRaw = trim((string)($row['dEffectiveTo'] ?? ''));
            $toDate = ($toRaw === '' || substr($toRaw, 0, 10) === '0000-00-00') ? null : substr($toRaw, 0, 10);
            $groupKey = $fromDate . '|' . ($toDate ?? '');
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                    'vehiCatId' => null,
                    'nightCharges' => [],
                    'cells' => []
                ];
            }
            $locId = intval($row['iRateChartLocID']);
            $catId = intval($row['iVCatID']);
            if ($locId <= 0 || $catId <= 0) {
                continue;
            }
            $rateVal = $row['fRate'];
            if ($rateVal === null || $rateVal === '') {
                $rateOut = null;
            } else {
                $rateNum = (float)$rateVal;
                $rateOut = (abs($rateNum - round($rateNum)) < 0.00001) ? (int)round($rateNum) : $rateNum;
            }
            $groups[$groupKey]['cells'][$locId][$catId] = $rateOut;
            if (!isset($knownCats[$catId])) {
                $catName = db_output2($row['catName'] ?? '');
                if ($catName !== '') {
                    $vehiCatArr[] = ['id' => $catId, 'name' => $catName];
                    $knownCats[$catId] = true;
                }
            }
        }

        ft_rate_merge_headers($groups, $chartSql, $knownCats);

        ksort($groups);
        $entries = [];
        $entryId = 1;
        foreach ($groups as $group) {
            $rates = [];
            foreach ($locArr as $loc) {
                $rateRow = ['LOCATION' => $loc['id']];
                foreach ($vehiCatArr as $cat) {
                    $rateRow[(string)$cat['id']] = $group['cells'][$loc['id']][$cat['id']] ?? null;
                }
                $rates[] = $rateRow;
            }
            $entries[] = [
                'entryId' => $entryId,
                'fromDate' => $group['fromDate'],
                'toDate' => $group['toDate'],
                'vehiCatId' => $group['vehiCatId'],
                'nightCharges' => $group['nightCharges'] ?? [],
                'rates' => $rates
            ];
            $entryId++;
        }

        echo json_encode([
            "data" => [
                "message" => "Rate chart loaded successfully",
                "chartArr" => $chartArr,
                "vehiCatArr" => $vehiCatArr,
                "locArr" => $locArr,
                "entries" => $entries
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
