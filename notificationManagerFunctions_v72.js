// ======================================================
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
            url: 'daily_manager_prepare_notifications_save_item.php', // The new PHP file
            type: 'POST',
            data: { item: assignmentData }, // Send the data nested under an 'item' key
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    console.log("Successfully saved assignment to the server-side queue. New ID:", response.inserted_id);
                    // After saving, we can refresh the queue from the database to ensure it's in sync.
                    loadNotificationQueue();
                } else {
                    showErrorAlert("Could not add pilot to queue: " + (response.error || 'Unknown error'));
                    // Optional: remove the temporary item from the UI if the save fails
                    delete notificationQueue[tempKey];
                    renderNotificationQueueTable();
                }
            },
            error: function (xhr) {
                showErrorAlert("A network error occurred while adding the pilot to the queue.");
                console.error("AJAX Error saving to queue:", xhr.responseText);
                // Optional: remove the temporary item from the UI if the save fails
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

    // ==============================================================
    // === END OF PASTED LISTENERS                                ===
    // ==============================================================

    // ==================================================
    // === START: FUNCTION NOTIFICATIONS & SMS        ===
    // ==================================================
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
        // Assume delete is allowed for history (adjust if needed based on permissions obj)
        const canDeleteHistory = true; // userPermissions.canDeleteMessages;
        $('#history-check-all').prop('checked', false).prop('disabled', !canDeleteHistory);

        if (!logs || logs.length === 0) {
            historyLogTableBody.html('<tr><td colspan="7" class="text-center text-muted">No notification history found matching criteria.</td></tr>');
            return;
        }

        logs.forEach(log => {
            const $row = $('<tr>');
            $row.append(`<td style="text-align: center;"><div class="checkbox"><label><input type="checkbox" class="history-checkbox" value="${log.id}" ${canDeleteHistory ? '' : 'disabled'}></label></div></td>`);
            $row.append(`<td>${log.schedule_date || 'N/A'}</td>`);
            $row.append(`<td>${log.craft_registration || 'N/A'}</td>`);
            $row.append(`<td>${log.pilot_name || 'N/A'}</td>`);
            $row.append(`<td>${log.recipient_contact || 'N/A'}</td>`); // Added Phone
            $row.append(`<td>${log.sent_at || 'N/A'}</td>`);
            $row.append(`<td><span class="badge bg-${log.status === 'Sent' ? 'success' : (log.status === 'Failed' ? 'danger' : 'secondary')}">${log.status || 'Unknown'}</span></td>`);
            historyLogTableBody.append($row);
        });
        $('#deleteHistoryBtn').prop('disabled', true); // Disable delete initially
    }

    function deleteNotificationHistory() {
        // Assume delete allowed (add permission check if needed)
        // const canDeleteHistory = userPermissions.canDeleteMessages;
        // if (!canDeleteHistory) { showErrorAlert("Permission denied."); return; }

        const selectedIds = $('#historyLogTable .history-checkbox:checked').map(function () { return $(this).val(); }).get();
        if (selectedIds.length === 0) { showWarningAlert('Please select history entries to delete.'); return; }
        if (!confirm(`Permanently delete ${selectedIds.length} selected history log(s)?`)) { return; }

        showLoadingOverlay("Deleting history...");
        const $button = $('#deleteHistoryBtn'); // Use correct ID
        const originalHtml = $button.html();
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');

        $.ajax({
            url: 'daily_manager_delete_messages.php', // This script deletes from notifications_log
            type: 'POST', dataType: 'json', data: { log_ids: selectedIds }, // Server checks permission
            success: function (response) {
                if (response.success) { showSuccessAlert(response.message || 'Deleted.'); loadNotificationHistory(); } // Refresh history
                else { showErrorAlert(`Error: ${response.error || 'Unknown'}`); }
            },
            error: function (xhr) { showErrorAlert('AJAX Error.'); console.error("AJAX Delete History Error:", xhr.responseText); },
            complete: function () { hideLoadingOverlay(); $button.prop('disabled', true).html(originalHtml); }
        });
    }

    function loadNotificationQueue() {
        console.log("Loading saved notification queue...");
        messageQueueTableBody.html('<tr><td colspan="7" class="text-center">Loading queue... <i class="fa fa-spinner fa-spin"></i></td></tr>');
        $('#sendPreparedNotiBtn, #deleteQueueItemsBtn').prop('disabled', true);
        $.ajax({
            url: 'daily_manager_prepare_notifications_load.php', type: 'GET', dataType: 'json',
            success: function (response) {
                if (response.success && response.queue) {
                    notificationQueue = {}; // Clear JS queue
                    // Rebuild JS queue from loaded DB data
                    response.queue.forEach(item => {
                        const key = item.queue_id; // Use DB primary key as the key now
                        notificationQueue[key] = item; // Store DB data
                    });
                    renderNotificationQueueTable();
                } else {
                    showErrorAlert("Failed to load saved queue: " + (response.error || 'Unknown error'));
                    notificationQueue = {}; // Ensure queue is empty on failure
                    renderNotificationQueueTable(); // Render empty state
                }
            },
            error: function () {
                showErrorAlert("AJAX error loading saved queue.");
                notificationQueue = {}; // Ensure queue is empty on failure
                renderNotificationQueueTable(); // Render empty state
            }
        });
    }

    function deleteSelectedQueueItems() {
        const selectedKeys = []; // These are now DB queue IDs
        $('#messageQueueTable .queue-checkbox:checked').each(function () {
            // Get the queue ID (primary key) stored as value or data attribute
            // Let's assume we store queue_id as value in render function
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
            data: { queue_ids: selectedKeys }, // Send DB IDs
            success: function (response) {
                if (response.success) {
                    showSuccessAlert(response.message);
                    // Remove deleted items from JS queue object manually
                    selectedKeys.forEach(id => {
                        // Find the key corresponding to the DB id (might need to iterate)
                        for (const k in notificationQueue) {
                            if (notificationQueue[k].queue_id == id) { // Loose comparison ok
                                delete notificationQueue[k];
                                break;
                            }
                        }
                    });
                    renderNotificationQueueTable(); // Re-render JS queue
                } else { showErrorAlert("Failed to delete: " + (response.error || 'Unknown')); }
            },
            error: function () { showErrorAlert('AJAX Error deleting queue items.'); },
            complete: function () { hideLoadingOverlay(); }
        });
    }

    function updateQueueActionButtons() {
        const $sendButton = $('#sendPreparedNotiBtn'); // Or use your globally defined var
        const $deleteButton = $('#deleteQueueItemsBtn'); // Or use your globally defined var
        const $queueTable = $('#messageQueueTable');     // Or use your globally defined var

        const queueHasItems = Object.keys(notificationQueue).length > 0; // Check if the queue object has any keys
        const itemsAreSelected = $queueTable.find('.queue-checkbox:checked').length > 0;

        if ($sendButton.length) {
            $sendButton.prop('disabled', !queueHasItems);
        } else {
            console.warn("updateQueueActionButtons: #sendPreparedNotiBtn not found.");
        }

        if ($deleteButton.length) {
            $deleteButton.prop('disabled', !itemsAreSelected);
        } else {
            console.warn("updateQueueActionButtons: #deleteQueueItemsBtn not found.");
        }
        // console.log("Queue action buttons updated. Send enabled:", !(!queueHasItems), "Delete enabled:", !(!itemsAreSelected));
    }
    // ==================================================
    // === END: FUNCTION NOTIFICATIONS & SMS          ===
    // ==================================================

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

    /**
     * Renders the contents of the global `notificationQueue` object into the HTML table.
     * --- FINAL STANDARDIZED VERSION ---
     */
    function renderNotificationQueueTable() {
        const messageQueueTableBody = $('#messageQueueBody');

        if (Object.keys(notificationQueue).length === 0) {
            messageQueueTableBody.html('<tr><td colspan="7" class="text-center text-muted">Select pilots on the \'Schedule\' tab to add notifications here.</td></tr>');
            $('#sendPreparedNotiBtn, #deleteQueueItemsBtn').prop('disabled', true);
            return;
        }

        let rowsHtml = '';
        for (const key in notificationQueue) {
            if (notificationQueue.hasOwnProperty(key)) {
                const item = notificationQueue[key];

                // Standardize on using the simple property names.
                const phoneDisplay = item.phone || ''; // Use the 'phone' property or an empty string.
                const schedDate = item.sched_date;
                const registration = item.registration;
                const craftType = item.craft_type;
                const pilotName = item.pilot_name;
                const position = item.pos;
                const queueId = item.queue_id; // Will be null for temporary items

                rowsHtml += `
                <tr data-queue-id="${queueId || 'temp'}">
                    <td class="text-center">
                        <div class="checkbox" style="margin: 0;">
                            <label><input type="checkbox" class="queue-checkbox-item" value="${queueId}"></label>
                        </div>
                    </td>
                    <td>${schedDate}</td>
                    <td>${registration} (${craftType})</td>
                    <td>${pilotName}</td>
                    <td>
                        <input type="text" class="form-control phone-input" 
                               value="${phoneDisplay}" 
                               placeholder="Enter phone #" 
                               data-queue-id="${queueId}">
                    </td>
                    <td>${position}</td>
                    <td class="text-center">
                        <button class="btn btn-xs btn-danger remove-queue-item" 
                                data-queue-id="${queueId}" 
                                title="Remove this item from the queue">
                            <i class="fa fa-times"></i>
                        </button>
                    </td>
                </tr>`;
            }
        }

        messageQueueTableBody.html(rowsHtml);
        // Enable Send button; Delete button should be disabled until an item is checked.
        $('#sendPreparedNotiBtn').prop('disabled', false);
        $('#deleteQueueItemsBtn').prop('disabled', true);
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
                recipients: recipientsToSend, // Send the combined list
                message: message
            }),
            success: function (response) {
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
                    showErrorAlert("Send failed: " + (response.error || response.message || "Unknown"));
                    // Don't clear form on failure, re-enable button via complete/validate
                }
            },
            error: function (xhr, status, error) {
                console.error("Direct SMS AJAX error:", status, error, xhr.responseText);
                showErrorAlert("AJAX Error sending SMS. Status: " + status);
                // Don't clear form on failure
            },
            complete: function (xhr, status) {
                console.log(`AJAX 'complete' Direct SMS. Status: ${status}`);
                hideLoadingOverlay();
                // Always restore button text and let validation handle disabled state
                directSmsBulkSendBtn.html('<i class="fa fa-paper-plane"></i> Send Bulk SMS to Selected Recipients');
                validateDirectBulkSmsForm();
            }
        });
    }

    function sendPreparedNotifications1() {
        const $button = $('#sendPreparedNotiBtn');
        const queueItemsToProcess = Object.values(notificationQueue);
        if (queueItemsToProcess.length === 0) { showWarningAlert('Queue is empty.'); return; }

        let notificationsToSend = []; let hasMissingOrInvalidPhones = false;
        let processedQueueIds = [];

        queueItemsToProcess.forEach(item => {
            // let currentPhone = notificationQueue[item.queue_id]?.phone || null;
            let currentPhone = item.phone || item.target_phone || null;
            let cleanedPhone = null;
            let phoneIsValid = false;

            if (currentPhone) {
                // Clean the phone number rigorously
                cleanedPhone = String(currentPhone).replace(/[\s\-\(\)\.]+/g, ''); // Remove space, dash, parens, dots

                // *** ADD '+' PREFIX LOGIC (Similar to PHP) ***
                if (cleanedPhone.charAt(0) !== '+') {
                    // Basic North America assumption (adjust if needed for other countries)
                    if (cleanedPhone.length === 10) { // e.g., 5147586158
                        cleanedPhone = '+1' + cleanedPhone; // Prepend +1
                    } else if (cleanedPhone.length === 11 && cleanedPhone.charAt(0) === '1') { // e.g., 15147586158
                        cleanedPhone = '+' + cleanedPhone; // Prepend +
                    } else {
                        // Attempt to add '+' - might be wrong country code
                        // console.warn("Assuming country code present, adding '+':", cleanedPhone);
                        cleanedPhone = '+' + cleanedPhone;
                    }
                }
                // *** END '+' PREFIX LOGIC ***

                // Validate E.164 format using the Regex
                if (/^\+[1-9]\d{1,14}$/.test(cleanedPhone)) {
                    phoneIsValid = true;
                }
            } // End if (currentPhone)

            if (phoneIsValid) {
                notificationsToSend.push({
                    schedule_id: notificationQueue[item.queue_id]?.schedule_id || null,
                    user_id: item.user_id,
                    sched_date: item.schedule_date,
                    registration: item.registration,
                    pos: item.position,
                    phone: cleanedPhone, // Send the E.164 formatted number
                    pilot_name: notificationQueue[item.queue_id]?.pilot_name || item.pilot_name
                });
                processedQueueIds.push(item.queue_id);
            } else {
                hasMissingOrInvalidPhones = true;
                // Show the original input phone in the warning for clarity
                showWarningAlert(`Skipping ${item.pilot_name}: Invalid/Missing phone [${currentPhone || 'Empty'}]`);
                console.warn(`Invalid phone after cleaning for ${item.pilot_name}: Raw='${currentPhone}', Cleaned='${cleanedPhone}'`);
            }
        }); // End forEach

        // ... (rest of the function: check notificationsToSend.length, confirm, AJAX call, success/error/complete) ...
        if (notificationsToSend.length === 0) { showErrorAlert('No valid notifications to send.'); return; }

        if (!confirm(`Send ${notificationsToSend.length} notification(s)?` + (hasMissingOrInvalidPhones ? "\n(Some queue items were skipped due to phone number issues)" : ""))) { return; }

        showLoadingOverlay(`Sending ${notificationsToSend.length} notifications...`);
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');

        $.ajax({
            url: 'daily_manager_prepare_send_notifications.php', // Use the correct backend script
            type: 'POST', contentType: 'application/json', dataType: 'json',
            data: JSON.stringify({ notifications: notificationsToSend }), // Send valid items
            success: function (response) {
                if (response.success) {
                    showSuccessAlert('Send successful!');
                    notificationQueue = {}; // Clear queue ONLY on success
                    renderNotificationQueueTable(); // Re-render empty queue
                    loadNotificationHistory(); // Refresh history
                } else { /* show backend error */ showErrorAlert(`Send failed: ${response.error || 'Unknown'}`); }
            },
            error: function (xhr) { /* show ajax error */ },
            complete: function () { /* hide overlay, reset button */ $button.prop('disabled', true).html('<i class="fa fa-paper-plane"></i> Send All Prepared'); }
        });

    }

    // In notificationManagerFunctions.js

    /**
     * Gathers, validates, and sends all items currently in the notification queue.
     * --- THIS IS THE CORRECTED VERSION ---
     */
    function sendPreparedNotifications() {
        const $button = $('#sendPreparedNotiBtn');
        const queueItemsToProcess = Object.values(notificationQueue);
        if (queueItemsToProcess.length === 0) { showWarningAlert('Queue is empty.'); return; }

        let notificationsToSend = [];
        let hasMissingOrInvalidPhones = false;

        // Loop through each item in our JavaScript queue object
        queueItemsToProcess.forEach(item => {
            // ===================================================================
            // === THE FIX: Read the CURRENT phone number from the input field
            // ===================================================================
            const $inputField = $(`input.phone-input[data-queue-id="${item.queue_id}"]`);
            const currentPhone = $inputField.val().trim(); // Get the number from the visible input

            let cleanedPhone = null;
            let phoneIsValid = false;

            if (currentPhone) {
                cleanedPhone = String(currentPhone).replace(/[\s\-\(\)\.]+/g, '');
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
                    schedule_id: item.schedule_id || null,
                    user_id: item.user_id,
                    sched_date: item.sched_date,
                    registration: item.registration,
                    pos: item.pos,
                    phone: cleanedPhone, // Send the validated number
                    pilot_name: item.pilot_name
                });
            } else {
                hasMissingOrInvalidPhones = true;
                showWarningAlert(`Skipping ${item.pilot_name}: Invalid/Missing phone [${currentPhone || 'Empty'}]`);
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
            data: JSON.stringify({ notifications: notificationsToSend }),
            success: function (response) {
                if (response.success) {
                    showSuccessAlert('Send successful!');
                    notificationQueue = {}; // Clear queue on success
                    renderNotificationQueueTable();
                    if (typeof loadNotificationHistory === 'function') loadNotificationHistory();
                } else {
                    showErrorAlert(`Send failed: ${response.error || 'Unknown'}`);
                }
            },
            error: function () { showErrorAlert('AJAX error sending notifications.'); },
            complete: function () {
                hideLoadingOverlay();
                $button.prop('disabled', true).html('<i class="fa fa-paper-plane"></i> Send All Prepared');
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
        $('#deleteQueueItemsBtn').off('click').on('click', deleteSelectedQueueItems);

        // Listeners for QUEUE checkboxes
        $(document).off('change.queue', '#queue-check-all').on('change.queue', '#queue-check-all', function () {
            const isChecked = this.checked;
            $('#messageQueueTable .queue-checkbox:not(:disabled)').prop('checked', isChecked);
            $('#deleteQueueItemsBtn').prop('disabled', $('#messageQueueTable .queue-checkbox:checked').length === 0);
        });
        // $('#messageQueueTable').on('change', '.queue-checkbox', function () {
        //     $('#deleteQueueItemsBtn').prop('disabled', $('#messageQueueTable .queue-checkbox:checked').length === 0);
        //     if (!this.checked) { $('#queue-check-all').prop('checked', false); }
        // });

        $('#messageQueueTable').on('change', '.queue-checkbox', function () {
            // $('#deleteQueueItemsBtn').prop('disabled', $('#messageQueueTable .queue-checkbox:checked').length === 0); // Old way
            updateQueueActionButtons(); // Call the central function
            if (!this.checked) {
                $('#queue-check-all').prop('checked', false);
            } else {
                // Check if all are checked
                if ($('#messageQueueTable .queue-checkbox:not(:checked)').length === 0) {
                    $('#queue-check-all').prop('checked', true);
                }
            }
        });

        // Listener for QUEUE select all checkbox
        $(document).on('change', '#queue-check-all', function () {
            const isChecked = this.checked;
            $('#messageQueueTable .queue-checkbox:not(:disabled)').prop('checked', isChecked);
            // $('#deleteQueueItemsBtn').prop('disabled', !isChecked && $('#messageQueueTable .queue-checkbox:checked').length === 0); // Old way
            updateQueueActionButtons(); // Call the central function
        });

        renderNotificationQueueTable(); // Render initial empty state
    }

    function initializeNotificationHistoryFeatures() {
        console.log("Initializing Notification History Features");
        // Assume delete allowed for now
        const canDeleteHistory = true; // userPermissions.canDeleteMessages;

        $('#history_date_filter, #history_search_filter, #refreshHistoryBtn').prop('disabled', false);
        $('#refreshHistoryBtn').off('click').on('click', loadNotificationHistory);
        $('#history_search_filter').off('keyup.history').on('keyup.history', debounce(loadNotificationHistory, 500));
        // Date filter listener added in datepicker init


        if (canDeleteHistory) {
            $('#deleteHistoryBtn').show().prop('disabled', true); // Show delete button, disabled initially
            $('#history-check-all').prop('disabled', false);

            $('#deleteHistoryBtn').off('click.history').on('click.history', deleteNotificationHistory);
            // Event listeners for history checkboxes to enable/disable delete button
            $(document).off('change.history', '#history-check-all').on('change.history', '#history-check-all', function () {

                const isChecked = this.checked;
                $('#historyLogTable .history-checkbox:not(:disabled)').prop('checked', isChecked);
                $('#deleteHistoryBtn').prop('disabled', !isChecked && $('#historyLogTable .history-checkbox:checked').length === 0);
            });
            $('#historyLogTable').on('change', '.history-checkbox', function () {
                $('#deleteHistoryBtn').prop('disabled', $('#historyLogTable .history-checkbox:checked').length === 0);
                if (!this.checked) { $('#history-check-all').prop('checked', false); }
            });
        } else {
            $('#deleteHistoryBtn').hide();
            $('#history-check-all').prop('disabled', true);
        }
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