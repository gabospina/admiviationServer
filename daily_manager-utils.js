// daily_manager-utils.js v83
/**
 * Debounce function to limit how often a function can be called
 * @param {Function} func - The function to debounce
 * @param {number} wait - The delay in milliseconds
 * @param {boolean} immediate - Whether to call immediately
 * @returns {Function} - The debounced function
 */
function debounce(func, wait, immediate) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            timeout = null;
            if (!immediate) func.apply(this, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(this, args);
    };
}

/**
 * A shared utility function to escape HTML special characters and prevent XSS.
 * @param {string} text The text to escape.
 * @returns {string} The escaped text.
 */
function escapeHtml(text) {
    // Ensure the input is a string
    if (typeof text !== 'string') {
        if (text === null || typeof text === 'undefined') {
            return "";
        }
        text = String(text);
    }
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}

/**
 * Capitalizes the first letter of a string.
 * @param {string} str The string to capitalize.
 * @returns {string}
 */
function capitalize(str) {
    if (typeof str !== 'string' || !str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Debounces a function to limit the rate at which it gets called.
 * @param {function} func The function to debounce.
 * @param {number} wait The delay in milliseconds.
 * @param {boolean} immediate If true, trigger the function on the leading edge, instead of the trailing.
 * @returns {function} The debounced function.
 */
function debounce(func, wait, immediate) {
    var timeout;
    return function () {
        var context = this, args = arguments;
        var later = function () {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        var callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
}

/**
 * Shows a global loading overlay.
 * @param {string} message The message to display.
 */
function showLoadingOverlay(message = "Loading...") {
    console.log(`OVERLAY SHOW: ${message}`);
    // Example: $('#appLoadingOverlay').show().find('.message').text(message);
}

/**
 * Hides the global loading overlay.
 */
function hideLoadingOverlay() {
    console.log("OVERLAY HIDE");
    // Example: $('#appLoadingOverlay').hide();
}

/**
 * Displays a simple success alert.
 * @param {string} message The message to show.
 */
function showSuccessAlert(message) {
    console.log("Success Alert:", message);
    alert("Success!\n" + message);
}

/**
 * Displays a simple error alert.
 * @param {string} message The message to show.
 */
function showErrorAlert(message) {
    console.error("Error Alert:", message);
    alert("Error!\n" + message);
}

/**
 * Displays a simple warning alert.
 * @param {string} message The message to show.
 */
function showWarningAlert(message) {
    console.warn("Warning Alert:", message);
    alert("Warning!\n" + message);
}

/**
 * A robust, shared function to show a Bootstrap alert in a specified container.
 * @param {jQuery} $container The jQuery object of the container to place the alert in.
 * @param {string} type The alert type ('success', 'danger', 'warning', 'info').
 * @param {string} message The message to display.
 * @param {number} autoDismiss Milliseconds to wait before auto-dismissing. 0 for no auto-dismiss.
 */
function showNotification($container, type, message, autoDismiss = 5000) {
    if (!$container || $container.length === 0) {
        console.error("showNotification failed: Invalid container provided.", $container);
        // Fallback to a standard alert
        alert(`${capitalize(type)}: ${message}`);
        return;
    }
    const alertHtml = `<div class="alert alert-${type} alert-dismissible fade in" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
        ${escapeHtml(message)}
    </div>`;
    $container.html(alertHtml).show();

    if (autoDismiss > 0) {
        setTimeout(function () {
            $container.find('.alert').alert('close');
        }, autoDismiss);
    }
}

/**
 * A shared function to update the CSRF token value in a hidden input.
 * @param {string} newToken The new token received from the server.
 */
// CSRF Token Strategy: Per-Session (token changes only on validation failures)
function updateCsrfToken(newToken) {
    console.log('Updating CSRF token to:', newToken);
    
    // Update the main CSRF token
    $('#form_token_manager').val(newToken);
    
    console.log('CSRF token updated successfully');
}
