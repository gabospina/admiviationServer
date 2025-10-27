// flight-tracker-utilities.js - Complete utility functions
var FlightTrackerUtils = FlightTrackerUtils || {};

FlightTrackerUtils.parseDMS = function (dmsStr) {
    if (!dmsStr) return NaN;
    const parts = dmsStr.trim().split(/[^\d\w\.]+/);
    if (parts.length < 3) return NaN;
    const deg = parseFloat(parts[0]), min = parseFloat(parts[1]), sec = parseFloat(parts[2]), dir = parts[3] || '';
    if (isNaN(deg) || isNaN(min) || isNaN(sec)) return NaN;
    let decimal = deg + (min / 60) + (sec / 3600);
    if (dir.toUpperCase() === 'S' || dir.toUpperCase() === 'W') decimal = -decimal;
    return decimal;
};

FlightTrackerUtils.decimalToDMS = function (dec, isLat) {
    const dir = dec >= 0 ? (isLat ? 'N' : 'E') : (isLat ? 'S' : 'W');
    dec = Math.abs(dec);
    const deg = Math.floor(dec);
    const minFloat = (dec - deg) * 60;
    const min = Math.floor(minFloat);
    const sec = Math.round((minFloat - min) * 60);
    return `${deg}° ${min}' ${sec}" ${dir}`;
};

FlightTrackerUtils.parseCoords = function (coordStr) {
    if (!coordStr) return [NaN, NaN];
    const parts = coordStr.split(',');
    if (parts.length !== 2) return [NaN, NaN];
    const lat = FlightTrackerUtils.parseDMS(parts[0].trim());
    const lon = FlightTrackerUtils.parseDMS(parts[1].trim());
    return [lat, lon];
};

FlightTrackerUtils.escapeHtml = function (text) {
    if (typeof text !== 'string') return text === null || text === undefined ? "" : String(text);
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, m => map[m]);
};

FlightTrackerUtils.calculateDistance = function (lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
};

FlightTrackerUtils.showNotification = function (type, message, duration = 4000) {
    const feedbackDiv = $('#save-location-feedback');
    if (feedbackDiv.length) {
        const alertClass = type === 'success' ? 'alert-success' :
            type === 'error' ? 'alert-danger' :
                type === 'warning' ? 'alert-warning' : 'alert-info';
        feedbackDiv.html(`<div class="alert ${alertClass}">${message}</div>`);
        setTimeout(() => {
            feedbackDiv.fadeOut(500, function () { $(this).empty().show(); });
        }, duration);
    } else {
        alert(message);
    }
};