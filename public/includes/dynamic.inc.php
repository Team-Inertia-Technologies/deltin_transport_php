<?php
##	GETCONNECTED	###################################################################################
$CON = GetConnected();
$S3OBJ = (defined('USE_OBJECT_STORAGE') && USE_OBJECT_STORAGE == 'Y') ? ObjectStorage() : null;

##	DYNAMIC DEFINES	###################################################################################

$USER_LEVEL_SUPER = '0';
$USER_LEVEL_ADMIN = '1';
$USER_LEVEL_HOD = '2';
$USER_LEVEL_NATIONAL_HEAD = '3';
$USER_LEVEL_LOYALTY_COORDINATOR = '4';
$USER_LEVEL_VIP_SERVICES = '5';
$USER_LEVEL_JR_RM = '6';
$USER_LEVEL_MEMBERS = '7';
$USER_LEVEL_SR_RM = '8';
$USER_LEVEL_CALLER = '9';
$USER_LEVEL_MANAGER ='10';
$USER_LEVEL_DELIGHTS ='13';
##	USER SESSION VARIABLES	###################################################################################
$logged = 0;
$sess_user_type = 'ADM';
$is_sys_admin = $is_super_admin = $is_rm = $is_hod = $is_national_head = $is_loyalty_coordinator = $is_vip_services = $is_members = $is_caller = $is_manager = $is_delights = $is_menu_closed = $sess_ref_type = false;
$sess_info_str = '';
$sess_module_access = array();
if (isset($_SESSION[PROJ_SESSION_ID]->log_stat)) // if the session variable has been set...
{
	if ($_SESSION[PROJ_SESSION_ID]->log_stat == "A") {
		$logged = 1;
		$sess_user_id = $_SESSION[PROJ_SESSION_ID]->user_id;
		$sess_user_name = $_SESSION[PROJ_SESSION_ID]->user_name;
		$sess_user_level = $_SESSION[PROJ_SESSION_ID]->user_level;
		$sess_user_type = $_SESSION[PROJ_SESSION_ID]->user_type;
		$sess_user_sess = $_SESSION[PROJ_SESSION_ID]->sess;
		$sess_user_pic = $_SESSION[PROJ_SESSION_ID]->user_pic;
		$sess_login_time = FormatDate($_SESSION[PROJ_SESSION_ID]->log_time, "B", 1);
		$sess_user_token = $_SESSION[PROJ_SESSION_ID]->sess_token;
		$sess_lhs_menu = $_SESSION[PROJ_SESSION_ID]->lhs_menu;
		$sess_user_active = $_SESSION[PROJ_SESSION_ID]->sess_active;
		$sess_ref_type = $_SESSION[PROJ_SESSION_ID]->user_reftype;
		$sess_ref_id = $_SESSION[PROJ_SESSION_ID]->user_refid;
		$sess_module_access = $_SESSION[PROJ_SESSION_ID]->module_access;

		switch ($sess_user_level) {
			case $USER_LEVEL_SUPER: {
					$is_super_admin = true;
					break;
				}
			case $USER_LEVEL_JR_RM: {
					$is_rm = true;
					break;
				}
			case $USER_LEVEL_SR_RM: {
					$is_rm = true;
					break;
				}
			case $USER_LEVEL_HOD: {
					$is_hod = true;
					break;
				}
			case $USER_LEVEL_NATIONAL_HEAD: {
					$is_national_head = true;
					break;
				}
			case $USER_LEVEL_LOYALTY_COORDINATOR: {
					$is_loyalty_coordinator = true;
					break;
				}
			case $USER_LEVEL_VIP_SERVICES: {
					$is_vip_services = true;
					break;
				}
			case $USER_LEVEL_MEMBERS: {
					$is_members = true;
					break;
				}
			case $USER_LEVEL_CALLER: {
					$is_caller = true;
					break;
				}

			case $USER_LEVEL_MANAGER: {
					$is_manager = true;
					break;
				}
				case $USER_LEVEL_DELIGHTS: {
					$is_delights = true;
					break;
				}
		}

		if ($is_super_admin && !$sess_user_id) $is_sys_admin = true;
	}

	$sess_user_access = true;
	$msg_unread_count = 0;
}

if (!$logged && empty($NO_REDIRECT)) {
	ForceOut(6);
}

if ($logged) {
	$sess_user_active = GetXFromYID("select cActive from users where iUserID='$sess_user_id' and vToken='$sess_user_token'");
	$_SESSION[PROJ_SESSION_ID]->sess_active = $sess_user_active;
}

$ACCESS_ARR = array();
/*case $USER_LEVEL_SUPER: { $is_super_admin = true; break; }
			case $USER_LEVEL_RM: { $is_rm = true; break; }
			case $USER_LEVEL_CS: { $is_cs = true; break; }
			case $USER_LEVEL_CE: { $is_ce = true; break; }
			case $USER_LEVEL_GM: { $is_gm = true; break; }
			case $USER_LEVEL_ACCOUTNTS: { $is_accounts = true; break; }*/



$ACCESS_ARR = array(14, 16);
if ($is_loyalty_coordinator) {
	$ACCESS_ARR = array(3, 8, 9, 10, 34, 39, 40, 41, 5, 43, 48, 52, 49, 50, 51, 53);
} elseif ($is_national_head) {
	$ACCESS_ARR = array(3, 34, 39, 40, 41, 5, 43, 48, 52,54,68,69);
} elseif ($is_vip_services) {
	$ACCESS_ARR = array(3,  34, 39, 40, 41, 5, 43, 44, 36, 48, 52, 49, 50, 51, 53,68,70);
} elseif ($is_rm) {
	$ACCESS_ARR = array(3, 34, 39, 40, 41, 5, 43, 48, 51, 49, 50, 44, 2, 53);
} elseif ($is_super_admin) {
	$ACCESS_ARR = array(52,54);
} elseif ($is_hod) {
	$ACCESS_ARR = array(3, 34, 39, 40, 41, 5, 43, 48, 52,54,68,69);
} elseif ($is_caller) {
	$ACCESS_ARR = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 38, 25, 24, 23, 22, 26, 27, 28, 29, 30, 31, 33, 34, 35, 20, 36, 37, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 55, 56,59,58,60);
}elseif ($is_manager){
	$ACCESS_ARR = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 38, 25, 24, 23, 22, 26, 27, 28, 29, 30, 31, 33, 34, 35, 20, 36, 37, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48);
}
elseif ($is_delights){
	$ACCESS_ARR = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 13, 38, 25, 24, 23, 22, 26, 27, 28, 29, 30, 31, 33, 34, 35, 20, 36, 37, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 53,62);
}
if ($logged) {
	##	DEFINED MENU ARRAYs	###################################################################################
	$MENU_ARR = array();
	$cond1 = $cond2 = "";

	if (!empty($ACCESS_ARR)) {
		$cond1 = " and iMenuID not in (" . implode(",", $ACCESS_ARR) . ")";
	}

	$q = "select * from menu where cStatus='A' $cond1 and iParentID=0 order by iRank";
	$r = sql_query($q, '');
	if (sql_num_rows($r)) {
		$i = 0;
		while ($mRow = sql_fetch_object($r)) {
			$URLS = GetLinkedURLS($mRow->iMenuID);
			$HREF = (!empty($mRow->vUrl) && $mRow->vUrl != '#') ? $mRow->vUrl : 'javascript:;';

			$MENU_ARR[$i] = array('TEXT' => $mRow->vTitle, 'ICON' => 'fa fa-arrow-alt-circle-right', 'HREF' => $HREF, 'URLS' => $URLS, 'IS_SUB' => $mRow->cHasSub);

			//query to grab second level
			$q1 = "select * from menu where cStatus='A' $cond1 and iParentID ='$mRow->iMenuID' order by iRank";
			$r1 = sql_query($q1, '');

			if (sql_num_rows($r1)) {
				$j = 0;
				while ($mRow1 = sql_fetch_object($r1)) {
					if ($mRow1->iParentID != '0') $has_sub = true;
					$URLS2 = GetLinkedURLS($mRow1->iMenuID);

					$MENU_ARR[$i]['SUB_MENU'][] = array('TEXT' => $mRow1->vTitle, 'ICON' => $mRow1->vIcon, 'HREF' => $mRow1->vUrl, 'URLS' => $URLS2, 'IS_SUB' => $mRow1->cHasSub);

					//query to grab third level
					$q2 = "select * from menu where cStatus='A' and iParentID ='$mRow1->iMenuID' order by iRank";
					$r2 = sql_query($q2, '');

					if (sql_num_rows($r2)) {
						$k = 0;
						while ($mRow2 = sql_fetch_object($r2)) {
							//if($mRow1->iParentID !='0') $has_sub = true;
							$URLS3 = GetLinkedURLS($mRow2->iMenuID);
							$MENU_ARR[$i]['SUB_MENU'][$j]['MENU'][] = array('TEXT' => $mRow2->vTitle, 'ICON' => $mRow2->vIcon, 'HREF' => $mRow2->vUrl, 'URLS' => $URLS3, 'IS_SUB' => $mRow2->cHasSub);

							$k++;
						}
					}

					$j++;
				}
			}

			$i++;
		}
	}
}

##	SYS_SETTINGS	###################################################################################
$q = "select cType, vCode, cData, vValue from sys_settings where cStatus='A'";
$r = sql_query($q, 'DYN.30');
while (list($sys_type, $sys_code, $sys_data, $sys_value) = sql_fetch_row($r)) {
	if ($sys_data == 'I')
		$sys_value = intval($sys_value);
	else if ($sys_data == 'N')
		$sys_value = floatval($sys_value);
	else if ($sys_data == 'B')
		$sys_value = boolval($sys_value);
	else
		$sys_value = strval($sys_value); // C, D

	if ($sys_type == 'D') // define
		define($sys_code, $sys_value);
	else if ($sys_type == 'V') // variable
		${$sys_code} = $sys_value;
	else if ($sys_type == 'A') // arrays
	{
		$x = json_decode($sys_value);

		foreach ($x as $key => $val)
			${$sys_code}[$key] = $val;
	}
}

##	USER ACCESS		###################################################################################
$_file_name = basename($_SERVER["SCRIPT_NAME"]);
$_file_module = GetFileModule($_file_name);

##	SESSION->INFO	###################################################################################
$lbl_display = 'none'; // used for LBL_ERR
if ($logged) {
	$sess_info = (isset($_SESSION[PROJ_SESSION_ID]->info)) ? NotifyThis($_SESSION[PROJ_SESSION_ID]->info, 'info') : '';
	$sess_success_info = (isset($_SESSION[PROJ_SESSION_ID]->success_info)) ? NotifyThis($_SESSION[PROJ_SESSION_ID]->success_info, 'success') : '';
	$sess_error_info = (isset($_SESSION[PROJ_SESSION_ID]->error_info)) ? NotifyThis($_SESSION[PROJ_SESSION_ID]->error_info, 'error') : '';
	$sess_alert_info = (isset($_SESSION[PROJ_SESSION_ID]->alert_info)) ? NotifyThis($_SESSION[PROJ_SESSION_ID]->alert_info, 'alert') : '';

	$sess_info_str = $sess_info . $sess_success_info . $sess_error_info . $sess_alert_info;

	$lbl_display = ($sess_info != "") ? '' : 'none';
	$_SESSION[PROJ_SESSION_ID]->info = "";
	$_SESSION[PROJ_SESSION_ID]->success_info = "";
	$_SESSION[PROJ_SESSION_ID]->error_info = "";
	$_SESSION[PROJ_SESSION_ID]->alert_info = "";	// */
}

$PAGE_OPTION_STR = '';
if (empty($NO_PRELOAD))
	if (!isset($USER_ARR) && $logged) $USER_ARR = GetUserDetails();

if (isset($sess_user_id)) {
	//$sess_user_pic = (!empty($USER_ARR[$sess_user_id]['pic']) && IsExistFile($USER_ARR[$sess_user_id]['pic'], USER_UPLOAD)) ? USER_PATH . $USER_ARR[$sess_user_id]['pic'] : false;
	$sess_user_pic = (!empty($USER_ARR[$sess_user_id]['pic']) && StoreFileExist('USER', $USER_ARR[$sess_user_id]['pic'])) ? StoreFilePath('USER', $USER_ARR[$sess_user_id]['pic'])[0] : null;
}

#######################################################################################################
function GetFileModule($_file_name)
{
	global $MENU_ARR;
	$str = '-1';
	$is_this_url = false;

	if (isset($MENU_ARR)) {
		foreach ($MENU_ARR as $M_CODE => $M) {
			if (IsInThisMenuLevel($M, $_file_name)) {
				$str = $M_CODE;
				break;
			}
		}
	}

	return strval($str);
}

function IsInThisMenuLevel($M, $_file_name)
{
	global $MENU_ARR;
	$x = false;

	if (isset($M['HREF']) && $M['HREF'] == $_file_name) {
		$x = true;
	} else if (isset($M['SUB']) && is_array($M['SUB'])) {
		foreach ($M['SUB'] as $N_CODE => $N) {
			$x = IsInThisMenuLevel($N, $_file_name);

			if ($x)
				break;
		}
	}

	return $x;
}

function NotifyThis($text, $mode = 'alert')
{
	if ($mode == 'success') $mode_str = 'alert-success';
	else if ($mode == 'error') $mode_str = 'alert-danger';
	else if ($mode == 'info') $mode_str = 'alert-warning';
	else $mode_str = 'alert-warning';

	if ($mode == 'success') $mode_icon = 'fa fa-check-circle';
	else if ($mode == 'error') $mode_icon = 'fa fa-times-circle';
	else if ($mode == 'info') $mode_icon = 'fa fa-exclamation-circle';
	else $mode_icon = 'fa fa-question-circle';


	$text = trim($text);
	return ($text != '') ? '<div class="alert ' . $mode_str . ' alert-dismissible fade show" role="alert"><button type="button" class="close" aria-label="Close"><spanaria-hidden="true">×</span></button><i class="' . $mode_icon . ' mr-1 text-muted opacity-6"></i>' . $text . '</div>' : '';
}

function GetAllUrls($menu_arr, $module)
{
	$arr = array();
	global $BREADCRUMB_ARR;
	$i = 0;

	foreach ($menu_arr as $a_key => $a) {
		if ($a[0] != '#')
			$BREADCRUMB_ARR[$a[0]] = array($module, '<a href="' . $a[0] . '">' . $a_key . '</a>');

		foreach ($a as $b_key => $b) {
			if (!is_array($b)) {
				$arr[$i++] = $b;
				continue;
			}

			foreach ($b as $c_key => $c) {
				$arr[$i++] = $c;

				if (is_numeric($c_key))
					$BREADCRUMB_ARR[$c] = array($module, '<a href="' . $a[0] . '">' . $a_key . '</a>');
				else
					$BREADCRUMB_ARR[$c] = array($module, '<a href="' . $a[0] . '">' . $a_key . '</a>', '<a href="' . $b[0] . '">' . $b_key . '</a>');
			}
		}
	}

	$arr = array_unique($arr);
	return $arr;
}
