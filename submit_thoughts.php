<?php

	include_once "mailer/PHPMailerAutoload.php";

	$msg = $_POST["msg"];
	$name = ($_POST["name"] == "" ? "None Provided" : $_POST["name"]);
	$email = ($_POST["email"] == "" ? false : $_POST["email"]);

	$mail = new PHPMailer;

	$mail->isSMTP();                                      // Set mailer to use SMTP
	$mail->Host = 'p3plcpnl0945.prod.phx3.secureserver.net';  				  // Specify main and backup SMTP servers
	$mail->SMTPAuth = true;                               // Enable SMTP authentication
	$mail->Username = 'suggestions@helicopters-offshore.com';  // SMTP username
	$mail->Password = 'F[eVSycXMh98';                     // SMTP password
	$mail->SMTPSecure = 'ssl';                            // Enable TLS encryption, `ssl` also accepted
	$mail->Port = 465;                                    // TCP port to connect to

	if($email != false){
		$mail->From = $email;
		$mail->FromName = $name;
	}else{
		$mail->From = 'suggestions@helicopters-offshore.com';
		$mail->FromName = $name;
	}

	if(isset($_POST["landing"])){
		$ourEmail = "info@helicopters-offshore.com";
		$subject = "Helicopters Offshore Information Request";
	}else{
		$ourEmail = "suggestions@helicopters-offshore.com";
		$subject = "Helicopters Offshore Suggestion.";
	}
	$mail->addAddress($ourEmail);

	$mail->WordWrap = 50;                                 // Set word wrap to 50 characters
	$mail->isHTML(true);                                  // Set email format to HTML
	$mail->Subject = $subject;
	$mail->Body    = $msg;
	$mail->AltBody = $msg;

	if(!$mail->send()) {
	   	$msg = "Sorry, but something went wrong. Please try again later. Error: ".$mail->ErrorInfo;
	} else {
		if($email != false){
			$mail->ClearAddresses();
			$mail->addAddress($email);

			$mail->WordWrap = 50;                                 // Set word wrap to 50 characters
			$mail->isHTML(true);                                  // Set email format to HTML

			$mail->Subject = "Helicopters Offshor Suggestions";
			$mail->Body    = "<h2>Thank you for your input</h2><br/><br/><p>Helicopters Offshore is a growing venture looking match our customers needs. All of your input will be carefully looked over to help us improve our system.</p><br/><p>Regards,<br/><em>The Helicopters Offshore Team</em></p>";
			$mail->AltBody =  "Thank you for your input. Helicopters Offshore is a growing venture looking match our customers needs. All of your input will be carefully looked over to help us improve our system. Regards, The Helicopters Offshore Team";
			$mail->send();
		}
		$msg = "success";
	}

	print($msg);

?>