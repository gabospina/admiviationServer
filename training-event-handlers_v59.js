// training-event-handlers.js
// Contains callback functions for FullCalendar interactions.
console.log("SUCCESS: training-modal-handlers.js has been loaded and is being parsed.");
/**
 * Handles the selection of a date range.
 * This is called by FullCalendar's `select` option.
 * @param {moment} start - The start of the selection.
 * @param {moment} end - The end of the selection.
 */
function handleDaySelect1(start, end) {
    // Only allow adding events if the user has edit permissions.
    if (!canUserEditSchedule) {
        $('#calendar').fullCalendar('unselect'); // Deselect the range visually
        return;
    }

    let isAllDisabled = true, isAllEnabled = true;
    const tempStart = moment(start); // Use a clone

    while (tempStart.isBefore(end, 'day')) {
        const dayCell = $(`.fc-day[data-date='${tempStart.format("YYYY-MM-DD")}']`);
        if (dayCell.hasClass("alert-null")) {
            isAllEnabled = false; // At least one day in the range is unavailable
        } else {
            isAllDisabled = false; // At least one day is available
        }
        tempStart.add(1, "day");
    }

    // Delegate to the modal handler to open the "Add Event" modal
    launchModal(start, end, isAllDisabled, isAllEnabled); // From training-modal-handlers.js
}

/**
 * Handles the selection of a date or date range to open the add modal (v6).
 */
function handleDaySelect(selectInfo) {
    if (!canUserEditSchedule) return;

    // Use moment objects for consistency with our existing modal handlers
    const startMoment = moment(selectInfo.start);
    const endMoment = moment(selectInfo.end);

    // --- FIX: Restore the call to launch the modal ---
    launchModal(startMoment, endMoment);

    // Unselect the range visually after opening the modal
    if (mainCalendarInstance) {
        mainCalendarInstance.unselect();
    }
}

/**
 * Handles a click on an existing event.
 * @param {object} event - The FullCalendar event object.
 */
function handleEventClick(event) {
    if (!canUserEditSchedule) {
        return;
    }
    // Delegate to the modal handler to open the "Edit Event" modal
    launchEditModal(event); // From training-modal-handlers.js
}

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
 * Customizes the rendering of each event element.
 * Used here to add popovers.
 * @param {object} event - The FullCalendar event object.
 * @param {jQuery} element - The jQuery element for the event.
 */
function handleEventRenderV3(event, element) {
    console.log(`Rendering event: ${event.id}, Title: ${event.title}, Start: ${event.start.format()}`);
    element.find('.fc-title').html(event.title);
    // Add a unique class to each event for debugging
    element.addClass(`event-id-${event.id}`);
    return true; // Ensure the event is always rendered
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

/**
 * Handles the event when a user drags and drops a calendar event.
 * @param {object} dropInfo - An object containing event, oldEvent, delta, etc.
 */
function handleEventDrop(dropInfo) {
    const { event, revert } = dropInfo;

    // Use a moment object for formatting, as our AJAX function expects it
    const startMoment = moment(event.start);

    if (!confirm(`Are you sure you want to move this event to ${startMoment.format('MMMM Do')}?`)) {
        revert();
        return;
    }

    // Call the AJAX function to save the change.
    updateDropDate(event.id, startMoment)
        .then(response => {
            showNotification('success', response.message);
            // After a successful drop, refetch events to ensure data is fresh.
            if (mainCalendarInstance) mainCalendarInstance.refetchEvents();
            if (trainerCalendarInstance) trainerCalendarInstance.refetchEvents();
        })
        .catch(error => {
            showNotification('error', `Could not save the new date: ${error.message}`);
            revert(); // CRITICAL: Move the event back if the save fails.
        });
}

/**
 * Handles a click on an existing event to open the edit modal (v6).
 */
function handleEventClick(clickInfo) {
    if (!canUserEditSchedule) return;

    // --- FIX: Restore the call to launch the edit modal ---
    // The full server data is now in the extendedProps property.
    launchEditModal(clickInfo.event.extendedProps);
}

/**
 * Handles the selection of a date or date range to open the add modal.
 * @param {object} selectInfo - An object containing start, end, allDay, etc.
 */
function handleDaySelect(selectInfo) {
    if (!canUserEditSchedule) return;

    // Use moment objects for consistency with our modal handlers
    const startMoment = moment(selectInfo.start);
    const endMoment = moment(selectInfo.end);

    // launchModal(startMoment, endMoment);
    console.log("Date range selected:", startMoment.format(), endMoment.format());

    launchModal(startMoment, endMoment); // Ensure this line is active
    if (mainCalendarInstance) {
        mainCalendarInstance.unselect();
    }
}

