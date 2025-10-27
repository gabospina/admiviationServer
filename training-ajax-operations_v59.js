// training-ajax-operations.js

// This will now be a global variable to hold our rich data.
let fullEventDataMap = new Map();

/**
 * Fetches events for the main calendar.
 * The viewType ('trainee' or 'trainer') determines the title format.
 */
function getMainCalendarEvents1(start, end, timezone, callback) {
    const currentView = $(".view-as-btn.active").data("value") || 'trainee';

    $.ajax({
        type: "GET",
        url: "training_get_schedule.php",
        data: {
            start: start.format('YYYY-MM-DD'),
            end: end.format('YYYY-MM-DD'),
            viewType: currentView
        },
        dataType: "json",
        success: (data) => callback(processMainCalendarEvents(data)),
        error: () => callback([])
    });
}

// Updated and cleaned up AJAX functions for training-ajax-operations.js
// Replace your duplicate functions with these clean versions

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
                eventId: eventId, // Match what your PHP expects
                newStartDate: newStart.format('YYYY-MM-DD') // Match what your PHP expects
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
function getMainCalendarEvents1(fetchInfo, successCallback, failureCallback) {
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
 * Fetches events for the dedicated "Trainers' Duty Schedule" calendar.
 * It now uses the main schedule source (`training_get_schedule.php`).
 */
function getTrainerCalendarEvents1(start, end, timezone, callback) {
    $.ajax({
        type: "GET",
        url: "training_get_schedule.php",
        data: {
            start: start.format('YYYY-MM-DD'),
            end: end.format('YYYY-MM-DD'),
            viewType: 'trainer' // ALWAYS force the trainer view
        },
        dataType: "json",
        success: (data) => callback(processMainCalendarEvents(data)), // REUSE the main processor
        error: () => callback([])
    });
}

function getTrainerCalendarEvents2(fetchInfo, successCallback, failureCallback) {
    $.ajax({
        type: "GET",
        url: "training_get_schedule.php",
        data: {
            // --- FIX: Use v6's provided date strings ---
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
function processMainCalendarEvents3(eventsFromServer) {
    console.log('Processing events from server:', eventsFromServer);

    if (!Array.isArray(eventsFromServer)) {
        console.warn('Invalid events data - not an array:', eventsFromServer);
        return [];
    }

    // Clear the map every time we get new data to prevent stale data
    fullEventDataMap.clear();

    // Use a Set to track processed event IDs and prevent duplicates
    const processedEventIds = new Set();

    const calendarEvents = eventsFromServer
        .filter(event => {
            // Basic validation
            if (!event || event.id == null || !event.start || !event.end) {
                console.warn("Invalid Event Data Discarded:", event);
                return false;
            }

            // Check for duplicate IDs
            if (processedEventIds.has(event.id)) {
                console.warn("Duplicate event ID detected, skipping:", event.id);
                return false;
            }

            processedEventIds.add(event.id);
            return true;
        })
        .map(serverEvent => {
            try {
                // Store the full, rich server data in our parallel map
                fullEventDataMap.set(serverEvent.id, serverEvent);

                // Create a clean event object for FullCalendar
                const cleanEvent = {
                    id: serverEvent.id,
                    start: moment(serverEvent.start),
                    end: moment(serverEvent.end),
                    allDay: true,
                    backgroundColor: CalendarState.craftAndColors[serverEvent.craft] || '#3a87ad',
                    textColor: "#333",
                    borderColor: "rgb(73, 191, 242)"
                };

                // Set the title based on the current view
                const activeTab = $(".tab.active").data("tab-toggle");
                const craft = serverEvent.craft || "N/A";
                const trainees = serverEvent.pilots || "No Trainees";
                const trainers = serverEvent.trainers || "No Trainers";

                if (activeTab === 'trainer') {
                    cleanEvent.title = `${craft} - <strong>${trainers}</strong>`;
                } else {
                    const viewAsButton = $(".view-as-btn.active").data("value");
                    if (viewAsButton === 'trainee') {
                        cleanEvent.title = `${craft} - <strong>${trainees}</strong> <small>(${trainers})</small>`;
                    } else {
                        cleanEvent.title = `${craft} - <strong>${trainers}</strong> <small>(${trainees})</small>`;
                    }
                }

                console.log(`Processed event ${cleanEvent.id}: ${cleanEvent.title}`);
                return cleanEvent;

            } catch (error) {
                console.error('Error processing event:', serverEvent, error);
                return null;
            }
        })
        .filter(event => event !== null); // Remove any failed processing attempts

    console.log(`Successfully processed ${calendarEvents.length} events`);
    return calendarEvents;
}

function processMainCalendarEvents4(eventsFromServer) {
    if (!Array.isArray(eventsFromServer)) {
        return [];
    }

    return eventsFromServer
        .filter(event => {
            // This validation is still excellent.
            if (!event || event.id == null || !event.start || !event.end) {
                return false;
            }
            return true;
        })
        .map(serverEvent => {
            // --- THIS IS THE ONLY CHANGE ---
            // We no longer need to convert to moment objects or store originalData.
            // We just pass the clean server data through.
            const calendarEvent = {
                id: serverEvent.id,
                start: serverEvent.start, // The string "YYYY-MM-DD" is perfect
                end: serverEvent.end,     // The string "YYYY-MM-DD" is perfect
                allDay: true,
                backgroundColor: CalendarState.craftAndColors[serverEvent.craft] || '#3a87ad',
                textColor: "#333",
                borderColor: "rgb(73, 191, 242)",
                // We will create the title here, but the HTML rendering happens in the eventContent callback
                title: '', // The title will be built below
                extendedProps: serverEvent
            };

            // Your title logic is still perfect.
            const craft = serverEvent.craft || "N/A";
            const trainees = serverEvent.pilots || "No Trainees";
            const trainers = serverEvent.trainers || "No Trainers";
            const currentView = $(".view-as-btn.active").data("value");

            if (currentView === 'trainee') {
                calendarEvent.title = `${craft} - <strong>${trainees}</strong> <small>(${trainers})</small>`;
            } else {
                calendarEvent.title = `${craft} - <strong>${trainers}</strong> <small>(${trainees})</small>`;
            }

            return calendarEvent;
        });
}

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
                extendedProps: serverEvent // Store original data here
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
                resolve(0); // Default to no admin rights on error
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
            url: "training_update.php", // Or "training_add.php" if you have separate scripts
            data: eventData,
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
            data: { id: eventId },
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
                end: end.format("YYYY-MM-DD")
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
            data: eventData,
            dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    resolve(response);
                } else {
                    // Reject the promise with the server's error message if available
                    reject(new Error(response.error || "An unknown error occurred on the server."));
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                // Reject the promise with a network-level error
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
    // Add the event ID to the data payload for the PHP script
    eventData.eventid = eventId;

    return new Promise((resolve, reject) => {
        $.ajax({
            type: "POST",
            url: "training_update_event.php", // Your script for updating
            data: eventData,
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
 * Removes a training event from the server.
 * @param {number} eventId - The ID of the event to remove.
 * @returns {Promise}
 */
function removeTrainingEvent(eventId) {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: "POST",
            url: "training_remove.php",
            data: { id: eventId },
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

// Add this function to your training-ajax-operations.js file

/**
 * Updates an event's start date on the server after a drag-and-drop operation.
 * @param {number} eventId - The ID of the event that was moved.
 * @param {moment} newStart - The new start date of the event.
 * @returns {Promise} A promise that resolves with the server response.
 */
function updateDropDate1(eventId, newStart) {
    return new Promise((resolve, reject) => {
        $.ajax({
            type: "POST",
            url: "training_update_drop_date.php",
            data: {
                eventId: eventId,
                newStartDate: newStart.format('YYYY-MM-DD') // Send date in standard format
            },
            dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    resolve(response);
                } else {
                    reject(new Error(response.message || "Server returned an error."));
                }
            },
            error: function (jqXHR) {
                reject(new Error("A network error occurred while saving the new date."));
            }
        });
    });
}

function updateDropDate1(eventId, newStart) {
    return $.ajax({
        url: 'training_update_drop_date.php',
        method: 'POST',
        dataType: 'json',
        data: {
            id: eventId,
            new_start_date: newStart.format('YYYY-MM-DD')
        }
    });


    // Complete handleEventDrop function for training-event-handlers.js
    // Replace your existing handleEventDrop function with this one

    /**
     * Handles the event when a user drags and drops a calendar event to a new date.
     * @param {object} event - The FullCalendar event object that was moved.
     * @param {moment.Duration} delta - The amount of time the event was moved.
     * @param {function} revertFunc - A function to call to undo the move if the save fails.
     */
    function handleEventDrop(event, delta, revertFunc) {
        // Get the event's original data from our global map
        const originalData = fullEventDataMap.get(event.id);
        const eventTitle = originalData ? `${originalData.craft || 'Event'}` : 'Event';

        // Show confirmation dialog
        const confirmMessage = `Move "${eventTitle}" to ${event.start.format('MMMM Do, YYYY')}?`;
        if (!confirm(confirmMessage)) {
            revertFunc(); // Revert the visual change
            return;
        }

        // Check if the new date is available for training
        const newDateStr = event.start.format('YYYY-MM-DD');
        const isDateAvailable = CalendarState.trainingDates.some(date =>
            date.moment.format('YYYY-MM-DD') === newDateStr
        );

        if (!isDateAvailable) {
            showNotification('error', 'Cannot move event to an unavailable training date.');
            revertFunc();
            return;
        }

        // Show loading state
        showNotification('info', 'Saving new date...');

        // Call the AJAX function to update the server
        updateDropDate(event.id, event.start)
            .then(response => {
                // Success: show confirmation and refresh calendars
                showNotification('success', response.message || 'Event moved successfully!');

                // Refresh both calendars to ensure data consistency
                setTimeout(() => {
                    if ($('#calendar').data('fullCalendar')) {
                        $('#calendar').fullCalendar('refetchEvents');
                    }
                    if ($('#trainer-calendar').data('fullCalendar')) {
                        $('#trainer-calendar').fullCalendar('refetchEvents');
                    }
                }, 500);
            })
            .catch(error => {
                // Error: show error message and revert the visual change
                console.error('Error updating event date:', error);
                showNotification('error', `Could not save new date: ${error.message}`);
                revertFunc(); // This moves the event back to its original position
            });
    }

    // Also add this helper function to check if user can edit schedule
    // Add this if you don't have it already defined elsewhere
    if (typeof canUserEditSchedule === 'undefined') {
        // This should be set from your PHP - replace with your actual logic
        var canUserEditSchedule = false; // Set this based on user permissions
    }
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
        removeTrainingEvent,
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