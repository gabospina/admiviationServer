// flight-tracker-functions.js (FINAL, COMPLETE, AND INTEGRATED VERSION)

// --- GLOBAL UTILITY FUNCTIONS ---
// These helper functions are pure and can be defined globally.
function parseDMS(dmsStr) {
    if (!dmsStr) return NaN;
    const parts = dmsStr.trim().split(/[^\d\w\.]+/);
    if (parts.length < 3) return NaN;
    const deg = parseFloat(parts[0]), min = parseFloat(parts[1]), sec = parseFloat(parts[2]), dir = parts[3] || '';
    if (isNaN(deg) || isNaN(min) || isNaN(sec)) return NaN;
    let decimal = deg + (min / 60) + (sec / 3600);
    if (dir.toUpperCase() === 'S' || dir.toUpperCase() === 'W') decimal = -decimal;
    return decimal;
}
function decimalToDMS(dec, isLat) {
    const dir = dec >= 0 ? (isLat ? 'N' : 'E') : (isLat ? 'S' : 'W');
    dec = Math.abs(dec);
    const deg = Math.floor(dec);
    const minFloat = (dec - deg) * 60;
    const min = Math.floor(minFloat);
    const sec = Math.round((minFloat - min) * 60);
    return `${deg}° ${min}' ${sec}" ${dir}`;
}
function parseCoords(coordStr) {
    if (!coordStr) return [NaN, NaN];
    const parts = coordStr.split(',');
    if (parts.length !== 2) return [NaN, NaN];

    const lat = parseDMS(parts[0].trim());
    const lon = parseDMS(parts[1].trim());

    return [lat, lon];
}
function escapeHtml(text) {
    if (typeof text !== 'string') return text === null || text === undefined ? "" : String(text);
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.replace(/[&<>"']/g, m => map[m]);
}
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}
// --- END UTILITIES ---


// This is the main entry point. All operational code goes inside here.
$(document).ready(function () {
    console.log("Flight Tracker Initializing...");

    // --- 1. CONFIGURATION & SETUP ---
    // --- NEW: Centralize the API key for weather layers ---
    const OWM_API_KEY = 'b756cb0ed0290bceb8b22a58ed5beeda'; // Your OpenWeatherMap API Key

    let locationTypes = [];

    // --- 2. SETUP & SCOPED VARIABLES ---
    let savedLocations = [];
    let activeFlights = []; // Array for all currently animating flights
    let isCalculating = false; // Flag for the ad-hoc planner calculator
    const aircraftMarkers = {};
    const aircraftPaths = {};
    const flightColors = ['#007bff', '#dc3545', '#28a745', '#6f42c1', '#fd7e14'];
    const locationMarkers = []; // --- NEW: Array to hold our location markers ---

    $('#dispatch-date').text(`- ${moment().format('dddd, MMMM D, YYYY')}`);

    // --- 2. MAP INITIALIZATION & WEATHER LAYERS ---
    // (This section now contains all map setup logic)

    // Fix for default Leaflet icon paths
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });

    // --- Define Base and Weather Layers ---
    const streetMap = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    const owmPrecipitation = L.tileLayer(`https://tile.openweathermap.org/map/precipitation_new/{z}/{x}/{y}.png?appid=${OWM_API_KEY}`, {
        attribution: '&copy; <a href="https://openweathermap.org/">OpenWeatherMap</a>',
        opacity: 0.7
    });

    const owmClouds = L.tileLayer(`https://tile.openweathermap.org/map/clouds_new/{z}/{x}/{y}.png?appid=${OWM_API_KEY}`, {
        attribution: '&copy; <a href="https://openweathermap.org/">OpenWeatherMap</a>',
        opacity: 0.6
    });

    // --- Initialize the Map ---
    const map = L.map('map', {
        center: [54.0, -89.0], // Centered on Canada
        zoom: 4,
        layers: [streetMap, owmPrecipitation] // Add Street Map and Precipitation by default
    });
    setTimeout(() => map.invalidateSize(), 150);

    // --- Setup Layer Control ---
    const baseLayers = {
        "Street Map": streetMap
    };
    const overlayMaps = {
        "Precipitation": owmPrecipitation,
        "Clouds": owmClouds
    };
    L.control.layers(baseLayers, overlayMaps, {
        collapsed: false // Keep the layer control box open
    }).addTo(map);

    const aircraftIcon = L.icon({
        // iconUrl: '/admviation_2/images/heli-icon.png',
        iconUrl: '/admviation_2/images/loading.gif',
        iconSize: [40, 40],
    });

    // Initialize date/time pickers for the ad-hoc planner
    const atdPlannerPicker = flatpickr("#atd-planner", { enableTime: true, noCalendar: true, dateFormat: "H:i" });
    const etaPlannerPicker = flatpickr("#eta-planner", { enableTime: true, noCalendar: true, dateFormat: "H:i" });

    // --- 4. DATA LOADING & UI BUILDING (For Dispatch Panel) ---
    function loadInitialData() {
        Promise.all([
            $.getJSON('tracker_saved_locations.php'),
            $.getJSON('tracker_today_flights.php'),
            $.getJSON('tracker_get_location_types.php')
        ]).then(([locations, scheduledFlights, types]) => {
            savedLocations = locations;
            locationTypes = types;

            displayLocationMarkers(locations); // Draw the location icons on the map
            populateLocationDropdowns(); // Populate the ad-hoc planner dropdowns
            buildFlightList(scheduledFlights); // Build the dispatch list
            populateLocationTypeDropdown(); // <-- Populate the new dropdown
            populateManageTypesList(); // <-- Populate the modal list
        }).catch(error => {
            console.error("Failed to load initial data:", error);
            $('#todays-flights-list').html('<p class="text-danger p-3">Could not load initial data.</p>');
        });
    }

    /**
     * Displays all saved locations on the map as blue circular markers.
     * @param {Array} locations - The array of location objects from the server.
     */
    function displayLocationMarkers(locations) {
        locationMarkers.forEach(marker => map.removeLayer(marker));
        locationMarkers.length = 0;

        locations.forEach(loc => {
            const type = locationTypes.find(t => t.id === loc.location_type_id);
            const color = type ? type.color_hex : '#30e351ff'; // Default to grey if no type

            const circleMarker = L.circleMarker([loc.latitude, loc.longitude], {
                radius: 8, color: 'rgba(220, 35, 35, 1)', weight: 1, fillColor: color, fillOpacity: 0.9
            });

            const latDMS = decimalToDMS(loc.latitude, true);
            const lonDMS = decimalToDMS(loc.longitude, false);
            const typeName = type ? escapeHtml(type.type_name) : 'Uncategorized';

            circleMarker.bindPopup(`<b>${escapeHtml(loc.location_name)}</b><br><small>${typeName}</small><br><small>${latDMS}, ${lonDMS}</small>`);
            circleMarker.addTo(map);
            locationMarkers.push(circleMarker);
        });
    }

    // --- NEW FUNCTIONS FOR LOCATION TYPES ---
    function populateLocationTypeDropdown() {
        const $select = $('#new-location-type');
        $select.empty().append('<option value="">Select a Type</option>');
        locationTypes.forEach(type => {
            $select.append(`<option value="${type.id}">${escapeHtml(type.type_name)}</option>`);
        });
    }

    function populateManageTypesList() {
        const $list = $('#manage-types-list');
        $list.empty();
        if (locationTypes.length === 0) {
            $list.append('<li>No types created yet.</li>');
            return;
        }
        locationTypes.forEach(type => {
            const itemHtml = `
                <li data-type-id="${type.id}">
                    <span>
                        <span class="color-box" style="background-color: ${type.color_hex};"></span>
                        ${escapeHtml(type.type_name)}
                    </span>
                    <button class="btn btn-xs btn-danger delete-type-btn">&times;</button>
                </li>`;
            $list.append(itemHtml);
        });
    }

    // --- END of new function ---

    function populateLocationDropdowns() {
        const $depSelect = $('#departure-select');
        const $destSelect = $('#destination-select');
        $depSelect.empty().append('<option value="">Select Departure</option>');
        $destSelect.empty().append('<option value="">Select Destination</option>');
        savedLocations.forEach(loc => {
            const option = `<option value="${loc.id}" data-lat="${loc.latitude}" data-lon="${loc.longitude}">${escapeHtml(loc.location_name)}</option>`;
            $depSelect.append(option);
            $destSelect.append(option);
        });
    }
    // loadInitialData();

    function buildFlightList(scheduledFlights) {
        const listContainer = $('#todays-flights-list');
        listContainer.empty();
        if (!scheduledFlights || scheduledFlights.length === 0) {
            listContainer.html('<p class="text-muted p-3">No flights scheduled for today.</p>');
            return;
        }

        let flightDataStore = {};
        scheduledFlights.forEach((flight, index) => {
            flightDataStore[flight.flight_id] = flight;
            const color = flightColors[index % flightColors.length];
            let locationOptions = '<option value="">Select Location</option>';
            savedLocations.forEach(loc => {
                locationOptions += `<option value="${loc.id}" data-lat="${loc.latitude}" data-lon="${loc.longitude}">${escapeHtml(loc.location_name)}</option>`;
            });

            const flightHtml = `
                <div class="flight-item" data-flight-id="${flight.flight_id}" style="border-left: 5px solid ${color};">
                    <h5>${escapeHtml(flight.craft)} <small>(${escapeHtml(flight.pilot_name)})</small></h5>
                    <div class="controls-row">
                        <div class="form-group" style="width: 50%;"><label>Departure</label><select class="form-control form-control-sm departure-select">${locationOptions}</select></div>
                        <div class="form-group" style="width: 50%;"><label>Destination</label><select class="form-control form-control-sm destination-select">${locationOptions}</select></div>
                    </div>
                    <div class="controls-row mt-2">
                        <div class="form-group" style="width: 35%;"><label>ATD</label><input type="text" class="form-control form-control-sm atd-input" placeholder="HH:MM"></div>
                        <div class="form-group" style="width: 35%;"><label>ETA</label><input type="text" class="form-control form-control-sm eta-input" placeholder="HH:MM"></div>
                        <div class="form-group" style="flex-grow: 1; text-align: right;"><button class="btn btn-sm btn-success depart-btn">Start Tracking</button></div>
                    </div>
                </div>`;
            listContainer.append(flightHtml);
        });

        flatpickr(".atd-input, .eta-input", { enableTime: true, noCalendar: true, dateFormat: "H:i", time_24hr: true });

        // This is now the ONLY handler for the depart buttons
        listContainer.off('click', '.depart-btn').on('click', '.depart-btn', function () {
            const $item = $(this).closest('.flight-item');
            const flightId = $item.data('flight-id');
            const depOption = $item.find('.departure-select option:selected');
            const destOption = $item.find('.destination-select option:selected');
            const atdInput = $item.find('.atd-input')[0]._flatpickr.selectedDates[0];
            const etaInput = $item.find('.eta-input')[0]._flatpickr.selectedDates[0];

            if (!depOption.val() || !destOption.val() || !atdInput || !etaInput) {
                alert('Please select Departure, Destination, ATD, and ETA.');
                return;
            }

            const today = new Date();
            const atd = new Date(today.getFullYear(), today.getMonth(), today.getDate(), atdInput.getHours(), atdInput.getMinutes());
            let eta = new Date(today.getFullYear(), today.getMonth(), today.getDate(), etaInput.getHours(), etaInput.getMinutes());
            if (eta <= atd) eta.setDate(eta.getDate() + 1);

            activeFlights = activeFlights.filter(f => f.flight_id !== flightId);

            const originalFlightData = flightDataStore[flightId];
            activeFlights.push({
                ...originalFlightData,
                departure_loc: [parseFloat(depOption.data('lat')), parseFloat(depOption.data('lon'))],
                destination_loc: [parseFloat(destOption.data('lat')), parseFloat(destOption.data('lon'))],
                departure_time: atd.toISOString(),
                arrival_time: eta.toISOString()
            });

            $(this).prop('disabled', true).text('Tracking...');
            updateFlightPositions();
        });
    }

    // --- 4. CALCULATION LOGIC & EVENT LISTENERS ---
    /**
     * Calculates ETA based on departure/destination, ETD, and Speed.
     */
    function calculateEtaFromSpeed() {
        if (isCalculating) return;
        isCalculating = true;

        const depCoords = parseCoords($('#departure-coords').val());
        const destCoords = parseCoords($('#destination-coords').val());
        const speedKnots = parseFloat($('#speed-planner').val());
        const etdTime = atdPlannerPicker.selectedDates[0];

        // Check if we have all valid data
        if (depCoords && !isNaN(depCoords[0]) && !isNaN(depCoords[1]) &&
            destCoords && !isNaN(destCoords[0]) && !isNaN(destCoords[1]) &&
            speedKnots > 0 && etdTime) {

            const today = new Date();
            const etd = new Date(today.getFullYear(), today.getMonth(), today.getDate(), etdTime.getHours(), etdTime.getMinutes());

            const distanceKm = calculateDistance(depCoords[0], depCoords[1], destCoords[0], destCoords[1]);
            const speedKph = speedKnots * 1.852;

            const durationHours = distanceKm / speedKph;
            const newEta = new Date(etd.getTime() + (durationHours * 3600 * 1000));

            etaPlannerPicker.setDate(newEta, false);
        }
        isCalculating = false;
    }

    /**
     * Calculates Speed based on departure/destination, ETD, and ETA.
     */
    function calculateSpeedFromEta() {
        if (isCalculating) return;
        isCalculating = true;

        const depCoords = parseCoords($('#departure-coords').val());
        const destCoords = parseCoords($('#destination-coords').val());
        const etdTime = atdPlannerPicker.selectedDates[0];
        const etaTime = etaPlannerPicker.selectedDates[0];

        if (depCoords && !isNaN(depCoords[0]) && destCoords && !isNaN(destCoords[0]) && etdTime && etaTime) {
            const today = new Date();
            let etd = new Date(today.getFullYear(), today.getMonth(), today.getDate(), etdTime.getHours(), etdTime.getMinutes());
            let eta = new Date(today.getFullYear(), today.getMonth(), today.getDate(), etaTime.getHours(), etaTime.getMinutes());

            // Handle overnight flights
            if (eta <= etd) {
                eta.setDate(eta.getDate() + 1);
            }

            const durationHours = (eta.getTime() - etd.getTime()) / (3600 * 1000);
            if (durationHours > 0) {
                const distanceKm = calculateDistance(depCoords[0], depCoords[1], destCoords[0], destCoords[1]);
                const speedKph = distanceKm / durationHours;
                $('#speed-planner').val(Math.round(speedKph * 0.539957)); // Update the speed input
            }
        }
        isCalculating = false;
    }

    // --- 6. EVENT LISTENERS (For Ad-Hoc Planner) ---
    $('#departure-select, #destination-select').on('change', function () {
        const depOpt = $('#departure-select option:selected');
        const destOpt = $('#destination-select option:selected');

        if (depOpt.val() && depOpt.data('lat') && depOpt.data('lon')) {
            $('#departure-coords').val(`${decimalToDMS(parseFloat(depOpt.data('lat')), true)}, ${decimalToDMS(parseFloat(depOpt.data('lon')), false)}`);
        } else {
            $('#departure-coords').val('');
        }

        if (destOpt.val() && destOpt.data('lat') && destOpt.data('lon')) {
            $('#destination-coords').val(`${decimalToDMS(parseFloat(destOpt.data('lat')), true)}, ${decimalToDMS(parseFloat(destOpt.data('lon')), false)}`);
        } else {
            $('#destination-coords').val('');
        }

        calculateEtaFromSpeed();
    });

    $('#speed').on('blur', calculateEtaFromSpeed);

    $('#eta-planner, #atd-planner, #departure-coords, #destination-coords').on('change', calculateSpeedFromEta);

    // Ad-Hoc "Animate Flight" Button - FIXED
    $('#start-flight').on('click', function () {
        const atdTime = atdPlannerPicker.selectedDates[0];
        const etaTime = etaPlannerPicker.selectedDates[0];
        const depCoords = parseCoords($('#departure-coords').val());
        const destCoords = parseCoords($('#destination-coords').val());

        // Proper validation
        if (!atdTime || !etaTime ||
            !depCoords || isNaN(depCoords[0]) || isNaN(depCoords[1]) ||
            !destCoords || isNaN(destCoords[0]) || isNaN(destCoords[1])) {
            alert("Please set valid Departure, Destination, ATD, and ETA using the planner controls.");
            return;
        }

        const today = new Date();
        const atd = new Date(today.getFullYear(), today.getMonth(), today.getDate(), atdTime.getHours(), atdTime.getMinutes());
        let eta = new Date(today.getFullYear(), today.getMonth(), today.getDate(), etaTime.getHours(), etaTime.getMinutes());

        if (eta <= atd) {
            eta.setDate(eta.getDate() + 1);
        }

        activeFlights.push({
            flight_id: `adhoc-${Date.now()}`,
            craft: "Ad-Hoc Flight",
            pilot_name: "Manual Entry",
            departure_loc: depCoords,
            destination_loc: destCoords,
            departure_time: atd.toISOString(),
            arrival_time: eta.toISOString()
        });

        updateFlightPositions();
    });

    // Add New Location Type Form
    $('#add-location-type-form').on('submit', function (e) {
        e.preventDefault();
        const typeData = {
            name: $('#new-type-name').val(),
            color: $('#new-type-color').val()
        };

        // Simple validation
        if (!typeData.name) {
            // You can replace this alert with a more elegant notification if you have one
            alert('Please enter a type name.');
            return;
        }

        $.ajax({
            url: 'tracker_add_location_type.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(typeData),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // First, update the data in the background
                    locationTypes.push(response.new_type);
                    populateLocationTypeDropdown();
                    populateManageTypesList();
                    $('#add-location-type-form')[0].reset();

                    // --- NEW BEHAVIOR ---
                    // 1. Close the modal
                    $('#addLocationTypeModal').modal('hide');

                    // 2. Show a success notification
                    // Assuming you have a 'showNotification' function like in your other form.
                    // If not, you can replace this with a simple alert('Success!');
                    showNotification('success', response.message || 'New location type created!');

                } else {
                    // On failure, show the error but keep the modal open for correction
                    showNotification('error', 'Error: ' + response.message);
                }
            },
            error: function () {
                // Handle network or server errors
                showNotification('error', 'A server error occurred. Please try again.');
            }
        });
    });

    // Helper function for notifications (make sure this is in your JS file)
    // If it's not, add it. It's used by the save location form as well.
    function showNotification(type, message) {
        // You can customize this to work with your specific notification library (e.g., Toastr, SweetAlert)
        // For now, we'll use a simple feedback div if it exists. Let's use the one from the save location form.
        const feedbackDiv = $('#save-location-feedback');
        if (feedbackDiv.length) {
            const alertClass = type === 'success' ? 'alert-success' : (type === 'warning' ? 'alert-warning' : 'alert-danger');
            feedbackDiv.html(`<div class="alert ${alertClass}">${message}</div>`);

            // Fade out the message after a few seconds
            setTimeout(() => {
                feedbackDiv.fadeOut(500, function () { $(this).empty().show(); });
            }, 4000);
        } else {
            // Fallback to a simple alert if the feedback div isn't on the page
            alert(message);
        }
    }

    // Delete Location Type Button
    $('#manage-types-list').on('click', '.delete-type-btn', function () {
        const typeId = $(this).closest('li').data('type-id');

        // The confirmation dialog is good practice and remains.
        if (!confirm('Are you sure you want to delete this location type? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: 'tracker_delete_location_type.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ id: typeId }),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // --- NEW BEHAVIOR ---
                    // 1. Close the modal first.
                    $('#deleteLocationTypeModal').modal('hide');

                    // 2. Show a success notification.
                    showNotification('success', response.message || 'Location type deleted successfully!');

                    // 3. Update the data in the background.
                    // Note: loadInitialData() is comprehensive and handles all of this,
                    // so we can simplify the next few lines.
                    // locationTypes = locationTypes.filter(t => t.id != typeId); 
                    // populateLocationTypeDropdown();
                    // populateManageTypesList(); 

                    // This single function re-fetches all data and redraws everything,
                    // which is the most reliable way to ensure the map and dropdowns are in sync.
                    loadInitialData();

                } else {
                    // On failure, show an error but keep the modal open.
                    showNotification('error', 'Error: ' + response.message);
                }
            },
            error: function () {
                // Handle network or server errors.
                showNotification('error', 'A server error occurred. Please try again.');
            }
        });
    });

    // REMINDER: Make sure the showNotification function is present in your file.
    function showNotification(type, message) {
        const feedbackDiv = $('#save-location-feedback');
        if (feedbackDiv.length) {
            const alertClass = type === 'success' ? 'alert-success' : (type === 'warning' ? 'alert-warning' : 'alert-danger');
            feedbackDiv.html(`<div class="alert ${alertClass}">${message}</div>`);

            setTimeout(() => {
                feedbackDiv.fadeOut(500, function () { $(this).empty().show(); });
            }, 4000);
        } else {
            alert(message); // Fallback
        }
    }

    // Event listener for the "Add New Saved Location" form.
    $('#save-location-form').on('submit', function (e) {
        // Prevent the form from doing a full page reload.
        e.preventDefault();

        // --- 1. Gather Data from All Form Inputs ---
        const locationName = $('#new-location-name').val().trim();
        const locationTypeId = $('#new-location-type').val();
        const latDms = $('#new-location-lat').val();
        const lonDms = $('#new-location-lon').val();

        // --- 2. Perform Client-Side Validation ---
        if (!locationName || !locationTypeId || !latDms || !lonDms) {
            // Use our friendly showNotification utility for consistency.
            showNotification('warning', 'Please fill out all fields: Type, Name, Latitude, and Longitude.');
            return;
        }

        // Use our robust DMS parser to convert coordinates.
        const latDecimal = parseDMS(latDms);
        const lonDecimal = parseDMS(lonDms);

        if (isNaN(latDecimal) || isNaN(lonDecimal)) {
            showNotification('error', 'Invalid Latitude or Longitude format. Please use DMS, e.g., 49 11 38 N');
            return;
        }

        // --- 3. Prepare the Data Object for the Server ---
        // This object structure must match what `tracker_new_location.php` expects.
        const locationData = {
            name: locationName,
            type_id: locationTypeId,
            lat: latDecimal,
            lon: lonDecimal
        };

        const $form = $(this);
        const $submitButton = $form.find('button[type="submit"]');
        const originalButtonText = $submitButton.html();

        // Disable button to prevent multiple submissions.
        $submitButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        // --- 4. Send Data to the Server via AJAX ---
        $.ajax({
            url: 'tracker_new_location.php', // The correct PHP script
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(locationData),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#save-location-feedback').html(`<div class="alert alert-success">${response.message || 'Location saved!'}</div>`);
                    $form[0].reset(); // Clear the form fields.

                    // After a successful save, we must reload all the initial data.
                    // This will refresh the map markers and all dropdown menus.
                    loadInitialData();
                } else {
                    $('#save-location-feedback').html(`<div class="alert alert-danger">${response.message || 'An unknown error occurred.'}</div>`);
                }
            },
            error: function () {
                $('#save-location-feedback').html(`<div class="alert alert-danger">A network error occurred. Please try again.</div>`);
            },
            complete: function () {
                // Re-enable the button after the request is complete (success or error).
                $submitButton.prop('disabled', false).html(originalButtonText);
                // Optional: fade out the success/error message after a few seconds
                setTimeout(() => {
                    $('#save-location-feedback').fadeOut(500, function () { $(this).empty().show(); });
                }, 4000);
            }
        });
    });

    // --- 7. FLIGHT ANIMATION "TICKER" ---
    function updateFlightPositions() {
        if (activeFlights.length === 0) return;

        const now = new Date(); // Current time

        activeFlights.forEach((flight, index) => {
            const color = $(`[data-flight-id=${flight.flight_id}]`).css('border-left-color') || flightColors[0];

            // Clean up previous markers and paths
            if (aircraftMarkers[flight.flight_id]) map.removeLayer(aircraftMarkers[flight.flight_id]);
            if (aircraftPaths[flight.flight_id]) map.removeLayer(aircraftPaths[flight.flight_id]);

            // Draw the flight path
            aircraftPaths[flight.flight_id] = L.polyline([flight.departure_loc, flight.destination_loc], {
                color: color,
                dashArray: '5, 10'
            }).addTo(map);

            // Calculate flight progress based on ATD and ETA
            const departureTime = new Date(flight.departure_time);
            const arrivalTime = new Date(flight.arrival_time);

            let progress = 0;

            // If current time is before ATD, flight hasn't departed yet
            if (now < departureTime) {
                progress = 0;
            }
            // If current time is after ETA, flight has arrived
            else if (now >= arrivalTime) {
                progress = 1;
            }
            // If current time is between ATD and ETA, calculate progress
            else {
                const totalDuration = arrivalTime.getTime() - departureTime.getTime();
                const elapsedTime = now.getTime() - departureTime.getTime();
                progress = elapsedTime / totalDuration;
            }

            // Calculate current position
            const lat = flight.departure_loc[0] + (flight.destination_loc[0] - flight.departure_loc[0]) * progress;
            const lng = flight.departure_loc[1] + (flight.destination_loc[1] - flight.departure_loc[1]) * progress;

            // Create or update aircraft marker
            aircraftMarkers[flight.flight_id] = L.marker([lat, lng], { icon: aircraftIcon }).addTo(map);

            // Update popup with current status
            const status = (progress >= 1) ? "Arrived" : (progress > 0 ? "In-Flight" : "Scheduled");
            const estimatedArrival = (progress < 1) ? `ETA: ${moment(arrivalTime).format('HH:mm')}` : `Arrived at: ${moment(arrivalTime).format('HH:mm')}`;

            aircraftMarkers[flight.flight_id].bindPopup(`
            <b>${escapeHtml(flight.craft)}</b><br>
            <b>Status:</b> ${status}<br>
            <b>ATD:</b> ${moment(departureTime).format('HH:mm')}<br>
            <b>${estimatedArrival}</b>
        `);
        });
    }

    // --- 8. INITIALIZE THE PAGE ---
    loadInitialData();
    setInterval(updateFlightPositions, 5000); // Update every 5 seconds
});
