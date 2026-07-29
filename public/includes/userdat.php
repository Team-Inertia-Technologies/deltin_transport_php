<?php 
// data that needs to be rememberered...
#[\AllowDynamicProperties]
class userdat
{
	var $log_time;		// time of login
	var $log_stat;		// log status - is the user logged in or not
	var $sess_id;		// session id
///////////////////////////////////////////////

	var $user_id;		// de user's id		
	var $user_code;		// de user's id		
	var $user_name;		// de user's name	
	var $user_level;	//
	var $user_pic;	//	
	var $user_lastlogin;	//	
	var $user_ip;	//	
	var $user_reftype;
	var $user_refid;

	var $lhs_menu = true;
	
	var $info;			// error msg
	var $success_info;		// error msg
	var $error_info;			// error msg
	var $alert_info;			// error msg
	var $sess_token;
	var $sess_active;
}

if (session_status() === PHP_SESSION_NONE)
{
	ini_set('session.gc_maxlifetime', '3600');

	// Some deployments leave session.save_path empty. For file-based
	// sessions, use PHP's writable temporary directory as a safe fallback.
	if (ini_get('session.save_handler') === 'files' && trim(session_save_path()) === '')
	{
		$tempSessionPath = sys_get_temp_dir();
		if (is_dir($tempSessionPath) && is_writable($tempSessionPath))
		{
			session_save_path($tempSessionPath);
		}
	}

	// Do not corrupt JSON/API responses with a PHP warning.
	if (!@session_start())
	{
		$sessionError = error_get_last();
		error_log('Unable to start PHP session: ' . ($sessionError['message'] ?? 'unknown error'));
	}
}
?>