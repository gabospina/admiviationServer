// training-event-handlers.js
// Contains callback functions for FullCalendar interactions.

/**
 * Handles the selection of a date range.
 * This is called by FullCalendar's `select` option.
 * @param {moment} start - The start of the selection.
 * @param {moment} end - The end of the selection.
 */
/**
 * Handles the selection of a date or date range to open the add modal (v6).
 * @param {object} selectInfo - An object containing start, end, allDay, etc.
 */
function handleDaySelect(selectInfo) {
    if (!canUserEditSchedule) return;

    // Use moment objects for consistency with our existing modal handlers
    const startMoment = moment(selectInfo.start);
    const endMoment = moment(selectInfo.end);

    // launchModal(startMoment, endMoment);
    console.log("Date range selected:", startMoment.format(), endMoment.format());

    // --- FIX: Restore the call to launch the modal ---
    launchModal(startMoment, endMoment);

    // Unselect the range visually after opening the modal
    if (mainCalendarInstance) {
        mainCalendarInstance.unselect();
    }
}

// ================== DD =======================
// ================== BELOW - THIS IS CORRECT =======================
/**
 Handles a click on an existing event to open the edit modal (v6).
 * @param {object} clickInfo - An object containing the event, element, etc.
 */
function handleEventClick(clickInfo) {
    if (!canUserEditSchedule) return;

    // --- THIS IS THE FIX ---
    // Correctly get the full server data from extendedProps and pass it to the modal handler.
    if (clickInfo.event.extendedProps) {
        launchEditModal(clickInfo.event.extendedProps);
    } else {
        console.error("Could not open edit modal: extendedProps not found on event.", clickInfo.event);
        showNotification('error', 'Could not load event details.');
    }
}
// ================== ABOVE =======================
// ================== DD ==========================

/**
 * Customizes the rendering of each day cell.
 * Adds a class to unavailable days.
 * @param {moment} date - The date of the cell being rendered.
 * @param {jQuery} cell - The jQuery element for the day cell.
 */
function handleDayRender(date, cell) {
    let isAvailable = false;
    for (const trainingDate of CalendarState.trainingDates) {
        if (date.isSame(trainingDate.moment, 'day')) {
            isAvailable = true;
            break;
        }
    }

    if (!isAvailable) {
        cell.addClass("alert-null");
    }
}

/**
 * V6 - Renders the content of an event element. This is the new 'eventRender'. (v6 Signature)
 * @param {object} renderInfo - An object containing the event, element, view, etc.
 * @returns {object} An object with an 'html' property.
 */
function handleEventRender(renderInfo) {
    // The title with HTML tags is now in event.title
    return { html: renderInfo.event.title };
}

/**
 * Handles mouseover on an event.
 * @param {object} event - The FullCalendar event object.
 * @param {Event} jsEvent - The native JS event.
 */
function handleEventMouseover(event, jsEvent) {
    // You can add custom hover effects here if needed
}

/**
 * Handles mouseout of an event.
 */
function handleEventMouseout() {
    // You can remove custom hover effects here
}

function handleTrainerDaySelect(start, end) {
    if (!canUserEditSchedule) {
        $('#trainer-calendar').fullCalendar('unselect');
        return;
    }
    launchTrainerModal(start, end, false, true); // Assume trainer availability is always possible
}

function handleTrainerEventClick(event) {
    if (!canUserEditSchedule) {
        return;
    }
    launchTrainerEditModal(event);
}

/**
 * Customizes the rendering of each event on the dedicated "Trainers' Duty Schedule" calendar.
 * Its primary job is to ensure that event titles containing HTML tags are rendered
 * correctly, rather than being displayed as plain text.
 * @param {object} event - The FullCalendar event object being rendered. This object
 *                         is created by our `processMainCalendarEvents` function and
 *                         contains a `title` property with HTML like "<strong>...</strong>".
 * @param {jQuery} element - The jQuery object representing the event's DOM element
 *                           that FullCalendar has just created.
 */
function handleTrainerEventRender(event, element) {
    // 1. Find the specific child element within the event's HTML that holds the title.
    //    In FullCalendar v3, this element has the class 'fc-title'.
    const $titleElement = element.find('.fc-title');

    // 2. Instead of letting FullCalendar set the text (which escapes HTML),
    //    we use jQuery's .html() method. This tells the browser to interpret
    //    the string in `event.title` as HTML, rendering the <strong> tags
    //    as bold text.
    $titleElement.html(event.title);
}

function handleTrainerDaySelect(start, end) {
    if (!canUserEditSchedule) {
        $('#trainer-calendar').fullCalendar('unselect');
        return;
    }
    launchTrainerModal(start, end, false, true);
}

function handleTrainerEventClick(event) {
    if (!canUserEditSchedule) {
        return;
    }
    launchTrainerEditModal(event);
}

// ** THIS IS THE MISSING FUNCTION **
function handleTrainerEventMouseover(event, jsEvent) {
    // For now, it can be empty or reuse the main handler
    handleEventMouseover(event, jsEvent);
}

// ================== DD =======================
// ================== BELOW =======================

/**
 * Handles the event when a user drags and drops a calendar event (v6).
 * @param {object} dropInfo - An object containing event, revert function, etc.
 */

function handleEventDropDDGOOD(dropInfo) {
    const { event, revert } = dropInfo;

    if (!confirm(`Are you sure you want to move this event to ${event.start.toLocaleDateString()}?`)) {
        revert(); // Snaps the event back to its original position
        return;
    }

    // Our existing AJAX function still works perfectly.
    // FullCalendar v6 uses standard Date objects for event.start.
    updateDropDate(event.id, moment(event.start))
        .then(response => {
            showNotification('success', response.message);
        })
        .catch(error => {
            showNotification('error', `Could not save the new date: ${error.message}`);
            revert(); // CRITICAL: Move the event back if the save fails
        });
}

function handleEventDropDD2(dropInfo) {
    const { event, revert } = dropInfo;
    const startMoment = moment(event.start);

    if (!confirm(`Are you sure you want to move this event to ${startMoment.format('MMMM Do, YYYY')}?`)) {
        revert(); // Snaps the event back if the user cancels.
        return;
    }

    updateDropDate(event.id, startMoment)
        .then(response => {
            showNotification('success', response.message);
            // After a drop, refetch all events to ensure data is perfectly fresh.
            // if (mainCalendarInstance) mainCalendarInstance.refetchEvents();
            // if (trainerCalendarInstance) trainerCalendarInstance.refetchEvents();
        })
        .catch(error => {
            showNotification('error', `Could not save the new date: ${error.message}`);
            revert(); // CRITICAL: Move the event back if the save fails.
        });
}

// ================== ABOVE =======================
// ================== DD =======================

function handleEventDrop1(dropInfo) {
    const { event, revert } = dropInfo;
    const startMoment = moment(event.start);

    if (!confirm(`Are you sure you want to move this event to ${startMoment.format('MMMM Do, YYYY')}?`)) {
        revert(); // Snaps the event back if the user cancels.
        return;
    }

    updateDropDate(event.id, startMoment)
        .then(response => {
            showNotification('success', response.message);
            // After a drop, refetch all events to ensure data is perfectly fresh.
            if (mainCalendarInstance) mainCalendarInstance.refetchEvents();
            if (trainerCalendarInstance) trainerCalendarInstance.refetchEvents();
        })
        .catch(error => {
            showNotification('error', `Could not save the new date: ${error.message}`);
            revert(); // CRITICAL: Move the event back if the save fails.
        });
}

// latest function
/**
 * Handles the event when a user drags and drops a calendar event (v6).
 * Implements the "one-shot" editing workflow.
 * @param {object} dropInfo - An object containing event, revert function, etc.
 */
function handleEventDrop(dropInfo) {
    const { event, revert } = dropInfo;
    const startMoment = moment(event.start);

    // Target the correct calendar instance and button
    const activeTab = $(".tab.active").data("tab-toggle");
    const calendarInstance = (activeTab === 'trainer') ? trainerCalendarInstance : mainCalendarInstance;
    const $button = $('#toggle-edit-mode-btn');

    // This function will reset the UI back to read-only mode.
    const resetToReadMode = () => {
        if (calendarInstance) {
            calendarInstance.setOption('editable', false);
        }
        $button.data('mode', 'read')
            .removeClass('btn-success')
            .addClass('btn-warning')
            .html('<i class="fa fa-pencil"></i> Enable Drag & Drop');
    };

    // 1. Confirm the action with the user.
    if (!confirm(`Are you sure you want to move this event to ${startMoment.format('MMMM Do, YYYY')}?`)) {
        revert(); // Snap the event back.
        resetToReadMode(); // Reset to read-only even if they cancel.
        return;
    }

    // 2. If confirmed, call the AJAX function to save the change.
    updateDropDate(event.id, startMoment)
        .then(response => {
            showNotification('success', response.message);
            // After a successful drop, refetch events to ensure data is fresh.
            if (calendarInstance) {
                calendarInstance.refetchEvents();
            }
        })
        .catch(error => {
            showNotification('error', `Could not save the new date: ${error.message}`);
            revert(); // CRITICAL: Move the event back if the save fails.
        })
        .finally(() => {
            // 3. CRITICAL: This .finally() block runs after success OR failure.
            // It guarantees that the calendar is always returned to read-only mode.
            resetToReadMode();
        });
}




