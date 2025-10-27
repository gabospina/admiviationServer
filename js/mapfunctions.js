var rad = function(x) {
  return x * Math.PI / 180;
};

var getDistance = function(p1, p2) {
  var R = 6378137; // Earth’s mean radius in meter
  var dLat = rad(p2.lat - p1.lat);
  var dLong = rad(p2.lon - p1.lon);
  var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(rad(p1.lat)) * Math.cos(rad(p2.lat)) * Math.sin(dLong / 2) * Math.sin(dLong / 2);
  var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  var d = R * c;
  return d; // returns the distance in meter
};
$(document).ready(function(){
	$(".copyrightDate").text(new Date().getFullYear());
	$(".mnav").click(function(){
		$(".mobile-nav").toggle("blind");
	})

	var mapOptions = {
          center: { lat: -9.690203, lng: 13.965870},
          zoom: 8
        };
    var map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);
	//get pilot info
	$.ajax({
		type: "POST",
		url: "assets/php/get_pilot_info.php",
		success: function(response){
			if(response != "false" && response != "" && response != null){
				var res = JSON.parse(response);
				$("#username").text(res["fname"]+" "+res["lname"]);
			}
		}
	});

	$.ajax({
		type: "POST",
		url: "assets/php/checkAdmin.php",
		success: function(data){
			if(data != "false"){
				$('nav.fullnav ul, .mobile-nav').append("<li><a href=\"pilots.php\">Pilots</a></li><li><a href=\"contracts.php\">Contracts & Crafts</a></li>")
				$("#footerMenu").append("<a href='pilots.php'><button class='btn btn-default form-control'>Pilots</button></a><a href='contracts.php'><button class='btn btn-default form-control'>Contracts & Crafts</button></a>")
			}
		}
	});

});