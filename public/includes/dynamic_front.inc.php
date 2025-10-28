<?php
##	GETCONNECTED	###################################################################################
$CON = GetConnected();
$S3OBJ = (defined('USE_OBJECT_STORAGE') && USE_OBJECT_STORAGE=='Y')?ObjectStorage():null;

##	USER SESSION VARIABLES	###################################################################################
$logged = $sess_user_id = $sess_app_reg = $sess_app_spot = $sess_app_id = 0;
$sess_info_str = '';

if(isset($_SESSION[PROJ_RM_SESSION_ID]->log_stat)) // if the session variable has been set...
{	
	if($_SESSION[PROJ_RM_SESSION_ID]->log_stat == "A")
	{
		$logged = 1;
		$sess_user_id = $_SESSION[PROJ_RM_SESSION_ID]->user_id;
		$sess_user_name = $_SESSION[PROJ_RM_SESSION_ID]->user_name;
		$sess_user_sess = $_SESSION[PROJ_RM_SESSION_ID]->sess;
		$sess_login_time = FormatDate($_SESSION[PROJ_RM_SESSION_ID]->log_time, "B", 1);
		$sess_user_token = $_SESSION[PROJ_RM_SESSION_ID]->sess_token;
		$sess_user_active = $_SESSION[PROJ_RM_SESSION_ID]->sess_active;
		$sess_app_reg = $_SESSION[PROJ_RM_SESSION_ID]->reg_app;
		if($sess_app_reg=='Y') $sess_app_reg = '1';

		$sess_app_spot = $_SESSION[PROJ_RM_SESSION_ID]->spot_app;
		if($sess_app_spot=='Y') $sess_app_spot = '1';
	}
}

if(!$logged && empty($NO_REDIRECT))
{
	ForceOut(6);
}

/* if($logged)
{
	$sess_user_active = GetXFromYID("select cActive from adm_candidate where iCandidateID='$sess_user_id' and vToken='$sess_user_token'");
	$_SESSION[PROJ_RM_SESSION_ID]->sess_active = $sess_user_active;
} */

##	SYS_SETTINGS	###################################################################################
$q = "select cType, vCode, cData, vValue from sys_settings where cStatus='A'";
$r = sql_query($q, 'DYN.30');
while(list($sys_type, $sys_code, $sys_data, $sys_value) = sql_fetch_row($r))
{
	if($sys_data=='I')
		$sys_value = intval($sys_value);
	else if($sys_data=='N')
		$sys_value = floatval($sys_value);
	else if($sys_data=='B')
		$sys_value = boolval($sys_value);
	else
		$sys_value = strval($sys_value); // C, D

	if($sys_type=='D') // define
		define($sys_code, $sys_value);
	else if($sys_type=='V') // variable
		${$sys_code} = $sys_value;
	else if($sys_type=='A') // arrays
	{
		$x = json_decode($sys_value);

		foreach($x as $key=>$val)
			${$sys_code}[$key] = $val;
	}
}

##	SESSION->INFO	###################################################################################
$lbl_display = 'none'; // used for LBL_ERR
$sess_info_str = $sess_success_info = $sess_error_info = $sess_alert_info = $sess_warning_info = '';
if($logged)
{
	$sess_info = (isset($_SESSION[PROJ_RM_SESSION_ID]->info))? NotifyThis($_SESSION[PROJ_RM_SESSION_ID]->info, 'info'): '';
	$sess_success_info = (isset($_SESSION[PROJ_RM_SESSION_ID]->success_info))? NotifyThis($_SESSION[PROJ_RM_SESSION_ID]->success_info, 'success'): '';
	$sess_error_info = (isset($_SESSION[PROJ_RM_SESSION_ID]->error_info))? NotifyThis($_SESSION[PROJ_RM_SESSION_ID]->error_info, 'error'): '';
	$sess_alert_info = (isset($_SESSION[PROJ_RM_SESSION_ID]->alert_info))? NotifyThis($_SESSION[PROJ_RM_SESSION_ID]->alert_info, 'alert'): '';

	$sess_info_str = $sess_info . $sess_success_info . $sess_error_info . $sess_alert_info;

	$lbl_display = ($sess_info!="")? '': 'none';
	$_SESSION[PROJ_RM_SESSION_ID]->info = $_SESSION[PROJ_RM_SESSION_ID]->success_info = $_SESSION[PROJ_RM_SESSION_ID]->error_info = $_SESSION[PROJ_RM_SESSION_ID]->alert_info = '';	// */
}

if(!isset($_SESSION[PROJ_RM_SESSION_ID]))	// if there is no obj create it
{
	${PROJ_RM_SESSION_ID} = new alertdat2;
	$_SESSION[PROJ_RM_SESSION_ID] = new alertdat2;
	$_SESSION[PROJ_RM_SESSION_ID]->success_info = $_SESSION[PROJ_RM_SESSION_ID]->error_info = $_SESSION[PROJ_RM_SESSION_ID]->alert_info = $_SESSION[PROJ_RM_SESSION_ID]->warning_info = '';
	$_SESSION[PROJ_RM_SESSION_ID]->city_id = $_SESSION[PROJ_RM_SESSION_ID]->area_id = '0';
}

if(isset($_SESSION[PROJ_RM_SESSION_ID]->success_info) && !empty($_SESSION[PROJ_RM_SESSION_ID]->success_info))
	$sess_success_info = $_SESSION[PROJ_RM_SESSION_ID]->success_info;
if(isset($_SESSION[PROJ_RM_SESSION_ID]->error_info) && !empty($_SESSION[PROJ_RM_SESSION_ID]->error_info))
	$sess_error_info = $_SESSION[PROJ_RM_SESSION_ID]->error_info;
if(isset($_SESSION[PROJ_RM_SESSION_ID]->alert_info) && !empty($_SESSION[PROJ_RM_SESSION_ID]->alert_info))
	$sess_alert_info = $_SESSION[PROJ_RM_SESSION_ID]->alert_info;
if(isset($_SESSION[PROJ_RM_SESSION_ID]->warning_info) && !empty($_SESSION[PROJ_RM_SESSION_ID]->warning_info))
	$sess_warning_info = $_SESSION[PROJ_RM_SESSION_ID]->warning_info;

$_SESSION[PROJ_RM_SESSION_ID]->success_info = $_SESSION[PROJ_RM_SESSION_ID]->error_info = $_SESSION[PROJ_RM_SESSION_ID]->alert_info = $_SESSION[PROJ_RM_SESSION_ID]->warning_info = '';

$sess_info_str = $sess_success_info . $sess_error_info . $sess_alert_info;

function NotifyThis($text, $mode='alert')
{
	if($mode == 'success') $mode_str = 'alert-success';
	else if($mode == 'error') $mode_str = 'alert-danger';
	else if($mode == 'info') $mode_str = 'alert-warning';
	else $mode_str = 'alert-warning';

	if($mode == 'success') $mode_icon = 'fa fa-check-circle';
	else if($mode == 'error') $mode_icon = 'fa fa-times-circle';
	else if($mode == 'info') $mode_icon = 'fa fa-exclamation-circle';
	else $mode_icon = 'fa fa-question-circle';

	
	$text = trim($text);
	return ($text!='')?'<div class="alert '.$mode_str.' alert-dismissible fade show" role="alert"><button type="button" class="close" aria-label="Close"><spanaria-hidden="true">×</span></button><i class="'.$mode_icon.' mr-1 text-muted opacity-6"></i>'.$text.'</div>':'';
}
?>