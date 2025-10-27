<?php
	include_once "db_connect.php";

	$start = $_POST["start"];
	// $end = $_POST["end"];
	// if(isset($_POST["tri"])){
	// 	$trainingType = "tri";
	// 	$trainer = $_POST["tri"];
	// }else if(isset($_POST["tre"])){
	// 	$trainingType = "tre";
	// 	$trainer = $_POST["tre"];
	// }
	$tri1 = $_POST["tri1"];
	$tri2 = $_POST["tri2"];
	$tre = $_POST["tre"];
	$length = $_POST["length"];
	$craft = $_POST["craft"];
	$pilots = json_decode($_POST["ids"], true);
	$pilot1 = $pilots[0];
	$pilot2 = $pilots[1];
	$pilot3 = $pilots[2];
	$pilot4 = $pilots[3];
	$query = "INSERT INTO training_schedule VALUES(null, '$start', $length, '$craft', $tri1, $tri2, $tre, $pilot1, $pilot2, $pilot3, $pilot4)";
	if($mysqli->query($query)){
		print("success");
	}else{
		print($mysqli->error);
	}
	// $startTime = strtotime($start);
	// $endTime = strtotime($end);

	// $days = ($endTime-$startTime)/86400;
	// $isSingleDay = intVal($_POST["isSingleDay"]);
	
	// for($i = 0; $i < count($pilots); $i++){
	// 	$id = $pilots[$i];

	// 	if($isSingleDay == 1){
	// 		$query = "INSERT INTO training_schedule VALUES(null, '$start', '$craft', '$trainingType', $trainer, $id)";
	// 		if($mysqli->query($query)){
	// 			print("success");
	// 		}else{
	// 			print($mysqli->error);
	// 		}
	// 	}else{
	// 		for($j = 0; $j <= $days; $j++){
	// 			$day = date("Y-m-d", strtotime($start." + $j days"));
	// 			$query = "INSERT INTO training_schedule VALUES(null, '$day', '$craft', '$trainingType', $trainer, $id)";
	// 			if($mysqli->query($query)){
	// 				print("success");
	// 			}else{
	// 				print($mysqli->error);
	// 			}
	// 		}
	// 	}
	// }
?>