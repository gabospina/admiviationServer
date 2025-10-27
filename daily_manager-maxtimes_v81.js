// daily_manager-maxtimes.js v72
$(document).ready(function () {
    // --- Globals / Config for Max Times Tab ---
    const $maxTimesForm = $('#editMaxTimesForm');
    const $loadingIndicator = $('#max-times-loading-indicator');
    const $fieldsContainer = $('#max-times-limits-fields');
    const $saveBtn = $('#saveMaxTimesBtn');
    const $alertContainer = $('#max-times-alert-container');

    // ADD THIS FUNCTION - it was missing from this file
    // CSRF Token Strategy: Per-Session (token changes only on validation failures)
function updateCsrfToken(newToken) {
        console.log('Updating CSRF token to:', newToken);

        // Update the main CSRF token
        $('#csrf_token_manager').val(newToken);

        console.log('CSRF token updated successfully');
    }

    // ===================================================== //
    // === START: MAX TIME LIMITS TAB LOGIC & EDIT LIMITS ====
    // ===================================================== //
    function loadMaxTimeLimits1() {
        console.log("--- [DEBUGging] 1. loadMaxTimeLimits() function called. ---");

        $loadingIndicator.show();
        $fieldsContainer.hide();
        $alertContainer.hide();

        $.ajax({
            url: 'stats_get_max_times.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    const limits = response.data;

                    const displayIdentifier = limits.company_name || `ID: ${limits.company_id}`;
                    $('#max-times-company-id-display').text(limits.company_name || 'N/A');

                    $('.current-limit').each(function () {
                        const $spanElement = $(this);
                        const limitName = $spanElement.data('limit-name');
                        const value = limits[limitName];

                        if (value !== undefined && value !== null) {
                            $spanElement.text(parseFloat(value).toFixed(1));
                        } else {
                            $spanElement.text('Not Set');
                        }
                    });

                } else {
                    showNotification($alertContainer, 'warning', response.message || 'Could not load limits.');
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : "A network error occurred.";
                showNotification($alertContainer, 'danger', 'Error loading limits: ' + errorMsg);
            },
            complete: function () {
                $loadingIndicator.hide();
                $fieldsContainer.show();
            }
        });
    }

    function loadMaxTimeLimits() {
        console.log("--- [DEBUGging] 1. loadMaxTimeLimits() function called. ---");

        $loadingIndicator.show();
        $fieldsContainer.hide();
        $alertContainer.hide();

        $.ajax({
            url: 'stats_get_max_times.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    const limits = response.data;

                    const displayIdentifier = limits.company_name || `ID: ${limits.company_id}`;
                    $('#max-times-company-id-display').text(limits.company_name || 'N/A');

                    // Update current limit displays
                    $('.current-limit').each(function () {
                        const $spanElement = $(this);
                        const limitName = $spanElement.data('limit-name');
                        const value = limits[limitName];

                        if (value !== undefined && value !== null && value !== 0) {
                            $spanElement.text(parseFloat(value).toFixed(1));
                        } else {
                            $spanElement.text('Not Set');
                        }
                    });

                    // REMOVED: $('.limit-input').val('');
                    // Input fields will now preserve their values between loads

                } else {
                    showNotification($alertContainer, 'warning', response.message || 'Could not load limits.');
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : "A network error occurred.";
                showNotification($alertContainer, 'danger', 'Error loading limits: ' + errorMsg);
            },
            complete: function () {
                $loadingIndicator.hide();
                $fieldsContainer.show();
            }
        });
    }

    $maxTimesForm.on('submit', function (e) {
        e.preventDefault();
        let dataToSend = {};
        let hasValues = false;

        $maxTimesForm.find('.limit-input').each(function () {
            const value = $(this).val().trim();
            if (value !== '') {
                const name = $(this).data('limit-name');
                dataToSend[name] = value;
                hasValues = true;
            }
        });

        if (!hasValues) {
            showNotification($alertContainer, 'info', 'No new values were entered. Nothing to save.');
            return;
        }

        // ADD THIS VALIDATION
        const csrfToken = $('#csrf_token_manager').val();
        if (!csrfToken) {
            showNotification($alertContainer, 'danger', 'Security token missing. Please refresh the page.');
            return;
        }

        dataToSend.csrf_token = csrfToken;

        $saveBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $alertContainer.hide();

        console.log("DEBUG: Sending data:", dataToSend); // ADD THIS

        $.ajax({
            url: 'stats_update_max_times.php',
            type: 'POST',
            data: dataToSend,
            dataType: 'json',
            success: function (response) {
                console.log("DEBUG: Success response:", response); // ADD THIS

                // FIXED: Check response.data instead of response.data.new_csrf_token
                if (response.data && response.data.new_csrf_token) {
                    updateCsrfToken(response.data.new_csrf_token);
                }

                if (response.success) {
                    showNotification($alertContainer, 'success', response.message);

                    // OPTIONAL: Clear inputs only after successful save
                    $('.limit-input').val('');

                    loadMaxTimeLimits();
                } else {
                    showNotification($alertContainer, 'danger', response.message || 'An unknown error occurred.');
                }
            },
            error: function (xhr, status, error) {
                console.log("DEBUG: Error response:", xhr.responseText); // ADD THIS

                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.data && errorResponse.data.new_csrf_token) {
                        updateCsrfToken(errorResponse.data.new_csrf_token);
                    }
                    showNotification($alertContainer, 'danger', 'Error saving limits: ' + (errorResponse.message || error));
                } catch (e) {
                    showNotification($alertContainer, 'danger', 'Network error: ' + error);
                }
            },
            complete: function () {
                $saveBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Changes');
            }
        });
    });

    // Attach the load function to the tab's show event
    $('a[data-toggle="tab"][href="#max-times"], .tab[data-tab-toggle="max-times"]').on('shown.bs.tab click', function (e) {
        loadMaxTimeLimits();
    });

    // If the tab might be active on page load, call it once
    if ($('.tab[data-tab-toggle="max-times"]').hasClass('active') || $('#max-times').hasClass('active')) {
        loadMaxTimeLimits();
    }
});