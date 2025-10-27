$(document).ready(function(){
	Dropzone.autoDiscover = false;

	$("#change-profile-picture").on("hidden.bs.modal", function(){
		$(".dz-preview").remove();
		$(".dropzone").removeClass("dz-started");
	})

	$("#uploadDocuments").dropzone({
		url: "assets/php/upload_news_image.php",
		maxFilesize: 20,
		clickable: true,
		acceptedFiles: ".jpg,.jpeg,.png,.gif,.bmp,.JPG,.JPEG,.PNG,.GIF,.BMP",
		init: function(){
			this.on("success", function(file, response) {
				if(response.substring(0,7) == "success"){
					$("#uploadImageModal").modal("hide");
					filename = response.substring(8);
					$("#uploadedImage").text(filename);
				}
			});
		}
	});

	$("#removeImage").click(function(){
		if($("#uploadedImage").text() != ""){
			$.ajax({
				type: "POST",
				data: {file: $("#uploadedImage").text()},
				url: "assets/php/remove_news_image.php",
				success: function(result){
					$("#uploadedImage").text("");
				}
			})
		}
	});

	$("#submitPost").click(function(){
		var title = $("#post-title").val(),
			message = $("#post-text").val(),
			imageName = $("#uploadedImage").text(),
			link = $("#link-url").val();

			if(title != "" && (message != "" || imageName != "" || link != "")){
				$.ajax({
					type: "POST",
					url: "assets/php/create_news_post.php",
					data: {title: title, message: message, image: imageName, link: link},
					success: function(result){
						console.log(result);
						if(result == "success"){
							$("#post-title, #post-text, #link-url").val("");
							$("#uploadedImage").text("");
							showNotification("success", "you successfully added the post");
						}
					}
				})
			}
	})
})