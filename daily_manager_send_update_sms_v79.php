<?php
	include_once "db_connect.php";
	include_once "twilio-php/Services/Twilio.php";
	$pilots = json_decode($_POST["pilots"], true);

	foreach($pilots as $id => $dates){
		if($id != "" && $id != 0){
			$query = "SELECT fname, lname, phone FROM pilot_info WHERE id=$id";
			$result = $mysqli->query($query);
			if($result != false){
				$row = $result->fetch_assoc();
				if($row["phone"] != null && $row["phone"] != ""){
					$number = str_replace('+', "", str_replace("-", "", str_replace(" ", "", str_replace("(", "", str_replace(")", "", $row["phone"])))));

					$name = $row["fname"]." ".$row["lname"];
					
					$message = "Hello $name, your schedule has been updated for ";
					for($i = 0; $i < count($dates); $i++){
						$message.=" ".$dates[$i].", ";
					}
					if($message != "Your schedule has been updated for "){
						$message = substr($message, 0, strlen($message)-2);
					}

					$data = array("From"=>"+14387937518", "To"=>"+".$number, "Body"=>$message);

					$AccountSid = "AC92d096297651c750e1f813e9feb8a74c";
					$AuthToken = "9280c4c55f592e6cba55faca54d6a8f2";
					 
					$client = new Services_Twilio($AccountSid, $AuthToken);
					
					try{
						$message = $client->account->messages->create($data);
					}catch(Services_Twilio_RestException $e){
						print($e->getMessage());
					}						
				}
			}
		}
	}
?>