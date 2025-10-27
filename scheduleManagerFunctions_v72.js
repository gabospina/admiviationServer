/**
 * scheduleManagerFunctions.js (FINAL, COMPLETE, AND RESTRUCTURED VERSION)
 * Manages the interactive schedule grid on the daily_manager.php page,
 * including auto-saving, timezone support, and notification triggers.
 */

// --- GLOBAL VARIABLES ---
const pilotSelectElements = [];
let pilotOptions = [];    // Your original master pilot list
let weekStartDate = null;
let weekEndDate = null;
let autoSaveTimer = null; // Declare it here
let weeklyAvailability = {};

// =========================================================================
// === CORE LOGIC FUNCTIONS                                              ===
// =========================================================================

/**
 * Main function that orchestrates building the schedule for a given week.
 */
function buildScheduleForWeek() {
    if (!weekStartDate) {
        console.error("buildScheduleForWeek cannot run: weekStartDate is not set.");
        return;
    }
    console.log('Building schedule for week starting:', weekStartDate);
    showLoadingOverlay("Loading schedule...");

    $.when(
        $.ajax({ url: "schedule_get_aircraft.php", dataType: "json" }),
        $.ajax({ url: "contract_get_all_contracts.php", dataType: "json" })
    ).done(function (aircraftResponse, contractResponse) {
        const aircraftData = (aircraftResponse[0]?.success) ? aircraftResponse[0].schedule : [];
        const contractData = (contractResponse[0]?.success) ? contractResponse[0].contracts : [];

        populateContractDropdown(contractData);
        populateAircraftDropdown(aircraftData);
        populateManagerContractLegend(contractData);

        if (aircraftData.length > 0) {
            buildAndPopulateSchedule(aircraftData, weekStartDate);
        } else {
            hideLoadingOverlay();
            $('table.fullsched tbody').html('<tr><td colspan="8" class="text-center">No aircraft available.</td></tr>');
        }
    }).fail(function (xhr) {
        hideLoadingOverlay();
        showErrorAlert("Network error fetching schedule data. Please refresh.");
    });
}

/**
 * Builds the visual grid and populates the dropdowns with available pilots.
 */

function buildAndPopulateSchedule1(aircraftData, startDate) {
    const $tbody = $('table.fullsched tbody');
    $tbody.empty();
    const ajaxPromises = [];
    const baseDate = new Date(startDate + 'T12:00:00Z');
    const aircraftByType = aircraftData.reduce((acc, a) => {
        (acc[a.craft_type] = acc[a.craft_type] || []).push(a);
        return acc;
    }, {});

    Object.entries(aircraftByType).forEach(([craftType, aircraftList]) => {
        $tbody.append(`<tr class="type-header"><th colspan="8">${craftType}</th></tr>`);
        aircraftList.forEach(aircraft => {
            const $row = $(`<tr data-craft-id="${aircraft.id}" data-registration="${aircraft.registration}" data-craft-type="${aircraft.craft_type}">`);
            $row.append(`<th class="registration-cell">${aircraft.registration}</th>`);
            for (let i = 0; i < 7; i++) {
                const $cell = $(`<td class="day${i}" data-day="${i}"></td>`);
                // ========================================================
                // === CHANGE #1: Use standardized 'pic'/'sic' classes  ===
                // ========================================================
                const $picGroup = $(`<div class="pilot-select-group"><label>PIC</label><select class="pilot-select pic"></select></div>`);
                const $sicGroup = $(`<div class="pilot-select-group"><label>SIC</label><select class="pilot-select sic"></select></div>`);
                $cell.append($picGroup, $sicGroup);
                $row.append($cell);
                const dateForCell = new Date(baseDate);
                dateForCell.setUTCDate(baseDate.getUTCDate() + i);
                const promise = $.ajax({
                    url: `schedule_qualified_and_available_pilots.php`,
                    data: { date: formatDate(dateForCell), craft_id: aircraft.id },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        const $picSelect = $picGroup.find('select.pic');
                        const $sicSelect = $sicGroup.find('select.sic');
                        $picSelect.append('<option value="0">-- Select PIC --</option>');
                        $sicSelect.append('<option value="0">-- Select SIC --</option>');
                        if (Array.isArray(response.users_pic)) {
                            response.users_pic.forEach(pilot => $picSelect.append(`<option value="${pilot.id}">${pilot.display_name}</option>`));
                        }
                        if (Array.isArray(response.users_sic)) {
                            response.users_sic.forEach(pilot => $sicSelect.append(`<option value="${pilot.id}">${pilot.display_name}</option>`));
                        }
                    }
                });
                ajaxPromises.push(promise);
            }
            $tbody.append($row);
        });
    });
    $.when.apply($, ajaxPromises).always(() => {
        loadAndDisplayExistingAssignments(weekStartDate, weekEndDate);
    });
}

/**
 * Builds the visual schedule grid.
 * THIS IS THE FINAL, CORRECTED VERSION that applies the contract color as a background
 * ONLY to the aircraft registration cell (the `<th>`).
 */
function buildAndPopulateSchedule(aircraftData, startDate) {
    const $tbody = $('table.fullsched tbody');
    $tbody.empty();
    const ajaxPromises = [];
    const baseDate = new Date(startDate + 'T12:00:00Z');

    // This part correctly groups all aircraft by their type.
    const aircraftByType = aircraftData.reduce((acc, a) => {
        (acc[a.craft_type] = acc[a.craft_type] || []).push(a);
        return acc;
    }, {});

    // Iterate through each group of aircraft types.
    Object.entries(aircraftByType).forEach(([craftType, aircraftList]) => {

        // The group header row remains plain, with no color.
        $tbody.append(`<tr class="type-header"><th colspan="8">${craftType}</th></tr>`);

        // Now, iterate through each individual aircraft within the group.
        aircraftList.forEach(aircraft => {

            // =========================================================================
            // === THE DEFINITIVE FIX: Create a plain row and a colored cell         ===
            // =========================================================================

            // 1. Create a plain <tr> with no background color.
            const $row = $(`<tr data-craft-id="${aircraft.id}" 
                                data-registration="${aircraft.registration}" 
                                data-craft-type="${aircraft.craft_type}">
                          `);

            // 2. Get the specific color for THIS aircraft from the data.
            const cellColor = aircraft.color || 'transparent'; // Default to transparent if no color

            // 3. Create the registration cell (<th>) and apply the style DIRECTLY TO IT.
            const $registrationCell = $(`
                <th class="registration-cell" style="background-color: ${cellColor};">
                    ${aircraft.registration}
                </th>
            `);

            // 4. Append the single colored cell to the plain row.
            $row.append($registrationCell);

            // =========================================================================


            // The rest of your function continues as normal, creating the un-styled <td> cells.
            for (let i = 0; i < 7; i++) {
                const $cell = $(`<td class="day${i}" data-day="${i}"></td>`);

                const $picGroup = $(`<div class="pilot-select-group"><label>PIC</label><select class="pilot-select pic"></select></div>`);
                const $sicGroup = $(`<div class="pilot-select-group"><label>SIC</label><select class="pilot-select sic"></select></div>`);
                $cell.append($picGroup, $sicGroup);
                $row.append($cell);

                const dateForCell = new Date(baseDate);
                dateForCell.setUTCDate(baseDate.getUTCDate() + i);

                const promise = $.ajax({
                    url: `schedule_qualified_and_available_pilots.php`,
                    data: { date: formatDate(dateForCell), craft_id: aircraft.id },
                    dataType: 'json'
                }).done(function (response) {
                    if (response.success) {
                        const $picSelect = $picGroup.find('select.pic');
                        const $sicSelect = $sicGroup.find('select.sic');
                        $picSelect.append('<option value="0">-- Select PIC --</option>');
                        $sicSelect.append('<option value="0">-- Select SIC --</option>');
                        if (Array.isArray(response.users_pic)) {
                            response.users_pic.forEach(pilot => $picSelect.append(`<option value="${pilot.id}">${pilot.display_name}</option>`));
                        }
                        if (Array.isArray(response.users_sic)) {
                            response.users_sic.forEach(pilot => $sicSelect.append(`<option value="${pilot.id}">${pilot.display_name}</option>`));
                        }
                    }
                });
                ajaxPromises.push(promise);
            }
            $tbody.append($row);
        });
    });

    // This part remains the same.
    $.when.apply($, ajaxPromises).always(() => {
        loadAndDisplayExistingAssignments(weekStartDate, weekEndDate);
    });
}

/**
 * Fetches saved assignments and applies them to the UI.
 * --- THIS IS THE FINAL, COMBINED, AND ROBUST VERSION ---
 */
function loadAndDisplayExistingAssignments(startDate, endDate) {
    $.ajax({
        url: "schedule_get_existing_assignments.php",
        data: { start_date: startDate, end_date: endDate },
        dataType: "json"
    }).done(response => {
        if (response.success && Array.isArray(response.assignments)) {

            // ===== DO NOT DELETE this consle log. It shows all the Schedule tab information
            // console.log(`--- Displaying ${response.assignments.length} Saved Assignments for the Week ---`);
            //console.table(response.assignments);

            response.assignments.forEach(a => {
                const $row = $(`tr[data-registration="${a.registration}"]`);
                if ($row.length === 0) return;

                // 1. Use the correct, standardized logic from your new function
                const posClass = a.position === 'PIC' ? 'pic' : 'sic';
                const $select = $row.find(`td.day${a.day_index} select.${posClass}`);

                // 2. THE CRITICAL SAFEGUARD from your old function
                // This prevents the fatal error and stops the page from freezing.
                if ($select.length === 0) {
                    console.warn(`Could not find dropdown for assignment:`, a);
                    return; // Stop processing this single assignment and move to the next
                }

                // 3. The robust "CONFLICT" handling from your old function
                if ($select.find(`option[value="${a.user_id}"]`).length > 0) {
                    $select.val(a.user_id);
                } else {
                    // This correctly handles cases where a pilot is assigned but is off-duty.
                    $select.append(`<option value="${a.user_id}" disabled selected>CONFLICT: User ${a.user_id}</option>`);
                    $select.closest('td').addClass('schedule-conflict');
                }

                // This line is now safe to run
                $select.data('schedule-id', a.schedule_id);
            });

            for (let i = 0; i < 7; i++) {
                enforceExclusivityForDay(i);
            }
        }
    }).fail(() => {
        showErrorAlert("Error loading saved assignments.");
    }).always(() => {
        hideLoadingOverlay();
    });
}

/**
 * Handles when a pilot is selected or deselected from a dropdown in the schedule.
 * Saves the change to the database and triggers events for other modules.
 */
function handlePilotSelectionChange() {
    const $select = $(this);
    const userId = $select.val();
    const $cell = $select.closest('td');
    const $row = $select.closest('tr');
    const dayIndex = $cell.data('day');
    if (!weekStartDate) {
        console.error("Cannot handle pilot selection: weekStartDate is not set.");
        return;
    }

    // Determine the date for this cell
    const targetDate = new Date(weekStartDate + 'T12:00:00Z');
    targetDate.setUTCDate(targetDate.getUTCDate() + dayIndex);

    // Determine the position (PIC/SIC)
    const positionToSend = $select.hasClass("pic") ? "PIC" : "SIC";

    // Prepare the data to save the schedule entry
    const dataToSend = {
        value: userId,
        pos: positionToSend,
        pk: formatDate(targetDate),
        registration: $row.data('registration'),
        craftType: $row.data('craft-type'),
        // Send the existing ID if we have one
        schedule_id_if_known: $select.data('schedule-id') || null
    };

    // Make the AJAX call to save the change
    $.ajax({
        type: "POST",
        url: "schedule_update.php", // This script saves the assignment and returns the ID
        data: dataToSend,
        dataType: "json"
    }).done(response => {
        if (response && response.success) {
            // --- THIS IS THE FIX ---
            // 1. Determine the definitive schedule ID. The server should return 'new_schedule_id'
            //    for new entries and 'schedule_id' for updates.
            const finalScheduleId = response.new_schedule_id || response.schedule_id;

            // 2. Check if we have a valid ID. If not, we cannot proceed.
            if (!finalScheduleId || finalScheduleId <= 0) {
                console.error("Failed to get a valid schedule_id from the server after saving.");
                showErrorAlert("Could not update the notification queue: Missing Schedule ID.");
                return; // Stop execution
            }

            // 3. Update the dropdown's data attribute so we have the ID for future edits.
            $select.data('schedule-id', finalScheduleId);

            // 4. Now that we are GUARANTEED to have a valid ID, trigger the appropriate event.
            if (userId && userId !== '0') {
                // A pilot was assigned.
                const assignmentData = {
                    user_id: userId,
                    pilot_name: $select.find('option:selected').text(),
                    sched_date: formatDate(targetDate),
                    registration: $row.data('registration'),
                    craft_type: $row.data('craft-type'),
                    pos: positionToSend,
                    schedule_id: finalScheduleId, // Pass the guaranteed ID
                    routing: $cell.find('.routing-input').val() || '' // Also include routing info
                };
                console.log("Triggering 'pilotAssigned' with valid schedule_id:", finalScheduleId);
                $(document).trigger('pilotAssigned', [assignmentData]);
            } else {
                // A pilot was deselected (set to 'Unassigned').
                console.log("Triggering 'pilotDeselected' for schedule_id:", finalScheduleId);
                $(document).trigger('pilotDeselected', [{ schedule_id: finalScheduleId }]);
            }

            // 5. Enforce visual exclusivity rules on the schedule grid.
            enforceExclusivityForDay(dayIndex);

        } else {
            // Handle the case where the save itself failed
            const errorMessage = response.error || "An unknown error occurred while saving the schedule.";
            showErrorAlert(errorMessage);
            // Optional: revert the select dropdown to its previous value
        }
    }).fail(function () {
        showErrorAlert("A network error occurred while saving the schedule.");
    });
}

// =========================================================================
// === HELPER & UI FUNCTIONS                                             ===
// =========================================================================

function enforceExclusivityForDay(dayIndex) {
    const $dayCells = $(`td.day${dayIndex}`);
    const selectedOnDay = new Set();
    $dayCells.find('select.pilot-select').each(function () {
        const val = $(this).val();
        if (val && val !== '0') selectedOnDay.add(val);
    });
    $dayCells.find('select.pilot-select').each(function () {
        const $select = $(this);
        const currentValue = $select.val();
        $select.find('option').each(function () {
            const $option = $(this);
            const userIdStr = $option.val();
            if (userIdStr === '0') return;
            if (selectedOnDay.has(userIdStr) && userIdStr !== currentValue) {
                $option.prop('disabled', true).hide();
            } else {
                $option.prop('disabled', false).show();
            }
        });
    });
}

/**
 * Fetches the list of aircraft to be scheduled.
 */
function loadAircraftData() {
    return $.ajax({
        url: "schedule_get_aircraft.php",
        dataType: "json"
    });
}

/**
 * ===================================================================
 * === START: NEW FUNCTIONS FOR MANAGER SCHEDULE LEGEND & Colors ===
 * ===================================================================
 */
/**
 * Populates the contract dropdown filter on the manager page.
 */
function populateContractDropdown(contracts) {
    const $contractSelect = $('#contract-select'); // Targets the existing dropdown
    $contractSelect.find('option:gt(0)').remove(); // Removes old options
    if (contracts && contracts.length > 0) {
        contracts.forEach(contract => {
            // NOTE: Your contract_get_all_contracts.php uses 'contractid' and 'contract'
            $contractSelect.append(`<option value="${contract.contractid}">${contract.contract}</option>`);
        });
    }
}

/**
 * Populates the aircraft dropdown filter on the manager page.
 */
function populateAircraftDropdown(aircraft) {
    const $craftSelect = $('#craft-select'); // Targets the existing dropdown
    $craftSelect.find('option:gt(0)').remove(); // Removes old options
    if (aircraft && aircraft.length > 0) {
        aircraft.forEach(ac => {
            $craftSelect.append(`<option value="${ac.registration}">${ac.registration}</option>`);
        });
    }
}
/**
 * Populates the contract color legend on the manager page.
 */
function populateManagerContractLegend(contracts) {
    // Targets the new div with the unique ID "legendColorsManager"
    const $legendContainer = $('#legendColorsManager');
    $legendContainer.empty(); // Clears any old content

    if (contracts && contracts.length > 0) {
        contracts.forEach(contract => {
            const legendItemHtml = `
                <div class="legend-item">
                    <span class="color-box" style="background-color: ${contract.color || '#ccc'};"></span>
                    <span>${contract.contract}</span>
                </div>
            `;
            $legendContainer.append(legendItemHtml);
        });
    }
}

/**
 * Creates a pilot select element with proper attributes and unique ID
 */
function createPilotSelect(position, dayIndex, craftId) {
    const uniqueId = `pilot-${position}-day${dayIndex}-craft${craftId}`;
    const $select = $('<select>', {
        id: uniqueId,
        class: `pilot-select ${position} day${dayIndex}`,
        'data-day-index': dayIndex,
        'data-position': position,
        'data-craft-id': craftId
    });

    pilotSelectElements.push($select);
    return $select;
}

/**
 * Collects all pilot assignments and sends them to the server
 */
function saveEntireSchedule(silentMode = false) {
    console.log('saveEntireSchedule called', { silentMode });
    // Show loading indicator if not in silent mode
    if (!silentMode) {
        showLoadingOverlay("Saving schedule...");
    }

    // Collect all assignments from the schedule
    const changes = [];

    // Loop through all pilot select elements with values
    $('select.comandante, select.piloto').each(function () {
        const $select = $(this);
        const userId = parseInt($select.val());

        // Include all selections, even empty ones (to allow clearing)
        const $row = $select.closest('tr');
        // const position = $select.hasClass('comandante') ? 'com' : 'pil';
        const position = $select.hasClass("comandante") ? "PIC" : "SIC";
        const dayIndex = $select.data('day-index');
        const date = calculateDateFromDay(dayIndex);

        // Get craft type and registration from data attributes
        const craftType = $select.data('craft-type') || $row.data('craft-type');
        const registration = $select.data('registration') || $row.data('registration') || '';

        const otherPil = $row.find('input.other-pilot').val() || '';

        console.log("Sending to server:", { date, userId, craftType, registration, position, otherPil });

        changes.push({
            craftType: craftType,
            date: date,
            userId: userId,
            position: position,
            registration: registration,
            otherPil: otherPil
        });
    });

    // Send to server
    $.ajax({
        type: "POST",
        url: "schedule_save_entire.php",
        data: {
            changes: JSON.stringify(changes)
        },
        dataType: "json",
        success: function (response) {
            if (!silentMode) hideLoadingOverlay();

            if (response.success) {
                // Clear unsaved change markers
                $('select.unsaved-change').removeClass('unsaved-change');

                // Backup to session storage
                backupSelectionsToSessionStorage();

                // Update last update time
                sessionStorage.setItem('lastUpdateTime', new Date().getTime());

                if (!silentMode) {
                    // Show success message
                    showSuccessAlert(response.message || "Schedule saved successfully!");

                    // Update UI with returned data if needed
                    if (response.updated && response.updated.length > 0) {
                        updateUIWithConfirmedChanges(response.updated);
                    }
                }
            } else {
                if (!silentMode) {
                    showErrorAlert(response.error || "Failed to save schedule");
                } else {
                    console.error("Silent save failed:", response.error);
                }
            }
        },
        error: function (xhr) {
            if (!silentMode) hideLoadingOverlay();

            let error = "Network error";
            try {
                error = JSON.parse(xhr.responseText).error || error;
            } catch (e) { }

            if (!silentMode) {
                showErrorAlert(error);
            } else {
                console.error("Silent save failed:", error);
            }
        }
    });
}

/**
 * Updates UI with confirmed changes from server
 */
function updateUIWithConfirmedChanges(updates) {
    updates.forEach(update => {
        const dayClass = `day${update.day_index}`;
        const position = update.position === 'com' ? 'comandante' : 'piloto';

        // Find the corresponding select element
        $(`select.${position}.${dayClass}`).each(function () {
            const $select = $(this);
            if ($select.val() == update.user_id) {
                // Update the display text if needed
                $select.find('option:selected').text(update.pilot_name);
            }
        });
    });
}

/**
 * Saves current selections to session storage as backup
 */
function backupSelectionsToSessionStorage() {
    const selections = {};

    // Collect all current selections
    $('select.comandante, select.piloto').each(function () {
        const $select = $(this);
        const selectId = $select.attr('id');
        const value = $select.val();

        if (selectId && value && value !== '0') {
            const $row = $select.closest('tr');

            selections[selectId] = {
                value: value,
                text: $select.find('option:selected').text(),
                dayIndex: $select.data('day-index'),
                // position: $select.hasClass('comandante') ? 'com' : 'pil',
                position: $select.hasClass("comandante") ? "PIC" : "SIC",
                craftType: $select.data('craft-type') || $row.data('craft-type') || '',
                registration: $select.data('registration') || $row.data('registration') || ''
            };
        }
    });

    // Save to session storage
    if (Object.keys(selections).length > 0) {
        sessionStorage.setItem('pilotSelections', JSON.stringify(selections));
        sessionStorage.setItem('selectionTimestamp', new Date().getTime());
    }
}

/**
 * Restores selections from session storage if database load fails
 */
function restoreSelectionsFromSessionStorage() {
    const storedSelections = sessionStorage.getItem('pilotSelections');
    const timestamp = sessionStorage.getItem('selectionTimestamp');

    // Only use stored selections if they exist and are less than 1 hour old
    if (storedSelections && timestamp) {
        const age = (new Date().getTime() - parseInt(timestamp)) / (1000 * 60); // in minutes

        if (age < 60) { // less than 1 hour old
            const selections = JSON.parse(storedSelections);
            const currentSelections = {};

            // Apply stored selections
            Object.entries(selections).forEach(([selectId, data]) => {
                const $select = $(`#${selectId}`);

                if ($select.length) {
                    currentSelections[selectId] = {
                        value: data.value,
                        text: data.text
                    };

                    // Set the value
                    $select.val(data.value);

                    // Update data attributes if they exist in the stored data
                    if (data.craftType) {
                        $select.attr('data-craft-type', data.craftType);
                    }
                    if (data.registration) {
                        $select.attr('data-registration', data.registration);
                    }
                }
            });
            return true;
        }
    }

    return false;
}

/**
 * Shows a loading overlay
 */
function showLoadingOverlay(message) {
    // Create overlay if it doesn't exist
    if ($('#loadingOverlay').length === 0) {
        $('body').append(`
            <div id="loadingOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
                <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; padding:20px; border-radius:5px; text-align:center;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div id="loadingMessage" class="mt-2">Loading...</div>
                </div>
            </div>
        `);
    }

    $('#loadingMessage').text(message);
    $('#loadingOverlay').fadeIn(200);
}

/**
 * Hides the loading overlay
 */
function hideLoadingOverlay() {
    $('#loadingOverlay').fadeOut(200);
}

/**
 * Shows a success alert
 */
function showSuccessAlert(message) {
    const alertHtml = `
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    $('#alerts-container').append(alertHtml);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        $('#alerts-container .alert:first-child').alert('close');
    }, 5000);
}

/**
 * Shows an error alert
 */
function showErrorAlert(message) {
    const alertHtml = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    $('#alerts-container').append(alertHtml);

    // Auto-dismiss after 8 seconds
    setTimeout(() => {
        $('#alerts-container .alert:first-child').alert('close');
    }, 8000);
}

/**
 * Shows a warning alert
 */
function showWarningAlert(message) {
    const alertHtml = `
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    $('#alerts-container').append(alertHtml);

    // Auto-dismiss after 6 seconds
    setTimeout(() => {
        $('#alerts-container .alert:first-child').alert('close');
    }, 6000);
}

// =========================================================================
// === AUTO-SAVE & SESSION MANAGEMENT                                    ===
// =========================================================================

function startAutoSave() {
    if (autoSaveTimer) clearInterval(autoSaveTimer);
    autoSaveTimer = setInterval(function () {
        if (hasUnsavedChanges()) {
            saveEntireSchedule(true); // silent mode
        }
    }, 2 * 60 * 1000); // 2 minutes
}

function hasUnsavedChanges() {
    return $('select.unsaved-change').length > 0;
}

/**
 * Initializes the week start and end dates
 */
function initializeWeekDates() {
    const today = new Date();
    const dayOfWeek = today.getDay(); // 0 = Sunday, 1 = Monday, etc.

    // Calculate Monday of current week (French standard)
    const monday = new Date(today);
    monday.setDate(today.getDate() - (dayOfWeek || 7) + 1);
    // monday.setDate(today.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
    monday.setHours(0, 0, 0, 0);

    // Log to verify
    console.log(`Today: ${formatDate(today)}, Day of Week: ${dayOfWeek}, Calculated Monday: ${formatDate(monday)}`);

    // Set the atepicker value to French format
    // $("#sched_week").val($.atepicker.formatDate('dd/mm/yy', monday));

    // Calculate Sunday
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    sunday.setHours(23, 59, 59, 999);

    weekStartDate = formatDate(monday);
    // weekEndDate = formatDate(sunday);
    weekEndDate = formatDate(new Date(monday.getTime() + 6 * 24 * 60 * 60 * 1000));

    // Update UI with French formatted dates
    for (let i = 0; i < 7; i++) {
        const date = new Date(monday);
        date.setDate(monday.getDate() + i);
        console.log(`day${i}: ${formatDate(date)}`);
        $(`.day${i} .date`).text(formatDateDisplay(date));
    }
}

/**
 * ===================================================================
 * === END: NEW FUNCTIONS FOR MANAGER SCHEDULE                     ===
 * ===================================================================
 */

function formatDate(date) {
    if (!(date instanceof Date) || isNaN(date)) return '';
    return date.toISOString().split('T')[0];
}

function formatDateDisplay(date) {
    if (!(date instanceof Date) || isNaN(date)) return '';
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${monthNames[date.getUTCMonth()]} ${date.getUTCDate()}`;
}

function calculateDateFromDay(dayIndex) {
    // Get current week's Monday in local time
    const now = new Date();
    const monday = new Date(now);
    monday.setDate(now.getDate() - (now.getDay() || 7) + 1);
    monday.setHours(0, 0, 0, 0);
    // Add days in local time
    const targetDate = new Date(monday);
    targetDate.setDate(monday.getDate() + parseInt(dayIndex));

    // Format as YYYY-MM-DD (local date)
    const year = targetDate.getFullYear();
    const month = String(targetDate.getMonth() + 1).padStart(2, '0');
    const day = String(targetDate.getDate()).padStart(2, '0');

    console.log('Calculated date:', {
        dayIndex,
        localDate: `${year}-${month}-${day}`,
        utcDate: targetDate.toISOString().split('T')[0]
    });

    return `${year}-${month}-${day}`;
}

// Add to your scheduleFunctions.js
function syncTimeZonesWithServer() {
    // Load saved timezones from server
    $.get('timezone_settings.php', function (response) {
        if (response.success) {
            TimeZoneManager.homeTZ = response.home_timezone;
            TimeZoneManager.currentTZ = response.current_timezone === 'auto' ?
                Intl.DateTimeFormat().resolvedOptions().timeZone : response.current_timezone;

            $('#homeTimeZone').val(TimeZoneManager.homeTZ);
            $('#currentTimeZone').val(response.current_timezone);
        }
    });

    // Save timezone changes to server
    $(document).on('timezoneChange', function (e, type, tz) {
        $.post('timezone_settings.php', {
            timezone_type: type,
            timezone: tz
        });
    });
}

function updateScheduleHeaders(startDate) {
    const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    for (let i = 0; i < 7; i++) {
        const date = new Date(startDate);
        date.setUTCDate(startDate.getUTCDate() + i);
        const month = monthNames[date.getUTCMonth()];
        const day = date.getUTCDate();
        $(`.fullsched thead .day${i}`).html(`${daysOfWeek[i]}<br><span class="date">${month} ${day}</span>`);
    }
}

// =========================================================================
// === INITIALIZATION (DEFINITIVE, COMPLETE VERSION)                     ===
// =========================================================================

$(document).ready(function () {
    console.log("--- Document Ready: Initializing Schedule Page ---");

    // --- 1. SET UP NON-SCHEDULE EVENT HANDLERS ---
    $(document).on('change', 'select.pilot-select', handlePilotSelectionChange);
    $("#saveScheduleBtn").on("click", () => saveEntireSchedule());

    // Event handler for session management when the tab becomes visible again
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            const lastUpdate = sessionStorage.getItem('lastUpdateTime');
            // Check if the page has been inactive for more than 5 minutes (300000 ms)
            if (lastUpdate && (new Date().getTime() - parseInt(lastUpdate)) > 300000) {
                // Optional: You could prompt the user to refresh or do it automatically.
                // For now, we just update the timestamp.
                sessionStorage.setItem('lastUpdateTime', new Date().getTime());
            }
        }
    });

    // Backup any unsaved changes to session storage before the page is closed
    window.addEventListener('beforeunload', backupSelectionsToSessionStorage);

    // Set the initial session timestamp
    sessionStorage.setItem('lastUpdateTime', new Date().getTime());


    // --- 2. INITIALIZE AND LOAD THE DEFAULT SCHEDULE VIEW ---
    // This is the main function that sets up the page on load.
    function initializeDefaultSchedule() {

        // A. Calculate the current week's start/end dates and store them in global variables.
        const today = new Date();
        const mondayOfCurrentWeek = moment(today).startOf('isoWeek').toDate();
        weekStartDate = formatDate(mondayOfCurrentWeek);
        weekEndDate = moment(mondayOfCurrentWeek).endOf('isoWeek').format('YYYY-MM-DD');

        // B. Update the visual date headers (e.g., "Monday Aug 18") in the schedule table.
        console.log('Defaulting to current week:', weekStartDate, 'to', weekEndDate);
        updateScheduleHeaders(mondayOfCurrentWeek);

        // C. THIS IS THE KEY: Fetch the data and build the schedule for the current week immediately on page load.
        buildScheduleForWeek();

        // D. Now, set up the Flatpickr input to allow users to select a *different* week.
        const weekPickerElement = document.querySelector("#sched_week");
        if (weekPickerElement) {
            const weekPicker = flatpickr(weekPickerElement, {
                plugins: [new weekSelect()], // This enables week selection mode.
                altInput: true,
                altFormat: "M d, Y",       // The friendly format the user sees (e.g., Aug 18, 2025).
                dateFormat: "Y-m-d",       // The format sent to the server.

                // This 'onClose' function runs ONLY when the user manually selects a new week.
                onClose: function (selectedDates) {
                    if (selectedDates.length > 0) {
                        const newMonday = moment(selectedDates[0]).startOf('isoWeek').toDate();
                        weekStartDate = formatDate(newMonday);
                        weekEndDate = moment(newMonday).endOf('isoWeek').format('YYYY-MM-DD');

                        console.log('User selected a new week, starting:', weekStartDate);
                        updateScheduleHeaders(newMonday);
                        buildScheduleForWeek(); // Re-build the schedule for the newly selected week.
                    }
                }
            });

            // Set the initial visual date of the picker to today, but don't trigger the onClose event.
            weekPicker.setDate(today, false);
        }
    }

    // --- 3. START THE APPLICATION ---
    // We run our main initialization function.
    initializeDefaultSchedule();

    // Start other background processes.
    startAutoSave();
    syncTimeZonesWithServer();
});