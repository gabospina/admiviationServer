$(document).ready(function(){
	$(".sidebar-list a[href='account.php'] li").addClass("active");
	// $.ajax({
	// 	type: "POST",
	// 	url: "assets/php/get_pilot_info.php",
	// 	success: function(response){
	// 		if(response != "false" && response != "" && response != null){
	// 			var res = JSON.parse(response);
	// 			$("#username").text(res["fname"]+" "+res["lname"]);
	// 		}
	// 	}
	// });
	$.ajax({
		type: "GET",
		url: "assets/php/get_account_information.php",
		success: function(result){
			console.log(result);
			if(result.charAt(0) == "{" || result.charAt(0) == "["){
				var res = JSON.parse(result);
				$("#account-name").text(res.name);
				$("#account-nationality").text(res.nationality);
				$("#logbook-name").text(res.logbook);
				$("#maxInDay").text(res.max_in_day);
				$("#maxSeven").text(res.max_last_7);
				$("#maxTwentyEight").text(res.max_last_28);
				$("#max365").text(res.max_last_365);
				$("#maxShifts").text(res.max_days_in_row);
				$("#maxDutyInDay").text(res.max_duty_in_day);
				$("#maxDutySeven").text(res.max_duty_7);
				$("#maxDutyTwentyEight").text(res.max_duty_28);
				$("#maxDuty365").text(res.max_duty_365);

				$(".edit-account").editable({
					type: "text",
					url: "assets/php/update_account.php",
					pk: "holder",
					ajaxOptions:{
						type: "POST",
						cache: false
					},
					success: function(response, newValue){
						console.log(response);
					}
				})
			}
		}
	})
});