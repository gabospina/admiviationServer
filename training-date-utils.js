// date-utils.js
// Contains generic, reusable date and time manipulation utilities.
// Depends on moment.js.

/**
 * Creates a moment.js object for the first day of a given month and year.
 * @param {number} m - The month index (0-11).
 * @param {number} [yr] - The full year (e.g., 2024). Defaults to the current year.
 * @returns {moment} A moment.js object.
 */
function returnMonthMoment(m, yr) {
    // Use the provided year, or default to the current year if 'yr' is not supplied.
    const year = yr || new Date().getFullYear();
    return moment(`${year}-${doubleDigit(m + 1)}-01`);
}

/**
 * Pads a single-digit number with a leading zero.
 * @param {number} n - The number to pad.
 * @returns {string} The formatted number as a string.
 */
function doubleDigit(n) {
    return n < 10 ? "0" + n : String(n);
}