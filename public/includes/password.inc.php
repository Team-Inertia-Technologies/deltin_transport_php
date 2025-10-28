<?php
require_once("ti-salt.php");

$mode = (isset($_GET['mode']))?$_GET['mode']:'';
if($mode=='GetPass')
{
	$pass = (isset($_GET['pass']))?$_GET['pass']:'';
	$passwd = '';

	if($pass!='')
	{	
		$salt_obj= new SaltIT;
		$passwd= $salt_obj->Encode($pass);
	}

	echo $passwd;
	exit;
}
?>