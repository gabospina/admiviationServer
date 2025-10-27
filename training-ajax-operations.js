// training-ajax-operations.js v83

// This will now be a global variable to hold our rich data.
let fullEventDataMap = new Map();

/**
 * Updates an event's start date on the server after a drag-and-drop operation.
 * @param {number} eventId - The ID of the event that was moved.
 * @param {moment} newStart - The new start date of the event.
 * @returns {Promise} A promise that resolves with the server response.
 */
function updateDropDate(eventId, newStart) {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: "POST",
            url: "training_update_drop_date.php",
            data: {
                eventId: eventId,
                newStartDate: newStart.format('YYYY-MM-DD'),
                form_token: $('#form_token_manager').val() // ✅ ADD CSRF
            },
            dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    resolve(response);
                } else {
                    reject(new Error(response.message || "Server returned an error."));
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                reject(new Error("A network error occurred while saving the new date."));
            }
        });
    });
}

/**
 * Fetches events for the main calendar (FullCalendar v6 signature).
 * @param {object} fetchInfo - An object with start, end, and timeZone properties.
 * @param {function} successCallback - The function to call with the array of events.
 * @param {function} failureCallback - The function to call if the AJAX request fails.
 */

// ================== DD =======================
// ================== BELOW - STILL CHECKING =======================

function getMainCalendarEventsDD(fetchInfo, successCallback, failureCallback) {
    const currentView = $(".view-as-btn.active").data("value") || 'trainee';

    $.ajax({
        type: "GET",
        url: "training_get_schedule.php",
        data: {
            // v6 provides startStr and endStr for convenience
            start: fetchInfo.startStr,
            end: fetchInfo.endStr,
            viewType: currentView
        },
        dataType: "json",
        success: function (eventsFromServer) {
            // Process the raw data
            const processedEvents = processMainCalendarEvents(eventsFromServer);
            // Use the v6 successCallback to return the events
            successCallback(processedEvents);
        },
        error: function (jqXHR) {
            console.error("AJAX Error fetching main calendar events:", jqXHR.responseText);
            // Use the v6 failureCallback
            failureCallback(new Error('Failed to load events.'));
        }
    });
}

// ================== ABOVE =======================
// ================== DD =======================

function getMainCalendarEvents(fetchInfo, successCallback, failureCallback) {
    const currentView = $(".view-as-btn.active").data("value") || 'trainee';
    $.ajax({
        type: "GET",
        url: "training_get_schedule.php",
        data: {
            // --- FIX: Use v6's provided date strings ---
            start: fetchInfo.startStr,
            end: fetchInfo.endStr,
            viewType: currentView
        },
        dataType: "json",
        success: (data) => successCallback(processMainCalendarEvents(data)),
        error: (err) => failureCallback(err)
    });
}

/**
 * Fetches events for the dedicated "Trainers' Duty Schedule" calendar (v6).
 */
function getTrainerCalendarEvents(fetchInfo, successCallback, failureCallback) {
    $.ajax({
        type: "GET",
        url: "training_get_schedule.php",
        data: {
            // --- FIX: Use the v6 startStr and endStr properties ---
            start: fetchInfo.startStr,
            end: fetchInfo.endStr,
            viewType: 'trainer'
        },
        dataType: "json",
        success: (data) => successCallback(processMainCalendarEvents(data)),
        error: (err) => failureCallback(err)
    });
}

/**
 * Safely processes raw event data from the server for ALL calendars.
 * This version prevents duplicates and handles edge cases better.
 */

function processMainCalendarEvents(eventsFromServer) {
    if (!Array.isArray(eventsFromServer)) return [];

    return eventsFromServer
        .filter(event => event && event.id != null && event.start && event.end)
        .map(serverEvent => {
            const calendarEvent = {
                id: serverEvent.id,
                start: moment(serverEvent.start).toDate(), // Use native Date for v6
                end: moment(serverEvent.end).toDate(),
                extendedProps: serverEvent, // This line is crucial
                allDay: true,
                backgroundColor: CalendarState.craftAndColors[serverEvent.craft] || '#3a87ad',
                textColor: "#333",
                borderColor: "rgb(73, 191, 242)",
            };

            // --- FIX: Use the correct method to determine the active tab ---
            const activeTab = $(".tab.active").data("tab-toggle");
            const craft = serverEvent.craft || "N/A";
            const trainees = serverEvent.pilots || "No Trainees Assigned";
            const trainers = serverEvent.trainers || "No Trainers Assigned";

            if (activeTab === 'trainer') {
                // We are on the "Trainers' Duty Schedule" tab. Create a clean, trainer-only title.
                calendarEvent.title = `${craft} - <strong>${trainers}</strong>`;
            } else {
                const viewAsButton = $(".view-as-btn.active").data("value");
                if (viewAsButton === 'trainee') {
                    calendarEvent.title = `${craft} - <strong>${trainees}</strong> <small>(${trainers})</small>`;
                } else {
                    calendarEvent.title = `${craft} - <strong>${trainers}</strong> <small>(${trainees})</small>`;
                }
            }
            return calendarEvent;
        });
}

// Call this function in the console if you need to debug: validateCalendarEvents()

// You can now DELETE processTrainerCalendarEvents and processDedicatedTrainerEvents from this file.

/**
 * Fetches the distinct list of craft types to populate the color map.
 * @returns {Promise} A promise that resolves when crafts are fetched.
 */
function fetchAllCrafts() {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: "GET",
            url: "trainer_get_all_crafts.php",
            data: { distinct: true },
            dataType: "json",
            success: function (result) {
                if (Array.isArray(result)) {
                    const craftAndColors = {};
                    const defaultColors = ["#FFF7A1", "#26FFFF", "#D4D4D4", "#2BA3ED", "#ED752B", "#45D16F"];
                    result.forEach((craftName, i) => {
                        if (typeof craftName === 'string') {
                            craftAndColors[craftName] = defaultColors[i % defaultColors.length];
                        }
                    });
                    CalendarState.craftAndColors = craftAndColors; // Update global state
                    resolve(craftAndColors);
                } else {
                    reject(new Error("Invalid crafts data received"));
                }
            },
            error: (jqXHR, textStatus, error) => reject(error)
        });
    });
}

/**
 * Fetches the dates where training is available.
 * @returns {Promise} A promise that resolves when dates are fetched.
 */
function fetchTrainingDates() {
    // This function is well-written and fits perfectly. Keep as-is.
    return new Promise((resolve, reject) => {
        $.ajax({
            type: "GET",
            url: "training_get_dates.php",
            dataType: "json",
            success: function (result) {
                if (Array.isArray(result)) {
                    const processedDates = result.map(date => ({ id: date.id, moment: moment(date.on) }));
                    CalendarState.trainingDates = processedDates; // Update global state
                    resolve(processedDates);
                } else {
                    reject(new Error("Invalid training dates data received"));
                }
            },
            error: (jqXHR, textStatus, error) => reject(error)
        });
    });
}

/**
 * Checks admin privileges from server
 */
function checkAdminPrivileges() {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: "POST",
            url: "checkAdmin.php",
            data: {
                form_token: $('#form_token_manager').val() // ✅ ADD CSRF
            },
            success: function (data) {
                console.log("checkAdmin.php data:", data);
                const adminLevel = parseInt(data) || 0;
                CalendarState.adminLevel = adminLevel;
                console.log("Admin level set to:", adminLevel);
                resolve(adminLevel);
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error("Error fetching checkAdmin.php:", textStatus, errorThrown, jqXHR.responseText);
                CalendarState.adminLevel = 0;
                resolve(0);
            }
        });
    });
}

/**
 * Saves a new or updated training event.
 * @param {object} eventData - The data for the event to save.
 * @returns {Promise} A promise that resolves with the server response.
 */
function saveTrainingEvent(eventData) {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: "POST",
            url: "training_update.php",
            data: {
                ...eventData,
                form_token: $('#form_token_manager').val() // ✅ ADD CSRF
            },
            dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    resolve(response);
                } else {
                    reject(response || { error: "Unknown error saving event." });
                }
            },
            error: (jqXHR, textStatus, error) => reject(error)
        });
    });
}

/**
 * Removes an entire training event from the schedule.
 * @param {number} eventId - The ID of the event to remove.
 * @returns {Promise} A promise that resolves on success.
 */
function removeTrainingEvent(eventId) {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: "POST",
            url: "training_remove.php",
            data: { 
                id: eventId,
                form_token: $('#form_token_manager').val() // ✅ ADD CSRF
            },
            dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    resolve(response);
                } else {
                    reject(response || { error: "Unknown error removing event." });
                }
            },
            error: (jqXHR, textStatus, error) => reject(error)
        });
    });
}

/**
 * Helper function to format a moment.js object for AJAX.
 * @param {moment} date - The date object.
 * @returns {string} Date in "YYYY-MM-DD" format.
 */
function formatDateForAjax(date) {
    if (date && typeof date.format === 'function') {
        return date.format("YYYY-MM-DD");
    } else if (date instanceof Date) {
        return moment(date).format("YYYY-MM-DD");
    } else {
        console.error("Invalid date object for AJAX formatting:", date);
        return moment().format("YYYY-MM-DD");
    }
}

/**
 * Updates training dates on server
 */
function updateTrainingDatesOnServer(start, end, action) {
    return new Promise((resolve, reject) => {
        const url = action === 'enable' ? 'training_enable_availability.php' : 'training_disable_availability.php';

        $.ajax({
            type: "POST",
            url: url,
            data: {
                start: start.format("YYYY-MM-DD"),
                end: end.format("YYYY-MM-DD"),
                form_token: $('#form_token_manager').val() // ✅ ADD CSRF
            },
            dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    resolve(response);
                } else {
                    reject(response || new Error("Server returned unsuccessful response"));
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error(`Error ${action} availability:`, textStatus, errorThrown, jqXHR.responseText);
                reject(errorThrown);
            }
        });
    });
}

/**
 * Fetches available aircraft for dropdowns.
 * @returns {Promise} A promise that resolves with an array of craft type strings.
 */
function fetchAircraftList() {
    return new Promise((resolve, reject) => {
        // Your trainer_get_all_crafts.php script is perfect for this
        $.ajax({
            url: 'trainer_get_all_crafts.php',
            type: 'GET',
            data: { distinct: true },
            dataType: 'json',
            success: resolve,
            error: (jqXHR, status, error) => reject(error)
        });
    });
}

/**
 * Fetches available pilots (trainees) for a given craft and date range.
 * @param {string} craft - The selected craft type.
 * @param {moment} start - The start date of the event.
 * @param {moment} end - The end date of the event.
 * @returns {Promise} A promise that resolves with an array of pilot objects.
 */
function fetchAvailablePilots(craft, start, end) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'training_get_pilots.php',
            type: 'GET',
            data: {
                craft: craft,
                start: start.format('YYYY-MM-DD'),
                end: end.format('YYYY-MM-DD')
            },
            dataType: 'json',
            success: resolve,
            error: (jqXHR, status, error) => reject(error)
        });
    });
}

/**
 * Fetches available TRIs for a given craft and date range.
 * @param {string} craft - The selected craft type.
 * @param {moment} start - The start date of the event.
 * @param {moment} end - The end date of the event.
 * @returns {Promise} A promise that resolves with an array of TRI objects.
 */
function fetchAvailableTRIs(craft, start, end) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'trainers_get_tri.php',
            type: 'GET',
            data: {
                craft: craft,
                start: start.format('YYYY-MM-DD'),
                end: end.format('YYYY-MM-DD')
            },
            dataType: 'json',
            success: resolve,
            error: (jqXHR, status, error) => reject(error)
        });
    });
}

/**
 * Fetches available TREs for a given craft and date range.
 * @param {string} craft - The selected craft type.
 * @param {moment} start - The start date of the event.
 * @param {moment} end - The end date of the event.
 * @returns {Promise} A promise that resolves with an array of TRE objects.
 */
function fetchAvailableTREs(craft, start, end) {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'trainers_get_tre.php',
            type: 'GET',
            data: {
                craft: craft,
                start: start.format('YYYY-MM-DD'),
                end: end.format('YYYY-MM-DD')
            },
            dataType: 'json',
            success: resolve,
            error: (jqXHR, status, error) => reject(error)
        });
    });
}

/**
 * Saves a new training event to the database.
 * @param {object} eventData - An object containing all the event details.
 * @returns {Promise} A promise that resolves with the server response.
 */
function saveNewTrainingEvent(eventData) {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: "POST",
            url: "training_add_sim_pilot.php",
            data: {
                ...eventData,
                form_token: $('#form_token_manager').val() // ✅ ADD CSRF
            },
            dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    resolve(response);
                } else {
                    reject(new Error(response.error || "An unknown error occurred on the server."));
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                reject(new Error(`Network error: ${textStatus}`));
            }
        });
    });
}

/**
 * Updates an existing training event on the server.
 * @param {number} eventId - The ID of the event to update.
 * @param {object} eventData - The new data for the event.
 * @returns {Promise}
 */
function updateTrainingEvent(eventId, eventData) {
    eventData.eventid = eventId;

    return new Promise((resolve, reject) => {
        $.ajax({
            type: "POST",
            url: "training_update_event.php",
            data: {
                ...eventData,
                form_token: $('#form_token_manager').val() // ✅ ADD CSRF
            },
            dataType: "json",
            success: (response) => {
                if (response && response.success) {
                    resolve(response);
                } else {
                    reject(response || { error: "Unknown server error." });
                }
            },
            error: (jqXHR) => reject({ error: "A network error occurred." })
        });
    });
}

/**
 * Updates an event's start date on the server after a drag-and-drop operation.
 * @param {string} eventId - The ID of the event that was moved.
 * @param {moment} newStart - The new start date of the event.
 * @returns {Promise} A promise that resolves with the server response.
 */
function updateDropDate(eventId, newStart) {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: "POST",
            url: "training_update_drop_date.php",
            data: {
                eventId: eventId,
                newStartDate: newStart.format('YYYY-MM-DD')
            },
            dataType: "json",
            success: (response) => {
                if (response && response.success) {
                    resolve(response);
                } else {
                    reject(new Error(response.message || "Server returned an error."));
                }
            },
            error: () => reject(new Error("A network error occurred."))
        });
    });
}


// Export functions for other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        getMainCalendarEvents,
        getTrainerCalendarEvents,
        fetchAllCrafts,
        fetchTrainingDates,
        checkAdminPrivileges,
        saveTrainingEvent,
        updateTrainingDatesOnServer,
        updateTrainingDates,
        formatDateForAjax,
        fetchAvailableTREs,
        fetchAvailableTRIs,
        fetchAvailablePilots,
        fetchAircraftList,
        saveNewTrainingEvent,
        updateTrainingEvent,
        removeTrainingEvent,
        updateDropDate
    };
}