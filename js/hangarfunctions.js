$(document).ready(function(){	
	Dropzone.autoDiscover = false;
	$("#confpass").keydown(function(e){
		if(e.which == 13){
			$("#changePassBtn").trigger("click");
		}
	});
	$("body").addClass("body-bg");
	$.ajax({
		type: "POST",
		url: "assets/php/checkAdmin.php",
		success: function(data){
			$(".sidebar-list a[href='hangar.php'] li").addClass("active");
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

			//get pilot info
			$.ajax({
				type: "POST",
				url: "assets/php/get_pilot.php",
				data: {id: "user"},
				success: function(response){
					if(response != "false" && response != "" && response != null){
						var res = JSON.parse(response);
						// $(".dropdown-header #username").text(res["fname"]+" "+res["lname"]);
						$("#personalInfo #name").html("<span class='infoEdit' data-name='fname'>"+res["fname"][0]+"</span> <div style='margin-left: 0.5em; display: inline;'><span class='infoEdit' data-name='lname'>"+res["lname"][0]+"</span></div>");
						$("#personalInfo #username").html("<span class='infoEdit' data-name='username'>"+res["username"][0]+"</span>");
						$("#personalInfo #nationality").html("<span class='infoEdit' data-name='nationality'>"+res["nationality"][0]+"</span>");
						if(res["comandante"][0] == 1){
							pos = "Comandante";
						}else{
							pos = "Piloto";
						}

						$("#personalInfo #pos").html("<span class='infoEdit' data-type='select' data-name='comandante'>"+pos+"<span>");
						$("#personalInfo #angLic").html("<span class='infoEdit' data-name='ang_license'>"+res["ang_license"][0]+"</span>");
						$("#personalInfo #forLic").html("<span class='infoEdit' data-name='for_license'>"+res["for_license"][0]+"</span>");
						$("#personalInfo #persEmail").html("<span class='infoEdit' data-name='email'>"+res["email"][0]+"</span>");
						$("#personalInfo #persPhone").html("<span class='infoEdit' data-name='phone' data-placeholder='1 555-555-5555'>"+res["phone"][0]+"</span>");
						$("#personalInfo #persPhoneTwo").html("<span class='infoEdit' data-name='phonetwo' data-placeholder='1 555-555-5555'>"+res["phonetwo"][0]+"</span>");
						
						if(res["profile_picture"][0] != null){
							$("#profile-picture").attr("src", "uploads/pictures/"+res["profile_picture"][0]);
						}else{
							$("#profile-picture").attr("src", "uploads/pictures/default_picture.jpg");
						}

						$("#personalInfo li").children().css("display", "inline");
						$(".infoEdit").editable({
							pk: "notneeded",
							url: "assets/php/update_personal_info.php",
							ajaxOptions:{
								type: "POST",
								cache: false,
								success: function(result){
								}
							},
							source: {"1": "Comandante", "2": "Piloto"}
						}).on("shown", function(e, editable){
							if($(this).data("name") == "username"){
								editable.input.$input.keyup(function(){
									if($(this).val() != $.trim($(this).val()))
										$(this).val($.trim($(this).val()));
							        var that = this;
							        if($(this).val().length < 4){
							            $(this).removeClass("input-success").addClass("input-error");
							            $(".editable-error-block").show().text("Username must be more that 4 characters");
							        }else{
							           $.ajax({
							                type: "GET",
							                data: {username: $(this).val()},
							                url: "assets/php/check_username.php",
							                success: function(result){
							                    if(result == "not taken"){
							                        $(that).removeClass("input-error").addClass("input-success");
							                        $(that).parents(".form-group").find("button[type='submit']").removeClass("disabled");
							                        $(".editable-error-block").show().text("");
							                    }else{
							                        $(that).removeClass("input-success").addClass("input-error");
							                        $(that).parents(".form-group").find("button[type='submit']").addClass("disabled");
							                        $(".editable-error-block").show().html("This username is taken");
							                    }
							                }
							            }) 
							        }
								})
							}	
						});

						var onOffAr = res["onOff"][0];
						for(var o = 0; o < onOffAr.length; o++){
							if(onOffAr[o]["inSched"]){
								var inSchedStr = "<td class='active'>True</td><th class=\"text-center\"><div class=\"btn btn-sm btn-warning removeAvailDates\" data-on='1' data-id='"+res["id"][0]+"'>-</div></th></tr>";
								var oclss = "dateEditable";
							}else{
								var inSchedStr = "<td class='info'>False</td><th class=\"text-center\"><div class=\"btn btn-sm btn-warning removeAvailDates\" data-on='0' data-id='"+res["id"][0]+"'>-</div></th></tr>";
								var oclss = "dateEditable";
							}
							$("#availabilityTable tbody").prepend("<tr><td><span class='on-date "+oclss+"' data-name='on' data-pk='"+res["id"][0]+"'>"+onOffAr[o]["on"]+"</span></td><td><span class='off-date "+oclss+"' data-name='off' data-pk='"+res["id"][0]+"'>"+onOffAr[o]["off"]+"</span></td>"+inSchedStr);
						}

						$(".dateEditable").css("cursor", "pointer");
						$(".dateEditable").editable({
							type: "date",
							url: "assets/php/update_on_off.php",
							datepicker: {
								weekStart: 1
							},
							params: function(params){
								params.on = $(this).parents("tr").find(".on-date").text(); 
								params.off = $(this).parents("tr").find(".off-date").text();
								return params
							},
							ajaxOptions: {
								type: "POST",
								cache: false
							},
							success: function(result){
								showNotification("success", "You successfully changed the date");
							}
						})
						
						$("input.on-date:not(.on-off), input.off-date:not(.on-off)").datepicker({
							format: "yyyy-mm-dd",
							autoclose: true,
							weekStart: 1
						})

						$(".addOnOffDate").click(function(e){
							e.stopPropagation();
							if($(this).parent().parent().find(".on-date").val() == ""){
								$(this).parent().parent().find(".on-date").focus();
							}else if($(this).parent().parent().find(".off-date").val() == ""){
								$(this).parent().parent().find(".off-date").focus();
							}else{
								var on = $(this).parent().parent().find(".on-date").val();
								var off = $(this).parent().parent().find(".off-date").val();
								var id = $(this).attr("data-id");
								var inputOn = new Date(on+"T12:00:00");
								var inputOff = new Date(off+"T12:00:00");
								var already = false, error = [];
								$(this).parents("tbody").children().each(function(){
									var tempOn = new Date($(this).find(".on-date").text()+"T12:00:00");
									var tempOff = new Date($(this).find(".off-date").text()+"T12:00:00");
									if(dates.inRange(inputOn, tempOn, tempOff)){
										error.push("Date ranges may not overlap. Your STARTING date is already scheduled.");

										already = true;
									}else if(dates.inRange(inputOff, tempOn, tempOff)){
										error.push("Date ranges may not overlap. Your ENDING date is already scheduled.");
										already = true;
									}
								});
								if(already){
									for(var el = 0; el < error.length; el++){
										showNotification("error", error[el]);
									}
									return;
								}
								var that = this;
								$.ajax({
									type: "POST",
									url: "assets/php/insert_on_off.php",
									data: {id: id, on: on, off: off},
									cache: false,
									success: function(result){
										if(result == "success"){
											showNotification("success", "You successfully added the date");
											var rowstr = "<tr><td><span class='dateEditable on-date' data-name='off'>"+on+"</span></td><td><span class='dateEditable off-date'>"+off+"</span></td><td class='info'>False</td><th class=\"text-center\"><div class=\"btn btn-sm btn-warning removeAvailDates\" data-id='"+id+"'>-</div></th></tr>";
											$(rowstr).insertBefore($(that).parent().parent()); 


											$(".dateEditable").css("cursor", "pointer");
											$(".dateEditable").editable({
												type: "date",
												url: "assets/php/update_on_off.php"
											});
											$(".removeAvailDates").click(function(e){
												e.stopPropagation();

												var id = $(this).data("id");
												var on = $(this).parent().parent().find(".on-date").text();
												var off = $(this).parent().parent().find(".off-date").text();
												var that = this;
												$.ajax({
													type: "POST",
													url: "assets/php/remove_available.php",
													data: {id: id, on: on, off: off},
													success: function(result){
														if(result == "success"){
															showNotification("success", "You successfully removed the date");
															$(that).parent().parent().remove();
														}else{
							  								showNotification("error", result);
														}
													}
												})
											});
										}else{
											showNotification("error", result);
										}
									}
								});
							}
						});

						$(".removeAvailDates").click(function(e){
							e.stopPropagation();

							var id = $(this).data("id");
							var on = $(this).parent().parent().find(".on-date").text();
							var off = $(this).parent().parent().find(".off-date").text();

							var that = this;
							$.ajax({
								type: "POST",
								url: "assets/php/remove_available.php",
								data: {id: id, on: on, off: off},
								success: function(result){
									if(result == "success"){
										showNotification("success", "You successfully removed the date");
										$(that).parent().parent().remove();
									}else{
										showNotification("error", result);
									}
								}
							})
						});
					}
				}
			});


			//get validity check
			$.ajax({
				type: "POST",
				url: "assets/php/get_pilot_validity.php",
				success: function(response){
					if(response != "false" && response != ""){
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
								if(array[i][j].val != null){
									var vald = new Date(array[i][j].val+"T12:00:00");
									var cls = "tint-bg";
									var dateStr = vald.getFullYear()+"-"+returnAbrvMonth(vald.getMonth())+"-"+doubleDigit(vald.getDate())
									var content = "<span class='valText'>Valid</span><br/><span class='validityDate' data-type='date' data-name='"+array[i][j].header+"' data-pk='holder'>"+dateStr+"</span>";
									if(vald.getTime() < cur.getTime()){
										cls = "alert alert-danger"
										content = "<span class='valText'>EXPIRED</span><br/><span class='validityDate' data-type='date' data-name='"+array[i][j].header+"' data-pk='holder'>"+dateStr+"</span>";
									}else if(vald.getTime() - cur.getTime() <= (4*7*24*60*60*1000)){
										cls = "alert alert-warning";
										content = "<span class='valText'>Expires Soon</span><br/><span class='validityDate' data-type='date' data-name='"+array[i][j].header+"' data-pk='holder'>"+dateStr+"</span>";
									}
								}else{
									var cls = "alert-null";
									var content = "<span class='valText'></span><br/><span class='validityDate' data-type='date' data-name='"+array[i][j].header+"' data-pk='holder'>Select Expiry Date</span>";
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
							emptyclass: null,
							datepicker: {
								weekStart: 1
							},
							success: function(data){
								console.log(data);
								if(data == "null"){
									$(this).text("Select Expiry Date")
									$(this).parent().attr("class", "alert-null");
									$(this).siblings(".valText").text("");
								}else{
									data = data.substring(1, (data.length-1));
									var checkD = new Date(data+"T12:00:00");
									var valCur = new Date();
									//$(this).text(data)
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
								}
							},
							validate: function(value){
								// entered = moment(value);
								// today = moment().subtract(1, "d").format("YYYY-MM-DD");
								// if(entered.isBefore(today, "day"))
								// 	return "Please select future date";
							},
							viewformat: "yyyy-M-dd"
						}).on("shown", function(e, editable){
							var that = this;
							$(".editable-clear a").text("Remove test date").unbind("click").click(function(){
								$(that).editable("setValue", "");
								$(that).editable("submit");
								$(that).editable("hide");
							});
						})
					}
				}
			});
		}
	});

	var TZ = {"Afghanistan Standard Time": {"Display":"(UTC+04:30) Kabul","Dlt":"","Std":"Afghanistan Standard Time","Bias":"-270","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Alaskan Standard Time": {"Display":"(UTC-09:00) Alaska","Dlt":"","Std":"Alaskan Standard Time","Bias":"540","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, 1st Sun in Nov","DltDate":"2:00:00 AM, 2nd Sun in Mar"},"Arab Standard Time": {"Display":"(UTC+03:00) Kuwait, Riyadh","Dlt":"","Std":"Arab Standard Time","Bias":"-180","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Arabian Standard Time": {"Display":"(UTC+04:00) Abu Dhabi, Muscat","Dlt":"","Std":"Arabian Standard Time","Bias":"-240","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Arabic Standard Time": {"Display":"(UTC+03:00) Baghdad","Dlt":"","Std":"Arabic Standard Time","Bias":"-180","StdBias":"0",
"DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Argentina Standard Time": {"Display":"(UTC-03:00) Buenos Aires","Dlt":"","Std":"Argentina Standard Time","Bias":"180","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Atlantic Standard Time": {"Display":"(UTC-04:00) Atlantic Time (Canada)","Dlt":"","Std":"Atlantic Standard Time","Bias":"240","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, 1st Sun in Nov","DltDate":"2:00:00 AM, 2nd Sun in Mar"},"AUS Central Standard Time": {"Display":"(UTC+09:30) Darwin","Dlt":"","Std":"AUS Central Standard Time","Bias":"-570","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"AUS Eastern Standard Time": {"Display":"(UTC+10:00) Canberra, Melbourne, Sydney","Dlt":"","Std":"AUS Eastern Standard Time","Bias":"-600","StdBias":"0","DltBias":"-60","StdDate":"3:00:00 AM, 1st Sun in Apr","DltDate":"2:00:00 AM, 1st Sun in Oct"},
"Azerbaijan Standard Time": {"Display":"(UTC+04:00) Baku","Dlt":"","Std":"Azerbaijan Standard Time","Bias":"-240","StdBias":"0","DltBias":"-60","StdDate":"5:00:00 AM, last Sun in Oct","DltDate":"4:00:00 AM, last Sun in Mar"},"Azores Standard Time": {"Display":"(UTC-01:00) Azores","Dlt":"","Std":"Azores Standard Time","Bias":"60","StdBias":"0","DltBias":"-60","StdDate":"1:00:00 AM, last Sun in Oct","DltDate":"12:00:00 AM, last Sun in Mar"},"Bahia Standard Time": {"Display":"(UTC-03:00) Salvador","Dlt":"","Std":"Bahia Standard Time","Bias":"180","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Bangladesh Standard Time": {"Display":"(UTC+06:00) Dhaka","Dlt":"","Std":"Bangladesh Standard Time","Bias":"-360","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Belarus Standard Time": {"Display":"(UTC+03:00) Minsk","Dlt":"","Std":"Belarus Standard Time","Bias":"-180","StdBias":"0","DltBias":"-60",
"StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Canada Central Standard Time": {"Display":"(UTC-06:00) Saskatchewan","Dlt":"","Std":"Canada Central Standard Time","Bias":"360","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Cape Verde Standard Time": {"Display":"(UTC-01:00) Cape Verde Is.","Dlt":"","Std":"Cape Verde Standard Time","Bias":"60","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Caucasus Standard Time": {"Display":"(UTC+04:00) Yerevan","Dlt":"","Std":"Caucasus Standard Time","Bias":"-240","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Cen. Australia Standard Time": {"Display":"(UTC+09:30) Adelaide","Dlt":"","Std":"Cen. Australia Standard Time","Bias":"-570","StdBias":"0","DltBias":"-60","StdDate":"3:00:00 AM, 1st Sun in Apr","DltDate":"2:00:00 AM, 1st Sun in Oct"},"Central America Standard Time": {"Display":"(UTC-06:00) Central America",
"Dlt":"","Std":"Central America Standard Time","Bias":"360","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Central Asia Standard Time": {"Display":"(UTC+06:00) Astana","Dlt":"","Std":"Central Asia Standard Time","Bias":"-360","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Central Brazilian Standard Time": {"Display":"(UTC-04:00) Cuiaba","Dlt":"","Std":"Central Brazilian Standard Time","Bias":"240","StdBias":"0","DltBias":"-60","StdDate":"11:59:59 PM, 3rd Sat in Feb","DltDate":"11:59:59 PM, 3rd Sat in Oct"},"Central Europe Standard Time": {"Display":"(UTC+01:00) Belgrade, Bratislava, Budapest, Ljubljana, Prague","Dlt":"","Std":"Central Europe Standard Time","Bias":"-60","StdBias":"0","DltBias":"-60","StdDate":"3:00:00 AM, last Sun in Oct","DltDate":"2:00:00 AM, last Sun in Mar"},"Central European Standard Time": {"Display":"(UTC+01:00) Sarajevo, Skopje, Warsaw, Zagreb","Dlt":"",
"Std":"Central European Standard Time","Bias":"-60","StdBias":"0","DltBias":"-60","StdDate":"3:00:00 AM, last Sun in Oct","DltDate":"2:00:00 AM, last Sun in Mar"},"Central Pacific Standard Time": {"Display":"(UTC+11:00) Solomon Is., New Caledonia","Dlt":"","Std":"Central Pacific Standard Time","Bias":"-660","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Central Standard Time": {"Display":"(UTC-06:00) Central Time (US & Canada)","Dlt":"","Std":"Central Standard Time","Bias":"360","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, 1st Sun in Nov","DltDate":"2:00:00 AM, 2nd Sun in Mar"},"Central Standard Time (Mexico)": {"Display":"(UTC-06:00) Guadalajara, Mexico City, Monterrey","Dlt":"","Std":"Central Standard Time (Mexico)","Bias":"360","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct","DltDate":"2:00:00 AM, 1st Sun in Apr"},"China Standard Time": {"Display":"(UTC+08:00) Beijing, Chongqing, Hong Kong, Urumqi",
"Dlt":"","Std":"China Standard Time","Bias":"-480","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Dateline Standard Time": {"Display":"(UTC-12:00) International Date Line West","Dlt":"","Std":"Dateline Standard Time","Bias":"720","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"E. Africa Standard Time": {"Display":"(UTC+03:00) Nairobi","Dlt":"","Std":"E. Africa Standard Time","Bias":"-180","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"E. Australia Standard Time": {"Display":"(UTC+10:00) Brisbane","Dlt":"","Std":"E. Australia Standard Time","Bias":"-600","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"E. Europe Standard Time": {"Display":"(UTC+02:00) E. Europe","Dlt":"","Std":"E. Europe Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60",
"StdDate":"3:00:00 AM, last Sun in Oct","DltDate":"2:00:00 AM, last Sun in Mar"},"E. South America Standard Time": {"Display":"(UTC-03:00) Brasilia","Dlt":"","Std":"E. South America Standard Time","Bias":"180","StdBias":"0","DltBias":"-60","StdDate":"11:59:59 PM, 3rd Sat in Feb","DltDate":"11:59:59 PM, 3rd Sat in Oct"},"Eastern Standard Time": {"Display":"(UTC-05:00) Eastern Time (US & Canada)","Dlt":"","Std":"Eastern Standard Time","Bias":"300","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, 1st Sun in Nov","DltDate":"2:00:00 AM, 2nd Sun in Mar"},"Egypt Standard Time": {"Display":"(UTC+02:00) Cairo","Dlt":"","Std":"Egypt Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60","StdDate":"11:59:59 PM, last Thu in Sep","DltDate":"11:59:59 PM, 3rd Thu in May"},"Ekaterinburg Standard Time": {"Display":"(UTC+05:00) Ekaterinburg (RTZ 4)","Dlt":"","Std":"Russia TZ 4 Standard Time","Bias":"-300","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct",
"DltDate":"12:00:00 AM, 1st Wed in Jan"},"Fiji Standard Time": {"Display":"(UTC+12:00) Fiji","Dlt":"","Std":"Fiji Standard Time","Bias":"-720","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, 3rd Sun in Jan","DltDate":"2:00:00 AM, 4th Sun in Oct"},"FLE Standard Time": {"Display":"(UTC+02:00) Helsinki, Kyiv, Riga, Sofia, Tallinn, Vilnius","Dlt":"","Std":"FLE Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60","StdDate":"4:00:00 AM, last Sun in Oct","DltDate":"3:00:00 AM, last Sun in Mar"},"Georgian Standard Time": {"Display":"(UTC+04:00) Tbilisi","Dlt":"","Std":"Georgian Standard Time","Bias":"-240","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"GMT Standard Time": {"Display":"(UTC) Dublin, Edinburgh, Lisbon, London","Dlt":"","Std":"GMT Standard Time","Bias":"0","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct","DltDate":"1:00:00 AM, last Sun in Mar"},
"Greenland Standard Time": {"Display":"(UTC-03:00) Greenland","Dlt":"","Std":"Greenland Standard Time","Bias":"180","StdBias":"0","DltBias":"-60","StdDate":"11:00:00 PM, last Sat in Oct","DltDate":"10:00:00 PM, last Sat in Mar"},"Greenwich Standard Time": {"Display":"(UTC) Monrovia, Reykjavik","Dlt":"","Std":"Greenwich Standard Time","Bias":"0","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"GTB Standard Time": {"Display":"(UTC+02:00) Athens, Bucharest","Dlt":"","Std":"GTB Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60","StdDate":"4:00:00 AM, last Sun in Oct","DltDate":"3:00:00 AM, last Sun in Mar"},"Hawaiian Standard Time": {"Display":"(UTC-10:00) Hawaii","Dlt":"","Std":"Hawaiian Standard Time","Bias":"600","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"India Standard Time": {"Display":"(UTC+05:30) Chennai, Kolkata, Mumbai, New Delhi","Dlt":"",
"Std":"India Standard Time","Bias":"-330","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Iran Standard Time": {"Display":"(UTC+03:30) Tehran","Dlt":"","Std":"Iran Standard Time","Bias":"-210","StdBias":"0","DltBias":"-60","StdDate":"11:59:59 PM, 3rd Mon in Sep","DltDate":"11:59:59 PM, 3rd Sat in Mar"},"Israel Standard Time": {"Display":"(UTC+02:00) Jerusalem","Dlt":"","Std":"Jerusalem Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct","DltDate":"2:00:00 AM, last Fri in Mar"},"Jordan Standard Time": {"Display":"(UTC+02:00) Amman","Dlt":"","Std":"Jordan Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60","StdDate":"1:00:00 AM, last Fri in Oct","DltDate":"11:59:59 PM, last Thu in Mar"},"Kaliningrad Standard Time": {"Display":"(UTC+02:00) Kaliningrad (RTZ 1)","Dlt":"","Std":"Russia TZ 1 Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct",
"DltDate":"12:00:00 AM, 1st Wed in Jan"},"Kamchatka Standard Time": {"Display":"(UTC+12:00) Petropavlovsk-Kamchatsky - Old","Dlt":"","Std":"Kamchatka Standard Time","Bias":"-720","StdBias":"0","DltBias":"-60","StdDate":"3:00:00 AM, last Sun in Oct","DltDate":"2:00:00 AM, last Sun in Mar"},"Korea Standard Time": {"Display":"(UTC+09:00) Seoul","Dlt":"","Std":"Korea Standard Time","Bias":"-540","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Libya Standard Time": {"Display":"(UTC+02:00) Tripoli","Dlt":"","Std":"Libya Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Line Islands Standard Time": {"Display":"(UTC+14:00) Kiritimati Island","Dlt":"","Std":"Line Islands Standard Time","Bias":"-840","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Magadan Standard Time": {"Display":"(UTC+10:00) Magadan","Dlt":"",
"Std":"Magadan Standard Time","Bias":"-600","StdBias":"0","DltBias":"-120","StdDate":"2:00:00 AM, last Sun in Oct","DltDate":"12:00:00 AM, 1st Wed in Jan"},"Mauritius Standard Time": {"Display":"(UTC+04:00) Port Louis","Dlt":"","Std":"Mauritius Standard Time","Bias":"-240","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Mid-Atlantic Standard Time": {"Display":"(UTC-02:00) Mid-Atlantic - Old","Dlt":"","Std":"Mid-Atlantic Standard Time","Bias":"120","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Sep","DltDate":"2:00:00 AM, last Sun in Mar"},"Middle East Standard Time": {"Display":"(UTC+02:00) Beirut","Dlt":"","Std":"Middle East Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60","StdDate":"11:59:59 PM, last Sat in Oct","DltDate":"11:59:59 PM, last Sat in Mar"},"Montevideo Standard Time": {"Display":"(UTC-03:00) Montevideo","Dlt":"","Std":"Montevideo Standard Time","Bias":"180","StdBias":"0","DltBias":"-60",
"StdDate":"2:00:00 AM, 2nd Sun in Mar","DltDate":"2:00:00 AM, 1st Sun in Oct"},"Morocco Standard Time": {"Display":"(UTC) Casablanca","Dlt":"","Std":"Morocco Standard Time","Bias":"0","StdBias":"0","DltBias":"-60","StdDate":"3:00:00 AM, last Sun in Oct","DltDate":"2:00:00 AM, last Sun in Mar"},"Mountain Standard Time": {"Display":"(UTC-07:00) Mountain Time (US & Canada)","Dlt":"","Std":"Mountain Standard Time","Bias":"420","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, 1st Sun in Nov","DltDate":"2:00:00 AM, 2nd Sun in Mar"},"Mountain Standard Time (Mexico)": {"Display":"(UTC-07:00) Chihuahua, La Paz, Mazatlan","Dlt":"","Std":"Mountain Standard Time (Mexico)","Bias":"420","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct","DltDate":"2:00:00 AM, 1st Sun in Apr"},"Myanmar Standard Time": {"Display":"(UTC+06:30) Yangon (Rangoon)","Dlt":"","Std":"Myanmar Standard Time","Bias":"-390","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},
"N. Central Asia Standard Time": {"Display":"(UTC+06:00) Novosibirsk (RTZ 5)","Dlt":"","Std":"Russia TZ 5 Standard Time","Bias":"-360","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct","DltDate":"12:00:00 AM, 1st Wed in Jan"},"Namibia Standard Time": {"Display":"(UTC+01:00) Windhoek","Dlt":"","Std":"Namibia Standard Time","Bias":"-60","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, 1st Sun in Apr","DltDate":"2:00:00 AM, 1st Sun in Sep"},"Nepal Standard Time": {"Display":"(UTC+05:45) Kathmandu","Dlt":"","Std":"Nepal Standard Time","Bias":"-345","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"New Zealand Standard Time": {"Display":"(UTC+12:00) Auckland, Wellington","Dlt":"","Std":"New Zealand Standard Time","Bias":"-720","StdBias":"0","DltBias":"-60","StdDate":"3:00:00 AM, 1st Sun in Apr","DltDate":"2:00:00 AM, last Sun in Sep"},"Newfoundland Standard Time": {"Display":"(UTC-03:30) Newfoundland","Dlt":"",
"Std":"Newfoundland Standard Time","Bias":"210","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, 1st Sun in Nov","DltDate":"2:00:00 AM, 2nd Sun in Mar"},"North Asia East Standard Time": {"Display":"(UTC+08:00) Irkutsk (RTZ 7)","Dlt":"","Std":"Russia TZ 7 Standard Time","Bias":"-480","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct","DltDate":"12:00:00 AM, 1st Wed in Jan"},"North Asia Standard Time": {"Display":"(UTC+07:00) Krasnoyarsk (RTZ 6)","Dlt":"","Std":"Russia TZ 6 Standard Time","Bias":"-420","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct","DltDate":"12:00:00 AM, 1st Wed in Jan"},"Pacific SA Standard Time": {"Display":"(UTC-04:00) Santiago","Dlt":"","Std":"Pacific SA Standard Time","Bias":"240","StdBias":"0","DltBias":"-60","StdDate":"11:59:59 PM, last Sat in Apr","DltDate":"11:59:59 PM, 1st Sat in Sep"},"Pacific Standard Time": {"Display":"(UTC-08:00) Pacific Time (US & Canada)","Dlt":"","Std":"Pacific Standard Time","Bias":"480",
"StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, 1st Sun in Nov","DltDate":"2:00:00 AM, 2nd Sun in Mar"},"Pacific Standard Time (Mexico)": {"Display":"(UTC-08:00) Baja California","Dlt":"","Std":"Pacific Standard Time (Mexico)","Bias":"480","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct","DltDate":"2:00:00 AM, 1st Sun in Apr"},"Pakistan Standard Time": {"Display":"(UTC+05:00) Islamabad, Karachi","Dlt":"","Std":"Pakistan Standard Time","Bias":"-300","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Paraguay Standard Time": {"Display":"(UTC-04:00) Asuncion","Dlt":"","Std":"Paraguay Standard Time","Bias":"240","StdBias":"0","DltBias":"-60","StdDate":"11:59:59 PM, 4th Sat in Mar","DltDate":"11:59:59 PM, 1st Sat in Oct"},"Romance Standard Time": {"Display":"(UTC+01:00) Brussels, Copenhagen, Madrid, Paris","Dlt":"","Std":"Romance Standard Time","Bias":"-60","StdBias":"0","DltBias":"-60","StdDate":"3:00:00 AM, last Sun in Oct",
"DltDate":"2:00:00 AM, last Sun in Mar"},"Russia Time Zone 10": {"Display":"(UTC+11:00) Chokurdakh (RTZ 10)","Dlt":"","Std":"Russia TZ 10 Standard Time","Bias":"-660","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Russia Time Zone 11": {"Display":"(UTC+12:00) Anadyr, Petropavlovsk-Kamchatsky (RTZ 11)","Dlt":"","Std":"Russia TZ 11 Standard Time","Bias":"-720","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Russia Time Zone 3": {"Display":"(UTC+04:00) Izhevsk, Samara (RTZ 3)","Dlt":"","Std":"Russia TZ 3 Standard Time","Bias":"-240","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Russian Standard Time": {"Display":"(UTC+03:00) Moscow, St. Petersburg, Volgograd (RTZ 2)","Dlt":"","Std":"Russia TZ 2 Standard Time","Bias":"-180","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct","DltDate":"12:00:00 AM, 1st Wed in Jan"},
"SA Eastern Standard Time": {"Display":"(UTC-03:00) Cayenne, Fortaleza","Dlt":"","Std":"SA Eastern Standard Time","Bias":"180","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"SA Pacific Standard Time": {"Display":"(UTC-05:00) Bogota, Lima, Quito, Rio Branco","Dlt":"","Std":"SA Pacific Standard Time","Bias":"300","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"SA Western Standard Time": {"Display":"(UTC-04:00) Georgetown, La Paz, Manaus, San Juan","Dlt":"","Std":"SA Western Standard Time","Bias":"240","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Samoa Standard Time": {"Display":"(UTC+13:00) Samoa","Dlt":"","Std":"Samoa Standard Time","Bias":"-780","StdBias":"0","DltBias":"-60","StdDate":"1:00:00 AM, 1st Sun in Apr","DltDate":"12:00:00 AM, last Sun in Sep"},"SE Asia Standard Time": {"Display":"(UTC+07:00) Bangkok, Hanoi, Jakarta","Dlt":"",
"Std":"SE Asia Standard Time","Bias":"-420","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Singapore Standard Time": {"Display":"(UTC+08:00) Kuala Lumpur, Singapore","Dlt":"","Std":"Malay Peninsula Standard Time","Bias":"-480","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"South Africa Standard Time": {"Display":"(UTC+02:00) Harare, Pretoria","Dlt":"","Std":"South Africa Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Sri Lanka Standard Time": {"Display":"(UTC+05:30) Sri Jayawardenepura","Dlt":"","Std":"Sri Lanka Standard Time","Bias":"-330","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Syria Standard Time": {"Display":"(UTC+02:00) Damascus","Dlt":"","Std":"Syria Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60",
"StdDate":"11:59:59 PM, last Thu in Oct","DltDate":"11:59:59 PM, 1st Thu in Apr"},"Taipei Standard Time": {"Display":"(UTC+08:00) Taipei","Dlt":"","Std":"Taipei Standard Time","Bias":"-480","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Tasmania Standard Time": {"Display":"(UTC+10:00) Hobart","Dlt":"","Std":"Tasmania Standard Time","Bias":"-600","StdBias":"0","DltBias":"-60","StdDate":"3:00:00 AM, 1st Sun in Apr","DltDate":"2:00:00 AM, 1st Sun in Oct"},"Tokyo Standard Time": {"Display":"(UTC+09:00) Osaka, Sapporo, Tokyo","Dlt":"","Std":"Tokyo Standard Time","Bias":"-540","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Tonga Standard Time": {"Display":"(UTC+13:00) Nuku'alofa","Dlt":"","Std":"Tonga Standard Time","Bias":"-780","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Turkey Standard Time": {"Display":"(UTC+02:00) Istanbul","Dlt":"",
"Std":"Turkey Standard Time","Bias":"-120","StdBias":"0","DltBias":"-60","StdDate":"4:00:00 AM, last Sun in Oct","DltDate":"3:00:00 AM, last Mon in Mar"},"Ulaanbaatar Standard Time": {"Display":"(UTC+08:00) Ulaanbaatar","Dlt":"","Std":"Ulaanbaatar Standard Time","Bias":"-480","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"US Eastern Standard Time": {"Display":"(UTC-05:00) Indiana (East)","Dlt":"","Std":"US Eastern Standard Time","Bias":"300","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, 1st Sun in Nov","DltDate":"2:00:00 AM, 2nd Sun in Mar"},"US Mountain Standard Time": {"Display":"(UTC-07:00) Arizona","Dlt":"","Std":"US Mountain Standard Time","Bias":"420","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"UTC": {"Display":"(UTC) Coordinated Universal Time","Dlt":"","Std":"Coordinated Universal Time","Bias":"0","StdBias":"0","DltBias":"0","StdDate":"0 - No date established.","DltDate":"0 - No date established."},
"UTC+12": {"Display":"(UTC+12:00) Coordinated Universal Time+12","Dlt":"","Std":"UTC+12","Bias":"-720","StdBias":"0","DltBias":"0","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"UTC-02": {"Display":"(UTC-02:00) Coordinated Universal Time-02","Dlt":"","Std":"UTC-02","Bias":"120","StdBias":"0","DltBias":"0","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"UTC-11": {"Display":"(UTC-11:00) Coordinated Universal Time-11","Dlt":"","Std":"UTC-11","Bias":"660","StdBias":"0","DltBias":"0","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Venezuela Standard Time": {"Display":"(UTC-04:30) Caracas","Dlt":"","Std":"Venezuela Standard Time","Bias":"270","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Vladivostok Standard Time": {"Display":"(UTC+10:00) Vladivostok, Magadan (RTZ 9)","Dlt":"","Std":"Russia TZ 9 Standard Time","Bias":"-600","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct",
"DltDate":"12:00:00 AM, 1st Wed in Jan"},"W. Australia Standard Time": {"Display":"(UTC+08:00) Perth","Dlt":"","Std":"W. Australia Standard Time","Bias":"-480","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"W. Central Africa Standard Time": {"Display":"(UTC+01:00) West Central Africa","Dlt":"","Std":"W. Central Africa Standard Time","Bias":"-60","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"W. Europe Standard Time": {"Display":"(UTC+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna","Dlt":"","Std":"W. Europe Standard Time","Bias":"-60","StdBias":"0","DltBias":"-60","StdDate":"3:00:00 AM, last Sun in Oct","DltDate":"2:00:00 AM, last Sun in Mar"},"West Asia Standard Time": {"Display":"(UTC+05:00) Ashgabat, Tashkent","Dlt":"","Std":"West Asia Standard Time","Bias":"-300","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},
"West Pacific Standard Time": {"Display":"(UTC+10:00) Guam, Port Moresby","Dlt":"","Std":"West Pacific Standard Time","Bias":"-600","StdBias":"0","DltBias":"-60","StdDate":"0 - No date established.","DltDate":"0 - No date established."},"Yakutsk Standard Time": {"Display":"(UTC+09:00) Yakutsk (RTZ 8)","Dlt":"","Std":"Russia TZ 8 Standard Time","Bias":"-540","StdBias":"0","DltBias":"-60","StdDate":"2:00:00 AM, last Sun in Oct","DltDate":"12:00:00 AM, 1st Wed in Jan"}};
	
	timezoneOptions = "";
	$.each(TZ, function(i, val){
		timezoneOptions += "<option value='"+val.Bias+"'>"+val.Display+"</option>";
	});
	$("#clock-timezone").html(timezoneOptions);

	var c = $("#clock-timezone option");
	c.sort(function(a,b){
		if(parseInt($(a).attr("value")) > parseInt($(b).attr("value"))){
			return 1;
		}else if(parseInt($(a).attr("value")) < parseInt($(b).attr("value"))){
			return -1;
		}
		return 0;	
	});
	$("#clock-timezone").empty();
	$("#clock-timezone").html(c);

	$("#saveClockSettings").click(function(){
		var that = this;
		if($("#clock-name").val() != ""){
			$.ajax({
				url: "assets/php/save_clock.php",
				type: "POST",
				data: {timezone: $("#clock-timezone").val(), name: $("#clock-name").val()},
				success: function(result){
					console.log(result);
					if(result == "success"){
						showNotification("success", "You successfully changed your clock.");
						resetUserClock();
					}else{
						showNotification("error", "Changing your clock failed.");
					}						
				}
			})
		}
	});

	$.ajax({
		type: "GET",
		url: "assets/php/get_clock_settings.php",
		success: function(result){
			console.log(result);
			if(result.charAt(0) == "{" || result.charAt(0) == "["){
				res = JSON.parse(result);
				$("#clock-timezone").val(res.tz);
				$("#clock-name").val(res.name);
			}
		}
	});

	$("#change-profile-picture").on("hidden.bs.modal", function(){
		$(".dz-preview").remove();
		$(".dropzone").removeClass("dz-started");
	})

	$("#uploadDocuments").dropzone({
		url: "assets/php/change-profile-picture.php",
		maxFilesize: 1.5,
		clickable: true,
		acceptedFiles: ".jpg,.jpeg,.png,.gif,.bmp,.JPG,.JPEG,.PNG,.GIF,.BMP",
		previewTemplate: '<div class="dz-preview dz-file-preview"><div class="dz-details"><div class="dz-filename"><span data-dz-name></span></div><div class="dz-size" data-dz-size></div><img data-dz-thumbnail /></div><div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span></div><div class="dz-success-mark"><span class="fa fa-check-circle-o fa-2x"></span></div><div class="dz-error-mark"><span class="fa fa-times-circle-o fa-2x"></span></div><div class="dz-error-message"><span data-dz-errormessage></span></div></div>',
		init: function(){
			this.on("success", function(file, response) {
				console.log(file, response);
				if(response.substring(0,7) == "success"){
					$("#change-profile-picture").modal("hide");
					filename = response.substring(8);
					$("#profile-picture").attr("src", "uploads/pictures/"+filename);
				}
			});
		}
	});
});
function changePass(oldPass, newPass, confPass){
	var newP = $(newPass).val();
	var old = $(oldPass).val();
	var conf = $(confPass).val();
	if(old != "" || newP != "" || conf != ""){
		if(newP == conf){
			if(newP.length >= 8){
				var sym = /[!@#$%^&*()_+-=?<>{}~]/g;
				var alph = /[A-Za-z]/g;
				var num = /[0-9]/g;
				if(sym.test(newP) && alph.test(newP) && num.test(newP)){
					var pass = hex_sha512(newP);
					var oldP = hex_sha512(old);
					$.ajax({
						type: "POST",
						data: {pass: pass, old: oldP},
						url: "assets/php/change_password.php",
						success: function(result){
							if(result == "success"){
								showNotification("success", "You successfully changed your password.");
								$(".changepass").modal("toggle");
								$(oldPass).val("");
								$(newPass).val("");
								$(confPass).val("");
								$("body").animate({scrollTop: 0}, 900);
							}else if(result == "failed"){
								showNotification("error", "Changing your password was unsuccessful. Please try again later.");
								$(".changepass").modal("toggle");
								$(oldPass).val("");
								$(newPass).val("");
								$(confPass).val("");
								$("body").animate({scrollTop: 0}, 900);
							}else{
								$("#changePassError").text(result);
								$(oldPass).val("");
								$(newPass).val("");
								$(confPass).val("");
								$(oldPass).focus();
							}
						}
					})
				}else{
					$("#changePassError").text("Your new password does not contain the required characters.");
					$(newPass).val("");
					$(confPass).val("");
					$(newPass).focus();
				}
			}else{
				$("#changePassError").text("Your new password is too short.");
				$(newPass).val("");
				$(confPass).val("");
				$(newPass).focus();
			}
		}else{
			$("#changePassError").text("Your new passwords do not match");
			$(newPass).val("");
			$(confPass).val("");
			$(newPass).focus();
		}
	}else{
		$("#changePassError").text("Please fill in all fields");
	}
		
}