function returnBackground(i){
	var bg = ["dark-bg", "tint-bg", "", "blue-bg"];
	return(bg[(i%4)]);
}
var CRAFTS,
CONTRACTS,
usedAr = [],
PILOTS;
$(window).resize(function(){
	$("#craft_info_section").width($(".craftTypes").width()-330+"px");
})
$(document).ready(function(){
	$(window).trigger("resize");
	$("#search_crafts").keyup(function(){
		var val = $(this).val().toUpperCase();
		$(".craft-name").each(function(){
			var craftName = $(this).text().toUpperCase();
			var className = $(this).parent().next().children(".class-name").text().toUpperCase();
			if(className.indexOf(val) != -1 || craftName.indexOf(val) != -1){
				$(this).parent().parent().show();
			}else{
				$(this).parent().parent().hide();
			}
		});
	});

	$(".sidebar-list a[href='crafts.php'] li").addClass("active");
	$.ajax({
		type: "POST",
		url: "assets/php/checkAdmin.php",
		success: function(data){
			console.log(data);
			if(data != "false"){
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
				$.ajax({
					type: "GET",
					url: "assets/php/get_all_crafts.php",
					success: function(result){
						if(result != "false"){
							var res = JSON.parse(result);
							var crafts = [];
							for(var i = 0; i < res.length; i++){
								if(crafts.indexOf(res[i].craft) == -1){
									crafts[res[i].craft] = [];
									for(var j = 0; j < res.length; j++){
										if(res[j].craft == res[i].craft){
											var tempObj = {"class": res[j].class, "tod": res[j].tod, "craftid": res[j].id, "alive": res[j].alive};
											crafts[res[i].craft].push(tempObj)
										}	
									}
								}
							}
							CRAFTS = crafts;
							var craftTable = "<table class='table table-condensed table-bordered craft_table' style='-webkit-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); -moz-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); box-shadow: 0px 0px 0px 0px rgba(0,0,0,0);'>"+
											"<thead><th>Craft</th><th>Registration</th><th>Time Of Day</th><th>In Service</th></thead><tbody>";
							for(key in crafts){
								for(var i = 0; i < crafts[key].length; i++){
									craftTable += "<tr><td><span class='craft-name'>"+key+"</span></td><td><span class='class-name'>"+crafts[key][i].class+"</span></td><td><span class='craft-tod' data-pk='"+crafts[key][i].craftid+"'>"+crafts[key][i].tod.capitalize()+"</span></td><td><span class='craft-alive' data-pk='"+crafts[key][i].craftid+"'>"+(crafts[key][i].alive == 1 ? "True" : "False")+"</span></td><th><button class='btn btn-warning fa fa-minus removeCraftBtn' data-pk='"+crafts[key][i].craftid+"' data-craft='"+key+"'></button></tr>";
								}
							}
							craftTable += "<tr><td><input class='form-control' type='text' placeholder='Craft' id='addCraftName'></td><td><input class='form-control' type='text' placeholder='Registration' id='addCraftClass'></td><td><select class='form-control' id='addCraftTOD'><option value='day'>Day</option><option value='night'>Night</option></select></td><td><select class='form-control' id='addCraftAlive'><option value='1'>True</option><option value='0'>False</option></select></td><th><button class='btn btn-success fa fa-plus' id='addCraftBtn'></button></tr>"+
										"</tbody></table>"
							$("#crafts").html(craftTable);


							//functions for crafts
							$(".craft-tod").editable({
								type: "select",
								source: {"day": "Day", "night": "Night"},
								url: "assets/php/update_craft_tod.php",
								name: "craft",
								ajaxOptions: {
									type: "POST",
									cache: false
								},
								success: function(response, newValue){
									console.log(response);
								}
							})
							$(".craft-alive").editable({
								type: "select",
								source: {"0": "False", "1": "True"},
								url: "assets/php/update_craft_status.php",
								name: "craft",
								ajaxOptions: {
									type: "POST",
									cache: false
								},
								success: function(response, newValue){
									console.log(response);
								}
							});
							$(".removeCraftBtn").click(function(){
								var that = this;
								$.ajax({
									type: "POST",
									url: "assets/php/remove_craft.php",
									data: {craft: $(this).data("pk")},
									success: function(result){
										if(result == "success"){
											var craft = $(that).data("craft");
											$(that).parent().parent().remove();
											checkIfLast(craft);
										}else{
											//error
										}
									}
								})
							});
							$("#addCraftBtn").click(function(){
								var that = this;
								if($("#addCraftName").val() != ""){
									if($("#addCraftClass").val() != ""){
										var craft = $("#addCraftName").val();
										var cclass = $("#addCraftClass").val();
										var timeOfDay = $("#addCraftTOD").val();
										var alive = $("#addCraftAlive").val();
										$.ajax({
											type: "POST",
											url: "assets/php/add_craft.php",
											data: {craft: craft, class: cclass, tod: timeOfDay, alive: alive},
											success: function(result){
												if(result.substring(0,7) == "success"){
													id = parseInt(result.substring(8));
													//if first, add to #craft_list for pilot editing
													checkIfFirst(craft);
													//add to dom
													$("<tr><td><span class='craft-name'>"+craft+"</span></td><td><span class='class-name'>"+cclass+"</span></td><td><span class='craft-tod' data-pk='"+id+"'>"+timeOfDay.capitalize()+"</span></td><td><span class='craft-alive' data-pk='"+id+"'>"+(alive ? "True" : "False")+"</span></td><th><button class='btn btn-warning fa fa-minus removeCraftBtn' data-pk='"+id+"' data-craft='"+craft+"'></button></tr>").insertBefore($(that).parent().parent())
													//set up remove button
													$(".removeCraftBtn").unbind("click").click(function(){
														var that = this;
														$.ajax({
															type: "POST",
															url: "assets/php/remove_craft.php",
															data: {craft: $(this).data("pk")},
															success: function(result){
																if(result == "success"){
																	var craft = $(that).data("craft");
																	$(that).parent().parent().remove();
																	checkIfLast(craft);
																}else{
																	//error
																}
															}
														})
													});
													//reset values
													$("#addCraftName").val("");
													$("#addCraftClass").val("");
													$("#addCraftAlive").val(1);
													//set up editable
													$(".craft-tod").editable({
														type: "select",
														source: {"day": "Day", "night": "Night"},
														url: "assets/php/update_craft_tod.php",
														name: "craft",
														ajaxOptions: {
															type: "POST",
															cache: false
														},
														success: function(result){
														}
													})
													$(".craft-alive").editable({
														type: "select",
														source: {"0": "False", "1": "True"},
														url: "assets/php/update_craft_status.php",
														name: "craft",
														ajaxOptions: {
															type: "POST",
															cache: false
														},
														success: function(result){
															console.log(result);
														}
													});
												}else{
													//error
												}
											}
										});
									}else{
										$("#addCraftClass").focus();
									}
								}else{
									$("#addCraftName").focus()
								}
							})
						}	
					}
				});
				$("#search_craft_type").keyup(function(){
					var val = $(this).val().toLowerCase();
					$("#craft_list .select").each(function(){
						if($(this).text().toLowerCase().indexOf(val) != -1){
							$(this).show()
						}else{
							$(this).hide();
						}
					})
				})
				$.ajax({
					type: "GET",
					url: "assets/php/get_all_pilots.php",
					success: function(result){
						if(result.charAt(0) == "{" || result.charAt(0) == "["){
							PILOTS = JSON.parse(result);
						}
					}
				})
				$.ajax({
					type: "GET",
					url: "assets/php/get_all_crafts.php",
					data: {distinct: true},
					success: function(result){
						if(result.charAt(0) == "{" || result.charAt(0) == "["){
							var res = JSON.parse(result);
							for(i = 0; i < res.length; i++){
								$("#craft_list").append("<div class='select' data-pk='"+res[i]+"'>"+res[i]+"</div>")
							}
							$(".select").click(function(){
								$(".select").removeClass("selected");
								$(this).addClass("selected");
								getCraftPilots($(this).data("pk"));
							})
						}
					}
				})	
			}
		}
	});
});

function getCraftPilots(craftName){
	table = "<div class='col-md-7 center-block'><table class='table table-condensed table-bordered contract_table' style='-webkit-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); -moz-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); box-shadow: 0px 0px 0px 0px rgba(0,0,0,0);'><thead><th>Craft</th><th>Registration</th></thead><tbody>"
	options = "";
	for(var i = 0; i < PILOTS.id.length; i++){
		if(PILOTS.crafts[i] != null && PILOTS.crafts[i].indexOf(craftName) != -1)
			table+="<tr><td>"+PILOTS.lname[i]+", "+PILOTS.fname[i]+"</td><th><button class='btn btn-warning fa fa-minus removeContractItem' data-pk='"+PILOTS.id[i]+"' data-index='"+i+"' data-craft='"+craftName+"'></div></button></tr>";
		else
			options += "<option value='"+PILOTS.id[i]+"' data-index='"+i+"'>"+PILOTS.lname[i]+", "+PILOTS.fname[i]+"</option>";
	}

	table += "<tr id='addPilotsRow'><td colspan='2'><select multiple size='4' class='form-control insertPilot'>"+options+"</select>"

	table += "</td><th><button class='btn btn-success fa fa-plus insertContractBtn' data-pk='"+craftName+"'></button></th></tr></tbody></table></div>";

	$("#craft_info").html(table);

	setUpRemovePilot();
	$(".insertContractBtn").click(function(){
		if($(".insertPilot").val() != null){
			var craftName = $(this).data("pk")
			$.ajax({
				type: "POST",
				url: "assets/php/add_pilots_to_craft.php",
				data: {pilots: JSON.stringify($(".insertPilot").val()), craft: craftName},
				success: function(result){
					if(result == "success"){
						showNotification("success", "You have added the pilot to the craft");
					}else{
						showNotification("error", "Adding the pilot to the craft was unsuccessful");
					}
					table = "";
					$.each($(".insertPilot").val(), function(ind, val){
						i = $(".insertPilot option[value='"+val+"']").data("index");
						table += "<tr><td>"+PILOTS.lname[i]+", "+PILOTS.fname[i]+"</td><th><button class='btn btn-warning fa fa-minus removeContractItem' data-pk='"+PILOTS.id[i]+"' data-index='"+i+"' data-craft='"+craftName+"'></div></button></tr>"
						$(".insertPilot option[value='"+val+"']").remove();
					})
					$(table).insertBefore($("#addPilotsRow"));
					$(".insertPilots").val("");
					setUpRemovePilot();
				}
			})
		}
	})
}

function setUpRemovePilot(){
	$(".removeContractItem").unbind("click").click(function(){
		var row = $(this).parent().parent();
		var id = $(this).data("pk");
		var i = $(this).data("index");
		$.ajax({
			url: "assets/php/remove_pilot_from_craft.php",
			type: "POST",
			data: {pilot: id, craft: $(this).data("craft")},
			success: function(result){
				console.log(result);
				if(result == "success"){
					showNotification("success", "You have removed the pilot from the craft.");
					row.remove();
					$("select.insertPilot").append("<option value='"+PILOTS.id[i]+"' data-index='"+i+"'>"+PILOTS.lname[i]+", "+PILOTS.fname[i]+"</option>");
				}else{
					showNotification("error", "Removing the pilot from the craft was unsuccessful.");
				}					
			}
		})
	})
}

function checkIfLast(craft){
	var last = true;
	$(".craft_table tbody tr").each(function(){
		if($(this).find(".craft-name").text() == craft)
			last = false;
	})
	if(last){
		$("#craft_list .select[data-pk='"+craft+"']").remove();
		$("#craft_info").empty();
	}
}

function checkIfFirst(craft){
	var first = true;
	$(".craft_table tbody tr").each(function(){
		if($(this).find(".craft-name").text() == craft)
			first = false;
	});
	if(first){
		$("#craft_list").append("<div class='select' data-pk='"+craft+"'>"+craft+"</div>")
		$(".select").unbind("click").click(function(){
			$(".select").removeClass("selected");
			$(this).addClass("selected");
			getCraftPilots($(this).data("pk"));
		})
	}
}