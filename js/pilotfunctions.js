function returnBackground(i){
	var bg = ["dark-bg", "tint-bg", "", "blue-bg"];
	return(bg[(i%4)]);
}

var CONTRACTS = [], admin = false, CRAFTS = [];
$(window).resize(function(){
	$("#pilot_info_section").width($(".pilots").width()-330+"px");
})
$(document).ready(function(){
	Dropzone.autoDiscover = false;
	$("#printPilots").click(function(){
		$(".pilot_head").trigger("click");
		window.print();
	})
	$(".sidebar-list a[href='pilots.php'] li").addClass("active");
	$.fn.datepicker.defaults.format = "yyyy/mm/dd"; 

	$("#change-profile-picture").on("hidden.bs.modal", function(){
		$(".dz-preview").remove();
		$(".dropzone").removeClass("dz-started");
	})

	$("#uploadDocuments").dropzone({
		url: "assets/php/change-profile-picture.php?pilot_id=",
		maxFilesize: 1.5,
		clickable: true,
		acceptedFiles: ".jpg,.jpeg,.png,.gif,.bmp,.JPG,.JPEG,.PNG,.GIF,.BMP",
		previewTemplate: '<div class="dz-preview dz-file-preview"><div class="dz-details"><div class="dz-filename"><span data-dz-name></span></div><div class="dz-size" data-dz-size></div><img data-dz-thumbnail /></div><div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span></div><div class="dz-success-mark"><span class="fa fa-check-circle-o fa-2x"></span></div><div class="dz-error-mark"><span class="fa fa-times-circle-o fa-2x"></span></div><div class="dz-error-message"><span data-dz-errormessage></span></div></div>',
		init: function(){
			this.on("success", function(file, response) {
				if(response.substring(0,7) == "success"){
					$("#change-profile-picture").modal("hide");
					filename = response.substring(8);
					$("#profile-picture").attr("src", "uploads/pictures/"+filename);
				}
			});
		}
	});

	$("#printPilot").click(function(){
		if($("#pilots_list .selected").length != 0){
			data = ($("#pilot-print-validity").prop("checked") ? "validity;" : "")+($("#pilot-print-availability").prop("checked") ? "availability;" : "")+($("#pilot-print-info").prop("checked") ? "personal_info;" : "");
			if(data != ""){
				window.open("print.php?print_type=user_info&user_id="+$("#pilots_list .selected").data("pk")+"&data="+data+"&output=xlsx", "_blank");
				$("#printPilotModal").modal("hide");
			}else{
				console.log(data)
			}
		}else{
			console.log("no pilot selected");
		}
	})
		$.ajax({
			type: "POST",
			url: "assets/php/checkAdmin.php",
			success: function(data){
				$(window).resize();
				data = parseInt(data);
				admin = data;
				if([8,7,6,3].indexOf(data) != -1){
					//get pilot info
					$("#addPilotHeader").html("<div class='col-md-6'><button class=\"btn btn-danger outer-bottom-xs outer-left-xs\" data-toggle=\"modal\" data-target=\".addPilot\" style=\"margin-right: 15px;\">Add New Pilot/Manager</button></div><div class='col-md-6'><button class=\"btn btn-primary pull-right outer-bottom-xs outer-left-xs\" data-toggle=\"modal\" data-target=\"#printModal\" style=\"margin-right: 15px;\">Report Options</button></div>");
				}
					$.ajax({
						type: "GET",
						url: "assets/php/get_all_contracts.php",
						async: false,
						success: function(result){
							var res = JSON.parse(result);
							var contracts = [];
							for(var i = 0; i < res.length; i++){
								if(contracts.indexOf(res[i].contract) == -1){
									contracts[res[i].contract] = [];
									for(var j = 0; j < res.length; j++){
										if(res[j].craft == res[i].craft){
											var tempObj = {"class": res[j]["class"], "craft": res[j].craft, "craftid": res[j].craftid};
											contracts[res[i].contract].push(tempObj)
										}
									}
								}
								if($("#pilotContracts select option[value='"+res[i].contract+"']").length == 0){
									$("#pilotContracts select").append("<option value='"+res[i].contract+"'>"+res[i].contract+"</option>");
								}
							}
							CONTRACTS = contracts;
						}
					});

					$.ajax({
						type: "GET",
						url: "assets/php/get_all_crafts.php",
						async: false,
						data: {distinct: true},
						success: function(result){
							if(result.charAt(0) == "{" || result.charAt(0) == "["){
								CRAFTS = JSON.parse(result);
								var craftStr = "";
								for(i = 0; i < CRAFTS.length; i++){
									craftStr += "<option value='"+CRAFTS[i]+"'>"+CRAFTS[i]+"</option>";
								}
								$("#pilotCrafts select, #craftType").append(craftStr);
							}
						}
					});

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
					
				if([8,7,6,3].indexOf(admin) != -1){
					// ADD PILOTS MODAL
					$("#addDates").click(function(e){
						e.stopPropagation();
						$("#onOffDates").append("<div class=\"addedDate\"><div class=\"col-md-6\"><div class=\"lbl\">On</div><input type=\"text\"  placeholder='YYYY-mm-dd' class=\"dp on-date\" /></div><div class=\"col-md-6\"><div class=\"lbl\">Off</div><input type=\"text\" class=\"dp off-date\"  placeholder='YYYY-mm-dd' /></div></div>")
						$(".dp").datepicker("remove").datepicker({
							format: "yyyy-mm-dd",
							autoclose: true
						})
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

					$(".dp").each(function(){
						$(this).datepicker({
							format: "yyyy-mm-dd",
							autoclose: true
						})
					})

					var tests = ["ang_lic","for_lic","instruments","med","sim","base_check","line_check","ifr_check","ifr_cur","night_check","night_cur","hoist_check","crm","dang_good","huet","herds","hook","faids","fire","english","passport","ang_visa","us_visa","booklet"];
					var testStr = "<div class='col-md-6'>";
					for(var i = 0; i < tests.length; i++){
						testStr += (i == 14 ? "</div><div class='col-md-6'>" : "" )+'<div class="checkbox"><label><input type="checkbox" name="expiration_field" value="'+tests[i]+'">'+getTestName(tests[i])+'</label></div>';
					}
					$("#expirationTypeSection").html(testStr);
					$("input[name='expiration_field']").first().prop("checked", true);
					$("#launchLog").click(function(){
						if($("input[name='expiration_field']:checked").length != 0 && $("input[name='expiration_type']:checked").length != 0){
							var fields = [];
							$("input[name='expiration_field']:checked").each(function(){
								fields.push($(this).val());
							})
							var myWindow = window.open("print.php?print_type=validity&validity_type="+$("input[name='expiration_type']:checked").val()+"&validity_fields="+JSON.stringify(fields), "_blank");
						}else{
							showNotification("error", "Please select an expiration type and an expiration field");
						}
					})
					$("#addUsername").keyup(function(){
						if($(this).val() != $.trim($(this).val()))
							$(this).val($.trim($(this).val()));
				        var that = this;
				        if($(this).val().length < 4){
				            $(this).removeClass("input-success").addClass("input-error");
				        }else{
				           $.ajax({
				                type: "GET",
				                data: {username: $(this).val()},
				                url: "assets/php/check_username.php",
				                success: function(result){
				                    if(result == "not taken"){
				                        $(that).removeClass("input-error").addClass("input-success");
				                        $("#usernameTaken").text("");
				                    }else{
				                        $(that).removeClass("input-success").addClass("input-error");
				                        $("#usernameTaken").html(" <strong>This username is taken</strong>");
				                    }
				                }
				            }) 
				        }
					})
					$("#submitPilot").click(function(e){
						e.stopPropagation();
						var fname = $(".addPilot #fname").val().trim();
						var lname = $(".addPilot #lname").val().trim();
						var username = $("#addUsername").val();
						var validUsername = $("#addUsername").hasClass("input-success")
						var email = $(".addPilot #email").val().trim();
						var phone = $(".addPilot #phone").val();
						var nationality = $(".addPilot #nationality").val();
						var ang_license = $(".addPilot #ang_license").val();
						var position = $(".addPilot #comandante").val();
						var for_license = $(".addPilot #for_license").val();
						if(fname != "" && lname != "" && username != "" && validUsername && comandante != ""){
							var str = "";
							$(".addPilot input:not(.on-date, .off-date)").each(function(){
								if($(this).attr("id") == undefined){
								}
								if($(this).val().trim() != ""){
									str+=$(this).attr("id")+": '"+$(this).val().trim()+"', ";
								}else{
									str+=$(this).attr("id")+": null, ";
								}
							});

							str+="comandante: '"+$(".addPilot #comandante").val()+"'";
							
							var inputs = eval("({"+str+"})");
							inputs["training"] = $("input[name='training']:checked").val();
							inputs["admin"] = parseInt($("#adminType").val());
							// var onOffArray = [];
							// for(var i = 0; i < $(".addPilot .on-date").length; i++){
							// 	var on = $($(".addPilot .on-date")[i]).val();
							// 	var off = $($(".addPilot .off-date")[i]).val();
							// 	if(on != "" && off != ""){
							// 		var temp ="({on: '"+on+"', off: '"+off+"'})";
							// 		onOffArray.push(eval(temp));
							// 	}
							// }
							// var selectedContracts = $("#pilotContracts select").val();
							// var contractStr = "";
							// if(selectedContracts != null){
							// 	for(var i = 0; i < selectedContracts.length; i++){
							// 		if(selectedContracts[i] != "none"){
							// 			contractStr += selectedContracts[i]+";";
							// 		}
							// 	}
							// }

							// var selectedCrafts = $("#pilotCrafts select").val();
							// var craftStr = "";
							// if(selectedCrafts != null){
							// 	for(var i = 0; i < selectedCrafts.length; i++){
							// 		if(selectedCrafts[i] != "none"){
							// 			craftStr += selectedCrafts[i]+";";
							// 		}
							// 	}
							// }

							$(".addPilot").modal("hide");
							//TODO ADD LOADING MODAL
							$.ajax({
								type: "POST",
								url: "assets/php/create_new_pilot.php",
								data: {inputs: JSON.stringify(inputs), /*onOff: JSON.stringify(onOffArray), contracts: contractStr, crafts: craftStr*/},
								success: function(r){
									console.log(r);
									if(r.substring(0, 7) == "success"){
										//reset values
										$(".addPilot input[name!='training']").val("");
										$(".addPilot input[name='training'][value='0']").prop("checked", true);
										$("#pilotContracts select, #pilotCrafts select").val("none");
										$(".addPilot #comandante").val(1);
										$(".addedDate").remove();
										$("#addUsername").removeClass("input-success input-error");
										showNotification("success", "You have successfully added a pilot!");
										//append new pilot
										id = r.substring(8);
										$("#pilots_list").append("<div class='select' data-pk='"+id+"' data-name='"+inputs["fname"]+"' data-pos='"+inputs["comandante"]+"' data-id='"+(parseInt($("#pilots_list .select").last().attr("data-id"))+1)+"' data-onoff='0' data-contracts='"+/*contractStr*/""+"'><span class='pilotNumber'>"+($(".select").length+1)+".</span>"+inputs["lname"]+", "+inputs["fname"]+"</div>")
										$(".select").unbind("click").click(function(){
											if(!$(this).hasClass("selected")){
												$(".selected").removeClass("selected");
												$(this).addClass("selected");
												getPilot($(this).attr("data-pk"));
											}
										});
										$("#pilots_list .select[data-pk='"+id+"']").trigger("click");
									}else{
										showNotification("error", (r == "exists" ? "Pilot already exists" : "Adding the pilot failed. Please try again."));
									}
								}
							});
						}else{
							showNotification("error", "Please fill in all the required (*) information.");
						}
					})

				}
					$.ajax({
						type: "GET",
						url: "assets/php/get_all_pilots.php",
						data: {showNonPilots: $("#showNonPilots").val()},
						success: function(data){
							var res = JSON.parse(data);
							for(var i = 0; i < res["id"].length; i++){
								var Now = new Date().getTime();
								var OnDuty = false;
								//ON OFF
								var onOffAr = res["onOff"][i];
								for(var o = 0; o < onOffAr.length; o++){
									if(new Date(onOffAr[o]["on"]).getTime() <= Now && new Date(onOffAr[o]["off"]).getTime() >= Now){
										OnDuty = true;
									}
								}
								expireClass = "";
								if(!res["allValid"][i]){
									expireClass = "alert-danger";
								}else if(!res["monthValid"][i]){
									expireClass = "alert-warning";
								}
								$("#pilots_list").append("<div class='select "+expireClass+"' data-pk='"+res.id[i]+"' data-name='"+res["lname"][i]+"' data-pos='"+res["comandante"][i]+"' data-id='"+i+"' data-onoff='"+(OnDuty ? 1 : 0)+"' data-contracts='"+res.contracts[i]+"' data-crafts='"+res.crafts[i]+"'><span class='pilotNumber'>"+(i+1)+".</span>"+res.lname[i]+", "+res.fname[i]+"</div>");
							}

							$(".select").click(function(){
								$(".selected").removeClass("selected");
								$(this).addClass("selected");
								getPilot($(this).attr("data-pk"));
							})

							$("#sortBy").change(function(){
								switch($(this).val()){
									case "creation":
										sortByCreation("#pilots_list", "#pilots_list .select");
									break;
									case "name":
										sortByName("#pilots_list", "#pilots_list .select")
									break;
									case "position":
										sortByPosition("#pilots_list", "#pilots_list .select");
									break;
									case "duty":
										sortByOnOff("#pilots_list", "#pilots_list .select");
									break;
								}
								$(".select").each(function(i){
									$(this).find(".pilotNumber").text((i+1));
								});

								resetInfoPanel()

								$(".select").click(function(){
									if(!$(this).hasClass("selected")){
										$(".selected").removeClass("selected");
										$(this).addClass("selected");
										getPilot($(this).attr("data-pk"));
									}
								})
							});

							if(lastHeliSelected != ""&& $("#craftType option[value='"+lastHeliSelected+"']").length != 0){
								$("#craftType option[value='"+lastHeliSelected+"']").prop("selected", true);
								pilotsByCraft(lastHeliSelected);
							}

							$("#craftType").change(function(){
								var craft = $(this).val();
								if(craft != "all"){
									document.cookie = "lastHeliSelected="+$(this).val()+"; expires="+new Date(new Date().getTime() + (86400 * 1000 * 1000)).toString();
									$.ajax({
										url: "assets/php/change_default_craft.php",
										data: {craft: $(this).val()},
										type: "POST",
										success: function(result){
											pilotsByCraft(craft);
											resetInfoPanel();
										}
									});
								}else{
									pilotsByCraft(craft);
									resetInfoPanel();
								}
							});
						}
					});
					
					$("#showNonPilots").change(function(){
						resetInfoPanel()
						updatePilotList();
					})

					$('#search_pilot').keyup(function(e){
						var input = $(this).val().toLowerCase();
						$('#pilots_list').children().each(function(){
							if($(this).attr("data-crafts").indexOf($("#craftType").val()) != -1 || $("#craftType").val() == "all"){
								if($(this).text().toLowerCase().indexOf(input) >= 0 || $(this).attr("data-contracts").toLowerCase().indexOf(input) >= 0 || $(this).attr("data-crafts").toLowerCase().indexOf(input) >= 0){
									$(this).show();
								}else{
									$(this).hide();
								}
							}
						});
					});

					$("#printPilotBtn").click(function(){
						var id = $(this).attr("data-pk");
					})
			}
		});
			
});
//function for getting specific pilot and all of his data
function getPilot(id){
	$.ajax({
		type: "POST",
		url: "assets/php/get_pilot.php",
		data: {id: id},
		success: function(data){
			var res = JSON.parse(data);
			res["admin"][0] = parseInt(res["admin"][0]);
			var i = 0;
			var pilotIsMe = (res.id[0] == myPilotID);
			var body = "";
			body += "<div class='inner-bottom-sm'><div class='errors'></div>"
			$("#pilot_info_section").children("h2").html(res["fname"][i]+" "+res["lname"][i]+" <button class='btn btn-primary pull-right' id='printPilotBtn' data-toggle='modal' data-target='#printPilotModal'>Export XLSX</button>");
			$("#printPilotBtn").show().attr("data-pk", res.id[i]);

			body += "<div class='col-md-11 center-block outer-bottom-xs'><h4 class='page-header'>Personal Information</h4>";
			body += "<div class='col-md-6'><ul id='personalInfo'>";
			body += "<li>Name: <div id=\"name\"><span class='infoEdit' data-pk='"+res.id[i]+"' data-name='fname'>"+res["fname"]+"</span> <div style='margin-left: 0.5em; display: inline;'><span class='infoEdit' data-name='lname' data-pk='"+res.id[i]+"'>"+res["lname"]+"</span></div></div></li>";
			body += "<li>Username: <div id=\"username\"><span class='infoEdit' data-pk='"+res.id[i]+"' data-name='username'>"+res["username"][i]+"</span></div></li>";
			body += "<li>Nationality: <div id=\"nationality\"><span class='infoEdit' data-pk='"+res.id[i]+"' data-name='nationality'>"+res["nationality"]+"</span></div></li>";
			if(res["comandante"] == 1){
				pos = "Comandante";
			}else{
				pos = "Piloto";
			}
			if([0,1,2,3,8].indexOf(res["admin"][i]) != -1){
				body += "<li>Current Position: <div id=\"pos\"><span class='infoEdit' data-type='select' data-pk='"+res.id[i]+"' data-name='comandante'>"+pos+"<span></div></li>";
				body += "<li>"+accountNationality+" License: <div id=\"angLic\"><span class='infoEdit' data-pk='"+res.id[i]+"' data-name='ang_license'>"+res["ang_license"]+"</span></div></li>";
				body += "<li>Foreign License: <div id=\"forLic\"><span class='infoEdit' data-pk='"+res.id[i]+"' data-name='for_license'>"+res["for_license"]+"</span></div></li>";
			}
			body += "<li>E-mail: <div id=\"persEmail\"><span class='infoEdit' data-pk='"+res.id[i]+"' data-name='email'>"+res["email"]+"</span></div></li>";
			body += "<li>Phone: <div id=\"persPhone\"><span class='infoEdit' data-pk='"+res.id[i]+"' data-name='phone' data-placeholder='1 555-555-5555'>"+res["phone"]+"</span></div></li>";
			body += "<li>Secondary Phone: <div id=\"persPhoneTwo\"><span class='infoEdit' data-pk='"+res.id[i]+"' data-name='phonetwo' data-placeholder='1 555-555-5555'>"+res["phonetwo"]+"</span></div></li>";
			body += "</ul>";
			//permissions
			if([8,7,6,3].indexOf(admin) != -1){
				body += "<div class='col-md-12 outer-bottom-xs'><h4>Permission Settings <a class='pull-right' id='adminTypesInfo'>Learn More</a></h4>";
				body += "<div class='col-md-12 no-padding no-margin'><select class='form-control' id='adminTypeSelect'><option value='8'>Administrator - Pilot</option><option value='7'>Administrator</option><option value='6'>Manager</option><option value='5'>Training Manager</option><option value='4'>Schedule Manager</option><option value='3'>Manager - Pilot</option><option value='2'>Training Manager - Pilot</option><option value='1'>Schedule Manager - Pilot</option><option value='0'>Pilot</option></select></div><div class='col-md-4 col-md-offset-1 no-padding'><button class='btn btn-primary' id='adminTypeSave' style='display: none;' data-pk='"+res.id[i]+"'>Save</button></div></div>";
			}
			body += "</div>";

			body +="<div class='col-md-6 outer-bottom-xs'><div class='lbl no-margin'>Profile Picture</div><div id=\"profile-picture-container\">";
			body += "<img src=\"uploads/pictures/"+(res.profile_picture[i] != null ? res.profile_picture[i] : "default_picture.jpg")+"\" id=\"profile-picture\" width=\"100%\"/>";
			if(admin > 0 || pilotIsMe)
				body += "<div id=\"profile-picture-overlay\" data-toggle=\"modal\" data-target=\"#change-profile-picture\"><div class=\"fa fa-3x fa-pencil\"></div></div>";
			body += "</div></div>";
			
			if([0,1,2,3,8].indexOf(res["admin"][i]) != -1){
				var Now = new Date().getTime();
				var OnDuty = false;
				//ON OFF
				var onOffAr = res["onOff"][i];
				var onTbl = "<table class='table table-condensed table-bordered' style='-webkit-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); -moz-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); box-shadow: 0px 0px 0px 0px rgba(0,0,0,0);'><thead><th>On Duty</th><th>Off Duty</th><th>Scheduled</th></thead>";
				for(var o = 0; o < onOffAr.length; o++){
					if(onOffAr[o]["inSched"]){
						var inSchedStr = "<td class='active'>True</td><th class=\"text-center\"><div class=\"btn btn-sm btn-warning removeAvailDates\" data-on='1' data-id='"+res["id"][i]+"'>-</div></th></tr>";
						var oclss = "dateEditable";
					}else{
						var inSchedStr = "<td class='info'>False</td><th class=\"text-center\"><div class=\"btn btn-sm btn-warning removeAvailDates\" data-on='0' data-id='"+res["id"][i]+"'>-</div></th></tr>";
						var oclss = "dateEditable";
					}
					onTbl += "<tr><td><span class='on-date "+oclss+"' data-name='on' data-pk='"+res["id"][i]+"'>"+onOffAr[o]["on"]+"</span></td><td><span class='off-date "+oclss+"' data-name='off' data-pk='"+res["id"][i]+"'>"+onOffAr[o]["off"]+"</span></td>"+inSchedStr;
				}
				onTbl += "<tr id='addDateRow'><td><input type='text' placeholder='YYYY-mm-dd' class='on-date form-control' /></td><td><input type='text'  placeholder='YYYY-mm-dd' class='off-date form-control' /></td><td></td><th class=\"text-center\"><div class=\"btn btn-sm btn-primary addOnOffDate\" data-id='"+res["id"][i]+"'>+</div></th></tr>";
				onTbl += "</table><p class='outer-bottom-sm'><strong>NOTE: Deleting or modifying the availability of a pilot who is in the schedule will result in the truncation of the schedule to that date.</strong></p>";
				body += onTbl;

				//VALIDITY TABLE
				var array = [[]];
				var counter = 0;
				var c = 0;
				for(var key in res["validity"][i]){
					if(key != "id"){
						array[c].push({header: key, val: res["validity"][i][key]})
						counter++;
						if(counter != 0 && counter%7 == 0){
							array.push([]);
							c++;
						}
					}	
				}

				var str = "";
				var headerStr, bodyStr;
				for(var k = 0; k < array.length; k++){
					headerStr = "<thead>";
					bodyStr = "<tr>";
					for(var j = 0; j < array[k].length; j++){
						headerStr += "<th>"+getTestName(array[k][j].header)+"</th>";
						if(array[k][j].val != null){
							var cur = new Date();
							var vald = new Date(array[k][j].val+"T12:00:00");
							var cls = "tint-bg";
							var dateStr = vald.getFullYear()+"-"+returnAbrvMonth(vald.getMonth())+"-"+doubleDigit(vald.getDate());
							var content = "<span class='valText'>Valid</span><br/><span class='validityDate' data-type='date' data-name='"+array[k][j].header+"' data-pk='"+res["id"][i]+"'>"+dateStr+"</span>";
							if(vald.getTime() < cur.getTime()){
								cls = "alert alert-danger"
								content = "<span class='valText'>EXPIRED</span><br/><span class='validityDate' data-type='date' data-name='"+array[k][j].header+"' data-pk='"+res["id"][i]+"'>"+dateStr+"</span>";
							}else if(vald.getTime() - cur.getTime() <= (4*7*24*60*60*1000)){
								cls = "alert alert-warning";
								content = "<span class='valText'>Expires Soon</span><br/><span class='validityDate' data-type='date' data-name='"+array[k][j].header+"' data-pk='"+res["id"][i]+"'>"+dateStr+"</span>";
							}
						}else{
							var cls = "alert-null";
							var content = "<span class='valText'></span><br/><span class='validityDate' data-type='date' data-name='"+array[k][j].header+"' data-pk='"+res["id"][i]+"'>Select Expiry Date</span>";
						}													
						bodyStr += "<td class='"+cls+"'>"+content+"</td>";
					}
					headerStr += "</thead>";
					bodyStr += "</tr>";
					str += "<div class='col-md-12'><table class='val_table'>"+headerStr+bodyStr+"</table></div>";
				}
				body += str;

				// console.log(CONTRACTS, res["contracts"][i]);
				if(res["contracts"][i] != null){
					var pilContracts = res["contracts"][i].split(";");
				}else{
					var pilContracts = [];
				}
				
				if(res["crafts"][i] != null){
					var pilCrafts = res["crafts"][i].split(";");
				}else{
					var pilCrafts = [];
				}
			}
			if([0,1,2,3,8].indexOf(res["admin"][i]) != -1 && (admin > 0 || pilotIsMe)){
				// var contractStr = "<div class='checkbox'><label><input type='checkbox' name='contract-checkbox' id='noContractCheck' value='none'>None</label></div>";
				// for(key in CONTRACTS){
				// 	if(pilContracts.indexOf(key) != -1){
				// 		contractStr += "<div class='checkbox'><label><input type='checkbox' name='contract-checkbox' value='"+key+"' checked>"+key+"</label></div>";
				// 	}else{
				// 		contractStr += "<div class='checkbox'><label><input type='checkbox' name='contract-checkbox' value='"+key+"'>"+key+"</label></div>";
				// 	}
				// }
				var contractStr = "<div class='col-md-3 no-padding no-margin'><div class='check-box contractChecks' data-value='none' id='AllContractCheck'>All <div class='check-mark'><div class='fa fa-check-square-o'></div></div></div></div>";
				for(key in CONTRACTS){
					if(pilContracts.indexOf(key) != -1){
						contractStr += "<div class='col-md-3 no-padding no-margin'><div class='check-box selected contractChecks' data-value='"+key+"'>"+key+"<div class='check-mark'><div class='fa fa-check-square-o'></div></div></div></div>";
					}else{
						contractStr += "<div class='col-md-3 no-padding no-margin'><div class='check-box contractChecks' data-value='"+key+"'>"+key+"<div class='check-mark'><div class='fa fa-check-square-o'></div></div></div></div>";
					}
				}

				body += "<div class='col-md-9 center-block'><h3 class='page-header'>Pilot Contracts"+(admin > 0 ? "<button class='btn btn-primary pull-right saveContractList' data-pk='"+res["id"][i]+"'>Save</button>" : "")+"</h3><div class='col-md-12 outer-bottom-xxs'>"+contractStr+"</div></div>"
				
				// var craftStr = "<div class='checkbox'><label><input type='checkbox' name='craft-checkbox' id='noCraftCheck' value='none'>None</label></div>";
				// for(cr = 0; cr < CRAFTS.length; cr++){
				// 	if(pilCrafts.indexOf(CRAFTS[cr]) != -1){
				// 		craftStr += "<div class='checkbox'><label><input type='checkbox' name='craft-checkbox' value='"+CRAFTS[cr]+"' checked>"+CRAFTS[cr]+"</label></div>";
				// 	}else{
				// 		craftStr += "<div class='checkbox'><label><input type='checkbox' name='craft-checkbox' value='"+CRAFTS[cr]+"'>"+CRAFTS[cr]+"</label></div>";
				// 	}
				// }
				var craftStr = "<div class='col-md-3 no-padding no-margin'><div class='check-box craftChecks' id='AllCraftCheck' data-value='none'>All <div class='check-mark'><div class='fa fa-check-square-o'></div></div></div></div>";
				for(cr = 0; cr < CRAFTS.length; cr++){
					if(pilCrafts.indexOf(CRAFTS[cr]) != -1){
						craftStr += "<div class='col-md-3 no-padding no-margin'><div class='check-box selected craftChecks' data-value='"+CRAFTS[cr]+"'>"+CRAFTS[cr]+"<div class='check-mark'><div class='fa fa-check-square-o'></div></div></div></div>";
					}else{
						craftStr += "<div class='col-md-3 no-padding no-margin'><div class='check-box craftChecks' data-value='"+CRAFTS[cr]+"'>"+CRAFTS[cr]+"<div class='check-mark'><div class='fa fa-check-square-o'></div></div></div></div>";
					}
				}

				body += "<div class='col-md-9 center-block'><h3 class='page-header'>Pilot Crafts"+(admin > 0 ? "<button class='btn btn-primary pull-right saveCraftList' data-pk='"+res["id"][i]+"'>Save</button>" : "")+"</h3>"+craftStr+"</div>"

				radioOptions = '<div class="radio"><label><input type="radio" name="training" value="0" '+(parseInt(res["training"][i]) == 0 ? "checked" : "")+'>None</label></div><div class="radio"><label><input type="radio" name="training" value="2" '+(parseInt(res["training"][i]) == 2 ? "checked" : "")+'>TRE</label></div><div class="radio"><label><input type="radio" name="training" value="1" '+(parseInt(res["training"][i]) == 1 ? "checked" : "")+'>TRI</label></div>'
				body += "<div class='col-md-6 center-block'><h3 class='page-header'>Trainer Type"+(admin > 0 ? "<button class='btn btn-primary pull-right saveTrainingType' data-pk='"+res["id"][i]+"'>Save</button>" : "" )+"</h3>"+radioOptions+"</div>"
				
				if([8,7,6,3].indexOf(admin) != -1 && !pilotIsMe)
					body += "<div class='col-md-3 center-block outer-top-xs'><button class='form-control btn btn-danger deletePilotBtn' data-pk='"+res["id"][i]+"'>Delete Pilot</button></div>";
			}

			body+="</div>";
			$("#pilot_info").html("<div class='row outer-left-xxs outer-right-xxs'>"+body+"</div>")

			$("#adminTypeSelect").val(res["admin"][i]);

			$("#personalInfo li").children().css("display", "inline");


			if(admin > 0 || pilotIsMe){
				$(".infoEdit").editable({
					url: "assets/php/update_pilots_info.php",
					ajaxOptions:{
						type: "POST",
						cache: false
					},
					source: {"1": "Comandante", "2": "Piloto"},
					success: function(result, newValue){
						if($(this).data("name") == "fname" || $(this).data("name") == "lname"){
							var pilotNumber = $(".selected span.pilotNumber").text(),
								firstName = ($(this).data("name") == "fname" ? newValue : $(".infoEdit[data-name='fname']").text()),
								lastName = ($(this).data("name") == "lname" ? newValue : $(".infoEdit[data-name='lname']").text());
							$("#pilots_list .selected").html("<span class='pilotNumber'>"+pilotNumber+"</span>"+lastName+", "+firstName);
							$("#pilots_list .selected").attr("data-name", lastName);
							$("#pilot_info_section").children("h2").text(firstName+" "+lastName);
						}else if($(this).data("name") == "comandante"){
							$("#pilots_list .selected").attr("data-pos", ($("#pos .infoEdit").text() == "comandante" ? 1 : 0));
						}
					}
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

				$(".val_table .validityDate").editable({
					url: "assets/php/update_validity.php",
					ajaxOptions: {
						type: "POST",
						cache: false
					},
					emptyclass: "",
					datepicker: {
						weekStart: 1
					},
					success: function(data){
						if(data == "null"){
							$(this).text("Select Expiry Date")
							$(this).parent().attr("class", "alert-null");
							$(this).siblings(".valText").text("");
							if($(".val_table .alert.alert-danger, .val_table .alert.alert-warning").length == 0){
								$(".select[data-pk='"+$(this).attr("data-pk")+"']").removeClass("alert-danger alert-warning")
							}else if($(".val_table .alert.alert-danger").length == 0 && $(".val_table .alert.alert-warning").length != 0){
								$(".select[data-pk='"+$(this).attr("data-pk")+"']").removeClass("alert-danger").addClass("alert-warning");
							}else if($(".val_table .alert.alert-danger").length != 0 && $(".val_table .alert.alert-warning").length == 0){
								$(".select[data-pk='"+$(this).attr("data-pk")+"']").removeClass("alert-warning").addClass("alert-danger");
							}
						}else{
							data = data.substring(1, (data.length-1));
							var checkD = new Date(data+"T12:00:00");
							var valCur = new Date();
							$(this).text(data);
							if(checkD.getTime() < valCur.getTime()){
								$(this).parent().attr("class", "alert alert-danger");
								$(this).siblings(".valText").text("EXPIRED");
								$(".select[data-pk='"+$(this).attr("data-pk")+"']").removeClass("alert-warning").addClass("alert-danger");
							}else if((checkD.getTime()-valCur.getTime()) <= (4*7*24*60*60*1000)){
								$(this).parent().attr("class", "alert alert-warning");
								$(this).siblings(".valText").text("Expires Soon");
								if($(".val_table .alert.alert-danger").length == 0){
									$(".select[data-pk='"+$(this).attr("data-pk")+"']").removeClass("alert-danger").addClass("alert-warning");
								}
							}else{
								$(this).parent().attr("class", "tint-bg");
								$(this).siblings(".valText").text("Valid");
								if($(".val_table .alert.alert-danger, .val_table .alert.alert-warning").length == 0){
									$(".select[data-pk='"+$(this).attr("data-pk")+"']").removeClass("alert-danger alert-warning")
								}
							}
						}
					},
					validate: function(value){
						// entered = moment(value);
						// today = moment().format("YYYY-MM-DD");
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

				$("#picture-pilot-id").val(res.id[i]);
			}


			if(admin > 0){
				$(".deletePilotBtn").click(function(){
					$.ajax({
						type: "POST",
						url: "assets/php/delete_pilot.php",
						data: {id: $(this).data("pk")},
						success: function(result){
							if(result == "success"){
								$(".selected").remove();
								$("#pilot_info").empty()
								$("#pilot_info_section").children("h2").text("Pilot Information");
								showNotification("success", "Successfully deleted pilot!");
							}else{
								showNotification("error", "Deleting the pilot was unsuccessful.");
							}
						}
					})
				});
				
				// $("#noContractCheck").click(function(){
				// 	if($(this).prop("checked")){
				// 		$("input[name='contract-checkbox']:checked:not(#noContractCheck)").prop("checked", false);
				// 	}
				// })
				// $("input[name='contract-checkbox']").click(function(){
				// 	if($(this).attr("id") != "noContractCheck"){
				// 		$("#noContractCheck").prop("checked", false);
				// 	}
				// })
				$(".contractChecks").click(function(){
					$(this).toggleClass("selected");
					if($(this).attr("id") == "AllContractCheck"){
						if($(this).hasClass("selected")){
							$(".contractChecks:not(#AllContractCheck)").addClass("selected");	
						}else{
							$(".contractChecks:not(#AllContractCheck)").removeClass("selected");
						}
					}else{
						$("#AllContractCheck").removeClass("selected");
					}
				})
				$(".craftChecks").click(function(){
					$(this).toggleClass("selected");
					if($(this).attr("id") == "AllCraftCheck"){
						if($(this).hasClass("selected")){
							$(".craftChecks:not(#AllCraftCheck)").addClass("selected");	
						}else{
							$(".craftChecks:not(#AllCraftCheck)").removeClass("selected");
						}
					}else{
						$("#AllCraftCheck").removeClass("selected");
					}
				})
				// $("#noCraftCheck").click(function(){
				// 	if($(this).prop("checked")){
				// 		$("input[name='craft-checkbox']:checked:not(#noCraftCheck)").prop("checked", false);
				// 	}
				// })
				// $("input[name='craft-checkbox']").click(function(){
				// 	if($(this).attr("id") != "noCraftCheck"){
				// 		$("#noCraftCheck").prop("checked", false);
				// 	}
				// })

				$(".saveContractList").click(function(){
					var conList = [];
					// $("input[name='contract-checkbox']:checked").each(function(){
					// 	if($(this).val() != "none"){
					// 		conList.push($(this).val());
					// 	}
					// })
					$(".contractChecks.selected").each(function(){
						if($(this).data("value") != "none"){
							conList.push($(this).data("value"));
						}
					})
					var conStr = "";
					if(conList != null){
						for(var i = 0; i < conList.length; i++){
							if(conList[i] != "none"){
								conStr += conList[i]+";";
							}
						}
					}
					$.ajax({
						type: "POST",
						url: "assets/php/update_pilot_contracts.php",
						data: {uid: $(this).data("pk"), contracts: conStr},
						success: function(result){
							if(result == "success"){
								$(".selected").attr("data-contracts", conStr);
								showNotification("success", "Success!");
							}else{
								showNotification("error", "Update was unsuccessful.");
							}
						}
					})
				})

				$(".saveCraftList").click(function(){
					var craftList = [];
					// $("input[name='craft-checkbox']:checked").each(function(){
					// 	if($(this).val() != "none"){
					// 		craftList.push($(this).val());
					// 	}
					// })
					$(".craftChecks.selected").each(function(){
						if($(this).data("value") != "none"){
							craftList.push($(this).data("value"));
						}
					})
					var craftStr = "";
					if(craftList != null){
						for(var i = 0; i < craftList.length; i++){
							if(craftList[i] != "none"){
								craftStr += craftList[i]+";";
							}
						}
					}
					$.ajax({
						type: "POST",
						url: "assets/php/update_pilot_crafts.php",
						data: {uid: $(this).data("pk"), crafts: craftStr},
						success: function(result){
							if(result == "success"){
								$(".selected").attr("data-crafts", craftStr);
								showNotification("success", "Success!");
							}else{
								showNotification("error", "Update was unsuccessful.");
							}
						}
					});
				})

				$(".saveTrainingType").click(function(){
					var training = $("#pilot_info input[name='training']:checked").val();
					$.ajax({
						type: "POST",
						url: "assets/php/update_pilot.php",
						data: {pk: $(this).data("pk"), value: training, name: "training"},
						success: function(result){
							if(result == "success"){
								showNotification("success", "You successfully updated the pilot's training type.");
							}else{
								showNotification("error", "Update was unsuccessful.");
							}
						}
					});
				})
				$(".dateEditable").css("cursor", "pointer");
				$(".dateEditable").editable({
					type: "date",
					url: "assets/php/update_on_off.php",
					datepicker: {
						weekStart: 1
					},
					validate: function(value){
						// var that = this;
						// var input = new Date(value);
						// console.log(input);
						// var already;
						// $(this).parents("tbody").children().each(function(){
						// 	if($(this) != $(that).parent().parent()){
						// 		console.log(input);
						// 		var tempOn = new Date($(this).find(".on-date").text()+"T12:00:00");
						// 		var tempOff = new Date($(this).find(".off-date").text()+"T12:00:00");
						// 		console.log(tempOn, tempOff);
						// 		if(dates.inRange(input, tempOn, tempOff)){
						// 			already = true;
						// 		}
						// 	}
						// });
						// if(already){
						// 	$(this).focus();
						// 	$(this).parents(".more_info").find(".errors").prepend("<div class=\"col-md-12 center-block\" style=\"margin-left: auto; margin-right: auto;\">"+
						// 			"<div class=\"alert_sec\"><div class=\"alert alert-danger alert-dismissible\" role=\"alert\">"+
	  			// 					"<button type=\"button\" class=\"close\" data-dismiss=\"alert\"><span aria-hidden=\"true\">&times;</span><span class=\"sr-only\">Close</span></button>"+
	  			// 					"<strong>Date ranges may not overlap. Your date is already scheduled.</strong></div></div></div>")
						// 	return "Date ranges may not overlap. Your date is already scheduled.";
						// }
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
								$(that).parent().parent().remove();
							}else{
								showNotification("error", result);
							}
						}
					})
				});

				$("#adminTypesInfo").on("click", function(){
					$("#adminTypesModal").modal("show");
				}).css("cursor", "pointer");
				
				$("#adminTypeSelect").change(function(){
					$("#adminTypeSave").show();
				})
				$("#adminTypeSave").click(function(){
					$.ajax({
						type: "POST",
						url: "assets/php/update_admin_type.php",
						data: {value: $("#adminTypeSelect").val(), pilot: $(this).attr("data-pk")},
						success: function(result){
							console.log(result);
							if(result == "success"){
								$("#adminTypeSave").hide();
								showNotification("success", "You successfully changed the permission type");
								if(pilotIsMe){
									setTimeout(function(){
										window.location.reload();
									}, 1000);
								}else{
									$("#pilots_list .selected").trigger("click");
								}
							}else{
								showNotification("error", "Updating the permission type was unsuccessful.");
							}								
						}
					})
				})
			}
		}
	})
}

function sortByName(parent, selector){
	var c = $(selector);
	c.sort(function(a,b){
		if($(a).data("name") > $(b).data("name")){
			return 1;
		}else if($(a).data("name") < $(b).data("name")){
			return -1;
		}
		return 0;
	});
	$(parent).empty();
	$(parent).html(c);
	$(parent).children().css("border-top", "").removeClass("separator position onOff onOffFirst positionFirst");

	$(".select").unbind("click").click(function(){
		$(".selected").removeClass("selected");
		$(this).addClass("selected");
		getPilot($(this).attr("data-pk"));
	})
}
function sortByPosition(parent, selector){
	var c = $(selector);
	c.sort(function(a,b){
		if($(a).data("pos") > $(b).data("pos")){
			return -1;
		}else if($(a).data("pos") < $(b).data("pos")){
			return 1;
		}
		return 0;	
	});
	$(parent).empty();
	$(parent).html(c);
	var current = "";
	$(parent).children().css("border-top", "").removeClass("separator position onOff onOffFirst positionFirst");
	$(parent).children().each(function(){
		if($(this).is(":visible")){
			if($(this).data("pos") != current){
				if(current != ""){
					$(this).css("border-top", "3px solid #333");
					$(this).prevAll(":visible:first").addClass("position separator");
				}else{
					$(this).addClass("positionFirst");
				}
				current = $(this).data("pos");
			}
		}
	})

	$(".select").unbind("click").click(function(){
		$(".selected").removeClass("selected");
		$(this).addClass("selected");
		getPilot($(this).attr("data-pk"));
	})
}
function sortByOnOff(parent, selector){
	var c = $(selector);
	c.sort(function(a,b){
		if($(a).data("onoff") > $(b).data("onoff")){
			return -1;
		}else if($(a).data("onoff") < $(b).data("onoff")){
			return 1;
		}
		return 0;	
	});
	$(parent).empty();
	$(parent).html(c);
	var current = "";
	$(parent).children().css("border-top", "").removeClass("separator position onOff onOffFirst positionFirst");
	$(parent).children().each(function(){
		if($(this).is(":visible")){
			if($(this).data("onoff") != current){
				if(current != ""){
					$(this).css("border-top", "3px solid #333");
					$(this).prevAll(":visible:first").addClass("onOff separator");
				}else{
					$(this).addClass("onOffFirst");
				}
				current = $(this).data("onoff");
			}
		}
	})

	$(".select").unbind("click").click(function(){
		$(".selected").removeClass("selected");
		$(this).addClass("selected");
		getPilot($(this).attr("data-pk"));
	})
}
function sortByCreation(parent, selector){
	var c = $(selector);
	c.sort(function(a,b){
		if($(a).data("id") > $(b).data("id")){
			return 1;
		}else if($(a).data("id") < $(b).data("id")){
			return -1;
		}
		return 0;	
	});
	$(parent).empty();
	$(parent).html(c);
	$(parent).children().css("border-top", "").removeClass("separator position onOff onOffFirst positionFirst");;

	$(".select").unbind("click").click(function(){
		$(".selected").removeClass("selected");
		$(this).addClass("selected");
		getPilot($(this).attr("data-pk"));
	})
}
function applyBackground(element){
	var bgs = "dark-bg tint-bg blue-bg";
	element.each(function(i, e){
		$(e).find(".pilot_head").removeClass(bgs);
		$(e).find(".pilot_head").addClass(returnBackground(i));
	})
}

function pilotsByCraft(craft){
	var i = 1;
	$(".select").each(function(){
		if(craft != "all" && $(this).attr("data-crafts").indexOf(craft) == -1){
			$(this).hide();
		}else{
			$(this).find(".pilotNumber").text(i);
			i++
			$(this).show();
		}
	})
	switch($("#sortBy").val()){
		case "creation":
			sortByCreation("#pilots_list", "#pilots_list .select");
		break;
		case "name":
			sortByName("#pilots_list", "#pilots_list .select")
		break;
		case "position":
			sortByPosition("#pilots_list", "#pilots_list .select");
		break;
		case "duty":
			sortByOnOff("#pilots_list", "#pilots_list .select");
		break;
	}
}

function resetInfoPanel(){
	//reset the selected pilot
	$(".selected").removeClass("selected");
	$("#pilot_info_section>h2").text("Pilot Information");
	$("#pilot_info").empty();
	$("#printPilotBtn").remove();
}

function updatePilotList(){
	$.ajax({
		type: "GET",
		url: "assets/php/get_all_pilots.php",
		data: {showNonPilots: $("#showNonPilots").val()},
		success: function(data){
			console.log(data);
			$("#pilots_list").empty();
			var res = JSON.parse(data);
			for(var i = 0; i < res["id"].length; i++){
				var Now = new Date().getTime();
				var OnDuty = false;
				//ON OFF
				var onOffAr = res["onOff"][i];
				for(var o = 0; o < onOffAr.length; o++){
					if(new Date(onOffAr[o]["on"]).getTime() <= Now && new Date(onOffAr[o]["off"]).getTime() >= Now){
						OnDuty = true;
					}
				}
				expireClass = "";
				if(!res["allValid"][i]){
					expireClass = "alert-danger";
				}else if(!res["monthValid"][i]){
					expireClass = "alert-warning";
				}
				$("#pilots_list").append("<div class='select "+expireClass+"' data-pk='"+res.id[i]+"' data-name='"+res["lname"][i]+"' data-pos='"+res["comandante"][i]+"' data-id='"+i+"' data-onoff='"+(OnDuty ? 1 : 0)+"' data-contracts='"+res.contracts[i]+"' data-crafts='"+res.crafts[i]+"'><span class='pilotNumber'>"+(i+1)+".</span>"+res.lname[i]+", "+res.fname[i]+"</div>");
			}

			pilotsByCraft($("#craftType").val());
		}
	});
}