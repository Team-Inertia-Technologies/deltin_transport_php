<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
include "../../includes/common_api.php";

header('Content-Type: application/json');

try {
    $mode = $_POST['mode'] ?? 'A';
    $txtid = $_POST['txtid'] ?? 0;

    // Validate mode
    $valid_modes = ["A", "I", "E", "U", "D", "DELPIC", "DELSIGNATURE"];
    if (!in_array($mode, $valid_modes)) {
        http_response_code(400);
        echo json_encode(['statusCode' => 400, 'message' => 'Invalid mode']);
        exit;
    }

    // Example: handle Insert mode (I)
    if ($mode === 'I') { 
        $txtid = NextID('iUserID', 'users_temp');
		$deptID = db_input($_POST['cmbdepartment']);
		$reportingTo = db_input($_POST['cmbreportingto']);
        $txtname = db_input($_POST['txtname']);
        $txtusername = db_input($_POST['txtusername']);
        $txtpassword = htmlspecialchars_decode($_POST['txtpassword']);
        $txtemail = db_input($_POST['txtemail']);
        $txtphone = db_input($_POST['txtphone']);
        $cmblevel = db_input($_POST['cmblevel']);

		// Stations are now stored via user_temp_station_assoc (iUserID, iStationID)
		$cmbstation_raw = $_POST['cmbstation'] ?? [];
		if (!is_array($cmbstation_raw)) {
			$cmbstation_raw = (trim($cmbstation_raw) !== '') ? [trim($cmbstation_raw)] : [];
		}
		$cmbstations = array_values(array_unique(array_map('intval', array_filter($cmbstation_raw, function($v) {
			return trim((string)$v) !== '';
		}))));

		// Staff / Vendor reference (mutually exclusive)
		$cmbstaff = trim($_POST['cmbstaff'] ?? '');
		$cmbvendor = trim($_POST['cmbvendor'] ?? '');

		if (!empty($cmbstaff) && !empty($cmbvendor)) {
			http_response_code(400);
			echo json_encode(['statusCode' => 400, 'message' => 'Please select either Staff or Vendor, not both']);
			exit;
		}

		$iRefIDVal = 'NULL';
		$cRefSrcTypeVal = 'NULL';
		if (!empty($cmbstaff)) {
			$iRefIDVal = intval($cmbstaff);
			$cRefSrcTypeVal = "'S'";
		} elseif (!empty($cmbvendor)) {
			$iRefIDVal = intval($cmbvendor);
			$cRefSrcTypeVal = "'V'";
		}

        $dtCreated = NOW;
        $sess_user_id = $_SESSION[PROJ_SESSION_ID]->user_id ?? 0;
		$cmbproperty = $_POST['cmbproperty2'] ?? [];

        $sql = "INSERT INTO users_temp 
                (iUserID, iDepartmentID, iReportingID, vName, vUName, vPassword, vEmail, vPhone, iLevel, cStatus, dtCreated, iCreated_UserID, cRefType, iRefID, cRefSrcType)
                VALUES 
                ($txtid, $deptID, $reportingTo, '$txtname', '$txtusername', '$txtpassword', '$txtemail', '$txtphone', $cmblevel, 'D', '$dtCreated', $sess_user_id, 'A', $iRefIDVal, $cRefSrcTypeVal)";
        sql_query($sql, 'API.USER.INSERT');
		LogMasterEdit($txtid, 'USR', $mode, $txtname);

		// Insert station associations
		foreach ($cmbstations as $stationID) {
			sql_query("INSERT INTO user_temp_station_assoc (iUserID, iStationID) VALUES ($txtid, $stationID)");
		}
		
		if (!is_array($cmbproperty)) {
			$cmbproperty = [$cmbproperty];
		}

		// if (!empty($cmbproperty)) {
		// 	foreach ($cmbproperty as $p) {
		// 		sql_query("INSERT INTO user_temp_property_assoc VALUES ($txtid, '$p')");
		// 	}
		// }

          // Fetch inserted status
		$status = GetXFromYID("SELECT cStatus FROM users_temp WHERE iUserID = $txtid");

		http_response_code(200);
		echo json_encode([
			'statusCode' => 200,
			'message' => 'User inserted successfully',
			'data' => [
				'iUserID' => $txtid,
				'Status'  => $status
			]
		]);
    
    }
	if ($mode === 'E') {
		$dataArr = GetDataFromID("users_temp", "iUserID", $txtid);
		//$dataArr2 = GetDataFromID("user_temp_property_assoc", "iUserID", $txtid);
		if (empty($dataArr) || $txtid == 0) {
			http_response_code(400);
			echo json_encode(['statusCode' => 400, 'message' => 'Invalid User ID']);
			exit;
		} 

		$user_id = db_output($dataArr[0]->iUserID);
	$txtname = db_output($dataArr[0]->vName);
	$deptID = db_output($dataArr[0]->iDepartmentID);
	$reportingTo = db_output($dataArr[0]->iReportingID);
	$txtemail = db_output($dataArr[0]->vEmail);
	$txtphone = db_output($dataArr[0]->vPhone);
	$txtusername = db_output($dataArr[0]->vUName);
	$cmblevel = db_output($dataArr[0]->iLevel);
	$txtpassword = db_output($dataArr[0]->vPassword);
	$rdstatus = db_output($dataArr[0]->cStatus);

	// Stations now come from the assoc table
	$cmbstationArr = GetXArrFromYID("SELECT iStationID FROM user_temp_station_assoc WHERE iUserID = '$txtid'");
	$cmbstationArr = !empty($cmbstationArr) ? array_map('intval', $cmbstationArr) : [];

	// Staff / Vendor reference
	$iRefID = db_output($dataArr[0]->iRefID ?? '');
	$cRefSrcType = db_output($dataArr[0]->cRefSrcType ?? '');
	$cmbstaff = ($cRefSrcType === 'S') ? $iRefID : '';
	$cmbvendor = ($cRefSrcType === 'V') ? $iRefID : '';
	
	$prevUserData = [];
	if (in_array($rdstatus, ['P', 'U'])) {
		$prevUserResult = sql_query("SELECT vName, vEmail, vPhone, vUName, iLevel FROM users WHERE iUserID = '$txtid'");
		$prevUserData = sql_fetch_assoc($prevUserResult);
	}
	$txtaction = db_output($dataArr[0]->cAction);
	$status_str = GetStatusImageString2('USERS', $rdstatus, $txtid, false);
	$txtreason = db_output($dataArr[0]->vRemark);
	//$cmbproperty2 = GetXArrFromYID('select iPropertyID from user_temp_property_assoc where iUserID=' . $txtid);

	http_response_code(200);
        echo json_encode([
            'statusCode' => 200,
            'message' => 'User Fetched successfully',
            'data' => [
				'iUserID' => $user_id,
				'DepartmentID' => $deptID,
				'DepartmenName' => GetXFromYID("SELECT vName FROM department WHERE iDepartmentID = '$deptID'"),
				'cmbstation' => $cmbstationArr,
				'ReportingTo' => $reportingTo,
				'ReportingToName' => GetXFromYID("SELECT vName FROM users WHERE iUserID = '$reportingTo'"),
				'vName' => $txtname,
				'vEmail' => $txtemail,
				'vPhone' => $txtphone,
				'vUName' => $txtusername,
				'iLevel' => $cmblevel,
				'vPassword' => $txtpassword,
				'cStatus' => $rdstatus,
				'cmbstaff' => $cmbstaff,
				'cmbvendor' => $cmbvendor,
				'prevUserData' => $prevUserData,
				'cAction' => $txtaction,
				'status_str' => $status_str,
				'vRemark' => $txtreason
				//'properties' => $cmbproperty2
			]
        ]);
        exit;
			
	}

	if ($mode === 'U') {
		if (!$txtid) {
			http_response_code(400);
			echo json_encode(['statusCode' => 400, 'message' => 'User ID missing']);
			exit;
		}
	
		$pass = '';
		$txtid = db_input($_POST['txtid']);
		$original = GetDataFromID("users_temp", "iUserID", $txtid)[0];
	
		$update_fields = [];
		$stationChanged = false;
		$fields_to_check = [
			'vName'          => 'txtname',
			'vEmail'         => 'txtemail',
			'vPhone'         => 'txtphone',
			'vUName'         => 'txtusername',
			'iDepartmentID'  => 'cmbdepartment',
			'iReportingID'   => 'cmbreportingto'
		];
	
		foreach ($fields_to_check as $db_field => $post_field) {
			$newValue = trim($_POST[$post_field] ?? '');
		
			if (in_array($db_field, ['iDepartmentID', 'iReportingID'])) {
				// Integer fields
				if ($newValue === '') {
					$update_fields[] = "$db_field = NULL";
				} elseif ($newValue != db_output($original->$db_field)) {
					$update_fields[] = "$db_field = " . intval($newValue);
				}
			} else {
				// Normal varchar fields
				if ($newValue != db_output($original->$db_field)) {
					$update_fields[] = "$db_field = '" . db_input($newValue) . "'";
				}
			}
		}

		// Handle Station IDs via assoc table (multi-value, replaces old comma-separated column)
		if (isset($_POST['cmbstation'])) {
			$newStation_raw = $_POST['cmbstation'];
			if (!is_array($newStation_raw)) {
				$newStation_raw = (trim($newStation_raw) !== '') ? [trim($newStation_raw)] : [];
			}
			$newStations = array_values(array_unique(array_map('intval', array_filter($newStation_raw, function($v) {
				return trim((string)$v) !== '';
			}))));
			sort($newStations);

			$currentStations = GetXArrFromYID("SELECT iStationID FROM user_temp_station_assoc WHERE iUserID = '$txtid'");
			$currentStations = !empty($currentStations) ? array_map('intval', $currentStations) : [];
			sort($currentStations);

			if ($newStations !== $currentStations) {
				sql_query("DELETE FROM user_temp_station_assoc WHERE iUserID = '$txtid'");
				foreach ($newStations as $stationID) {
					sql_query("INSERT INTO user_temp_station_assoc (iUserID, iStationID) VALUES ('$txtid', $stationID)");
				}
				$stationChanged = true;
			}
		}

		// Handle Staff / Vendor reference (mutually exclusive)
		if (isset($_POST['cmbstaff']) || isset($_POST['cmbvendor'])) {
			$newStaff = trim($_POST['cmbstaff'] ?? '');
			$newVendor = trim($_POST['cmbvendor'] ?? '');

			if (!empty($newStaff) && !empty($newVendor)) {
				http_response_code(400);
				echo json_encode(['statusCode' => 400, 'message' => 'Please select either Staff or Vendor, not both']);
				exit;
			}

			if (!empty($newStaff)) {
				$newRefID = intval($newStaff);
				$newRefSrcType = 'S';
			} elseif (!empty($newVendor)) {
				$newRefID = intval($newVendor);
				$newRefSrcType = 'V';
			} else {
				$newRefID = null;
				$newRefSrcType = null;
			}

			$origRefID = isset($original->iRefID) && $original->iRefID !== null ? (int)$original->iRefID : null;
			$origRefSrcType = $original->cRefSrcType ?? null;

			if ($newRefID !== $origRefID || $newRefSrcType !== $origRefSrcType) {
				$update_fields[] = "iRefID = " . ($newRefID !== null ? intval($newRefID) : "NULL");
				$update_fields[] = "cRefSrcType = " . ($newRefSrcType !== null ? "'" . db_input($newRefSrcType) . "'" : "NULL");
			}
		}
		
	
		if (!empty($_POST['txtpassword']) && htmlspecialchars_decode($_POST['txtpassword']) != db_output($original->vPassword)) {
			$update_fields[] = "vPassword = '" . htmlspecialchars_decode($_POST['txtpassword']) . "'";
		}
	
		if (db_input($_POST['cmblevel']) != db_output($original->iLevel)) {
			$update_fields[] = "iLevel = " . db_input($_POST['cmblevel']);
		}
	
		// $new_properties = $_POST['cmbproperty2'] ?? [];
		// $current_properties = GetXArrFromYID("SELECT iPropertyID FROM user_temp_property_assoc WHERE iUserID = '$txtid'");
	
		// sort($new_properties);
		// sort($current_properties);
	
		// if ($new_properties !== $current_properties) {
		// 	sql_query("DELETE FROM user_temp_property_assoc WHERE iUserID = '$txtid'");
		// 	foreach ($new_properties as $p) {
		// 		sql_query("INSERT INTO user_temp_property_assoc (iUserID, iPropertyID) VALUES ('$txtid', '$p')");
		// 	}
		// 	$update_fields[] = "cStatus = 'D'";
		// }
	
		if (!empty($update_fields) || $stationChanged) {
			if (db_output($original->cStatus) == 'A') {
				$update_fields[] = "cStatus = 'D'";
			}
			if (!empty($update_fields)) {
				$update_query = "UPDATE users_temp SET " . implode(", ", $update_fields) . " WHERE iUserID = $txtid";
				//echo $update_query;
				sql_query($update_query);
			}
		}
	
		if (isset($_POST['action'])) {
			$action = $_POST['action'];
			$dtAction = NOW;
			$txtuser = $sess_user_id;
			$txtname = $_POST['txtname'];
	
			switch ($action) {
				case 'send_for_approval':
					$txtuser= $sess_user_id;
					sql_query("UPDATE users_temp SET cAction = 'AWA', dtAction = '$dtAction', cStatus='P' WHERE iUserID = '$txtid'");
					if (!empty($txtuser)) {
						sql_query("UPDATE users_temp SET iAction_UserID = '$txtuser' WHERE iUserID = '$txtid'");
					}
					LogMasterEdit($txtid, 'USR', 'S', $txtname, "Sent for approval", $txtuser);
					break;
	
				case 'approve':
					sql_query("UPDATE users_temp SET cAction='APP', dtAction='$dtAction', cStatus='U' WHERE iUserID='$txtid'");
					LogMasterEdit($txtid, 'USR', 'AP', $txtname, "Approved by {$sess_user_name}");
					break;
	
				case 'reject':
					$reason = db_input($_POST['vRejectReason']);
					sql_query("UPDATE users_temp SET cAction='REJ', dtAction='$dtAction', vRemark='$reason', cStatus='D' WHERE iUserID='$txtid'");
					LogMasterEdit($txtid, 'USR', 'R', $txtname, "Rejected by {$sess_user_name} with reason: $reason");
					break;
	
				case 'activate':
					sql_query("UPDATE users_temp SET cStatus = 'A', cAction='ACT', iActivated_UserID = $sess_user_id WHERE iUserID = '$txtid'");
				$resultTemp = sql_query("SELECT vName, vUName, vPassword, vEmail, vPhone, iLevel, iDepartmentID, iReportingID, iRefID, cRefSrcType FROM users_temp WHERE iUserID = '$txtid'");
				$userTemp = sql_fetch_assoc($resultTemp);
	
				if ($userTemp) {
					$resultUser = sql_query("SELECT vName, vUName, vPassword, vEmail, vPhone, iLevel, iDepartmentID, iReportingID, iRefID, cRefSrcType FROM users WHERE iUserID = '$txtid'");
					$existingUser = sql_fetch_assoc($resultUser);
					$pwdDays = 0;
						$res = sql_query("SELECT vValue FROM sys_settings WHERE vCode = 'PASSWORD_ACTIVE_DAYS' LIMIT 1");
						if ($row = sql_fetch_assoc($res)) {
							$pwdDays = $row['vValue'];
						}
	
					if ($existingUser) {
						$updates = [];
						$vPassword = '';
						foreach ($userTemp as $field => $value) {
							if ($field === 'cStatus') continue;
						
							if (!isset($existingUser[$field]) || $existingUser[$field] != $value) {
								$escapedValue = addslashes($value);
								$updates[] = "$field = '$escapedValue'";
						
								if ($field === 'vPassword') {
									$vPassword = $escapedValue;
								}
							}
						}
	
						if (!empty($updates)) {
							 // If password updated, also update dtPasswordActive
							if (!empty($vPassword) && $pwdDays > 0) {
								$updates[] = "dtPasswordActive = DATE_ADD(NOW(), INTERVAL $pwdDays DAY)";
								$updates[] = "cRefType ='A'";
							}
							$updateSql = "UPDATE users SET " . implode(", ", $updates) . " WHERE iUserID = '$txtid'";
							sql_query($updateSql);
						}
	
	
						// $propRes = sql_query("SELECT iPropertyID FROM user_temp_property_assoc WHERE iUserID = " . intval($txtid));
						// $values = [];
						// while ($row = sql_fetch_assoc($propRes)) {
						// 	$propertyID = intval($row['iPropertyID']);
						// 	$existsRes = sql_query("SELECT 1 FROM users_property_assoc WHERE iUserID = " . intval($txtid) . " AND iPropertyID = $propertyID LIMIT 1");
						// 	if (sql_num_rows($existsRes) == 0) {
						// 		$values[] = "(" . intval($txtid) . ", $propertyID)";
						// 	}
						// }
						// if (!empty($values)) {
						// 	$valuesStr = implode(',', $values);
						// 	sql_query("INSERT INTO users_property_assoc (iUserID, iPropertyID) VALUES $valuesStr");
						// }
	
						if (!empty($vPassword)) {
							$iLYTPID = NextID('iLYTPID', 'log_user_temppassword');
							$sq = "INSERT INTO log_user_temppassword (iLYTPID, dtEntry, iUserID, vPassword, cIsTemp, cStatus) VALUES ('$iLYTPID',NOW(),'$txtid','$vPassword','Y','A')";
							sql_query($sq);
						}
					} else {
						$fields = ['iUserID'];
						$values = ["'" . db_input($txtid) . "'"];
						// append instead of overwriting
						$fields[] = 'cRefType';
						$values[] = "'A'";
						
						$vPassword = '';
						foreach ($userTemp as $field => $value) {
							if ($field === 'iUserID') continue;
							$escapedValue = addslashes($value);
							$fields[] = $field;
							$values[] = "'$escapedValue'";
							if ($field === 'vPassword') {
								$vPassword = $escapedValue;
							}
						}
	
						if ($pwdDays > 0) {
							$fields[] = "dtPasswordActive";
							$values[] = "DATE_ADD(NOW(), INTERVAL $pwdDays DAY)";
						}
	
						$insertSql = "INSERT INTO users (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
						sql_query($insertSql);
	
	
						// $propRes = sql_query("SELECT iPropertyID FROM user_temp_property_assoc WHERE iUserID = $txtid");
	
						// $values = [];
						// while ($row = sql_fetch_assoc($propRes)) {
						// 	$propertyID = $row['iPropertyID'];
						// 	$values[] = "($txtid, $propertyID)";
						// }
						//DFA($values);
						// if (!empty($values)) {
						// 	$valuesStr = implode(',', $values);
						// 	sql_query("INSERT INTO users_property_assoc (iUserID, iPropertyID) VALUES $valuesStr");
						// 	//echo "INSERT INTO user_property_assoc (iUserID, iPropertyID) VALUES $valuesStr";
	
						// }
						$iLYTPID = NextID('iLYTPID', 'log_user_temppassword');
						$sq = "INSERT INTO log_user_temppassword (iLYTPID, dtEntry, iUserID, vPassword, cIsTemp, cStatus) VALUES ('$iLYTPID',NOW(),'$txtid','$vPassword','Y','A')";
						sql_query($sq);
					}

					// Sync stations: mirror draft assoc rows into the live assoc table
					$tempStations = GetXArrFromYID("SELECT iStationID FROM user_temp_station_assoc WHERE iUserID = '$txtid'");
					$tempStations = !empty($tempStations) ? array_map('intval', $tempStations) : [];
					sql_query("DELETE FROM users_station_assoc WHERE iUserID = '$txtid'");
					foreach ($tempStations as $stationID) {
						sql_query("INSERT INTO users_station_assoc (iUserID, iStationID) VALUES ('$txtid', $stationID)");
					}

					LogMasterEdit($txtid, 'USR', 'AT', $txtname, "Activated by {$sess_user_name}");
				}
					break;
	
				case 'delete':
					sql_query("UPDATE users_temp SET cStatus='X' WHERE iUserID='$txtid'");
					LogMasterEdit($txtid, 'USR', 'D', $txtname, "Deleted by {$sess_user_name}");
					break;
	
				case 'unlock':
					sql_query("UPDATE users SET cStatus='A' WHERE iUserID='$txtid'");
					break;
			}
		}
		
		$status = GetXFromYID("SELECT cStatus FROM users_temp WHERE iUserID = $txtid");
		$dataArr = GetDataFromID("users_temp", "iUserID", $txtid);
		//$dataArr2 = GetDataFromID("user_temp_property_assoc", "iUserID", $txtid);
		if (empty($dataArr) || $txtid == 0) {
			http_response_code(400);
			echo json_encode(['statusCode' => 400, 'message' => 'Invalid User ID']);
			exit;
		} 

		$user_id = db_output($dataArr[0]->iUserID);
		$txtname = db_output($dataArr[0]->vName);
		$txtemail = db_output($dataArr[0]->vEmail);
		$txtphone = db_output($dataArr[0]->vPhone);
		$txtusername = db_output($dataArr[0]->vUName);
		$cmblevel = db_output($dataArr[0]->iLevel);
		$txtpassword = db_output($dataArr[0]->vPassword);
		$rdstatus = db_output($dataArr[0]->cStatus);

		// Stations now come from the assoc table
		$cmbstationArr = GetXArrFromYID("SELECT iStationID FROM user_temp_station_assoc WHERE iUserID = '$txtid'");
		$cmbstationArr = !empty($cmbstationArr) ? array_map('intval', $cmbstationArr) : [];

		// Staff / Vendor reference
		$iRefID = db_output($dataArr[0]->iRefID ?? '');
		$cRefSrcType = db_output($dataArr[0]->cRefSrcType ?? '');
		$cmbstaff = ($cRefSrcType === 'S') ? $iRefID : '';
		$cmbvendor = ($cRefSrcType === 'V') ? $iRefID : '';


		$prevUserData = [];
		if (in_array($rdstatus, ['P', 'U'])) {
			$prevUserResult = sql_query("SELECT vName, vEmail, vPhone, vUName, iLevel FROM users WHERE iUserID = '$txtid'");
			$prevUserData = sql_fetch_assoc($prevUserResult);
		}
		$txtaction = db_output($dataArr[0]->cAction);
		$status_str = GetStatusImageString2('USERS', $rdstatus, $txtid, false);
		$txtreason = db_output($dataArr[0]->vRemark);
	
		http_response_code(200);
		echo json_encode([
			'statusCode' => 200,
			'message' => 'User updated successfully',
			'User' => [
				'iUserID' => $txtid,
				'Status'  => $status,
				'data' => [
					'iUserID' => $user_id,
					'vName' => $txtname,
					'vEmail' => $txtemail,
					'vPhone' => $txtphone,
					'vUName' => $txtusername,
					'iLevel' => $cmblevel,
					'StationID' => $cmbstationArr,
					'StationName' => !empty($cmbstationArr) ? implode(', ', array_map(function($id) {
						return GetXFromYID("SELECT vName FROM fleet_station WHERE iFlt_StationID = '" . intval($id) . "'");
					}, $cmbstationArr)) : '',
					'vPassword' => $txtpassword,
					'cStatus' => $rdstatus,
					'cmbstaff' => $cmbstaff,
					'cmbvendor' => $cmbvendor,
					'prevUserData' => $prevUserData,
					'cAction' => $txtaction,
					'status_str' => $status_str,
					'vRemark' => $txtreason
				]
			]
		]);
		exit;
	}
    // Example: handle Delete mode (D)
    if ($mode === 'D') {
        if (!$txtid) {
            http_response_code(400);
            echo json_encode(['statusCode' => 400, 'message' => 'User ID missing']);
            exit;
        }

        sql_query("DELETE FROM users_temp WHERE iUserID = $txtid");
        sql_query("DELETE FROM user_temp_station_assoc WHERE iUserID = $txtid");
        http_response_code(200);
        echo json_encode([
            'statusCode' => 200,
            'message' => 'User deleted successfully',
            'data' => ['iUserID' => $txtid]
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'statusCode' => 500,
        'message' => 'Internal Server Error',
        'error' => $e->getMessage()
    ]);
    exit;
}
?>