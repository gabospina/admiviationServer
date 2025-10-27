// daily_manager-pilots.js

// Add this at the top of your daily_manager-pilots.js
let loadPilotsTimeout = null;
let isCurrentlyLoadingPilots = false;

// daily_manager-utils.js
// This file contains globally accessible helper functions for the Daily Management page.

/**
 * A shared utility function to escape HTML special characters to prevent XSS.
 * @param {*} text - The input to escape. Will be converted to a string.
 * @returns {string} The escaped string.
 */
function escapeHtml(text) {
    if (typeof text !== 'string') {
        if (text === null || typeof text === 'undefined') {
            return "";
        }
        text = String(text);
    }
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}

/**
 * Capitalizes the first letter of a string.
 * @param {string} str The string to capitalize.
 * @returns {string} The capitalized string.
 */
function capitalize(str) {
    if (typeof str !== 'string' || !str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Updates the value of the CSRF token in the hidden input field on the page.
 * This is a critical security function, called after any AJAX POST request
 * that returns a new token from the server.
 * @param {string} newToken - The new token received from the server.
 */
// CSRF Token Strategy: Per-Session (token changes only on validation failures)
function updateCsrfToken(newToken) {
    console.log('Updating CSRF token to:', newToken);
    
    // Update the main CSRF token
    $('#csrf_token_manager').val(newToken);
    
    console.log('CSRF token updated successfully');
} else {
            console.warn("CSRF token update failed: Input field '#csrf_token_manager' not found.");
        }
    }
}

/**
 * A robust, shared function to show a styled Bootstrap alert in a specified container.
 * @param {jQuery} $container - The jQuery object of the container where the alert should appear.
 * @param {string} type - The alert type ('success', 'danger', 'warning', 'info').
 * @param {string} message - The message to display.
 */
function showNotification($container, type, message) {
    if (!$container || $container.length === 0) {
        console.error("showNotification failed: Invalid or missing container provided. Falling back to alert.");
        alert(`${capitalize(type)}: ${message}`);
        return;
    }

    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            ${escapeHtml(message)}
        </div>`;

    $container.html(alertHtml).slideDown(300);

    // Optional: Auto-hide the alert after 5 seconds
    setTimeout(() => {
        $container.find('.alert').slideUp(300, () => $container.empty().hide());
    }, 5000);
}

// You can add any other future global utility functions for this section below.

$(document).ready(function () {
    // --- Globals / Config for Pilot Management ---
    const $managePilotsTableBody = $('#managePilotsTableBody');
    const $managePilotsAlertContainer = $('#manage-pilots-alert-container');
    const $createPilotAlertContainer = $('#create-pilot-alert-container');

    const $craftsListContainer = $('#manager_crafts_checkbox_list');
    const $contractsListContainer = $('#manager_contracts_checkbox_list');

    // ==================================================
    // === MANAGE PILOTS TAB & GENERAL PILOT FUNCTIONS ===
    // ==================================================
    function loadPilotsForManager() {
        // console.log('loadPilotsForManager called - Stack trace:', new Error().stack);

        // DEBOUNCE: Clear any pending calls
        if (loadPilotsTimeout) {
            clearTimeout(loadPilotsTimeout);
        }

        // PREVENT DUPLICATE REQUESTS: If already loading, queue the next call
        if (isCurrentlyLoadingPilots) {
            console.log('Pilot load already in progress, queuing next call...');
            loadPilotsTimeout = setTimeout(loadPilotsForManager, 100);
            return;
        }

        isCurrentlyLoadingPilots = true;

        const searchFilter = $('#pilot-search-filter').val();
        const statusFilter = $('#pilot-status-filter').val();

        $managePilotsTableBody.html('<tr><td colspan="6" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading pilots...</td></tr>');

        $.ajax({
            type: "GET",
            url: "daily_manager_get_pilots.php",
            data: { search: searchFilter, status: statusFilter, context: 'manage' },
            dataType: "json",
            success: function (response) {
                $managePilotsTableBody.empty();
                if (!response.success || !response.data || !Array.isArray(response.data.pilots)) {
                    $managePilotsTableBody.html('<tr><td colspan="6" class="text-center text-danger">Error: Could not load pilot data.</td></tr>');
                    return;
                }
                const pilots = response.data.pilots;
                if (pilots.length === 0) {
                    $managePilotsTableBody.html('<tr><td colspan="6" class="text-center">No pilots found.</td></tr>');
                    return;
                }

                console.log('Loaded pilots count:', pilots.length);

                pilots.forEach(function (pilot) {
                    const fullName = escapeHtml(pilot.firstname) + ' ' + escapeHtml(pilot.lastname);
                    const status = pilot.is_active == 1 ? '<span class="label label-success">Active</span>' : '<span class="label label-danger">Inactive</span>';
                    const toggleStatusBtnText = pilot.is_active == 1 ? 'Deactivate' : 'Activate';
                    const toggleStatusBtnClass = pilot.is_active == 1 ? 'btn-warning' : 'btn-success';
                    let actions = `<div class="btn-group">
                    <button class="btn btn-xs btn-primary edit-pilot-btn" data-userid="${pilot.id}">Edit</button>
                    <button class="btn btn-xs ${toggleStatusBtnClass} toggle-pilot-status-btn" data-userid="${pilot.id}" data-current-status="${pilot.is_active}">${toggleStatusBtnText}</button>`;
                    if (parseInt(pilot.is_active, 10) === 0) {
                        actions += `<button class="btn btn-xs btn-danger delete-pilot-btn" data-userid="${pilot.id}" data-pilotname="${fullName}">Delete</button>`;
                    }
                    actions += `</div>`;
                    const rowHtml = `<tr>
                    <td>${fullName}</td>
                    <td>${escapeHtml(pilot.username)}</td>
                    <td>${escapeHtml(pilot.email)}</td>
                    <td style="text-transform: capitalize;">${pilot.role_name ? escapeHtml(pilot.role_name) : '<span class="text-muted">N/A</span>'}</td>
                    <td>${status}</td>
                    <td class="text-center">${actions}</td>
                </tr>`;
                    $managePilotsTableBody.append(rowHtml);
                });
            },
            error: function (xhr) {
                $managePilotsTableBody.html('<tr><td colspan="6" class="text-center text-danger">A network error occurred.</td></tr>');
                console.error("AJAX call to get pilots failed:", xhr.responseText);
            },
            complete: function () {
                // RESET the loading flag
                isCurrentlyLoadingPilots = false;
            }
        });
    }

    // Event listeners for Manage Pilots Tab - MODIFIED VERSION
    $('div.tabs div[data-tab-toggle="manage-pilots"]').on('click', function () {
        loadPilotsForManager();
    });

    // Only load if the tab is active on initial page load
    if ($('div.tabs div[data-tab-toggle="manage-pilots"].active').length) {
        // Use setTimeout to ensure this doesn't conflict with other initial loads
        setTimeout(function () {
            loadPilotsForManager();
        }, 100);
    }

    $('#applyPilotFiltersBtn').on('click', function () {
        loadPilotsForManager();
    });

    $('#pilot-search-filter').on('keypress', function (e) {
        if (e.which === 13) {
            $('#applyPilotFiltersBtn').click();
        }
    });

    // Handler for Activate/Deactivate
    $managePilotsTableBody.on('click', '.toggle-pilot-status-btn', function () {
        const $button = $(this);
        const userId = $button.data('userid');
        const action = ($button.data('current-status') == 1) ? 'deactivate' : 'activate';
        const pilotName = $button.closest('tr').find('td:first').text();
        if (!confirm(`Are you sure you want to ${action.toUpperCase()} "${pilotName}"?`)) return;

        const originalButtonContent = $button.html();
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.ajax({
            url: 'daily_manager_pilot_activate_deactivate.php',
            type: 'POST',
            data: { user_id: userId, action: action, csrf_token: $("#csrf_token_manager").val() },
            dataType: 'json',
            success: function (response) {
                updateCsrfToken(response.new_csrf_token);
                if (response.success) {
                    showNotification($managePilotsAlertContainer, 'success', response.message);
                    loadPilotsForManager();
                } else {
                    showNotification($managePilotsAlertContainer, 'danger', response.message);
                    $button.prop('disabled', false).html(originalButtonContent);
                }
            },
            // --- THIS IS THE IMPROVED ERROR HANDLER ---
            error: function (xhr) {
                // Always try to update the CSRF token, even on failure, as the server sends a new one.
                if (xhr.responseJSON && xhr.responseJSON.new_csrf_token) {
                    updateCsrfToken(xhr.responseJSON.new_csrf_token);
                }

                let errorMsg = "An unexpected error occurred. Please try again.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    // Use the specific error message from our PHP script
                    errorMsg = xhr.responseJSON.message;
                } else if (xhr.status === 403) {
                    // Provide a user-friendly message for a 403 error
                    errorMsg = "Permission Denied. Your security session may have expired. Please refresh the page.";
                }

                showNotification($managePilotsAlertContainer, 'danger', errorMsg);
                $button.prop('disabled', false).html(originalButtonContent);
            }
        });
    });

    // Handler for Permanent Delete
    $managePilotsTableBody.on('click', '.delete-pilot-btn', function () {
        const $button = $(this);
        const userId = $button.data('userid');
        const pilotName = $button.data('pilotname');
        if (!confirm(`Are you sure you want to PERMANENTLY DELETE "${pilotName}"?\nThis cannot be undone.`)) return;

        const originalButtonContent = $button.html();
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.ajax({
            url: 'daily_manager_delete_pilot.php',
            type: 'POST',
            data: { user_id: userId, csrf_token: $("#csrf_token_manager").val() },
            dataType: 'json',
            success: function (response) {
                updateCsrfToken(response.new_csrf_token);
                if (response.success) {
                    showNotification($managePilotsAlertContainer, 'success', response.message);
                    loadPilotsForManager();
                } else {
                    showNotification($managePilotsAlertContainer, 'danger', response.message);
                    $button.prop('disabled', false).html(originalButtonContent);
                }
            },
            error: function (xhr) {
                if (xhr.responseJSON && xhr.responseJSON.new_csrf_token) { updateCsrfToken(xhr.responseJSON.new_csrf_token); }
                const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : `Server error (Status: ${xhr.status}).`;
                showNotification($managePilotsAlertContainer, 'danger', errorMsg);
                $button.prop('disabled', false).html(originalButtonContent);
            }
        });
    });

    function loadAvailableCrafts() {
        const $tbody = $('#manager_crafts_checkbox_list').find('tbody');
        $tbody.html('<tr><td colspan="3" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');

        $.ajax({
            url: "contract_get_all_crafts.php",
            dataType: "json",
            success: function (response) {
                $tbody.empty();
                if (response.success && response.crafts && response.crafts.length > 0) {
                    const uniqueCraftTypes = [...new Map(response.crafts.map(item => [item['craft_type'], item])).values()];
                    $.each(uniqueCraftTypes, function (index, craft) {
                        const craftType = craft.craft_type;
                        const safeCraftTypeName = craftType.replace(/[^a-zA-Z0-9]/g, '_');

                        // --- NEW "DOUBLE SELECTION" HTML ---
                        const craftHtml = `
                            <tr data-craft-type-name="${escapeHtml(craftType)}">
                                <td style="vertical-align: middle;">
                                    <div class="checkbox" style="margin:0;"><label>
                                        <input type="checkbox" class="craft-assign-checkbox" value="${escapeHtml(craftType)}">
                                        <strong>${escapeHtml(craftType)}</strong>
                                    </label></div>
                                </td>
                                <td>
                                    <!-- Group 1: Flying Position (Mandatory) -->
                                    <div class="position-radios">
                                        <strong>Position:</strong>
                                        <label class="radio-inline"><input type="radio" name="position_for_craft_${safeCraftTypeName}" value="PIC" disabled> PIC</label>
                                        <label class="radio-inline"><input type="radio" name="position_for_craft_${safeCraftTypeName}" value="SIC" disabled> SIC</label>
                                    </div>
                                    <!-- Group 2: Training Qualification (Optional) -->
                                    <div class="qualification-checks" style="margin-top: 5px;">
                                        <strong>Qualification:</strong>
                                        <label class="checkbox-inline"><input type="checkbox" name="is_tri_for_craft_${safeCraftTypeName}" value="1" disabled> <span class="text-info">TRI</span></label>
                                        <label class="checkbox-inline"><input type="checkbox" name="is_tre_for_craft_${safeCraftTypeName}" value="1" disabled> <span class="text-primary">TRE</span></label>
                                    </div>
                                </td>
                                <td class="text-center" style="vertical-align: middle;">
                                    <a href="#" class="delete-craft-type-btn text-danger" title="Delete craft type">
                                        <i class="fa fa-trash-o"></i>
                                    </a>
                                </td>
                            </tr>`;
                        $tbody.append(craftHtml);
                    });
                } else {
                    $tbody.html('<tr><td colspan="3" class="text-center text-danger">No craft types found.</td></tr>');
                }
            }
        });
    }

    function loadAvailableContracts() {
        $contractsListContainer.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading contracts...</p>');
        $.ajax({
            url: "contract_get_all_contracts.php", dataType: "json",
            success: function (response) {
                $contractsListContainer.empty();
                if (response.success && response.contracts && response.contracts.length > 0) {
                    $.each(response.contracts, function (index, contract) {
                        const contractName = `${contract.contract} (${contract.customer_name})`;
                        const contractHtml = `<div class="checkbox"><label><input type="checkbox" class="contract-assign-checkbox" name="assignments[contracts][]" value="${contract.contractid}"> ${escapeHtml(contractName)}</label> <a href="#" class="delete-contract-btn text-danger" data-contract-id="${contract.contractid}" data-contract-name="${escapeHtml(contractName)}" title="Delete contract"><i class="fa fa-trash-o"></i></a></div>`;
                        $contractsListContainer.append(contractHtml);
                    });
                } else {
                    $contractsListContainer.html('<p class="text-danger">No contracts found.</p>');
                }
            },
            error: function () { $contractsListContainer.html('<p class="text-danger">Error loading contracts.</p>'); }
        });
    }

    // ==================================================
    // === CREATE NEW PILOT TAB FUNCTIONALITY         ===
    // ==================================================
    if ($("#createNewPilotForm").length > 0) {
        const $createForm = $('#createNewPilotForm');
        let isSubmitting = false; // ADD THIS FLAG

        // Form Submission
        $createForm.on('submit', function (e) {
            e.preventDefault();

            // ADD THIS CHECK to prevent duplicate submissions
            if (isSubmitting) {
                console.log('Form submission already in progress...');
                return;
            }

            isSubmitting = true; // SET FLAG

            const $button = $('#submitNewPilotBtn');
            const originalButtonText = $button.html();
            $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');

            // Use a clearer variable for the alert container
            const $createPilotAlertContainer = $('#create-pilot-alert-container');
            $createPilotAlertContainer.empty();

            const userData = {
                firstname: $('#newPilot_firstname').val(),
                lastname: $('#newPilot_lastname').val(),
                user_nationality: $('#newPilot_user_nationality').val(),
                hire_date: $('#newPilot_hire_date').val(), // dd-mm-yyyy format
                email: $('#newPilot_email').val(),
                phone: $('#newPilot_phone').val(),
                phonetwo: $('#newPilot_phonetwo').val(),
                username: $('#newPilot_username').val(),
                password: $('#newPilot_password').val(),
                confpassword: $('#newPilot_confpassword').val(),
                nal_license: $('#newPilot_nal_license').val(),
                for_license: $('#newPilot_for_license').val(),
                role_ids: $('input[name="role_ids[]"]:checked').map(function () { return $(this).val(); }).get(),
                csrf_token: $("#csrf_token_manager").val(),
                assignments: {
                    contracts: $('input.contract-assign-checkbox:checked').map(function () { return $(this).val(); }).get(),
                    crafts: [] // We now send an array of objects
                    // edit_crafts: []
                }
            };

            $('input.craft-assign-checkbox:checked').each(function () {
                const craftType = $(this).val();
                const safeName = craftType.replace(/[^a-zA-Z0-9]/g, '_');

                const assignment = {
                    craft_type: craftType,
                    position: $(`input[name="position_for_craft_${safeName}"]:checked`).val() || 'PIC',
                    is_tri: $(`input[name="is_tri_for_craft_${safeName}"]`).is(':checked') ? 1 : 0,
                    is_tre: $(`input[name="is_tre_for_craft_${safeName}"]`).is(':checked') ? 1 : 0
                };
                userData.assignments.crafts.push(assignment);
            });

            console.log('Sending pilot creation request...');

            $.ajax({
                url: 'daily_manager_create_new_pilot.php', type: 'POST', data: userData, dataType: 'json',
                success: function (response) {
                    console.log('Pilot creation response:', response);

                    if (response.success) {
                        showNotification($managePilotsAlertContainer, 'success', response.message);

                        const successMessage = `<p><strong>New pilot account created successfully:</strong></p>
                            <p>Name: ${userData.firstname} ${userData.lastname}<br>Username: ${userData.username}</p>`;
                        $('#pilotCreationDetails').html(successMessage);
                        $('#pilotCreationSuccessModal').modal('show');

                        $createForm[0].reset();
                        // $('input.craft-assign-checkbox, input.contract-assign-checkbox').prop('checked', false).trigger('change');

                        // $('input[name="role_ids[]"][value="1"]').prop('checked', true);
                        // loadPilotsForManager();
                        if (typeof loadPilotsForManager === 'function') {
                            loadPilotsForManager();
                        }
                    } else {
                        showNotification($createPilotAlertContainer, 'danger', `Error: ${response.error || 'Unknown error'}`);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Pilot creation error:', status, error); // ADD FOR DEBUGGING
                    const errorMsg = xhr.responseJSON ? xhr.responseJSON.error : 'A network error occurred.';
                    showNotification($createPilotAlertContainer, 'danger', `Error: ${errorMsg}`);
                },
                complete: function () {
                    // RESET THE FLAG in complete to ensure it always gets reset
                    isSubmitting = false;
                    $button.prop('disabled', false).html(originalButtonText);
                }
            });
        });
        // Initial data load for the form (this part is correct)
        if (typeof loadAvailableCrafts === 'function') loadAvailableCrafts();
        if (typeof loadAvailableContracts === 'function') loadAvailableContracts();
    } // end if create pilot form exists

    // ==================================================
    // === EDIT PILOT MODAL FUNCTIONALITY             ===
    // ==================================================
    let currentlyEditingUserId = null;
    const $editPilotModal = $('#editPilotModal');

    const $editPilotForm = $('#editPilotForm');
    const $editPilotFeedback = $('#edit-pilot-modal-feedback');

    $managePilotsTableBody.on('click', '.edit-pilot-btn', function () {
        currentlyEditingUserId = $(this).data('userid');
        $editPilotForm.hide();
        $editPilotFeedback.html('<i class="fa fa-spinner fa-spin fa-2x"></i><p>Loading pilot data...</p>').show();
        $editPilotModal.modal('show');

        $.when(
            $.ajax({ url: 'daily_manager_get_pilot_details.php', data: { user_id: currentlyEditingUserId }, dataType: 'json' }),
            $.ajax({ url: 'contract_get_all_crafts.php', dataType: 'json' }),
            $.ajax({ url: 'contract_get_all_contracts.php', dataType: 'json' }),
            $.ajax({ url: 'daily_manager_get_all_roles.php', dataType: 'json' })
        ).done(function (pilotDetailsResp, allCraftsResp, allContractsResp, allRolesResp) {
            const pilotDetailsData = pilotDetailsResp[0];
            const allCraftsData = allCraftsResp[0];
            const allContractsData = allContractsResp[0];
            const allRolesData = allRolesResp[0];
            if (pilotDetailsData.success && allCraftsData.success && allContractsData.success && allRolesData.success) {
                populateEditPilotForm(pilotDetailsData.data, allCraftsData.crafts, allContractsData.contracts, allRolesData.roles);
                $editPilotFeedback.hide();
                $editPilotForm.show();
            } else {
                $editPilotFeedback.html('<p class="text-danger">Error: Could not load data.</p>');
            }
        }).fail(function () {
            $editPilotFeedback.html('<p class="text-danger">A network error occurred.</p>');
        });
    });

    function populateEditPilotForm(userData, allCrafts, allContracts, allRoles) {
        const details = userData.details;

        // --- POPULATE ALL FIELDS ---
        $('#editUser_id').val(details.id);
        $('#editPilot_firstname').val(details.firstname);
        $('#editPilot_lastname').val(details.lastname);
        $('#editPilot_email').val(details.email);
        $('#editPilot_username').val(details.username);
        $('#editPilot_user_nationality').val(details.user_nationality);
        $('#editPilot_phone').val(details.phone);
        $('#editPilot_phonetwo').val(details.phonetwo);
        $('#editPilot_nal_license').val(details.nal_license);
        $('#editPilot_for_license').val(details.for_license);
        $('#editPilot_hire_date').val(details.hire_date);

        // --- CRITICAL FIX: Re-initialize Flatpickr for the hire date field in edit modal ---
        if (typeof flatpickr === 'function') {
            // Destroy any existing Flatpickr instance on this field
            const hireDateInput = document.getElementById('editPilot_hire_date');
            if (hireDateInput && hireDateInput._flatpickr) {
                hireDateInput._flatpickr.destroy();
            }

            // Re-initialize Flatpickr with the same configuration
            flatpickr('#editPilot_hire_date', {
                altInput: true,
                altFormat: "M d, Y",    // Display: "Sept 4, 2025"
                dateFormat: "Y-m-d",    // Submit: "2025-09-04"
                defaultDate: details.hire_date // Set the date from database
            });
        }

        // --- Populate Craft Types ---
        const $craftsContainer = $('#edit_manager_crafts_checkbox_list tbody');
        $craftsContainer.empty();
        const uniqueCraftTypes = [...new Map(allCrafts.map(item => [item['craft_type'], item])).values()];

        uniqueCraftTypes.forEach(craft => {
            const craftType = craft.craft_type;
            const safeCraftTypeName = craftType.replace(/[^a-zA-Z0-9]/g, '_');
            const assignment = userData.assigned_crafts.find(ac => ac.craft_type === craftType);

            const isAssigned = !!assignment;
            const position = isAssigned ? assignment.position : 'PIC';
            const isTri = isAssigned && parseInt(assignment.is_tri) === 1;
            const isTre = isAssigned && parseInt(assignment.is_tre) === 1;

            const craftHtml = `
            <tr data-craft-type-name="${escapeHtml(craftType)}">
                <td style="vertical-align: middle;">
                    <div class="checkbox" style="margin:0;"><label>
                        <input type="checkbox" class="edit-craft-assign-checkbox" value="${escapeHtml(craftType)}" ${isAssigned ? 'checked' : ''}>
                        <strong>${escapeHtml(craftType)}</strong>
                    </label></div>
                </td>
                <td>
                    <!-- Group 1: Flying Position -->
                    <div class="position-radios">
                        <strong>Position:</strong>
                        <label class="radio-inline"><input type="radio" name="edit_position_for_craft_${safeCraftTypeName}" value="PIC" ${position === 'PIC' ? 'checked' : ''} ${!isAssigned ? 'disabled' : ''}> PIC</label>
                        <label class="radio-inline"><input type="radio" name="edit_position_for_craft_${safeCraftTypeName}" value="SIC" ${position === 'SIC' ? 'checked' : ''} ${!isAssigned ? 'disabled' : ''}> SIC</label>
                    </div>
                    <!-- Group 2: Training Qualification -->
                    <div class="qualification-checks" style="margin-top: 5px;">
                        <strong>Qualification:</strong>
                        <label class="checkbox-inline"><input type="checkbox" class="is-tri-checkbox" name="edit_is_tri_for_craft_${safeCraftTypeName}" value="1" ${isTri ? 'checked' : ''} ${!isAssigned ? 'disabled' : ''}> <span class="text-info">TRI</span></label>
                        <label class="checkbox-inline"><input type="checkbox" class="is-tre-checkbox" name="edit_is_tre_for_craft_${safeCraftTypeName}" value="1" ${isTre ? 'checked' : ''} ${!isAssigned ? 'disabled' : ''}> <span class="text-primary">TRE</span></label>
                    </div>
                </td>
            </tr>`;
            $craftsContainer.append(craftHtml);
        });

        // --- Populate Contracts ---
        const $contractsContainer = $('#edit_manager_contracts_checkbox_list');
        $contractsContainer.empty();
        const assignedContractIds = new Set(userData.assigned_contracts.map(String));
        allContracts.forEach(contract => {
            const isChecked = assignedContractIds.has(String(contract.contractid));
            const contractHtml = `<div class="checkbox"><label><input type="checkbox" name="edit_contract_ids[]" value="${contract.contractid}" ${isChecked ? 'checked' : ''}> ${escapeHtml(contract.contract)} (${escapeHtml(contract.customer_name)})</label></div>`;
            $contractsContainer.append(contractHtml);
        });

        // --- THIS IS THE CORRECTED SECTION FOR POPULATING ROLES ---
        const $rolesContainer = $('#edit_manager_roles_checkbox_list');
        $rolesContainer.empty();

        // *** THIS IS THE MISSING LINE THAT CAUSED THE ERROR ***
        const assignedRoleIds = new Set(userData.assigned_roles.map(String));

        allRoles.sort((a, b) => parseInt(a.id) - parseInt(b.id));

        let rolesHtml = '';
        allRoles.forEach(role => {
            const isChecked = assignedRoleIds.has(String(role.id));
            rolesHtml += `
            <div class="col-md-6">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="edit_role_ids[]" value="${role.id}" ${isChecked ? 'checked' : ''}>
                        ${escapeHtml(role.role_name)}
                    </label>
                </div>
            </div>`;
        });

        $rolesContainer.html(`<div class="row">${rolesHtml}</div>`);
        // --- END OF CORRECTED SECTION ---
    }

    $('#savePilotChangesBtn').on('click', function (e) {
        e.preventDefault();
        const $button = $(this);
        const originalButtonText = $button.html();

        // --- NEW: Client-Side Validation ---
        const firstname = $('#editPilot_firstname').val().trim();
        const lastname = $('#editPilot_lastname').val().trim();
        const email = $('#editPilot_email').val().trim();
        const newPassword = $('#editPilot_new_password').val();
        const confirmPassword = $('#editPilot_confirm_password').val();
        const hireDate = $('#editPilot_hire_date').val();

        if (!firstname || !lastname || !email) {
            alert("Error: First Name, Last Name, and Email are required.");
            return;
        }
        if (newPassword && newPassword.length < 8) {
            alert("Error: New password must be at least 8 characters long.");
            return;
        }
        if (newPassword !== confirmPassword) {
            alert("Error: Passwords do not match.");
            return;
        }

        // A simple regex to check for yyyy-mm-dd format.
        if (hireDate && !/^\d{4}-\d{2}-\d{2}$/.test(hireDate)) {
            alert("Error: Invalid Hire Date format. Please ensure it is YYYY-MM-DD.");
            return;
        }
        // --- END: Client-Side Validation ---

        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        // The userData object now uses the validated variables
        const userData = {
            user_id: currentlyEditingUserId,
            firstname: firstname,
            lastname: lastname,
            email: email,
            user_nationality: $('#editPilot_user_nationality').val(),
            phone: $('#editPilot_phone').val(),
            phonetwo: $('#editPilot_phonetwo').val(),
            nal_license: $('#editPilot_nal_license').val(),
            for_license: $('#editPilot_for_license').val(),
            hire_date: hireDate,
            new_password: newPassword,
            confirm_new_password: confirmPassword,
            edit_role_ids: $('input[name="edit_role_ids[]"]:checked').map(function () { return $(this).val(); }).get(),
            edit_contract_ids: $('input[name="edit_contract_ids[]"]:checked').map(function () { return $(this).val(); }).get(),
            edit_crafts: [],
            csrf_token: $("#csrf_token_manager").val()
        };

        $('input.edit-craft-assign-checkbox:checked').each(function () {
            const craftType = $(this).val();
            const safeName = craftType.replace(/[^a-zA-Z0-9]/g, '_');

            const assignment = {
                craft_type: craftType,
                position: $(`input[name="edit_position_for_craft_${safeName}"]:checked`).val() || 'PIC',
                is_tri: $(`input[name="edit_is_tri_for_craft_${safeName}"]`).is(':checked') ? 1 : 0,
                is_tre: $(`input[name="edit_is_tre_for_craft_${safeName}"]`).is(':checked') ? 1 : 0
            };
            userData.edit_crafts.push(assignment);
        });

        console.log("DEBUG: Sending craft data:", userData.edit_crafts); // For debugging

        $.ajax({
            url: 'daily_manager_update_pilot.php',
            type: 'POST',
            contentType: 'application/json', // Add this
            data: JSON.stringify(userData),  // Convert to JSON string
            dataType: 'json',
            success: function (response) {
                updateCsrfToken(response.new_csrf_token);

                if (response.success) {
                    $editPilotModal.modal('hide');
                    showNotification($managePilotsAlertContainer, 'success', response.message);
                    loadPilotsForManager();
                } else {
                    alert('Error: ' + (response.error || 'An unknown error occurred.'));
                }
            },
            error: function (xhr) {
                // Handle CSRF token update from error response
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.new_csrf_token) {
                        // handleCsrfTokenUpdate(errorResponse);
                        updateCsrfToken(errorResponse.new_csrf_token); // Use the correct function name

                    }
                    const errorMsg = errorResponse.error || "A network error occurred.";
                    alert('Error: ' + errorMsg);
                } catch (e) {
                    alert('Error: A network error occurred.');
                }
            },
            complete: function () {
                $button.prop('disabled', false).html(originalButtonText);
            }
        });
    });

    // It handles checkbox changes for both the "Create Pilot" and "Edit Pilot" forms.
    $(document).on('change', 'input.craft-assign-checkbox, input.edit-craft-assign-checkbox', function () {
        const $thisCheckbox = $(this);
        const $tableRow = $thisCheckbox.closest('tr');

        // --- THE FIX IS HERE ---
        // We now use more specific selectors to find ONLY the controls within the row.
        // This prevents the master checkbox from being included in the selection.
        const $positionRadios = $tableRow.find('.position-radios input[type="radio"]');
        const $qualificationChecks = $tableRow.find('.qualification-checks input[type="checkbox"]');
        // --- END OF FIX ---

        if ($thisCheckbox.is(':checked')) {
            // Enable all the controls in the row
            $positionRadios.prop('disabled', false);
            $qualificationChecks.prop('disabled', false);

            // As a good user experience, default to 'PIC' if no position is selected yet.
            if ($positionRadios.filter(':checked').length === 0) {
                $positionRadios.filter('[value="PIC"]').prop('checked', true);
            }
        } else {
            // If the master checkbox is unchecked, disable and clear all other controls in the row.
            $positionRadios.prop('disabled', true).prop('checked', false);
            $qualificationChecks.prop('disabled', true).prop('checked', false);
        }
    });


    // The handler for the TRE > TRI hierarchy remains the same and is correct.
    $(document).on('change', '.is-tre-checkbox', function () {
        const $treCheckbox = $(this);
        const $triCheckbox = $treCheckbox.closest('.qualification-checks').find('.is-tri-checkbox');

        if ($treCheckbox.is(':checked')) {
            $triCheckbox.prop('checked', true).prop('disabled', true);
        } else {
            // Only re-enable the TRI checkbox if its parent craft is still selected.
            if ($treCheckbox.closest('tr').find('.edit-craft-assign-checkbox, .craft-assign-checkbox').is(':checked')) {
                $triCheckbox.prop('disabled', false);
            }
        }
    });

});