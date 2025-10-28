<?php
function ExecQuery($q, $err_code='ERR')
{
	global $IS_LOG_SQL, $CON;
	
	$err_str = 'Error Code: '.$err_code;
	if(SQL_ERROR)
		$err_str .= ' query: '.$q;
	
	$r = mysqli_query($CON,$q) or die($err_str.mysqli_error($CON));
	return $r;
}

function sql_query($q, $err_code='ERR')
{
	global $IS_LOG_SQL, $CON;
	
	$err_str = '<b>Error Code: </b>'.$err_code.'<br>';
	if(SQL_ERROR)
		$err_str .= '<br>query: '.$q.' <br>error: ';

	if(isset($_GET['show_sql']) || isset($_POST['show_sql']))
		echo $q.'<br />';

	if(!empty($IS_LOG_SQL))
		UpdateLog('sql.txt', NOW.','.$_SERVER['PHP_SELF'].',"'.NewlinesToBR($q).'"'.NEWLINE);

	//echo $q.'<br><hr>';
	//echo $CON.'<br><hr>';
	$r = mysqli_query($CON,$q) or die($err_str.mysqli_error($CON));

	if(!empty($IS_LOG_SQL))
		UpdateLog('sql.txt', TAB_SPACE.'affected row(s): '.mysql_affected_rows().', error(s): '.TAB_SPACE.mysqli_error($CON).NEWLINE);
	else
	{
		$q = strtolower(trim($q));
		if(strpos($q, 'update vendor')===0)
			UpdateLog('sql.txt', '**'.NOW.','.$_SERVER['PHP_SELF'].',"'.NewlinesToBR($q).'"'.NEWLINE);
			
	}

	return $r;
}

function sql_num_rows($r)
{
	return mysqli_num_rows($r);
}

function sql_fetch_row($r)
{
	return mysqli_fetch_row($r);
}

function sql_fetch_object($r)
{
	$o = mysqli_fetch_object($r);
	
//	foreach($o as $fld=>$val)
//		$a->{$fld} = db_output($val);
	
	return $o;
}

function sql_fetch_array($r)
{
	return mysqli_fetch_array($r);
}

function sql_fetch_assoc($r)
{
	return mysqli_fetch_assoc($r);
}

function sql_affected_rows()
{
	global $CON;
	return mysqli_affected_rows($CON);
}

function sql_insert_id()
{
	global $CON;
	return mysqli_insert_id($CON);
}

function sql_get_data($r)
{
	$data = array();
	
	while($o = sql_fetch_object($r))
		$data[] = $o;
	
	return $data;
}

function data_get_field_arr($data_arr, $key, $is_unique=true)
{
	$x = array();
	
	foreach($data_arr as $o)
	{
		if(isset($o->$key))
		{
			if($is_unique)
				$x[$o->$key] = $o->$key;
			else
				$x[] = $o->$key;
		}
	}
	
	return $x;
}

function sql_real_escape($column)
{
	global $IS_LOG_SQL, $CON;

	return mysqli_real_escape_string($CON, $column);
}

function sql_close()
{
	global $IS_LOG_SQL, $CON;
	mysqli_close($CON);
}

register_shutdown_function('sql_close');
?>