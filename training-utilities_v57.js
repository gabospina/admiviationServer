// Utility functions
function escapeHtml(text) {
    if (typeof text !== 'string') {
        return text === null || typeof text === 'undefined' ? "" : String(text);
    }
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}

function showNotification(type, message, duration = 5000) {
    const alertContainer = $('#global-alert-container');
    if (!alertContainer.length) {
        console.warn("Notification container not found, using fallback");
        // Fallback to browser alert or console
        if (typeof alert === 'function') {
            alert(`${type.toUpperCase()}: ${message}`);
        } else {
            console[type === 'error' ? 'error' : 'log'](`${type}: ${message}`);
        }
        return;
    }
    let alertClass = 'alert-info';
    if (type === 'success') alertClass = 'alert-success';
    else if (type === 'error' || type === 'danger') alertClass = 'alert-danger';
    else if (type === 'warning') alertClass = 'alert-warning';

    const alertId = 'alert-' + new Date().getTime();
    const alertHtml = `
        <div id="${alertId}" class="alert ${alertClass} alert-dismissible fade show" role="alert" style="margin-bottom: 10px;">
            ${escapeHtml(message)}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">×</span>
            </button>
        </div>`;

    alertContainer.append(alertHtml);

    if (duration > 0) {
        setTimeout(function () {
            $('#' + alertId).alert('close');
        }, duration);
    }
}

function returnMonthMoment(m, yr) {
    if (yr == undefined) {
        year = new Date().getFullYear();
    } else {
        year = yr;
    }
    return moment(year + "-" + doubleDigit((m + 1)) + "-01");
}

function doubleDigit(n) {
    if (n < 10) {
        return "0" + n;
    }
    return n;
}