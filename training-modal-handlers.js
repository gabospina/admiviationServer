// training-modal-handlers.js

let currentEventForEdit = null;

/**
 * Launches and populates the "Edit Training Event" modal (#editEvent).
 * This is the primary entry point when an event is clicked.
 * @param {object} fullServerEventObject - The complete, original event object from our server.
 */
function launchEditModal(fullServerEventObject) {
    // This function now receives the CLEAN, original data object directly.
    console.log("launchEditModal called for event:", fullServerEventObject);
    currentEventForEdit = fullServerEventObject;

    const $modal = $("#editEvent");
    const modalStartDate = moment(fullServerEventObject.start);
    const modalInclusiveEndDate = moment(fullServerEventObject.end).subtract(1, 'day');

    // --- 1. Prepare Modal UI ---
    // Use the pre-formatted title strings from the server object.
    const displayTitle = `${fullServerEventObject.craft} - ${fullServerEventObject.pilots}`;
    $modal.find("#currentEventSelection").text(displayTitle);
    $modal.find("#removePilotName").html(`<b>${escapeHtml(displayTitle)}</b>`);
    const dateStr = `<b>${modalStartDate.format("MMMM D")} to ${modalInclusiveEndDate.format("D, YYYY")}</b>`;
    $modal.find("#removePilotDates").html(dateStr);

    // --- 2. Initialize Datepickers ---
    if (typeof flatpickr === 'function') {
        flatpickr("#editStartDate", { dateFormat: "d-m-Y", defaultDate: modalStartDate.toDate() });
        flatpickr("#editEndDate", { dateFormat: "d-m-Y", defaultDate: modalInclusiveEndDate.toDate() });
    } else {
        $('#editStartDate').val(modalStartDate.format('DD-MM-YYYY'));
        $('#editEndDate').val(modalInclusiveEndDate.format('DD-MM-YYYY'));
    }

    // --- 3. Wire up the "Save Changes" Button ---
    const $changeBtn = $modal.find("#confirmChange");
    $changeBtn.off('click').on('click', function () {
        // This button's logic remains the same, but it uses the correct event ID from the stored object.
        const originalText = $(this).html();
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        const start = moment($('#editStartDate').val(), 'DD-MM-YYYY');
        const end = moment($('#editEndDate').val(), 'DD-MM-YYYY');

        const eventDataForSave = {
            start: start.format('YYYY-MM-DD'),
            length: end.diff(start, 'days') + 1,
            tri1: $('#triList input:checked')[0]?.value || null,
            tri2: $('#triList input:checked')[1]?.value || null,
            tre: $('#treList input:checked')[0]?.value || null,
            ids: JSON.stringify(
                $('#traineeList input:checked').map((i, el) => $(el).val()).get().slice(0, 4)
            )
        };

        updateTrainingEvent(currentEventForEdit.id, eventDataForSave)
            .then(response => {
                showNotification('success', response.message || 'Event updated successfully!');
                $modal.modal('hide');
                if (mainCalendarInstance) mainCalendarInstance.refetchEvents(); // Use v6 API
            })
            .catch(err => {
                showNotification('error', err.error || 'Failed to update event.');
            })
            .finally(() => {
                $(this).prop('disabled', false).html(originalText);
            });
    });

    // --- 4. Wire up the "Remove Event" Button ---
    const $removeBtn = $modal.find("#confirmRemoval");
    $removeBtn.off('click').on('click', function () {
        if (!confirm("Are you sure you want to permanently remove this training event?")) {
            return;
        }
        const originalText = $(this).html();
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Removing...');

        removeTrainingEvent(currentEventForEdit.id)
            .then(response => {
                showNotification('success', response.message || 'Event removed successfully!');
                $modal.modal('hide');
                if (mainCalendarInstance) mainCalendarInstance.refetchEvents(); // Use v6 API
            })
            .catch(err => {
                showNotification('error', err.error || 'Failed to remove event.');
            })
            .finally(() => {
                $(this).prop('disabled', false).html(originalText);
            });
    });

    // --- 5. Load Dynamic Data (Personnel Lists) ---
    // Pass the correct full server object to the loader.
    loadEditModalData(fullServerEventObject, modalStartDate, modalInclusiveEndDate);

    // --- 6. Show the Modal ---
    $modal.modal("show");
}

/**
 * Loads data for the edit modal (TREs, TRIs, Trainees)
 */
function loadEditModalData1(event, start, end) {
    const $triList = $('#triList');
    const $treList = $('#treList');
    const $traineeList = $('#traineeList');

    // Show loading state
    $triList.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i></p>');
    $treList.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i></p>');
    $traineeList.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i></p>');

    // Get a list of currently assigned pilots
    const currentPilots = [event.pilotid1, event.pilotid2, event.pilotid3, event.pilotid4].filter(Boolean);
    const currentTRIs = [event.tri1id, event.tri2id].filter(Boolean);
    const currentTREs = [event.treid].filter(Boolean);

    // Fetch all available personnel in parallel
    Promise.all([
        fetchAvailableTRIs(event.craft, start, end),
        fetchAvailableTREs(event.craft, start, end),
        fetchAvailablePilots(event.craft, start, end)
    ]).then(([availableTRIs, availableTREs, availablePilots]) => {

        // Populate lists and pre-select the ones that are already assigned to the event
        populatePersonnelList($triList, availableTRIs, 'edit_tri_ids', 2, currentTRIs);
        populatePersonnelList($treList, availableTREs, 'edit_tre_ids', 1, currentTREs);
        populatePersonnelList($traineeList, availablePilots, 'edit_pilot_ids', 4, currentPilots);

    }).catch(error => {
        console.error("Error loading data for Edit Modal:", error);
        showNotification('error', 'Could not load all personnel lists for editing.');
        $triList.html('<p class="text-danger">Error</p>');
        $treList.html('<p class="text-danger">Error</p>');
        $traineeList.html('<p class="text-danger">Error</p>');
    });
}

/**
 * Helper function to load all dynamic data for the Edit Modal.
 * It receives the full server event object.
 */
function loadEditModalData(serverEvent, start, end) {
    const $triList = $('#triList');
    const $treList = $('#treList');
    const $traineeList = $('#traineeList');

    $triList.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i></p>');
    $treList.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i></p>');
    $traineeList.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i></p>');

    // Get a list of currently assigned personnel IDs directly from the server object.
    const currentPilots = [serverEvent.pilotid1, serverEvent.pilotid2, serverEvent.pilotid3, serverEvent.pilotid4].filter(Boolean);
    const currentTRIs = [serverEvent.tri1id, serverEvent.tri2id].filter(Boolean);
    const currentTREs = [serverEvent.treid].filter(Boolean);

    // Fetch all available personnel for the given craft and dates.
    Promise.all([
        fetchAvailableTRIs(serverEvent.craft, start, end),
        fetchAvailableTREs(serverEvent.craft, start, end),
        fetchAvailablePilots(serverEvent.craft, start, end)
    ]).then(([availableTRIs, availableTREs, availablePilots]) => {
        // Populate the lists and pre-select the currently assigned personnel.
        populatePersonnelList($triList, availableTRIs, 'edit_tri_ids', 2, currentTRIs);
        populatePersonnelList($treList, availableTREs, 'edit_tre_ids', 1, currentTREs);
        populatePersonnelList($traineeList, availablePilots, 'edit_pilot_ids', 4, currentPilots);
    }).catch(error => {
        console.error("Error loading data for Edit Modal:", error);
        showNotification('error', 'Could not load all personnel lists for editing.');
        $triList.html('<p class="text-danger">Error</p>');
        $treList.html('<p class="text-danger">Error</p>');
        $traineeList.html('<p class="text-danger">Error</p>');
    });
}

/**
 * Launches the trainer edit modal
 */
function launchTrainerEditModal(event) {
    const eventData = event.extendedProps || event;
    console.log("launchTrainerEditModal called for event:", eventData);

    $("#editTrainerEvent #currentEventSelectionTrainer").text(event.title || "N/A");
    $("#editTrainerEvent #removeTrainerName").html("<b>" + escapeHtml(event.title || "N/A") + "</b>");

    const modalStartDate = moment(event.start);
    const modalInclusiveEndDate = moment(event.end).subtract(1, 'day');
    const dateStrForDisplay = modalStartDate.format("MM-DD-YYYY") + "</b> to <b>" + modalInclusiveEndDate.format("MM-DD-YYYY");
    $("#editTrainerEvent #removeTrainerDates").html("<b>" + dateStrForDisplay + "</b>");

    $("#editTrainerEvent #confirmTrainerRemoval")
        .attr("data-pk", event.id)
        .off('click')
        .on('click', function () {
            removeTrainer($(this).attr("data-pk"));
        });

    // Load trainer list
    const ajaxCallStartDate = modalStartDate.format("YYYY-MM-DD");
    const ajaxCallEndDate = modalInclusiveEndDate.format("YYYY-MM-DD");

    $("#editTrainerEvent #changeTrainerList").empty().html("<div class='select'><i class='fa fa-spinner fa-spin'></i> Loading trainers...</div>");

    $.ajax({
        type: "GET",
        url: "trainers_get_all.php",
        data: { start: ajaxCallStartDate, end: ajaxCallEndDate },
        dataType: "json",
        success: function (trainersArray) {
            $("#editTrainerEvent #changeTrainerList").empty();

            if (Array.isArray(trainersArray) && trainersArray.length > 0) {
                trainersArray.forEach(trainer => {
                    $("#editTrainerEvent #changeTrainerList").append(`<div class='select ${trainer.expired || ''}' data-pk='${trainer.id}' data-pos='${trainer.training_level || '1'}'>${escapeHtml(trainer.name)}</div>`);
                });

                $("#editTrainerEvent #changeTrainerList .select").off('click').on('click', function () {
                    $("#editTrainerEvent #changeTrainerList .select").removeClass("selected");
                    $(this).addClass("selected");

                    const selectedPos = parseInt($(this).data("pos"));
                    if (selectedPos === 2) {
                        $("#editTrainerEvent #changeTrainerPosition option[value='tri']").prop("selected", false).prop("disabled", false);
                        $("#editTrainerEvent #changeTrainerPosition option[value='tre']").prop("disabled", false).prop("selected", true);
                        $("#editTrainerEvent #changeTrainerPosition option[value='tri/tre']").prop("disabled", false);
                    } else {
                        $("#editTrainerEvent #changeTrainerPosition option[value='tri']").prop("selected", true).prop("disabled", false);
                        $("#editTrainerEvent #changeTrainerPosition option[value='tre']").prop("disabled", true).prop("selected", false);
                        $("#editTrainerEvent #changeTrainerPosition option[value='tri/tre']").prop("disabled", true).prop("selected", false);
                    }
                });

                // Pre-select current trainer
                if (event.pilot_id) {
                    $(`#editTrainerEvent #changeTrainerList .select[data-pk='${event.pilot_id}']`).trigger('click');
                }
                if (event.position) {
                    $(`#editTrainerEvent #changeTrainerPosition option[value='${event.position.toLowerCase()}']`).prop("selected", true);
                }

            } else {
                $("#editTrainerEvent #changeTrainerList").html("<div class='select text-muted'>No alternative trainers available.</div>");
            }

            // Set dates
            const changeTrainerStartPicker = document.querySelector("#editTrainerEvent #changeTrainerStart")._flatpickr;
            const changeTrainerEndPicker = document.querySelector("#editTrainerEvent #changeTrainerEnd")._flatpickr;

            if (changeTrainerStartPicker) {
                changeTrainerStartPicker.setDate(modalStartDate.toDate(), true);
            }
            if (changeTrainerEndPicker) {
                changeTrainerEndPicker.setDate(modalInclusiveEndDate.toDate(), true);
            }

            // Set up change button
            $("#editTrainerEvent #confirmTrainerChange").off('click').on('click', function () {
                changeTrainer(event.id);
            });

            $("#editTrainerEvent").modal("show");
        },
        error: function () {
            $("#editTrainerEvent #changeTrainerList").html("<div class='select text-danger'>Error loading trainers.</div>");
        }
    });
}

/**
 * Launches and populates the "Add Training Event" modal (#eventModal).
 * This function orchestrates the entire modal's state and data loading.
 * @param {moment} start - The start of the selection from FullCalendar.
 * @param {moment} end - The end of the selection from FullCalendar (exclusive).
 */

/**
 * Launches and populates the "Add Training Event" modal (#eventModal).
 */
function launchModal(start, end) {
    const $modal = $('#eventModal');
    const inclusiveEndDate = end.clone().subtract(1, 'day');

    // --- THIS IS THE FIX ---
    // Hide the availability section by default to prioritize adding events.
    $modal.find('.eventAvailSection').hide();
    $modal.find('.eventDetailsSection').show();
    // A manager could toggle this view with another button if needed later.
    // --- END OF FIX ---

    resetAddEventModal(start, inclusiveEndDate);
    // We still run this to set up the button logic in the background
    setupAvailabilitySection(start, inclusiveEndDate);
    setupAddEventSection(start, inclusiveEndDate);

    $modal.modal('show');
}

/**
 * Sets up the top part of the modal for enabling/disabling date ranges.
 */
function setupAvailabilitySection(start, end) {
    // Determine if the selected range is fully enabled, disabled, or mixed
    let isAllDisabled = true, isAllEnabled = true;
    for (let m = moment(start); m.isSameOrBefore(end, 'day'); m.add(1, 'days')) {
        if ($(`.fc-day[data-date='${m.format("YYYY-MM-DD")}']`).hasClass("alert-null")) {
            isAllEnabled = false; // Found a disabled day
        } else {
            isAllDisabled = false; // Found an enabled day
        }
    }

    // Configure UI based on the state
    const $header = $("#eventModal #availabilityHeader");
    const $enableBtn = $("#eventModal #confirmEnableAvailability");
    const $disableBtn = $("#eventModal #confirmDisableAvailability");

    if (isAllDisabled) {
        $header.text("Enable the following dates for training:");
        $enableBtn.show();
        $disableBtn.hide();
    } else if (isAllEnabled) {
        $header.text("Disable the following dates for training:");
        $enableBtn.hide();
        $disableBtn.show();
    } else {
        $header.text("Enable or Disable the following dates:");
        $enableBtn.show();
        $disableBtn.show();
    }

    // Set date display
    const dateRangeHtml = start.isSame(end, 'day')
        ? `<b>${start.format("MMMM D, YYYY")}</b>`
        : `<b>${start.format("MMMM D")} - ${end.format("D, YYYY")}</b>`;
    $("#eventModal .startEndDates").html(dateRangeHtml);

    // Attach event listeners for the buttons
    $enableBtn.off('click').on('click', () => handleAvailabilityChange(start, end, 'enable'));
    $disableBtn.off('click').on('click', () => handleAvailabilityChange(start, end, 'disable'));
}

/**
 * Handles the click for enabling or disabling availability.
 */
function handleAvailabilityChange(start, end, action) {
    updateTrainingDatesOnServer(start, end, action) // from ajax-operations.js
        .then(response => {
            showNotification("success", `Availability successfully ${action}d.`);
            $('#eventModal').modal('hide');
            // Instead of updateCalendarView(), we call the specific AJAX function
            return fetchTrainingDates();
        })
        .then(() => {
            // After fetching the new dates, just re-render the calendar.
            // FullCalendar will automatically apply the new dayRender classes.
            $('#calendar').fullCalendar('rerenderEvents');
        })
        .catch(error => {
            showNotification("error", `Failed to ${action} availability.`);
        });
}

/**
 * Sets up the bottom part of the modal for adding a new training event.
 * @param {moment} start - The correct, inclusive start date.
 * @param {moment} inclusiveEnd - The correct, inclusive end date.
 */
function setupAddEventSection(start, inclusiveEnd) {
    const $craftSelect = $('#addEventCraft');
    const $addStartDate = $('#addStartDate');
    const $addEndDate = $('#addEndDate');

    if (typeof flatpickr === 'function') {
        // Initialize flatpickr with the correct, inclusive dates.
        flatpickr($addStartDate[0], {
            dateFormat: "d-m-Y",
            defaultDate: start.toDate()
        });
        flatpickr($addEndDate[0], {
            dateFormat: "d-m-Y",
            defaultDate: inclusiveEnd.toDate()
        });
    }

    fetchAircraftList()
        .then(crafts => {
            $craftSelect.empty().append('<option value="">-- Select Aircraft --</option>');
            crafts.forEach(craftName => {
                $craftSelect.append(`<option value="${escapeHtml(craftName)}">${escapeHtml(craftName)}</option>`);
            });
            $craftSelect.prop('disabled', false);
        });

    // When craft or dates change, reload personnel
    $('#addEventCraft, #addStartDate, #addEndDate').off('change').on('change', loadPersonnelForModal);
}

/**
 * Resets the "Add Event" modal to its initial state.
 * @param {moment} start - The correct, inclusive start date.
 * @param {moment} inclusiveEnd - The correct, inclusive end date.
 */
function resetAddEventModal1(start, inclusiveEnd) {
    const $modal = $('#eventModal');
    // Assuming the modal content is wrapped in a <form> tag to easily reset inputs
    const $form = $modal.find('form');
    if ($form.length) {
        $form[0].reset();
    }

    $('#addEventCraft').empty().append('<option value="">-- Loading Aircraft --</option>').prop('disabled', true);
    $('#triListSection').html('<p class="text-muted">Select aircraft and date range.</p>');
    $('#treListSection').html('<p class="text-muted">Select aircraft and date range.</p>');
    $('#TRIpilotListSection').html('<p class="text-muted">Select aircraft and date range.</p>');

    // Use the corrected dates to set the input values
    $('#addStartDate').val(start.format('DD-MM-YYYY'));
    $('#addEndDate').val(inclusiveEnd.format('DD-MM-YYYY'));

    $('#confirmAdd').prop('disabled', true);
}

/**
 * Resets the "Add Event" modal to its initial state.
 */
function resetAddEventModal(start, inclusiveEnd) {
    const $modal = $('#eventModal');
    const $form = $modal.find('form'); // It's better to wrap modal content in a <form>
    if ($form.length) {
        $form[0].reset();
    }

    $('#addEventCraft').empty().append('<option value="">-- Loading Aircraft --</option>').prop('disabled', true);
    $('#triListSection').html('<p class="text-muted">Select aircraft and date range.</p>');
    $('#treListSection').html('<p class="text-muted">Select aircraft and date range.</p>');
    $('#TRIpilotListSection').html('<p class="text-muted">Select aircraft and date range.</p>');

    // Set dates in the correct format for Flatpickr
    $('#addStartDate').val(start.format('DD-MM-YYYY'));
    $('#addEndDate').val(inclusiveEnd.format('DD-MM-YYYY'));

    // The "Add Selection" button should be disabled until data is loaded.
    $('#confirmAdd').prop('disabled', true);
}

/**
 * Fetches and populates all personnel lists in the "Add Event" modal.
 */
function loadPersonnelForModal() {
    const selectedCraft = $('#addEventCraft').val();
    const start = moment($('#addStartDate').val(), 'DD-MM-YYYY');
    const end = moment($('#addEndDate').val(), 'DD-MM-YYYY');

    const $triList = $('#triListSection');
    const $treList = $('#treListSection');
    const $pilotList = $('#TRIpilotListSection');

    // Do nothing if required info is missing
    if (!selectedCraft || !start.isValid() || !end.isValid()) {
        $('#confirmAdd').prop('disabled', true);
        return;
    }

    // Show loading indicators
    $triList.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</p>');
    $treList.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</p>');
    $pilotList.html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</p>');
    $('#confirmAdd').prop('disabled', true);

    // Fetch all three lists in parallel for efficiency
    Promise.all([
        fetchAvailableTRIs(selectedCraft, start, end),
        fetchAvailableTREs(selectedCraft, start, end),
        fetchAvailablePilots(selectedCraft, start, end)
    ]).then(([tris, tres, pilots]) => {
        // Populate lists once ALL data has arrived
        populatePersonnelList($triList, tris, 'tri_ids', 2);
        populatePersonnelList($treList, tres, 'tre_ids', 1);
        populatePersonnelList($pilotList, pilots, 'pilot_ids', 4);

        // Enable the save button
        $('#confirmAdd').prop('disabled', false);
    }).catch(error => {
        showNotification('error', 'Could not load personnel lists.');
        $triList.html('<p class="text-danger">Error loading data.</p>');
        $treList.html('<p class="text-danger">Error loading data.</p>');
        $pilotList.html('<p class="text-danger">Error loading data.</p>');
    });
}

/**
 * Helper function to build a checkbox list for personnel.
* @param {jQuery} $container - The jQuery element to append the list to.
 * @param {Array} personnel - Array of objects with 'id' and 'name' properties.
 * @param {string} inputName - The name attribute for the checkbox inputs.
 * @param {number} limit - The maximum number of selections allowed.
 * @param {Array<number>} [preselectedIds=[]] - An array of IDs to check by default.
 */
function populatePersonnelList($container, personnel, inputName, limit, preselectedIds = []) {
    $container.empty();

    // Create a Set for efficient lookup
    const preselectedSet = new Set(preselectedIds);

    // Ensure currently selected people are in the list, even if they're otherwise "unavailable"
    // This logic needs to be handled in the PHP scripts by including pilots from the current event.
    // We assume for now the PHP scripts do this.

    if (personnel.length === 0) {
        $container.html('<p class="text-muted">None available.</p>');
        return;
    }

    personnel.forEach(person => {
        // Check if this person's ID is in our pre-selected set
        const isChecked = preselectedSet.has(person.id);
        const html = `
            <div class="checkbox">
                <label>
                    <input type="checkbox" name="${inputName}[]" value="${person.id}" ${isChecked ? 'checked' : ''}>
                    ${escapeHtml(person.name)}
                </label>
            </div>`;
        $container.append(html);
    });

    // Add logic to enforce the selection limit
    $container.find('input[type="checkbox"]').on('change', function () {
        if ($container.find('input:checked').length > limit) {
            this.checked = false;
            showNotification('warning', `You can select a maximum of ${limit} for this role.`);
        }
    });
}

/**
 * Launches the trainer modal
 */
function launchTrainerModal(start, end, isAllDisabled, isAllEnabled) {
    console.log("launchTrainerModal called for dates:", start.format("YYYY-MM-DD"), "to", end.format("YYYY-MM-DD"));

    const isSingleDayLocal = end.diff(start, 'days') <= 1;
    const local_end_for_ajax = isSingleDayLocal ? start.format("YYYY-MM-DD") : moment(end).subtract(1, 'day').format("YYYY-MM-DD");

    // Setup enable/disable UI
    enableAvailSelect();
    enableEventSelect();

    if (isAllDisabled) {
        $("#trainerEventModal #availabilityHeader").text("Enable the following dates:");
        $("#trainerEventModal #confirmEnableAvailabilityTrainer").show();
        $("#trainerEventModal #confirmDisableAvailabilityTrainer").hide();
    } else if (isAllEnabled) {
        $("#trainerEventModal #availabilityHeader").text("Disable the following dates:");
        $("#trainerEventModal #confirmEnableAvailabilityTrainer").hide();
        $("#trainerEventModal #confirmDisableAvailabilityTrainer").show();
    } else {
        $("#trainerEventModal #availabilityHeader").text("Enable or disable the following dates:");
        $("#trainerEventModal #confirmEnableAvailabilityTrainer").show();
        $("#trainerEventModal #confirmDisableAvailabilityTrainer").show();
    }

    if (isSingleDayLocal) {
        $("#trainerEventModal .startEndDates").html("<b>" + start.format("YYYY-MM-DD") + "</b>");
    } else {
        $("#trainerEventModal .startEndDates").html("<b>" + start.format("YYYY-MM-DD") + " - " + local_end_for_ajax + "</b>");
    }

    // Load trainers
    loadTrainersForModal(start.format("YYYY-MM-DD"), local_end_for_ajax);

    // Show modal
    $("#trainerEventModal").modal("show");
}

/**
 * Loads aircraft types for the modal
 */
function loadAircraftTypesForModal(startDate, endDate) {
    $(".pilotListSection, #triListSection, #treListSection").html("<div class='select'>Select an Aircraft</div>");
    $("#addEventCraft").html("<option disabled selected>Loading Aircraft...</option>");

    $.ajax({
        type: "GET",
        url: "trainer_get_all_crafts.php",
        data: { distinct: true },
        dataType: "json",
        success: function (craftsArray) {
            $("#addEventCraft").html("<option disabled selected>Select Aircraft</option>");

            if (Array.isArray(craftsArray) && craftsArray.length > 0) {
                craftsArray.forEach(craft => {
                    $("#addEventCraft").append(`<option value='${escapeHtml(craft)}'>${escapeHtml(craft)}</option>`);
                });

                // Set up change handler
                $("#addEventCraft").off('change').on('change', function () {
                    const selectedCraft = $(this).val();
                    if (selectedCraft) {
                        loadModalDataForCraft(selectedCraft, startDate, endDate);
                    }
                });
            } else {
                $("#addEventCraft").append("<option disabled>No aircraft available</option>");
            }
        },
        error: function () {
            $("#addEventCraft").html("<option disabled>Error loading aircraft</option>");
        }
    });
}

/**
 * Loads modal data for a specific craft
 */
function loadModalDataForCraft(craft, startDate, endDate) {
    $(".pilotListSection").empty().html("<div class='select'><i class='fa fa-spinner fa-spin'></i> Loading trainees...</div>");
    $("#treListSection").empty().html("<div class='select'><i class='fa fa-spinner fa-spin'></i> Loading TREs...</div>");
    $("#triListSection").empty().html("<div class='select'><i class='fa fa-spinner fa-spin'></i> Loading TRIs...</div>");

    // Load trainees
    $.ajax({
        type: "GET",
        url: "training_get_pilots.php",
        data: { start: startDate.format("YYYY-MM-DD"), end: endDate.format("YYYY-MM-DD"), craft: craft },
        dataType: "json",
        success: function (pilotsArray) {
            $(".pilotListSection").empty();
            if (Array.isArray(pilotsArray) && pilotsArray.length > 0) {
                pilotsArray.forEach(pilot => {
                    $(".pilotListSection").append(`<div class='select ${pilot.expired || ''}' data-trainer='${pilot.trainer || 'false'}' data-pk='${pilot.id}'>${escapeHtml(pilot.name)}</div>`);
                });

                $(".pilotListSection .select").off('click').on('click', function () {
                    if ($(this).hasClass("selected")) {
                        $(this).removeClass("selected");
                    } else if ($(".pilotListSection .select.selected").length < 4) {
                        $(this).addClass("selected");
                    }
                });
            } else {
                $(".pilotListSection").html("<div class='select text-muted'>No Trainees Available</div>");
            }
        },
        error: function () {
            $(".pilotListSection").html("<div class='select text-danger'>Error loading trainees.</div>");
        }
    });

    // Load TREs and TRIs similarly...
}

/**
 * Loads trainers for the trainer modal
 */
function loadTrainersForModal(startDate, endDate) {
    $("#trainerEventModal #addTrainerList").empty().html("<div class='select'>Loading trainers...</div>");

    $.ajax({
        type: "GET",
        url: "trainers_get_all.php",
        data: { start: startDate, end: endDate },
        dataType: "json",
        success: function (trainersArray) {
            $("#trainerEventModal #addTrainerList").empty();

            if (Array.isArray(trainersArray) && trainersArray.length > 0) {
                trainersArray.forEach(trainer => {
                    $("#trainerEventModal #addTrainerList").append(`<div class='select ${trainer.expired || ''}' data-pk='${trainer.id}' data-pos='${trainer.training_level || '1'}'>${escapeHtml(trainer.name)}</div>`);
                });

                $("#trainerEventModal #addTrainerList .select").off('click').on('click', function () {
                    $("#trainerEventModal #addTrainerList .select").removeClass("selected");
                    $(this).addClass("selected");

                    const selectedPos = parseInt($(this).data("pos"));
                    if (selectedPos === 2) {
                        $("#trainerEventModal #addTrainerPosition option[value='tri']").prop("selected", false).prop("disabled", false);
                        $("#trainerEventModal #addTrainerPosition option[value='tre']").prop("disabled", false).prop("selected", true);
                        $("#trainerEventModal #addTrainerPosition option[value='tri/tre']").prop("disabled", false);
                    } else {
                        $("#trainerEventModal #addTrainerPosition option[value='tri']").prop("selected", true).prop("disabled", false);
                        $("#trainerEventModal #addTrainerPosition option[value='tre']").prop("disabled", true).prop("selected", false);
                        $("#trainerEventModal #addTrainerPosition option[value='tri/tre']").prop("disabled", true).prop("selected", false);
                    }
                });
            } else {
                $("#trainerEventModal #addTrainerList").html("<div class='select'>No Trainers Available</div>");
            }
        },
        error: function () {
            $("#trainerEventModal #addTrainerList").html("<div class='select text-danger'>Error loading trainers.</div>");
        }
    });
}

/**
 * Attaches the event listener for the "Add Selection" button in the #eventModal.
 * This should be called once when the page loads.
 */
function setupAddEventButtonHandler() {
    $('#confirmAdd').on('click', function () {
        const $button = $(this);
        const originalButtonText = $button.text();
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        // --- 1. Gather Data from the Modal ---
        const startDate = moment($('#addStartDate').val(), 'DD-MM-YYYY');
        const endDate = moment($('#addEndDate').val(), 'DD-MM-YYYY');
        const craftType = $('#addEventCraft').val();

        // Helper function to get an array of selected checkbox values
        const getSelectedIds = (selector) => $(selector + ' input:checked').map((i, el) => $(el).val()).get();

        const selectedTriIds = getSelectedIds('#triListSection');
        const selectedTreIds = getSelectedIds('#treListSection');
        const selectedPilotIds = getSelectedIds('#TRIpilotListSection');

        // --- 2. Basic Validation ---
        if (!startDate.isValid() || !endDate.isValid()) {
            showNotification('error', 'Invalid start or end date.');
            $button.prop('disabled', false).text(originalButtonText);
            return;
        }
        if (!craftType) {
            showNotification('error', 'Please select an aircraft.');
            $button.prop('disabled', false).text(originalButtonText);
            return;
        }
        if (selectedPilotIds.length === 0) {
            showNotification('error', 'You must select at least one trainee.');
            $button.prop('disabled', false).text(originalButtonText);
            return;
        }

        // Prepare data for the server, matching `training_add_sim_pilot.php`
        const eventData = {
            start: startDate.format('YYYY-MM-DD'),
            length: endDate.diff(startDate, 'days') + 1,
            craft: craftType,
            // Pad the arrays with nulls to match the PHP script's expectation of 4 pilot IDs
            ids: JSON.stringify([
                selectedPilotIds[0] || null,
                selectedPilotIds[1] || null,
                selectedPilotIds[2] || null,
                selectedPilotIds[3] || null,
            ]),
            tri1: selectedTriIds[0] || null,
            tri2: selectedTriIds[1] || null,
            tre: selectedTreIds[0] || null,
            // Assuming your CSRF token is in a hidden input on the page
            // csrf_token: $('#csrf_token_training').val() 
        };

        // --- 3. Call the AJAX function and handle the response ---
        saveNewTrainingEvent(eventData) // From training-ajax-operations.js
            .then(response => {
                showNotification('success', response.message || 'Training event saved successfully!');
                $('#eventModal').modal('hide');
                // Refresh the calendar to show the new event
                $('#calendar').fullCalendar('refetchEvents');
            })
            .catch(error => {
                showNotification('error', `Save failed: ${error.message}`);
            })
            .finally(() => {
                // This always runs, whether it succeeded or failed
                $button.prop('disabled', false).text(originalButtonText);
            });
    });
}

// UI control functions
function disableAvailSelect() {
    $(".eventAvailSection").addClass("disabled");
    $(".eventAvailSection button").prop("disabled", true).addClass("disabled");
}

function enableAvailSelect() {
    $(".eventAvailSection").removeClass("disabled");
    $(".eventAvailSection button").prop("disabled", false).removeClass("disabled");
}

function disableEventSelect() {
    $(".eventDetailsSection").addClass("disabled");
    $("#addEventCraft, .modal-footer>button").prop("disabled", true).addClass("disabled");
}

function enableEventSelect() {
    $(".eventDetailsSection").removeClass("disabled");
    $("#addEventCraft, .modal-footer>button").prop("disabled", false).removeClass("disabled");
}

function loadingModal() {
    $("#yearLoadingModal").modal("show");
}

// Export functions
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        launchEditModal,
        launchTrainerEditModal,
        launchModal,
        launchTrainerModal,
        disableAvailSelect,
        enableAvailSelect,
        disableEventSelect,
        enableEventSelect,
        loadingModal,
        currentEventForEdit,
        setupAddEventButtonHandler,

    };
}