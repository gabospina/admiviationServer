function updateMySched(date){
	var newd = new Date(date);
	
	dow = newd.getDay();
	offsetBack = dow*86400*1000;
	var start = new Date(newd.getTime()-offsetBack);
	offsetForward = (6-dow)*86400*1000;
	var end = new Date(newd.getTime()+offsetForward);
	startStr = start.getFullYear()+"-"+(start.getMonth()+1)+"-"+start.getDate();
	endStr = end.getFullYear()+"-"+(end.getMonth()+1)+"-"+end.getDate();

	$.ajax({
		type: "POST",
		data: {start: startStr, end: endStr},
		url: "assets/php/get_user_schedule.php",
		success: function(data){	
			console.log(data);
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
						for(var j = 0; j < res["date"].length; j++){
							temp = new Date(res["date"][j]+" 12:00:00");
							if(temp.getDay() == i){
								classname = "tint-bg";
								if(res["pos"][j] == "com" && res["otherPil"][j] != ""){
									withStr = "With piloto: "+res["otherPil"][j];
								}else if(res["pos"][j] == "pil" && res["otherPil"][j] != ""){
									withStr = "With comandante: "+res["otherPil"][j];
								}else{
									withStr = "Solo";
								}
								tempStr = "<strong>"+res["craft"][j].toUpperCase()+"</strong><br/>"+withStr;
							}
						}
					}	
					if(tempStr == ""){
						str = "<strong>OFF</strong>";
					}else{
						str = tempStr;
					}
					$($(".mysched th")[i]).html(returnDOW("abv",i)+"<br>"+(headerD.getMonth()+1)+"/"+headerD.getDate());
					$($("#userSched td")[i]).html(str);
					$($("#userSched td")[i]).attr("class", classname);
				}
			}
				
			
		}
	})
}
function updateFullSchedule(date){
	var d = new Date(date);
	dow = d.getDay();
	offsetBack = dow*86400*1000;
	var start = new Date(d.getTime()-offsetBack);
	offsetForward = (6-dow)*86400*1000;
	var end = new Date(d.getTime()+offsetForward);
	startStr = start.getFullYear()+"-"+(start.getMonth()+1)+"-"+start.getDate();
	endStr = end.getFullYear()+"-"+(end.getMonth()+1)+"-"+end.getDate();
	var timestamp = date;
	console.log(date);
	$.ajax({
		type: "POST",
		data: {start: startStr, end: endStr},
		url: "assets/php/get_full_schedule.php",
		success: function(data){
			console.log(data);
			if(data != "false"){
				var res = JSON.parse(data);
				var classes = ["evv","eqi","evt","eqh","ewo","eem","evm","eqt","eex","eet","eqxt"];
				var comStr, pilStr, str="", classStr;

				var newSchedD = new Date(timestamp);
				dow = newSchedD.getDay();
				offsetBack = dow*86400*1000;
				var newcald = new Date(newSchedD.getTime()-offsetBack);

				for(var i = 0; i < 7; i++){
					//itterate through days of week; rows of table
					classStr = ""
					//set row date
					if(i > 0){
						newcald.setTime(newcald.getTime()+(86400*1000));
					}
					var thStr = "<th scope=\"row\" rowspan=\"1\">"+returnDOW("abv", i)+" <br/>"+(newcald.getMonth()+1)+"/"+newcald.getDate()+" </th>";
					console.log(newcald);
					for(var j = 0; j < classes.length; j++){
						//itterate through craft classes
						comStr = "";
						pilStr = "";
						if(res["date"] != undefined){
							for(var k = 0; k < res["date"].length; k++){
								//itterate through result and check if date i = res date and if classes[j] = res["craft"][k]
								tempD = new Date(res["date"][k]+" 12:00:00");
								if(tempD.getDay() == i && classes[j] == res["craft"][k]){
									if(res["pos"][k] == "com"){	
										if(window.ADMIN){
											data = "data-type='select' data-name=\""+res["craft"][k]+"\" data-pk=\""+newcald.getFullYear()+"-"+(newcald.getMonth()+1)+"-"+newcald.getDate()+"\" data-pos=\"com\" data-value=\"com"+res["id"][k]+"\" data-source=\"assets/php/get_valid_pilots.php?type=com&date="+newcald.getFullYear()+"-"+(newcald.getMonth()+1)+"-"+newcald.getDate()+"\""
											cls = "edit"
										}else{
											cls = ""
											data="";
										}
										var tdClass = "blue-bg";
										comStr = "<div class='com'><strong>Comandante:</strong><br/><a class='"+cls+"' "+data+"><strong>"+res["name"][k]+"</strong></a><br/></div>";
									}else if(res["pos"][k] == "pil"){
										if(window.ADMIN){
											cls = "edit"
											data = "data-type='select' data-name=\""+res["craft"][k]+"\" data-pk=\""+newcald.getFullYear()+"-"+(newcald.getMonth()+1)+"-"+newcald.getDate()+"\" data-pos=\"pil\" data-value=\"pil"+res["id"][k]+"\" data-source=\"assets/php/get_valid_pilots.php?type=pil&date="+newcald.getFullYear()+"-"+(newcald.getMonth()+1)+"-"+newcald.getDate()+"\" ";
										}else{
											cls = ""
											data ="";
										}
										pilStr = "<div class='pil'><strong>Piloto:</strong><br/><a class='"+cls+"' "+data+"><strong>"+res["name"][k]+"</strong></a></div>";
									}
								}
							}
						}
						if(comStr == ""){
							if(window.ADMIN){
								data = "data-type='select' data-name=\""+classes[j]+"\" data-pk=\""+newcald.getFullYear()+"-"+(newcald.getMonth()+1)+"-"+newcald.getDate()+"\" data-pos=\"com\" data-source=\"assets/php/get_valid_pilots.php?type=com&date="+newcald.getFullYear()+"-"+(newcald.getMonth()+1)+"-"+newcald.getDate()+"\" ";
								cls = "edit"
							}else{
								data="";
								cls = ""
							}
							tdClass= "dark-bg";
							comStr = "<div class='com'><strong>Comandante:</strong><br/><a class='"+cls+"' "+data+">None</a></br></div>";
						}
						if(pilStr == ""){
							if(window.ADMIN){
								cls = "edit"
								data = "data-type='select' data-name=\""+classes[j]+"\" data-pk=\""+newcald.getFullYear()+"-"+(newcald.getMonth()+1)+"-"+newcald.getDate()+"\" data-pos=\"pil\" data-source=\"assets/php/get_valid_pilots.php?type=pil&date="+newcald.getFullYear()+"-"+(newcald.getMonth()+1)+"-"+newcald.getDate()+"\"";
							}else{
								data ="";
								cls = ""
							}
							pilStr = "<div class='pil'><strong>Piloto:</strong><br/><a class='"+cls+"' "+data+">None</a></div>";
						}
						classStr += "<td class='"+tdClass+"'>"+comStr+pilStr+"</td>";
					}

					
					$(".fullsched #day"+i).html(thStr+classStr);
				}

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
						type: "GET"
					},
					escape: false,
					success: function(result){
						console.log(result)
						if(result.substr(0,5) != "false"){
							var res = JSON.parse(result);
							if(res["name"] == "None"){
								$(this).html(res["name"])
							}else{
								$(this).html("<strong>"+res["name"]+"</strong>")
							}
							if($(this).parent().parent().find(".com").find(".edit").text() == "None"){
								$(this).parent().parent().removeClass("blue-bg");
								$(this).parent().parent().addClass("dark-bg");
							}else{
								$(this).parent().parent().removeClass("dark-bg");
								$(this).parent().parent().addClass("blue-bg");
							}
							updateMySched(parseInt($("#sched_week").val()));
						}else{
							alert("An error occured, please try again")
						}
						//update user sched if need be
					},
					display: false
				});
			}
		}
	});
}
$(document).ready(function(){
	$(".copyrightDate").text(new Date().getFullYear());
	
	$.ajax({
		type: "POST",
		url: "assets/php/checkAdmin.php",
		success: function(data){
			if(data == "1"){
				window.ADMIN = data;
				$('nav ul').append("<li><a href=\"pilots.php\">| Pilots</a></li>")
				$("#addPilotHeader").html("<div class=\"page-header\"><h2><span class=\"btn btn-danger\" data-toggle=\"modal\" data-target=\".addPilot\" style=\"margin-right: 15px;\">Add New Pilot</span><small>Add a new pilot to your fleet</small></h2></div>");
				$("#footerMenu").append("<p><a>Pilots</a></p>")
				
				// ADD PILOTS MODAL
				$("#addDates").click(function(e){
					e.stopPropagation();
					$("#onOffDates").append("<div class=\"addedDate\"><div class=\"col-md-6\"><div class=\"lbl\">On</div><input type=\"date\" class=\"datepicker on-date\" /></div><div class=\"col-md-6\"><div class=\"lbl\">Off</div><input type=\"date\" class=\"datepicker off-date\" /></div></div>")
					$("#removeDates").css("display", "inline-block");
				});
				$("#removeDates").click(function(e){
					e.stopPropagation();
					if($(".addedDate").length != 0){
						$(".addedDate").last().remove();
					}
					if($(".addedDate").length == 0){
						$(this).css("display", "none");
					}
				})

				$("#submitPilot").click(function(e){
					e.stopPropagation();
					var fname = $(".addPilot #fname").val();
					var lname = $(".addPilot #lname").val();
					var email = $(".addPilot #email").val();
					var nationality = $(".addPilot #nationality").val();
					var ang_license = $(".addPilot #ang_license").val();
					var position = $(".addPilot #comandante").val();
					var for_license = $(".addPilot #for_license").val();
					if(fname != "" && lname != "" && email != "" && nationality != "" && ang_license != "" && comandante != "" && for_license != ""){
						var str = "";
						$(".addPilot input:not(.on-date, .off-date)").each(function(){
							if($(this).attr("id") == undefined){
								console.log($(this));
							}
							if($(this).val() != ""){
								str+=$(this).attr("id")+": '"+$(this).val()+"', ";
							}else{
								str+=$(this).attr("id")+": null, ";
							}
						});

						str+="comandante: '"+$(".addPilot #comandante").val()+"'";
						
						var inputs = eval("({"+str+"})");

						var onOffArray = [];
						for(var i = 0; i < $(".addPilot .on-date").length; i++){
							var on = $($(".addPilot .on-date")[i]).val();
							var off = $($(".addPilot .off-date")[i]).val();
							if(on != "" && off != ""){
								var temp ="({on: '"+on+"', off: '"+off+"'})";
								onOffArray.push(eval(temp));
							}
						}
						//PASSWORD CREATION
						tp = fname.substring(0,1).toUpperCase()+lname.toLowerCase()+lname.length+""+fname.length
						var tempPass = hex_sha512(tp);

						$(".addPilot").modal("hide");
						$.ajax({
							type: "POST",
							url: "assets/php/create_new_pilot.php",
							data: {inputs: JSON.stringify(inputs), onOff: JSON.stringify(onOffArray), pass: tempPass},
							success: function(r){
								console.log(r);
								if(r == "success"){
									$("#notifications").append("<div class=\"col-md-5 center-block\" style=\"margin-left: auto; margin-right: auto;\">"+
										"<div class=\"alert_sec\"><div class=\"alert alert-success alert-dismissible\" role=\"alert\">"+
		  								"<button type=\"button\" class=\"close\" data-dismiss=\"alert\"><span aria-hidden=\"true\">&times;</span><span class=\"sr-only\">Close</span></button>"+
		  								"<strong>You have successfully added a pilot!</strong></div></div></div>");
								}else{
									$("#notifications").append("<div class=\"col-md-5 center-block\" style=\"margin-left: auto; margin-right: auto;\">"+
										"<div class=\"alert_sec\"><div class=\"alert alert-danger alert-dismissible\" role=\"alert\">"+
		  								"<button type=\"button\" class=\"close\" data-dismiss=\"alert\"><span aria-hidden=\"true\">&times;</span><span class=\"sr-only\">Close</span></button>"+
		  								"<strong>Adding the pilot failed. Please try again.</strong></div></div></div>");
								}
							}
						});
					}else{
						$(".addPilot .modal-body").append("<div class=\"col-md-5 center-block\" style=\"margin-left: auto; margin-right: auto;\">"+
							"<div class=\"alert_sec\"><div class=\"alert alert-danger alert-dismissible\" role=\"alert\">"+
		  					"<button type=\"button\" class=\"close\" data-dismiss=\"alert\"><span aria-hidden=\"true\">&times;</span><span class=\"sr-only\">Close</span></button>"+
		  					"<strong>Please fill in all the required (*) information.</strong></div></div></div>");
					}
				})
			}else{
				window.ADMIN = false;
				$(".addPilot").remove();
			}

			//week drop down
			var twd = new Date();
			offs = twd.getDay()*24*60*60*1000;
			var tempWeekDate = new Date(twd.getTime()-offs);
			for(var wc = 0; wc < 10; wc++){
				var tdc = new Date(tempWeekDate.getTime()+(wc*7*24*60*60*1000));
				$("#sched_week").append("<option value=\""+tdc.getTime()+"\">Sunday "+returnDate(tdc.getMonth())+" "+returnIndicator(tdc.getDate())+"</option>");
			}
			$("#sched_week").change(function(){
				updateMySched(parseInt($(this).val()));
				updateFullSchedule(parseInt($(this).val()));
			})

			//get pilot info
			$.ajax({
				type: "POST",
				url: "assets/php/get_pilot_info.php",
				success: function(response){
					if(response != "false" && response != "" && response != null){
						var res = JSON.parse(response);
						$("#username").text(res["fname"]+" "+res["lname"]);
						$("#personalInfo #name").html("<span class='infoEdit' data-name='fname'>"+res["fname"]+"</span> <div style='margin-left: 0.5em; display: inline;'><span class='infoEdit' data-name='lname'>"+res["lname"]+"</span></div>");
						$("#personalInfo #nationality").html("<span class='infoEdit' data-name='nationality'>"+res["nationality"]+"</span>");
						if(res["comandante"] == 1){
							pos = "comandante";
						}else{
							pos = "piloto";
						}

						$("#personalInfo #pos").html("<span class='infoEdit' data-name='comandante'>"+pos+"<span>");
						$("#personalInfo #angLic").html("<span class='infoEdit' data-name='ang_license'>"+res["ang_license"]+"</span>");
						$("#personalInfo #forLic").html("<span class='infoEdit' data-name='for_license'>"+res["for_license"]+"</span>");
						$("#personalInfo #persEmail").html("<span class='infoEdit' data-name='email'>"+res["email"]+"</span>");
						
						$("#personalInfo li").children().css("display", "inline");
						$(".infoEdit").editable({
							pk: "notneeded",
							url: "assets/php/update_personal_info.php",
							ajaxOptions:{
								type: "POST",
								cache: false,
								success: function(result){
									console.log("firing")
									console.log(result);
								}
							}
						})
					}
				}
			});

			//get On Off
			$.ajax({
				type: "POST",
				url: "assets/php/get_user_onoff.php",
				success: function(data){	
					if(data != "false"){
						var res = JSON.parse(data);
						if(res["on"].length != 0){
							tempOnD = new Date();
							var curTime = tempOnD.getHours()+":"+tempOnD.getMinutes()+":"+tempOnD.getSeconds()
							var sStart = new Date(res["on"][0]+" "+curTime);
							var sEnd = new Date(res["off"][0]+" "+curTime);
							$("#onOffHeader").text(returnDate(sStart.getMonth())+" "+sStart.getDate()+" "+sStart.getFullYear()+" - "+returnDate(sEnd.getMonth())+" "+sEnd.getDate()+" "+sEnd.getFullYear());										
							var str = "";
							for(var i = 0; i < res["on"].length; i++){
								var start = new Date(res["on"][i]+" "+curTime);
								var end = new Date(res["off"][i]+" "+curTime);
								str+="<li>"+returnDate(start.getMonth())+" "+start.getDate()+" "+start.getFullYear()+" - "+returnDate(end.getMonth())+" "+end.getDate()+" "+end.getFullYear()+"</li>";
							}
							$("ul#userOnOff").html(str);
						}
					}
				}
			})


			//get validity check
			$.ajax({
				type: "POST",
				url: "assets/php/get_pilot_validity.php",
				success: function(response){
					if(response != false && response != ""){
						var res = JSON.parse(response);
						var array = [[]];
						var counter = 0;
						var i = 0;
						for(var key in res){
							if(key != "id"){
								array[i].push({header: key, val: res[key]})
								counter++;
								if(counter != 0 && counter%7 == 0){
									array.push([]);
									i++;
								}
							}	
						}
						var str = "";
						var headerStr, bodyStr;
						for(var i = 0; i < array.length; i++){
							headerStr = "<thead>";
							bodyStr = "<tr>";
							for(var j = 0; j < array[i].length; j++){
								headerStr += "<th>"+getTestName(array[i][j].header)+"</th>";
								var cur = new Date();
								var vald = new Date(array[i][j].val+" "+cur.getHours()+":"+cur.getMinutes()+":"+cur.getSeconds());
								var cls = "tint-bg";
								var dateStr = vald.getFullYear()+"-"+(vald.getMonth()+1)+"-"+vald.getDate()
								var content = "<span class='valText'>Valid</span><br/><span class='validityDate' data-type='date' data-name='"+array[i][j].header+"' data-pk='"+dateStr+"'>"+dateStr+"</span>";
								if(vald.getTime() < cur.getTime()){
									cls = "alert alert-danger"
									content = "<span class='valText'>EXPIRED</span><br/><span class='validityDate' data-type='date' data-name='"+array[i][j].header+"' data-pk='"+dateStr+"'>"+dateStr+"</span>";
								}else if(vald.getTime() - cur.getTime() <= (4*7*24*60*60*1000)){
									cls = "alert alert-warning";
									content = "<span class='valText'>Expires Soon</span><br/><span class='validityDate' data-type='date' data-name='"+array[i][j].header+"' data-pk='"+dateStr+"'>"+dateStr+"</span>";
								}
								bodyStr += "<td class='"+cls+"'>"+content+"</td>";
							}
							headerStr += "</thead>";
							bodyStr += "</tr>";
							str += "<table class='val_table'>"+headerStr+bodyStr+"</table>";
						}

						$("#validityHolder").html(str);

						$(".val_table .validityDate").editable({
							url: "assets/php/update_validity.php",
							ajaxOptions: {
								type: "POST",
								cache: false
							},
							success: function(data){
								console.log(data);
								var checkD = new Date(data+" 12:00:00");
								var valCur = new Date();
								$(this).text(data.substring(1, (data.length-1)))
								if(checkD.getTime() < valCur.getTime()){
									$(this).parent().attr("class", "alert alert-danger");
									$(this).siblings(".valText").text("EXPIRED");
								}else if((checkD.getTime()-valCur.getTime()) <= (4*7*24*60*60*1000)){
									$(this).parent().attr("class", "alert alert-warning");
									$(this).siblings(".valText").text("Expires Soon");
								}else{
									$(this).parent().attr("class", "tint-bg");
									$(this).siblings(".valText").text("Valid");
								}
							},
							display: false
						})
					}
				}
			});
			//get current week schedule
			var d = new Date();
			dow = d.getDay();
			offsetBack = dow*86400*1000;
			var start = new Date(d.getTime()-offsetBack);
			offsetForward = (6-dow)*86400*1000;
			var end = new Date(d.getTime()+offsetForward);
			startStr = start.getFullYear()+"-"+(start.getMonth()+1)+"-"+start.getDate();
			endStr = end.getFullYear()+"-"+(end.getMonth()+1)+"-"+end.getDate();

			$.ajax({
				type: "POST",
				data: {start: startStr, end: endStr},
				url: "assets/php/get_full_schedule.php",
				success: function(data){
					if(data != "false"){
						var res = JSON.parse(data);
						var classes = ["evv","eqi","evt","eqh","ewo","eem","evm","eqt","eex","eet","eqxt"];
						var comStr, pilStr, str="", classStr;

						var SchedD = new Date();
						dow = SchedD.getDay();
						offsetBack = dow*86400*1000;
						var cald = new Date(SchedD.getTime()-offsetBack);

						for(var i = 0; i < 7; i++){
							//itterate through days of week; rows of table
							classStr = ""
							//set row date
							if(i > 0){
								cald.setTime(cald.getTime()+(86400*1000));
							}
							$(".fullsched #day"+i+" th").append("<br/>"+(cald.getMonth()+1)+"/"+cald.getDate());
							
							for(var j = 0; j < classes.length; j++){
								//itterate through craft classes
								comStr = "";
								pilStr = "";
								if(res["date"] != undefined){
									for(var k = 0; k < res["date"].length; k++){
										//itterate through result and check if date i = res date and if classes[j] = res["craft"][k]
										tempD = new Date(res["date"][k]+" 12:00:00");
										if(tempD.getDay() == i && classes[j] == res["craft"][k]){
											if(res["pos"][k] == "com"){	
												if(window.ADMIN){
													data = "data-type='select' data-name=\""+res["craft"][k]+"\" data-pk=\""+cald.getFullYear()+"-"+(cald.getMonth()+1)+"-"+cald.getDate()+"\" data-pos=\"com\" data-value=\"com"+res["id"][k]+"\" data-source=\"assets/php/get_valid_pilots.php?type=com&date="+cald.getFullYear()+"-"+(cald.getMonth()+1)+"-"+cald.getDate()+"\""
													cls = "edit"
												}else{
													cls = ""
													data="";
												}
												var tdClass = "blue-bg";
												comStr = "<div class='com'><strong>Comandante:</strong><br/><a class='"+cls+"' "+data+"><strong>"+res["name"][k]+"</strong></a><br/></div>";
											}else if(res["pos"][k] == "pil"){
												if(window.ADMIN){
													cls = "edit"
													data = "data-type='select' data-name=\""+res["craft"][k]+"\" data-pk=\""+cald.getFullYear()+"-"+(cald.getMonth()+1)+"-"+cald.getDate()+"\" data-pos=\"pil\" data-value=\"pil"+res["id"][k]+"\" data-source=\"assets/php/get_valid_pilots.php?type=pil&date="+cald.getFullYear()+"-"+(cald.getMonth()+1)+"-"+cald.getDate()+"\" ";
												}else{
													cls = ""
													data ="";
												}
												pilStr = "<div class='pil'><strong>Piloto:</strong><br/><a class='"+cls+"' "+data+"><strong>"+res["name"][k]+"</strong></a></div>";
											}
										}
									}
								}
								if(comStr == ""){
									if(window.ADMIN){
										data = "data-type='select' data-name=\""+classes[j]+"\" data-pk=\""+cald.getFullYear()+"-"+(cald.getMonth()+1)+"-"+cald.getDate()+"\" data-pos=\"com\" data-source=\"assets/php/get_valid_pilots.php?type=com&date="+cald.getFullYear()+"-"+(cald.getMonth()+1)+"-"+cald.getDate()+"\" ";
										cls = "edit"
									}else{
										data="";
										cls = ""
									}
									tdClass= "dark-bg";
									comStr = "<div class='com'><strong>Comandante:</strong><br/><a class='"+cls+"' "+data+">None</a></br></div>";
								}
								if(pilStr == ""){
									if(window.ADMIN){
										cls = "edit"
										data = "data-type='select' data-name=\""+classes[j]+"\" data-pk=\""+cald.getFullYear()+"-"+(cald.getMonth()+1)+"-"+cald.getDate()+"\" data-pos=\"pil\" data-source=\"assets/php/get_valid_pilots.php?type=pil&date="+cald.getFullYear()+"-"+(cald.getMonth()+1)+"-"+cald.getDate()+"\"";
									}else{
										data ="";
										cls = ""
									}
									pilStr = "<div class='pil'><strong>Piloto:</strong><br/><a class='"+cls+"' "+data+">None</a></div>";
								}
								classStr += "<td class='"+tdClass+"'>"+comStr+pilStr+"</td>";
							}

							
							$(".fullsched #day"+i).append(classStr);
							
						}

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
								type: "GET"
							},
							escape: false,
							success: function(result){
								console.log(result)
								if(result.substr(0,5) != "false"){
									var res = JSON.parse(result);
									if(res["name"] == "None"){
										$(this).html(res["name"])
									}else{
										$(this).html("<strong>"+res["name"]+"</strong>")
									}
									if($(this).parent().parent().find(".com").find(".edit").text() == "None"){
										$(this).parent().parent().removeClass("blue-bg");
										$(this).parent().parent().addClass("dark-bg");
									}else{
										$(this).parent().parent().removeClass("dark-bg");
										$(this).parent().parent().addClass("blue-bg");
									}
									updateMySched(parseInt($("#sched_week").val()));
								}else{
									alert("An error occured, please try again")
								}
								//update user sched if need be
							},
							display: false
						});
					}
				}
			});

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
								for(var j = 0; j < res["date"].length; j++){
									temp = new Date(res["date"][j]+" 12:00:00");
									if(temp.getDay() == i){
										if(res["pos"][j] == "com" && res["otherPil"][j] != ""){
											withStr = "With piloto: "+res["otherPil"][j];
										}else if(res["pos"][j] == "pil" && res["otherPil"][j] != ""){
											withStr = "With comandante: "+res["otherPil"][j];
										}else{
											withStr = "Solo";
										}
										tempStr = "<td class=\"tint-bg\"><strong>"+res["craft"][j].toUpperCase()+"</strong><br/>"+withStr+"</td>";
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
							$(".mysched thead #"+i).append("<br/>"+(tempD.getMonth()+1)+"/"+tempD.getDate());
						}

						$("#userSched").html(str);
					}
				}
			})
		}
	});
})