// flight-tracker-ui.js - Complete UI event handlers
var FlightTrackerUI = FlightTrackerUI || {};

// flight-tracker-ui.js - Update flatpickr initialization for 24-hour format
FlightTrackerUI.init = function () {
    // Initialize date/time pickers with 24-hour format
    FlightTrackerUI.atdPlannerPicker = flatpickr("#atd-planner", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",  // 24-hour format
        time_24hr: true,    // Force 24-hour display
        minuteIncrement: 1
    });

    FlightTrackerUI.etaPlannerPicker = flatpickr("#eta-planner", {
        enableTime: true,
        noCalendar: true,
        dateFormat: "H:i",  // 24-hour format
        time_24hr: true,    // Force 24-hour display
        minuteIncrement: 1
    });

    // Set up event handlers
    FlightTrackerUI.setupPlannerEvents();
    FlightTrackerUI.setupLocationTypeEvents();
    FlightTrackerUI.setupSaveLocationEvents();
};

FlightTrackerUI.setupPlannerEvents = function () {
    $('#departure-select, #destination-select').on('change', function () {
        const depOpt = $('#departure-select option:selected');
        const destOpt = $('#destination-select option:selected');

        if (depOpt.val() && depOpt.data('lat') && depOpt.data('lon')) {
            $('#departure-coords').val(`${FlightTrackerUtils.decimalToDMS(parseFloat(depOpt.data('lat')), true)}, ${FlightTrackerUtils.decimalToDMS(parseFloat(depOpt.data('lon')), false)}`);
        } else {
            $('#departure-coords').val('');
        }

        if (destOpt.val() && destOpt.data('lat') && destOpt.data('lon')) {
            $('#destination-coords').val(`${FlightTrackerUtils.decimalToDMS(parseFloat(destOpt.data('lat')), true)}, ${FlightTrackerUtils.decimalToDMS(parseFloat(destOpt.data('lon')), false)}`);
        } else {
            $('#destination-coords').val('');
        }

        FlightTrackerUI.calculateEtaFromSpeed();
    });

    $('#speed').on('blur', FlightTrackerUI.calculateEtaFromSpeed);
    $('#eta-planner, #atd-planner, #departure-coords, #destination-coords').on('change', FlightTrackerUI.calculateSpeedFromEta);

    $('#start-flight').on('click', function () {
        const atdTime = FlightTrackerUI.atdPlannerPicker.selectedDates[0];
        const etaTime = FlightTrackerUI.etaPlannerPicker.selectedDates[0];
        const depCoords = FlightTrackerUtils.parseCoords($('#departure-coords').val());
        const destCoords = FlightTrackerUtils.parseCoords($('#destination-coords').val());

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

        FlightTrackerFlights.addAdHocFlight({
            departure_loc: depCoords,
            destination_loc: destCoords,
            departure_time: atd.toISOString(),
            arrival_time: eta.toISOString()
        });
    });
};

FlightTrackerUI.calculateEtaFromSpeed = function () {
    if (FlightTracker.state.isCalculating) return;
    FlightTracker.state.isCalculating = true;

    const depCoords = FlightTrackerUtils.parseCoords($('#departure-coords').val());
    const destCoords = FlightTrackerUtils.parseCoords($('#destination-coords').val());
    const speedKnots = parseFloat($('#speed-planner').val());
    const etdTime = FlightTrackerUI.atdPlannerPicker.selectedDates[0];

    // Check if we have all valid data
    if (depCoords && !isNaN(depCoords[0]) && !isNaN(depCoords[1]) &&
        destCoords && !isNaN(destCoords[0]) && !isNaN(destCoords[1]) &&
        speedKnots > 0 && etdTime) {

        const today = new Date();
        const etd = new Date(today.getFullYear(), today.getMonth(), today.getDate(), etdTime.getHours(), etdTime.getMinutes());

        const distanceKm = FlightTrackerUtils.calculateDistance(depCoords[0], depCoords[1], destCoords[0], destCoords[1]);
        const speedKph = speedKnots * 1.852;

        const durationHours = distanceKm / speedKph;
        const newEta = new Date(etd.getTime() + (durationHours * 3600 * 1000));

        FlightTrackerUI.etaPlannerPicker.setDate(newEta, false);
    }
    FlightTracker.state.isCalculating = false;
};

FlightTrackerUI.calculateSpeedFromEta = function () {
    if (FlightTracker.state.isCalculating) return;
    FlightTracker.state.isCalculating = true;

    const depCoords = FlightTrackerUtils.parseCoords($('#departure-coords').val());
    const destCoords = FlightTrackerUtils.parseCoords($('#destination-coords').val());
    const etdTime = FlightTrackerUI.atdPlannerPicker.selectedDates[0];
    const etaTime = FlightTrackerUI.etaPlannerPicker.selectedDates[0];

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
            const distanceKm = FlightTrackerUtils.calculateDistance(depCoords[0], depCoords[1], destCoords[0], destCoords[1]);
            const speedKph = distanceKm / durationHours;
            $('#speed-planner').val(Math.round(speedKph * 0.539957));
        }
    }
    FlightTracker.state.isCalculating = false;
};

FlightTrackerUI.setupLocationTypeEvents = function () {
    $('#add-location-type-form').on('submit', function (e) {
        e.preventDefault();
        const typeData = {
            name: $('#new-type-name').val(),
            color: $('#new-type-color').val()
        };

        if (!typeData.name) {
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
                    FlightTracker.state.locationTypes.push(response.new_type);
                    FlightTrackerLocations.populateLocationTypeDropdown();
                    FlightTrackerLocations.populateManageTypesList();
                    $('#add-location-type-form')[0].reset();
                    $('#addLocationTypeModal').modal('hide');
                    FlightTrackerUtils.showNotification('success', response.message || 'New location type created!');
                } else {
                    FlightTrackerUtils.showNotification('error', 'Error: ' + response.message);
                }
            },
            error: function () {
                FlightTrackerUtils.showNotification('error', 'A server error occurred. Please try again.');
            }
        });
    });

    $('#manage-types-list').on('click', '.delete-type-btn', function () {
        const typeId = $(this).closest('li').data('type-id');

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
                    $('#deleteLocationTypeModal').modal('hide');
                    FlightTrackerUtils.showNotification('success', response.message || 'Location type deleted successfully!');
                    FlightTrackerLocations.loadInitialData();
                } else {
                    FlightTrackerUtils.showNotification('error', 'Error: ' + response.message);
                }
            },
            error: function () {
                FlightTrackerUtils.showNotification('error', 'A server error occurred. Please try again.');
            }
        });
    });
};

FlightTrackerUI.setupSaveLocationEvents = function () {
    $('#save-location-form').on('submit', function (e) {
        e.preventDefault();

        const locationName = $('#new-location-name').val().trim();
        const locationTypeId = $('#new-location-type').val();
        const latDms = $('#new-location-lat').val();
        const lonDms = $('#new-location-lon').val();

        if (!locationName || !locationTypeId || !latDms || !lonDms) {
            FlightTrackerUtils.showNotification('warning', 'Please fill out all fields: Type, Name, Latitude, and Longitude.');
            return;
        }

        const latDecimal = FlightTrackerUtils.parseDMS(latDms);
        const lonDecimal = FlightTrackerUtils.parseDMS(lonDms);

        if (isNaN(latDecimal) || isNaN(lonDecimal)) {
            FlightTrackerUtils.showNotification('error', 'Invalid Latitude or Longitude format. Please use DMS, e.g., 49 11 38 N');
            return;
        }

        const locationData = {
            name: locationName,
            type_id: locationTypeId,
            lat: latDecimal,
            lon: lonDecimal
        };

        const $form = $(this);
        const $submitButton = $form.find('button[type="submit"]');
        const originalButtonText = $submitButton.html();

        $submitButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: 'tracker_new_location.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(locationData),
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $('#save-location-feedback').html(`<div class="alert alert-success">${response.message || 'Location saved!'}</div>`);
                    $form[0].reset();
                    FlightTrackerLocations.loadInitialData();
                } else {
                    $('#save-location-feedback').html(`<div class="alert alert-danger">${response.message || 'An unknown error occurred.'}</div>`);
                }
            },
            error: function () {
                $('#save-location-feedback').html(`<div class="alert alert-danger">A network error occurred. Please try again.</div>`);
            },
            complete: function () {
                $submitButton.prop('disabled', false).html(originalButtonText);
                setTimeout(() => {
                    $('#save-location-feedback').fadeOut(500, function () { $(this).empty().show(); });
                }, 4000);
            }
        });
    });
};

// // When user changes something, publish event
// FlightTrackerEvents.publish(FlightTrackerEvents.EVENTS.UI_PLANNER_UPDATED, plannerData);

// // Subscribe to notifications
// FlightTrackerEvents.subscribe(FlightTrackerEvents.EVENTS.UI_NOTIFICATION_SHOW, function (type, message) {
//     FlightTrackerUtils.showNotification(type, message);
// });