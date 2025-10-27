// training-year-view.js (FullCalendar v6 Compliant)

// This array will hold the instances of our 12 mini-calendars
let miniCalendarInstances = [];

function createYearView1(parentSelector) {
    console.log("Creating year view for:", parentSelector);

    // Check if year view already exists to avoid duplicates
    if ($(parentSelector + " .fc-year-container").length > 0) {
        console.log("Year view already exists for", parentSelector);
        return;
    }

    const yearDiv = `
        <div class='row'>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-jan' class='fc-month-container'></div></div>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-feb' class='fc-month-container'></div></div>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-mar' class='fc-month-container'></div></div>
        </div>
        <div class='row'>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-apr' class='fc-month-container'></div></div>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-may' class='fc-month-container'></div></div>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-jun' class='fc-month-container'></div></div>
        </div>
        <div class='row'>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-jul' class='fc-month-container'></div></div>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-aug' class='fc-month-container'></div></div>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-sep' class='fc-month-container'></div></div>
        </div>
        <div class='row'>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-oct' class='fc-month-container'></div></div>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-nov' class='fc-month-container'></div></div>
            <div class='col-md-4'><div id='${parentSelector.substring(1)}-dec' class='fc-month-container'></div></div>
        </div>`;

    $(parentSelector).append("<div class='fc-year-container' style='display: none'>" + yearDiv + "</div>");

    // Add directional buttons if they don't exist
    if ($(parentSelector + " #non-year-directionals").length === 0) {
        $(parentSelector + " .fc-toolbar .fc-left .fc-button-group").attr("id", "non-year-directionals");
    }

    if ($(parentSelector + " #year-directionals").length === 0) {
        $(parentSelector + " .fc-toolbar .fc-left").append(
            '<div class="fc-button-group" id="year-directionals" style="display: none;">' +
            '<button type="button" id="prevYear" class="fc-button fc-state-default fc-corner-left">' +
            '<span class="fc-icon fc-icon-left-single-arrow"></span></button>' +
            '<button type="button" id="nextYear" class="fc-button fc-state-default fc-corner-right">' +
            '<span class="fc-icon fc-icon-right-single-arrow"></span></button></div>'
        );
    }

    // Set up button listeners
    setupYearViewButtonListeners(parentSelector);
}

/**
 * Creates the hidden HTML container for the 12-month year view.
 */
function createYearView(parentSelector) {
    if ($(parentSelector + " .fc-year-container").length > 0) return;

    // Build the grid of containers for the 12 mini-calendars
    let yearDiv = "<div class='row'>";
    for (let i = 0; i < 12; i++) {
        if (i > 0 && i % 3 === 0) {
            yearDiv += "</div><div class='row'>";
        }
        yearDiv += `<div class='col-md-4'><div class='fc-month-container' id='mini-cal-${i}'></div></div>`;
    }
    yearDiv += "</div>";

    $(parentSelector).append("<div class='fc-year-container' style='display: none;'>" + yearDiv + "</div>");

    // Add custom year navigation buttons
    const $toolbarLeft = $(parentSelector + " .fc-toolbar .fc-left");
    $toolbarLeft.find(".fc-button-group").first().attr("id", "non-year-directionals");
    $toolbarLeft.append(
        '<div class="fc-button-group" id="year-directionals" style="display: none;">' +
        '<button type="button" class="fc-button fc-state-default fc-corner-left prevYearBtn"><span class="fc-icon fc-icon-left-single-arrow"></span></button>' +
        '<button type="button" class="fc-button fc-state-default fc-corner-right nextYearBtn"><span class="fc-icon fc-icon-right-single-arrow"></span></button></div>'
    );

    // Attach handlers for the next/prev year buttons
    $toolbarLeft.off('click', '#year-directionals button').on('click', '#year-directionals button', function () {
        // We use moment() here for its powerful date manipulation functions
        let currentYearMoment = moment(CalendarState.currentYear);
        if ($(this).hasClass('nextYearBtn')) {
            currentYearMoment.add(1, "year");
        } else {
            currentYearMoment.subtract(1, "year");
        }
        CalendarState.currentYear = currentYearMoment.toDate(); // Store it back as a native Date object

        // Reload the 12 mini-calendars with the new year
        loadYearCalendar(parentSelector);
    });
}

/**
 * Toggles the calendar between standard and our custom 12-month year view.
 */
function toggleYearView(parentSelector) {
    CalendarState.isYearView = !CalendarState.isYearView;
    const calendarInstance = (parentSelector === '#calendar') ? mainCalendarInstance : trainerCalendarInstance;

    // Load the mini-calendars if we are entering year view for the first time
    if (CalendarState.isYearView && miniCalendarInstances.length === 0) {
        loadYearCalendar(parentSelector);
    }

    // Trigger the master UI handler to show/hide the correct containers
    handleViewRender({ view: calendarInstance.view }, parentSelector);
}

/**
 * Loads or reloads the 12 mini-calendars for the year view using the v6 API.
 */
function loadYearCalendar(parentSelector) {
    showLoadingModal();

    // Destroy any previous mini-calendar instances to prevent memory leaks
    miniCalendarInstances.forEach(cal => cal.destroy());
    miniCalendarInstances = [];

    // Use moment() to easily work with the date
    const currentYearMoment = moment(CalendarState.currentYear);
    console.log(`Loading Year View for ${currentYearMoment.format("YYYY")}`);

    for (let i = 0; i < 12; i++) {
        const monthContainerEl = $(`${parentSelector} #mini-cal-${i}`)[0];
        if (!monthContainerEl) continue;

        const calendarDate = currentYearMoment.clone().month(i).startOf('month');

        const miniCal = new FullCalendar.Calendar(monthContainerEl, {
            initialView: 'dayGridMonth',
            initialDate: calendarDate.format('YYYY-MM-DD'),
            headerToolbar: { left: '', center: 'title', right: '' },
            events: function (fetchInfo, successCallback, failureCallback) {
                handleYearViewEvents(parentSelector, moment(fetchInfo.start), moment(fetchInfo.end), successCallback);
            },
            eventContent: function (arg) { return { html: arg.event.title }; },
            height: "auto",
            firstDay: 1
        });

        miniCal.render();
        miniCalendarInstances.push(miniCal);
    }

    setTimeout(hideLoadingModal, 300);
}

/**
 * Resets the UI to the standard (non-year) view.
 */
function exitYearView(parentSelector) {
    const $calendar = $(parentSelector);
    $calendar.find('.fc-year-button').removeClass('fc-state-active');
    $calendar.find(".fc-year-container, #year-directionals").hide();
    $calendar.find(".fc-view-harness, #non-year-directionals, .fc-toolbar-title").show();
    CalendarState.isYearView = false;
}

// ========================  ========================
// =======================  ==========================

// training-year-view.js - Replace the setupYearViewButtonListeners function

function setupYearViewButtonListeners(parentSelector) {
    console.log("Setting up year view button listeners for:", parentSelector);

    // Remove any existing handlers first
    $(parentSelector + " #fc-year-button").off("click");
    $(parentSelector + " .fc-toolbar .fc-right .fc-button-group").children(":not(#fc-year-button)").off("click");
    $(parentSelector + " #year-directionals #nextYear").off("click");
    $(parentSelector + " #year-directionals #prevYear").off("click");

    // Setup year button handler with a more direct approach
    $(parentSelector + " #fc-year-button").on("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        console.log("Year button clicked for:", parentSelector);

        if ($(this).hasClass("fc-state-active")) {
            return; // Already active
        }

        loadingModal();

        // Remove active state from other buttons
        $(parentSelector + " .fc-toolbar .fc-right .fc-button-group").children().removeClass("fc-state-active");
        $(this).addClass("fc-state-active");

        setTimeout(() => {
            // Hide regular calendar view
            $(parentSelector + " > .fc-view-container").hide();
            $(parentSelector + " > .fc-toolbar > .fc-left > #non-year-directionals").hide();
            $(parentSelector + " .fc-today-button").hide();
            $(parentSelector + " > .fc-toolbar > .fc-center").hide();

            // Show year view
            $(parentSelector + " > .fc-year-container").show();
            $(parentSelector + " > .fc-toolbar > .fc-left > #year-directionals").show();

            // Load or refresh year calendar
            loadYearCalendar(parentSelector);

            $("#yearLoadingModal").modal("hide");
            CalendarState.isYearView = true;
            CalendarState.currentView = "year";
        }, 100);
    });

    // Setup other button handlers (month, week, day)
    $(parentSelector + " .fc-toolbar .fc-right .fc-button-group").children(":not(#fc-year-button)").on("click", function () {
        console.log("Standard view button clicked:", $(this).text());

        const buttonText = $(this).text().toLowerCase();
        const viewType = buttonText === "month" ? "month" :
            buttonText === "week" ? "agendaWeek" :
                buttonText === "day" ? "agendaDay" : "month";

        // Exit year view mode
        $(parentSelector + " #fc-year-button").removeClass("fc-state-active");
        $(this).addClass("fc-state-active");

        // Hide year view
        $(parentSelector + " > .fc-year-container").hide();
        $(parentSelector + " > .fc-toolbar > .fc-left > #year-directionals").hide();

        // Show regular calendar view
        $(parentSelector + " > .fc-view-container").show();
        $(parentSelector + " > .fc-toolbar > .fc-left > #non-year-directionals").show();
        $(parentSelector + " .fc-today-button").show();
        $(parentSelector + " > .fc-toolbar > .fc-center").show();

        CalendarState.isYearView = false;
        CalendarState.currentView = viewType;

        // Change to the correct view
        try {
            $(parentSelector).fullCalendar('changeView', viewType);
        } catch (error) {
            console.error("Error changing view to", viewType, ":", error);
            // Fallback: reinitialize the calendar
            if (parentSelector === "#calendar") {
                initializeMainCalendar();
            } else {
                initializeTrainerCalendar();
            }
        }
    });

    // Next year button
    $(parentSelector + " #year-directionals #nextYear").on("click", function () {
        console.log("Next year clicked");
        CalendarState.currentYear.add(1, "year");
        loadYearCalendar(parentSelector);
    });

    // Previous year button
    $(parentSelector + " #year-directionals #prevYear").on("click", function () {
        console.log("Previous year clicked");
        CalendarState.currentYear.subtract(1, "year");
        loadYearCalendar(parentSelector);
    });

    console.log("Year view button listeners setup complete for:", parentSelector);
}

//Sets up year button click handler for a calendar
function setupYearButtonHandler(parent) {
    console.log("Setting up year button handler for:", parent);

    // Remove any existing click handlers
    $(parent + " #fc-year-button").off("click");

    // Add new click handler
    $(parent + " #fc-year-button").on("click", function () {
        console.log("Year button clicked for:", parent);

        if (!$(this).hasClass("fc-state-active")) {
            loadingModal();

            // Remove active state from other buttons
            $(parent + " .fc-toolbar .fc-right .fc-button-group").children().removeClass("fc-state-active");
            $(this).addClass("fc-state-active");

            setTimeout(function () {
                // Hide regular calendar view
                $(parent + " > .fc-view-container, " +
                    parent + " > .fc-toolbar > .fc-left > #non-year-directionals, " +
                    parent + " .fc-today-button, " +
                    parent + " > .fc-toolbar > .fc-center").hide();

                // Show year view
                $(parent + " > .fc-year-container, " +
                    parent + " > .fc-toolbar > .fc-left > #year-directionals").show();

                // Load the year calendar if not already loaded
                if ($(parent + " .fc-month-container").length > 0 &&
                    !$(parent + " .fc-month-container").first().hasClass('fc')) {
                    loadYearCalendar(parent);
                } else {
                    // Just render if already initialized
                    $(parent + " .fc-year-container").children().each(function (i) {
                        $(this).children().children(".fc-month-container").fullCalendar("render");
                    });
                }

                $("#yearLoadingModal").modal("hide");
                CalendarState.isYearView = true;
                CalendarState.currentView = "year";
            }, 100);
        }
    });

    console.log("Year button handler setup complete for:", parent);
}

// Loads or reloads the 12 mini-calendars for the year view.
function loadYearCalendar1(parentSelector) {
    console.log("Loading year calendar for:", parentSelector, "with year:", CalendarState.currentYear.format("YYYY"));

    showLoadingModal("Loading year view...");

    try {
        const $monthWrappers = $(parentSelector + " .fc-year-container .col-md-4");

        if ($monthWrappers.length !== 12) {
            console.error("Expected 12 month containers, found:", $monthWrappers.length);
            hideLoadingModal();
            return;
        }

        $monthWrappers.each(function (monthIndex) {
            const $monthContainer = $(this).children(".fc-month-container");

            // Safely destroy any previous calendar instance
            if ($monthContainer.hasClass('fc')) {
                try {
                    $monthContainer.fullCalendar("destroy");
                } catch (e) {
                    console.warn("Error destroying month calendar:", e);
                    $monthContainer.empty();
                }
            }

            // Calculate the correct date for this specific month - FIXED
            const calendarDate = moment().year(CalendarState.currentYear.year()).month(monthIndex).startOf('month');

            if (!calendarDate.isValid()) {
                console.error("Invalid calendar date for monthIndex:", monthIndex);
                return;
            }

            // Set proper title for the month
            const monthName = calendarDate.format('MMMM YYYY');

            $monthContainer.fullCalendar({
                header: {
                    left: '',
                    center: 'title',
                    right: ''
                },
                //title: monthName, // Set the title explicitly
                titleFormat: 'MMMM YYYY',
                defaultDate: calendarDate,
                contentHeight: 250,
                height: "auto",
                eventLimit: false,
                firstDay: 1,
                allDaySlot: true,
                events: function (start, end, timezone, callback) {
                    handleYearViewEvents(parentSelector, start, end, callback);
                },
                weekNumbers: true,
                fixedWeekCount: false,
                selectable: canUserEditSchedule,
                select: function (start, end) { handleYearViewSelect(parentSelector, start, end); },
                dayRender: handleYearViewDayRender,
                eventClick: function (event) { handleYearViewEventClick(parentSelector, event); },
                viewRender: function (view, element) {
                    // Force the title to be correct after rendering
                    element.find('.fc-center h2').text(calendarDate.format('MMMM YYYY'));
                },
                ventRender: function (event, element) {
                    // Find the title element and render its content as HTML
                    element.find('.fc-title').html(event.title);
                },
                // ... other options remain the same
            });
        });

        setTimeout(hideLoadingModal, 500);

    } catch (error) {
        console.error("Error in loadYearCalendar:", error);
        hideLoadingModal();
        showNotification("error", "Failed to load year view. Please try again.");
    }
}

/**
 * Fetches and processes events for the mini-calendars in the year view.
 * This is now the unified function for both the main and trainer year views.
 */
function handleYearViewEvents(parent, start, end, callback) {
    const s_date_str = start.format("YYYY-MM-DD");
    const e_date_str = end.format("YYYY-MM-DD");

    console.log("YearView '" + parent + "' fetching for", s_date_str, "to", e_date_str);

    // --- THIS IS THE FIX ---
    // Determine the viewType based on which calendar is being rendered.
    // The dedicated trainer calendar always uses the 'trainer' view.
    const viewType = (parent === "#trainer-calendar")
        ? 'trainer'
        : ($(".view-as-btn.active").data("value") || 'trainee');

    // Both calendars will now use the same robust PHP script.
    $.ajax({
        type: "GET",
        url: "training_get_schedule.php",
        data: {
            start: s_date_str,
            end: e_date_str,
            viewType: viewType,
            _: new Date().getTime() // Prevent caching
        },
        dataType: "json",
        success: function (eventDataFromServer) {
            // Both calendars will use the same robust processing function.
            const events = processMainCalendarEvents(eventDataFromServer);
            callback(events);
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error("YearView AJAX Error for " + parent + ":", textStatus, errorThrown);
            callback([]);
        }
    });
    // --- END OF FIX ---
}

// Handles year view event mouseover
function handleYearViewEventMouseover(parent, event, e) {
    if (parent == "#calendar") {
        if (event.trainers && event.trainers !== "") {
            $("#eventInfoPopover").text(event.trainers).css({ top: e.pageY, left: e.pageX }).show();
        }
    } else {
        if (event.title && event.title !== "") {
            $("#eventInfoPopover").text(event.title).css({ top: e.pageY, left: e.pageX }).show();
        }
    }
}

// Handles year view event clicks
function handleYearViewEventClick(parent, event, jsEvent, view) {
    if (hasCalendarAdminPrivileges()) {
        if (parent == "#calendar") {
            launchEditModal(event);
        } else {
            launchTrainerEditModal(event);
        }
    }
}

/**
 * Handles year view selection
 */
function handleYearViewSelect(parent, start, end, jsEvent, view) {
    if (hasCalendarAdminPrivileges()) {
        // Navigate main calendar to selected date
        if (parent === "#calendar") {
            $("#calendar").fullCalendar('gotoDate', start);
        } else {
            $("#trainer-calendar").fullCalendar('gotoDate', start);
        }

        const tempStart = moment(start);
        const momentEnd = moment(end);

        let isAllDisabled = true;
        let isAllEnabled = true;

        // Check each day in the selected range
        while (tempStart.isBefore(momentEnd, 'day')) {
            const dateStr = tempStart.format("YYYY-MM-DD");
            const isDayDisabled = $(`${parent} .fc-year-container .fc-day[data-date='${dateStr}']`).hasClass("alert-null");

            if (isDayDisabled) {
                isAllEnabled = false;
            } else {
                isAllDisabled = false;
            }

            tempStart.add(1, "day");
        }

        // Launch appropriate modal
        if (parent == "#calendar") {
            launchModal(start, end, isAllDisabled, isAllEnabled);
        } else {
            launchTrainerModal(start, end, isAllDisabled, isAllEnabled);
        }
    } else {
        console.log("Admin privileges required for selection");
    }
}

/**
 * Handles year view day rendering
 */
function handleYearViewDayRender(date, cell) {
    let inRange = false;

    if (CalendarState.trainingDates && CalendarState.trainingDates.length > 0) {
        for (let i = 0; i < CalendarState.trainingDates.length; i++) {
            const trainingDate = CalendarState.trainingDates[i];
            if (trainingDate && trainingDate.moment && typeof trainingDate.moment.isSame === 'function') {
                if (date.isSame(trainingDate.moment, "day")) {
                    inRange = true;
                    break;
                }
            }
        }
    }

    if (!inRange) {
        $(cell).addClass("alert-null");
    } else {
        $(cell).removeClass("alert-null");
    }
}

/**
 * Handles year view render completion
 */
function handleYearViewRender(view, element) {
    // Additional rendering logic if needed
    console.log("Year view rendered:", view.name);
}

/**
 * Resets the UI to the standard (non-year) view.
 * This is called when the user clicks Month, Week, or Day.
 */
function exitYearView1(parent) {
    // Deactivate the custom year button. FullCalendar will handle activating the correct standard button.
    $(parent + ' .fc-year-button').removeClass('fc-state-active');

    // Hide the year view and its controls
    $(parent + " > .fc-year-container").hide();
    $(parent + " .fc-toolbar .fc-left #year-directionals").hide();

    // Show the standard view and its controls
    $(parent + " > .fc-view-container").show();
    $(parent + " .fc-toolbar .fc-left #non-year-directionals").show();
    $(parent + " .fc-toolbar .fc-center").show();

    CalendarState.isYearView = false;
}

/**
 * Checks if year view is currently active
 */
function isYearViewActive(parent) {
    return $(parent + " .fc-year-button").hasClass("fc-state-active");
}

// Gets the current year view state
function getYearViewState() {
    return {
        currentYear: CalendarState.currentYear.clone(),
        isYearView: CalendarState.isYearView,
        trainingDates: [...CalendarState.trainingDates]
    };
}

// Ensures year button is properly created and set up
function ensureYearButtonExists(parentSelector) {
    console.log("Ensuring year button exists for:", parentSelector);

    // Validate the selector
    if (typeof parentSelector !== 'string' || !parentSelector.startsWith('#')) {
        console.error("Invalid parent selector:", parentSelector);
        return false;
    }

    // Check if button already exists
    if ($(parentSelector + " #fc-year-button").length === 0) {
        console.log("Creating year button for:", parentSelector);

        // Create the year button
        const yearButton = $('<button type="button" id="fc-year-button" class="fc-button fc-state-default fc-corner-left">year</button>');

        // Prepend to button group
        $(parentSelector + " .fc-toolbar .fc-right .fc-button-group").prepend(yearButton);

        // Remove corner-left from month button
        $(parentSelector + " .fc-toolbar .fc-month-button").removeClass("fc-corner-left");

        console.log("Year button created for:", parentSelector);
        return true;
    }

    console.log("Year button already exists for:", parentSelector);
    return false;
}

// Debug function to check year button status
function debugYearButtonStatus(parent) {
    console.log("=== YEAR BUTTON DEBUG ===");
    console.log("Parent:", parent);
    console.log("Button exists:", $(parent + " #fc-year-button").length > 0);
    console.log("Button visible:", $(parent + " #fc-year-button").is(":visible"));
    console.log("Has click handlers:", $._data($(parent + " #fc-year-button")[0], "events"));
    console.log("Calendar state - isYearView:", CalendarState.isYearView);
    console.log("Calendar state - currentYear:", CalendarState.currentYear ? CalendarState.currentYear.format("YYYY-MM-DD") : "undefined");
    console.log("=========================");
}

// Call this in your console to debug: debugYearButtonStatus('#calendar')

// Export functions for other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        setupYearButtonHandler,
        createYearView,
        loadYearCalendar,
        exitYearView,
        isYearViewActive,
        getYearViewState,
        handleYearViewEvents,
        handleYearViewEventMouseover,
        handleYearViewEventClick,
        handleYearViewSelect,
        handleYearViewDayRender,
        debugYearButtonStatus,
        handleYearViewRender,
        ensureYearButtonExists
    };
}