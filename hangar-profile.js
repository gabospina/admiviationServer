// File: hangar_profile.js
// Purpose: Manages user profile updates like password and profile picture.

function initializeProfilePictureUploader() {
    Dropzone.autoDiscover = false;
    if ($("#profilePictureUpload").length > 0) {
        if (Dropzone.instances.length > 0) Dropzone.instances.forEach(i => i.destroy());
        new Dropzone("#profilePictureUpload", {
            url: "hangar_change_profile_picture.php", maxFilesize: 2, acceptedFiles: "image/jpeg,image/png,image/gif", dictDefaultMessage: "Drop image here or click<br><small>(JPG, PNG, GIF - Max 2MB)</small>",
            init: function () {
                this.on("success", function (file, response) {
                    let res = (typeof response === 'string') ? JSON.parse(response) : response;
                    if (res.success) {
                        const newUrl = `uploads/pictures/${res.filename}?t=${new Date().getTime()}`;
                        $("#profile-picture").attr("src", newUrl);
                        $('#change-profile-picture').modal('hide');
                    } else { alert("Upload failed: " + (res.error || "Unknown error.")); }
                    this.removeFile(file);
                });
                this.on("error", (file, msg) => { alert("Error: " + (typeof msg === 'string' ? msg : "Check file.")); this.removeFile(file); });
            }
        });
    }
    $.ajax({
        url: 'pilots_get_pilot_details.php', dataType: 'json',
        success: (res) => {
            if (res.success && res.data.details.profile_picture) {
                $("#profile-picture").attr("src", `uploads/pictures/${res.data.details.profile_picture}?t=${new Date().getTime()}`);
            }
        }
    });
}

function initializePasswordChanger() {
    $('#changePassBtn').on('click', function (e) {
        e.preventDefault();
        const $btn = $(this), oldP = $('#old_password').val(), newP = $('#new_password').val(), confP = $('#confirm_password').val(), $err = $("#changePassError");
        $err.text("").hide();
        if (!oldP || !newP || !confP) return $err.text("Please fill in all fields.").show();
        if (newP.length < 8) return $err.text("New password must be at least 8 characters long.").show();
        if (newP !== confP) return $err.text("New passwords do not match.").show();
        $btn.val("Submitting...").prop('disabled', true);
        $.ajax({
            url: "hangar_change_password.php", type: "POST", data: { old: oldP, pass: newP }, dataType: "json",
            success: (res) => res.success ? (showNotification("success", res.message || "Password changed!"), $('.changepass').modal('hide')) : $err.text(res.message || "Error.").show(),
            error: () => $err.text("Server error. Please try again.").show(),
            complete: () => $btn.val("Change Password").prop('disabled', false)
        });
    });
    $('.changepass').on('hidden.bs.modal', function () {
        $(this).find("form")[0].reset();
        $("#changePassError").text("").hide();
    });
}