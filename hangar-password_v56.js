// This file handles the "Change Password" modal functionality in hangar.php

// This is a safe wrapper for jQuery code.
// It waits for the document to be ready and ensures '$' is an alias for jQuery.
jQuery(function ($) {

    // A check to ensure the form actually exists on the page before trying to add listeners.
    const $changePasswordForm = $('#changePasswordForm');
    if ($changePasswordForm.length === 0) {
        return; // Exit if the form isn't found
    }

    // Hide alert container on modal close and reset form
    $('.changepass').on('hidden.bs.modal', function () {
        $('#changePassAlertContainer').hide().empty();
        $changePasswordForm[0].reset();
    });

    $changePasswordForm.on('submit', function (e) {
        e.preventDefault(); // Prevent the form from doing a full page reload

        const $alertContainer = $('#changePassAlertContainer');
        const $button = $('#submitChangePassBtn');
        const oldPass = $('#oldpass').val();
        const newPass = $('#newpass').val();
        const confPass = $('#confpass').val();

        $alertContainer.hide().empty(); // Clear old messages

        if (newPass !== confPass) {
            $alertContainer.html('<div class="alert alert-danger">New passwords do not match.</div>').show();
            return;
        }

        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'hangar_change_password.php', // Make sure this path is correct
            type: 'POST',
            data: {
                old_password: oldPass,
                new_password: newPass,
                confirm_password: confPass
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $alertContainer.html('<div class="alert alert-success">' + response.message + '</div>').show();
                    $changePasswordForm[0].reset();
                    // Close the modal after a successful change
                    setTimeout(function () {
                        $('.changepass').modal('hide');
                    }, 2500);
                } else {
                    $alertContainer.html('<div class="alert alert-danger">' + (response.message || response.error) + '</div>').show();
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON ? (xhr.responseJSON.message || xhr.responseJSON.error) : 'A server error occurred. Please try again.';
                $alertContainer.html('<div class="alert alert-danger">' + errorMsg + '</div>').show();
            },
            complete: function () {
                $button.prop('disabled', false).html('Save New Password');
            }
        });
    });
});