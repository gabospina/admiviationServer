// daily_manager-maxtimes.js v83 - FIXED
$(document).ready(function () {
    // --- Globals / Config for Max Times Tab ---
    const $maxTimesForm = $('#editMaxTimesForm');
    const $loadingIndicator = $('#max-times-loading-indicator');
    const $fieldsContainer = $('#max-times-limits-fields');
    const $saveBtn = $('#saveMaxTimesBtn');
    const $alertContainer = $('#max-times-alert-container');

    // ✅ FIXED: Use form_token_manager (consistent with other files)
    function updateCsrfToken(newToken) {
        console.log('Updating CSRF token to:', newToken);
        
        // ✅ FIXED: Update the correct token field
        $('#form_token_manager').val(newToken);
        
        console.log('CSRF token updated successfully');
    }

    // ===================================================== //
    // === START: MAX TIME LIMITS TAB LOGIC & EDIT LIMITS ====
    // ===================================================== //

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

        // ✅ FIXED: Use form_token (consistent with other files)
        const csrfToken = $('#form_token_manager').val();
        if (!csrfToken) {
            showNotification($alertContainer, 'danger', 'Security token missing. Please refresh the page.');
            return;
        }

        // ✅ FIXED: Use form_token field name
        dataToSend.form_token = csrfToken;

        $saveBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        $alertContainer.hide();

        console.log("DEBUG: Sending data:", dataToSend);

        $.ajax({
            url: 'stats_update_max_times.php',
            type: 'POST',
            data: dataToSend,
            dataType: 'json',
            success: function (response) {
                console.log("DEBUG: Success response:", response);

                // ✅ FIXED: Check response.data for new_csrf_token
                if (response.data && response.data.new_csrf_token) {
                    updateCsrfToken(response.data.new_csrf_token);
                }

                if (response.success) {
                    showNotification($alertContainer, 'success', response.message);
                    $('.limit-input').val('');
                    loadMaxTimeLimits();
                } else {
                    showNotification($alertContainer, 'danger', response.message || 'An unknown error occurred.');
                }
            },
            error: function (xhr, status, error) {
                console.log("DEBUG: Error response:", xhr.responseText);

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