<?php
$NO_REDIRECT = 1;
include '../includes/common.php';

if(isset($_POST['id'])){

    $table = $_POST['table'];
    $id = $_POST['id'];
    $pk = $_POST['pk'];
    $status = $_POST['status'];

    $q = "update ".$table." set cStatus = '$status' where ".$pk."= $id";
    $r = sql_query($q, "_change_status.11");

    if($r){

        echo $status;

    }


}