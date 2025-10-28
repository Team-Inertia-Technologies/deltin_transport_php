<?php
function IsUniqueEntry($id_fld, $id_val, $txt_fld, $txt_val, $tbl)
{
	$ret_val = '2';
	$curr_txt = (isset($id_val))? GetXFromYID("select $txt_fld from $tbl where $id_fld=$id_val"): "";
	
	if($txt_val!='' && $txt_val != $curr_txt) // no change in value, ignore
	{
		$q_str = (isset($id_val))? " and $id_fld!=$id_val": "";
		$chk = GetXFromYID("select count(*) from $tbl where $txt_fld='$txt_val' " . $q_str);

		$ret_val = ($chk)? '0': '1';
	}
	
	return $ret_val;
}

function SetMinRank($tb1,$cond="")
{
	$cond = (strtoupper(trim($cond)) != "")? " where " . $cond: "";	
	$min = GetXFromYID("select min(iRank) from $tb1 $cond");
	
	if($min == 0)
	{
		mysql_query("update $tb1 set iRank=iRank+2 $cond")or die("<strong>ERROR CODE :</strong> COM-68");
	}
	else
	{
		mysql_query("update $tb1 set iRank=iRank+1 $cond")or die("<strong>ERROR CODE :</strong> COM-72");
	}
	
	return 1;
}

function ContactDetails($phone, $mobile, $email)
{	
	$arr = array();

	if($phone!='' || $mobile!='' || $email!='')
	{
		if(!empty($email))
			$arr['email'] = $email;

		if(!empty($mobile))
			$arr['mobile'] = $mobile;

		if(!empty($phone))
			$arr['phone'] = $phone;
	}

	return '&nbsp;'.implode(', ',$arr);
}

function FillYearArr()
{
	$arr = array();
	for($i=START_YEAR;$i<=THIS_YEAR;$i++)
		$arr[$i] = $i.'-'.($i+1);
	return $arr;
}

/* 
function FillLocations($selected, $ctr, $tp, $comp, $cond, $fn="", $class="form-control")
{	
	$display = ($tp=="COMBO" || $tp=="COMBO2")? "": "size=10";
	$cond = (strtoupper(trim($cond)) != 'N' && trim($cond)!='')? " where " . $cond: "";	
	$class_str = (trim($class)=="")? "": $class;

	$stat_fld = ($tp == "COMBO")? ", 'A' ": ", cStatus";
	
	$q = "select iLocID, vName, iLevel " . $stat_fld . " from gen_location " . $cond . " order by iRank";
	$result = sql_query($q, 'COM.130');
	$str = '<select name="'.$ctr.'" id="'.$ctr.'" class="'.$class_str.'" '.$display.' '.$fn.'>'."\n"; //

	if($comp<>'y'&&$comp<>'Y') 
	{
		$str .= '<option value="0" selected> - select - </option>'."\n";
	}

	while(list($id,$nm,$level,$stat)=sql_fetch_row($result))
	{
		$stat_style = ($stat=="A" && $tp=="COMBO2")? "": ' style="background-color: #FFC5C5;"';
		$selected_str = (trim($selected) == trim($id))? "selected": "";
		$space = GenerateSpace($level);
		$str .=  '<option value="'.$id.'" '.$selected_str.'>'.$space.$nm.'</option>'."\n";
	}

	$str .= '</select>'."\n";
	return $str;
}

function FillPropclass($selected, $ctr, $tp, $comp, $cond, $fn="", $class="form-control")
{
	$display = ($tp=="COMBO" || $tp=="COMBO2")? "": "size=10";
	$cond = (strtoupper(trim($cond)) != 'N' && trim($cond)!='')? " where " . $cond: "";	
	$class_str = (trim($class)=="")? "": $class;

	$stat_fld = ($tp == "COMBO")? ", 'A' ": ", cStatus";
	
	$q = "select iPropClassID, vName, iLevel " . $stat_fld . " from gen_propclass " . $cond . " order by iRank";
	$result = sql_query($q, 'COM.130');
	$str = '<select name="'.$ctr.'" id="'.$ctr.'" class="'.$class_str.'" '.$display.' '.$fn.'>'."\n"; //

	if($comp<>'y'&&$comp<>'Y') 
	{
		$str .= '<option value="0" selected> - select - </option>'."\n";
	}

	while(list($id,$nm,$level,$stat)=sql_fetch_row($result))
	{
		$stat_style = ($stat=="A" && $tp=="COMBO2")? "": ' style="background-color: #FFC5C5;"';
		$selected_str = (trim($selected) == trim($id))? "selected": "";
		$space = GenerateSpace($level);
		$str .=  '<option value="'.$id.'" '.$selected_str.'>'.$space.$nm.'</option>'."\n";
	}

	$str .= '</select>'."\n";
	return $str;
}	// */

function GetDuration($dfrom, $dto)
{
	$str = '';
	
	if($dfrom!='')	$str .= $dfrom;	
	if($dfrom!='' && $dto!='')	$str .= ' to: ';	
	if($dto!='')	$str .= $dto;
	
	if($str=='')	$str = '&nbsp;';	
	return $str;
}
function GetUrlName($title)
{
	$title = htmlspecialchars_decode($title);
	$URL_CHAR_ARR = array("%","/",".","#","?","*","!","@","&",":","|",";","=","<",">","^","~","'","\"",",","-","(",")","'",'"','\\');
	$rurl = trim($title);
	$rurl = str_replace($URL_CHAR_ARR,'',$title);
	$rurl = str_replace('   ',' ',$rurl);
	$rurl = str_replace('  ',' ',$rurl);
	$rurl = str_replace(' ','-',$rurl);
	$rurl = trim(strtolower($rurl));

	return $rurl;
}

function GetLocationArr($level, $parentid, $arr, $mode="1")
{
	return GetTreeArr('iLocID', 'gen_location', $level, $parentid, $arr, $mode);
}

function GenerateSpace($level, $symbol='&nbsp;&nbsp;')
{
	$space = '';
	
	for($i=0; $i < $level; $i++)
		$space .= $symbol;
		
	return $space;
}

function GetBookPrimaryCategory()
{
	$arr=array();
	$q="select iBookID, iCategoryID from book_category where cFeatured='Y'";
	$r = sql_query($q) or die("<strong>Error Code: COM1802</strong>");
	while(list($b_id,$c_id) = sql_fetch_row($r))
	$arr[$b_id] = $c_id;
	return $arr;
}

function GetCatUrlName($sess_lan)
{
	$q_str = ($sess_lan=='G')?'vG_UrlName':'vE_UrlName';

	$arr=array();
	$q="select iCatID, $q_str from category";
	$r = sql_query($q) or die("<strong>Error Code: COM1802</strong>");
	while(list($c_id,$c_name) = sql_fetch_row($r))
	$arr[$c_id] = $c_name;
	return $arr;
}

function GetFirstOfMonth($date_ymd)
{
	list($y,$m,$d) = explode('-',$date_ymd);
	return $y.'-'.$m.'-01';
}

function GetLastOfMonth($date_ymd, $month_offset=0)
{
	$d = GetFirstOfMonth($date_ymd);
	return DateTimeAdd($d, -1, ($month_offset+1), 0, 0, 0, 0, $format="Y-m-d");
}
function GetPropertyData($cond = "")
{
	$response_arr = array();
	$q = "select * from property where cStatus = 'A' $cond";
	$r = sql_query($q, "common.inc.3729");

	if (sql_num_rows($r)) {

		while ($row = sql_fetch_assoc($r)) {
			$response_arr[] = $row;
		}
	}
	return $response_arr;
}

function GetColorData($cond = "")
{
	$response_arr = array();
	$q = "select * from gen_color where cStatus = 'A' order by iRank";
	$r = sql_query($q, "common.inc.3729");

	if (sql_num_rows($r)) {

		while ($row = sql_fetch_assoc($r)) {
			$response_arr[] = $row;
		}
	}
	return $response_arr;
}

function GetGroupPackageData($cond = "")
{
	$response_arr = array();

	$q = "select p.iPackageID, p.iPackageCategoryID, p.iGroupID, p.iPropertyID, p.vName, p.vDisplayName, p.cCode, p.iColorID, p.iPax, p.cGender, p.cType, p.iPoints, p.vDays, p.dFrom, p.dTo, p.tFrom, p.tTo, p.cMatchPlayPackage, p.cOtherPackage, p.cKioskPackage, p.cNonNegPackage, p.cDiscountPackage, p.vInclusions, p.vChoiceOfDrinks, p.vDesc, p.cStatus, p.iMemLevelID, p.iMemTypeID, i.iInvID, i.iPackageID as inv_pack, i.iPropertyID as inv_prop, i.dFrom as inv_from, i.dFrom_website as inv_from_website, i.dTo as inv_to, i.dTo_website as inv_to_website, i.tFrom as inv_tfrom, i.tFrom_website as inv_tfrom_website, i.tTo as inv_tto, i.tTo_website as inv_tto_website, i.fRate, i.fRate_corporate, i.fRate_website, i.iInventory, i.iInventory_website, i.iChips, i.iChips_website, i.iMPC, i.iMPC_website, i.iNNPC, i.iNNPC_website, i.cStatus as inv_status, p.cWebsite, p.cHidden, p.cPackageType, i.iInventory_corporate as iInventory_corporate, i.iChips_corporate as iChips_corporate, i.iMPC_corporate as iMPC_corporate, i.iNNPC_corporate as iNNPC_corporate from package p left outer join inventory i on p.iPackageID = i.iPackageID where 1 $cond";
	$r = sql_query($q, "common.inc.3758");

	if (sql_num_rows($r)) {

		while ($row = sql_fetch_assoc($r)) {
			$response_arr[$row['iPropertyID'] . "_" . $row['iGroupID']] = $row;
		}
	}

	return $response_arr;
}

function GetPackageData($cond = "")
{
	$response_arr = array();

	$q = "select p.iPackageID, p.iPackageCategoryID, p.iGroupID, p.iPropertyID, p.vName, p.vDisplayName, p.cCode, p.iColorID, p.iPax, p.cGender, p.cType, p.iPoints, p.vDays, p.dFrom, p.dTo, p.tFrom, p.tTo, p.cMatchPlayPackage, p.cOtherPackage, p.cKioskPackage, p.cNonNegPackage, p.cDiscountPackage, p.vInclusions, p.vChoiceOfDrinks, p.vDesc, p.cStatus, i.iInvID, i.iPackageID as inv_pack, i.iPropertyID as inv_prop, i.dFrom as inv_from, i.dFrom_website as inv_from_website, i.dTo as inv_to, i.dTo_website as inv_to_website, i.tFrom as inv_tfrom, i.tFrom_website as inv_tfrom_website, i.tTo as inv_tto, i.tTo_website as inv_tto_website, i.fRate, i.fRate_corporate, i.fRate_website, i.iInventory, i.iInventory_website, i.iInventory_corporate as iInventory_corporate, i.iChips, i.iChips_website, i.iChips_corporate as iChips_corporate, i.iMPC, i.iMPC_website, i.iMPC_corporate as iMPC_corporate, i.iNNPC, i.iNNPC_website, i.iNNPC_corporate as iNNPC_corporate, i.cStatus as inv_status, p.cWebsite, p.cHidden from package p join inventory i on p.iPackageID = i.iPackageID where 1 $cond";
	$r = sql_query($q, "common.inc.3758");

	if (sql_num_rows($r)) {

		while ($row = sql_fetch_assoc($r)) {
			$response_arr[$row['iPackageID']] = $row;
		}
	}

	return $response_arr;
}

function SetStatusFlags($status)
{
	global $is_inactive, $is_active, $is_complete;
	
	if($status=='C')		$is_complete = true;
	else if($status=='A')	$is_active = true;
	else					$is_inactive = true;
} 

function GetWeekStart($dt)
{
	$curr_day = date('w', strtotime($dt));
	
	$curr_week_start_offset = ($curr_day != WEEK_START_DAY)? ($curr_day - WEEK_START_DAY): 0;
	return DateTimeAdd($dt, -$curr_week_start_offset, 0, 0, 0, 0, 0, 'Y-m-d');
}

function GetRelevantItemCatIDArr($icat_id)
{
	$arr = array();
	$arr[$icat_id] = $icat_id;
	$arr = GetSubItemCat(1, $icat_id, $arr, '4');
	return $arr;
}

function GetGenLocationDat($cond='')
{
	$arr = array();
	
	$q = "select iLocID, vName from gen_location where 1 $cond"; // iLocID is not NULL
	$r = sql_query($q, 'COM.16');
	while(list($id, $name) = sql_fetch_row($r))
		$arr[$id] = $name;	
	
	return $arr;
}

function GetLevelStr($level, $char='&nbsp;')
{
	$str = '';
	
	for($i=1; $i < $level; $i++)
		$str .= $char;
		
	return $str;
}

function GetUniqueCode($id, $val, $pk_fld, $code_fld, $tbl, $char_len=1, $num_len=2, $min_num=0) // GetItemUniqueCode
{
	$ret_val = '';
		
	$val = strtoupper(trim($val));
	$prefix = substr($val, 0, $char_len);
	
	$ret_val = $prefix . str_pad('1', 2, '0', STR_PAD_LEFT);
	$code_arr = array();
	
	$q_str = ($id)? ' and '.$pk_fld.'!='.$id: '';
	
	$q = "select upper($code_fld) from $tbl where $code_fld like '$prefix%' ".$q_str;
	$r = sql_query($q, 'COM.160');
	while(list($code) = sql_fetch_row($r))
	{
		$code_no = str_replace($prefix, '', $code);
		
		if(!is_numeric($code_no))
			$code_no = '0';
			
		$code_arr[$code] = $code_no;
	}
			
	if(count($code_arr))
	{
		rsort($code_arr, SORT_NUMERIC);
		reset($code_arr);
		
		$code_no = max($code_arr[0], $min_num) + 1;
		$ret_val = $prefix . str_pad($code_no, 2, '0', STR_PAD_LEFT);
	}
	
	return $ret_val;
}

function GetGenTaxDat()
{
	$arr = array();
	$q = "select iTaxID, vName from gen_tax where 1 order by vName";
	$r = sql_query($q, 'COM.742');
	while(list($id, $name) = sql_fetch_row($r))
		$arr[$id] = $name;
	
	return $arr;
}

function GetGenTaxValueArr($cond='N', $ord='a.iRank, b.fPerc')
{	
	$arr = array('0.0'=>'NA: 0%');
	$q = "select CONCAT(a.iTaxID,'.', fPerc), CONCAT(a.vName,': ',fPerc,'%') from gen_tax as a, gen_tax_values as b 
			where a.iTaxID=b.iTaxID " . $cond . " order by " . $ord;
	$r = sql_query($q, 'COM.921');
	while(list($id, $nm) = sql_fetch_row($r))
		$arr[$id] = $nm;

	return $arr;
}

function FillGenTaxValues($selected, $ctr, $tp, $comp, $cond='N', $ord='a.iRank, b.fPerc', $fn='', $class='')
{	
	$display = ($tp=="COMBO" || $tp=="COMBO2")? "": "size=10";
	$cond = (strtoupper(trim($cond)) != 'N' && trim($cond)!='')? " and " . $cond: "";	
	$class_str = (trim($class)=="")? "": $class;

	$stat_fld = ($tp == "COMBO")? ", 'A' ": ", a.cStatus";
	
	$q = "select CONCAT(a.iTaxID,'|',fPerc), CONCAT(fPerc,'% (',a.vName,')') " . $stat_fld . " from gen_tax as a, gen_tax_values as b where a.iTaxID=b.iTaxID " . $cond . " order by " . $ord;
	$r = sql_query($q, 'COM.939');
	$str ="<select name='$ctr' id='$ctr' class='$class_str' $display $fn>\n"; //

	if($comp<>'y'&&$comp<>'Y') 
	{
		if($comp=='0')
			$str .= "<option value='0|0' selected>NA: 0%</option>\n";
	}
	
	$level_arr = array();

	while(list($id, $nm, $stat) = sql_fetch_row($r))
	{
		$stat_style = ($stat=="A" && $tp=="COMBO2")? "": " style='background-color: #FFC5C5;'";
		$selected_str = (trim($selected) == trim($id))? "selected": "";
		
		$str .= "<option value='$id' $selected_str>$nm</option>\n";
	}

	$str .= "</select>\n";
	return $str;
}

function GetGenUserDat($cond='')
{
	$arr = array();
	
	$q = "select iUID, vName from gen_user where 1 $cond"; // iLocID is not NULL
	$r = sql_query($q, 'COM.1549');
	while(list($id, $name) = sql_fetch_row($r))
		$arr[$id] = $name;
	
	return $arr;
}

function GetParentItemCategoryArr(&$parent_item_cat_arr, &$item_cat_arr)
{
	$q = "select iItemCatID, vName, iParentID, iAncestorID from gen_cat order by vName";
	$r = sql_query($q, 'COM.1685');
	while(list($itemcat_id, $itemcat_name, $parent_id, $ancestor_id) = sql_fetch_row($r))
	{
		$item_cat_arr[$itemcat_id] = array('NAME'=>$itemcat_name, 'ANCESTOR'=>$ancestor_id);
		
		if(empty($parent_id))
			$parent_item_cat_arr[$itemcat_id] = $itemcat_name;
	}
}

function CalcWeightedAdjustValue($item_value, $total_value, $total_adjust)
{
	return (!empty($total_value) && !empty($total_adjust))? $item_value/ $total_value * $total_adjust: 0;
}

function GetAdjustedMISStartDt($dfrom)
{}

function LogReset($ref_id, $ref_type) // GR/ GRRET/ STI/ STO
{
	global $sess_user_id;

	$q = "INSERT INTO log_reset (iLocID, iRefID, cRefType, dtLog, iUserID, cFlag, cStatus) 
			VALUES ('".SYS_LOCID."', $ref_id, '$ref_type', '".NOW."', '$sess_user_id', '0', 'A')";
	$r = sql_query($q, 'COM.5136');
}

function LogQuery($q_str, $error_flag)
{
	global $sess_user_id;
	
	$q = "INSERT INTO log_query (iLocID, dtLog, vQuery, iUserID, cFlag, cStatus) 
			VALUES ('".SYS_LOCID."', '".NOW."', '".addslashes($q_str)."', '$sess_user_id', '$error_flag', 'A')";
	$r = sql_query($q, 'COM.5144');
}

function IsReset($id, $type)
{
	return (!empty($id) && !empty($type))? GetXFromYID("select count(*) from log_reset where iRefID=$id and cRefType='$type'"): 0;
}

function QuickAddClient($name)
{
	$id = NextID('iClientID', 'client');
	$code = GetUniqueCode($id, $name, 'iClientID', 'cCode', 'client');
	$q = "insert into client values ($id, '$code', '$name', '$name', '', '', '', '', '', '', 0, '', '".TODAY."', 'B', 'A')";
	$r = sql_query($q, 'COM.528');
	return $id;
}

function QuickAddContact($name, $ref_id, $ref_type)
{
	$id = NextID('iContactID', 'contacts');
	$q = "insert into contacts values ($id, '$ref_type', $ref_id, '$name', '', '', '', '', '', '', '', '', 'Y', 'A')";
	$r = sql_query($q, 'COM.535');
	return $id;
}

function GetLeadStatusIcon($lead_status_id, $status_str='')
{
	global $LSTAT_IMG_SML_ARR;
	$x_stat_img = (isset($LSTAT_IMG_SML_ARR[$lead_status_id]))? $LSTAT_IMG_SML_ARR[$lead_status_id]: '';
	return '<a class="lead_status" style="background:url('.$x_stat_img.') no-repeat #fff  4px center;">'.$status_str.'</a>';
}

function GetLeadDatDirectionStr($direction) //, $responsibility
{
	$str = '&nbsp;';
	
	if($direction=='O') //  && $responsibility=='S'
		$str = '<img src="images/sales_customer.png" title="Contact Client"/>';
	elseif($direction=='I') //  && $responsibility=='S'
		$str = '<img src="images/customer_sales.png" title="Feedback from Client"/>';
//	elseif($direction=='0' && $responsibility=='S')
//		$str = '<img src="images/sales_sales.png" title="Discuss within"/>';
	
	return $str;
}

function DisplayActivityStatus($status) // X: Cancelled, N: New, A: Active/ Inprocess, C: Complete
{	
	$css = '';
	$txt = 'na';
	if($status == 'P') 
	{
		$css = 'pending_3d';
		$txt = 'pending';
	}
	elseif($status == 'C') 
	{
		$css = 'complete_3d';
		$txt = 'complete';
	}
	
	return '<span class="'.$css.'">'.$txt.'</span>';
}

function GenerateCode($mode, $id=false)
{
	if(!$id)
	{
		if($mode=='QUOTE')
			$id = NextID('iQuoteID', 'quote');
	}
	
	if(!$id)	$id = 1;

	$prefix = '';
	
	if($mode=='QUOTE')		$prefix = 'Q';
	else if($mode=='LEAD')	$prefix = 'L';

	return $prefix.str_pad($id, 5, '0', STR_PAD_LEFT);
}

function GetClientName($client_id)
{
	JustID($client_id);
	return GetXFromYID("select vName from client where iClientID=$client_id");
}

function GetServiceName($service_id)
{
	JustID($service_id);
	return GetXFromYID("select vName from service where iServiceID=$service_id");
}

function GetUserName($user_id)
{
	JustID($user_id);
	return GetXFromYID("select vName from gen_user where iUID=$user_id");
}

function GetUserCode($user_id)
{
	JustID($user_id);
	return GetXFromYID("select cCode from gen_user where iUID=$user_id");
}

function GetServiceStandardName($service_standard_id)
{
	JustID($service_standard_id);
	return GetXFromYID("select vName from service_standard where iServiceStandardID=$service_standard_id");
}

/* function GetServiceLevelName($service_level_id)
{
	JustID($service_level_id);
	return GetXFromYID("select vName from service_level where iServiceLevelID=$service_level_id");
}	// */

function GetStatusString($status, $status_arr, $mode='1')
{
	$str = '';

	if($status == 'A')		$css = 'success';
	else if($status == 'I')	$css = 'warning';
	else if($status == 'P')	$css = 'warning';
	else					$css = 'info';
		
	if(isset($status_arr[$status]))
	{
		if($mode=='2')
			$str = '<span class="badge badge-'.$css.'">'.$status_arr[$status].'</span>';
		else if($mode=='3')
			$str = '<input type="button" name="btn_just" value="'.$status_arr[$status].'" class="btn-'.$css.' btn">';
		else
			$str = '<span class="label label-'.$css.'">'.$status_arr[$status].'</span>';
	}
	
	return $str;
}

function GetFinancialYears($date) // date: yyyy-mm-dd
{
	$start_year = THIS_YEAR;
	$end_year = THIS_YEAR + 1;
	list($year, $month, $day) = explode('-', $date);

	if(intval($month) < 4)
	{
		$start_year--;
		$end_year--;
	}
	
	return $start_year.'-'.$end_year;
}

function GetSuffix($parent_id, $table_name, $key_field)
{
	$x = '';
	MustID($parent_id);
	
	if($parent_id)
	{
		$x = GetXFromYID("select max(cSuffix) from $table_name where $key_field=$parent_id");
		$x = ($x)? ++$x: 'a';
	}
	
	return $x;
}

function GetUserDetails($cond="")
{
	$arr = array();	
	$q = "select iUserID, vName, vPic, cStatus from users where 1 $cond order by iLevel, vName";
	$r = sql_query($q, 'COM.1187');
	while(list($id, $name, $pic, $status) = sql_fetch_row($r))
		$arr[$id] = array('id'=>$id, 'text'=>$name, 'name'=>$name, 'status'=>$status, 'pic'=>$pic);
	
	return $arr;
}

function GetTableTreeDetails($parent_id, $pk_id, $tbl, $pk_fld)
{
	$ancestorid = $pk_id;
	$level = 0;

	if(!empty($parent_id))
	{
		$q = "select iAncestorID, iLevel from $tbl where $pk_fld=$parent_id";
		$r = sql_query($q, 'GL_E.250');
		if(sql_num_rows($r))
		{
			list($ancestorid, $level) = sql_fetch_row($r);
			
			if(empty($ancestorid)) $ancestorid = $pk_id;
			$level++;
		}
	}

	return array($ancestorid, $level);
}

function GetTreeArr($tbl, $pk_fld, $level, $parentid, $arr, $mode="1", $cond='', $order='vE_Name, iLevel')
{
	$space = "";
	$level++;
	$q = "select * from $tbl where iParentID=$parentid and $pk_fld!=$parentid $cond order by $order";
	$r = sql_query($q, 'COM.400');
	
	if(sql_num_rows($r))
	{
		if($mode == "1")
		{
			for($i=0; $i < $level; $i++)
				$space .= "&nbsp;&nbsp;";
		}
		elseif($mode == "2") {}
		
		while($a = sql_fetch_assoc($r))
		{
			$id = $a[$pk_fld];
			$arr[$id] = $a;
			$arr[$id]['space'] = $space;
			
			if($mode=='3')
				$arr[$id] = $id;
			else
			{
				$arr[$id] = $a;
				$arr[$id]['space'] = $space;
				$arr[$id]['level'] = $level;
			}
			
			$arr = GetTreeArr($tbl, $pk_fld, $level, $id, $arr, $mode, $cond, $order);
		}
	}

	return $arr;
}

function SortTreeStruct($tbl, $pk_fld)
{
	$arr = array();
	$arr = GetTreeArr($tbl, $pk_fld, -1, 0, $arr);
	
	$i=0;
	foreach($arr as $id=>$a)
	{		
		$q = "update $tbl set iRank=".(++$i).", iLevel=".$a['level']." where $pk_fld=$id;";
		$r = sql_query($q, 'COM.704');
	}
}

function SuggestPassword()
{
	$arr = array();
	
	// length: 6 - 8 chars
	$len = rand(6, 8);	
	// echo $len."<br>";
	
	// atleast 1 uppercase char
	$a_len = rand(1, $len-3);
	// echo 'a_len: '.$a_len.'<br>';
	
	for($i=0; $i<$a_len; $i++)
		$arr[$i] = chr(rand(65, 90));

	$ctr = $i;

	// atleast 1 lowercase char
	$b_len = rand(1, $len-2-$a_len);
	// echo 'b_len: '.$b_len.'<br>';

	for(;$i<($ctr+$b_len); $i++)
		$arr[$i] = chr(rand(97, 122));

	$ctr = $i;

	// atleast 1 number
	$c_len = rand(1, $len-1-$a_len-$b_len);
	// echo 'c_len: '.$c_len.'<br>';
	
	for(;$i<($ctr+$c_len); $i++)
		$arr[$i] = rand(0, 9);

	$ctr = $i;

	// atleast 1 symbol
	$symb_arr = array('!', '#', '$', '%', '&', '*', '+', ',', '-', '.', ':', '=', '?', '@', '_', '~');
	$d_len = $len-$a_len-$b_len-$c_len;
	// echo 'd_len: '.$d_len.'<br>';
	
	for(;$i<($ctr+$d_len); $i++)
		$arr[$i] = $symb_arr[rand(0, count($symb_arr)-1)];

	// DFA($arr);
	shuffle($arr);
	// DFA($arr);
	
	$str = '';	
	foreach($arr as $a)
		$str .= $a;
	
	return $str;
}

function CutMe($text, $length){
    if(strlen($text) > $length) {  
	$text = substr($text, 0, strpos($text, ' ', $length)); 
	}    
	return $text;
}

function SendSMS($contact, $sms_content, $content_id, $page_url)
{
	$data = array(
	    "username"=>"imagoa",
	    "password"=>"123456",
	    "sender"=>"IMAGOA",
	    "mobile"=>$contact,
	    "message"=>$sms_content,
	    "route"=>"T",
	    "entity_id"=>"1201162169168701817",
	    "content_id"=>$content_id   
	);

	list($header, $content) = PostRequest("http://shudhsms.in/sendsms.php", $page_url, $data);

	return $content;
}

//RM functions
function GetRMContacts($cond=''){
	$where_cond = '';
	$response_arr = array();
	if(!empty($cond)){
		$where_cond .= "$cond";
	}
	
	$q = "select * from gen_rm_contacts where 1 $where_cond";
	$r = sql_query($q, "common.inc.720");
	
	if(sql_num_rows($r)){
		while($row = sql_fetch_assoc($r)){
			
			$response_arr[] = $row;
			
		}
	}
	return $response_arr;
}

function GetRMRequests($cond=''){
	$where_cond = '';
	$response_arr = array();
	if(!empty($cond)){
		$where_cond .= "$cond";
	}
	
	$q = "select * from concrequest cr join concsession cs on cr.iConcSessionID = cs.iConcSessionID where 1 $where_cond";
	$r = sql_query($q, "common.inc.741");
	
	if(sql_num_rows($r)){
		
		while($row = sql_fetch_assoc($r)){
			
			$response_arr[] = $row;
			
		}
		
	}
	
	return $response_arr;
	
}function GetBlockRequests($cond=''){
	$where_cond = '';
	$response_arr = array();
	if(!empty($cond)){
		$where_cond .= "$cond";
	}
	
	$q = "select * from concblock_request where 1 $where_cond";
	$r = sql_query($q, "common.inc.741");
	
	if(sql_num_rows($r)){
		
		while($row = sql_fetch_assoc($r)){
			
			$response_arr[] = $row;
			
		}
		
	}
	
	return $response_arr;
	
}
//RM Functions

//CM Functions
function GetCMBookings($cond='', $user_id = ''){
	$where_cond = '';
	$response_arr = '';
	$response_arr = array();
	if(!empty($cond)){
		$where_cond .= "$cond";
	}
	$prop_arr = array();
	$q0 = "select iPropertyID from users_property_assoc where iUserID = '$user_id'";
	$r0 = sql_query($q0, "common.inc.767");
	if(sql_num_rows($r0)){
		while($row0 = sql_fetch_object($r0)){
		$prop_arr[] = $row0->iPropertyID;
		}
	}
	$prop_str = implode(",", $prop_arr);
	$q = "select * from concbooking_invoice i join concbooking_property p on i.iBookingID = p.iBookingID where p.iPropertyID IN ($prop_str) $where_cond group by i.iInvoiceID";
	$r = sql_query($q, "common.inc.741");
	
	if(sql_num_rows($r)){
		
		while($row = sql_fetch_assoc($r)){
			
			$response_arr[] = $row;
			
		}
		
	}
	
	return $response_arr;
	
}
//CM Functions
?>