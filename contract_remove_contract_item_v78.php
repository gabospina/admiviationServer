<?php
	include_once "db_connect.php";
	// include_once "check_login.php";

	$contract = $_POST["contract"];
	$craft = $_POST["craft"];

	$sql = "DELETE FROM contracts WHERE contract_id='$contract' AND craftid=$craft";
	$delete = $mysqli->query($sql);
	if($delete){
		print("success");
	}else{
		print("failed");
	}
?>