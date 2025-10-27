<?php
// trainers_get.php
	include_once "db_connect.php";

	$start = $_GET["start"];
	$end = date("Y-m-d", strtotime($start." + 7 days"));
	$backSeven = date("Y-m-d", strtotime($start." - 7 days"));

	$query = "SELECT DISTINCT p.id, p.lname, p.fname FROM pilot_info p INNER JOIN available a ON a.id=p.id 
	WHERE p.id NOT IN (SELECT pilot_id FROM training_schedule WHERE `date`>='$backSeven' AND `date` <='$end') AND p.id NOT IN (SELECT id FROM available WHERE `on` <= '$end' AND `off`>='$start') ORDER BY p.lname";
	$result = $mysqli->query($query);
	$res = array();
	if($result != false){
		while($row = $result->fetch_assoc()){
			array_push($res, array("id"=>$row["id"], "name"=>$row["lname"].", ".$row["fname"]));
		}
	}
	print(json_encode($res));
?>