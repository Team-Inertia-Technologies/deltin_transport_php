<?php
function LogMasterEdit($id, $flag, $mode, $name='', $desc_str='', $user_id=false)
{
	global $_POST, $_SERVER, $sess_user_locid, $sess_user_id, $sess_user_name;

	if(empty($name))
	{
		if(isset($_POST['txtname']) && !empty($_POST['txtname']) && trim($_POST['txtname'])!='') $name = $_POST['txtname'];
		else if($flag=='USR') $name = GetXFromYID("select vName from users where iUserID=$id");
		else if($flag=='VND') $name = GetXFromYID("select vName from vendor where iVendorID=$id");
		else if($flag=='VHC') $name = GetXFromYID("select vRnum from vehicle where iVehicleID=$id");
		else if($flag=='DRV') $name = GetXFromYID("select vName from driver where iDriverID=$id");
		else if($flag=='RTE') $name = GetXFromYID("select vName from st_route where iRouteID=$id");
		else if($flag=='STF') $name = GetXFromYID("select vName from staff where iStaffID=$id");
	}
	
	$ip = $_SERVER['REMOTE_ADDR'];
	
	if(empty($desc_str))
	{
		if($mode=='I') $desc_str = 'Newly Created';
		else if($mode=='U') $desc_str = 'Updated';
		else if($mode=='D') $desc_str = 'Deleted';
	}
	
	// Always log the operation
	$u_id = $u_loc_id = 0;
	$u_name = 'API User';
	
	if(isset($sess_user_id) && is_numeric($sess_user_id))
	{
		$u_id = $sess_user_id;
		$u_loc_id = 0; //$sess_user_locid;
		$u_name = $sess_user_name;
	}
	
	if(!empty($user_id))
	{
		$q = "select iUserID, vName from users where iUserID=$user_id";
		$r = sql_query($q, 'COM.1410');
		if(sql_num_rows($r))
			list($u_id, $u_name) = sql_fetch_row($r);
	}
	
	if(empty($u_loc_id)) $u_loc_id = 0;
	if(empty($u_id)) $u_id = 0;
	
	$lmid = NextID('iLMID', 'log_masters');
	$q = "insert into log_masters values ($lmid, $u_loc_id, $u_id, '".db_input($u_name)."', '".NOW."', $id, '$flag', '".db_input($name)."', '".db_input($desc_str)."', '$mode', '$ip', 'A')";
	$r = sql_query($q, 'COM.1421');
}

function showOldVal($label, $curr, $old) {
	if ($curr != $old && !empty($old)) {
		return " <span style='color:red; font-weight:bold;'>(Old: " . htmlspecialchars($old) . ")</span>";
	}
	return "";
}

function PrepareEditedDesc()
{
	global $_POST;
	$arr = $_POST;
	$str = '';
	
		
	foreach($arr as $key=>$val)
	{
		$key_len = strlen($key);
		$key_new = substr($key,0,($key_len-4));
	
		if((strpos($key, '_old') != ($key_len-4)) || !isset($arr[$key_new]))
			continue;

		$old = $val;
		$new = $arr[$key_new];
		
		if($old!=$new)
		{
			$key_title = $key_new.'_title';
			$title = (isset($arr[$key_title]))? $arr[$key_title]: substr($key_new, 3);
			$ref_flag = (isset($arr[$key_new.'_ref']))? $arr[$key_new.'_ref']: false;
			$arr_flag = (isset($arr[$key_new.'_arr']))? $arr[$key_new.'_arr']: false;
			
			if($ref_flag)
			{
				$ref_arr = array();
				
				JustID($old);
				JustID($new);

				if($ref_flag=='users') 
					$ref_arr = GetXArrFromYID("select iUserID, vName from users where iUserID in ($old, $new)", '3');
				else if($ref_flag=='vendor')
					$ref_arr = GetXArrFromYID("select iVendorID, vName from vendor where iVendorID in ($old, $new)", '3');
				else if($ref_flag=='vehicle')
					$ref_arr = GetXArrFromYID("select iVehicleID, vRnum from vehicle where iVehicleID in ($old, $new)", '3');
				else if($ref_flag=='driver')
					$ref_arr = GetXArrFromYID("select iDriverID, vName from driver where iDriverID in ($old, $new)", '3');
				else if($ref_flag=='route')
					$ref_arr = GetXArrFromYID("select iRouteID, vName from st_route where iRouteID in ($old, $new)", '3');

				if(count($ref_arr))
				{
					$old = (isset($ref_arr[$old]))? $ref_arr[$old]: 'n/a';
					$new = (isset($ref_arr[$new]))? $ref_arr[$new]: 'n/a';
				}
			}
			else if($arr_flag)
			{
				global ${$arr_flag};
				
				$old = (isset(${$arr_flag}[$old]))? ${$arr_flag}[$old]: 'n/a';
				$new = (isset(${$arr_flag}[$new]))? ${$arr_flag}[$new]: 'n/a';
			}
			
			$str .= '| <strong><u>'.strtoupper($title).'</u>:</strong> <span class="text-danger">'.$old.'</span> -&gt; <span class="text-success">'.$new.'</span>';
		}
	}

	return ($str!='')? substr($str, 1): '';
}
?>