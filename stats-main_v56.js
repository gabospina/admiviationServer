// ==========================================================================
// === stats-main.js - Globals, Helpers, and Initialization Orchestrator ===
// ==========================================================================

// --- Globals (Used across multiple files) ---
var editableRegistration = [], editableCraftsTypes = [];
var currentGraphData = null;
var currentHoverData = null;
var plotInstance = null;
var allCraftsData = []; // Store grouped data globally
var groupedRegistrations = {};
let commonOptions;

// --- Helper Functions ---

/**
 * Formats a number to include thousands separators and a fixed number of decimal places.
 * @param {number} num The number to format.
 * @param {number} [decimals=1] The number of decimal places to show.
 * @returns {string} The formatted number string.
 */
function formatNumberWithCommas(num, decimals = 1) {
    const number = parseFloat(num);
    if (isNaN(number)) {
        return (0).toFixed(decimals);
    }
    return number.toLocaleString(undefined, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

/**
 * Helper function to format a Date object or Moment object using Moment.js
 * @param {Date|moment} dateObject - The date to format.
 * @param {string} formatString - The Moment.js format string (e.g., 'YYYY-MM-DD').
 * @returns {string} Formatted date string or empty string if input is invalid.
 */
function formatDate(dateObject, formatString) {
    if (!dateObject) return "";
    try {
        if (typeof moment === 'function') {
            const momentDate = moment(dateObject);
            if (momentDate.isValid()) {
                return momentDate.format(formatString);
            }
        }
        const d = new Date(dateObject);
        if (isNaN(d.getTime())) throw new Error("Invalid date");
        const pad = num => String(num).padStart(2, '0');
        if (formatString === 'YYYY-MM-DD') {
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        }
        if (formatString === 'MMM-dd-yyyy') {
            const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            return `${months[d.getMonth()]}-${pad(d.getDate())}-${d.getFullYear()}`;
        }
        if (formatString === 'M-dd-yyyy') {
            return `${d.getMonth() + 1}-${pad(d.getDate())}-${d.getFullYear()}`;
        }
        return `${d.getMonth() + 1}-${pad(d.getDate())}-${d.getFullYear()}`;
    } catch (e) {
        console.error("Error in formatDate:", dateObject, formatString, e);
        return "";
    }
}

// ==========================================================================
// === SINGLE Document Ready Block - The Starting Point ===
// ==========================================================================

// =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=
// === SINGLE Document Ready Block - The Starting Point (FINAL VERSION)      ===
// =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

$(document).ready(function () {
    console.log("Document Ready: Initializing statistics page...");

    // 1. Basic UI Setup
    $(".sidebar-list a[href='statistics.php'] li").addClass("active");
    $("body").addClass("body-bg");

    // 2. Initialize Core Components (Forms, Modals, etc.)
    initializeAddHourForm();
    initializeExperienceForm();
    initializeGraphControls();
    initializeReportModals();

    // 3. Set Default Date for the Logbook and Trigger Initial Data Load
    setTimeout(function () {
        // --- Set default for "Log book beginning from" ---
        const logDatePickerElement = document.querySelector("#log-date");
        if (logDatePickerElement && logDatePickerElement._flatpickr) {
            const logDatePicker = logDatePickerElement._flatpickr;

            // Add the onChange event handler. This will fire every time the user selects a new date.
            logDatePicker.config.onChange.push(function (selectedDates, dateStr, instance) {
                console.log("Logbook date changed, refreshing logbook...");
                getLogbook(true); // 'true' resets pagination to the first page
            });

            // Now, set the initial default date
            const fourDaysAgo = moment().subtract(4, 'days').toDate();
            logDatePicker.setDate(fourDaysAgo, false); // 'false' prevents the onChange from firing on initial load

            // Manually trigger the very first data load
            initializeCraftSelection();
        }

        // --- Set default for "Add Flight Hours" date input ---
        const addDatePickerElement = document.querySelector("#addDate");
        if (addDatePickerElement && addDatePickerElement._flatpickr) {
            addDatePickerElement._flatpickr.setDate(new Date(), true);
        }
    }, 100);

    // 4. Tab change listeners
    $('a[data-toggle="tab"][href="#graphs"]').on('shown.bs.tab', () => plotGraph());
    $('a[data-toggle="tab"][href="#crafts"]').on('shown.bs.tab', () => getExperience());

    console.log("Document Ready: Initializations complete.");
});