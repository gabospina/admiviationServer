var classes = [], heliContracts = [], contractsColor = [], changed = {}, nameHoverTimeout;
function updateMySched(date){
	var newd = new Date(date);
	var start = getMonday("date", newd);
	var end = getSunday("date", newd);
	var startStr = getMonday("string", newd);
	var endStr = getSunday("string", newd)
	$.ajax({
		type: "POST",
		data: {start: startStr, end: endStr},
		url: "assets/php/get_user_schedule.php",
		success: function(data){	
			if(data != "false"){
				var res = JSON.parse(data);
				var str = "";
				var tempStr;
				var headerD = new Date()
				for(var i = 0; i < 7; i++){
					var classname = "";
					tempStr = "";
					headerD.setTime(start.getTime()+(i*24*60*60*1000));
					if(data != "[]"){
						if(res["date"] != undefined){
							for(var j = 0; j < res["date"].length; j++){
								temp = new Date(res["date"][j]+"T12:00:00");
								tempDOW = temp.getDay();
								tempDOW--;
								tempDOW = (tempDOW == -1 ? 6 : tempDOW);
								if(tempDOW == i){
									classname = "tint-bg";
									if(res["pos"][j] == "com" && res["otherPil"][j] != ""){
										withStr = res["otherPil"][j];
									}else if(res["pos"][j] == "pil" && res["otherPil"][j] != ""){
										withStr = res["otherPil"][j];
									}else{
										withStr = "Solo";
									}
									tempStr = "<strong>"+res["craft"][j].toUpperCase()+"</strong><br/>"+withStr;
								}
							}
						}
						if(tempStr == "" && res.training_date != undefined){
							for(var j = 0; j < res["training_date"].length; j++){
								temp = new Date(res["training_date"][j]+"T12:00:00");
								tempDOW = temp.getDay();
								tempDOW--;
								tempDOW = (tempDOW == -1 ? 6 : tempDOW);
								if(tempDOW == i){
									classname = "tint-bg";
									if(res["training_type"][j] == "trainee")
										tempStr = "<strong>"+res["isExam"][j]+" for</strong><br/>"+res["training_craft"][j].toUpperCase();
									else
										tempStr = "<strong>"+res["training_type"][j].toUpperCase()+" for</strong><br/>"+res["training_craft"][j].toUpperCase();
								}
							}
						}
					}	
					if(tempStr == ""){
						str = "<strong>OFF</strong>";
					}else{
						str = tempStr;
					}
					$($(".mysched th")[i]).html(returnDOW("full",i)+"<br>"+returnAbrvMonth(headerD.getMonth())+" "+doubleDigit(headerD.getDate()));
					$($("#userSched td")[i]).html(str);
					$($("#userSched td")[i]).attr("class", classname);
				}
			}
				
			
		}
	})
}
function updateFullSchedule(){
	date = $("#sched_week").datepicker("getDate").getTime();
	var d = new Date(date);
	var start = getMonday("date", d),
		startStr = getMonday("string",d);
		end = getSunday("date", d);
		endStr = getSunday("string", d);
	var timestamp = d.getTime();
	var isPast = moment(getMonday("date").getTime()-86400000).subtract(1, "w").toDate().getTime() > start.getTime();

	var contract = $("#contract-select").val();
	var craft = $("#craft-select").val();
	$.ajax({
		type: "POST",
		data: {contract: contract, craft: craft, start: startStr, end: endStr},
		url: "assets/php/get_full_schedule.php",
		success: function(data){
			if(data != "false"){
				$(".fullsched tbody").html("");
				var res = JSON.parse(data);
				var comStr, pilStr, str="", classStr;
				classes = res["classes"];
				heliContracts = res["heliContracts"];

				var newcald = start

				var dates = [];
				dates.push(new Date(newcald.getTime()));
				for(var t = 0; t < 7; t++){
					if(t > 0){
						newcald.setTime(newcald.getTime()+(86400*1000));
						dates.push(new Date(newcald.getTime()));
					}
						
					$(".fullsched .day"+t+" span.date").text(returnAbrvMonth(dates[t].getMonth())+" "+doubleDigit(dates[t].getDate()));
				}
				for(var i = 0; i < classes.length; i++){
					var rowStr = "<tr data-contract='"+heliContracts[i]+"'><th>"+classes[i].class+(classes[i].alive == 0 ? "<br/>A.O.G": "")+"</th>";

					for(var j = 0; j < 7; j++){
						//itterate through craft classes
						comStr = "";
						pilStr = "";
						if(res["date"] != undefined){
							for(var k = 0; k < res["date"].length; k++){
								//itterate through result and check if date i = res date and if classes[j] = res["craft"][k]
								tempD = new Date(res["date"][k]+"T12:00:00");
								tempDOW = tempD.getDay();
								tempDOW--;
								tempDOW = (tempDOW == -1 ? 6 : tempDOW);
								if(tempDOW == j && classes[i].class == res["craft"][k]){
									if(res["pos"][k] == "com"){	
										if([8,7,6,4,3,1].indexOf(window.ADMIN) != -1 && !isPast){
											data = "data-type='select' data-name=\""+res["craft"][k]+"\" data-pk=\""+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"\" data-pos=\"com\" data-value=\""+res["id"][k]+"\" data-source=\"assets/php/get_valid_pilots.php?type=com&date="+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"&contract="+heliContracts[i]+"&craft="+classes[i].class+"&tod="+classes[i].tod+"\""
											cls = "edit"
										}else{
											cls = ""
											data="";
										}
										comStr = "<div class='com'><a class='"+cls+"' "+data+"><strong>"+res["name"][k]+"</strong></a><br/></div>";
									}else if(res["pos"][k] == "pil"){
										if([8,7,6,4,3,1].indexOf(window.ADMIN) != -1 && !isPast){
											cls = "edit"
											data = "data-type='select' data-name=\""+res["craft"][k]+"\" data-pk=\""+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"\" data-pos=\"pil\" data-value=\""+res["id"][k]+"\" data-source=\"assets/php/get_valid_pilots.php?type=pil&date="+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"&contract="+heliContracts[i]+"&craft="+classes[i].class+"&tod="+classes[i].tod+"\" ";
										}else{
											cls = ""
											data ="";
										}
										pilStr = "<div class='pil'><a class='"+cls+"' "+data+"><strong>"+res["name"][k]+"</strong></a></div>";
									}
								}
							}
						}
						hasCom = false, hasPil = false, tdClass= "dark-bg";;
						if(comStr == ""){
							if([8,7,6,4,3,1].indexOf(window.ADMIN) != -1 && !isPast){
								data = "data-type='select' data-name=\""+classes[i].class+"\" data-pk=\""+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"\" data-pos=\"com\" data-source=\"assets/php/get_valid_pilots.php?type=com&date="+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"&contract="+heliContracts[i]+"&craft="+classes[i].class+"&tod="+classes[i].tod+"\" ";
								cls = "edit"
							}else{
								data="";
								cls = ""
							}
							comStr = "<div class='com'><a class='"+cls+"' "+data+">Comandante</a></br></div>";
						}else{
							hasCom = true;
						}
						if(pilStr == ""){
							if([8,7,6,4,3,1].indexOf(window.ADMIN) != -1 && !isPast){
								cls = "edit"
								data = "data-type='select' data-name=\""+classes[i].class+"\" data-pk=\""+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"\" data-pos=\"pil\" data-source=\"assets/php/get_valid_pilots.php?type=pil&date="+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"&contract="+heliContracts[i]+"&craft="+classes[i].class+"&tod="+classes[i].tod+"\"";
							}else{
								data ="";
								cls = ""
							}
							pilStr = "<div class='pil'><a class='"+cls+"' "+data+">Piloto</a></div>";
						}else{
							hasPil = true;
						}

						if(hasCom && hasPil){
							tdClass = "blue-bg";
						}

						rowStr += "<td class='"+tdClass+"'>"+comStr+pilStr+"</td>";
					}
					rowStr += "</tr>";
					$(".fullsched tbody").append(rowStr);
				}

				// sortRows(".fullsched tbody", ".fullsched tbody tr");
				markColor(".fullsched tbody tr");
				if($(".fullsched tbody").children().length == 0){
					$(".fullsched tbody").append("<tr><td colspan='8'>No Aircrafts available</td><tr>");
				}

				//hover function for highlighting pilot
				$(".com, .pil").mouseover(function(e){
					clearTimeout(nameHoverTimeout);
					if($(this).text() != "Comandante" && $(this).text() != "Piloto"){
						var name = $(this).text(),
							nameCount = 0,
							xpos = e.pageX+25,
							ypos = e.pageY+10;
						nameHoverTimeout = setTimeout(function(){
							$(".com, .pil").each(function(){
								if($(this).text() == name){
									$(this).addClass("highlight-red");
									nameCount++;
								}
							})
							$("#nameHoverCount").text(nameCount).css({left: xpos, top: ypos}).show();
						}, 600);
					}				
				}).mouseout(function(){
					$(".highlight-red").removeClass("highlight-red");
					$("#nameHoverCount").hide();
				})

				$(".edit").editable("destroy");
				if(!isPast){
					$(".edit").editable({
						url: "assets/php/update_schedule.php",
						ajaxOptions: {
							type: "POST",
							cache: false
						},
						params: function(params){
							params.pos = $(this).attr("data-pos");
							return params;
						},
						sourceCache: false,
						sourceOptions: {
							type: "GET",
							data: {strict: $("#strict-search").val()}
						},
						escape: false,
						success: function(result, newValue){
							if(result.substr(0,5) != "false"){
								var res = JSON.parse(result);
								if(res["name"] == "Comandante" || res["name"] == "Piloto"){
									$(this).html(res["name"])
									if(changed[key] == undefined){
										changed[key] = {com: 0, pil: 0};
									}
									changed[key][$(this).data("pos")] = 0;
								}else{
									var key = $(this).data("pk")+" "+$(this).data("name");
									if(changed[key] == undefined){
										changed[key] = {com: "", pil: ""};
									}
									changed[key][$(this).data("pos")] = newValue;
									$(this).html("<strong>"+res["name"]+"</strong>")
								}

								if($(this).parent().parent().find(".com").find(".edit").text() == "Comandante" || $(this).parent().parent().find(".pil").find(".edit").text() == "Piloto"){
									$(this).parent().parent().removeClass("blue-bg");
									$(this).parent().parent().addClass("dark-bg");
								}else{
									$(this).parent().parent().removeClass("dark-bg");
									$(this).parent().parent().addClass("blue-bg");
								}
								updateMySched($("#sched_week").datepicker("getDate").getTime());
							}else{
								showNotification("error", "An error occured");
							}
							//update user sched if need be
						},
						display: false
					}).on("shown", function(){
						sortScheduleList();
					});
				}
			}
		}
	});
}
$(document).ready(function(){	
	$("#confpass").keydown(function(e){
		if(e.which == 13){
			$("#changePassBtn").trigger("click");
		}
	});
	$(".sidebar-list a[href='home.php'] li").addClass("active");
	$("body").addClass("body-bg");
	$("body").append("<div id='nameHoverCount'></div>");
	$.ajax({
		type: "POST",
		url: "assets/php/checkAdmin.php",
		success: function(data){
			data = parseInt(data);
			window.ADMIN = data;
			if([8,7,6,4,3,1].indexOf(window.ADMIN) != -1){
				$("#printFullSched").click(function(){
					$(".pil>a:contains('Piloto'), .com>a:contains('Comandante')").hide();
					window.print();
					$(".pil>a:contains('Piloto'), .com>a:contains('Comandante')").show();
				});
				
				$("#clearWeekBtn").click(function(){
					var start = $(".fullsched tbody tr").first().children("td").first().find(".editable").data("pk");
					var end = $(".fullsched tbody tr").first().children("td").last().find(".editable").data("pk");
					var craftList = "(";
					$(".fullsched tbody tr").each(function(){
						craftList += "'"+$(this).find("th").text()+"',";
					});

					if(craftList != "("){
						craftList = craftList.substring(0, craftList.length-1)+")";
					}else{
						craftList = "()";
					}
						
					$.ajax({
						url: "assets/php/clear_week.php",
						type: "POST",
						data: {start: start, end: end, crafts: craftList},
						success: function(result){
							showNotification("success", "You successfully cleared the week's schedule");
							updateFullSchedule();
						}
					})
				})

				$("#sendUpdateSMS").click(function(){
					if(Object.keys(changed).length != 0){
						var pilots = {};
						$.each(changed, function(key, val){
							if(pilots[val.pil] == undefined){
								pilots[val.pil] = []; 
							}
							if(pilots[val.com] == undefined){
								pilots[val.com] = [];
							}
							pilots[val.pil].push(moment(key.split(" ")[0]).format("MMM-DD"));
							pilots[val.com].push(moment(key.split(" ")[0]).format("MMM-DD"));
						});
						changed = {};
						$.ajax({
							type: "POST",
							url: "assets/php/send_update_sms.php",
							data: {pilots: JSON.stringify(pilots)},
							success: function(result){
								if(result == ""){
									showNotification("success", "Notifications have been sent");
								}else{
									showNotification("info", "Not all of the notifications were sent successfully.");
								}
							}
						})
					}else{
						showNotification("info", "There have been no changes to the schedule.")
					}
				})
				//remove mysched, personal info, on off dates
				//$("#personalSection, #onOffHeader, #myScheduleSection").remove();
			}

			$("#submitThoughts").click(function(){
				var email = $("#thoughtsEmail").val(),
					name = $("#thoughtsName").val(),
					msg = $("#thoughtsMessage").val();
				if(msg != "" && msg != null){
					//submit & email us
					var that = this;
					$.ajax({
						type: "POST",
						url: "assets/php/submit_thoughts.php",
						data: {msg: msg, name: name, email: email},
						success: function(result){
							if(result == "success"){
								$("#thoughtsMessage").val("");
								showNotification("success", "Thank you for your message.");
							}else{
								showNotification("error", result);
							}
						}
					})
				}else{
					showNotification("error", "Please fill in the text area with your message");
				}
			});
			//week drop down
			$("#sched_week").val(getMonday("user-string")).datepicker({
				daysOfWeekDisabled: [2,3,4,5,6,0],
				autoclose: true,
				weekStart: 1,
				format: "yyyy-MM-dd"
			}).on("changeDate", function(e){
				updateMySched(e.date.getTime());
				updateFullSchedule();
			})


			if([8,7,6,4,3,1].indexOf(window.ADMIN) != -1){
				$("#strict-search").change(function(){
					$(".edit").editable("destroy");
					$(".edit").editable({
						url: "assets/php/update_schedule.php",
						ajaxOptions: {
							type: "POST",
							cache: false
						},
						params: function(params){
							params.pos = $(this).attr("data-pos");
							return params;
						},
						sourceCache: false,
						sourceOptions: {
							type: "GET",
							data: {strict: $("#strict-search").val()}
						},
						escape: false,
						success: function(result){
							if(result.substr(0,5) != "false"){
								var res = JSON.parse(result);
								if(res["name"] == "Comandante" || res["name"] == "Piloto"){
									$(this).html(res["name"])
								}else{
									$(this).html("<strong>"+res["name"]+"</strong>")
								}
								if($(this).parent().parent().find(".com").find(".edit").text() == "Comandante" || $(this).parent().parent().find(".com").find(".edit").text() == "Piloto"){
									$(this).parent().parent().removeClass("blue-bg");
									$(this).parent().parent().addClass("dark-bg");
								}else{
									$(this).parent().parent().removeClass("dark-bg");
									$(this).parent().parent().addClass("blue-bg");
								}
								updateMySched(parseInt($("#sched_week").val()));
							}else{
								showNotification("error", "An error occured. Please try again");
							}
							//update user sched if need be
						},
						display: false
					}).on("shown", function(){
						sortScheduleList
					});
				});
			}
				

			//get pilot info
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
			if([0,1,2,3,8].indexOf(window.ADMIN) != -1){
				//get On Off
				$.ajax({
					type: "POST",
					url: "assets/php/get_user_onoff.php",
					success: function(data){	
						if(data != "false"){
							var res = JSON.parse(data);
							if(res["on"] != undefined && res["on"].length != 0){
								tempOnD = new Date();
								var curTime = "12:00:00";
								var sStart = new Date(res["on"][0]+"T"+curTime);
								var sEnd = new Date(res["off"][0]+"T"+curTime);
								$("#onOffHeaderText").text(returnDate(sStart.getMonth())+" "+sStart.getDate()+" "+sStart.getFullYear()+" - "+returnDate(sEnd.getMonth())+" "+sEnd.getDate()+" "+sEnd.getFullYear());										
								var str = "";
								for(var i = 0; i < res["on"].length; i++){
									var start = new Date(res["on"][i]+"T"+curTime);
									var end = new Date(res["off"][i]+"T"+curTime);
									str+="<li>"+returnDate(start.getMonth())+" "+start.getDate()+" "+start.getFullYear()+" - "+returnDate(end.getMonth())+" "+end.getDate()+" "+end.getFullYear()+"</li>";
								}
								$("ul#userOnOff").html(str);
							}else{
								$("#onOffHeaderText").text("No Dates Listed");
								$("ul#userOnOff").html("<li>You have no listed on off dates.</li>")
							}
						}
					}
				})
			}
				
			$.ajax({
				url: "assets/php/get_contracts.php",
				type: "POST",
				success: function(data){
					//make select dropdown with different contracts
					if(data != "false"){
						var res = JSON.parse(data);
						if(res["name"] != undefined){
							for(var i = 0; i < res["name"].length; i++){
								contractsColor[res["name"][i]] = res["color"][i];
								$("#contract-select, #pilotContracts select").append("<option value='"+res["name"][i]+"'>"+res["name"][i]+"</option>");
							}
						}	
					}
					$("#contract-select").change(function(){
						updateFullSchedule();
					});
					//add checking for contract selected and which craft is selected
					$.ajax({
						url: "assets/php/get_aircrafts.php",
						type: "POST",
						success: function(data){
							console.log(data);
							if(data != "false"){
								var craftResult = JSON.parse(data);
								classes = craftResult["classes"];
								heliContracts = craftResult["contract"];
								var crafts = craftResult["crafts"];
								for(var i = 0; i<crafts.length; i++){
									$("#craft-select").append("<option value='"+crafts[i]+"'>"+crafts[i]+"</option>");
								}
								$("#craft-select").change(function(){
									updateFullSchedule();
									document.cookie = "lastHeliSelected="+$(this).val()+"; expires="+new Date(new Date().getTime() + (86400 * 1000 * 1000)).toString();
									$.ajax({
										url: "assets/php/change_default_craft.php",
										data: {craft: $(this).val()},
										type: "POST",
										success: function(result){

										}
									});
								});
								//get current week schedule
								var start = getMonday("date");
									startStr = getMonday("string");
									end = getSunday("date");
									endStr = getSunday("string");

								// $.ajax({
								// 	type: "POST",
								// 	data: {start: startStr, end: endStr},
								// 	url: "assets/php/get_full_schedule.php",
								// 	success: function(data){
								// 		if(data != "false"){
											// var res = JSON.parse(data);
											// var comStr, pilStr, str="", classStr;

											// //get week start date
											// var cald = getMonday("date");
											// var dates = [];
											// dates.push(new Date(cald.getTime()));
											// for(var t = 0; t < 7; t++){
											// 	if(t > 0){
											// 		cald.setTime(cald.getTime()+(86400*1000));
											// 		dates.push(new Date(cald.getTime()));
											// 	}
											// 	$(".fullsched .day"+t+" span.date").text(returnAbrvMonth(dates[t].getMonth())+" "+doubleDigit(dates[t].getDate()));
											// }

											// for(var i = 0; i < classes.length; i++){
											// 	var rowStr = "<tr data-contract='"+heliContracts[i]+"'><th>"+classes[i]+"</th>";

											// 	for(var j = 0; j < 7; j++){
											// 		//itterate through craft classes
											// 		comStr = "";
											// 		pilStr = "";
											// 		if(res["date"] != undefined){
											// 			for(var k = 0; k < res["date"].length; k++){
											// 				//itterate through result and check if date i = res date and if classes[j] = res["craft"][k]
											// 				tempD = new Date(res["date"][k]+"T12:00:00");
											// 				if(tempD.getDay() == j && classes[i] == res["craft"][k]){
											// 					if(res["pos"][k] == "com"){	
											// 						if(window.ADMIN){
											// 							data = "data-type='select' data-name=\""+res["craft"][k]+"\" data-pk=\""+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"\" data-pos=\"com\" data-value=\"com"+res["id"][k]+"\" data-source=\"assets/php/get_valid_pilots.php?type=com&date="+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"&contract="+heliContracts[i]+"&craft="+classes[i]+"\""
											// 							cls = "edit"
											// 						}else{
											// 							cls = ""
											// 							data="";
											// 						}
											// 						var tdClass = "blue-bg";
											// 						comStr = "<div class='com'><a class='"+cls+"' "+data+"><strong>"+res["name"][k]+"</strong></a><br/></div>";
											// 					}else if(res["pos"][k] == "pil"){
											// 						if(window.ADMIN){
											// 							cls = "edit"
											// 							data = "data-type='select' data-name=\""+res["craft"][k]+"\" data-pk=\""+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"\" data-pos=\"pil\" data-value=\"pil"+res["id"][k]+"\" data-source=\"assets/php/get_valid_pilots.php?type=pil&date="+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"&contract="+heliContracts[i]+"&craft="+classes[i]+"\" ";
											// 						}else{
											// 							cls = ""
											// 							data ="";
											// 						}
											// 						pilStr = "<div class='pil'><a class='"+cls+"' "+data+"><strong>"+res["name"][k]+"</strong></a></div>";
											// 					}
											// 				}
											// 			}
											// 		}
											// 		if(comStr == ""){
											// 			if(window.ADMIN){
											// 				data = "data-type='select' data-name=\""+classes[i]+"\" data-pk=\""+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"\" data-pos=\"com\" data-source=\"assets/php/get_valid_pilots.php?type=com&date="+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"&contract="+heliContracts[i]+"&craft="+classes[i]+"\" ";
											// 				cls = "edit"
											// 			}else{
											// 				data="";
											// 				cls = ""
											// 			}
											// 			tdClass= "dark-bg";
											// 			comStr = "<div class='com'><a class='"+cls+"' "+data+">Comandante</a></br></div>";
											// 		}
											// 		if(pilStr == ""){
											// 			if(window.ADMIN){
											// 				cls = "edit"
											// 				data = "data-type='select' data-name=\""+classes[i]+"\" data-pk=\""+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"\" data-pos=\"pil\" data-source=\"assets/php/get_valid_pilots.php?type=pil&date="+dates[j].getFullYear()+"-"+(dates[j].getMonth()+1)+"-"+dates[j].getDate()+"&contract="+heliContracts[i]+"&craft="+classes[i]+"\"";
											// 			}else{
											// 				data ="";
											// 				cls = ""
											// 			}
											// 			pilStr = "<div class='pil'><a class='"+cls+"' "+data+">Piloto</a></div>";
											// 		}
											// 		rowStr += "<td class='"+tdClass+"'>"+comStr+pilStr+"</td>";
											// 	}
											// 	rowStr += "</tr>";
											// 	$(".fullsched tbody").append(rowStr);
											// }
											// sortRows(".fullsched tbody", ".fullsched tbody tr");
											// markColor(".fullsched tbody tr");
											// if($(".fullsched tbody").children().length == 0){
											// 	$(".fullsched tbody").append("<tr><td colspan='8'>No Aircrafts available</td><tr>");
											// }

											if(lastHeliSelected != undefined && lastHeliSelected != ""){
												$("#craft-select option[value='"+lastHeliSelected+"']").prop("selected", true);
											}
											$("#craft-select").trigger("change");

											// $(".edit").editable({
											// 	url: "assets/php/update_schedule.php",
											// 	ajaxOptions: {
											// 		type: "POST",
											// 		cache: false
											// 	},
											// 	params: function(params){
											// 		params.pos = $(this).attr("data-pos");
											// 		console.log(params)
											// 		return params;
											// 	},
											// 	sourceCache: false,
											// 	sourceOptions: {
											// 		type: "GET",
											// 		data: {strict: $("#strict-search").val()}
											// 	},
											// 	escape: false,
											// 	success: function(result){
											// 		console.log(result)
											// 		if(result.substr(0,5) != "false"){
											// 			var res = JSON.parse(result);
											// 			console.log(res["name"] == "Comandante");
											// 			if(res["name"] == "Comandante" || res["name"] == "Piloto"){
											// 				$(this).html(res["name"])
											// 			}else{
											// 				$(this).html("<strong>"+res["name"]+"</strong>")
											// 			}
											// 			if($(this).parent().parent().find(".com").find(".edit").text() == "Comandante"){
											// 				$(this).parent().parent().removeClass("blue-bg");
											// 				$(this).parent().parent().addClass("dark-bg");
											// 			}else{
											// 				$(this).parent().parent().removeClass("dark-bg");
											// 				$(this).parent().parent().addClass("blue-bg");
											// 			}
											// 			updateMySched(parseInt($("#sched_week").val()));
											// 		}else{
											// 			alert("An error occured, please try again")
											// 		}
											// 		//update user sched if need be
											// 	},
											// 	display: false
											// }).on("shown", function(){
											// 	sortScheduleList
											// });
								// 		}
								// 	}
								// });
								if([0,1,2,3,8].indexOf(window.ADMIN) != -1){
									$.ajax({
										type: "POST",
										data: {start: startStr, end: endStr},
										url: "assets/php/get_user_schedule.php",
										success: function(data){	
											console.log(data);
											if(data != "false" && data != ""){
												var res = JSON.parse(data);
												var str = "";
												var tempStr;
												var tempD = start;
												for(var i = 0; i < 7; i++){
													tempStr = "";
													if(data != "[]"){
														if(res.date != undefined){
															for(var j = 0; j < res["date"].length; j++){
																temp = new Date(res["date"][j]+"T12:00:00");
																tempDOW = temp.getDay();
																tempDOW--;
																tempDOW = (tempDOW == -1 ? 6 : tempDOW);
																if(tempDOW == i){
																	if(res["pos"][j] == "com" && res["otherPil"][j] != ""){
																		withStr = res["otherPil"][j];
																	}else if(res["pos"][j] == "pil" && res["otherPil"][j] != ""){
																		withStr = res["otherPil"][j];
																	}else{
																		withStr = "Solo";
																	}
																	tempStr = "<td class=\"tint-bg\"><strong>"+res["craft"][j].toUpperCase()+"</strong><br/>"+withStr+"</td>";
																}
															}
														}
														if(tempStr == "" && res.training_date != undefined){
															for(var j = 0; j < res["training_date"].length; j++){
																temp = new Date(res["training_date"][j]+"T12:00:00");
																tempDOW = temp.getDay();
																tempDOW--;
																tempDOW = (tempDOW == -1 ? 6 : tempDOW);
																if(tempDOW == i){
																	if(res["training_type"][j] == "trainee")
																		tempStr = "<td class=\"tint-bg\"><strong>"+res["isExam"][j]+" for</strong><br/>"+res["training_craft"][j].toUpperCase()+"</td>";
																	else
																		tempStr = "<td class=\"tint-bg\"><strong>"+res["training_type"][j].toUpperCase()+" for</strong><br/>"+res["training_craft"][j].toUpperCase()+"</td>";
																}
															}
														}
													}	
													if(tempStr == ""){
														str += "<td><strong>OFF</strong></td>";
													}else{
														str+= tempStr;
													}
													if(i > 0){
														tempD.setTime(tempD.getTime()+(86400*1000));
													}
													$(".mysched thead #"+i).append("<br/>"+returnAbrvMonth(tempD.getMonth())+" "+doubleDigit(tempD.getDate()));
												}

												$("#userSched").html(str);
											}
										}
									});
								}
							}
						}
					});
				}
			});
		}
	});
});

function sortRows(parent, selector){
	// var c = $(selector);
	// c.sort(function(a,b){
	// 	if($(a).data("contract") > $(b).data("contract")){
	// 		return 1;
	// 	}else if($(a).data("contract") < $(b).data("contract")){
	// 		return -1;
	// 	}
	// 	return 0;
	// });
	// $(parent).empty();
	// $(parent).html(c);
}

function getColor(colors, min, max){
	return colors[(Math.floor(Math.random()*(max-min))+min)]
}