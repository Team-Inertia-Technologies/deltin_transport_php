<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
$NO_REDIRECT = $NO_PRELOAD = 1;
include "../../includes/common_api.php";
//require_once('../../includes/ti-salt.php');

if (true) {
	if ($_SERVER['REQUEST_METHOD'] == "POST") {
		####################################################################################
		// First, make sure the form was posted from a browser.
		// For basic web-forms, we don't care about anything  other than requests from a browser:    
		if (!isset($_SERVER['HTTP_USER_AGENT']))
			ForceOut(5);

		// Make sure the form was indeed POST'ed: (requires your html form to use: action="post") 
		if (!$_SERVER['REQUEST_METHOD'] == "POST")
			ForceOut(5);

		#########################################################################################  
		if (isset($_POST["txtusername"]) && isset($_POST["txtpassword"])) //  && isset($_POST["type"]) && isset($_POST["btnlogin"]))
		{
			$username = db_input($_POST["txtusername"]);
			$txtpassword = htmlspecialchars_decode(db_input2($_POST["txtpassword"]));
			//echo $txtpassword;
			// $salt_obj = new SaltIT;
			// $txtpassword = $salt_obj->EnCode($txtpassword);
			// echo $txtpassword;
			// $ret = 0; //error flag

			if ($txtpassword == '') {
				session_destroy();
				$response = array(
					"error" => array(
						"message" => "password cannot be blank",
					),
					"statusCode" => 400,
				);
				http_response_code(400);
				header('Content-Type: application/json');
				echo json_encode($response);
				exit;

			}
				
			elseif ($username == ''){
			session_destroy();
			$response = array(
				"error" => array(
					"message" => "username cannot be blank",
				),
				"statusCode" => 400,
			);
			http_response_code(400);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit;}
			else {
				$u_id = $u_level = 0;
				$q = "select iUserID, vName, vPassword, iLevel from users where vUName='" . $username . "' and cStatus='A'";
				$r = sql_query($q, 'AUTH.61');
				if (sql_num_rows($r)) {
					list($u_id, $u_name, $u_pass, $u_level) = sql_fetch_row($r);
					$u_pass = htmlspecialchars_decode($u_pass);
					//$USER_MODULE_ACCESS = GetIDString2('select distinct(iModuleID) from role_access where iRoleID=' . $u_level);
					
					$MODULE_ACCESS_ARR = GetXArrFromYID('select m.iModuleID, m.vCode from module as m join module_level_assoc as ma on m.iModuleID=ma.iModuleID where ma.iLevelD='.$u_level.' and m.cStatus="A" and ma.cType="FL"', '3');
					
					// Get menu IDs for modules that user has access to - only show menus where user has access to at least one module and menu is active
					$MENU_ACCESS_ARR = GetXArrFromYID('select distinct(m.iMenuID) from menu as m join module_menu_assoc as mma on m.iMenuID=mma.iRefID join module_level_assoc as mla on mma.iModuleID=mla.iModuleID where mla.iLevelD='.$u_level.' and mla.cType="FL" and mma.cRefType="M" and m.cDisplayInMenu="Y" and m.cStatus="A"', '1');
					
		
					$MENU_DETAILS = array();
					if (!empty($MENU_ACCESS_ARR) && is_array($MENU_ACCESS_ARR)) {
						$menu_ids = implode(',', $MENU_ACCESS_ARR);
						$menu_query = "SELECT iMenuID, cType, iParentID, vTitle, vUrl, vCode, cHasSub, cDisplayInMenu, cStatus 
									   FROM menu 
									   WHERE iMenuID IN ($menu_ids) AND cDisplayInMenu='Y' AND cStatus='A' 
									   ORDER BY iParentID ASC, iMenuID ASC";
						$menu_result = sql_query($menu_query, 'AUTH.MENU');
						
						$all_menus = array();
						while ($menu_row = sql_fetch_assoc($menu_result)) {
							$all_menus[] = array(
								'menuId' => (int)$menu_row['iMenuID'],
								'parentId' => (int)$menu_row['iParentID'],
								'title' => $menu_row['vTitle'],
								'url' => $menu_row['vUrl'],
								'code' => $menu_row['vCode'],
								'hasSub' => $menu_row['cHasSub']
							);
						}
						
						// Build hierarchical structure - parents with sub_menu arrays
						$menu_map = array();
						foreach ($all_menus as $menu) {
							if ($menu['parentId'] == 0) {
								// This is a parent menu
								$menu['sub_menu'] = array();
								$menu_map[$menu['menuId']] = $menu;
							}
						}
						
						// Add sub_menu to their parents
						foreach ($all_menus as $menu) {
							if ($menu['parentId'] != 0 && isset($menu_map[$menu['parentId']])) {
								$menu_map[$menu['parentId']]['sub_menu'][] = $menu;
							}
						}
						
						// Convert to indexed array for response
						$MENU_DETAILS = array_values($menu_map);
					}
					
					/*if (!empty($USER_MODULE_ACCESS) && $USER_MODULE_ACCESS != '1') {
						LogAttempt($username, 'F', 'Invalid Module Access Detected');
						ForceOut(4);
					}*/
					
					//echo $txtpassword;
					//exit;

					$LIMIT = 5;
					if(!empty(FAILED_LOGIN_ATTEMPTS)) $LIMIT = FAILED_LOGIN_ATTEMPTS;
					
					$FAILED_LOGIN_COUNT = 0;
					$IS_LAST_ENTRY_FAILURE = ''; $LAST_TRY_TIME = '';
					$cfq = 'select cStatus, vDateTime from log_signin where vUserName="'.$username.'" order by vDateTime desc limit '.$LIMIT;
					$cfr = sql_query($cfq,'');
					if(sql_num_rows($cfr))
					{
						while(list($cStatus,$vDateTime) = sql_fetch_row($cfr))
						{
							if(empty($LAST_ENTRY))
							{
								if($cStatus=='F')
								{
									$IS_LAST_ENTRY_FAILURE = 'Y';
									$LAST_TRY_TIME = $vDateTime;
								}
							}
							
							if(empty($LAST_ENTRY)) $IS_LAST_ENTRY_FAILURE = 'N';
							
							if($IS_LAST_ENTRY_FAILURE=='Y')
							{
								if($cStatus=='F') $FAILED_LOGIN_COUNT = $FAILED_LOGIN_COUNT + 1;
								else
								{
									$IS_LAST_ENTRY_FAILURE = 'N';
								}
							}
						}
					}
					
					if($FAILED_LOGIN_COUNT>=FAILED_LOGIN_ATTEMPTS)
						$ret = 3;
					else
					{
						$ret = ($u_pass == ($txtpassword)) ? 1 : -1;	// 1 - txtpassword Matches ::  -1 - txtpassword MisMatch
					/* echo $u_pass.'<br>'.$txtpassword; exit; */
					}
				} else
					$ret = -2;	//No User Found

				if($ret == 3)
				{
					session_destroy();
					$response = array(
						"error" => array(
							"message" => "Login Failed",
						),
						"statusCode" => 400,
					);
					http_response_code(400);
					header('Content-Type: application/json');
					echo json_encode($response);
					exit;
				} elseif ($ret == -1 || $ret == -2) {
					LogAttempt($username, 'F', 'Wrong User Name');
					session_destroy();
					$response = array(
						"error" => array(
							"message" => "Wrong User Name",
						),
						"statusCode" => 400,
					);
					http_response_code(400);
					header('Content-Type: application/json');
					echo json_encode($response);
					exit;
				} elseif ($ret == 1) {
					session_destroy();
					session_start();
					session_regenerate_id();
					${PROJ_SESSION_ID} = new userdat;

					$randomtoken = base64_encode(uniqid(rand(), true));

					$_SESSION[PROJ_SESSION_ID] = new userdat;
					$_SESSION[PROJ_SESSION_ID]->log_time = NOW2;
					$_SESSION[PROJ_SESSION_ID]->log_stat = "A";
					$_SESSION[PROJ_SESSION_ID]->user_id = $u_id;
					$_SESSION[PROJ_SESSION_ID]->user_name = $u_name;
					$_SESSION[PROJ_SESSION_ID]->user_level = $u_level;
					$_SESSION[PROJ_SESSION_ID]->sess = session_id();
					$_SESSION[PROJ_SESSION_ID]->rmadr = $_SERVER['REMOTE_ADDR'];
					$_SESSION[PROJ_SESSION_ID]->lhs_menu = true;
					$_SESSION[PROJ_SESSION_ID]->sess_token = $randomtoken;
					$_SESSION[PROJ_SESSION_ID]->sess_active = 'Y';
					$_SESSION[PROJ_SESSION_ID]->allow_vessel_close = 'N';
					$_SESSION[PROJ_SESSION_ID]->module_access = $MODULE_ACCESS_ARR;
					$_SESSION[PROJ_SESSION_ID]->menu_access = $MENU_ACCESS_ARR;
					$_SESSION[PROJ_SESSION_ID]->menu_details = $MENU_DETAILS;

					LogAttempt($username, 'S', 'Logged');

					$q = "update users set dtLastLogin='" . NOW . "', vLastLoginIP='" . $_SERVER['REMOTE_ADDR'] . "', vToken='$randomtoken', cActive='Y' where iUserID=$u_id";
					$r = sql_query($q, 'AUTH.78');
					$token = EncodeParam($u_id);
					$userlevel = GetXFromYID('select vName from levels where iLevelD=' . $u_level);
					$browser = '';
					$browser2 = getBrowser();
					if (!empty($browser2) && count($browser2))
						$browser = $browser2['name'] . ' ' . $browser2['version'];

					// $URL = 'home.php';
					//$URL2 = GetXFromYID('select vUrl from menu as m join user_role_access as ua on m.iMenuID=ua.iMenuID where m.cStatus="A" and ua.cStatus="A" and m.iModuleID=1 and ua.iModuleID=1 and ua.cPrimary="Y" and ua.iUserID='.$u_id);
					//if (!empty($URL2) && $URL2 != '-1') $URL = $URL2;

					//SELECT `iLYTPID`, `dtEntry`, `iUserID`, `vPassword`, `cIsTemp`, `cStatus` FROM `log_user_temppassword` WHERE 1
					// $IS_SALE_DASHBOARD = GetXFromYID('select count(*) from module_level_assoc where iModuleID=57 and cType="BL" and iLevelD='.$u_level);
					// if(!empty($IS_SALE_DASHBOARD) && $IS_SALE_DASHBOARD!='-1')
					// 	$URL = 'home2.php';
					$NOW= NOW;
					$response = array(
						"data" => array(
							"userInfo" => array(
							"user_id" => $u_id,
							"userName" => $u_name,
							"userLevel" => $userlevel,
							"date" => date('d/m/Y H:i:s', strtotime($NOW)),
							),
							"token" => $token,
							"modules" => $MODULE_ACCESS_ARR,
							"menuIds" => $MENU_ACCESS_ARR,
							"menu_details"=> $MENU_DETAILS
						),
						"statusCode" => 200,
					);
					http_response_code(200);
					header('Content-Type: application/json');
					echo json_encode($response);
					exit;
				}
			}
		} else
			session_destroy();
			$response = array(
				"error" => array(
					"message" => "Login Failed",
				),
				"statusCode" => 400,
			);
			http_response_code(400);
			header('Content-Type: application/json');
			echo json_encode($response);
			exit;
	} else {
		session_destroy(); // destroy all data in session
		$response = array('statusCode' => 403, 'message' => "Forbidden - You are not authorized to view this page",);
		http_response_code(403);
		header('Content-Type: application/json');
		echo json_encode($response);
		exit;
	}
} else {
	session_destroy(); // destroy all data in session
	//die("Forbidden - You are not authorized to view this page");
	$response = array('statusCode' => 403, 'message' => "Forbidden - You are not authorized to view this page",);
	http_response_code(403);
	header('Content-Type: application/json');
	echo json_encode($response);
	exit;
}
