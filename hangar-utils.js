// File: hangar_utils.js
// Purpose: Contains globally useful utility functions for the hangar page.

/**
 * Displays a dismissible notification in the '#notification-container' element.
 * @param {string} type - The alert type (e.g., 'success', 'error', 'warning').
 * @param {string} message - The message to display.
 */
function showNotification(type, message) {
    if (typeof Noty === 'function') {
        new Noty({ layout: "top", type: type, text: message, timeout: 5000 }).show();
        return;
    }
    const notification = $('<div>').addClass(`alert alert-${type} alert-dismissible`)
        .text(message)
        .append('<button type="button" class="close" data-dismiss="alert">×</button>');
    $('#notification-container').empty().append(notification).find('.alert').delay(3000).fadeOut();
}

/**
 * Escapes HTML special characters in a string to prevent XSS.
 * @param {string} text - The input string.
 * @returns {string} The escaped string.
 */
function escapeHtml(text) {
    if (typeof text !== 'string') {
        if (text === null || typeof text === 'undefined') { return ""; }
        text = String(text);
    }
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}