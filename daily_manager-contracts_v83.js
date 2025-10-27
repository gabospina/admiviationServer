// daily_manager-contracts.js - v83 INTEGRATED WORKING VERSION WITH CSRF TOKEN SUPPORT

// Add at the very top of the file
console.log("DEBUG: daily_manager-contracts.js loading...");
// console.log("DEBUG: jQuery version:", $?.fn?.jquery);
// console.log("DEBUG: Spectrum available:", typeof $.fn.spectrum !== 'undefined');

// Add CSRF token update function at the top level (outside document.ready)
// CSRF Token Strategy: Per-Session (token changes only on validation failures)
function updateCsrfToken(newToken) {
    console.log('Updating CSRF token to:', newToken);
    $('#csrf_token_manager').val(newToken);
    console.log('CSRF token updated successfully');
}

// Tab-specific functionality
$(document).ready(function () {
    console.log("DEBUG: daily_manager-contracts.js - tab-specific code loaded");

    // This safe-guard ensures this code only runs if the tab exists
    if ($('div[data-tab-toggle="manage-contracts"]').length === 0) {
        return;
    }

    const $contractsAlertContainer = $('#manager-contracts-alert-container');

    // =============================================================
    // === DATA LOADING & UI BUILDING FUNCTIONS                  ===
    // =============================================================

    // Fetches all necessary data for the ADD modal.
    function loadDataForAddModal() {
        $.getJSON("contract_get_all_customers.php", res => populateSelect('#managerNewContractCustomerSelect', res.customers, 'id', 'customer_name'));
        $.getJSON("pilots_get_all_crafts.php", res => populateSelect('#managerNewContractCraftSelect', res.crafts, 'id', 'registration', 'craft_type'));
        $.getJSON("contract_get_pilots.php", pilots => populateCheckboxes('#managerNewContractPilotsCheckboxes', pilots, 'id', 'firstname', 'lastname'));
    }

    // Fetches all necessary data for the EDIT modal.
    function loadDataForEditModal(contractData) {
        Promise.all([
            $.getJSON("contract_get_all_customers.php"),
            $.getJSON("pilots_get_all_crafts.php"),
            $.getJSON("contract_get_pilots.php")
        ]).then(([customersRes, craftsRes, pilots]) => {
            populateSelect('#editContractCustomerSelect', customersRes.customers, 'id', 'customer_name', [contractData.details.customer_id]);
            populateSelect('#editContractCraftSelect', craftsRes.crafts, 'id', 'registration', 'craft_type', contractData.assigned_craft_ids);
            populateCheckboxes('#editContractPilotsCheckboxes', pilots, 'id', 'firstname', 'lastname', contractData.assigned_pilot_ids);
        });
    }

    // Helper to populate a <select> element
    function populateSelect(selector, items, valKey, textKey, textKey2 = null, selectedVals = []) {
        const $select = $(selector).empty();
        if (items && items.length > 0) {
            const selectedSet = new Set(selectedVals.map(String));
            items.forEach(item => {
                const isSelected = selectedSet.has(String(item[valKey]));

                const displayText = (textKey2 && item[textKey2])
                    ? `${item[textKey]} (${item[textKey2]})`
                    : item[textKey];

                $select.append(`<option value="${item[valKey]}" ${isSelected ? 'selected' : ''}>${escapeHtml(displayText)}</option>`);
            });
        }
    }

    // Helper to populate a div with checkboxes
    function populateCheckboxes(selector, items, valKey, textKey1, textKey2, selectedVals = []) {
        const $container = $(selector).empty();
        if (items && items.length > 0) {
            const selectedSet = new Set(selectedVals.map(String));
            items.forEach(item => {
                const isSelected = selectedSet.has(String(item[valKey]));
                $container.append(`<div class="checkbox"><label><input type="checkbox" name="${valKey}s[]" value="${item[valKey]}" ${isSelected ? 'checked' : ''}> ${escapeHtml(item[textKey1])} ${escapeHtml(item[textKey2])}</label></div>`);
            });
        }
    }

    // --- THIS IS THE FIXED FUNCTION ---
    function loadManagerCustomerList() {
        $('#manager-customerList').html('<p class="text-center"><i class="fa fa-spinner fa-spin"></i></p>');
        $.ajax({
            // EXPLANATION: We add `cache: false` to the AJAX options. This is a jQuery shortcut
            // that automatically appends a unique timestamp parameter (like `_={timestamp}`)
            // to the URL, forcing the browser to bypass its cache and get fresh data.
            url: "contract_get_all_customers.php",
            dataType: 'json',
            cache: false, // <-- THIS IS THE FIX
            success: function (res) {
                let listHtml = '';
                if (res.success && Array.isArray(res.customers) && res.customers.length > 0) {
                    listHtml = "<table class='table table-bordered'><thead><tr><th>Customer Name</th><th>Action</th></tr></thead><tbody>";
                    res.customers.forEach(c => {
                        listHtml += `<tr><td>${escapeHtml(c.customer_name)}</td><td><button class='btn btn-danger btn-xs manager-delete-customer-btn' data-id='${c.id}' data-name='${escapeHtml(c.customer_name)}'>-</button></td></tr>`;
                    });
                    listHtml += "</tbody></table>";
                } else {
                    // If the request is successful but there are no customers for this company, show this message.
                    listHtml = '<p class="text-center text-muted">No customers found.</p>';
                }
                $('#manager-customerList').html(listHtml);
            },
            error: function () {
                // If the AJAX call itself fails, show an error.
                $('#manager-customerList').html('<p class="text-center text-danger">Failed to load customer list.</p>');
            }
        });
    }
    // --- END OF FIX ---

    function loadManagerContractList() {
        const $listContainer = $('#manager-contractsList');
        $listContainer.html('<p class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading contracts...</p>');

        $.ajax({
            url: 'contract_get_all_contracts.php',
            method: 'GET',
            dataType: 'json',
            cache: false, // It's good practice to add this here too.
            success: function (response) {
                if (response.success && response.contracts) {
                    let tableHtml = '<table class="table table-bordered table-striped"><thead><tr><th>Contract</th><th>Customer</th><th>Color</th><th>Actions</th></tr></thead><tbody>';

                    if (response.contracts.length === 0) {
                        tableHtml += '<tr><td colspan="4" class="text-center">No contracts found.</td></tr>';
                    } else {
                        response.contracts.forEach(function (contract) {

                            tableHtml += `<tr>
                            <td>${escapeHtml(contract.contract)}</td>
                            <td>${escapeHtml(contract.customer_name)}</td>
                            <td><div style="width: 25px; height: 25px; background-color: ${contract.color}; border: 1px solid #ccc; margin: auto;"></div></td>
                            <td>
                                <button class="btn btn-sm btn-info manager-edit-contract-btn" data-id="${contract.contractid}" data-name="${escapeHtml(contract.contract)}" data-customer-id="${contract.customer_id}" data-customer-name="${escapeHtml(contract.customer_name)}" data-color="${contract.color}" data-toggle="modal" data-target="#editContractModal"><i class="fa fa-pencil"></i> Edit</button>
                                <button class="btn btn-sm btn-danger manager-delete-contract-btn" data-id="${contract.contractid}" data-name="${escapeHtml(contract.contract)}"><i class="fa fa-trash"></i> Delete</button>
                            
                            <a href="contract_details.php?id=${contract.contractid}" class="btn btn-sm btn-secondary" target="_blank">
                                <i class="fa fa-eye"></i> View Details
                            </a>
                            </td>
                        </tr>`;
                            // --- END OF FIX ---
                        });
                    }
                    tableHtml += '</tbody></table>';
                    $listContainer.html(tableHtml);
                } else {
                    $listContainer.html(`<p class="text-danger text-center">Failed to load contracts: ${response.message || ''}</p>`);
                }
            },
            error: function () {
                $listContainer.html('<p class="text-danger text-center">AJAX Error loading contracts.</p>');
            }
        });
    }

    // ===== FIXED COLOR PICKER FUNCTIONS =====
    function initializeColorPickers() {
        // console.log("DEBUG: Initializing color pickers...");
        // console.log("DEBUG: Spectrum available:", typeof $.fn.spectrum !== 'undefined');

        if (typeof $.fn.spectrum !== 'function') return;

        const spectrumOptions = {
            preferredFormat: "hex",
            showInput: true,
            allowEmpty: false,
            showInitial: true,
            showPalette: true,
            showSelectionPalette: true,
            palette: [
                ["#000", "#444", "#666", "#999", "#ccc", "#eee", "#f3f3f3", "#fff"],
                ["#f00", "#f90", "#ff0", "#0f0", "#0ff", "#00f", "#90f", "#f0f"]
            ],
            showButtons: false,
            cancelText: "Cancel",
            chooseText: "Choose"
        };

        try {
            // Initialize Add Contract color picker
            $('#managerNewContractColor').spectrum(spectrumOptions);
            // console.log("DEBUG: Add contract color picker initialized");

            // Initialize Edit Contract color picker  
            $('#editContractColor').spectrum(spectrumOptions);
            // console.log("DEBUG: Edit contract color picker initialized");

        } catch (error) {
            // console.error("DEBUG: Error initializing color pickers:", error);
        }
    }

    // FIXED getColorValue function
    function getColorValue(selector) {
        if (typeof $.fn.spectrum !== 'undefined') {
            const color = $(selector).spectrum('get');
            // Check if color is valid before calling toHexString
            if (color && typeof color.toHexString === 'function') {
                return color.toHexString();
            } else {
                console.warn("DEBUG: Invalid color object, using default");
                return '#3f51b5'; // Default color
            }
        } else {
            // Fallback to native color input
            return $(selector).val() || '#3f51b5';
        }
    }

    // console.log("DEBUG: Manage contracts tab found, initializing tab-specific functions...");

    // =============================================================
    // === EVENT LISTENERS                                       ===
    // =============================================================

    $('#managerAddNewContractModal').on('show.bs.modal', function () {
        loadDataForAddModal();
        $('#managerNewContractColor').spectrum('set', '#3f51b5');
    });

    $('#managerSubmitNewContractBtn').off('click').on('click', function (e) {
        e.preventDefault();
        const $submitBtn = $(this);
    
        // Gather selected pilot IDs from checkboxes
        const selectedPilotIds = [];
        $('#managerNewContractPilotsCheckboxes input[name="pilot_ids[]"]:checked').each(function () {
            selectedPilotIds.push($(this).val());
        });
    
        // Gather selected craft IDs from select
        const selectedCraftIds = $("#managerNewContractCraftSelect").val() || [];
    
        const dataToSend = {
            contract_name: $("#managerNewContractName").val().trim(),
            customer_id: $("#managerNewContractCustomerSelect").val(),
            color: getColorValue('#managerNewContractColor'),
            craft_ids: selectedCraftIds,
            pilot_ids: selectedPilotIds,
            form_token: $("#form_token_manager").val() // ✅ FIXED: Use form_token
        };
    
        // Validation
        if (!dataToSend.contract_name || !dataToSend.customer_id) {
            alert('Please enter a Contract Name and select a Customer.');
            return;
        }
    
        $submitBtn.prop("disabled", true).html("<i class='fa fa-spinner fa-spin'></i> Processing...");
    
        $.ajax({
            url: 'contract_add_contract.php',
            type: 'POST',
            data: dataToSend,
            dataType: 'json',
            success: function (response) {
                // Update CSRF token if a new one is provided
                if (response.new_csrf_token) {
                    updateCsrfToken(response.new_csrf_token);
                }
    
                if (response.success) {
                    alert('Contract created successfully!');
                    $('#managerAddNewContractModal').modal('hide');
                    // Clear form
                    $("#managerNewContractName").val('');
                    $("#managerNewContractPilotsCheckboxes").empty();
                    $("#managerNewContractCraftSelect").val([]);
    
                    // Refresh contract list if we're in the manage contracts tab
                    if ($('div[data-tab-toggle="manage-contracts"]').length > 0) {
                        loadManagerContractList();
                    }
                } else {
                    alert('Error: ' + (response.message || 'Failed to create contract'));
                }
            },
            error: function (xhr) {
                // Handle CSRF token updates even on error
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.new_csrf_token) {
                        updateCsrfToken(errorResponse.new_csrf_token);
                    }
                } catch (e) {
                    // Ignore JSON parse errors for token updates
                }
    
                const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'A server error occurred.';
                alert('AJAX Error: ' + errorMsg);
            },
            complete: function () {
                $submitBtn.prop("disabled", false).html("Submit Contract");
            }
        });
    });

    // =============================================================
    // === "ADD NEW CUSTOMER" MODAL LOGIC                        ===
    // =============================================================

    // --- Handler for the "Add Customer" button inside the modal ---
    // New Customer Submission
    $('#managerSubmitNewCustomerBtn').on('click', function () {
        const customerName = $("#managerNewCustomerName").val().trim();
        if (!customerName) return alert("Customer name cannot be empty.");
    
        // === FIX: Read from the new ID ===
        const formToken = $("#form_token_manager").val();

        $.ajax({
            type: "POST",
            url: "contract_add_customer.php",
            data: {
                customer_name: customerName,
                form_token: formToken // ✅ ADD CSRF TOKEN
            },
            dataType: "json",
            success: function (response) {
                // Update CSRF token if a new one is provided
                if (response.new_csrf_token) {
                    $("#form_token_manager").val(response.new_csrf_token);
                }

                if (response.success) {
                    console.log('DEBUG: Success response:', response);
                    alert("Customer added successfully!");
                    $('#managerAddNewCustomerModal').modal('hide');
                    loadManagerCustomerList();
                    if (typeof loadDataForNewContractModal === 'function') {
                        loadDataForNewContractModal();
                    }
                } else {
                    alert("Error: " + response.message);
                }
            },
            error: function (xhr) {
                console.log('DEBUG: Error response:', xhr.responseText);
                // Handle CSRF token updates even on error
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.new_csrf_token) {
                        updateCsrfToken(errorResponse.new_csrf_token);
                    }
                    alert("Error: " + (errorResponse.message || "A server error occurred."));
                } catch (e) {
                    alert("A network error occurred. Please check your connection.");
                }
                // alert("A server error occurred.");
            }
        });
    });

    // --- "EDIT CONTRACT" MODAL ---
    $(document).on('click', '.manager-edit-contract-btn', function () {
        const contractId = $(this).data('id');
        $('#editContractModal').modal('show'); // Open the modal first

        // Show a loading state in the modal body
        $('#editContractPilotsCheckboxes, #editContractCraftSelect').html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading assignments...</p>');

        // Fetch all details for this contract
        $.getJSON(`contract_get_details.php?contract_id=${contractId}`, response => {
            if (response.success) {
                const data = response.data;
                $('#editContractId').val(contractId);
                $('#editContractName').val(data.details.contract_name);
                $('#editContractColor').spectrum('set', data.details.color);
                loadDataForEditModal(data); // Load dropdowns and pre-select items
            }
        });
    });

    $('#saveEditedContract').on('click', function () {
        const $saveButton = $(this);

        const dataToSend = {
            contract_id: $('#editContractId').val(),
            contract_name: $('#editContractName').val().trim(),
            customer_id: $('#editContractCustomerSelect').val(),
            color: $('#editContractColor').spectrum('get').toHexString(),
            craft_ids: $('#editContractCraftSelect').val() || [],
            pilot_ids: $('#editContractPilotsCheckboxes input:checked').map((i, el) => $(el).val()).get(),
            form_token: $("#form_token_manager").val() // ✅ FIXED
        };
        
        // ✅ ADD DEBUG LOGGING
        console.log("DEBUG: Sending contract edit data:", dataToSend);
        console.log("DEBUG: CSRF token length:", dataToSend.form_token ? dataToSend.form_token.length : 0);

        if (!dataToSend.contract_name || !dataToSend.customer_id) {
            alert('Contract Name and Customer are required.');
            return;
        }

        $saveButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'contract_edit_contract.php',
            method: 'POST',
            data: dataToSend, // Send all three editable fields to the PHP script
            dataType: 'json',
            success: function (response) {
                // Update CSRF token if a new one is provided
                if (response.new_csrf_token) {
                    updateCsrfToken(response.new_csrf_token);
                }

                if (response.success) {
                    $('#editContractModal').modal('hide');
                    showNotification($contractsAlertContainer, 'success', 'Contract updated successfully!');

                    loadManagerContractList();
                    loadManagerCustomerList();
                } else {
                    alert('Error: ' + (response.message || 'Failed to update contract.'));
                }
            },
            error: function (xhr) {
                // Handle CSRF token updates even on error
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.new_csrf_token) {
                        updateCsrfToken(errorResponse.new_csrf_token);
                    }
                } catch (e) {
                    // Ignore JSON parse errors for token updates
                }
                alert('A critical server error occurred.');
            },
            complete: function () {
                $saveButton.prop('disabled', false).html('Save Changes');
            }
        });
    });

    $(document).on('click', '.manager-delete-contract-btn', function () {
        const $button = $(this);
        const contractId = $button.data("id");
        const contractName = $button.data("name");
        if (confirm(`Are you sure you want to delete the contract "${contractName}"?`)) {
            $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            $.ajax({
                type: "POST",
                url: "contract_delete_contract.php",
                data: {
                    contract_id: contractId,
                    form_token: $("#form_token_manager").val() // ✅ FIXED
                },
                dataType: "json",
                success: function (response) {
                    // Update CSRF token if a new one is provided
                    if (response.new_csrf_token) {
                        updateCsrfToken(response.new_csrf_token);
                    }

                    if (response.success) {
                        showNotification($contractsAlertContainer, 'success', 'Contract deleted successfully.');
                        loadManagerContractList();
                    } else {
                        alert('Error: ' + (response.message || "Could not delete contract."));
                        $button.prop('disabled', false).html('<i class="fa fa-trash"></i> Delete');
                    }
                },
                error: function (xhr) {
                    // Handle CSRF token updates even on error
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.new_csrf_token) {
                            updateCsrfToken(errorResponse.new_csrf_token);
                        }
                    } catch (e) {
                        // Ignore JSON parse errors for token updates
                    }
                    alert('A server error occurred.');
                    $button.prop('disabled', false).html('<i class="fa fa-trash"></i> Delete');
                }
            });
        }
    });

    $(document).on('click', '.manager-delete-customer-btn', function () {
        const $button = $(this);
        const customerId = $button.data("id");
        const customerName = $button.data("name");
        const formToken = $("#form_token_manager").val();

        // TEMPORARY DEBUG
        console.log("DEBUG: Sending delete request with:", {
            customer_id: customerId,
            form_token: formToken,
            token_length: formToken ? formToken.length : 0,
            token_element: $("#form_token_manager").length
        });

        if (!confirm(`Are you sure you want to delete the customer "${customerName}"?`)) {
            return;
        }

        // Provide immediate UI feedback
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        $.ajax({
            type: "POST",
            url: "contract_delete_customer.php", // Calls our new, secure script
            data: {
                customer_id: customerId,
                form_token: $("#form_token_manager").val()
            },
            dataType: "json",
            success: function (response) {
                // Update CSRF token if a new one is provided
                if (response.new_csrf_token) {
                    $("#form_token_manager").val(response.new_csrf_token);
                }

                if (response.success) {
                    // On success, show a success message and reload the customer list
                    showNotification($contractsAlertContainer, 'success', response.message);
                    loadManagerCustomerList();
                    loadManagerContractList(); // Also reload contracts in case something changed
                } else {
                    // This 'else' block will likely not be hit if the PHP uses HTTP error codes,
                    // but it's good for safety.
                    showNotification($contractsAlertContainer, 'danger', response.message || "An unknown error occurred.");
                    $button.prop('disabled', false).html('-'); // Restore button on failure
                }
            },
            error: function (xhr) {
                // --- THIS IS THE FIX ---
                // Handle CSRF token updates even on error
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.new_csrf_token) {
                        $("#form_token_manager").val(errorResponse.new_csrf_token);
                    }
                } catch (e) {
                    // Ignore JSON parse errors for token updates
                }

                // This block will now catch the "409 Conflict" error from our PHP script.
                let errorMessage = "A server or network error occurred.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showNotification($contractsAlertContainer, 'warning', errorMessage); // Use a 'warning' for this type of error
                $button.prop('disabled', false).html('-'); // Restore the button
            }
        });
    });

    loadManagerCustomerList();
    loadManagerContractList();
    initializeColorPickers();

    // Utility functions
    function escapeHtml(text) {
        if (typeof text !== 'string') return text;
        const map = {
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function showNotification($container, type, message) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        $container.html(`<div class="alert ${alertClass} alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>${message}</div>`);
    }

});