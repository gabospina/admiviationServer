// training-calendar.js (FINAL VERSION)

// This global object holds the application's state.
window.CalendarState = {
    trainingDates: [],
    currentYear: new Date(),
    craftAndColors: {},
    isYearView: false
};

// These will hold the live instances of our calendars.
let mainCalendarInstance = null;
let trainerCalendarInstance = null;

// ================== DD =======================
// ================== BELOW =======================

function initializeMainCalendarDD() {
    console.log("Initializing FullCalendar v6 on #calendar (Read-Only Mode)");

    if (mainCalendarInstance) {
        mainCalendarInstance.destroy();
    }

    // Perform a "hard reset" to prevent any rendering bugs.
    // destroyCalendarIfExists('#calendar');

    const calendarEl = document.getElementById('calendar');

    mainCalendarInstance = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'year,dayGridMonth,timeGridWeek,timeGridDay'
        },
        customButtons: {
            year: {
                text: 'Year',
                click: function () {
                    toggleYearView('#calendar');
                }
            }
        },
        viewDidMount: function (viewInfo) {
            // Your custom year view logic needs a handler. We'll use a simplified one for now.
            if (!CalendarState.isYearView) {
                exitYearView('#calendar');
            }
        },
        editable: canUserEditSchedule,
        selectable: canUserEditSchedule,

        // Event Handlers
        eventDrop: handleEventDrop,
        select: handleDaySelect,
        eventClick: handleEventClick,

        events: getMainCalendarEvents,
        eventContent: function (arg) { return { html: arg.event.title }; },

        // Other options
        firstDay: 1,
        timeZone: 'local',
        height: 'auto'
    });

    console.log("FullCalendar v6 instance CREATED. Rendering now...");
    mainCalendarInstance.render();
    console.log("FullCalendar v6 RENDERED successfully.");
    createYearView('#calendar'); // This is correct, it builds the hidden container
}

// ================== ABOVE =======================
// ================== DD =======================

/**
 * Initializes the main calendar.
 */
function initializeMainCalendarEXCELLENT() {
    console.log("Initializing FullCalendar v6 on #calendar");
    destroyCalendarIfExists('#calendar');
    const calendarEl = document.getElementById('calendar');

    mainCalendarInstance = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'year,dayGridMonth,timeGridWeek,timeGridDay'
        },
        customButtons: {
            year: {
                text: 'Year',
                click: function (mouseEvent, htmlElement) {
                    // --- THIS IS THE NEW, DIRECT LOGIC ---
                    const $calendar = $('#calendar');
                    const $button = $(htmlElement);

                    // Deactivate all other view buttons
                    $button.siblings().removeClass('fc-button-active');
                    $button.addClass('fc-button-active'); // Use v6 class name

                    // Hide the main calendar view and show our custom year container
                    $calendar.find('.fc-view-harness').hide();
                    $calendar.find('#non-year-directionals, .fc-toolbar-title').hide();
                    $calendar.find('.fc-year-container, #year-directionals').show();

                    // Load the 12 mini-calendars
                    loadYearCalendar('#calendar');
                    CalendarState.isYearView = true;
                }
            }
        },

        // When a standard view renders, we need to exit the year view
        viewDidMount: function (viewInfo) {
            exitYearView('#calendar');
        },

        // --- THIS IS THE CORRECT INITIAL STATE ---
        // editable: false, // Drag-and-drop is OFF by default.
        editable: canUserEditSchedule,
        selectable: canUserEditSchedule, // Managers can select empty days to add events.

        eventDrop: handleEventDrop, // This will be activated when editable becomes true.

        events: getMainCalendarEvents,

        eventContent: function (arg) { return { html: arg.event.title }; },
        select: handleDaySelect,
        eventClick: handleEventClick,

        selectable: true,
        // Other options
        firstDay: 1,
        timeZone: 'local',
        height: 'auto'
    });

    mainCalendarInstance.render();
    console.log("FullCalendar v6 initialized.");
    createYearView('#calendar'); // Build the hidden year view container
}

/**
 * Initializes the trainer calendar using the v6 API.
 */
function initializeTrainerCalendarEXCELLENT() {
    console.log("Initializing FullCalendar v6 on #trainer-calendar");
    destroyCalendarIfExists('#trainer-calendar');
    const calendarEl = document.getElementById('trainer-calendar');

    trainerCalendarInstance = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'year,dayGridMonth,timeGridWeek,timeGridDay'
        },
        customButtons: {
            year: {
                text: 'Year',
                click: function (mouseEvent, htmlElement) {
                    const $calendar = $('#trainer-calendar');
                    const $button = $(htmlElement);
                    $button.siblings().removeClass('fc-button-active');
                    $button.addClass('fc-button-active');
                    $calendar.find('.fc-view-harness, #non-year-directionals, .fc-toolbar-title').hide();
                    $calendar.find('.fc-year-container, #year-directionals').show();
                    loadYearCalendar('#trainer-calendar');
                    CalendarState.isYearView = true;
                }
            }
        },
        viewDidMount: function (viewInfo) {
            exitYearView('#trainer-calendar');
        },

        editable: false,
        eventDrop: handleEventDrop,

        events: getTrainerCalendarEvents, // You will need to update this to the v6 signature too
        eventContent: function (arg) {
            return { html: arg.event.title };
        },
        // viewRender: function (viewInfo) { handleTrainerViewRender(viewInfo, '#trainer-calendar'); },
    });

    trainerCalendarInstance.render();
    createYearView('#trainer-calendar');


}

/**
 * Safely and thoroughly destroys a calendar instance.
 */
function destroyCalendarIfExistsEXCELLENT(selector) {
    const instance = (selector === '#calendar') ? mainCalendarInstance : trainerCalendarInstance;
    if (instance) {
        console.log(`-> destroyCalendarIfExists: Found instance for ${selector}. Destroying...`);
        instance.destroy();
        if (selector === '#calendar') {
            mainCalendarInstance = null;
            console.log("   mainCalendarInstance set to null.");
        } else {
            trainerCalendarInstance = null;
        }
        console.log("   Instance destroyed.");
    } else {
        console.log(`-> destroyCalendarIfExists: No instance found for ${selector}. Nothing to do.`);
    }
    $(selector).empty();
    console.log(`   Container ${selector} emptied.`);
}

// ===================================
// ============== YES BELOW ===========
// ===================================
function initializeMainCalendar() {
    console.log("Initializing FullCalendar v6 on #calendar (Read-Only Mode)");
    destroyCalendarIfExists('#calendar');
    const calendarEl = document.getElementById('calendar');

    mainCalendarInstance = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'year,dayGridMonth,timeGridWeek,timeGridDay'
        },
        customButtons: {
            year: {
                text: 'Year',
                click: function () { toggleYearView('#calendar'); }
            }
        },
        // This callback handles clicks on the standard Month, Week, Day buttons.
        viewDidMount: function (viewInfo) {
            // If a standard view was just rendered, we must ensure we exit the year view.
            exitYearView('#calendar');
        },

        // --- THIS IS THE CORRECT STATE CONTROL ---
        editable: false, // Start with drag-and-drop DISABLED.
        selectable: canUserEditSchedule, // Allow managers to select empty days.

        // Event Handlers
        eventDrop: handleEventDrop,
        select: handleDaySelect,
        eventClick: handleEventClick,

        events: getMainCalendarEvents,
        eventContent: function (arg) { return { html: arg.event.title }; },

        // Other options
        // firstDay: 1,
        // timeZone: 'local',
        //height: 'auto'
    });

    console.log("FullCalendar v6 instance CREATED. Rendering now...");
    mainCalendarInstance.render();
    console.log("FullCalendar v6 RENDERED successfully.");
    createYearView('#calendar'); // This is correct, it builds the hidden container
}

// YEAR then stuck all toher MONTH-W-D
function initializeTrainerCalendar() {
    console.log("Initializing FullCalendar v6 on #trainer-calendar");
    destroyCalendarIfExists('#trainer-calendar');
    const calendarEl = document.getElementById('trainer-calendar');

    trainerCalendarInstance = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'year,dayGridMonth,timeGridWeek,timeGridDay'
        },
        customButtons: {
            year: {
                text: 'Year',
                click: function () { toggleYearView('#trainer-calendar'); }
            }
        },
        viewDidMount: function (viewInfo) {
            exitYearView('#trainer-calendar');
        },
        editable: false,
        selectable: false, // Not used for adding events on this view.
        events: getTrainerCalendarEvents,
        eventContent: function (arg) { return { html: arg.event.title }; },
    });

    trainerCalendarInstance.render();
    createYearView('#trainer-calendar');
}


function destroyCalendarIfExists(selector) {
    const instance = (selector === '#calendar') ? mainCalendarInstance : trainerCalendarInstance;
    if (instance) {
        instance.destroy();
        if (selector === '#calendar') mainCalendarInstance = null;
        else trainerCalendarInstance = null;
    }
    $(selector).empty();
}
// ===================================
// ============== YES ABOVE ===========
// ===================================

/**
 * Refreshes the appropriate calendar based on active tab
 */
function refreshActiveCalendar() {
    if ($(".tab[data-tab-toggle='trainee']").hasClass("active")) {
        if (CalendarState.currentView === "year") {
            loadYearCalendar("#calendar");
        } else {
            initializeMainCalendar();
        }
    } else if ($(".tab[data-tab-toggle='trainer']").hasClass("active")) {
        if (CalendarState.currentView === "year") {
            loadYearCalendar("#trainer-calendar");
        } else {
            initializeTrainerCalendar();
        }
    }
}

/**
 * Updates calendar view after data changes
 */
function updateCalendarView(viewType = null) {
    if (viewType) {
        CalendarState.currentView = viewType;
    }
    refreshActiveCalendar();
}

/**
 * Gets the current calendar state
 */
function getCalendarState() {
    return {
        trainingDates: [...CalendarState.trainingDates],
        currentYear: CalendarState.currentYear.clone(),
        adminLevel: CalendarState.adminLevel,
        currentView: CalendarState.currentView,
        currentDay: CalendarState.currentDay ? CalendarState.currentDay.clone() : null
    };
}

/**
 * Updates calendar state with new training dates
 */
function updateTrainingDatesState(newDates) {
    CalendarState.trainingDates = newDates.map(date => ({
        id: date.id,
        moment: moment(date.on)
    }));
}

/**
 * Updates admin level in calendar state
 */
function updateAdminLevel(level) {
    CalendarState.adminLevel = parseInt(level) || 0;
    console.log("Admin level set to:", CalendarState.adminLevel);
}

/**
 * Checks if user has admin privileges for calendar operations
 */
function hasCalendarAdminPrivileges() {
    return CalendarState.adminLevel > 0 && CalendarState.adminLevel != 2 && CalendarState.adminLevel != 4;
}

// Calendar-specific helper functions
function next7Days(checkMoment) {
    let next7DaysAvail = true;
    const tempMoment = checkMoment.clone();

    for (let i = 0; i < 7; i++) {
        if ($(".fc-day[data-date='" + tempMoment.format("YYYY-MM-DD") + "']").hasClass("alert-null")) {
            next7DaysAvail = false;
            break;
        }
        tempMoment.add(1, "day");
    }
    return next7DaysAvail;
}

// ================

function logCurrentEvents(context) {
    console.log(`=== EVENTS ${context} ===`);

    if ($('#calendar').data('fullCalendar')) {
        const events = $('#calendar').fullCalendar('clientEvents');
        console.log(`Main calendar has ${events.length} events:`);
        events.forEach(event => {
            console.log(`  ID: ${event.id}, Title: "${event.title}", Start: ${event.start.format('YYYY-MM-DD')}`);
        });

        // Check for duplicates
        const ids = events.map(e => e.id);
        const duplicates = ids.filter((id, index) => ids.indexOf(id) !== index);
        if (duplicates.length > 0) {
            console.error('DUPLICATE EVENT IDS FOUND:', duplicates);
        }
    }

    console.log('=== END EVENTS LOG ===');
}

// Export functions for other modules (if using module system)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initializeMainCalendar,
        initializeTrainerCalendar,
        refreshActiveCalendar,
        updateCalendarView,
        getCalendarState,
        updateTrainingDatesState,
        updateAdminLevel,
        hasCalendarAdminPrivileges,
        next7Days
    };
}