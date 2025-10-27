<?php
// stats_edit_max_times.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Authentication & Authorization check - Adapt as needed
// Should only admins edit this? Or pilots their own related account limits?
// Let's assume an admin level is needed for now.
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["admin"]) || $_SESSION["admin"] < 7) { // Example: Admin level 7+
    // Redirect or show error if not authorized
    // For simplicity, we'll rely on JS fetch failing if account ID isn't available
     // header("Location: index.php"); // Or show a permission denied message
     // exit();
}

$page = "settings"; // Or a new page identifier
$pageTitle = "Edit Max Time Limits";
include_once "header.php"; // Includes DB connection potentially, session check done above
?>

<div class="light-bg">
    <div class="container inner-sm">
        <h1 class="page-header">Maximum Time Limit Settings</h1>

        <div id="alert-container" style="display: none;"></div> <!-- For success/error messages -->

        <!-- Form to display and edit limits -->
        <form id="editMaxTimesForm">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Edit Limits for Account ID: <span id="account-id-display">Loading...</span></h3>
                     <!-- Store the account ID fetched by JS -->
                     <input type="hidden" id="account_id_hidden" name="account_id">
                </div>
                <div class="panel-body">
                    <div id="loading-indicator" class="text-center" style="display: none; padding: 20px;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i> Loading current limits...
                    </div>

                    <div id="limits-fields">
                        <!-- Fields will be populated by JavaScript -->
                        <!-- Example structure (repeat for all limits) -->
                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="max_in_day">Max Flying Hours / Day</label>
                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_in_day" data-limit-name="max_in_day" placeholder="e.g., 8.0" required>
                                </div>
                            </div>
                             <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="max_duty_in_day">Max Duty Hours / Day</label>
                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_duty_in_day" data-limit-name="max_duty_in_day" placeholder="e.g., 14.0" required>
                                </div>
                            </div>
                        </div>
                        <hr>
                         <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="max_last_7">Max Flying Hours / 7 Days</label>
                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_last_7" data-limit-name="max_last_7" placeholder="e.g., 40.0" required>
                                </div>
                            </div>
                             <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="max_duty_7">Max Duty Hours / 7 Days</label>
                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_duty_7" data-limit-name="max_duty_7" placeholder="e.g., 60.0" required>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="max_last_28">Max Flying Hours / 28 Days</label>
                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_last_28" data-limit-name="max_last_28" placeholder="e.g., 160.0" required>
                                </div>
                            </div>
                             <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="max_duty_28">Max Duty Hours / 28 Days</label>
                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_duty_28" data-limit-name="max_duty_28" placeholder="e.g., 190.0" required>
                                </div>
                            </div>
                        </div>
                        <hr>
                         <div class="row">
                             <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="max_last_365">Max Flying Hours / 365 Days</label>
                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_last_365" data-limit-name="max_last_365" placeholder="e.g., 1000.0" required>
                                </div>
                             </div>
                             <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label for="max_duty_365">Max Duty Hours / 365 Days</label>
                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_duty_365" data-limit-name="max_duty_365" placeholder="e.g., 2000.0" required>
                                </div>
                             </div>
                        </div>
                        <!-- NOTE: max_days_in_row is missing from pilot_max_times schema provided -->
                        <!-- If you add it, include an input here -->
                        <!--
                        <hr>
                         <div class="row">
                             <div class="col-md-6 col-sm-12">
                                 <div class="form-group">
                                     <label for="max_days_in_row">Max Flying Days in a Row</label>
                                     <input type="number" step="1" min="0" class="form-control limit-input" id="max_days_in_row" data-limit-name="max_days_in_row" placeholder="e.g., 6" required>
                                 </div>
                             </div>
                         </div>
                         -->

                    </div><!-- /#limits-fields -->
                </div><!-- /panel-body -->
                <div class="panel-footer text-right">
                    <button type="submit" class="btn btn-success" id="saveLimitsBtn">
                        <i class="fa fa-save"></i> Save Changes
                    </button>
                </div>
            </div><!-- /panel -->
        </form>

    </div><!-- /container -->
</div><!-- /light-bg -->

<?php include_once "footer.php"; ?>

<!-- Add JavaScript for fetching and saving -->
<script>
$(document).ready(function() {

    const accountId = <?php echo isset($_SESSION['account']) ? json_encode((int)$_SESSION['account']) : 'null'; ?>;
    const $form = $('#editMaxTimesForm');
    const $fieldsContainer = $('#limits-fields');
    const $loadingIndicator = $('#loading-indicator');
    const $saveButton = $('#saveLimitsBtn');
    const $alertContainer = $('#alert-container');
    const $accountIdDisplay = $('#account-id-display');
     const $accountIdHidden = $('#account_id_hidden');

    function showAlert(type, message) {
        $alertContainer.html(`<div class="alert alert-${type} alert-dismissible fade in" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
            ${message}
        </div>`).show();
         // Auto-hide after 5 seconds
        setTimeout(() => { $alertContainer.fadeOut(); }, 5000);
    }

    function loadLimits() {
        if (!accountId) {
             showAlert('danger', 'Account ID not found in session. Cannot load limits.');
             $accountIdDisplay.text('Error');
             return;
        }

        $loadingIndicator.show();
        $fieldsContainer.hide();
        $accountIdDisplay.text(accountId); // Display the account ID being edited
        $accountIdHidden.val(accountId); // Store it in the hidden field

        $.ajax({
            url: 'stats_get_max_times.php', // API endpoint to fetch limits
            method: 'GET',
            data: { account_id: accountId }, // Send account ID if needed by API
            dataType: 'json',
            success: function(response) {
                $loadingIndicator.hide();
                if (response && response.success && response.data) {
                    const limits = response.data;
                    // Populate form fields
                    $('.limit-input').each(function() {
                        const limitName = $(this).data('limit-name');
                        if (limits.hasOwnProperty(limitName)) {
                            // Format float values to one decimal place for display
                            let value = limits[limitName];
                            if (!limitName.includes('days')) { // Assuming only 'days' is integer
                                value = parseFloat(value).toFixed(1);
                            }
                            $(this).val(value);
                        } else {
                            console.warn(`Limit data for '${limitName}' not found in response.`);
                            // Leave placeholder or default
                        }
                    });
                     $fieldsContainer.show(); // Show fields after loading
                } else {
                     showAlert('danger', 'Failed to load limits: ' + (response.message || 'Unknown error'));
                     $accountIdDisplay.text('Error Loading');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $loadingIndicator.hide();
                 console.error("AJAX Error loading limits:", textStatus, errorThrown, jqXHR.responseText);
                 showAlert('danger', 'AJAX Error loading limits. Please check console.');
                 $accountIdDisplay.text('Error Loading');
            }
        });
    }

    // Form submission handler
    $form.on('submit', function(e) {
        e.preventDefault();
        $saveButton.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');
        $alertContainer.hide(); // Hide previous alerts

        const updatedLimits = {};
        let isValid = true;

        // Collect data from form fields
        $('.limit-input').each(function() {
            const limitName = $(this).data('limit-name');
            const value = $(this).val();

            // Basic validation (must be non-negative number)
            if (value === '' || isNaN(value) || parseFloat(value) < 0) {
                showAlert('warning', `Invalid value entered for ${$(this).prev('label').text()}. Must be a non-negative number.`);
                 $(this).focus(); // Focus the invalid field
                 isValid = false;
                 return false; // Exit .each() loop
            }
            updatedLimits[limitName] = value;
        });

        if (!isValid) {
            $saveButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
            return; // Stop submission if validation failed
        }

        // Add account ID to the data being sent
        updatedLimits.account_id = accountId; // Use accountId fetched earlier

        console.log("Saving Limits:", updatedLimits);

        // AJAX call to update limits
        $.ajax({
            url: 'stats_update_max_times.php', // API endpoint to save limits
            method: 'POST',
            data: updatedLimits,
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                     showAlert('success', response.message || 'Limits updated successfully!');
                } else {
                     showAlert('danger', 'Failed to update limits: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                 console.error("AJAX Error saving limits:", textStatus, errorThrown, jqXHR.responseText);
                 showAlert('danger', 'AJAX Error saving limits. Please check console.');
            },
            complete: function() {
                $saveButton.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save');
            }
        });
    });

    // Initial load of limits when page is ready
    loadLimits();

});
</script>

</body>
</html>