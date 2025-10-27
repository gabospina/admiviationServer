// flight-tracker-flights.js - Complete flight tracking and animation
var FlightTrackerFlights = FlightTrackerFlights || {};

FlightTrackerFlights.buildFlightList = function (scheduledFlights) {
    const listContainer = $('#todays-flights-list');
    listContainer.empty();

    if (!scheduledFlights || scheduledFlights.length === 0) {
        listContainer.html('<p class="text-muted p-3">No flights scheduled for today.</p>');
        return;
    }

    let flightDataStore = {};
    scheduledFlights.forEach((flight, index) => {
        flightDataStore[flight.flight_id] = flight;
        const color = FlightTracker.state.flightColors[index % FlightTracker.state.flightColors.length];
        let locationOptions = '<option value="">Select Location</option>';

        FlightTracker.state.savedLocations.forEach(loc => {
            locationOptions += `<option value="${loc.id}" data-lat="${loc.latitude}" data-lon="${loc.longitude}">${FlightTrackerUtils.escapeHtml(loc.location_name)}</option>`;
        });

        const flightHtml = `
            <div class="flight-item" data-flight-id="${flight.flight_id}" style="border-left: 5px solid ${color};">
                <h5>${FlightTrackerUtils.escapeHtml(flight.craft)} <small>(${FlightTrackerUtils.escapeHtml(flight.pilot_name)})</small></h5>
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

    // Initialize date pickers
    flatpickr(".atd-input, .eta-input", { enableTime: true, noCalendar: true, dateFormat: "H:i", time_24hr: true, minuteIncrement: 1 });

    // Set up event handlers
    FlightTrackerFlights.setupFlightEventHandlers(flightDataStore);
};

FlightTrackerFlights.setupFlightEventHandlers = function (flightDataStore) {
    $('#todays-flights-list').off('click', '.depart-btn').on('click', '.depart-btn', function () {
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

        FlightTracker.state.activeFlights = FlightTracker.state.activeFlights.filter(f => f.flight_id !== flightId);

        const originalFlightData = flightDataStore[flightId];
        FlightTracker.state.activeFlights.push({
            ...originalFlightData,
            departure_loc: [parseFloat(depOption.data('lat')), parseFloat(depOption.data('lon'))],
            destination_loc: [parseFloat(destOption.data('lat')), parseFloat(destOption.data('lon'))],
            departure_time: atd.toISOString(),
            arrival_time: eta.toISOString()
        });

        $(this).prop('disabled', true).text('Tracking...');
        FlightTrackerFlights.updateFlightPositions();
    });
};

FlightTrackerFlights.addAdHocFlight = function (flightData) {
    FlightTracker.state.activeFlights.push({
        flight_id: `adhoc-${Date.now()}`,
        craft: "Ad-Hoc Flight",
        pilot_name: "Manual Entry",
        ...flightData
    });
    FlightTrackerFlights.updateFlightPositions();
};

FlightTrackerFlights.updateFlightPositions = function () {
    if (FlightTracker.state.activeFlights.length === 0) return;

    const now = new Date();

    FlightTracker.state.activeFlights.forEach((flight) => {
        const color = $(`[data-flight-id=${flight.flight_id}]`).css('border-left-color') || FlightTracker.state.flightColors[0];

        // Clean up previous markers and paths
        if (FlightTracker.state.aircraftMarkers[flight.flight_id]) {
            FlightTracker.state.map.removeLayer(FlightTracker.state.aircraftMarkers[flight.flight_id]);
        }
        if (FlightTracker.state.aircraftPaths[flight.flight_id]) {
            FlightTracker.state.map.removeLayer(FlightTracker.state.aircraftPaths[flight.flight_id]);
        }

        // Draw the flight path
        FlightTracker.state.aircraftPaths[flight.flight_id] = L.polyline([flight.departure_loc, flight.destination_loc], {
            color: color,
            dashArray: '5, 10'
        }).addTo(FlightTracker.state.map);

        // Calculate flight progress
        const departureTime = new Date(flight.departure_time);
        const arrivalTime = new Date(flight.arrival_time);
        let progress = 0;

        if (now < departureTime) {
            progress = 0;
        } else if (now >= arrivalTime) {
            progress = 1;
        } else {
            const totalDuration = arrivalTime.getTime() - departureTime.getTime();
            const elapsedTime = now.getTime() - departureTime.getTime();
            progress = elapsedTime / totalDuration;
        }

        // Calculate current position
        const lat = flight.departure_loc[0] + (flight.destination_loc[0] - flight.departure_loc[0]) * progress;
        const lng = flight.departure_loc[1] + (flight.destination_loc[1] - flight.departure_loc[1]) * progress;

        // Create or update aircraft marker
        FlightTracker.state.aircraftMarkers[flight.flight_id] = L.marker([lat, lng], {
            icon: FlightTracker.state.aircraftIcon
        }).addTo(FlightTracker.state.map);

        // Update popup
        const status = (progress >= 1) ? "Arrived" : (progress > 0 ? "In-Flight" : "Scheduled");
        const estimatedArrival = (progress < 1) ?
            `ETA: ${moment(arrivalTime).format('HH:mm')}` :
            `Arrived at: ${moment(arrivalTime).format('HH:mm')}`;

        FlightTracker.state.aircraftMarkers[flight.flight_id].bindPopup(`
            <b>${FlightTrackerUtils.escapeHtml(flight.craft)}</b><br>
            <b>Status:</b> ${status}<br>
            <b>ATD:</b> ${moment(departureTime).format('HH:mm')}<br>
            <b>${estimatedArrival}</b>
        `);
    });
};

FlightTrackerFlights.startAnimationLoop = function () {
    setInterval(FlightTrackerFlights.updateFlightPositions, 5000);
};

// // When flight is added, publish event
// FlightTrackerEvents.publish(FlightTrackerEvents.EVENTS.FLIGHT_ADDED, flightData);

// // Subscribe to map ready event
// FlightTrackerEvents.subscribe(FlightTrackerEvents.EVENTS.MAP_READY, function (map) {
//     // Store map reference
//     FlightTracker.state.map = map;
// });
