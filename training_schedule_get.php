<?php
// traing_schedule_get.php
	include_once "db_connect.php";

	$start = $_GET["start"];
	$end = $_GET["end"];
	$account = $_SESSION["account"];
	if($_GET["viewType"] == "trainee"){
		$query = "SELECT t.id, t.date, t.length, t.craft, t.tri1_id, t.tri2_id, t.tre_id, t.pilot_id1, t.pilot_id2, t.pilot_id3, t.pilot_id4 FROM training_schedule t 
				  WHERE t.date >= '$start' AND t.date <='$end' AND ((t.pilot_id1 IS NOT NULL AND t.pilot_id1 IN (SELECT id FROM pilot_info WHERE account=$account)) OR (t.pilot_id2 IS NOT NULL AND t.pilot_id2 IN (SELECT id FROM pilot_info WHERE account=$account)) OR (t.pilot_id3 IS NOT NULL AND t.pilot_id3 IN (SELECT id FROM pilot_info WHERE account=$account)) OR (t.pilot_id4 IS NOT NULL AND t.pilot_id4 IN (SELECT id FROM pilot_info WHERE account=$account)))";
		// error_log()
		$result = $mysqli->query($query);
		$res = array();
		if($result != false){
			$i = 0;
			while($row = $result->fetch_assoc()){
				$res[$i]["id"] = $row["id"];
				$res[$i]["craft"] = $row["craft"];
				
				//TRI One
				$res[$i]["tri1id"] = $row["tri1_id"];
				$res[$i]["tri_one"] = ($row["tri1_id"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[tri1_id]")->fetch_assoc()["name"] : null);
				
				//TRI Two
				$res[$i]["tri2id"] = $row["tri2_id"];
				$res[$i]["tri_two"] = ($row["tri2_id"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[tri2_id]")->fetch_assoc()["name"] : null);
				
				//TRE
				$res[$i]["treid"] = $row["tre_id"];
				$res[$i]["tre"] = ($row["tre_id"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[tre_id]")->fetch_assoc()["name"] : null);
				
				//Pilot One
				$res[$i]["pilotid1"] = $row["pilot_id1"];
				$res[$i]["pilot_name1"] = ($row["pilot_id1"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[pilot_id1]")->fetch_assoc()["name"] : null);
				
				//Pilot Two
				$res[$i]["pilotid2"] = $row["pilot_id2"];
				$res[$i]["pilot_name2"] = ($row["pilot_id2"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[pilot_id2]")->fetch_assoc()["name"] : null);
				
				//Pilot Three
				$res[$i]["pilotid3"] = $row["pilot_id3"];
				$res[$i]["pilot_name3"] = ($row["pilot_id3"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[pilot_id3]")->fetch_assoc()["name"] : null);

				//Pilot Four
				$res[$i]["pilotid4"] = $row["pilot_id4"];
				$res[$i]["pilot_name4"] = ($row["pilot_id4"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[pilot_id4]")->fetch_assoc()["name"] : null);

				$res[$i]["pilots"] = ($res[$i]["pilot_name1"] != null ? $res[$i]["pilot_name1"]."; " : "").($res[$i]["pilot_name2"] != null ? $res[$i]["pilot_name2"]."; " : "").($res[$i]["pilot_name3"] != null ? $res[$i]["pilot_name3"]."; " : "").($res[$i]["pilot_name4"] != null ? $res[$i]["pilot_name4"]."; " : "");
				$res[$i]["trainers"] = ($res[$i]["tri_one"] != null ? "TRI 1: ".$res[$i]["tri_one"]."; " : "").($res[$i]["tri_two"] != null ? "TRI 2: ".$res[$i]["tri_two"]."; " : "").($res[$i]["tre"] != null ? "TRE: ".$res[$i]["tre"]."; " : "");
				//Start/End
				$res[$i]["start"] = $row["date"];
				$res[$i]["end"] = date("Y-m-d", strtotime($row["date"]." + ".$row["length"]." days"));
				
				$i++;
			}
		}else{
			print($mysqli->error);
		}
		print(json_encode($res));
	}else if($_GET["viewType"] == "trainer"){
		$query = "SELECT t.id, t.date, t.length, t.craft, t.tri1_id, t.tri2_id, t.tre_id, t.pilot_id1, t.pilot_id2, t.pilot_id3, t.pilot_id4 FROM training_schedule t 
				  WHERE t.date >= '$start' AND t.date <='$end' AND ((t.tri1_id IS NOT NULL AND t.tri1_id IN (SELECT id FROM pilot_info WHERE account=$account)) OR (t.tre_id IS NOT NULL AND t.tre_id IN (SELECT id FROM pilot_info WHERE account=$account)) OR (t.tri2_id IS NOT NULL AND t.tri2_id IN (SELECT id FROM pilot_info WHERE account=$account)))";
		// error_log()
		$result = $mysqli->query($query);
		$res = array();
		if($result != false){
			$i = 0;
			while($row = $result->fetch_assoc()){
				$res[$i]["id"] = $row["id"];
				$res[$i]["craft"] = $row["craft"];
				
				//TRI One
				$res[$i]["tri1id"] = $row["tri1_id"];
				$res[$i]["tri_one"] = ($row["tri1_id"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[tri1_id]")->fetch_assoc()["name"] : null);
				
				//TRI Two
				$res[$i]["tri2id"] = $row["tri2_id"];
				$res[$i]["tri_two"] = ($row["tri2_id"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[tri2_id]")->fetch_assoc()["name"] : null);
				
				//TRE
				$res[$i]["treid"] = $row["tre_id"];
				$res[$i]["tre"] = ($row["tre_id"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[tre_id]")->fetch_assoc()["name"] : null);
				
				//Pilot One
				$res[$i]["pilotid1"] = $row["pilot_id1"];
				$res[$i]["pilot_name1"] = ($row["pilot_id1"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[pilot_id1]")->fetch_assoc()["name"] : null);
				
				//Pilot Two
				$res[$i]["pilotid2"] = $row["pilot_id2"];
				$res[$i]["pilot_name2"] = ($row["pilot_id2"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[pilot_id2]")->fetch_assoc()["name"] : null);
				
				//Pilot Three
				$res[$i]["pilotid3"] = $row["pilot_id3"];
				$res[$i]["pilot_name3"] = ($row["pilot_id3"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[pilot_id3]")->fetch_assoc()["name"] : null);

				//Pilot Four
				$res[$i]["pilotid4"] = $row["pilot_id4"];
				$res[$i]["pilot_name4"] = ($row["pilot_id4"] != null ? $mysqli->query("SELECT CONCAT(lname,', ',fname) AS name FROM pilot_info WHERE id=$row[pilot_id4]")->fetch_assoc()["name"] : null);

				$res[$i]["pilots"] = ($res[$i]["pilot_name1"] != null ? $res[$i]["pilot_name1"]."; " : "").($res[$i]["pilot_name2"] != null ? $res[$i]["pilot_name2"]."; " : "").($res[$i]["pilot_name3"] != null ? $res[$i]["pilot_name3"]."; " : "").($res[$i]["pilot_name4"] != null ? $res[$i]["pilot_name4"]."; " : "");
				$res[$i]["trainers"] = ($res[$i]["tri_one"] != null ? "TRI 1: ".$res[$i]["tri_one"]."; " : "").($res[$i]["tri_two"] != null ? "TRI 2: ".$res[$i]["tri_two"]."; " : "").($res[$i]["tre"] != null ? "TRE: ".$res[$i]["tre"]."; " : "");
				
				//Start/End
				$res[$i]["start"] = $row["date"];
				$res[$i]["end"] = date("Y-m-d", strtotime($row["date"]." + ".$row["length"]." days"));
				
				$i++;
			}
		}else{
			print($mysqli->error);
		}
		print(json_encode($res));
	}
?>