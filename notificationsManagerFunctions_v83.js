// =======  notificationsManagerFunctions.js v83 ========
// === START: NOTIFICATIONS / SMS & Max. Times        ===
// ======================================================
$(document).ready(function () {
    // --- Globals / Config ---
    // SWAPPED: Point variables to the correct NEW tbody IDs
    const messageQueueTableBody = $('#messageQueueBody');       // Body for the QUEUE tab (data-tab="queue")
    const historyLogTableBody = $('#historyLogBody');         // Body for the HISTORY tab (data-tab="history")

    let notificationQueue = {}; // Holds items for the QUEUE
    $('#directSmsCharCount');  // <<< NEW Global
    // *** END NEW GLOBALS ***

    // *** UPDATED/NEW Globals for Direct SMS Tab ***
    const directSmsPilotListDivBulk = $('#directSmsPilotListBulk'); // Updated ID
    const directSmsBulkMessageText = $('#directSmsBulkMessage');   // Updated ID
    const directSmsBulkSendBtn = $('#sendDirectBulkSmsBtn');     // Updated ID
    const directSmsBulkCharCount = $('#directSmsBulkCharCount');  // Updated ID
    const directSmsCustomPhonesInput = $('#directSmsCustomPhones');

    // ==============================================================
    // === PASTE THE TWO EVENT LISTENERS HERE                     ===
    // ==============================================================
    // Replace your existing 'pilotAssigned' listener with this one.
    $(document).on('pilotAssigned', function (event, assignmentData) {
        console.log(">>> 'pilotAssigned' event received. Data:", assignmentData);

        if (!assignmentData || !assignmentData.user_id) {
            console.error("Event received with invalid assignment data.");
            return;
        }

        // Immediately add a temporary version to the queue for a fast UI response
        const tempKey = `temp_${assignmentData.user_id}_${assignmentData.sched_date}_${assignmentData.pos}_${assignmentData.registration}`;
        notificationQueue[tempKey] = {
            ...assignmentData,
            phone: '', // Initialize empty phone field for temporary items
            queue_id: null // Mark as temporary; // Add to the local JS queue
        };
        renderNotificationQueueTable(); // Update the table display instantly

        // Now, send the data to the server to be saved permanently in the queue table
        $.ajax({
            url: 'daily_manager_prepare_notifications_save_item.php',
            type: 'POST',
            data: { 
                item: assignmentData,
                form_token: $("#form_token_manager").val() // ✅ ADD CSRF TOKEN
            },
            dataType: 'json',
            success: function (response) {
                // ✅ UPDATE CSRF TOKEN IF PROVIDED
                if (response.new_csrf_token) {
                    $("#form_token_manager").val(response.new_csrf_token);
                }
                
                if (response.success) {
                    console.log("Successfully saved assignment to the server-side queue. New ID:", response.inserted_id);
                    loadNotificationQueue();
                } else {
                    showErrorAlert("Could not add pilot to queue: " + (response.message || 'Unknown error'));
                    delete notificationQueue[tempKey];
                    renderNotificationQueueTable();
                }
            },
            error: function (xhr) {
                // ✅ HANDLE CSRF TOKEN UPDATES ON ERROR
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.new_csrf_token) {
                        $("#form_token_manager").val(errorResponse.new_csrf_token);
                    }
                } catch (e) {
                    // Ignore JSON parse errors
                }
                
                showErrorAlert("A network error occurred while adding the pilot to the queue.");
                console.error("AJAX Error saving to queue:", xhr.responseText);
                delete notificationQueue[tempKey];
                renderNotificationQueueTable();
            }
        });
    });

    // Optional listener for deselection
    $(document).on('pilotDeselected', function (event, data) {
        console.log(">>> Deselection event received:", data);
        // Use the same composite key logic to find the item to remove
        if (data && data.schedule_id) { // Prefer schedule_id if available
            const key = `sched-${data.schedule_id}`; // Need to know how schedule ID maps if not always present
            // Find the key in notificationQueue that matches data.schedule_id if possible
            let keyToRemove = null;
            for (const k in notificationQueue) {
                if (notificationQueue[k].schedule_id == data.schedule_id) { // Loose comparison ok here
                    keyToRemove = k;
                    break;
                }
            }
            if (keyToRemove) removeNotificationFromQueue(keyToRemove);

        } else if (data) { // Fallback to composite key if no schedule_id
            const key = `temp_${data.user_id}_${data.sched_date}_${data.pos}_${data.registration}`;
            removeNotificationFromQueue(key);
        }
    });

    // ==================================================
    // === START: FUNCTION NOTIFICATIONS & SMS        ===
    // ==================================================
    function setupAllCheckboxLogic() {
        const allCheckbox = document.getElementById('queue-check-all');
        if (!allCheckbox) return;

        allCheckbox.addEventListener('change', function (e) {
            const isChecked = e.target.checked;

            // Select all checkboxes in TODAY'S assignments section only
            const todayCheckboxes = document.querySelectorAll('#messageQueueTable .today-assignment .queue-checkbox-item');

            todayCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;

                // Trigger any change events if needed
                const event = new Event('change', { bubbles: true });
                checkbox.dispatchEvent(event);
            });

            // Update the action buttons state
            updateQueueActionButtons();
        });
    }

    // Call this when the page loads AND when new content is added to the table
    document.addEventListener('DOMContentLoaded', function () {
        setupAllCheckboxLogic();
    });

    /**
     * Formats phone number to display format while keeping +1 if present
     * @param {string} phone - Raw phone number from database
     * @returns {string} Formatted phone number
     */
    function formatPhoneForDisplay(phone) {
        if (!phone) return '';

        // Remove all non-digit characters except +
        let cleaned = phone.replace(/[^\d+]/g, '');

        // If it starts with +1, format as +1-XXX-XXX-XXXX
        if (cleaned.startsWith('+1') && cleaned.length === 12) {
            return `+1-${cleaned.substring(2, 5)}-${cleaned.substring(5, 8)}-${cleaned.substring(8)}`;
        }
        // If it's 10 digits without country code, format as XXX-XXX-XXXX
        else if (cleaned.length === 10) {
            return `${cleaned.substring(0, 3)}-${cleaned.substring(3, 6)}-${cleaned.substring(6)}`;
        }
        // If it's 11 digits starting with 1, format as 1-XXX-XXX-XXXX
        else if (cleaned.length === 11 && cleaned.startsWith('1')) {
            return `1-${cleaned.substring(1, 4)}-${cleaned.substring(4, 7)}-${cleaned.substring(7)}`;
        }

        // Return original if doesn't match expected patterns
        return phone;
    }

    function loadNotificationHistory() {
        console.log("loadNotificationHistory called");
        const filterDate = $("#history_date_filter").datepicker("getDate");
        const formattedDate = filterDate ? $.datepicker.formatDate("yy-mm-dd", filterDate) : null;
        const searchTerm = $('#history_search_filter').val();
        historyLogTableBody.html('<tr><td colspan="7" class="text-center">Loading history... <i class="fa fa-spinner fa-spin"></i></td></tr>'); // Colspan 7

        // Use get_message_log.php to fetch history from notifications_log
        $.ajax({
            url: 'daily_manager_get_message_log.php', // This script reads notifications_log
            type: 'GET', dataType: 'json',
            data: { date: formattedDate, search: searchTerm },
            success: function (response) {
                if (response.success && response.logs) { renderNotificationHistoryTable(response.logs); }
                else { historyLogTableBody.html(`<tr><td colspan="7" class="text-center text-danger">Error: ${response.error || 'No data'}</td></tr>`); }
            },
            error: function () { historyLogTableBody.html('<tr><td colspan="7" class="text-center text-danger">AJAX Error loading history</td></tr>'); }
        });
    }

    function renderNotificationHistoryTable(logs) {
        historyLogTableBody.empty();
        const canDeleteHistory = true;
        $('#history-check-all').prop('checked', false).prop('disabled', !canDeleteHistory);

        if (!logs || logs.length === 0) {
            historyLogTableBody.html('<tr><td colspan="7" class="text-center text-muted">No notification history found matching criteria.</td></tr>');
            return;
        }

        logs.forEach(log => {
            const $row = $('<tr>');

            // CRITICAL FIX: Use the existing composite_id field instead of creating a new one
            const compositeId = log.composite_id || `unknown-${Date.now()}`;

            $row.append(`<td style="text-align: center;"><div class="checkbox"><label><input type="checkbox" class="history-checkbox" value="${compositeId}" ${canDeleteHistory ? '' : 'disabled'}></label></div></td>`);
            $row.append(`<td>${log.schedule_date || 'N/A'}</td>`);
            $row.append(`<td>${log.craft_registration || 'N/A'}</td>`);
            $row.append(`<td>${log.pilot_name || 'N/A'}</td>`);
            $row.append(`<td>${log.recipient_contact || 'N/A'}</td>`);
            $row.append(`<td>${log.sent_at || 'N/A'}</td>`);
            $row.append(`<td><span class="badge bg-${log.status === 'Sent' ? 'success' : (log.status === 'Failed' ? 'danger' : 'secondary')}">${log.status || 'Unknown'}</span></td>`);
            historyLogTableBody.append($row);
        });
        $('#deleteHistoryBtn').prop('disabled', true);
    }

    function deleteNotificationHistory() {
        console.log("deleteNotificationHistory called");
    
        const selectedIds = $('#historyLogTable .history-checkbox:checked').map(function () {
            const value = $(this).val();
            console.log("Selected checkbox value:", value, "Type:", typeof value);
            return value;
        }).get();
    
        console.log("Selected IDs to delete:", selectedIds);
    
        const validIds = selectedIds.filter(id => id && !id.includes('undefined'));
        console.log("Valid IDs after filtering:", validIds);
    
        if (validIds.length === 0) {
            showWarningAlert('Please select valid history entries to delete.');
            return;
        }
    
        if (!confirm(`Permanently delete ${validIds.length} selected history log(s)?`)) {
            return;
        }
    
        showLoadingOverlay("Deleting history...");
        const $button = $('#deleteHistoryBtn');
        const originalHtml = $button.html();
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');
    
        console.log("Sending delete request for valid IDs:", validIds);
    
        $.ajax({
            url: 'daily_manager_delete_messages.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                log_ids: validIds,
                form_token: $("#form_token_manager").val() // ✅ ADD CSRF TOKEN
            },
            success: function (response) {
                // ✅ UPDATE CSRF TOKEN IF PROVIDED
                if (response.new_csrf_token) {
                    $("#form_token_manager").val(response.new_csrf_token);
                }
                
                console.log("Delete response:", response);
                if (response.success) {
                    showSuccessAlert(response.message || 'Deleted.');
                    loadNotificationHistory();
                } else {
                    showErrorAlert(`Error: ${response.message || 'Unknown'}`);
                }
            },
            error: function (xhr) {
                // ✅ HANDLE CSRF TOKEN UPDATES ON ERROR
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.new_csrf_token) {
                        $("#form_token_manager").val(errorResponse.new_csrf_token);
                    }
                } catch (e) {
                    // Ignore JSON parse errors
                }
                
                console.error("AJAX Delete History Error:", xhr.responseText);
                showErrorAlert('AJAX Error.');
            },
            complete: function () {
                hideLoadingOverlay();
                $button.prop('disabled', true).html(originalHtml);
            }
        });
    }

    function loadNotificationQueue() {
        console.log("Loading saved notification queue with Today/Tomorrow sections...");
        messageQueueTableBody.html('<tr><td colspan="8" class="text-center">Loading queue... <i class="fa fa-spinner fa-spin"></i></td></tr>');
        $('#sendPreparedNotiBtn, #deleteQueueItemsBtn').prop('disabled', true);

        $.ajax({
            url: 'daily_manager_prepare_notifications_load.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Rebuild JS queue with new structure
                    notificationQueue = {
                        today_queue: response.today_queue || [],
                        tomorrow_queue: response.tomorrow_queue || [],
                        today_date: response.today_date,
                        tomorrow_date: response.tomorrow_date
                    };

                    renderNotificationQueueTable();
                } else {
                    showErrorAlert("Failed to load saved queue: " + (response.error || 'Unknown error'));
                    notificationQueue = { today_queue: [], tomorrow_queue: [] };
                    renderNotificationQueueTable();
                }
            },
            error: function () {
                showErrorAlert("AJAX error loading saved queue.");
                notificationQueue = { today_queue: [], tomorrow_queue: [] };
                renderNotificationQueueTable();
            }
        });
    }

    function deleteSelectedQueueItems() {
        const selectedKeys = [];
        $('#messageQueueTable .queue-checkbox:checked').each(function () {
            const queueId = $(this).val();
            if (queueId) { selectedKeys.push(queueId); }
        });
    
        if (selectedKeys.length === 0) { /* warning */ return; }
        if (!confirm(`Remove ${selectedKeys.length} selected item(s) from the queue?`)) { return; }
    
        showLoadingOverlay("Removing items from queue...");
        $.ajax({
            url: 'daily_manager_prepare_notifications_delete_items.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                queue_ids: selectedKeys,
                form_token: $("#form_token_manager").val() // ✅ ADD CSRF TOKEN
            },
            success: function (response) {
                // ✅ UPDATE CSRF TOKEN IF PROVIDED
                if (response.new_csrf_token) {
                    $("#form_token_manager").val(response.new_csrf_token);
                }
                
                if (response.success) {
                    showSuccessAlert(response.message);
                    selectedKeys.forEach(id => {
                        for (const k in notificationQueue) {
                            if (notificationQueue[k].queue_id == id) {
                                delete notificationQueue[k];
                                break;
                            }
                        }
                    });
                    renderNotificationQueueTable();
                } else { 
                    showErrorAlert("Failed to delete: " + (response.message || 'Unknown')); 
                }
            },
            error: function (xhr) { 
                // ✅ HANDLE CSRF TOKEN UPDATES ON ERROR
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.new_csrf_token) {
                        $("#form_token_manager").val(errorResponse.new_csrf_token);
                    }
                } catch (e) {
                    // Ignore JSON parse errors
                }
                
                showErrorAlert('AJAX Error deleting queue items.'); 
            },
            complete: function () { hideLoadingOverlay(); }
        });
    }

    function updateQueueActionButtons() {
        const $sendButton = $('#sendPreparedNotiBtn');

        // Always update send button state
        const queueHasItems = Object.keys(notificationQueue).length > 0;
        if ($sendButton.length) {
            $sendButton.prop('disabled', !queueHasItems);
        } else {
            console.warn("updateQueueActionButtons: #sendPreparedNotiBtn not found.");
        }
    }

    function removeNotificationFromQueue(dataForRemoval) {
        console.log("DM_FN_LOG: removeNotificationFromQueue trying to remove based on:", dataForRemoval);
        let itemKeyToRemove = null;

        if (dataForRemoval.schedule_id) {
            // Find by DB-persisted schedule_id (if item was saved and then deselected)
            for (const key in notificationQueue) {
                if (notificationQueue[key].schedule_id &&
                    notificationQueue[key].schedule_id.toString() === dataForRemoval.schedule_id.toString()) {
                    itemKeyToRemove = key;
                    console.log("DM_FN_LOG: Found item to remove by schedule_id. Key:", itemKeyToRemove);
                    break;
                }
            }
        } else if (dataForRemoval.user_id_deselected) {
            // Fallback for items added to queue client-side but not yet saved to DB schedule table
            // (and thus schedule_id is null on the item in the queue)
            // Match based on the details of the assignment slot.
            for (const key in notificationQueue) {
                const itemInQueue = notificationQueue[key];
                console.log("DM_FN_LOG: Comparing for removal - Queue Item:", itemInQueue, "Data for Removal:", dataForRemoval);
                if (itemInQueue.schedule_id === null && // Ensure we are looking at an unsaved queue item
                    itemInQueue.user_id && itemInQueue.user_id.toString() === dataForRemoval.user_id_deselected.toString() &&
                    itemInQueue.sched_date === dataForRemoval.sched_date &&
                    itemInQueue.registration === dataForRemoval.registration &&
                    itemInQueue.pos === dataForRemoval.pos && // This is 'PIC'/'SIC'
                    itemInQueue.craft_type === dataForRemoval.craft_type) {
                    itemKeyToRemove = key;
                    console.log("DM_FN_LOG: Found unsaved item to remove by details. Key:", itemKeyToRemove);
                    break;
                }
            }
        }

        if (itemKeyToRemove) {
            delete notificationQueue[itemKeyToRemove];
            console.log("DM_FN_LOG: Item removed from client queue. Key:", itemKeyToRemove, ". Re-rendering queue table.");
            renderNotificationQueueTable();
            updateQueueActionButtons(); // Assumes this function is defined

            // If it had a schedule_id, it means it was an assignment that existed in 'schedule' table
            // and also potentially in 'prepare_notifications' table.
            // You might want to call a backend script to remove it from 'prepare_notifications' table.
            if (dataForRemoval.schedule_id) {
                console.log("DM_FN_LOG: Making AJAX call to delete item from backend prepare_notifications queue for schedule_id:", dataForRemoval.schedule_id);
                $.post('prepare_notifications_delete_item.php', { schedule_id: dataForRemoval.schedule_id, by_schedule_id: true })
                    .done(function (response) {
                        if (response.success) {
                            console.log('DM_FN_LOG: Item removed from backend prepare_notifications queue by schedule_id:', dataForRemoval.schedule_id);
                        } else {
                            console.warn('DM_FN_LOG: Failed to remove item from backend prepare_notifications queue:', response.error);
                        }
                    })
                    .fail(function () {
                        console.error('DM_FN_LOG: AJAX error calling prepare_notifications_delete_item.php');
                    });
            }
        } else {
            console.warn("DM_FN_LOG: Could not find item in client-side queue to remove with data:", dataForRemoval);
        }
    }

    function renderNotificationQueueTable() {
        const messageQueueTableBody = $('#messageQueueBody');
        messageQueueTableBody.empty();

        // Get today and tomorrow queues from the global notificationQueue object
        const todayQueue = notificationQueue.today_queue || [];
        const tomorrowQueue = notificationQueue.tomorrow_queue || [];
        const todayDate = notificationQueue.today_date || moment().format('YYYY-MM-DD');
        const tomorrowDate = notificationQueue.tomorrow_date || moment().add(1, 'day').format('YYYY-MM-DD');

        // Format dates for display
        const todayDisplay = moment(todayDate).format("MMM DD, YYYY");
        const tomorrowDisplay = moment(tomorrowDate).format("MMM DD, YYYY");

        let rowsHtml = '';

        // =============================================
        // TODAY'S SECTION - URGENT (Top Position)
        // =============================================
        if (todayQueue.length > 0) {
            rowsHtml += `
        <tr class="info">
            <td colspan="8" class="text-center">
                <strong><i class="fa fa-exclamation-circle text-warning"></i> TODAY'S ASSIGNMENTS - ${todayDisplay}</strong>
                <small class="text-muted"> - Ready to Send</small>
            </td>
        </tr>`;

            todayQueue.forEach(item => {
                const phoneDisplay = formatPhoneForDisplay(item.phone || '');
                const schedDate = moment(item.sched_date).format("MMM DD, YYYY");
                const registration = item.registration || '';
                const pilotName = item.pilot_name || '';
                const position = item.pos || '';
                const routing = item.routing || '';
                const queueId = item.queue_id;
                const status = item.status || 'Pending';

                rowsHtml += `
            <tr data-queue-id="${queueId}" class="today-assignment">
                <td class="text-center">
                    <div class="checkbox" style="margin: 0;">
                        <label><input type="checkbox" class="queue-checkbox-item" value="${queueId}"></label>
                    </div>
                </td>
                <td>${schedDate}</td>
                <td>${registration}</td>
                <td>${pilotName}</td>
                <td>${position}</td>
                <td>
                    <input type="text" class="form-control routing-input" 
                           value="${routing}" 
                           placeholder="Enter flight routing" 
                           data-queue-id="${queueId}">
                </td>
                <td>${phoneDisplay}</td>
                <td class="text-center">
                    <span class="badge bg-success" title="Ready to send today">
                        <i class="fa fa-check-circle"></i> Ready
                    </span>
                </td>
            </tr>`;
            });
        } else {
            rowsHtml += `
        <tr class="info">
            <td colspan="8" class="text-center">
                <strong>TODAY'S ASSIGNMENTS - ${todayDisplay}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="8" class="text-center text-muted">
                No assignments scheduled for today.
            </td>
        </tr>`;
        }

        // =============================================
        // TOMORROW'S SECTION
        // =============================================
        if (tomorrowQueue.length > 0) {
            rowsHtml += `
        <tr style="background-color: #f8f9fa;">
            <td colspan="8" class="text-center">
                <strong><i class="fa fa-calendar text-primary"></i> TOMORROW'S ASSIGNMENTS - ${tomorrowDisplay}</strong>
                <small class="text-muted"> - Planning</small>
            </td>
        </tr>`;

            tomorrowQueue.forEach(item => {
                const phoneDisplay = formatPhoneForDisplay(item.phone || '');
                const schedDate = moment(item.sched_date).format("MMM DD, YYYY");
                const registration = item.registration || '';
                const pilotName = item.pilot_name || '';
                const position = item.pos || '';
                const routing = item.routing || '';
                const queueId = item.queue_id;
                const status = item.status || 'Pending';

                rowsHtml += `
            <tr data-queue-id="${queueId}" class="tomorrow-assignment">
                <td class="text-center">
                    <div class="checkbox" style="margin: 0;">
                        <label><input type="checkbox" class="queue-checkbox-item" value="${queueId}"></label>
                    </div>
                </td>
                <td>${schedDate}</td>
                <td>${registration}</td>
                <td>${pilotName}</td>
                <td>${position}</td>
                <td>
                    <input type="text" class="form-control routing-input" 
                           value="${routing}" 
                           placeholder="Enter flight routing" 
                           data-queue-id="${queueId}">
                </td>
                <td>${phoneDisplay}</td>
                <td class="text-center">
                    <span class="badge bg-secondary" title="Pending for tomorrow">
                        <i class="fa fa-clock"></i> Pending
                    </span>
                </td>
            </tr>`;
            });
        } else {
            rowsHtml += `
        <tr style="background-color: #f8f9fa;">
            <td colspan="8" class="text-center">
                <strong>TOMORROW'S ASSIGNMENTS - ${tomorrowDisplay}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="8" class="text-center text-muted">
                No assignments scheduled for tomorrow.
            </td>
        </tr>`;
        }

        // =============================================
        // EMPTY STATE - No assignments at all
        // =============================================
        if (todayQueue.length === 0 && tomorrowQueue.length === 0) {
            rowsHtml = `
        <tr>
            <td colspan="8" class="text-center text-muted">
                Select pilots on the 'Schedule' tab to add notifications here.
            </td>
        </tr>`;
        }

        messageQueueTableBody.html(rowsHtml);

        // Enable Send button if there are any items
        const totalItems = todayQueue.length + tomorrowQueue.length;
        $('#sendPreparedNotiBtn').prop('disabled', totalItems === 0);
        $('#deleteQueueItemsBtn').prop('disabled', true);

        // === ADD THIS RIGHT HERE ===
        // After rendering, setup the checkbox logic
        setTimeout(() => {
            setupAllCheckboxLogic();
        }, 0);
    }

    $('a[data-toggle="tab"][href="#queue"]').on('shown.bs.tab', function (e) {
        console.log("Prepare Notifications tab shown. Loading queue...");
        loadNotificationQueue();
    });

    function loadPilotsForDirectSmsBulk() {
        console.log("Loading pilots for Direct Bulk SMS tab...");

        const directSmsPilotListDivBulk = $('#directSmsPilotListBulk');
        directSmsPilotListDivBulk.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading pilots...</p>');
        $('#directSmsSelectAllBulk').prop('checked', false);
        validateDirectBulkSmsForm();

        $.ajax({
            url: 'daily_manager_get_pilots.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                // console.log("DEBUG: Full response:", response);
                // console.log("DEBUG: response.success:", response.success);
                // console.log("DEBUG: response.data:", response.data);
                // console.log("DEBUG: response.data.pilots:", response.data?.pilots);

                directSmsPilotListDivBulk.empty();

                // FIXED: Check response.data.pilots instead of response.data
                if (response.success && response.data && Array.isArray(response.data.pilots) && response.data.pilots.length > 0) {
                    const pilots = response.data.pilots; // FIXED: Access the pilots array

                    pilots.forEach(pilot => {
                        if (pilot.id && pilot.firstname && pilot.lastname) {
                            const displayName = `${pilot.firstname} ${pilot.lastname}`;
                            const phone = pilot.phone || null;

                            const $checkboxDiv = $('<div>', { class: 'checkbox' });
                            const $label = $('<label>');
                            const $input = $('<input>', {
                                type: 'checkbox',
                                class: 'directSmsPilotCheckBulk',
                                value: pilot.id,
                                'data-phone': phone
                            });

                            if (!phone) {
                                $input.prop('disabled', true);
                                $label.addClass('text-muted');
                                $label.append($input).append(` ${displayName} <small class="text-danger">(No Phone)</small>`);
                            } else {
                                $label.append($input).append(` ${displayName} (${phone})`);
                            }

                            $checkboxDiv.append($label);
                            directSmsPilotListDivBulk.append($checkboxDiv);
                        }
                    });
                } else {
                    // FIXED: Handle both empty pilots array and server errors
                    const message = response.error || "No active pilots found in this company.";
                    directSmsPilotListDivBulk.html(`<p class="text-danger">${message}</p>`);
                }
            },
            error: function (xhr) {
                directSmsPilotListDivBulk.html('<p class="text-danger">Error: Could not load pilot list. Please refresh and try again.</p>');
                console.error("Failed to load pilots for SMS tab:", xhr.status, xhr.responseText);
            }
        });
    }

    function validateDirectBulkSmsForm() {
        const pilotsSelected = directSmsPilotListDivBulk.length > 0
            ? directSmsPilotListDivBulk.find('.directSmsPilotCheckBulk:checked').length > 0
            : false;

        const customPhonesVal = (directSmsCustomPhonesInput && directSmsCustomPhonesInput.length > 0)
            ? directSmsCustomPhonesInput.val()
            : '';
        const messageVal = (directSmsBulkMessageText && directSmsBulkMessageText.length > 0)
            ? directSmsBulkMessageText.val()
            : '';

        const customPhonesEntered = customPhonesVal.trim().length > 0;
        const messageEntered = messageVal.trim().length > 0;

        if (directSmsBulkSendBtn && directSmsBulkSendBtn.length > 0) {
            directSmsBulkSendBtn.prop('disabled', !(messageEntered && (pilotsSelected || customPhonesEntered)));
        }

        // Update char count
        const currentLength = messageVal.length;
        if (directSmsBulkCharCount && directSmsBulkCharCount.length > 0) {
            directSmsBulkCharCount.text(currentLength);
            directSmsBulkCharCount.toggleClass('text-danger', currentLength > 1600);
        }
    }

    function sendDirectBulkSms() {
        let recipientsToSend = []; // Array to hold {id, phone, name} or {id: 'custom', phone, name: 'Custom'}
        let hasInvalidCustom = false;

        // 1. Gather selected pilots
        directSmsPilotListDivBulk.find('.directSmsPilotCheckBulk:checked').each(function () {
            const $cb = $(this);
            const phone = $cb.data('phone');
            const userId = $cb.val();
            if (phone && userId) { // Ensure both phone and ID exist
                recipientsToSend.push({
                    id: userId, // Pilot's user ID
                    phone: phone,
                    name: $cb.parent('label').text().trim().split('(')[0].trim() // Get name from label
                });
            } else {
                console.warn("Skipping selected pilot due to missing data:", $cb.parent('label').text());
            }
        });

        // 2. Gather and validate custom phone numbers
        const customPhonesRaw = directSmsCustomPhonesInput.val().trim();
        if (customPhonesRaw) {
            const customPhonesArray = customPhonesRaw.split(','); // Split by comma
            customPhonesArray.forEach(rawPhone => {
                let phoneToValidate = rawPhone.trim();
                if (phoneToValidate) { // Skip empty strings resulting from multiple commas
                    let cleanedPhone = phoneToValidate.replace(/[\s\-\(\)\.]+/g, '');
                    if (cleanedPhone.charAt(0) !== '+') {
                        if (cleanedPhone.length === 10) { cleanedPhone = '+1' + cleanedPhone; }
                        else if (cleanedPhone.length === 11 && cleanedPhone.charAt(0) === '1') { cleanedPhone = '+' + cleanedPhone; }
                        else { cleanedPhone = '+' + cleanedPhone; } // Best guess
                    }

                    // Validate final format
                    if (/^\+[1-9]\d{1,14}$/.test(cleanedPhone)) {
                        // Add custom recipient (use phone as name for now, ID as null/custom)
                        recipientsToSend.push({
                            id: null, // No specific user ID
                            phone: cleanedPhone,
                            name: `Custom (${cleanedPhone})` // Identify as custom
                        });
                    } else {
                        hasInvalidCustom = true;
                        showWarningAlert(`Invalid custom phone number format skipped: ${rawPhone}`);
                        console.warn(`Invalid custom phone after cleaning: Raw='${rawPhone}', Cleaned='${cleanedPhone}'`);
                    }
                }
            });
        }

        // 3. Get message body
        const message = directSmsBulkMessageText.val().trim();

        // 4. Final Validation and Confirmation
        if (recipientsToSend.length === 0) {
            showWarningAlert("No valid recipients selected or entered.");
            return;
        }
        if (!message) {
            showWarningAlert("Please enter a message body.");
            return;
        }
        if (!confirm(`Send SMS:\n\n"${message}"\n\nTo ${recipientsToSend.length} recipient(s)?` + (hasInvalidCustom ? "\n(Some manually entered numbers were skipped due to invalid format)" : ""))) {
            return;
        }

        // 5. Prepare and Send AJAX
        showLoadingOverlay(`Sending direct SMS to ${recipientsToSend.length} recipient(s)...`);
        directSmsBulkSendBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');

        console.log("Data being sent to send_direct_sms.php:", JSON.stringify({ recipients: recipientsToSend, message: message }));

        $.ajax({
            url: 'daily_manager_send_direct_sms.php', // Backend script handles the list
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                recipients: recipientsToSend,
                message: message,
                form_token: $("#form_token_manager").val() // ✅ ADD CSRF TOKEN
            }),
            success: function (response) {
                // ✅ UPDATE CSRF TOKEN IF PROVIDED
                if (response.new_csrf_token) {
                    $("#form_token_manager").val(response.new_csrf_token);
                }
                if (response.success) {
                    console.log("Direct SMS successful:", response);
                    showSuccessAlert(response.message || "Direct SMS sent!");
                    // Clear form (including custom input)
                    directSmsBulkMessageText.val('');
                    directSmsCustomPhonesInput.val(''); // Clear custom input
                    directSmsPilotListDivBulk.find('.directSmsPilotCheckBulk:checked').prop('checked', false);
                    $('#directSmsSelectAllBulk').prop('checked', false);
                    validateDirectBulkSmsForm(); // Re-validate
                    if ($('.tab-pane[data-tab="history"]').hasClass('active')) { loadNotificationHistory(); }
                } else {
                    console.error("Direct SMS failed (server reported):", response);
                    showErrorAlert("Send failed: " + (response.message || "Unknown"));
                }
            },
            error: function (xhr, status, error) {
                // ✅ HANDLE CSRF TOKEN UPDATES ON ERROR
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.new_csrf_token) {
                        $("#form_token_manager").val(errorResponse.new_csrf_token);
                    }
                } catch (e) {
                    // Ignore JSON parse errors
                }
                
                console.error("Direct SMS AJAX error:", status, error, xhr.responseText);
                showErrorAlert("AJAX Error sending SMS. Status: " + status);
            },
            complete: function (xhr, status) {
                console.log(`AJAX 'complete' Direct SMS. Status: ${status}`);
                hideLoadingOverlay();
                directSmsBulkSendBtn.html('<i class="fa fa-paper-plane"></i> Send Bulk SMS to Selected Recipients');
                validateDirectBulkSmsForm();
            }
        });
    }

    function sendPreparedNotifications() {
        const $button = $('#sendPreparedNotiBtn');

        // Get only checked items from TODAY'S assignments
        const checkedItems = [];
        $('#messageQueueTable .today-assignment .queue-checkbox-item:checked').each(function () {
            const queueId = $(this).val();
            // Find the corresponding item in notificationQueue
            const todayQueue = notificationQueue.today_queue || [];
            const item = todayQueue.find(item => item.queue_id == queueId);
            if (item) {
                checkedItems.push(item);
            }
        });

        if (checkedItems.length === 0) {
            showWarningAlert('No items selected to send. Please check the items you want to send.');
            return;
        }

        let notificationsToSend = [];
        let hasMissingOrInvalidPhones = false;

        // Process only the checked items
        checkedItems.forEach(item => {
            // Get the CURRENT routing from the input field
            const $routingField = $(`input.routing-input[data-queue-id="${item.queue_id}"]`);
            const currentRouting = $routingField.val().trim();

            const phoneDisplay = formatPhoneForDisplay(item.phone || '');
            let cleanedPhone = null;
            let phoneIsValid = false;

            if (item.phone) {
                cleanedPhone = String(item.phone).replace(/[\s\-\(\)\.]+/g, '');
                if (cleanedPhone.charAt(0) !== '+') {
                    if (cleanedPhone.length === 10) cleanedPhone = '+1' + cleanedPhone;
                    else if (cleanedPhone.length === 11 && cleanedPhone.charAt(0) === '1') cleanedPhone = '+' + cleanedPhone;
                    else cleanedPhone = '+' + cleanedPhone;
                }
                if (/^\+[1-9]\d{1,14}$/.test(cleanedPhone)) {
                    phoneIsValid = true;
                }
            }

            if (phoneIsValid) {
                notificationsToSend.push({
                    queue_id: item.queue_id,
                    schedule_id: item.schedule_id || null,
                    user_id: item.user_id,
                    sched_date: item.sched_date,
                    registration: item.registration,
                    pos: item.pos,
                    phone: cleanedPhone,
                    routing: currentRouting,
                    pilot_name: item.pilot_name
                });
            } else {
                hasMissingOrInvalidPhones = true;
                showWarningAlert(`Skipping ${item.pilot_name}: Invalid/Missing phone [${phoneDisplay || 'Empty'}]`);
            }
        });

        if (notificationsToSend.length === 0) {
            showErrorAlert('No valid notifications to send.');
            return;
        }

        if (!confirm(`Send ${notificationsToSend.length} notification(s)?` + (hasMissingOrInvalidPhones ? "\n(Some queue items were skipped due to phone number issues)" : ""))) {
            return;
        }

        showLoadingOverlay(`Sending ${notificationsToSend.length} notifications...`);
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');

        $.ajax({
            url: 'daily_manager_prepare_send_notifications.php',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                notifications: notificationsToSend,
                include_routing: true,
                form_token: $("#form_token_manager").val() // ✅ ADD CSRF TOKEN
            }),
            success: function (response) {
                // ✅ UPDATE CSRF TOKEN IF PROVIDED
                if (response.new_csrf_token) {
                    $("#form_token_manager").val(response.new_csrf_token);
                }
                
                if (response.success) {
                    showSuccessAlert('Send successful!');
                    loadNotificationQueue();
                    if (typeof loadNotificationHistory === 'function') loadNotificationHistory();
                } else {
                    showErrorAlert(`Send failed: ${response.message || 'Unknown'}`);
                }
            },
            error: function (xhr) {
                // ✅ HANDLE CSRF TOKEN UPDATES ON ERROR
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.new_csrf_token) {
                        $("#form_token_manager").val(errorResponse.new_csrf_token);
                    }
                } catch (e) {
                    // Ignore JSON parse errors
                }
                
                showErrorAlert('AJAX error sending notifications.');
            },
            complete: function () {
                hideLoadingOverlay();
                $button.prop('disabled', true).html('<i class="fa fa-paper-plane"></i> Send to Selected');
            }
        });
    }

    // =============================================
    // === INITIALIZATION CALLS  Event Listeners ===
    // =============================================

    function initializeManagerDatepickers() {
        const commonOptions = { dateFormat: "dd/mm/yy", changeMonth: true, changeYear: true };
        // History filter datepicker
        if ($("#history_date_filter").length) {
            $("#history_date_filter").datepicker(commonOptions);
            $("#history_date_filter").off('change.history').on('change.history', loadNotificationHistory);
        }
        // Add other datepickers if needed (e.g., record form modal)
    }

    // UPDATED: initializeManagerTabs
    function initializeManagerTabs() {
        $('.tab[data-tab-toggle]').on('click', function () {
            const $thisTab = $(this);
            const tabId = $thisTab.data('tab-toggle'); // 'schedule', 'queue', 'history', 'direct-sms'
            $('.tab').removeClass('active'); $('.tab-pane').removeClass('active').hide();
            $thisTab.addClass('active');
            $(`.tab-pane[data-tab="${tabId}"]`).addClass('active').show();

            if (tabId === 'history') { loadNotificationHistory(); }
            if (tabId === 'queue') {
                console.log("--- 'Prepare Notifications' tab clicked. About to load queue... ---");
                loadNotificationQueue();
            }
            // Load PILOT LIST for Bulk Direct SMS tab
            if (tabId === 'direct-sms' && directSmsPilotListDivBulk.children().length <= 1) { // Check if list needs loading (<=1 assumes only placeholder exists)
                loadPilotsForDirectSmsBulk(); // Use renamed function
            }
        });
        // Activate first tab
        $('.tab[data-tab-toggle]:first').addClass('active');
        $('.tab-pane:first').addClass('active').show();
    }

    function initializeNotificationQueueFeatures() {
        console.log("Initializing Notification Queue Features");
        // Send Button Listener
        $('#sendPreparedNotiBtn').off('click').on('click', sendPreparedNotifications);

        // Listener for individual queue checkboxes
        $('#messageQueueTable').on('change', '.queue-checkbox-item', function () {
            updateQueueActionButtons();

            // Update "All" checkbox state
            const todayCheckboxes = $('#messageQueueTable .today-assignment .queue-checkbox-item');
            const allTodayChecked = todayCheckboxes.length > 0 && todayCheckboxes.length === todayCheckboxes.filter(':checked').length;
            $('#queue-check-all').prop('checked', allTodayChecked);
        });

        // Listener for QUEUE select all checkbox
        $(document).on('change', '#queue-check-all', function () {
            const isChecked = this.checked;

            // Only check TODAY'S assignments
            $('#messageQueueTable .today-assignment .queue-checkbox-item:not(:disabled)').prop('checked', isChecked);

            // Trigger change events on individual checkboxes
            $('#messageQueueTable .today-assignment .queue-checkbox-item:not(:disabled)').trigger('change');

            updateQueueActionButtons();
        });

        renderNotificationQueueTable(); // Render initial empty state
    }

    function initializeNotificationHistoryFeatures() {
        console.log("Initializing Notification History Features");

        // Ensure delete button is properly bound
        const canDeleteHistory = true; // userPermissions.canDeleteMessages;

        $('#history_date_filter, #history_search_filter, #refreshHistoryBtn').prop('disabled', false);
        $('#refreshHistoryBtn').off('click').on('click', loadNotificationHistory);
        $('#history_search_filter').off('keyup.history').on('keyup.history', debounce(loadNotificationHistory, 500));

        if (canDeleteHistory) {
            $('#deleteHistoryBtn').show().prop('disabled', true); // Show delete button, disabled initially
            $('#history-check-all').prop('disabled', false);

            // CRITICAL: Ensure delete button is properly bound
            $('#deleteHistoryBtn').off('click.history').on('click.history', deleteNotificationHistory);

            // Event listeners for history checkboxes to enable/disable delete button
            $(document).off('change.history', '#history-check-all').on('change.history', '#history-check-all', function () {
                const isChecked = this.checked;
                $('#historyLogTable .history-checkbox:not(:disabled)').prop('checked', isChecked);
                $('#deleteHistoryBtn').prop('disabled', !isChecked && $('#historyLogTable .history-checkbox:checked').length === 0);
            });

            $('#historyLogTable').on('change', '.history-checkbox', function () {
                $('#deleteHistoryBtn').prop('disabled', $('#historyLogTable .history-checkbox:checked').length === 0);
                if (!this.checked) {
                    $('#history-check-all').prop('checked', false);
                }
            });
        } else {
            $('#deleteHistoryBtn').hide();
            $('#history-check-all').prop('disabled', true);
        }

        // Load initial history
        loadNotificationHistory();
    }

    // UPDATED: Initializer for Direct SMS Tab features
    function initializeDirectSmsFeatures() {
        console.log("Initializing Direct Bulk SMS Features");
        directSmsBulkSendBtn.off('click').on('click', sendDirectBulkSms); // Use renamed function
        // Use new IDs/Classes for checkboxes and textarea
        $('#directSmsSelectAllBulk').off('change').on('change', function () {
            directSmsPilotListDivBulk.find('.directSmsPilotCheckBulk:not(:disabled)').prop('checked', this.checked);
            validateDirectBulkSmsForm();
        });
        directSmsPilotListDivBulk.on('change', '.directSmsPilotCheckBulk', function () {
            if (!this.checked) { $('#directSmsSelectAllBulk').prop('checked', false); }
            // Check if ALL are checked
            else if (directSmsPilotListDivBulk.find('.directSmsPilotCheckBulk:not(:checked)').length === 0) {
                $('#directSmsSelectAllBulk').prop('checked', true);
            }
            validateDirectBulkSmsForm();
        });
        directSmsBulkMessageText.off('input keyup').on('input keyup', validateDirectBulkSmsForm);
        directSmsCustomPhonesInput.off('input keyup').on('input keyup', validateDirectBulkSmsForm);
        validateDirectBulkSmsForm(); // Set initial state
    }

    // --- INITIALIZATION CALLS & Event Listeners ---
    initializeManagerDatepickers(); // Initialize datepickers
    initializeManagerTabs();      // Set up tab switching logic
    initializeNotificationQueueFeatures(); // Setup for the QUEUE tab (now the third tab)
    initializeNotificationHistoryFeatures(); // Setup for the HISTORY tab (now the second tab)
    initializeDirectSmsFeatures();

    $('[data-toggle="tooltip"]').tooltip();

    // ==============================================
    // === END UTILITY FUNCTIONS                  ===
    // ==============================================

    // --- Initial Load for Active Tab ---
    const activeTabId = $('.tab.active').data('tab-toggle');
    if (activeTabId === 'queue') { loadNotificationQueue(); }
    else if (activeTabId === 'history') { loadNotificationHistory(); }
    else if (activeTabId === 'direct-sms') { loadPilotsForDirectSmsBulk(); } // Load pilots if starting on direct tab

    // ============================  ABVOE ============================

}); // End document ready
// ======================================================
// === END: NOTIFICATIONS / SMS & Max. Times          ===
// ======================================================