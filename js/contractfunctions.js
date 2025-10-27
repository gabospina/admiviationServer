function returnBackground(i){
	var bg = ["dark-bg", "tint-bg", "", "blue-bg"];
	return(bg[(i%4)]);
}
var CRAFTS,
CONTRACTS,
usedAr = [];
$(document).ready(function(){

	$("#search_contracts").keyup(function(){
		var that = this;
		$(".contract-name").each(function(){
			if($(this).text().toUpperCase().indexOf($(that).val().toUpperCase()) != -1){
				$(this).parent().show();
			}else{
				$(this).parent().hide();
			}
		})
	})

	$("#expandAccordion").click(function(){
		if($(this).hasClass("active")){
			$(this).removeClass("active");
			$(this).text("Expand All");
			$(".more_info").collapse("hide");
		}else{
			$(this).addClass("active");
			$(this).text("Collapse All");
			$(".more_info").collapse("show");
		}
	});

	$("#printContracts").click(function(){
		$(".more_info").collapse("show");
		window.print();
	})

	$("#newContractColor").spectrum();
	$(".sidebar-list a[href='contracts.php'] li").addClass("active");

	getContracts();
});

function updateContractColor(){
	$(".contract_head").each(function(i){
		$(this).removeClass("dark-bg tint-bg blue-bg").addClass(returnBackground(i));
	})
}
function setContractEvents(init){
	if(!init){
		$(".colorpicker").spectrum("destroy");
		$("#contracts").sortable("destroy");
		$("#removeContractItem").unbind("click");
	}
	$(".colorpicker").spectrum({
		change: function(color){
			var id = $(this).parents(".more_info").data("pk");
			var c = color.toHexString();
			$.ajax({
				url: "assets/php/change_contract_color.php",
				type: "POST",
				data: {color: c, id: id},
				success: function(result){
					console.log(result);
					showNotification("success", "You successfully changed the color of the contract");
				}
			})
		}
	});

	$("#contracts").sortable({
		update: function(event, ui){
			newOrder = [];
			$(".collapsable-group").each(function(){
				newOrder.push({"id": $(this).data("pk"), "order": $(this).index()});
			});
			$.ajax({
				type: "POST",
				url: "assets/php/update_contract_order.php",
				data: {contracts: JSON.stringify(newOrder)},
				success: function(result){
					if(result == "success"){
						updateContractColor()
						showNotification("success", "You successfully changed the order of you contracts");
					}else{
						showNotification("error", "Updating the order was unsuccessful");
					}
				}
			})
		}
	});

	$(".removeContractItem").click(function(){
		if($(this).parent().parent().siblings().length == 1){
			showNotification("error", "Contracts must have at least one craft.")
		}else{
			var that = this;
			$.ajax({
				type: "POST",
				url: "assets/php/remove_contract_item.php",
				data: {contract: $(this).data("contract"), craft: $(this).data("pk")},
				success: function(result){
					console.log(result);
					if(result == "success"){
						var className = $(that).parent().siblings(".cls").text();
						var classVal = $(that).data("pk");
						var craftName = $(that).parent().siblings(".crft").text();
						console.log(craftName)
						$(".insertContract, #newContractCraftSelect").each(function(){
							var optgroup = $(this).find("optgroup[label='"+craftName+"']");
							optgroup.append("<option value='"+classVal+"'>"+className+"</option>");
						});
						var copterCount = $(that).parent().parent().siblings().length-1;
						$(that).parents(".more_info").prev().find(".copterCount").text(copterCount);
						$(that).parents(".more_info").children(".errors").append("<div class=\"col-md-6 center-block\" style=\"margin-left: auto; margin-right: auto;\">"+
							"<div class=\"alert_sec\"><div class=\"alert alert-success alert-dismissible\" role=\"alert\">"+
								"<button type=\"button\" class=\"close\" data-dismiss=\"alert\"><span aria-hidden=\"true\">&times;</span><span class=\"sr-only\">Close</span></button>"+
								"<strong>Successfully removed entry.</strong></div></div></div>");
						showNotification("success", "Successfully removed entry.")
						$(that).parent().parent().remove();
					}else{
						showNotification("error", "Failed to update Contract");
					}
				}
			})
		}
	});
}

function getContracts(){
	$("#contracts").empty();
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
								var tempObj = {"class": res[j].class, "craftid": res[j].id, "alive": res[j].alive};
								crafts[res[i].craft].push(tempObj)
							}	
						}
					}
				}
				CRAFTS = crafts;

				$.ajax({
					type: "GET",
					url: "assets/php/get_all_contracts.php",
					success: function(result){
						if(result != "false"){
							var res = JSON.parse(result);
							console.log(res);
							var contracts = [];
							for(var i = 0; i < res.length; i++){
								if(contracts[res[i].contract] == undefined){
									contracts[res[i].contract] = {};
									contracts[res[i].contract].id = res[i].contractid;
									contracts[res[i].contract].color = res[i].color
									contracts[res[i].contract].crafts = [];
									for(var j = 0; j < res.length; j++){
										if(res[j].contract == res[i].contract){
											var tempObj = {"class": res[j]["class"], "craft": res[j].craft, "craftid": res[j].craftid};
											contracts[res[i].contract].crafts.push(tempObj)
										}
									}
								}
							}
							CONTRACTS = contracts;
							var i = 0;
							for(key in contracts){
								var head = "<div class=\"contract_head inner-left-sm inner-right-sm "+returnBackground(i)+"\" data-toggle=\"collapse\" data-target=\".contract_info"+i+"\">"+
									"<div class='sorting-icon'><div class='fa fa-lg fa-arrows-v'></div></div><span class=\"contract-name\">"+key+"</span><div class='collapse-caret'><div class='fa fa-lg fa-chevron-down'></div></div><span class=\"contract-heli pull-right\">Helicopters (<span class='copterCount'>"+contracts[key].crafts.length+"</span>)</span></div>"
								
								var body = "<div class=\"contract_info"+i+" more_info collapse inner-sm inner-left-sm inner-right-sm\" data-pk='"+contracts[key].id+"'><div class='errors'></div>"
								body += "<div class='col-md-7 center-block'><table class='table table-condensed table-bordered contract_table' style='-webkit-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); -moz-box-shadow: 0px 0px 0px 0px rgba(0,0,0,0); box-shadow: 0px 0px 0px 0px rgba(0,0,0,0);'><thead><th>Craft</th><th>Registration</th></thead><tbody>"
										for(var k = 0; k < contracts[key].crafts.length; k++){
											body+="<tr><td class='crft'>"+contracts[key].crafts[k].craft+"</td><td class='cls'>"+contracts[key].crafts[k]["class"]+"</td><th><button class='btn btn-warning fa fa-minus removeContractItem' data-pk='"+contracts[key].crafts[k]["craftid"]+"' data-contract='"+contracts[key].id+"'></div></button></tr>"
											usedAr.push(contracts[key].crafts[k].class);
										}

								body += "<tr><td colspan='2'><select multiple size='4' class='form-control insertContract'>"
					
								body += "<option value='none'>None</option></select></td><th><button class='btn btn-success fa fa-plus insertContractBtn' data-pk='"+contracts[key].id+"'></button></th></tr></tbody></table></div>";
								body += "<div class='col-md-3 center-block outer-top-xs'><div class='lbl'>Contract Color</div><input type='color' class='colorpicker form-control' value='"+contracts[key].color+"'></div>";
								body += "<div class='col-md-3 center-block outer-top-xs'><button class='btn btn-danger form-control deleteContractBtn' data-pk='"+contracts[key].id+"' data-name='"+key+"' data-head='.contract_info"+i+"'>Delete Contract</button></div>";
								body += "</div>";
								i++;
								$("#contracts").append("<div class='collapsable-group' data-pk='"+contracts[key].id+"'>"+head+body+"</div>");
							}

							var options = "";
							for(h in CRAFTS){
								options += "<optgroup label='"+h+"'>";
								for(var p = 0; p < CRAFTS[h].length; p++){
									if(usedAr.indexOf(CRAFTS[h][p].class) == -1){
										options += "<option value='"+CRAFTS[h][p].craftid+"'>"+CRAFTS[h][p].class+"</option>";
									}
								}
								options += "</optgroup>"
							}

							$("#newContractCraftSelect, .insertContract").append(options);

							setContractEvents(true);

							$(".insertContractBtn").click(function(){
								var selectBox = $(this).parent().siblings("td").children(".insertContract");
								var vals = $(selectBox).val();
								var craftNames = [], classNames = [];
								if(vals != null){
									
									if(vals.length == 1 && vals.indexOf("none") != -1){
										//if the only entry is none, do nothing;
									
									}else{
										//else, remove none if it's there and submit new values
										if(vals.indexOf("none") != -1){
											vals.splice(vals.indexOf("none"), 1);
										}
										for(var i = 0; i < vals.length; i++){
											classNames.push(selectBox.find("option[value='"+vals[i]+"']").text());
											craftNames.push(selectBox.find("option[value='"+vals[i]+"']").parent().attr("label"));
										}
										var contractName = $(this).data("pk");
										$.ajax({
											type: "POST",
											url: "assets/php/update_contract.php",
											data: {contract: contractName, crafts: JSON.stringify(vals)},
											success: function(result){
												console.log(result);
												if(result == "success"){
													var str = "";
													for(var i = 0; i < vals.length; i++){
														str += "<tr><td class='crft'>"+craftNames[i]+"</td><td class='cls'>"+classNames[i]+"</td><th><button class='btn btn-warning fa fa-minus removeContractItem' data-pk='"+vals[i]+"' data-contract='"+contractName+"'></div></button></tr>"
														$(".insertContract, #newContractCraftSelect").find("option[value='"+vals[i]+"']").remove();
													}
													$(str).insertBefore($(selectBox).parent().parent());

													setContractEvents(false);

													var copterCount = $(selectBox).parent().parent().siblings().length;
													$(selectBox).parents(".more_info").prev().find(".copterCount").text(copterCount);
													$(selectBox).parents(".more_info").children(".errors").append("<div class=\"col-md-6 center-block\" style=\"margin-left: auto; margin-right: auto;\">"+
														"<div class=\"alert_sec\"><div class=\"alert alert-success alert-dismissible\" role=\"alert\">"+
						  								"<button type=\"button\" class=\"close\" data-dismiss=\"alert\"><span aria-hidden=\"true\">&times;</span><span class=\"sr-only\">Close</span></button>"+
						  								"<strong>Success!</strong></div></div></div>");
													showNotification("success", "Success!");
												}else{
													showNotification("error", "Failed to update Contract");
												}
											}
										});
									}
								}
							});

							$.ajax({
								url: "assets/php/get_all_pilots.php",
								success: function(result){
									if(result != "false"){
										var res = JSON.parse(result);
										for(var i = 0; i < res["id"].length; i++){
											$("#newContractPilotSelect").append("<option value='"+res["id"][i]+"'>"+res["fname"][i].capitalize()+" "+res["lname"][i].capitalize()+"</option>");
										}
									}
								}
							});

							$("#submitNewContractBtn").click(function(){
								console.log($("#newContractName").val());

								if($("#newContractName").val() != ""){
									if($("#newContractCraftSelect").val() != null){
										if($("#newContractPilotSelect").val() == null){
											var conPils = [];
										}else{
											var conPils = $("#newContractPilotSelect").val();
										}
										var color = $("#newContractColor").spectrum("get").toHexString();
										$.ajax({
											type: "POST",
											url: "assets/php/add_contract.php",
											data: {name: $("#newContractName").val(), crafts: JSON.stringify($("#newContractCraftSelect").val()), pilots: JSON.stringify(conPils), order: $(".collapsable-group").last().index()+1, color: color},
											success: function(result){
												if(result.substring(0, 7) == "success"){
													$(".addNewContractModal").modal("hide");
													getContracts();
												}else{
													showNotification("error", "Failed to add contract. Please try again later.");
												}
											}
										})
									}else{
										showNotification("error", "Please select atleast one craft");
									}
								}else{
									showNotification("error", "Please enter the contract name");
								}
							});

							$(".deleteContractBtn").click(function(){
								var that = this;
								$("#loadingModal").modal("show");
								$.ajax({
									type: "POST",
									url: "assets/php/delete_contract.php",
									data: {contractID: $(this).data("pk"), contractName: $(this).data("name")},
									success: function(result){
										$("#loadingModal").modal("hide");
										if(result == "success"){
											showNotification("success", "Successfully deleted contract.");
											$(that).parents(".collapsable-group").remove();
											updateContractColor();
										}else{
											showNotification("error", "Failed to delete Contract");
										}
									}
								})
							});
						}
					}
				});
			}	
		}
	})
}