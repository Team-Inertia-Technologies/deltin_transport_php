<?php
$NO_REDIRECT = 1;
$NO_PRELOAD = true;
require_once("common.php");

if (isset($_POST["response"])) $response = $_POST["response"];
else if (isset($_GET["response"])) $response = $_GET["response"];
else $response = "";

$result = 'false'; //0~0~0~0";

if ($response == 'UNIQUE_PHONE') {
	$phone = $_GET['val'];
	$result = sql_query("SELECT COUNT(*) AS cnt FROM member WHERE vMobile = '" . ($phone) . "'");
	$row = sql_fetch_assoc($result);
	echo ($row['cnt'] > 0) ? '0' : '1';
	exit;
}

if ($response == "UNIQUE_CODE") {
	if (isset($_GET["id"]) && isset($_GET['val']) && isset($_GET['mode'])) {
		$id = $_GET["id"];
		$val = trim($_GET["val"]);
		$mode = $_GET['mode'];

		if ($mode == 'USERS') {
			$pk_fld = 'iUserID';
			$code_fld = 'vUName';
			$tbl = 'users';
		} elseif ($mode == 'EMPLOYEE_CODE') {
			$pk_fld = 'iUserID';
			$code_fld = 'vCode';
			$tbl = 'users';
		} elseif ($mode === 'membership') {
			$query = "SELECT COUNT(*) as cnt FROM member WHERE vCode = '" . ($val) . "'";
			$result = sql_query($query);
			$row = sql_fetch_assoc($result);
			echo ($row['cnt'] > 0) ? '0' : '1';
			exit;
		} elseif ($mode == 'rm') {
			$pk_fld = 'iRMID';
			$code_fld = 'cCode';
			$tbl = 'rm';
		}

		$flag = IsUniqueEntry($pk_fld, $id, $code_fld, $val, $tbl);
		$result = ($flag == '0') ? '0' : '1';
	}
} else if ($response == 'UPDATE_STATUS') {
	if (isset($_GET["mode"]) && isset($_GET["status"]) && isset($_GET["id"])) {
		$mode = $_GET["mode"];
		$status = $_GET["status"];
		$id = $_GET["id"];

		$valid_modes = array('USERS', 'RM', 'MEM_TYPE', 'MEM_LEVEL', 'AIRPORT', 'PROPERTY', 'ALCOHOL', 'CIGARETTE', 'ALLERGY', 'HOTEL', 'OFFERS', 'LOCATION', 'SLIDER', 'SPLASH_SLIDER', 'RM_SLIDER', 'PRIVILEGE', 'FLIGHT_REQUEST', 'HOTEL_REQUEST', 'CAB_REQUEST', 'EVENT_REQUEST', 'STATE', 'COUNTRY', 'SYS_SETTING','AREA','GEN_COMPANY','GEN_COUNTER','COLOR','PACKAGE_CATEGORY','VENDOR_CATS','VENDOR_CATS','CAMPAIGN');
		if (in_array($mode, $valid_modes)) {
			if ($mode == 'USERS') {
				$pk_fld = 'iUserID';
				$tbl = 'users_temp';
				$msg = 'User';
			} elseif ($mode == 'RM') {
				$pk_fld = 'iRMID';
				$tbl = 'rm';
				$msg = 'RM';
			} elseif ($mode == 'MEM_TYPE') {
				$pk_fld = 'iMemTypeID';
				$tbl = 'gen_memtype';
				$msg = 'Member Type';
			} elseif ($mode == 'MEM_LEVEL') {
				$pk_fld = 'iMemLevelID';
				$tbl = 'gen_memlevel';
				$msg = 'Member Level';
			} elseif ($mode == 'AIRPORT') {
				$pk_fld = 'iAirportID';
				$tbl = 'gen_airports';
				$msg = 'Airports';
			} elseif ($mode == 'PROPERTY') {
				$pk_fld = 'iPropertyID';
				$tbl = 'property';
				$msg = 'Property';
			} elseif ($mode == 'ALCOHOL') {
				$pk_fld = 'iAlcID';
				$tbl = 'gen_alcohol';
				$msg = 'Alcohol';
			} elseif ($mode == 'CIGARETTE') {
				$pk_fld = 'iCigID';
				$tbl = 'gen_cigarette';
				$msg = 'Cigarette';
			} elseif ($mode == 'ALLERGY') {
				$pk_fld = 'iAllergyID';
				$tbl = 'gen_allergies';
				$msg = 'Allergy';
			} elseif ($mode == 'HOTEL') {
				$pk_fld = 'iHotelID';
				$tbl = 'gen_hotel';
				$msg = 'Hotel';
			} elseif ($mode == 'OFFERS') {
				$pk_fld = 'iOfferID';
				$tbl = 'offers';
				$msg = 'Offers';
			} elseif ($mode == 'LOCATION') {
				$pk_fld = 'iLocID';
				$tbl = 'location';
				$msg = 'Location';
			} elseif ($mode == 'SLIDER') {
				$pk_fld = 'iSlideID';
				$tbl = 'slider';
				$msg = 'Slider';
			} elseif ($mode == 'SPLASH_SLIDER') {
				$pk_fld = 'iSlideID';
				$tbl = 'splash_slider';
				$msg = 'Splash Slider';
			} elseif ($mode == 'RM_SLIDER') {
				$pk_fld = 'iRmSlideID';
				$tbl = 'rm_slides';
				$msg = 'RM Slide';
			} elseif ($mode == 'PRIVILEGE') {
				$pk_fld = 'iPrivilegeID';
				$tbl = 'gen_privilege';
				$msg = 'Privilege';
			} elseif ($mode == 'FLIGHT_REQUEST') {
				$pk_fld = 'iRequestID';
				$tbl = 'member_flightrequest';
				$msg = 'Flight Request';
			} elseif ($mode == 'HOTEL_REQUEST') {
				$pk_fld = 'iRequestID';
				$tbl = 'member_hotelrequest';
				$msg = 'Hotel Request';
			} elseif ($mode == 'CAB_REQUEST') {
				$pk_fld = 'iRequestID';
				$tbl = 'member_cabrequest';
				$msg = 'Cab Request';
			} elseif ($mode == 'EVENT_REQUEST') {
				$pk_fld = 'iRequestID';
				$tbl = 'member_eventrequest';
				$msg = 'Event Request';
			} elseif ($mode == 'STATE') {
				$pk_fld = 'iStateID';
				$tbl = 'gen_state';
				$msg = 'State';
			} elseif ($mode == 'COUNTRY') {
				$pk_fld = 'iCountryID ';
				$tbl = 'country';
				$msg = 'Country';
			} elseif ($mode == 'SYS_SETTING') {
				$pk_fld = 'iSettingID ';
				$tbl = 'sys_settings';
				$msg = 'Settings';
			}
			 elseif ($mode == 'AREA') {
				$pk_fld = 'iLocationID ';
				$tbl = 'gen_area';
				$msg = 'Area';
			}
 elseif ($mode == 'GEN_COMPANY') {
				$pk_fld = 'iCompanyID ';
				$tbl = 'gen_company';
				$msg = 'Company';
			}
		
			 elseif ($mode == 'GEN_COUNTER') {
				$pk_fld = 'iCounterID ';
				$tbl = 'gen_counter';
				$msg = 'Counter';
			}
			 elseif ($mode == 'COLOR') {
				$pk_fld = 'iColorID ';
				$tbl = 'gen_color';
				$msg = 'Color';
			}
			 elseif ($mode == 'PACKAGE_CATEGORY') {
				$pk_fld = 'iPackageCategoryID ';
				$tbl = 'package_category';
				$msg = 'Package Category';
			}
			 elseif ($mode == 'VENDOR_CATS') {
				$pk_fld = 'iCatID ';
				$tbl = 'vendorcat';
				$msg = 'Vendor Category';
			}
			 elseif ($mode == 'VENDOR') {
				$pk_fld = 'iVendorID ';
				$tbl = 'vendor';
				$msg = 'Vendor';
			}
			 elseif ($mode == 'DL_OFFERS') {
				$pk_fld = 'iOfferID ';
				$tbl = 'dl_offers';
				$msg = 'Delights Offers';
			}
				 elseif ($mode == 'CAMPAIGN') {
				$pk_fld = 'iCampaignID ';
				$tbl = 'campaign';
				$msg = 'Campaign';
			}
			$q = "update " . $tbl . " set cStatus='$status' where " . $pk_fld . "=" . $id;
			$r = sql_query($q, 'AJX.68');

			if (sql_affected_rows()) {
				$str = GetStatusImageString($mode, $status, $id);
				$result = "$str~$msg Status Has Been Changed";
			}	// */
		}
	}
} else if ($response == 'GET_ROOMTYPE') {
	if (isset($_GET['h_ids'])) {
		$str = '';
		$h_ids = $_GET['h_ids'];
		$n = $_GET['n'];

		$ROOMTYPE_ARR = array();
		if (!empty($h_ids)) {
			$_hq = 'select r.iRoomTypeID, r.vName, r.iHotelID, h.vName from gen_roomtype as r join gen_hotel as h on r.iHotelID=h.iHotelID where r.cStatus="A" and h.cStatus="A" and r.iHotelID IN (' . $h_ids . ') order by h.vName, r.vName';
			$_hr = sql_query($_hq);
			if (sql_num_rows($_hr)) {
				while (list($r_id, $r_name, $h_id, $h_name) = sql_fetch_row($_hr))
					$ROOMTYPE_ARR[$h_id . '~' . $r_id] = array('NAME' => htmlspecialchars_decode($r_name), 'OPT_GROUP' => htmlspecialchars_decode($h_name));
			}
		}

		$str = FillMultiCombo('', 'cmbnewrequest_roomtype' . $n, 'COMBO', 'Y', $ROOMTYPE_ARR, 'data-live-search="true" style="background-color:#fff !important;"', 'form-control form-control-sm multiSELECT_STYLE multiSELECT2_' . $n, 'SPLIT_FOR_OPTGROUP');

		$result = $str;
	} else
		$result = '2~*~Invalid Access Detected!!!';
} else if ($response == 'GET_ROOMTYPE2') {
	if (isset($_GET['hid'])) {
		$str = '';
		$hid = $_GET['hid'];
		if (empty($hid)) $hid = 0;

		$ROOMTYPE_ARR = GetXArrFromYID('select iRoomTypeID, vName from gen_roomtype where iHotelID=' . $hid, '3');
		$str = FillCombo('', 'cmbroomtype', 'COMBO', '-4', $ROOMTYPE_ARR, 'data-live-search="true"', 'form-control form-control-sm');

		$result = $str;
	} else
		$result = '2~*~Invalid Access Detected!!!';
} else if ($response == 'GET_ROOMTYPE3') {
	if (isset($_GET['hid'])) {
		$str = '';
		$n = $_GET['n'];
		$hid = $_GET['hid'];
		if (empty($hid)) $hid = 0;

		$ROOMTYPE_ARR = GetXArrFromYID('select iRoomTypeID, vName from gen_roomtype where iHotelID=' . $hid, '3');
		$str = FillCombo('', 'cmbnewrequest_roomtype' . $n, 'COMBO', '-4', $ROOMTYPE_ARR, 'data-live-search="true"', 'form-control form-control-sm multi');

		$result = $str;
	} else
		$result = '2~*~Invalid Access Detected!!!';
} else if ($response == 'GET_ROOMTYPE4') {
	if (isset($_GET['hid'])) {
		$str = '';
		$n = $_GET['n'];
		$hid = $_GET['hid'];
		if (empty($hid)) $hid = 0;

		$ROOMTYPE_ARR = GetXArrFromYID('select iRoomTypeID, vName from gen_roomtype where iHotelID=' . $hid, '3');
		$str = FillCombo('', 'cmbroomtype' . $n, 'COMBO', '-4', $ROOMTYPE_ARR, 'data-live-search="true"', 'form-control form-control-sm multi');

		$result = $str;
	} else
		$result = '2~*~Invalid Access Detected!!!';
} else if ($response == 'SEND_REQUEST_TO_HOTEL') {
	if (isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['id2']) && !empty($_GET['id2']) && is_numeric($_GET['id2']) && isset($_GET['id3']) && !empty($_GET['id3']) && is_numeric($_GET['id3']) && isset($_GET['h_id']) && !empty($_GET['h_id']) && is_numeric($_GET['h_id']) && isset($_GET['rt_id']) && !empty($_GET['rt_id']) && is_numeric($_GET['rt_id'])) {
		$booking_add_url = 'bookings_add.php';

		$sess_id = $_GET['sess_id'];
		$request_id = $_GET['id'];
		$request_datid = $_GET['id3'];
		$request_optionid = $_GET['id2'];
		$hotel_id = $_GET['h_id'];
		$roomtype_id = $_GET['rt_id'];
		$txtdtenq = NOW;

		LockTable('concrequest_hotelenq');
		$enq_id = NextID('iHotelEnqID', 'concrequest_hotelenq');
		sql_query("insert into concrequest_hotelenq (iHotelEnqID, iHotelID, iRoomTypeID, iRequestDatOptionID, iRequestDatID, iRequestID, iConcSessionID, dtEnq, iReqBy_UserID, cStatus) values ('$enq_id', '$hotel_id', '$roomtype_id', '$request_optionid', '$request_datid', '$request_id', '$sess_id', '$txtdtenq', '$sess_user_id', 'RS')", "");
		UnlockTable();

		if (!isset($UNI_HOTEL_ARR[$hotel_id])) $UNI_HOTEL_ARR[$hotel_id] = GetXFromYID("select vName from gen_hotel where iHotelID=$hotel_id");
		if (!isset($UNI_ROOMTYPE_ARR[$roomtype_id])) $UNI_ROOMTYPE_ARR[$roomtype_id] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$roomtype_id");

		$hName = $UNI_HOTEL_ARR[$hotel_id];
		$rtName = $UNI_ROOMTYPE_ARR[$roomtype_id];

		//$desc_str = 'Request sent to the Hotel: '.$hName.' for RoomType: '.$rtName;
		$desc_str = 'Sent Request to check availibility';
		LogRequests($sess_id, $request_id, $request_optionid, 'RO', 'I', $desc_str, $sess_user_id, $hName, $rtName);

		$requestStatus = GetXFromYID('select cStatus from concrequest where iRequestID=' . $request_id);
		if ($requestStatus == 'D') {
			$q = "update concrequest set cStatus='I' where iRequestID='$request_id' and iConcSessionID='$sess_id'";
			$r = sql_query($q, '');

			$desc_str = 'Request status is changed from ' . $REQUEST_STATUS_ARR[$requestStatus] . ' to ' . $REQUEST_STATUS_ARR['I'];
			LogRequests($sess_id, $request_id, 0, 'RQ', 'U', $desc_str, $sess_user_id, '', ''); //$hName, $rtName);

			$q2 = "update concsession set cStatus='I' where iConcSessionID='$sess_id'";
			$r2 = sql_query($q2, '');

			$REQUEST_STATUS = '<a href="javascript:void(0);" class="mb-2 mr-2 badge badge-' . $REQUESTSTATUS_COLOR_ARR['I'] . '" onClick="GetUpdateRequestStatusModal();">' . strtoupper($REQUEST_STATUS_ARR['I']) . '<span class="change-status">Change</span></a>';
		} else
			$REQUEST_STATUS = '<a href="javascript:void(0);" class="mb-2 mr-2 badge badge-' . $REQUESTSTATUS_COLOR_ARR[$requestStatus] . '" onClick="GetUpdateRequestStatusModal();">' . strtoupper($REQUEST_STATUS_ARR[$requestStatus]) . '<span class="change-status">Change</span></a>';

		SendRequestMail($request_id, $request_datid, $request_optionid, $enq_id, $hotel_id, $hName, $rtName, $sess_user_id);

		$html = GetHotelRoomActivityStatus($request_id, $request_optionid, $enq_id);

		$result = '1~~**~~Request sent to hotel~~**~~' . $html . '~~**~~' . $REQUEST_STATUS;
	}
} else if ($response == 'ADD_HOTEL_TO_ACCOMODATION') {
	if (isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['id2']) && !empty($_GET['id2']) && is_numeric($_GET['id2']) && isset($_GET['h_ids']) && !empty($_GET['h_ids']) && isset($_GET['rt_ids']) && !empty($_GET['rt_ids'])) {
		$booking_add_url = 'bookings_add.php';

		$a = $_GET['a'];
		$sess_id = $_GET['sess_id'];
		$request_id = $_GET['id'];
		$request_datid = $_GET['id2'];
		$h_ids = $_GET['h_ids'];
		$rt_ids = $_GET['rt_ids'];

		$h_arr = explode(',', $h_ids);
		$rt_arr = explode(',', $rt_ids);

		$html = '';

		foreach ($h_arr as $h_id) {
			foreach ($rt_arr as $roomtype) {
				$rt = explode('~', $roomtype);

				if ($rt[0] == $h_id) {
					$rt_id = $rt[1];
					LockTable('concrequestdat_options');
					$txtid = NextID('iRequestDatOptionID', 'concrequestdat_options');
					sql_query("insert into concrequestdat_options values ('$txtid', '$request_datid', '$request_id', '$h_id', '$rt_id', '')", "");
					UnlockTable();

					if (!isset($UNI_HOTEL_ARR[$h_id])) $UNI_HOTEL_ARR[$h_id] = GetXFromYID("select vName from gen_hotel where iHotelID=$h_id");
					if (!isset($UNI_ROOMTYPE_ARR[$rt_id])) $UNI_ROOMTYPE_ARR[$rt_id] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$rt_id");

					$hName = $UNI_HOTEL_ARR[$h_id];
					$rtName = $UNI_ROOMTYPE_ARR[$rt_id];

					$desc_str = 'New Hotel/RoomType details added';
					LogRequests($sess_id, $request_id, $txtid, 'RO', 'I', $desc_str, $sess_user_id, $hName, $rtName);

					$_rq = 'select dCheckin, dCheckOut from concrequest where iRequestID=' . $request_id;
					$_rr = sql_query($_rq, '');
					list($txtchkin, $txtchkout) = sql_fetch_row($_rr);

					$_rq2 = 'select iNumPax, iNumRooms from concrequestdat where iRequestDatID=' . $request_datid;
					$_rr2 = sql_query($_rq2, '');
					list($txtguests, $txtrooms) = sql_fetch_row($_rr2);

					$COST = GetHotelRoomCost($h_id, $rt_id, $txtid, $txtguests, $txtrooms, $txtchkin, $txtchkout);

					$DET = '';
					if (!empty($h_id)) $DET .= $HOTEL_ARR[$h_id] . ' | ';
					if (!empty($rt_id) && isset($ROOMTYPE_ARR[$rt_id]))
						$DET .= $ROOMTYPE_ARR[$rt_id] . ' | ';
					if (!empty($COST))
						$DET .= '<span class="price-room" style="cursor:pointer;" onClick="GetRoomTypeBreakUp(\'' . $h_id . '\',\'' . $rt_id . '\',\'' . $txtid . '\');">Rs. ' . FormatNumber($COST) . '</span> | ';
					if (empty($COST))
						$DET .= '<span class="price-room no-price" style="cursor:pointer;" onClick="GetRoomTypeBreakUp2(\'' . $h_id . '\',\'' . $rt_id . '\',\'' . $txtid . '\',\'' . $request_datid . '\');">Rs. ' . FormatNumber($COST) . '</span> | ';
					if (!empty($DET)) $DET = substr($DET, 0, '-3');

					$lastestACTION = '<div class="ml-auto ml-1 pl-1 badge badge-alternate">No Action Taken</div>';
					$REQUEST_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="SendReuestToHotel(\'' . $txtid . '\',\'' . $h_id . '\',\'' . $rt_id . '\',\'' . $request_datid . '\');"><i class="nav-link-icon pe-7s-paper-plane"> </i><span>Send Request</span></a>';
					$MARKAVAILABLE_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="SpecifyAvailability(\'0\',\'' . $txtid . '\')"><i class="nav-link-icon pe-7s-ticket"> </i><span>Specify Availability</span></a>';
					$RMCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="MarkConfirmedByRM(\'0\',\'' . $txtid . '\');"><i class="nav-link-icon pe-7s-ticket"> </i><span>Confirm by RM</span></a>';
					$HOTELCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="MarkConfirmedByHotel(\'0\',\'' . $txtid . '\');"><i class="nav-link-icon pe-7s-like"> </i><span>Confirm by Hotel</span></a>';
					$BOOKNOW_BUTTON = '<a href="' . $booking_add_url . '?req_id=' . $request_id . '&dat_id=' . $request_datid . '&id=' . $txtid . '" class="nav-link"><i class="nav-link-icon pe-7s-notebook"></i><span>Book</span></a>';

					$html .= '<div class="main-card card acc-hotel-block">';
					$html .= '<div class="card-body">';
					$html .= '<h5 class="card-title"><span id="LATEST_ACTION_' . $txtid . '">' . $lastestACTION . '</span>';
					$html .= '<span class="title-txt">' . $DET . '</span>';
					$html .= '<div class="btn-actions-pane-right actions-icon-btn float-right">';
					$html .= '<div class="dropup btn-group">';
					$html .= '<button type="button" aria-haspopup="true" aria-expanded="false" data-toggle="dropdown" class="dropdown-toggle btn btn-primary">Actions</button>';
					$html .= '<div tabindex="-1" role="menu" aria-hidden="true" class="dropdown-menu">';
					$html .= '<ul class="nav flex-column" id="ACTIVITY_' . $txtid . '">';
					$html .= '<li class="nav-item-header nav-item">Activity</li>';
					$html .= '<li class="nav-item">' . $REQUEST_BUTTON . '</li>';
					$html .= '<li class="nav-item">' . $MARKAVAILABLE_BUTTON . '</li>';
					$html .= '<li class="nav-item">' . $RMCONFIRM_BUTTON . '</li>';
					$html .= '<li class="nav-item">' . $HOTELCONFIRM_BUTTON . '</li>';
					$html .= '<li class="nav-item">' . $BOOKNOW_BUTTON . '</li>';
					$html .= '</ul>';
					$html .= '</div>';
					$html .= '</div>';
					$html .= '</div>';
					$html .= '</h5>';
					$html .= '</div>';
					$html .= '</div>';
				}
			}
		}

		$result = '1~~**~~Hotel added to Accomodation ' . $a . '~~**~~' . $html;
	}
} else if ($response == 'ADD_ACCOMODATION_TO_REQUEST') {
	if (isset($_POST['sess_id']) && !empty($_POST['sess_id']) && is_numeric($_POST['sess_id']) && isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id']) && isset($_POST['cmbnewrequest_hotel']) && !empty($_POST['cmbnewrequest_hotel']) && isset($_POST['cmbnewrequest_roomtype']) && !empty($_POST['cmbnewrequest_roomtype']) && isset($_POST['txtnewrequest_rooms']) && !empty($_POST['txtnewrequest_rooms'])) {
		$booking_add_url = 'bookings_add.php';

		$a = $_POST['a'];
		$sess_id = $_POST['sess_id'];
		$request_id = $_POST['id'];
		$cmbnewrequest_hotel = $_POST['cmbnewrequest_hotel'];
		$cmbnewrequest_roomtype = $_POST['cmbnewrequest_roomtype'];
		$txtnewrequest_rooms = $_POST['txtnewrequest_rooms'];
		$cmbnewrequest_earlycheckin = 'N'; //$_POST['cmbnewrequest_earlycheckin'];
		$cmbnewrequest_latecheckout = 'N'; //$_POST['cmbnewrequest_latecheckout'];
		$txtnewrequest_inclusions = ''; //db_input2($_POST['txtnewrequest_inclusions']);
		$txtnewrequest_billinginstructions = ''; //db_input2($_POST['txtnewrequest_billinginstructions']);
		$guest = 0; //$_POST['txtnewrequest_guests'];

		LockTable('concrequestdat');
		$request_datid = NextID('iRequestDatID', 'concrequestdat');
		sql_query("insert into concrequestdat values ('$request_datid', '$request_id', '$guest', '$txtnewrequest_rooms', '0', '', '$txtnewrequest_inclusions', '$txtnewrequest_billinginstructions', '$cmbnewrequest_earlycheckin', '$cmbnewrequest_latecheckout', '$request_datid', 'D')", "");
		UnlockTable();

		if (!isset($UNI_HOTEL_ARR[$cmbnewrequest_hotel])) $UNI_HOTEL_ARR[$cmbnewrequest_hotel] = GetXFromYID("select vName from gen_hotel where iHotelID=$cmbnewrequest_hotel");
		if (!isset($UNI_ROOMTYPE_ARR[$cmbnewrequest_roomtype])) $UNI_ROOMTYPE_ARR[$cmbnewrequest_roomtype] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$cmbnewrequest_roomtype");

		$hName = $UNI_HOTEL_ARR[$cmbnewrequest_hotel];
		$rtName = $UNI_ROOMTYPE_ARR[$cmbnewrequest_roomtype];

		//$desc_str = 'New Accomodation added. Rooms: '.$txtnewrequest_rooms;
		$desc_str = 'New Accomodation details added';
		LogRequests($sess_id, $request_id, $request_datid, 'RD', 'I', $desc_str, $sess_user_id, '', ''); //$hName, $rtName);

		$ROOMBILLINGINSTRUCTIONS_DET = array();
		$GUESTLIST_DET = array();
		$ROOM_OCCUPANCY_ARR = array();
		if (!empty($txtnewrequest_rooms)) {
			for ($r = 1; $r <= $txtnewrequest_rooms; $r++) {
				$cmbnewrequest_roomoccupancy = $_POST['cmbnewrequest_roomoccupancy_' . $r];
				$txtnewrequest_guestname = $_POST['txtnewrequest_guestname_' . $r];  //array
				$txtnewrequest_guestno = $_POST['txtnewrequest_guestno_' . $r];  //array
				$txtnewrequest_billinginstructions = db_input2($_POST['txtnewrequest_billinginstructions_' . $r]);

				$txtroom_occupancy = $OCCUPANCY_ARR2[$cmbnewrequest_roomoccupancy];
				$ROOM_OCCUPANCY_ARR[$r] = $txtroom_occupancy;
				$guest = $guest + $txtroom_occupancy;

				foreach ($txtnewrequest_guestname as $gKEY => $gVALUE) {
					$guestNAME = db_input2($gVALUE);
					$guestN0 = db_input2($txtnewrequest_guestno[$gKEY]);

					if (!empty($guestNAME)) {
						LockTable('concrequestdat_guests');
						$conc_guestid = NextID('iGuestID', 'concrequestdat_guests');
						sql_query("insert into concrequestdat_guests values ('$conc_guestid', '$request_id', '$request_datid', '$r', '$guestNAME', '$guestN0', '$conc_guestid', 'A')", "");
						UnlockTable();

						$desc_str = 'New Guest details added. Guest: ' . $guestNAME;
						LogRequests($sess_id, $request_id, $request_datid, 'RG', 'I', $desc_str, $sess_user_id, $hName, $rtName);

						//if(!empty($guestN0)) $guestNAME .= ' ('.$guestN0.')';

						if (!isset($GUESTLIST_DET[$r])) $GUESTLIST_DET[$r] = array();
						array_push($GUESTLIST_DET[$r], htmlspecialchars_decode($guestNAME));
					}
				}

				LockTable('concrequestdat_rooms');
				$conc_request_roomid = NextID('iRoomID', 'concrequestdat_rooms');
				sql_query("insert into concrequestdat_rooms values ('$conc_request_roomid', '$request_id', '$request_datid', '$r', '$txtroom_occupancy', '$txtnewrequest_billinginstructions', 'A')", "");
				UnlockTable();

				if (empty($txtnewrequest_billinginstructions)) $txtnewrequest_billinginstructions = '-NA-';
				$ROOMBILLINGINSTRUCTIONS_DET[$r] = stripslashes(htmlspecialchars_decode($txtnewrequest_billinginstructions));
			}
		}

		sql_query("update concrequestdat set iNumPax='$guest' where iRequestDatID='$request_datid'", "");

		$html = '';
		$aDET = '';
		if (!empty($guest)) $aDET .= 'Guests: <strong><u>' . $guest . '</u></strong> | ';
		if (!empty($room)) $aDET .= 'Rooms: <strong><u>' . $room . '</u></strong> | ';
		if (!empty($cmbnewrequest_earlycheckin) && $cmbnewrequest_earlycheckin == 'Y') $aDET .= '<strong><u>Early CheckIn</u></strong> | ';
		if (!empty($cmbnewrequest_latecheckout) && $cmbnewrequest_latecheckout == 'Y') $aDET .= '<strong><u>Late CheckOut</u></strong> | ';
		if (!empty($aDET)) $aDET = substr($aDET, 0, '-3');

		if (empty($txtnewrequest_inclusions)) $txtnewrequest_inclusions = '-NA-';
		if (empty($txtnewrequest_billinginstructions)) $txtnewrequest_billinginstructions = '-NA-';
		if (empty($guestLIST)) $guestLIST = '-NA-';

		$html .= '<div class="acc-block">';
		$html .= '<div class="app-inner-layout__header-boxed p-0">';
		$html .= '<div class=" text-white bg-header p-1">';
		$html .= '<div class="">';
		$html .= '<div class="page-title-wrapper">';
		$html .= '<div class="acc-head" style="width:100%"> <strong>Details ' . $a . ' | <span id="ACCOMODATION_DETAILS_' . $request_datid . '">' . $aDET . '</span></strong></div>'; // <button class="edit-link mr-2 btn btn-link" onClick="EditAccomodationDetails(\''.$request_datid.'\',\''.$a.'\');">Edit Request </button>
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="acc-body">';

		if (!empty($cmbnewrequest_hotel) && !empty($cmbnewrequest_roomtype)) {
			$h_id = $cmbnewrequest_hotel;
			$rt_id = $cmbnewrequest_roomtype;

			LockTable('concrequestdat_options');
			$conc_requestid3 = NextID('iRequestDatOptionID', 'concrequestdat_options');
			sql_query("insert into concrequestdat_options values ('$conc_requestid3', '$request_datid', '$request_id', '$h_id', '$rt_id', '0')", "");
			UnlockTable();

			$desc_str = 'New Hotel/RoomType added.';
			LogRequests($sess_id, $request_id, $conc_requestid3, 'RO', 'I', $desc_str, $sess_user_id, $hName, $rtName);

			$_rq = 'select dCheckin, dCheckOut from concrequest where iRequestID=' . $request_id;
			$_rr = sql_query($_rq, '');
			list($txtchkin, $txtchkout) = sql_fetch_row($_rr);

			$COST = GetHotelRoomCost($h_id, $rt_id, $conc_requestid3, $guest, $txtnewrequest_rooms, $txtchkin, $txtchkout, $ROOM_OCCUPANCY_ARR);

			$DET = '';
			if (!empty($h_id)) $DET .= $UNI_HOTEL_ARR[$h_id] . ' | ';
			if (!empty($rt_id) && isset($UNI_ROOMTYPE_ARR[$rt_id]))
				$DET .= $UNI_ROOMTYPE_ARR[$rt_id] . ' | ';
			if (!empty($COST))
				$DET .= '<span class="price-room" style="cursor:pointer;" onClick="GetRoomTypeBreakUp(\'' . $h_id . '\',\'' . $rt_id . '\',\'' . $conc_requestid3 . '\');">Rs. ' . FormatNumber($COST) . '</span> | ';
			if (empty($COST))
				$DET .= '<span class="price-room no-price" style="cursor:pointer;" onClick="GetRoomTypeBreakUp2(\'' . $h_id . '\',\'' . $rt_id . '\',\'' . $conc_requestid3 . '\',\'' . $request_datid . '\');">Rs. ' . FormatNumber($COST) . '</span> | ';
			if (!empty($DET)) $DET = substr($DET, 0, '-3');

			$lastestACTION = '<div class="ml-auto ml-1 pl-1 badge badge-alternate">No Action Taken</div>';
			$REQUEST_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="SendReuestToHotel(\'' . $conc_requestid3 . '\',\'' . $h_id . '\',\'' . $rt_id . '\',\'' . $request_datid . '\');"><i class="nav-link-icon pe-7s-paper-plane"> </i><span>Send Request</span></a>';
			$MARKAVAILABLE_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="SpecifyAvailability(\'0\',\'' . $conc_requestid3 . '\')"><i class="nav-link-icon pe-7s-ticket"> </i><span>Specify Availability</span></a>';
			$RMCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="MarkConfirmedByRM(\'0\',\'' . $conc_requestid3 . '\');"><i class="nav-link-icon pe-7s-ticket"> </i><span>Confirm by RM</span></a>';
			$HOTELCONFIRM_BUTTON = '<a href="javascript:void(0);" class="nav-link" onClick="MarkConfirmedByHotel(\'0\',\'' . $conc_requestid3 . '\');"><i class="nav-link-icon pe-7s-like"> </i><span>Confirm by Hotel</span></a>';
			$BOOKNOW_BUTTON = '<a href="' . $booking_add_url . '?req_id=' . $request_id . '&dat_id=' . $request_datid . '&id=' . $conc_requestid3 . '" class="nav-link"><i class="nav-link-icon pe-7s-notebook"></i><span>Book</span></a>';

			$html .= '<div class="main-card card acc-hotel-block">';
			$html .= '<div class="card-body">';
			$html .= '<h5 class="card-title"><span id="LATEST_ACTION_' . $conc_requestid3 . '">' . $lastestACTION . '</span>';
			$html .= '<span class="title-txt">' . $DET . '</span>';
			$html .= '<div class="btn-actions-pane-right actions-icon-btn float-right">';
			$html .= '<div class="dropup btn-group">';
			$html .= '<button type="button" aria-haspopup="true" aria-expanded="false" data-toggle="dropdown" class="dropdown-toggle btn btn-primary">Actions</button>';
			$html .= '<div tabindex="-1" role="menu" aria-hidden="true" class="dropdown-menu">';
			$html .= '<ul class="nav flex-column" id="ACTIVITY_' . $conc_requestid3 . '">';
			$html .= '<li class="nav-item-header nav-item">Activity</li>';
			$html .= '<li class="nav-item">' . $REQUEST_BUTTON . '</li>';
			$html .= '<li class="nav-item">' . $MARKAVAILABLE_BUTTON . '</li>';
			$html .= '<li class="nav-item">' . $RMCONFIRM_BUTTON . '</li>';
			$html .= '<li class="nav-item">' . $HOTELCONFIRM_BUTTON . '</li>';
			$html .= '<li class="nav-item">' . $BOOKNOW_BUTTON . '</li>';
			$html .= '</ul>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</h5>';
			$html .= '</div>';
			$html .= '</div>';
		}

		$GUESTLIST = '';
		if (!empty($GUESTLIST_DET) && count($GUESTLIST_DET)) {
			foreach ($GUESTLIST_DET as $ROOM_NO => $ROOM_GUEST) {
				$GUESTLIST .= '<strong>Room ' . $ROOM_NO . '</strong>: ';
				foreach ($ROOM_GUEST as $gKEY => $gVALUE)
					$GUESTLIST .= $gVALUE . ', ';

				if (!empty($GUESTLIST)) $GUESTLIST = substr($GUESTLIST, '0', '-2') . ' | ';
			}
		}
		if (!empty($GUESTLIST)) $GUESTLIST = substr($GUESTLIST, '0', '-3');
		else $GUESTLIST = '-NA-';


		$BILLINGINSTRUCTIONS_DET = '';
		$txtnewrequest_billinginstructions = '';
		if (!empty($ROOMBILLINGINSTRUCTIONS_DET) && count($ROOMBILLINGINSTRUCTIONS_DET)) {
			foreach ($ROOMBILLINGINSTRUCTIONS_DET as $ROOM_NO => $ROOM_BILLINGINSTRUCTIONS)
				$txtnewrequest_billinginstructions .= '<strong>Room ' . $ROOM_NO . '</strong>: ' . $ROOM_BILLINGINSTRUCTIONS . ' | ';
		}
		if (!empty($txtnewrequest_billinginstructions)) $txtnewrequest_billinginstructions = substr($txtnewrequest_billinginstructions, '0', '-3');
		else $txtnewrequest_billinginstructions = '-NA-';


		$html .= '<div id="MORE_HOTEL_' . $a . '"></div>';
		$html .= '<div style="clear: both"></div>';
		$html .= '<div class="main-card card  guest-list-block">';
		$html .= '<div class="card-body">';
		$html .= '<p id="GUEST_LIST_HTML_' . $request_datid . '"><a href="javascript:void(0);" onClick="GetAccomodationGuestListForm(\'' . $request_datid . '\',\'' . $a . '\');"><strong><u>Guest List:</u></strong></a> ' . stripslashes(htmlspecialchars_decode($GUESTLIST)) . '</p>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="main-card card mb-2 inclusion-block">';
		$html .= '<div class="row">';
		/*$html .= '<div class="col-md-12 col-xl-6 border-right spacer-b">';
		$html .= '<p id="INCLUSIONS_HTML_'.$request_datid.'"><a href="javascript:void(0);" onClick="GetAccomodationInclusionsForm(\''.$request_datid.'\',\''.$a.'\');"><strong><u>Inclusions:</u></strong></a> '.stripslashes(htmlspecialchars_decode($txtnewrequest_inclusions)).'</p>';
		$html .= '</div>';*/
		$html .= '<div class="col-md-12 col-xl-12 spacer-b">';
		$html .= '<p id="BILLING_INSTRUCTIONS_HTML_' . $request_datid . '"><a href="javascript:void(0);" onClick="GetAccomodationBillingInstructionsForm(\'' . $request_datid . '\',\'' . $a . '\');"><strong><u>Billing Instructions:</u></strong></a> ' . stripslashes(htmlspecialchars_decode($txtnewrequest_billinginstructions)) . '</p>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '<div class="divider"></div>';

		$result = '1~~**~~Details added to Request~~**~~' . $html;
	}
} else if ($response == 'EDIT_ACCOMODATION_DETAILS') {
	if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['id2']) && !empty($_GET['id2']) && is_numeric($_GET['id2']) && isset($_GET['guest']) && !empty($_GET['guest'])  && isset($_GET['room']) && !empty($_GET['room'])) {
		$a = $_GET['a'];
		$request_id = $_GET['id'];
		$request_datid = $_GET['id2'];
		$guest = $_GET['guest'];
		$room = $_GET['room'];
		$checkin = $_GET['checkin'];
		$checkout = $_GET['checkout'];

		sql_query("update concrequestdat set iNumPax='$guest', iNumRooms='$room', cEarlyCheckIn='$checkin', cLateCheckOut='$checkout' where iRequestDatID='$request_datid' and iRequestID='$request_id'", "");

		UpdateHotelRoomCost($request_id, $request_datid, $guest, $room);

		$aDET = '';
		if (!empty($guest)) $aDET .= 'Guests: <strong><u>' . $guest . '</u></strong> | ';
		if (!empty($room)) $aDET .= 'Rooms: <strong><u>' . $room . '</u></strong> | ';
		if (!empty($checkin) && $checkin == 'Y') $aDET .= '<strong><u>Early CheckIn</u></strong> | ';
		if (!empty($checkout) && $checkout == 'Y') $aDET .= '<strong><u>Late CheckOut</u></strong> | ';
		if (!empty($aDET)) $aDET = substr($aDET, 0, '-3');

		$html = $aDET;

		$result = '1~~**~~Accomodation ' . $a . ' details successfully updated~~**~~' . $html;
	}
} else if ($response == 'UPDATE_HOTEL_ROOM_AVAILABILITY') {
	if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id3']) && !empty($_GET['id3']) && is_numeric($_GET['id3']) && isset($_GET['av']) && !empty($_GET['av'])) // && isset($_GET['id2']) && !empty($_GET['id2']) && is_numeric($_GET['id2']) 
	{
		$request_id = $_GET['id'];
		$sess_id = $_GET['sess_id'];
		$enquiry_id = $_GET['id2'];
		$option_id = $_GET['id3'];
		$available = $_GET['av'];
		$txtdtenq = NOW;

		$availableSTATUS = ($available == 'Y') ? 'AV' : 'NAV';

		if (!empty($enquiry_id))
			sql_query("update concrequest_hotelenq set cAvailable='$available', iAvailable_UserID='$sess_user_id', dtResponse='" . NOW . "', cStatus='$availableSTATUS' where iHotelEnqID='$enquiry_id' and iRequestID='$request_id' and iRequestDatOptionID='$option_id'", "");
		else {
			$q = 'select iHotelID, iRoomTypeID, iRequestDatID from concrequestdat_options where iRequestDatOptionID=' . $option_id . ' and iRequestID=' . $request_id;
			$r = sql_query($q, '');
			if (!sql_num_rows($r)) {
				echo 'Invalid Access Detected!!!';
				exit;
			}

			list($iHotelID, $iRoomTypeID, $iRequestDatID) = sql_fetch_row($r);

			LockTable('concrequest_hotelenq');
			$enquiry_id = NextID('iHotelEnqID', 'concrequest_hotelenq');
			sql_query("insert into concrequest_hotelenq (iHotelEnqID, iHotelID, iRoomTypeID, iRequestDatOptionID, iRequestDatID, iRequestID, iConcSessionID, dtEnq, iReqBy_UserID, cAvailable, iAvailable_UserID, dtResponse, cStatus) values ('$enquiry_id', '$iHotelID', '$iRoomTypeID', '$option_id', '$iRequestDatID', '$request_id', '$sess_id', '$txtdtenq', '$sess_user_id', '$available', '$sess_user_id', '" . NOW . "', '$availableSTATUS')", "");
			UnlockTable();
		}

		$_q = 'select iHotelID, iRoomTypeID, iConcSessionID from concrequest_hotelenq where iHotelEnqID=' . $enquiry_id . ' and iRequestID=' . $request_id . ' and iRequestDatOptionID=' . $option_id;
		$_r = sql_query($_q, '');
		list($hotel_id, $roomtype_id, $iConcSessionID) = sql_fetch_row($_r);

		if (!isset($UNI_HOTEL_ARR[$hotel_id])) $UNI_HOTEL_ARR[$hotel_id] = GetXFromYID("select vName from gen_hotel where iHotelID=$hotel_id");
		if (!isset($UNI_ROOMTYPE_ARR[$roomtype_id])) $UNI_ROOMTYPE_ARR[$roomtype_id] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$roomtype_id");

		$hName = $UNI_HOTEL_ARR[$hotel_id];
		$rtName = $UNI_ROOMTYPE_ARR[$roomtype_id];

		$available = ($available == 'Y') ? 'Available' : 'Not Available';

		$desc_str = 'Room is marked as ' . $available;
		LogRequests($iConcSessionID, $request_id, $option_id, 'RO', 'U', $desc_str, $sess_user_id, $hName, $rtName);

		$requestStatus = GetXFromYID('select cStatus from concrequest where iRequestID=' . $request_id);
		if ($requestStatus == 'D') {
			$q = "update concrequest set cStatus='I' where iRequestID='$request_id' and iConcSessionID='$sess_id'";
			$r = sql_query($q, '');

			$desc_str = 'Request status is changed from ' . $REQUEST_STATUS_ARR[$requestStatus] . ' to ' . $REQUEST_STATUS_ARR['I'];
			LogRequests($sess_id, $request_id, 0, 'RQ', 'U', $desc_str, $sess_user_id, '', ''); //$hName, $rtName);

			$q2 = "update concsession set cStatus='I' where iConcSessionID='$sess_id'";
			$r2 = sql_query($q2, '');

			$REQUEST_STATUS = '<a href="javascript:void(0);" class="mb-2 mr-2 badge badge-' . $REQUESTSTATUS_COLOR_ARR['I'] . '" onClick="GetUpdateRequestStatusModal();">' . strtoupper($REQUEST_STATUS_ARR['I']) . '<span class="change-status">Change</span></a>';
		} else
			$REQUEST_STATUS = '<a href="javascript:void(0);" class="mb-2 mr-2 badge badge-' . $REQUESTSTATUS_COLOR_ARR[$requestStatus] . '" onClick="GetUpdateRequestStatusModal();">' . strtoupper($REQUEST_STATUS_ARR[$requestStatus]) . '<span class="change-status">Change</span></a>';

		$html = GetHotelRoomActivityStatus($request_id, $option_id, $enquiry_id);

		$result = '1~~**~~Availability status has been changed~~**~~' . $html . '~~**~~' . $REQUEST_STATUS;
	}
} else if ($response == 'MARK_CONFIRMED_BY_RM') {
	if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id3']) && !empty($_GET['id3']) && is_numeric($_GET['id3'])) // && isset($_GET['id2']) && !empty($_GET['id2']) && is_numeric($_GET['id2']) 
	{
		$request_id = $_GET['id'];
		$sess_id = $_GET['sess_id'];
		$enquiry_id = $_GET['id2'];
		$option_id = $_GET['id3'];
		$txtdtenq = NOW;

		$q = 'select iHotelID, iRoomTypeID, iRequestDatID from concrequestdat_options where iRequestDatOptionID=' . $option_id . ' and iRequestID=' . $request_id;
		$r = sql_query($q, '');
		if (!sql_num_rows($r)) {
			echo 'Invalid Access Detected!!!';
			exit;
		}

		list($iHotelID, $iRoomTypeID, $iRequestDatID) = sql_fetch_row($r);

		if (!empty($enquiry_id)) {
			$currentEnqSatus = GetXFromYID('select cStatus from concrequest_hotelenq where iHotelEnqID=' . $enquiry_id . ' and iRequestID=' . $request_id . ' and iRequestDatOptionID=' . $option_id);

			$qSTR = '';
			if ($currentEnqSatus == 'RS')
				$qSTR = "cAvailable='Y', dtResponse='" . NOW . "', iAvailable_UserID='$sess_user_id', ";

			sql_query("update concrequest_hotelenq set $qSTR cBookReq='Y', dtBookReq='" . NOW . "', iBookRequest_UserID='$sess_user_id', cStatus='RMC' where iHotelEnqID='$enquiry_id' and iRequestID='$request_id' and iRequestDatOptionID='$option_id'", "");
		} else {
			LockTable('concrequest_hotelenq');
			$enquiry_id = NextID('iHotelEnqID', 'concrequest_hotelenq');
			sql_query("insert into concrequest_hotelenq (iHotelEnqID, iHotelID, iRoomTypeID, iRequestDatOptionID, iRequestDatID, iRequestID, iConcSessionID, dtEnq, iReqBy_UserID, cAvailable, iAvailable_UserID, dtResponse, cBookReq, dtBookReq, iBookRequest_UserID, cStatus) values ('$enquiry_id', '$iHotelID', '$iRoomTypeID', '$option_id', '$iRequestDatID', '$request_id', '$sess_id', '$txtdtenq', '$sess_user_id', 'Y', '$sess_user_id', '" . NOW . "', 'Y', '" . NOW . "', '$sess_user_id', 'RMC')", "");
			UnlockTable();
		}

		$_q = 'select iHotelID, iRoomTypeID, iConcSessionID from concrequest_hotelenq where iHotelEnqID=' . $enquiry_id . ' and iRequestID=' . $request_id . ' and iRequestDatOptionID=' . $option_id;
		$_r = sql_query($_q, '');
		list($hotel_id, $roomtype_id, $iConcSessionID) = sql_fetch_row($_r);

		if (!isset($UNI_HOTEL_ARR[$hotel_id])) $UNI_HOTEL_ARR[$hotel_id] = GetXFromYID("select vName from gen_hotel where iHotelID=$hotel_id");
		if (!isset($UNI_ROOMTYPE_ARR[$roomtype_id])) $UNI_ROOMTYPE_ARR[$roomtype_id] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$roomtype_id");

		$hName = $UNI_HOTEL_ARR[$hotel_id];
		$rtName = $UNI_ROOMTYPE_ARR[$roomtype_id];

		$desc_str = 'Room is marked as "Confirmed By RM"';
		LogRequests($iConcSessionID, $request_id, $option_id, 'RO', 'U', $desc_str, $sess_user_id, $hName, $rtName);

		SendBookRequestMail($request_id, $iRequestDatID, $option_id, $enquiry_id, $hotel_id, $hName, $rtName, $sess_user_id);

		$requestStatus = GetXFromYID('select cStatus from concrequest where iRequestID=' . $request_id);
		if ($requestStatus == 'D') {
			$q = "update concrequest set cStatus='I' where iRequestID='$request_id' and iConcSessionID='$sess_id'";
			$r = sql_query($q, '');

			$desc_str = 'Request status is changed from ' . $REQUEST_STATUS_ARR[$requestStatus] . ' to ' . $REQUEST_STATUS_ARR['I'];
			LogRequests($sess_id, $request_id, 0, 'RQ', 'U', $desc_str, $sess_user_id, '', ''); //$hName, $rtName);

			$q2 = "update concsession set cStatus='I' where iConcSessionID='$sess_id'";
			$r2 = sql_query($q2, '');

			$REQUEST_STATUS = '<a href="javascript:void(0);" class="mb-2 mr-2 badge badge-' . $REQUESTSTATUS_COLOR_ARR['I'] . '" onClick="GetUpdateRequestStatusModal();">' . strtoupper($REQUEST_STATUS_ARR['I']) . '<span class="change-status">Change</span></a>';
		} else
			$REQUEST_STATUS = '<a href="javascript:void(0);" class="mb-2 mr-2 badge badge-' . $REQUESTSTATUS_COLOR_ARR[$requestStatus] . '" onClick="GetUpdateRequestStatusModal();">' . strtoupper($REQUEST_STATUS_ARR[$requestStatus]) . '<span class="change-status">Change</span></a>';

		$html = GetHotelRoomActivityStatus($request_id, $option_id, $enquiry_id);

		$result = '1~~**~~Confirmed by RM~~**~~' . $html . '~~**~~' . $REQUEST_STATUS;
	}
} else if ($response == 'MARK_CONFIRMED_BY_HOTEL') {
	if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id3']) && !empty($_GET['id3']) && is_numeric($_GET['id3']) && isset($_GET['confirmationNum'])) // && isset($_GET['id2']) && !empty($_GET['id2']) && is_numeric($_GET['id2']) 
	{
		$request_id = $_GET['id'];
		$sess_id = $_GET['sess_id'];
		$enquiry_id = $_GET['id2'];
		$option_id = $_GET['id3'];
		$confirmationNum = $_GET['confirmationNum'];
		$txtdtenq = NOW;

		if (!empty($enquiry_id)) {
			$currentEnqSatus = GetXFromYID('select cStatus from concrequest_hotelenq where iHotelEnqID=' . $enquiry_id . ' and iRequestID=' . $request_id . ' and iRequestDatOptionID=' . $option_id);

			$qSTR = '';
			if ($currentEnqSatus == 'RS' || $currentEnqSatus == 'AV')
				$qSTR = "cBookReq='Y', dtBookReq='" . NOW . "', iBookRequest_UserID='$sess_user_id', ";

			sql_query("update concrequest_hotelenq set $qSTR cBooked='Y', dtBooked='" . NOW . "', iBooked_UserID='$sess_user_id', vBookingRef='$confirmationNum', cStatus='HC' where iHotelEnqID='$enquiry_id' and iRequestID='$request_id' and iRequestDatOptionID='$option_id'", "");
		} else {
			$q = 'select iHotelID, iRoomTypeID, iRequestDatID from concrequestdat_options where iRequestDatOptionID=' . $option_id . ' and iRequestID=' . $request_id;
			$r = sql_query($q, '');
			if (!sql_num_rows($r)) {
				echo 'Invalid Access Detected!!!';
				exit;
			}

			list($iHotelID, $iRoomTypeID, $iRequestDatID) = sql_fetch_row($r);

			LockTable('concrequest_hotelenq');
			$enquiry_id = NextID('iHotelEnqID', 'concrequest_hotelenq');
			sql_query("insert into concrequest_hotelenq (iHotelEnqID, iHotelID, iRoomTypeID, iRequestDatOptionID, iRequestDatID, iRequestID, iConcSessionID, dtEnq, iReqBy_UserID, cAvailable, iAvailable_UserID, dtResponse, cBookReq, dtBookReq, iBookRequest_UserID, cBooked, dtBooked, iBooked_UserID, vBookingRef, cStatus) values ('$enquiry_id', '$iHotelID', '$iRoomTypeID', '$option_id', '$iRequestDatID', '$request_id', '$sess_id', '$txtdtenq', '$sess_user_id', 'Y', '$sess_user_id', '" . NOW . "', 'Y', '" . NOW . "', '$sess_user_id', 'Y', '" . NOW . "', '$sess_user_id', '$confirmationNum', 'HC')", "");
			UnlockTable();
		}

		$_q = 'select iHotelID, iRoomTypeID, iConcSessionID from concrequest_hotelenq where iHotelEnqID=' . $enquiry_id . ' and iRequestID=' . $request_id . ' and iRequestDatOptionID=' . $option_id;
		$_r = sql_query($_q, '');
		list($hotel_id, $roomtype_id, $iConcSessionID) = sql_fetch_row($_r);

		if (!isset($UNI_HOTEL_ARR[$hotel_id])) $UNI_HOTEL_ARR[$hotel_id] = GetXFromYID("select vName from gen_hotel where iHotelID=$hotel_id");
		if (!isset($UNI_ROOMTYPE_ARR[$roomtype_id])) $UNI_ROOMTYPE_ARR[$roomtype_id] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$roomtype_id");

		$hName = $UNI_HOTEL_ARR[$hotel_id];
		$rtName = $UNI_ROOMTYPE_ARR[$roomtype_id];

		$desc_str = 'Room is marked as "Confirmed By Hotel"';
		LogRequests($iConcSessionID, $request_id, $option_id, 'RO', 'U', $desc_str, $sess_user_id, $hName, $rtName);

		ConvertRequestToBooking($sess_id, $request_id, $option_id, $enquiry_id);

		$requestStatus = GetXFromYID('select cStatus from concrequest where iRequestID=' . $request_id);
		if ($requestStatus == 'D') {
			$q = "update concrequest set cStatus='I' where iRequestID='$request_id' and iConcSessionID='$sess_id'";
			$r = sql_query($q, '');

			$desc_str = 'Request status is changed from ' . $REQUEST_STATUS_ARR[$requestStatus] . ' to ' . $REQUEST_STATUS_ARR['I'];
			LogRequests($sess_id, $request_id, 0, 'RQ', 'U', $desc_str, $sess_user_id, '', ''); //$hName, $rtName);

			$q2 = "update concsession set cStatus='I' where iConcSessionID='$sess_id'";
			$r2 = sql_query($q2, '');

			$REQUEST_STATUS = '<a href="javascript:void(0);" class="mb-2 mr-2 badge badge-' . $REQUESTSTATUS_COLOR_ARR['I'] . '" onClick="GetUpdateRequestStatusModal();">' . strtoupper($REQUEST_STATUS_ARR['I']) . '<span class="change-status">Change</span></a>';
		} else
			$REQUEST_STATUS = '<a href="javascript:void(0);" class="mb-2 mr-2 badge badge-' . $REQUESTSTATUS_COLOR_ARR[$requestStatus] . '" onClick="GetUpdateRequestStatusModal();">' . strtoupper($REQUEST_STATUS_ARR[$requestStatus]) . '<span class="change-status">Change</span></a>';

		$html = GetHotelRoomActivityStatus($request_id, $option_id, $enquiry_id);

		$result = '1~~**~~Confirmed by Hotel~~**~~' . $html . '~~**~~' . $REQUEST_STATUS;
	}
} else if ($response == 'UPDATE_GUEST_DETAILS') {
	if (isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id']) && isset($_POST['sess_id']) && !empty($_POST['sess_id']) && is_numeric($_POST['sess_id']) && isset($_POST['dat_id']) && !empty($_POST['dat_id']) && is_numeric($_POST['dat_id']) && isset($_POST['a']) && !empty($_POST['a']) && is_numeric($_POST['a']) && isset($_POST['r']) && !empty($_POST['r']) && is_numeric($_POST['r'])) {
		$booking_id = $_POST['id'];
		$sess_id = $_POST['sess_id'];
		$bookingdat_id = $_POST['dat_id'];
		$guest_id = $_POST['guest_id'];
		$a = $_POST['a'];
		$roomNO = $_POST['r'];

		$q = 'select iHotelID, iRoomTypeID from concbooking_dat where iBookingID=' . $booking_id . ' and iBookingDatID=' . $bookingdat_id;
		$r = sql_query($q, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($r);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$txtbooking_guestname = db_input2($_POST['txtbooking_guestname']);
		$txtbooking_guestcontactno = db_input2($_POST['txtbooking_guestcontactno']);

		$gDET = '';
		if (!empty($txtbooking_guestname)) $gDET .= $txtbooking_guestname . ' | ';
		if (!empty($txtbooking_guestcontactno)) $gDET .= $txtbooking_guestcontactno . ' | ';
		if (!empty($gDET)) $gDET = substr($gDET, 0, '-3');

		if (empty($guest_id)) {
			$guest_id = NextID('iGuestID', 'concbooking_guests');
			sql_query("insert into concbooking_guests values ('$guest_id', '$booking_id', '$bookingdat_id', '$sess_id', '$roomNO', '$txtbooking_guestname', '$txtbooking_guestcontactno', '$guest_id', 'A')", "");

			$desc_str = 'New Guest added. Guest: ' . $txtbooking_guestname . '.';
			LogRequests($sess_id, $booking_id, $bookingdat_id, 'BD', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

			$msg = 'New Guest Details have been added';
			$html = '<p class="mb-3" id="GUEST_' . $guest_id . '">' . $a . '.&nbsp;' . $gDET . '<span class="float-right ml-2 text-danger" style="cursor:pointer;" onClick="DeleteGuestDetails(\'' . $bookingdat_id . '\',\'' . $guest_id . '\',\'' . $a . '\',\'' . $roomNO . '\');"><i class="fa fa-trash"></i></span><span class="float-right" style="cursor:pointer;" onClick="UpdateGuestDetails(\'' . $bookingdat_id . '\',\'' . $guest_id . '\',\'' . $a . '\',\'' . $roomNO . '\');"><i class="fa fa-edit"></i></span></p>';
		} else {
			sql_query("update concbooking_guests set vName='$txtbooking_guestname', vContactNum='$txtbooking_guestcontactno' where iGuestID='$guest_id' and iBookingID='$booking_id' and iBookingDatID='$bookingdat_id'", "");

			$desc_str = 'Guest details Updated. Guest: ' . $txtbooking_guestname . '.';
			LogRequests($sess_id, $booking_id, $bookingdat_id, 'BD', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

			$msg = 'Guest Details have been updated';
			$html = $a . '.&nbsp;' . $gDET . '<span class="float-right ml-2 text-danger" style="cursor:pointer;" onClick="DeleteGuestDetails(\'' . $bookingdat_id . '\',\'' . $guest_id . '\',\'' . $a . '\',\'' . $roomNO . '\');"><i class="fa fa-trash"></i></span><span class="float-right" style="cursor:pointer;" onClick="UpdateGuestDetails(\'' . $bookingdat_id . '\',\'' . $guest_id . '\',\'' . $a . '\',\'' . $roomNO . '\');"><i class="fa fa-edit"></i></span>';
		}

		$guestCount = GetXFromYID('select iGuests from concbooking_dat where iBookingID=' . $booking_id . ' and iBookingDatID=' . $bookingdat_id);
		$guestAddedCount = GetXFromYID('select count(*) from concbooking_guests where iBookingID=' . $booking_id . ' and iBookingDatID=' . $bookingdat_id . ' and cStatus!="X"');

		$ADD = 'Y';
		if (($guestCount - $guestAddedCount) == 0)
			$ADD = 'N';

		$result = '1~~**~~' . $msg . '~~**~~' . $html . '~~**~~' . $ADD . '~~**~~' . $guestCount . '~~**~~' . $guestAddedCount;
	}
} else if ($response == 'DELETE_GUEST_DETAILS') {
	if (isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id']) && isset($_POST['sess_id']) && !empty($_POST['sess_id']) && is_numeric($_POST['sess_id']) && isset($_POST['dat_id']) && !empty($_POST['dat_id']) && is_numeric($_POST['dat_id']) && isset($_POST['a']) && !empty($_POST['a']) && is_numeric($_POST['a'])) {
		$booking_id = $_POST['id'];
		$sess_id = $_POST['sess_id'];
		$bookingdat_id = $_POST['dat_id'];
		$guest_id = $_POST['guest_id'];
		$a = $_POST['a'];

		sql_query("update concbooking_guests set cStatus='X' where iGuestID='$guest_id' and iBookingID='$booking_id' and iBookingDatID='$bookingdat_id'", "");

		$q = 'select iHotelID, iRoomTypeID from concbooking_dat where iBookingID=' . $booking_id . ' and iBookingDatID=' . $bookingdat_id;
		$r = sql_query($q, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($r);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$guestName = GetXFromYID('select vName from concbooking_guests where iGuestID=' . $guest_id . ' and iBookingDatID=' . $bookingdat_id);
		$desc_str = 'Guest details deleted. Deleted Guest:' . db_input2($guestName) . '.';
		LogRequests($sess_id, $booking_id, $bookingdat_id, 'BD', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

		$guestCount = GetXFromYID('select iGuests from concbooking_dat where iBookingID=' . $booking_id . ' and iBookingDatID=' . $bookingdat_id);

		$GUEST_DET = array();
		$_gq = 'select iGuestID, vName, vContactNum from concbooking_guests where iBookingDatID=' . $bookingdat_id . ' and cStatus!="X" order by iRank';
		$_gr = sql_query($_gq, '');
		if (sql_num_rows($_gr)) {
			while (list($guestid, $name, $contact) = sql_fetch_row($_gr))
				array_push($GUEST_DET, array('ID' => $guestid, 'NAME' => htmlspecialchars_decode($name), 'CONTACT' => $contact));
		}

		$msg = 'Guest details deleted successfully';

		$html = '<div id="GUEST_LIST_' . $bookingdat_id . '">';
		$html .= '<p class="mb-3">Guest List</p>';

		$g = '1';
		if (!empty($GUEST_DET) && count($GUEST_DET)) {
			foreach ($GUEST_DET as $gKEY => $gVALUE) {
				$gDET = '';
				if (!empty($gVALUE['NAME'])) $gDET .= $gVALUE['NAME'] . ' | ';
				if (!empty($gVALUE['CONTACT'])) $gDET .= $gVALUE['CONTACT'] . ' | ';
				if (!empty($gDET)) $gDET = substr($gDET, 0, '-3');

				$html .= '<p class="mb-3" id="GUEST_' . $gVALUE['ID'] . '">' . $g . '.&nbsp;' . $gDET . '<span class="float-right ml-2 text-danger" style="cursor:pointer;" onClick="DeleteGuestDetails(\'' . $bookingdat_id . '\',\'' . $gVALUE['ID'] . '\',\'' . $g . '\');"><i class="fa fa-trash"></i></span><span class="float-right" style="cursor:pointer;" onClick="UpdateGuestDetails(\'' . $bookingdat_id . '\',\'' . $gVALUE['ID'] . '\',\'' . $g . '\');"><i class="fa fa-edit"></i></span></p>';

				$guestCount = $guestCount - 1;
				$g++;
			}
		}
		$html .= '</div>';
		if (!empty($guestCount))
			$html .= '<div class="pb-2 pr-2 pt-0" id="GUEST_ADD_BUTTON_' . $bookingdat_id . '"><a href="javascript:void(0);" class="badge badge-primary float-right" onClick="UpdateGuestDetails(\'' . $bookingdat_id . '\',\'0\',\'' . $i . '\');" style="width:25%;"><i class="fa fa-plus"></i> Add Guests</a></div>';


		$result = '1~~**~~' . $msg . '~~**~~' . $html;
	}
} else if ($response == 'MARK_NO_SHOW_ROOM') {
	if (isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['dat_id']) && !empty($_GET['dat_id']) && is_numeric($_GET['dat_id']) && isset($_GET['room_id'])  && !empty($_GET['room_id']) && is_numeric($_GET['room_id'])) {
		$sess_id = $_GET['sess_id'];
		$booking_id = $_GET['id'];
		$booking_dat_id = $_GET['dat_id'];
		$room_id = $_GET['room_id'];

		sql_query("update concbooking_dat_rooms set cNoShow='Y' where iBookingID='$booking_id' and iBookingDatID='$booking_dat_id' and iRoomID='$room_id'", "");

		$html = '<span class="ml-2 badge badge-secondary float-right"><i class="fa fa-minus"></i> No Show</span>';

		$q = 'select iHotelID, iRoomTypeID from concbooking_dat where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id;
		$r = sql_query($q, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($r);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$iRoomNo = GetXFromYID('select iRoomNo from concbooking_dat_rooms where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id . ' and iRoomID=' . $room_id);

		$desc_str = 'Room No: ' . $iRoomNo . '. Marked as No Show.';
		LogRequests($sess_id, $booking_id, $booking_dat_id, 'BR', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

		$html2 = '';
		$html3 = '<span class="ml-2 badge badge-warning float-right" style="cursor:pointer;" onClick="MarkRoomReset(\'' . $booking_dat_id . '\',\'' . $room_id . '\');"><i class="fa fa-undo"></i> Reset</span>';

		$result = '1~~**~~Marked No Show~~**~~' . $html . '~~**~~' . $html2 . '~~**~~' . $html3;
	}
} else if ($response == 'MARK_CHECKED_IN_ROOM') {
	if (isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['dat_id']) && !empty($_GET['dat_id']) && is_numeric($_GET['dat_id']) && isset($_GET['room_id'])  && !empty($_GET['room_id']) && is_numeric($_GET['room_id'])) {
		$sess_id = $_GET['sess_id'];
		$booking_id = $_GET['id'];
		$booking_dat_id = $_GET['dat_id'];
		$room_id = $_GET['room_id'];

		sql_query("update concbooking_dat_rooms set cCheckedIn='Y' where iBookingID='$booking_id' and iBookingDatID='$booking_dat_id' and iRoomID='$room_id'", "");

		$html = '<span class="ml-2 badge badge-success float-right"><i class="fa fa-check"></i> Checked In</span>';

		$q = 'select iHotelID, iRoomTypeID from concbooking_dat where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id;
		$r = sql_query($q, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($r);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$q2 = 'select iRoomNo, cCheckedOut from concbooking_dat_rooms where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id . ' and iRoomID=' . $room_id;
		$r2 = sql_query($q2, '');
		list($iRoomNo, $cCheckedOut) = sql_fetch_row($r2);

		$desc_str = 'Room No: ' . $iRoomNo . '. Marked as Checked In.';
		LogRequests($sess_id, $booking_id, $booking_dat_id, 'BR', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

		$html2 = '<span class="ml-2 badge badge-alternate float-right" style="cursor:pointer;" onClick="MarkRoomCheckedOut(\'' . $booking_dat_id . '\',\'' . $room_id . '\');"><i class="fa fa-check"></i> Mark Checked Out</span>';
		if ($cCheckedOut == 'Y')
			$html2 .= '<span class="ml-2 badge badge-success float-right"><i class="fa fa-check"></i> Checked Out</span>';

		$html3 = '<span class="ml-2 badge badge-warning float-right" style="cursor:pointer;" onClick="MarkRoomReset(\'' . $booking_dat_id . '\',\'' . $room_id . '\');"><i class="fa fa-undo"></i> Reset</span>';

		$result = '1~~**~~Marked Checked In~~**~~' . $html . '~~**~~' . $html2 . '~~**~~' . $html3;
	}
} else if ($response == 'MARK_CHECKED_OUT_ROOM') {
	if (isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['dat_id']) && !empty($_GET['dat_id']) && is_numeric($_GET['dat_id']) && isset($_GET['room_id'])  && !empty($_GET['room_id']) && is_numeric($_GET['room_id'])) {
		$sess_id = $_GET['sess_id'];
		$booking_id = $_GET['id'];
		$booking_dat_id = $_GET['dat_id'];
		$room_id = $_GET['room_id'];

		sql_query("update concbooking_dat_rooms set cCheckedOut='Y' where iBookingID='$booking_id' and iBookingDatID='$booking_dat_id' and iRoomID='$room_id'", "");

		$q = 'select iHotelID, iRoomTypeID from concbooking_dat where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id;
		$r = sql_query($q, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($r);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$iRoomNo = GetXFromYID('select iRoomNo from concbooking_dat_rooms where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id . ' and iRoomID=' . $room_id);

		$desc_str = 'Room No: ' . $iRoomNo . '. Marked as Checked Out.';
		LogRequests($sess_id, $booking_id, $booking_dat_id, 'BR', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

		$html = '<span class="ml-2 badge badge-success float-right"><i class="fa fa-check"></i> Checked Out</span>';

		$result = '1~~**~~Marked Checked Out~~**~~' . $html;
	}
} else if ($response == 'MARK_RESET_ROOM') {
	if (isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['dat_id']) && !empty($_GET['dat_id']) && is_numeric($_GET['dat_id']) && isset($_GET['room_id'])  && !empty($_GET['room_id']) && is_numeric($_GET['room_id'])) {
		$sess_id = $_GET['sess_id'];
		$booking_id = $_GET['id'];
		$booking_dat_id = $_GET['dat_id'];
		$room_id = $_GET['room_id'];

		sql_query("update concbooking_dat_rooms set cCheckedIn='N', cCheckedOut='N', cNoShow='N' where iBookingID='$booking_id' and iBookingDatID='$booking_dat_id' and iRoomID='$room_id'", "");

		$html = '<span class="ml-2 badge badge-secondary float-right"><i class="fa fa-minus"></i> No Show</span>';

		$q = 'select iHotelID, iRoomTypeID from concbooking_dat where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id;
		$r = sql_query($q, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($r);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$iRoomNo = GetXFromYID('select iRoomNo from concbooking_dat_rooms where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id . ' and iRoomID=' . $room_id);

		$desc_str = 'Room No: ' . $iRoomNo . '. Room details have been reset.';
		LogRequests($sess_id, $booking_id, $booking_dat_id, 'BR', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

		$html = '<span class="ml-2 badge badge-alternate float-right" style="cursor:pointer;" onClick="MarkRoomCheckedIn(\'' . $booking_dat_id . '\',\'' . $room_id . '\');"><i class="fa fa-check"></i> Mark Checked In</span>';
		$html2 = '<span class="ml-2 badge badge-alternate float-right" style="cursor:pointer;" onClick="MarkRoomNoShow(\'' . $booking_dat_id . '\',\'' . $room_id . '\');"><i class="fa fa-check"></i> Mark No Show</span>';
		$html3 = '';

		$result = '1~~**~~Room details reset~~**~~' . $html . '~~**~~' . $html2 . '~~**~~' . $html3;
	}
} else if ($response == 'UPDATE_HOTEL_ROOM_TARIFF_DETAILS') {
	if (isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id']) && isset($_POST['h_id']) && !empty($_POST['h_id']) && is_numeric($_POST['h_id']) && isset($_POST['rt_id']) && !empty($_POST['rt_id']) && is_numeric($_POST['rt_id'])) {
		$h_id = $_POST['h_id'];
		$rt_id = $_POST['rt_id'];
		$optiondat_id = $_POST['id'];
		$txtroomtariff_idstr = $_POST['txtroomtariff_idstr'];

		$GRAND_TOTAL = 0;

		$idSTR = explode(',', $txtroomtariff_idstr);
		if (!empty($idSTR) && count($idSTR)) {
			foreach ($idSTR as $ID) {
				$txtroomrate = $_POST['txtroomrate_' . $ID];
				$txtroomxadultrate = $_POST['txtroomxadultrate_' . $ID];

				$q = 'select iNumOfDays, iExtraAdults, iExtraChild from concrequestdat_options_tariff where iROTariffID=' . $ID . ' and iHotelID=' . $h_id . ' and iRoomTypeID=' . $rt_id . ' and iRequestDatOptionID=' . $optiondat_id;
				$r = sql_query($q, '');
				list($iNumOfDays, $iExtraAdults, $iExtraChild) = sql_fetch_row($r);

				if (empty($iExtraAdults)) $txtroomxadultrate = 0;

				$TOTAL = ($txtroomrate * $iNumOfDays) + ($iExtraAdults * $txtroomxadultrate * $iNumOfDays);

				sql_query("update concrequestdat_options_tariff set fRoomRate='$txtroomrate', fExtaAdultCharges='$txtroomxadultrate', fTotal='$TOTAL' where iROTariffID='$ID' and iHotelID='$h_id' and iRoomTypeID='$rt_id' and iRequestDatOptionID='$optiondat_id'", "");

				$GRAND_TOTAL = $GRAND_TOTAL + $TOTAL;
			}
		}

		if (!empty($GRAND_TOTAL))
			sql_query("update concrequestdat_options set fCost='$GRAND_TOTAL' where iRequestDatOptionID='$optiondat_id'", "");

		$result = '1~~**~~Hotel Room Tariff details have been updated';
	}
} else if ($response == 'UPDATE_HOTEL_ROOM_TARIFF_DETAILS2') {
	if (isset($_POST['r_id']) && !empty($_POST['r_id']) && is_numeric($_POST['r_id']) && isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id']) && isset($_POST['id2']) && !empty($_POST['id2']) && is_numeric($_POST['id2']) && isset($_POST['h_id']) && !empty($_POST['h_id']) && is_numeric($_POST['h_id']) && isset($_POST['rt_id']) && !empty($_POST['rt_id']) && is_numeric($_POST['rt_id'])) {
		$h_id = $_POST['h_id'];
		$rt_id = $_POST['rt_id'];
		$request_id = $_POST['r_id'];
		$requestdat_id = $_POST['id2'];
		$optiondat_id = $_POST['id'];
		$txtroom_nostr = $_POST['txtroom_nostr'];

		$q = 'select dCheckin, dCheckOut from concrequest where iRequestID=' . $r_id;
		$r = sql_query($q, '');
		list($chkInDate, $chkOutDate) = sql_fetch_row($r);

		$q2 = 'select iNumPax, iNumRooms from concrequestdat where iRequestDatID=' . $requestdat_id . ' and iRequestID=' . $r_id;
		$r2 = sql_query($q2, '');
		list($guests, $rooms) = sql_fetch_row($r2);

		$_rq = 'select iPax, iMaxPax from gen_roomtype where iHotelID=' . $h_id . ' and iRoomTypeID=' . $rt_id;
		$_rr = sql_query($_rq, '');
		list($PAX, $MAX_PAX) = sql_fetch_row($_rr);

		$TOTAL_GUEST = $EXTRA_ADULT = $EXTRA_CHILD = 0;
		$ROOM_ARR = array();
		for ($r = 1; $r <= $rooms; $r++) {
			$ROOM_ARR[$r] = array('GUEST' => 0, 'X_ADULT' => 0, 'X_CHILD' => 0);
		}

		for ($g = 1; $g <= $guests; $g++) {
			foreach ($ROOM_ARR as $rKEY => $rVALUE) {
				if ($rVALUE['GUEST'] < $PAX) {
					$ROOM_ARR[$rKEY]['GUEST'] = $ROOM_ARR[$rKEY]['GUEST'] + 1;
					$ROOM_ARR =  MultiDimensionalArraySort($ROOM_ARR, 'GUEST', SORT_ASC);
					break;
				} else {
					$TOTAL_ROOM_PAX = $rVALUE['GUEST'] + $rVALUE['X_ADULT'];
					if ($TOTAL_ROOM_PAX < $MAX_PAX) {
						$ROOM_ARR[$rKEY]['X_ADULT'] = $ROOM_ARR[$rKEY]['X_ADULT'] + 1;
						$ROOM_ARR = MultiDimensionalArraySort($ROOM_ARR, 'X_ADULT', SORT_ASC);
						break;
					}
				}
			}
		}

		$NOOFDAYS = DateDiff($chkInDate, $chkOutDate);

		$GRAND_TOTAL = 0;
		$idSTR = explode(',', $txtroom_nostr);
		if (!empty($idSTR) && count($idSTR)) {
			foreach ($idSTR as $ID) {
				//sql_query('update concrequestdat_options_tariff set cStatus="X" where iRequestDatOptionID='.$optiondat_id.' and iHotelID='.$h_id.' and iRoomTypeID='.$rt_id.' and iRoomID='.$ID);
				$txtroom_str = (isset($_POST['txtroomno_' . $ID . '_str'])) ? $_POST['txtroomno_' . $ID . '_str'] : '0';
				if (!empty($txtroom_str)) {
					$idSTR2 = explode(',', $txtroom_str);
					if (!empty($idSTR2) && count($idSTR2)) {
						foreach ($idSTR2 as $ID2) {
							$txtroomrate = $_POST['txtroomrate_' . $ID . '_' . $ID2];
							$txtroomxadultrate = $_POST['txtroomxadultrate_' . $ID . '_' . $ID2];
							$txtecchildharge = 0;

							$OCCUPANCY = ($ROOM_ARR[$ID]['GUEST'] < $PAX) ? 'S' : 'D';
							$txtnumadults = $ROOM_ARR[$ID]['GUEST'];
							$txtextraadults = $ROOM_ARR[$ID]['X_ADULT'];
							$txtextrachild = $ROOM_ARR[$ID]['X_CHILD'];

							$total_room_val = ($txtroomrate + ($txtroomxadultrate * $txtextraadults) + ($txtecchildharge * $txtextrachild)) * $NOOFDAYS;

							$txtid = NextID('iROTariffID', 'concrequestdat_options_tariff');
							$q = "insert into concrequestdat_options_tariff values ('$txtid', '$optiondat_id', '$h_id', '$rt_id', '$ID', '0', '$OCCUPANCY', '$chkInDate', '$chkOutDate', '$NOOFDAYS', '$txtnumadults', '$txtextraadults', '$txtroomxadultrate', '0', '$txtextrachild', '$txtecchildharge', '$txtroomrate', '$total_room_val', 'A')";
							$r = sql_query($q, 'RES_E.213');

							$GRAND_TOTAL += $total_room_val;
						}
					}
				}
			}
		}

		$str = $GRAND_TOTAL;
		if (!empty($GRAND_TOTAL))
			sql_query("update concrequestdat_options set fCost='$GRAND_TOTAL' where iRequestDatOptionID='$optiondat_id'", "");

		$result = '1~~**~~Hotel Room Tariff details have been updated';
	}
} elseif ($response == 'UPDATE_REQUEST_STATUS') {
	if (isset($_POST['r_id']) && !empty($_POST['r_id']) && is_numeric($_POST['r_id']) && isset($_POST['sess_id']) && !empty($_POST['sess_id']) && is_numeric($_POST['sess_id'])) {
		$r_id = $_POST['r_id'];
		$sess_id = $_POST['sess_id'];
		$rdrequeststatus_modal = db_input($_POST['rdrequeststatus_modal']);

		$earlier_requeststatus = GetXFromYID('select cStatus from concrequest where iRequestID=' . $r_id . ' and iConcSessionID=' . $sess_id);

		$q = "update concrequest set cStatus='$rdrequeststatus_modal' where iRequestID='$r_id' and iConcSessionID='$sess_id'";
		$r = sql_query($q, '');

		$desc_str = 'Request status is changed from ' . $REQUEST_STATUS_ARR[$earlier_requeststatus] . ' to ' . $REQUEST_STATUS_ARR[$rdrequeststatus_modal];
		LogRequests($sess_id, $r_id, 0, 'RQ', 'U', $desc_str, $sess_user_id, '', '');

		$q2 = "update concsession set cStatus='$rdrequeststatus_modal' where iConcSessionID='$sess_id'";
		$r2 = sql_query($q2, '');

		$html = '<a href="javascript:void(0);" class="mb-2 mr-2 badge badge-' . $REQUESTSTATUS_COLOR_ARR[$rdrequeststatus_modal] . '" onClick="GetUpdateRequestStatusModal();">' . strtoupper($REQUEST_STATUS_ARR[$rdrequeststatus_modal]) . '<span class="change-status">Change</span></a>';

		$result = '1~*~Request status successfully updated~*~' . $html;
	}
} elseif ($response == 'UPDATE_ACCOMODATION_GUEST_DETAILS') {
	if (isset($_POST['sess_id']) && !empty($_POST['sess_id']) && is_numeric($_POST['sess_id']) && isset($_POST['r_id']) && !empty($_POST['r_id']) && is_numeric($_POST['r_id']) && isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id'])) {
		$sess_id = $_POST['sess_id'];
		$r_id = $_POST['r_id'];
		$id = $_POST['id'];
		$n = $_POST['n'];

		$_hq = 'select iHotelID, iRoomTypeID from concrequestdat_options where iRequestID=' . $r_id . ' and iRequestDatID=' . $id;
		$_hr = sql_query($_hq, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($_hr);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$ROOM_ARR = array();
		$_rq2 = 'select iRoomNo, iGuest from concrequestdat_rooms where iRequestID=' . $r_id . ' and iRequestDatID=' . $id . ' and cStatus="A" order by iRoomNo';
		$_rr2 = sql_query($_rq2, '');
		if (sql_num_rows($_rr2)) {
			while (list($iRoomNo, $iGuest) = sql_fetch_row($_rr2))
				$ROOM_ARR[$iRoomNo] = $iGuest;
		}

		sql_query("delete from concrequestdat_guests where iRequestID='$r_id' and iRequestDatID='$id'", "");

		$ROOM_GUEST_ARR = array();
		if (!empty($ROOM_ARR) && count($ROOM_ARR)) {
			foreach ($ROOM_ARR as $rKEY => $rVALUE) {
				$txtguestName = $_POST['txtguestName_' . $rKEY];
				$txtguestNo = $_POST['txtguestNo_' . $rKEY];

				foreach ($txtguestName as $gKEY => $gVALUE) {
					$guestNAME = db_input2($gVALUE);
					$guestN0 = db_input2($txtguestNo[$gKEY]);

					if (!empty($guestNAME)) {
						LockTable('concrequestdat_guests');
						$conc_guestid = NextID('iGuestID', 'concrequestdat_guests');
						sql_query("insert into concrequestdat_guests values ('$conc_guestid', '$r_id', '$id', '$rKEY', '$guestNAME', '$guestN0', '$conc_guestid', 'A')", "");
						UnlockTable();

						$desc_str = 'Guest details updated. Guest: ' . $guestNAME;
						LogRequests($sess_id, $r_id, $id, 'RG', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

						//if(!empty($guestN0)) $guestNAME .= ' ('.$guestN0.')';
						if (!isset($ROOM_GUEST_ARR[$rKEY])) $ROOM_GUEST_ARR[$rKEY] = array();

						array_push($ROOM_GUEST_ARR[$rKEY], htmlspecialchars_decode($guestNAME));
					}
				}
			}
		}

		$GUESTLIST = '';
		if (!empty($ROOM_GUEST_ARR) && count($ROOM_GUEST_ARR)) {
			foreach ($ROOM_GUEST_ARR as $ROOM_NO => $ROOM_GUEST) {
				$GUESTLIST .= '<strong>Room ' . $ROOM_NO . '</strong>: ';
				foreach ($ROOM_GUEST as $gKEY => $gVALUE)
					$GUESTLIST .= $gVALUE . ', ';

				if (!empty($GUESTLIST)) $GUESTLIST = substr($GUESTLIST, '0', '-2') . ' | ';
			}
		}
		if (!empty($GUESTLIST)) $GUESTLIST = substr($GUESTLIST, '0', '-3');
		else $GUESTLIST = '-NA-';

		$html = '<a href="javascript:void(0);" onClick="GetAccomodationGuestListForm(\'' . $id . '\',\'' . $n . '\');"><strong><u>Guest List:</u></strong></a> ' . stripslashes(htmlspecialchars_decode($GUESTLIST));

		$result = '1~*~Guest details successfully updated~*~' . $html;
	}
} elseif ($response == 'UPDATE_BOOKING_STATUS') {
	if (isset($_POST['b_id']) && !empty($_POST['b_id']) && is_numeric($_POST['b_id']) && isset($_POST['sess_id']) && !empty($_POST['sess_id']) && is_numeric($_POST['sess_id'])) {
		$b_id = $_POST['b_id'];
		$sess_id = $_POST['sess_id'];
		$rdbookingstatus_modal = db_input($_POST['rdrequeststatus_modal']);
		$txtbooking_cancelled = (isset($_POST['txtbooking_cancelled'])) ? db_input2($_POST['txtbooking_cancelled']) : '';

		if ($rdbookingstatus_modal == 'C') $q_str = ", dtCancelled='" . NOW . "', vCancelaltionReason='$txtbooking_cancelled'";
		else $q_str = '';

		$earlier_bookingstatus = GetXFromYID('select cStatus from concbooking where iBookingID=' . $b_id . ' and iConcSessionID=' . $sess_id);

		$q = "update concbooking set cStatus='$rdbookingstatus_modal' $q_str where iBookingID='$b_id' and iConcSessionID='$sess_id'";
		$r = sql_query($q, '');

		$desc_str = 'Booking status is changed from ' . $BOOKING_STATUS_ARR[$earlier_bookingstatus] . ' to ' . $BOOKING_STATUS_ARR[$rdbookingstatus_modal];
		LogRequests($sess_id, $b_id, 0, 'BO', 'U', $desc_str, $sess_user_id, '', '');

		$html = '<a href="javascript:void(0);" class="mb-2 mr-2 badge badge-' . $BOOKINGSTATUS_COLOR_ARR[$rdbookingstatus_modal] . '" onClick="GetUpdateBookingStatusModal();">' . strtoupper($BOOKING_STATUS_ARR[$rdrequeststatus_modal]) . '<span class="change-status">Change</span></a>';

		$result = '1~*~Booking status successfully updated~*~' . $html;
	}
} else if ($response == 'GET_RM_PROPERTY') {
	if (isset($_GET['rm_id'])) {
		$str = '';
		$rm_id = $_GET['rm_id'];

		$PROPERTY_ARR = array();
		if (!empty($rm_id)) $PROPERTY_ARR = GetXArrFromYID('select p.iPropertyID, p.vName from property as p join users_property_assoc up on p.iPropertyID=up.iPropertyID where p.cStatus="A" and up.iUserID=' . $rm_id, '3');

		$str = FillMultiCombo('', 'cmbnewrequest_rmproperty', 'COMBO', 'Y', $PROPERTY_ARR, 'data-live-search="true" style="background-color:#fff !important;"', 'form-control form-control-sm multiSELECT_STYLE multiSELECT2_');

		$result = $str;
	} else
		$result = '2~*~Invalid Access Detected!!!';
} else if ($response == 'UPDATE_ROOM_AVAILABILITY_BY_HOTEL') {
	if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['av']) && !empty($_GET['av'])) {
		$enquiry_id = $_GET['id'];
		$available = $_GET['av'];
		$txtdtenq = NOW;

		$availableSTATUS = ($available == 'Y') ? 'AV' : 'NAV';

		$_q = 'select iHotelID, iRoomTypeID, iRequestID, iRequestDatOptionID, iConcSessionID from concrequest_hotelenq where iHotelEnqID=' . $enquiry_id;
		$_r = sql_query($_q, '');
		list($hotel_id, $roomtype_id, $request_id, $option_id, $iConcSessionID) = sql_fetch_row($_r);

		sql_query("update concrequest_hotelenq set cAvailable='$available', iAvailable_UserID='$sess_user_id', dtResponse='" . NOW . "', cStatus='$availableSTATUS' where iHotelEnqID='$enquiry_id' and iRequestID='$request_id' and iRequestDatOptionID='$option_id'", "");

		if (!isset($UNI_HOTEL_ARR[$hotel_id])) $UNI_HOTEL_ARR[$hotel_id] = GetXFromYID("select vName from gen_hotel where iHotelID=$hotel_id");
		if (!isset($UNI_ROOMTYPE_ARR[$roomtype_id])) $UNI_ROOMTYPE_ARR[$roomtype_id] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$roomtype_id");

		$HOTEL = $UNI_HOTEL_ARR[$hotel_id];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$roomtype_id];

		$available = ($available == 'Y') ? 'Available' : 'Not Available';

		$desc_str = 'Room is marked as ' . $available;
		LogRequests($iConcSessionID, $request_id, $option_id, 'RO', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

		$result = '1~~**~~Availability status has been changed';
	}
} else if ($response == 'ENQUIRY_CONFIRMED_BY_HOTEL') {
	if (isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['txtconfirmation_no'])) {
		$enquiry_id = $_GET['id'];
		$txtconfirmation_no = $_GET['txtconfirmation_no'];

		$_q = 'select iHotelID, iRoomTypeID, iRequestID, iRequestDatOptionID, iConcSessionID from concrequest_hotelenq where iHotelEnqID=' . $enquiry_id;
		$_r = sql_query($_q, '');
		list($hotel_id, $roomtype_id, $request_id, $option_id, $sess_id) = sql_fetch_row($_r);

		$txtdtenq = NOW;

		sql_query("update concrequest_hotelenq set cBooked='Y', dtBooked='" . NOW . "', iBooked_UserID='$sess_user_id', vBookingRef='$txtconfirmation_no', cStatus='HC' where iHotelEnqID='$enquiry_id' and iRequestID='$request_id' and iRequestDatOptionID='$option_id'", "");

		if (!isset($UNI_HOTEL_ARR[$hotel_id])) $UNI_HOTEL_ARR[$hotel_id] = GetXFromYID("select vName from gen_hotel where iHotelID=$hotel_id");
		if (!isset($UNI_ROOMTYPE_ARR[$roomtype_id])) $UNI_ROOMTYPE_ARR[$roomtype_id] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$roomtype_id");

		$HOTEL = $UNI_HOTEL_ARR[$hotel_id];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$roomtype_id];

		$desc_str = 'Room is marked as "Confirmed By Hotel"';
		LogRequests($sess_id, $request_id, $option_id, 'RO', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

		$booking_id = ConvertRequestToBooking($sess_id, $request_id, $option_id, $enquiry_id);

		$result = '1~~**~~Confirmed by Hotel~~**~~' . $booking_id;
	}
} else if ($response == 'MARK_CHECKED_IN_ROOM_BY_HOTEL') {
	if (isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['dat_id']) && !empty($_GET['dat_id']) && is_numeric($_GET['dat_id']) && isset($_GET['room_id']) && !empty($_GET['room_id']) && is_numeric($_GET['room_id'])) {
		$sess_id = $_GET['sess_id'];
		$booking_id = $_GET['id'];
		$booking_dat_id = $_GET['dat_id'];
		$room_id = $_GET['room_id'];

		sql_query("update concbooking_dat_rooms set cCheckedIn='Y' where iBookingID='$booking_id' and iBookingDatID='$booking_dat_id' and iRoomID='$room_id'", "");

		$html = '<div class="btn py-1 w-auto-bx text-center btn-success">Checked In</div>';

		$q = 'select iHotelID, iRoomTypeID from concbooking_dat where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id;
		$r = sql_query($q, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($r);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$q2 = 'select iRoomNo, cCheckedOut from concbooking_dat_rooms where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id . ' and iRoomID=' . $room_id;
		$r2 = sql_query($q2, '');
		list($iRoomNo, $cCheckedOut) = sql_fetch_row($r2);

		$desc_str = 'Room No: ' . $iRoomNo . '. Marked as Checked In.';
		LogRequests($sess_id, $booking_id, $booking_dat_id, 'BR', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

		$html .= '<div class="btn py-1 w-auto-bx text-center btn-secondary" onClick="UpdateRoomCheckedOUT(\'' . $booking_dat_id . '\');" style="cursor:pointer;">Mark Checked Out</div>';
		if ($cCheckedOut == 'Y')
			$html .= '<div class="btn py-1 w-auto-bx text-center btn-success">Checked  Out</div>';

		$result = '1~~**~~Marked Checked In~~**~~' . $html;
	}
} else if ($response == 'MARK_CHECKED_OUT_ROOM_BY_HOTEL') {
	if (isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['dat_id']) && !empty($_GET['dat_id']) && is_numeric($_GET['dat_id']) && isset($_GET['room_id']) && !empty($_GET['room_id']) && is_numeric($_GET['room_id'])) {
		$sess_id = $_GET['sess_id'];
		$booking_id = $_GET['id'];
		$booking_dat_id = $_GET['dat_id'];
		$room_id = $_GET['room_id'];

		sql_query("update concbooking_dat_rooms set cCheckedOut='Y' where iBookingID='$booking_id' and iBookingDatID='$booking_dat_id' and iRoomID='$room_id'", "");

		$q = 'select iHotelID, iRoomTypeID from concbooking_dat where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id;
		$r = sql_query($q, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($r);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$iRoomNo = GetXFromYID('select iRoomNo from concbooking_dat_rooms where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id . ' and iRoomID=' . $room_id);

		$desc_str = 'Room No: ' . $iRoomNo . '. Marked as Checked Out.';
		LogRequests($sess_id, $booking_id, $booking_dat_id, 'BR', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

		$html = '<div class="btn py-1 w-auto-bx text-center btn-success">Checked In</div>';
		$html .= '<div class="btn py-1 w-auto-bx text-center btn-success">Checked  Out</div>';

		$result = '1~~**~~Marked Checked Out~~**~~' . $html;
	}
} else if ($response == 'MARK_NO_SHOW_ROOM_BY_HOTEL') {
	if (isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['dat_id']) && !empty($_GET['dat_id']) && is_numeric($_GET['dat_id']) && isset($_GET['room_id']) && !empty($_GET['room_id']) && is_numeric($_GET['room_id'])) {
		$sess_id = $_GET['sess_id'];
		$booking_id = $_GET['id'];
		$booking_dat_id = $_GET['dat_id'];
		$room_id = $_GET['room_id'];

		sql_query("update concbooking_dat_rooms set cNoShow='Y' where iBookingID='$booking_id' and iBookingDatID='$booking_dat_id' and iRoomID='$room_id'", "");

		$q = 'select iHotelID, iRoomTypeID from concbooking_dat where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id;
		$r = sql_query($q, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($r);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$iRoomNo = GetXFromYID('select iRoomNo from concbooking_dat_rooms where iBookingID=' . $booking_id . ' and iBookingDatID=' . $booking_dat_id . ' and iRoomID=' . $room_id);

		$desc_str = 'Room No: ' . $iRoomNo . '. Marked as No Show.';
		LogRequests($sess_id, $booking_id, $booking_dat_id, 'BR', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

		$html = '<div class="btn py-1 w-auto-bx text-center status-no-show">NO SHOW</div>';

		$result = '1~~**~~Marked Checked Out~~**~~' . $html;
	}
} else if ($response == 'UPDATE_REQUEST') {
	if (isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id']) && isset($_POST['txteditrequest_chkin']) && !empty($_POST['txteditrequest_chkin']) && isset($_POST['txteditrequest_chkout']) && !empty($_POST['txteditrequest_chkout']) && isset($_POST['cmbeditrequest_rmproperty']) && !empty($_POST['cmbeditrequest_rmproperty'])) {
		$request_id = $_POST['id'];
		$txteditrequest_chkin = $_POST['txteditrequest_chkin'];
		$txteditrequest_chkout = $_POST['txteditrequest_chkout'];
		$cmbeditrequest_rmproperty = $_POST['cmbeditrequest_rmproperty'];
		$txtedirequest_request = db_input2($_POST['txtedirequest_request']);

		$q = 'select iUserID_RM, dCheckin, dCheckOut, vRequest, $iConcSessionID from concrequest where iRequestID=' . $request_id;
		$r = sql_query($q, '');
		list($iUserID_RM, $dCheckin, $dCheckOut, $vRequest, $iConcSessionID) = sql_fetch_row($r);

		$conc_requestid = $request_id; //NextID('iRequestID', 'concrequest');
		sql_query("update concrequest set vRequest='$txtedirequest_request', dCheckin='$txteditrequest_chkin', dCheckOut='$txteditrequest_chkout' where iRequestID='$request_id'", "");

		$desc_str = '';
		if ($dCheckin != $txteditrequest_chkin) $desc_str .= 'CheckIn Date: Changed from ' . FormatDate($dCheckin, 'B') . ' to ' . FormatDate($txteditrequest_chkin, 'B') . ', ';
		if ($dCheckOut != $txteditrequest_chkout) $desc_str .= 'CheckOut Date: Changed from ' . FormatDate($dCheckOut, 'B') . ' to ' . FormatDate($txteditrequest_chkout, 'B') . ', ';
		if ($txtedirequest_request != $vRequest) $desc_str .= 'Special Request: updated to "' . stripslashes(htmlspecialchars_decode($txtedirequest_request)) . '", ';

		$propertyAdded = '';
		if (!empty($cmbeditrequest_rmproperty) && count($cmbeditrequest_rmproperty)) {
			sql_query("delete from concrequest_property where iRequestID='$request_id'", "");
			foreach ($cmbeditrequest_rmproperty as $property_id) {
				if (!empty($property_id)) {
					sql_query("insert into concrequest_property values ('$request_id', '$property_id')", "");
					$propertyAdded .= $property_id . ',';
				}
			}

			if (!empty($propertyAdded)) {
				$propertyAdded = GetIDString2('select vName from property where iPropertyID IN (' . substr($propertyAdded, 0, '-1') . ')');
				if (!empty($propertyAdded) && $propertyAdded != '-1')
					$desc_str .= 'Properties Added: ' . $propertyAdded . ', ';
			}
		}

		if (!empty($desc_str)) {
			$desc_str = substr($desc_str, 0, '-2');
			LogRequests($iConcSessionID, $conc_requestid, 0, 'RQ', 'U', $desc_str, $sess_user_id, '', '');
		}

		if ($dCheckin != $txteditrequest_chkin || $dCheckOut != $txteditrequest_chkout)
			UpdateAccomodationRoomRates($request_id);

		$result = '1~~**~~Request details have been updated';
	}
} elseif ($response == 'UPDATE_BOOKINGACCOMODATION_NOTE_DETAILS') {
	if (isset($_POST['sess_id']) && !empty($_POST['sess_id']) && is_numeric($_POST['sess_id']) && isset($_POST['b_id']) && !empty($_POST['b_id']) && is_numeric($_POST['b_id']) && isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id'])) {
		$sess_id = $_POST['sess_id'];
		$b_id = $_POST['b_id'];
		$id = $_POST['id'];
		$roomtype = db_input2($_POST['roomtype']);
		$txtbillingnotes = db_input2($_POST['txtbillingnotes']);

		$q = "update concbooking_dat set vNotes='$txtbillingnotes' where iBookingID='$b_id' and iBookingDatID='$id'";
		$r = sql_query($q, '');

		$q2 = 'select iHotelID, iRoomTypeID from concbooking_dat where iBookingID=' . $b_id . ' and iBookingDatID=' . $id;
		$r2 = sql_query($q2, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($r2);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		if (empty($txtbillingnotes)) $desc_str = 'Notes updated';
		else $desc_str = 'Notes updated to: ' . $txtbillingnotes;
		LogRequests($sess_id, $b_id, $id, 'RD', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

		if (empty($txtbillingnotes)) $txtbillingnotes = '-NA-';

		$html = '<a href="javascript:void(0);" onClick="GetAccomodationNoteForm(\'' . $id . '\');"><strong><u>Note:</u></strong></a> ' . stripslashes(htmlspecialchars_decode($txtbillingnotes));

		$result = '1~*~' . $roomtype . ' note successfully updated~*~' . $html;
	}
} elseif ($response == 'UPDATE_ACCOMODATION_ROOM_BILLING_INSTRUCTIONS_DETAILS') {
	if (isset($_POST['sess_id']) && !empty($_POST['sess_id']) && is_numeric($_POST['sess_id']) && isset($_POST['r_id']) && !empty($_POST['r_id']) && is_numeric($_POST['r_id']) && isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id'])) {
		$sess_id = $_POST['sess_id'];
		$r_id = $_POST['r_id'];
		$id = $_POST['id'];
		$n = $_POST['n'];

		$_hq = 'select iHotelID, iRoomTypeID from concrequestdat_options where iRequestID=' . $r_id . ' and iRequestDatID=' . $id;
		$_hr = sql_query($_hq, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($_hr);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$ROOMBILLINGINSTRUCTIONS_DET = array();
		$ROOM_ARR = GetXArrFromYID('select iRoomID, iRoomNo from concrequestdat_rooms where iRequestID=' . $r_id . ' and iRequestDatID=' . $id . ' and cStatus="A" order by iRoomID', '3');
		if (!empty($ROOM_ARR) && count($ROOM_ARR)) {
			foreach ($ROOM_ARR as $rID => $rVALUE) {
				$txtbillinginstructions = db_input2($_POST['txtbillinginstructions_' . $rVALUE]);

				$q = "update concrequestdat_rooms set vBillingInstructions='$txtbillinginstructions' where iRequestID='$r_id' and iRequestDatID='$id' and iRoomNo='$rVALUE' and iRoomID='$rID'";
				$r = sql_query($q, '');

				$desc_str = 'Room ' . $rVALUE . ' billing instructions updated.';
				LogRequests($sess_id, $r_id, $id, 'RR', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

				if (empty($txtbillinginstructions)) $txtbillinginstructions = '-NA-';
				$ROOMBILLINGINSTRUCTIONS_DET[$rVALUE] = stripslashes(htmlspecialchars_decode($txtbillinginstructions));
			}
		}

		$BILLINGINSTRUCTIONS_DET = '';
		if (!empty($ROOMBILLINGINSTRUCTIONS_DET) && count($ROOMBILLINGINSTRUCTIONS_DET)) {
			foreach ($ROOMBILLINGINSTRUCTIONS_DET as $ROOM_NO => $ROOM_BILLINGINSTRUCTIONS)
				$BILLINGINSTRUCTIONS_DET .= '<strong>Room ' . $ROOM_NO . '</strong>: ' . $ROOM_BILLINGINSTRUCTIONS . ' | ';
		}
		if (!empty($BILLINGINSTRUCTIONS_DET)) $BILLINGINSTRUCTIONS_DET = substr($BILLINGINSTRUCTIONS_DET, '0', '-3');
		else $BILLINGINSTRUCTIONS_DET = '-NA-';

		$html = '<a href="javascript:void(0);" onClick="GetAccomodationBillingInstructionsForm(\'' . $id . '\',\'' . $n . '\');"><strong><u>Billing Instructions:</u></strong></a> ' . $BILLINGINSTRUCTIONS_DET;

		$result = '1~*~Accomodation ' . $n . ' Room billing instructions successfully updated~*~' . $html;
	}
} elseif ($response == 'UPDATE_BOOKINGACCOMODATION_ROOM_BILLING_INSTRUCTIONS_DETAILS') {
	if (isset($_POST['sess_id']) && !empty($_POST['sess_id']) && is_numeric($_POST['sess_id']) && isset($_POST['b_id']) && !empty($_POST['b_id']) && is_numeric($_POST['b_id']) && isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id'])) {
		$sess_id = $_POST['sess_id'];
		$b_id = $_POST['b_id'];
		$id = $_POST['id'];
		$roomtype = db_input2($_POST['roomtype']);

		$_hq = 'select iHotelID, iRoomTypeID from concbooking_dat where iBookingID=' . $b_id . ' and iBookingDatID=' . $id;
		$_hr = sql_query($_hq, '');
		list($HOTEL_ID, $ROOMTYPE_ID) = sql_fetch_row($_hr);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");
		if (!isset($UNI_ROOMTYPE_ARR[$ROOMTYPE_ID])) $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID] = GetXFromYID("select vName from gen_roomtype where iRoomTypeID=$ROOMTYPE_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];
		$ROOMTYPE = $UNI_ROOMTYPE_ARR[$ROOMTYPE_ID];

		$ROOMBILLINGINSTRUCTIONS_DET = array();
		$ROOM_ARR = GetXArrFromYID('select iRoomID, iRoomNo from concbooking_dat_rooms where iBookingID=' . $b_id . ' and iBookingDatID=' . $id . ' and cStatus="A" order by iRoomID', '3');
		if (!empty($ROOM_ARR) && count($ROOM_ARR)) {
			foreach ($ROOM_ARR as $rID => $rVALUE) {
				$txtbillinginstructions = db_input2($_POST['txtbillinginstructions_' . $rVALUE]);

				$q = "update concbooking_dat_rooms set vBillingInstructions='$txtbillinginstructions' where iBookingID='$b_id' and iBookingDatID='$id' and iRoomNo='$rVALUE' and iRoomID='$rID'";
				$r = sql_query($q, '');

				if (empty($txtbillinginstructions)) $desc_str = 'Room ' . $rVALUE . ' billing instructions updated';
				else $desc_str = 'Room ' . $rVALUE . ' billing instructions updated to: ' . $txtbillinginstructions;
				LogRequests($sess_id, $b_id, $id, 'RD', 'U', $desc_str, $sess_user_id, $HOTEL, $ROOMTYPE);

				if (empty($txtbillinginstructions)) $txtbillinginstructions = '-NA-';
				$ROOMBILLINGINSTRUCTIONS_DET[$rVALUE] = stripslashes(htmlspecialchars_decode($txtbillinginstructions));
			}
		}

		$BILLINGINSTRUCTIONS_DET = '';
		if (!empty($ROOMBILLINGINSTRUCTIONS_DET) && count($ROOMBILLINGINSTRUCTIONS_DET)) {
			foreach ($ROOMBILLINGINSTRUCTIONS_DET as $ROOM_NO => $ROOM_BILLINGINSTRUCTIONS)
				$BILLINGINSTRUCTIONS_DET .= '<strong>Room ' . $ROOM_NO . '</strong>: ' . $ROOM_BILLINGINSTRUCTIONS . ' | ';
		}
		if (!empty($BILLINGINSTRUCTIONS_DET)) $BILLINGINSTRUCTIONS_DET = substr($BILLINGINSTRUCTIONS_DET, '0', '-3');
		else $BILLINGINSTRUCTIONS_DET = '-NA-';

		$html = '<a href="javascript:void(0);" onClick="GetAccomodationBillingInstructionsForm(\'' . $id . '\');"><strong><u>Billing Instructions:</u></strong></a> ' . $BILLINGINSTRUCTIONS_DET;

		$result = '1~*~' . $roomtype . ' billing instructions successfully updated~*~' . $html;
	}
} elseif ($response == 'UPDATE_INVOICE_STATUS') {
	if (isset($_POST['b_id']) && !empty($_POST['b_id']) && is_numeric($_POST['b_id']) && isset($_POST['sess_id']) && !empty($_POST['sess_id']) && is_numeric($_POST['sess_id']) && isset($_POST['inv_id']) && !empty($_POST['inv_id']) && is_numeric($_POST['inv_id'])) {
		$b_id = $_POST['b_id'];
		$sess_id = $_POST['sess_id'];
		$inv_id = $_POST['inv_id'];
		$rdinvoicestatus_modal = db_input($_POST['rdinvoicestatus_modal']);
		$txtinvoice_rejected = (isset($_POST['txtinvoice_rejected'])) ? db_input2($_POST['txtinvoice_rejected']) : '';

		$HOTEL_ID = GetXFromYID('select iHotelID from concbooking where iBookingID=' . $b_id);
		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];

		$q2 = 'select vInvoiceNo, cStatus from concbooking_invoice where iBookingID=' . $b_id . ' and iInvoiceID=' . $inv_id;
		$r2 = sql_query($q2, '');
		list($invoiceNo, $earlier_invoicestatus) = sql_fetch_row($r2);

		$q = "update concbooking_invoice set cStatus='$rdinvoicestatus_modal' where iBookingID='$b_id' and iInvoiceID='$inv_id'";
		$r = sql_query($q, '');

		if ($rdinvoicestatus_modal == 'I')
			$sendTo = 'H';
		elseif ($rdinvoicestatus_modal == 'P')
			$sendTo = 'CM';

		$txtinvoicestage_id = NextID('iInvoiceStageID', 'concbooking_invoice_stage');
		$q3 = "insert into concbooking_invoice_stage values ('$txtinvoicestage_id', '$b_id', '$inv_id', '" . NOW . "', '$sess_user_id', '$txtinvoice_rejected', '$sendTo', '$rdinvoicestatus_modal')";
		$r3 = sql_query($q3, '');

		$desc_str = 'Invoice No ' . $invoiceNo . ' status is changed from ' . $INVOICE_STATUS_ARR[$earlier_invoicestatus] . ' to ' . $INVOICE_STATUS_ARR[$rdinvoicestatus_modal];
		LogRequests($sess_id, $b_id, 0, 'BO', 'U', $desc_str, $sess_user_id, $HOTEL, '');

		$ACTION = '';
		if ($rdinvoicestatus_modal == 'D' || $rdinvoicestatus_modal == 'R')
			$ACTION = '&nbsp;<span id="INVOICE_' . $inv_id . '" class="badge phonesmall badge-primary float-right" onClick="TakeInvoiceAction(\'' . $inv_id . '\');" style="cursor:pointer;">Take Action</span>';

		$html = '<span class="badge phonesmall badge-' . $INVOICE_STATUS_COLOR_ARR[$rdinvoicestatus_modal] . '">' . $INVOICE_STATUS_ARR[$rdinvoicestatus_modal] . '</span>' . $ACTION;

		$result = '1~*~Invoice status successfully updated~*~' . $html;
	}
} elseif ($response == 'UPDATE_INVOICE_STATUS2') {
	if (isset($_POST['b_id']) && !empty($_POST['b_id']) && is_numeric($_POST['b_id']) && isset($_POST['inv_id']) && !empty($_POST['inv_id']) && is_numeric($_POST['inv_id'])) {
		$b_id = $_POST['b_id'];
		$sess_id = (isset($_POST['sess_id'])) ? $_POST['sess_id'] : '';
		$inv_id = $_POST['inv_id'];
		$rdinvoicestatus_modal = db_input($_POST['rdinvoicestatus_modal']);
		$txtinvoice_rejected = (isset($_POST['txtinvoice_rejected'])) ? db_input2($_POST['txtinvoice_rejected']) : '';

		$_hq = 'select iHotelID, iConcSessionID from concbooking where iBookingID=' . $b_id;
		$_hr = sql_query($_hq, '');
		list($HOTEL_ID, $sess_id) = sql_fetch_row($_hr);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];

		$q2 = 'select vInvoiceNo, cStatus from concbooking_invoice where iBookingID=' . $b_id . ' and iInvoiceID=' . $inv_id;
		$r2 = sql_query($q2, '');
		list($invoiceNo, $earlier_invoicestatus) = sql_fetch_row($r2);

		$q = "update concbooking_invoice set cStatus='$rdinvoicestatus_modal' where iBookingID='$b_id' and iInvoiceID='$inv_id'";
		$r = sql_query($q, '');

		if ($rdinvoicestatus_modal == 'I')
			$sendTo = 'H';
		elseif ($rdinvoicestatus_modal == 'P')
			$sendTo = 'CM';

		$txtinvoicestage_id = NextID('iInvoiceStageID', 'concbooking_invoice_stage');
		$q3 = "insert into concbooking_invoice_stage values ('$txtinvoicestage_id', '$b_id', '$inv_id', '" . NOW . "', '$sess_user_id', '$txtinvoice_rejected', '$sendTo', '$rdinvoicestatus_modal')";
		$r3 = sql_query($q3, '');

		$desc_str = 'Invoice No ' . $invoiceNo . ' status is changed from ' . $INVOICE_STATUS_ARR[$earlier_invoicestatus] . ' to ' . $INVOICE_STATUS_ARR[$rdinvoicestatus_modal];
		LogRequests($sess_id, $b_id, 0, 'BO', 'U', $desc_str, $sess_user_id, $HOTEL, '');

		$ACTION = '';
		if ($rdinvoicestatus_modal == 'D' || $rdinvoicestatus_modal == 'R')
			$ACTION = '&nbsp;<span id="INVOICE_' . $inv_id . '" class="badge phonesmall badge-primary float-right" onClick="TakeInvoiceAction(\'' . $inv_id . '\',\'' . $b_id . '\');" style="cursor:pointer;">Take Action</span>';

		$html = '<span class="badge phonesmall badge-' . $INVOICE_STATUS_COLOR_ARR[$rdinvoicestatus_modal] . '">' . $INVOICE_STATUS_ARR[$rdinvoicestatus_modal] . '</span>' . $ACTION;

		$result = '1~*~Invoice status successfully updated~*~' . $html;
	}
} elseif ($response == 'MARK_INVOICE_CLOSED') {
	if (isset($_POST['b_id']) && !empty($_POST['b_id']) && is_numeric($_POST['b_id']) && isset($_POST['inv_id']) && !empty($_POST['inv_id']) && is_numeric($_POST['inv_id'])) {
		$b_id = $_POST['b_id'];
		$inv_id = $_POST['inv_id'];
		$rdmodeofpayment = $_POST['rdmodeofpayment'];
		$txtpaymentrefno = $_POST['txtpaymentrefno'];

		$_hq = 'select iConcSessionID, iHotelID from concbooking where iBookingID=' . $b_id;
		$_hr = sql_query($_hq, '');
		list($sess_id, $HOTEL_ID) = sql_fetch_row($_hr);

		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];

		$q2 = 'select vInvoiceNo, cStatus from concbooking_invoice where iBookingID=' . $b_id . ' and iInvoiceID=' . $inv_id;
		$r2 = sql_query($q2, '');
		list($invoiceNo, $earlier_invoicestatus) = sql_fetch_row($r2);

		$q = "update concbooking_invoice set cModeOfPayment='$rdmodeofpayment', vTransactionNo='$txtpaymentrefno', cStatus='C' where iBookingID='$b_id' and iInvoiceID='$inv_id'";
		$r = sql_query($q, '');

		$txtinvoicestage_id = NextID('iInvoiceStageID', 'concbooking_invoice_stage');
		$q3 = "insert into concbooking_invoice_stage values ('$txtinvoicestage_id', '$b_id', '$inv_id', '" . NOW . "', '$sess_user_id', '', '', 'C')";
		$r3 = sql_query($q3, '');

		$desc_str = 'Invoice No ' . $invoiceNo . ' status is changed from ' . $INVOICE_STATUS_ARR[$earlier_invoicestatus] . ' to ' . $INVOICE_STATUS_ARR['C'];
		LogRequests($sess_id, $b_id, 0, 'BO', 'U', $desc_str, $sess_user_id, $HOTEL);

		$html = '<span class="badge phonesmall badge-' . $INVOICE_STATUS_COLOR_ARR['C'] . '">' . $INVOICE_STATUS_ARR['C'] . '</span>';

		$result = '1~*~Invoice status successfully updated~*~' . $html;
	}
} elseif ($response == 'ADD_PLAY_DETAILS') {
	if (isset($_POST['b_id']) && !empty($_POST['b_id']) && is_numeric($_POST['b_id']) && isset($_POST['sess_id']) && !empty($_POST['sess_id']) && is_numeric($_POST['sess_id'])) {
		$b_id = $_POST['b_id'];
		$sess_id = $_POST['sess_id'];
		$txtplay_date = $_POST['txtplay_date'];
		$cmbplay_property = $_POST['cmbplay_property']; //array();
		$cmbplay_guest = $_POST['cmbplay_guest']; //array();
		$cmbplay_status = $_POST['cmbplay_status'];

		$GUEST_ARR = GetXArrFromYID('select iGuestID, vName from concbooking_guests where iBookingID=' . $b_id, '3');

		$HOTEL_ID = GetXFromYID('select iHotelID from concbooking where iBookingID=' . $b_id);
		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];

		if (!empty($cmbplay_property) && count($cmbplay_property) && !empty($cmbplay_guest) && count($cmbplay_guest)) {
			foreach ($cmbplay_property as $pID) {
				foreach ($cmbplay_guest as $gID) {
					$guestName = stripslashes(htmlspecialchars_decode($GUEST_ARR[$gID]));

					$txtcbgpd_id = NextID('iCGPDID', 'concbooking_guest_playdetails');
					$q = "insert into concbooking_guest_playdetails values ('$txtcbgpd_id', '$b_id', '$txtplay_date', '$pID', '$gID', '$guestName', '$sess_user_id', '$cmbplay_status')";
					$r = sql_query($q, '');
				}
			}

			$desc_str = 'Play details added';
			LogRequests($sess_id, $b_id, 0, 'BO', 'U', $desc_str, $sess_user_id, $HOTEL, '');
		}

		$html = GetBookingPlayDetaisl($b_id);

		$result = '1~*~Play details successfully added~*~' . $html;
	}
} elseif ($response == 'DELETE_PLAY_DETAILS') {
	if (isset($_GET['b_id']) && !empty($_GET['b_id']) && is_numeric($_GET['b_id']) && isset($_GET['sess_id']) && !empty($_GET['sess_id']) && is_numeric($_GET['sess_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
		$b_id = $_GET['b_id'];
		$sess_id = $_GET['sess_id'];
		$id = $_GET['id'];

		$HOTEL_ID = GetXFromYID('select iHotelID from concbooking where iBookingID=' . $b_id);
		if (!isset($UNI_HOTEL_ARR[$HOTEL_ID])) $UNI_HOTEL_ARR[$HOTEL_ID] = GetXFromYID("select vName from gen_hotel where iHotelID=$HOTEL_ID");

		$HOTEL = $UNI_HOTEL_ARR[$HOTEL_ID];

		$q = "update concbooking_guest_playdetails set cStatus='X' where iCGPDID='$id'";
		$r = sql_query($q, '');

		$desc_str = 'Play entry details deleted';
		LogRequests($sess_id, $b_id, 0, 'BO', 'U', $desc_str, $sess_user_id, $HOTEL, '');

		$html = GetBookingPlayDetaisl($b_id);

		$result = '1~*~Play details successfully added~*~' . $html;
	}
} elseif ($response == 'UPDATE_HOTEL_ROOM_BLOCK_DETAILS') {
	if (isset($_POST['b_id']) && !empty($_POST['b_id']) && is_numeric($_POST['b_id']) && isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id'])) {
		$b_id = $_POST['b_id'];
		$id = $_POST['id'];
		$cmbhotel = $_POST['cmbhotel'];
		$cmbroomtype = $_POST['cmbroomtype'];
		$txtrooms = $_POST['txtrooms'];

		$q = 'select dCheckIn, dCheckOut from concblock where iBlockID=' . $b_id;
		$r = sql_query($q, '');
		list($dCheckIn, $dCheckOut) = sql_fetch_row($r);

		$txtnights = DateDiff($dCheckIn, $dCheckOut);
		$txtroomnights = $txtrooms * $txtnights;

		sql_query("update concblock_dat set iRooms='$txtrooms', iHotelID='$cmbhotel', iRoomTypeID='$cmbroomtype', dtAdded='" . NOW . "', iAdded_UserID='$sess_user_id', iRoomNights='$txtroomnights' where iBlockID='$b_id' and iBlockDatID='$id'", "");

		$txt_totalnights = GetXFromYID('select sum(iRoomNights) from concblock_dat where cStatus="A" and iBlockID=' . $b_id . ' group by iBlockID');
		sql_query("update concblock set iRoomNights='$txt_totalnights' where iBlockID='$b_id'", "");

		$result = '1~*~Details successfully updated';
	}
} elseif ($response == 'DELETE_HOTEL_ROOM_BLOCK_DETAILS') {
	if (isset($_GET['b_id']) && !empty($_GET['b_id']) && is_numeric($_GET['b_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id'])) {
		$b_id = $_GET['b_id'];
		$id = $_GET['id'];

		sql_query("update concblock_dat set cStatus='X' where iBlockID='$b_id' and iBlockDatID='$id'", "");

		$txt_totalnights = GetXFromYID('select sum(iRoomNights) from concblock_dat where cStatus="A" and iBlockID=' . $b_id . ' group by iBlockID');
		sql_query("update concblock set iRoomNights='$txt_totalnights' where iBlockID='$b_id'", "");

		$result = '1~*~Details successfully updated';
	}
} elseif ($response == 'SAVE_HOTEL_ROOM_RM_DETAILS') {
	if (isset($_POST['b_id']) && !empty($_POST['b_id']) && is_numeric($_POST['b_id']) && isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id'])) {
		$b_id = $_POST['b_id'];
		$id = $_POST['id'];
		$cmbrm = $_POST['cmbrm'];
		$txtrooms = $_POST['txtrooms'];

		$q = 'select dCheckIn, dCheckOut from concblock where iBlockID=' . $b_id;
		$r = sql_query($q, '');
		list($dCheckIn, $dCheckOut) = sql_fetch_row($r);

		$txtnights = DateDiff($dCheckIn, $dCheckOut);
		$txtroomnights = $txtrooms * $txtnights;

		$q = 'select iBlockDatDisID from concblock_dat_distribution where iBlockID=' . $b_id . ' and iBlockDatID=' . $id . ' and iRM_UserID=' . $cmbrm;
		$r = sql_query($q, '');
		if (sql_num_rows($r)) {
			list($txtid) = sql_fetch_row($r);

			sql_query("update concblock_dat_distribution set iRooms='$txtrooms', iRoomNightsAllocated='$txtroomnights', dtAdded='" . NOW . "', iAdded_UserID='$sess_user_id' where iBlockID='$b_id' and iBlockDatID='$id' and iBlockDatDisID='$txtid'", "");
		} else {
			LockTable('concblock_dat_distribution');
			$txtid = NextID('iBlockDatDisID', 'concblock_dat_distribution');
			$q = "insert into concblock_dat_distribution values ('$txtid', '$b_id', '$id', '" . NOW . "', '$sess_user_id', '$cmbrm', '$txtrooms', '$txtroomnights', '0', 'A')";
			$r = sql_query($q, "BB.I.123");
			UnLockTable();
		}

		$txt_totalnightsallocated = GetXFromYID('select sum(iRoomNightsAllocated) from concblock_dat_distribution where cStatus="A" and iBlockID=' . $b_id . ' and iBlockDatID=' . $id . ' group by iBlockDatID');
		sql_query("update concblock_dat set iRoomNightsAllocated='$txt_totalnightsallocated' where iBlockID='$b_id' and iBlockDatID='$id'", "");

		$txt_totalnightsallocated2 = GetXFromYID('select sum(iRoomNightsAllocated) from concblock_dat_distribution where cStatus="A" and iBlockID=' . $b_id . ' group by iBlockID');
		sql_query("update concblock set iRoomNightsAllocated='$txt_totalnightsallocated2' where iBlockID='$b_id'", "");

		$result = '1~*~Details successfully updated';
	}
} elseif ($response == 'DELETE_HOTEL_ROOM_BLOCK_DETAILS2') {
	if (isset($_GET['b_id']) && !empty($_GET['b_id']) && is_numeric($_GET['b_id']) && isset($_GET['id']) && !empty($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['id2']) && !empty($_GET['id2']) && is_numeric($_GET['id2'])) {
		$b_id = $_GET['b_id'];
		$id = $_GET['id'];
		$id2 = $_GET['id2'];

		sql_query("update concblock_dat_distribution set cStatus='X' where iBlockID='$b_id' and iBlockDatID='$id' and iBlockDatDisID='$id2'", "");

		$txt_totalnights2 = GetXFromYID('select sum(iRoomNightsAllocated) from concblock_dat_distribution where cStatus="A" and iBlockID=' . $b_id . '  and iBlockDatID=' . $id . ' group by iBlockDatID');

		sql_query("update concblock_dat set iRoomNightsAllocated='$txt_totalnights2' where iBlockID='$b_id' and iBlockDatID='$id'", "");

		$result = '1~*~Details successfully updated';
	}
} else if ($response == 'SEND_BOOKING_MAIL_TO_HOTEL') {
	if (isset($_GET['id']) && !empty($_GET['id'])) {
		$booking_id = $_GET['id'];

		SendBookindDetailstoHotelMail($booking_id, $sess_user_id);

		$result = '1~~**~~Booking details mailed to the hotel';
	}
} else if ($response == 'UPDATE_BOOKING') {
	if (isset($_POST['id']) && !empty($_POST['id']) && is_numeric($_POST['id']) && isset($_POST['cmbeditbooking_rmid']) && !empty($_POST['cmbeditbooking_rmid']) && is_numeric($_POST['cmbeditbooking_rmid']) && isset($_POST['txteditbooking_client']) && !empty($_POST['txteditbooking_client'])) {
		$booking_id = $_POST['id'];
		$cmbeditbooking_rmid = $_POST['cmbeditbooking_rmid'];
		$txteditbooking_client = $_POST['txteditbooking_client'];
		$txteditbooking_contact = $_POST['txteditbooking_contact'];

		$q = 'select iUserID_RM, iRMContactID, vClient, vContactNum_Client, iConcSessionID from concbooking where iBookingID=' . $booking_id;
		$r = sql_query($q, '');
		list($iUserID_RM, $iRMContactID, $vClient, $vContactNum_Client, $iConcSessionID) = sql_fetch_row($r);

		$CLIENT_ID = GetClientID($cmbeditbooking_rmid, 0, $txteditbooking_client, $txteditbooking_contact);

		$conc_bookingid = $booking_id; //NextID('iRequestID', 'concrequest');
		sql_query("update concbooking set iUserID_RM='$cmbeditbooking_rmid', iRMContactID='$CLIENT_ID', vClient='$txteditbooking_client', vContactNum_Client='$txteditbooking_contact' where iBookingID='$booking_id'", "");

		$desc_str = '';
		if ($iUserID_RM != $cmbeditbooking_rmid) {
			$RM_ARR = GetXArrFromYID('select iUserID, vName from users where iLevel=1 and cStatus="A"', '3');
			$desc_str .= 'RM: Changed from ' . $RM_ARR[$iUserID_RM] . ' to ' . $RM_ARR[$cmbeditbooking_rmid] . ', ';
		}
		if ($vClient != $txteditbooking_client) $desc_str .= 'Client : Changed from ' . $vClient . ' to ' . $txteditbooking_client . ', ';
		if ($vContactNum_Client != $txteditbooking_contact) $desc_str .= 'Client Contact : Changed from ' . $vContactNum_Client . ' to ' . $txteditbooking_contact . ', ';

		if (!empty($desc_str)) {
			$desc_str = substr($desc_str, 0, '-2');
			LogRequests($iConcSessionID, $conc_requestid, 0, 'RQ', 'U', $desc_str, $sess_user_id, '', '');
		}

		$result = '1~~**~~Booking details have been updated';
	}
} else if ($response == 'UPDATE_SORT') {

	if (isset($_GET["mode"]) && isset($_GET['id_str'])) {

		$mode = $_GET["mode"];

		$id_str = $_GET['id_str'];

		$ref_id = (!empty($_GET["ref_id"])) ? $_GET["ref_id"] : 0;



		$valid_modes = array('TEAM');

		if (strpos($id_str, ',') && in_array($mode, $valid_modes)) {

			$qref_str = '';



			if ($mode == 'TEAM') {

				$pk_fld = 'iTeamID';

				$tbl = 'team';

				$msg = 'Team';
			}


			$i = 0;

			$id_arr = explode(",", $id_str);

			foreach ($id_arr as $id) {

				if (empty($id) || !is_numeric($id))
					continue;



				$q = "UPDATE " . $tbl . " SET iRank= '" . (++$i) . "' WHERE " . $pk_fld . "=" . $id . $qref_str;

				$r = sql_query($q, 'AJX.5086');
			}



			$result = 1;
		}
	}
}

echo $result;
exit;
