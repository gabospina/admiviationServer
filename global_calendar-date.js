// =========================================================================
// === global_calendar-date.js                                           ===
// === MASTER CONFIGURATION FOR ALL DATEPICKERS ON THE SITE              ===
// =========================================================================

$(document).ready(function () {

    // if (typeof flatpickr === 'function') {
    //     console.log("Initializing all .datepicker elements with Flatpickr...");
    //     flatpickr(".datepicker", {
    //         dateFormat: "d-m-Y",
    //     });
    // }

    flatpickr(".datepicker", {
        // This creates a second, visible input for the user. The original input is hidden
        // and holds the value that will be submitted with the form.
        altInput: true,

        // This is the HUMAN-FRIENDLY format the user will see.
        // M = 3-letter month, d = day, Y = 4-digit year. e.g., "Aug 19, 2025"
        altFormat: "M d, Y",

        // This is the DATABASE-FRIENDLY format that will be sent to the server.
        // It's the standard YYYY-MM-DD format that MySQL loves.
        dateFormat: "Y-m-d",
    });
});