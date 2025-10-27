/**
 * File: scheduleManagerDutyFunctions.js v83
 * Manages the "Manage Duty Schedules" tab in daily_manager.php.
 * Handles the accordion view for all pilots.
 */

// --- HELPER FUNCTIONS ---

$(document).ready(function () {

    // Check if the manager tab exists before running any code.
    if ($('div.tab[data-tab-toggle="manage-duty"]').length === 0) {
        return; // Exit if this feature's tab isn't on the page.
    }

    // --- VARIABLES for this feature ---
    const $pilotSelector = $('#duty-pilot-selector');
    const $accordionContainer = $('#duty-schedule-accordion-container');

    // --- CORE FUNCTIONS ---

    /**
     * Fetches all pilots and builds the main accordion list structure.
     */
    function loadAndRenderPilotAccordion() {
        $.ajax({
            url: 'pilots_get_all_pilots.php',
            dataType: 'json',
            success: function (response) {
                $accordionContainer.empty();
                $pilotSelector.find('option:gt(0)').remove();

                if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                    response.data.forEach(pilot => {

                        // =========================================================
                        // === THE FIX IS HERE: Determine the CSS class to use   ===
                        // =========================================================
                        const dutyStatusClass = pilot.isOnDutyToday ? 'duty-status-on' : 'duty-status-off';

                        // 1. Add pilot to the main accordion list, now with the new class
                        const panelHtml = `
                        <div class="panel panel-default pilot-duty-panel" data-user-id="${pilot.id}">
                            <div class="panel-heading ${dutyStatusClass}">
                                <h4 class="panel-title">
                                    <a data-toggle="collapse" href="#collapse-${pilot.id}" class="accordion-toggle">
                                        ${pilot.name}
                                    </a>
                                </h4>
                            </div>
                            <div id="collapse-${pilot.id}" class="panel-collapse collapse">
                                <div class="panel-body" id="panel-body-${pilot.id}">
                                    <p class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading schedule...</p>
                                </div>
                            </div>
                        </div>`;
                        $accordionContainer.append(panelHtml);

                        // 2. Add pilot to the filter dropdown (no change here)
                        $pilotSelector.append(`<option value="${pilot.id}">${pilot.name}</option>`);
                    });
                } else {
                    $accordionContainer.html('<p class="text-center text-danger">Could not load pilot list.</p>');
                }
            },
            error: function () {
                $accordionContainer.html('<p class="text-center text-danger">A network error occurred while loading pilots.</p>');
            }
        });
    }

    /**
     * Fetches and displays the duty schedule inside an expanded accordion panel.
     * @param {jQuery} $panelBody - The jQuery object for the panel's body div.
     * @param {number} userId - The ID of the pilot to fetch the schedule for.
     */

    function loadDutyScheduleForPanel($panelBody, userId) {
        // Use the correct PHP script name from your provided files
        $.ajax({
            url: 'daily_manager_user_availability_get.php',
            data: { user_id: userId },
            dataType: 'json',
            success: function (response) {
                $panelBody.empty();

                if (response.success && Array.isArray(response.availability)) {
                    const tableHtml = `
                    <div class="duty-schedule-alert-container" style="display:none;"></div>
                    <table class="table table-bordered table-striped no-shadow">
                        <thead><tr><th>On Duty</th><th>Off Duty</th><th style="width: 15%; text-align: center;">Action</th></tr></thead>
                        <tbody>
                            <tr class="duty-add-new-row">
                                <td><input type="text" placeholder="Select On Duty Date" class="form-control new-on-date"></td>
                                <td><input type="text" placeholder="Select Off Duty Date" class="form-control new-off-date"></td>
                                <td class="text-center"><button class="btn btn-sm btn-primary duty-add-btn" data-user-id="${userId}"><i class="fa fa-plus"></i> Add</button></td>
                            </tr>
                        </tbody>
                    </table>`;
                    $panelBody.html(tableHtml);

                    const $tbody = $panelBody.find('tbody');
                    if (response.availability.length > 0) {
                        response.availability.forEach(period => {
                            const onDate = moment(period.on_date).format("MMM DD, YYYY");
                            const offDate = moment(period.off_date).format("MMM DD, YYYY");

                            // --- THIS IS THE FIX ---
                            // Ensure the data-availability-id attribute is correctly set with period.id
                            const rowHtml = `
                            <tr>
                                <td>${onDate}</td>
                                <td>${offDate}</td>
                                <td class="text-center">
                                    <button class="btn btn-xs btn-danger duty-delete-btn" data-availability-id="${period.id}" title="Delete this period">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>`;
                            // --- END OF FIX ---
                            $tbody.find('.duty-add-new-row').before(rowHtml);
                        });
                    } else {
                        const noRowsHtml = '<tr><td colspan="3" class="text-center text-muted">No duty periods scheduled.</td></tr>';
                        $tbody.find('.duty-add-new-row').before(noRowsHtml);
                    }

                    // Initialize linked Flatpickr instances for the new row
                    const onDateInput = $panelBody.find('.new-on-date')[0];
                    const offDateInput = $panelBody.find('.new-off-date')[0];
                    let offDatePicker;
                    const onDatePicker = flatpickr(onDateInput, {
                        dateFormat: "Y-m-d",
                        onChange: function (selectedDates) {
                            if (selectedDates.length > 0 && offDatePicker) {
                                offDatePicker.set('minDate', selectedDates[0]);
                            }
                        }
                    });
                    offDatePicker = flatpickr(offDateInput, { dateFormat: "Y-m-d" });
                } else {
                    $panelBody.html(`<p class="text-danger">${response.error || 'Failed to load schedule.'}</p>`);
                }
            },
            error: () => $panelBody.html('<p class="text-danger">A server error occurred.</p>')
        });
    }

    // --- EVENT HANDLERS ---

    // When a pilot's accordion header is clicked to expand/collapse
    $accordionContainer.on('click', '.accordion-toggle', function (e) {
        e.preventDefault();
        const $panel = $(this).closest('.pilot-duty-panel');
        const $panelBody = $panel.find('.panel-body');
        const userId = $panel.data('user-id');
        const isLoaded = $panelBody.hasClass('is-loaded');

        // Only load the data via AJAX the first time the panel is opened
        if (!isLoaded) {
            loadDutyScheduleForPanel($panelBody, userId);
            $panelBody.addClass('is-loaded');
        }

        // Manually toggle the collapse for a smoother experience
        $panel.find('.panel-collapse').collapse('toggle');
    });

    // When the manager uses the filter dropdown
    $pilotSelector.on('change', function () {
        const selectedId = $(this).val();
        if (selectedId) {
            const $targetPanel = $(`.pilot-duty-panel[data-user-id="${selectedId}"]`);
            if ($targetPanel.length) {
                // Scroll to the panel
                $('html, body').animate({
                    scrollTop: $targetPanel.offset().top - 70 // Adjust 70px for navbar height
                }, 500);

                // Expand the panel if it's not already open
                if (!$targetPanel.find('.panel-collapse').hasClass('in')) {
                    $targetPanel.find('.accordion-toggle').click();
                }
            }
        }
    });

    // --- When the manager clicks an "Add" button inside a panel ---
    $accordionContainer.on('click', '.duty-add-btn', function () {
        const $button = $(this);
        const userId = $button.data('user-id');
        const $row = $button.closest('tr');
        const onDate = $row.find('.new-on-date').val();
        const offDate = $row.find('.new-off-date').val();
        const $panelBody = $row.closest('.panel-body');

        if (!onDate || !offDate) {
            showAlert($panelBody, 'warning', 'Please select both an "On Duty" and "Off Duty" date.');
            return;
        }

        $button.prop('disabled', true).find('i').removeClass('fa-plus').addClass('fa-spinner fa-spin');

        $.ajax({
            // --- THIS IS THE FIX: Use the correct, new filename ---
            url: 'daily_manager_user_availability_insert.php',
            type: 'POST',
            data: {
                user_id: userId,
                on_date: onDate,
                off_date: offDate,
                form_token: $("#form_token_manager").val() // ✅ CORRECT
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Update CSRF token if provided
                    if (response.new_csrf_token) {
                        $("#form_token_manager").val(response.new_csrf_token);
                    }
                    alert(response.message || 'Duty period added successfully!'); // Use a simple alert before reloading
                    showAlert($panelBody, 'success', response.message || 'Duty period added successfully!');

                    // Clear the input fields
                    $row.find('.new-on-date').val('');
                    $row.find('.new-off-date').val('');

                    // Refresh only this panel's content
                    loadDutyScheduleForPanel($panelBody, userId);
                } else {
                    // showAlert($panelBody, 'danger', response.error || 'An unknown error occurred.');
                    alert('Error: ' + (response.error || 'An unknown error occurred.'));
                    showAlert($panelBody, 'danger', response.error || 'An unknown error occurred.');
                    $button.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-plus');
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'A server or network error occurred.';
                showAlert($panelBody, 'danger', errorMsg);
                $button.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-plus');
            }
        });
    });

    // --- When the manager clicks a "Delete" button inside a panel ---
    $accordionContainer.on('click', '.duty-delete-btn', function () {
        const $button = $(this);
        const availabilityId = $button.data('availability-id');
        const $row = $button.closest('tr');
        const $panelBody = $row.closest('.panel-body');

        // Verify we have a valid ID.
        if (!availabilityId || availabilityId <= 0) {
            showAlert($panelBody, 'danger', 'Error: Could not find the record ID to delete.');
            return;
        }

        // --- THIS IS A FIX: Removed the duplicate confirm() call ---
        if (!confirm('Are you sure you want to delete this duty period?')) {
            return;
        }

        $button.prop('disabled', true);
        $row.css('opacity', '0.5');

        $.ajax({
            // --- THIS IS THE FIX: Use the correct, new filename ---
            url: 'daily_manager_user_availability_delete.php',
            type: 'POST',
            data: {
                availability_id: availabilityId,
                form_token: $("#form_token_manager").val() // ✅ CORRECT
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Update CSRF token if provided
                    if (response.new_csrf_token) {
                        $("#form_token_manager").val(response.new_csrf_token);
                    }
                    alert(response.message || 'Duty period deleted successfully!');
                    showAlert($panelBody, 'success', response.message || 'Duty period deleted successfully!');

                    // Refresh only this panel's content
                    loadDutyScheduleForPanel($panelBody, $panelBody.closest('.pilot-duty-panel').data('user-id'));
                } else {
                    // showAlert($panelBody, 'danger', response.error || 'Could not delete the period.');
                    alert('Error: ' + (response.error || 'Could not delete the period.'));
                    showAlert($panelBody, 'danger', response.error || 'Could not delete the period.');
                    $button.prop('disabled', false);
                    $row.css('opacity', '1');
                }
            },
            error: function (xhr) {
                const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'A server error occurred.';
                showAlert($panelBody, 'danger', errorMsg);
                $button.prop('disabled', false);
                $row.css('opacity', '1');
            }
        });
    });

    // =========================================================
    // === START: NEW EVENT HANDLER FOR EXCEL EXPORT         ===
    // =========================================================
    $('#duty-export-btn').on('click', function () {
        const startDate = $('#duty-export-start').val();
        const endDate = $('#duty-export-end').val();

        if (!startDate || !endDate) {
            alert('Please select both a start and end date for the export.');
            return;
        }

        if (new Date(startDate) > new Date(endDate)) {
            alert('The start date cannot be after the end date.');
            return;
        }

        // 1. Construct the full URL to the download script.
        const downloadUrl = `daily_manager_user_availability_export_duty.php?start=${startDate}&end=${endDate}`;

        // 2. Create a temporary, invisible link element.
        const link = document.createElement('a');
        link.href = downloadUrl;

        // 3. Set the 'download' attribute (optional but good practice).
        // This suggests a filename to the browser. The PHP headers will ultimately decide.
        link.setAttribute('download', `Duty_Schedule_${startDate}_to_${endDate}.xlsx`);

        // 4. Append the link to the page (it's invisible).
        document.body.appendChild(link);

        // 5. Programmatically "click" the link to trigger the download.
        link.click();

        // 6. Clean up by removing the temporary link from the page.
        document.body.removeChild(link);
    });
    // =========================================================
    // === END: NEW EVENT HANDLER FOR EXCEL EXPORT           ===
    // =========================================================


    // --- INITIALIZATION ---

    // Load the main pilot list when the tab is clicked for the first time
    $('div.tabs div[data-tab-toggle="manage-duty"]').one('click', function () {
        loadAndRenderPilotAccordion();
    });

    // Also load if the tab is already active when the page first loads
    if ($('div.tabs div[data-tab-toggle="manage-duty"].active').length) {
        loadAndRenderPilotAccordion();
    }

    // =========================================================
    // === START: NEW INITIALIZATION FOR EXPORT DATEPICKERS  ===
    // =========================================================
    // Initialize date pickers for the new export inputs
    $('#duty-export-start, #duty-export-end').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true
    });
    // =========================================================
    // === END: NEW INITIALIZATION FOR EXPORT DATEPICKERS    ===
    // =========================================================
});

// In scheduleManagerDutyFunctions.js

/**
 * Displays a styled, auto-dismissing alert message inside a specific container.
 * --- THIS IS THE CORRECTED VERSION ---
 * @param {jQuery} $container - The jQuery object of the panel-body where the alert should appear.
 * @param {string} type - 'success', 'info', 'warning', or 'danger'.
 * @param {string} message - The message to display.
 */
function showAlert($container, type, message) {
    // Find the alert div WITHIN the provided container. This is much more reliable.
    const $alertContainer = $container.find('.duty-schedule-alert-container');
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            ${message}
        </div>`;

    $alertContainer.html(alertHtml).slideDown(200);

    setTimeout(() => {
        $alertContainer.slideUp(300, () => $alertContainer.empty());
    }, 5000);
}