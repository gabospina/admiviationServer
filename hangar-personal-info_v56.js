// File: hangar_personal_info.js
// Purpose: Handles fetching and updating the pilot's main personal details.

function initializePersonalInfo() {
    $.ajax({
        url: "pilots_get_pilot_details.php", dataType: "json",
        success: function (response) {
            if (response && response.success && response.data && response.data.details) {
                const details = response.data.details;
                $("#displayName").text(details.firstname + " " + details.lastname);
                $("#displayUsername").text(details.username);
                $("#nationality").text(details.user_nationality || 'N/A');
                $("#nalLic").text(details.nal_license || 'N/A');
                $("#forLic").text(details.for_license || 'N/A');
                $("#persEmail").text(details.email);
                $("#persPhone").text(details.phone || 'N/A');
                $("#persPhoneTwo").text(details.phonetwo || 'N/A');
                const hireDate = details.hire_date ? moment(details.hire_date).format("MMM DD, YYYY") : "N/A";
                $("#hireDateDisplay").html(`<strong>Hire Date:</strong> ${hireDate}`);
            } else {
                console.error("Failed to load personal info:", response);
                showNotification('error', 'Could not load your personal information.');
            }
        },
        error: function () { showNotification('error', 'A network error occurred while loading your information.'); }
    });
}

function initializePersonalInfoModal() {
    $('#personalInfoModal').on('show.bs.modal', function () {
        $('#firstname').val($('#displayName').text().split(' ')[0]);
        $('#lastname').val($('#displayName').text().split(' ').slice(1).join(' '));
        $('#usernameInput').val($('#displayUsername').text()).prop('readonly', true);
        $('#user_nationality').val($('#nationality').text());
        $('#nal_license').val($('#nalLic').text());
        $('#for_license').val($('#forLic').text());
        $('#email').val($('#persEmail').text());
        $('#phone').val($('#persPhone').text());
        $('#phonetwo').val($('#persPhoneTwo').text());
    });

    $('.savePersonalInfo').on('click', function () {
        const $button = $(this).prop('disabled', true);
        const dataToSend = {
            firstname: $('#firstname').val(), lastname: $('#lastname').val(), user_nationality: $('#user_nationality').val(),
            nal_license: $('#nal_license').val(), for_license: $('#for_license').val(), email: $('#email').val(),
            phone: $('#phone').val(), phonetwo: $('#phonetwo').val()
        };
        $.ajax({
            url: 'hangar_update_personal_info.php', type: 'POST', data: dataToSend, dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showNotification('success', 'Information updated successfully!');
                    $('#personalInfoModal').modal('hide');
                    initializePersonalInfo(); // Refresh display
                } else { showNotification('error', response.error || 'Update failed.'); }
            },
            error: function () { showNotification('error', 'A network error occurred.'); },
            complete: function () { $button.prop('disabled', false); }
        });
    });
}