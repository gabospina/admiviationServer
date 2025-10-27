<?php
	include_once "db_connect.php";
	
	$ids = json_decode($_POST["messages"], true);
	for($i = 0; $i < count($ids); $i++){
		$id = $ids[$i];
		$delete = "DELETE FROM sms_messages WHERE sms_id='$id'";
		$mysqli->query($delete);
	}

?>