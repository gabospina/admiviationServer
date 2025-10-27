// daily_manager-crafts.js v83 - FINAL VERSION with Drag-and-Drop

$(document).ready(function () {
    // This safe-guard ensures this code only runs if the tab exists
    if ($('div[data-tab-toggle="manage-crafts"]').length > 0) {

        const $craftsAlertContainer = $('#manager-crafts-alert-container');

        /**
         * Loads and builds the craft management table with sortable functionality.
         */
        function loadAndBuildManagerCraftsTable() {
            const $container = $("#manager-crafts-table-container");
            $container.html('<p class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading crafts...</p>');

            $.ajax({
                type: "GET",
                url: "contract_get_all_crafts.php",
                dataType: "json",
                success: function (result) {
                    if (!result.success || !Array.isArray(result.crafts)) {
                        $container.html('<p class="text-danger">Error: Could not load craft data.</p>');
                        return;
                    }

                    let tableHtml = `<div class="alert alert-info"><i class="fa fa-info-circle"></i> Drag and drop rows to change their display order on the schedule.</div>
                        <table class='table table-condensed table-bordered'>
                        <thead><tr><th style="width:50px;">Order</th><th>Craft Type</th><th>Registration</th><th>Time Of Day</th><th>In Service</th><th>Action</th></tr></thead>
                        <tbody class="sortable-crafts">`;

                    result.crafts.forEach(craft => {
                        tableHtml += `
                            <tr data-craft-id="${craft.id}">
                                <td class="drag-handle" style="cursor: move; text-align:center;"><i class="fa fa-bars"></i></td>
                                <td>${escapeHtml(craft.craft_type)}</td>
                                <td>${escapeHtml(craft.registration)}</td>
                                <td>${capitalize(craft.tod)}</td>
                                <td class="status-cell">
                                    <a href="#" class="toggle-status-link" data-pk="${craft.id}" data-current-status="${craft.alive}">
                                        ${craft.alive ? '<strong>True</strong>' : '<span class="text-muted">False</span>'}
                                    </a>
                                </td>
                                <td><button class='btn btn-xs btn-danger manager-remove-craft-btn' data-pk='${craft.id}' data-reg='${escapeHtml(craft.registration)}'><i class='fa fa-minus'></i></button></td>
                            </tr>`;
                    });
                    tableHtml += `</tbody></table>`;

                    tableHtml += `<hr><h4>Add New Craft</h4><table class='table table-condensed'><tbody><tr>
                            <td><input class='form-control input-sm' type='text' placeholder='New Craft Type' id='manager-add-craft-type'></td>
                            <td><input class='form-control input-sm' type='text' placeholder='New Registration' id='manager-add-craft-reg'></td>
                            <td><select class='form-control input-sm' id='manager-add-craft-tod'><option value='day'>Day</option><option value='night'>Night</option></select></td>
                            <td><select class='form-control input-sm' id='manager-add-craft-alive'><option value='1'>True</option><option value='0'>False</option></select></td>
                            <td><button class='btn btn-xs btn-success' id='manager-add-craft-btn' title='Add New Craft'><i class='fa fa-plus'></i> Add</button></td>
                        </tr></tbody></table>`;

                    $container.html(tableHtml);

                    // Initialize the sortable functionality after the table is in the DOM
                    if ($.ui && $.ui.sortable) {
                        $container.find('.sortable-crafts').sortable({
                            handle: '.drag-handle',
                            placeholder: 'ui-state-highlight',
                            axis: 'y',
                            update: function (event, ui) {
                                const orderedIds = $(this).sortable('toArray', { attribute: 'data-craft-id' });
                                showNotification($craftsAlertContainer, 'info', 'Saving new order...');
                                
                                // ✅ FIXED: Include CSRF token
                                $.ajax({
                                    url: 'craft_save_order.php',
                                    type: 'POST',
                                    data: { 
                                        craft_order: orderedIds,
                                        form_token: $('#form_token_manager').val() // ✅ CSRF token added
                                    },
                                    dataType: 'json',
                                    success: function (saveResponse) {
                                        showNotification($craftsAlertContainer, saveResponse.success ? 'success' : 'danger', saveResponse.message);
                                        
                                        // ✅ OPTIONAL: Update CSRF token if a new one is provided
                                        if (saveResponse.new_csrf_token) {
                                            $('#form_token_manager').val(saveResponse.new_csrf_token);
                                        }
                                        
                                        if (!saveResponse.success) $(event.target).sortable('cancel');
                                    },
                                    error: function (xhr) {
                                        const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'A server error occurred.';
                                        showNotification($craftsAlertContainer, 'danger', errorMsg);
                                        $(event.target).sortable('cancel');
                                        
                                        // ✅ OPTIONAL: Update CSRF token on error if provided
                                        try {
                                            const errorResponse = JSON.parse(xhr.responseText);
                                            if (errorResponse.new_csrf_token) {
                                                $('#form_token_manager').val(errorResponse.new_csrf_token);
                                            }
                                        } catch (e) {
                                            // Ignore JSON parse errors
                                        }
                                    }
                                });
                            }
                        }).disableSelection();
                    }
                },
                error: function () {
                    $container.html('<p class="text-danger">A network error occurred while loading crafts.</p>');
                }
            });
        }

        // --- EVENT LISTENERS ---

        // Load the crafts table when the tab is clicked for the first time
        $('div[data-tab-toggle="manage-crafts"]').one('click', loadAndBuildManagerCraftsTable);
        if ($('div.tabs div[data-tab-toggle="manage-crafts"].active').length) {
            loadAndBuildManagerCraftsTable();
        }

        // Add a new craft
        $(document).on('click', '#manager-add-craft-btn', function () {
            const craftType = $("#manager-add-craft-type").val().trim();
            const registration = $("#manager-add-craft-reg").val().trim();
            const csrfToken = $("#csrf_token_manager").val(); // Get CSRF token

            if (!craftType || !registration) {
                showNotification($craftsAlertContainer, 'danger', 'Craft Type and Registration are required.');
                return;
            }

            $.ajax({
                type: "POST",
                url: "craft_add_craft.php",
                dataType: "json",
                data: {
                    craft: craftType,
                    registration: registration,
                    tod: $("#manager-add-craft-tod").val(),
                    alive: $("#manager-add-craft-alive").val(),
                    form_token: $("#form_token_manager").val() // Use correct token element
                },
                success: function (result) {
                    // Update CSRF token if a new one is provided
                    if (result.new_csrf_token) {
                        updateCsrfToken(result.new_csrf_token); // Use the function
                    }

                    showNotification($craftsAlertContainer, result.success ? 'success' : 'danger', result.message || 'Craft added successfully!');
                    if (result.success) loadAndBuildManagerCraftsTable();
                },
                error: function (xhr) {
                    // Handle CSRF token updates even on error
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.new_csrf_token) {
                            updateCsrfToken(errorResponse.new_csrf_token); // Use the function
                        }
                        showNotification($craftsAlertContainer, 'danger', errorResponse.message || 'A server error occurred.');
                    } catch (e) {
                        showNotification($craftsAlertContainer, 'danger', 'A server error occurred.');
                    }
                }
            });
        });

        // Remove an existing craft
        $(document).on('click', '.manager-remove-craft-btn', function () {
            const craftId = $(this).data("pk");
            const registration = $(this).data("reg");
            
            if (confirm(`Are you sure you want to remove the craft "${registration}"?`)) {
                $.ajax({
                    type: "POST", url: "craft_remove_craft.php",
                    dataType: "json",
                    data: { 
                        craft: craftId,
                        form_token: $("#form_token_manager").val() // ← ADD THIS LINE
                    },
                    success: function (result) {
                        // Update CSRF token if a new one is provided
                if (result.new_csrf_token) {
                    $("#form_token_manager").val(result.new_csrf_token);
                    console.log("Updated CSRF token to:", result.new_csrf_token);
                    }
                
                    showNotification($craftsAlertContainer, result.success ? 'success' : 'danger', result.message || 'Craft removed successfully.');
                    if (result.success) loadAndBuildManagerCraftsTable();
                    },
                    error: function (xhr) { 
                        // Handle CSRF token updates on error too
                        try {
                            const errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse.new_csrf_token) {
                                $("#form_token_manager").val(errorResponse.new_csrf_token);
                                console.log("Updated CSRF token on error:", errorResponse.new_csrf_token);
                            }
                        } catch (e) {
                        // Ignore JSON parse errors
                        }
                        showNotification($craftsAlertContainer, 'danger', xhr.responseJSON ? xhr.responseJSON.message : 'A server error occurred.'); 
                    }
                });
            }
        });

        // Toggle the 'alive' status of a craft
        $(document).on('click', '.toggle-status-link', function (e) {
            e.preventDefault();
            const $link = $(this), craftId = $link.data('pk'), currentStatus = $link.data('current-status'), newStatus = (currentStatus == 1) ? 0 : 1, $cell = $link.parent();
            $cell.html('<i class="fa fa-spinner fa-spin"></i>');
            $.ajax({
                type: 'POST', url: 'craft_toggle_status.php', dataType: 'json', data: { craft_id: craftId, new_status: newStatus },
                success: function (response) {
                    if (response.success) {
                        const newStatusText = newStatus == 1 ? '<strong>True</strong>' : '<span class="text-muted">False</span>';
                        $cell.html(`<a href="#" class="toggle-status-link" data-pk="${craftId}" data-current-status="${newStatus}">${newStatusText}</a>`);
                    } else {
                        showNotification($craftsAlertContainer, 'danger', 'Error updating status: ' + (response.message || 'Unknown error.'));
                        loadAndBuildManagerCraftsTable();
                    }
                },
                error: function () {
                    showNotification($craftsAlertContainer, 'danger', 'A server error occurred. Could not update status.');
                    loadAndBuildManagerCraftsTable();
                }
            });
        });

        // --- UTILITY FUNCTIONS ---
        // Add this function to daily_manager-crafts.js
        // CSRF Token Strategy: Per-Session (token changes only on validation failures)
function updateCsrfToken(newToken) {
    console.log('Updating CSRF token to:', newToken);
    
    // Update the main CSRF token
    $('#csrf_token_manager').val(newToken);
    
    console.log('CSRF token updated successfully');
}

        function escapeHtml(text) {
            return text ? String(text).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m]) : '';
        }
        function capitalize(text) {
            return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
        }
        function showNotification($container, type, message) {
            const alertClass = type === 'success' ? 'alert-success' : (type === 'danger' ? 'alert-danger' : 'alert-info');
            $container.html(`<div class="alert ${alertClass} alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>${message}</div>`);
        }
    }
});