// training-event-handlers.js
// Contains callback functions for FullCalendar interactions.
console.log("SUCCESS: training-modal-handlers.js has been loaded and is being parsed.");
/**
 * Handles the selection of a date range.
 * This is called by FullCalendar's `select` option.
 * @param {moment} start - The start of the selection.
 * @param {moment} end - The end of the selection.
 */
function handleDaySelect(start, end) {
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
function handleEventRender(event, element) {
    // --- THIS IS THE FIX ---
    // Instead of letting FC render the title, we set the HTML of the title element ourselves.
    // This allows us to use tags like <strong> and <small>.
    element.find('.fc-title').html(event.title);
    // --- END OF FIX ---
    // Add popover for trainee details in trainer view
    if (event.popoverContent) {
        element.popover({
            title: 'Trainees',
            content: event.popoverContent,
            trigger: 'hover',
            placement: 'top',
            container: 'body'
        });
    }

    // Logic for the live search filter
    const searchTerm = $("#search_trainees").val().toLowerCase();
    if (searchTerm) {
        const title = event.title ? event.title.toLowerCase() : '';
        if (title.indexOf(searchTerm) === -1) {
            return false; // Hide the event if it doesn't match the search
        }
    }
    return true; // Show the event
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

/**
 * Called after the view has been rendered.
 * @param {object} view - The FullCalendar view object.
 * @param {jQuery} element - The jQuery element for the view container.
 */
function handleViewRender1(view, element) {
    // We can update our state with the current view name
    CalendarState.currentView = view.name;

    // Logic to attach the custom year view buttons if they don't exist yet
    if (element.find('#fc-year-button').length === 0) {
        const yearButton = $('<button type="button" id="fc-year-button" class="fc-button fc-state-default fc-corner-right">Year</button>');
        element.find('.fc-toolbar .fc-right .fc-button-group').append(yearButton);
        createYearView('#calendar'); // from training-year-view.js
    }
}

/**
 * Called after any view has been rendered on the main calendar.
 * This is the perfect place to manage the UI state when switching between views.
 * @param {object} view - The FullCalendar view object.
 * @param {jQuery} element - The jQuery element for the view container.
 */
function handleViewRender(view, element) {
    // Check if the new view is a standard one (NOT our custom 'year' view state)
    const isStandardView = ['month', 'agendaWeek', 'agendaDay'].includes(view.name);

    if (isStandardView) {
        // If we are switching TO a standard view, ensure the UI is in the correct state.
        // This effectively handles the click on Month, Week, or Day buttons.
        exitYearView('#calendar'); // from training-year-view.js
    }

    CalendarState.currentView = view.name;
}

// --- Handlers specific to the Trainer Calendar ---

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

// Add this entire function to your training-event-handlers.js file.

/**
 * Customizes the rendering of each event on the dedicated "Trainers' Duty Schedule" calendar.
 * Its primary job is to ensure that event titles containing HTML tags are rendered
 * correctly, rather than being displayed as plain text.
 *
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

function handleTrainerViewRender1(view, element) {
    CalendarState.currentView = view.name;
    if (element.find('#fc-year-button').length === 0) {
        const yearButton = $('<button type="button" id="fc-year-button" class="fc-button fc-state-default fc-corner-right">Year</button>');
        element.find('.fc-toolbar .fc-right .fc-button-group').append(yearButton);
        createYearView('#trainer-calendar');
    }
}

/**
 * Called after any view has been rendered on the trainer calendar.
 */
function handleTrainerViewRender(view, element) {
    const isStandardView = ['month', 'agendaWeek', 'agendaDay'].includes(view.name);

    if (isStandardView) {
        // Exit the year view if it was active
        exitYearView('#trainer-calendar');
    }

    CalendarState.currentView = view.name;
}

// ... (all your other handle... functions) ...

// --- Handlers specific to the Trainer Calendar ---

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

function handleTrainerViewRender(view, element) {
    CalendarState.currentView = view.name;
    if (element.find('#fc-year-button').length === 0) {
        const yearButton = $('<button type="button" id="fc-year-button" class="fc-button fc-state-default fc-corner-right">Year</button>');
        element.find('.fc-toolbar .fc-right .fc-button-group').append(yearButton);
        createYearView('#trainer-calendar');
    }
}

// ** THIS IS THE MISSING FUNCTION **
function handleTrainerEventMouseover(event, jsEvent) {
    // For now, it can be empty or reuse the main handler
    handleEventMouseover(event, jsEvent);
}

function handleTrainerViewRender(view, element) {
    CalendarState.currentView = view.name;
    if (element.find('#fc-year-button').length === 0) {
        const yearButton = $('<button type="button" id="fc-year-button" class="fc-button fc-state-default fc-corner-right">Year</button>');
        element.find('.fc-toolbar .fc-right .fc-button-group').append(yearButton);
        createYearView('#trainer-calendar');
    }
}

// training-event-handlers.js

// ... (your other handlers like handleDaySelect, handleEventClick, etc. should be in this file)

/**
 * Handles the event when a user drags and drops a calendar event to a new date.
 * @param {object} event - The FullCalendar event object that was moved.
 * @param {moment.Duration} delta - The amount of time the event was moved.
 * @param {function} revertFunc - A function to call to undo the move if the save fails.
 */
function handleEventDrop(event, delta, revertFunc) {
    // Use a user-friendly confirmation prompt.
    if (!confirm(`Are you sure you want to move this event to ${event.start.format('MMMM Do, YYYY')}?`)) {
        revertFunc(); // This snaps the event back to its original position.
        return;
    }

    // Call our new AJAX function to save the change to the server.
    updateDropDate(event.id, event.start)
        .then(response => {
            // On success, show a success notification.
            showNotification('success', response.message);
            // After a successful drop, it's a good idea to refetch all events
            // to ensure all data (especially availability) is fresh.
            $('#calendar').fullCalendar('refetchEvents');
            $('#trainer-calendar').fullCalendar('refetchEvents');
        })
        .catch(error => {
            // If the save fails, show an error message and revert the change on the calendar.
            showNotification('error', `Could not save the new date: ${error.message}`);
            revertFunc(); // CRITICAL: Move the event back if the save fails.
        });
}