// flight-tracker-locations.js - Complete location management
var FlightTrackerLocations = FlightTrackerLocations || {};

FlightTrackerLocations.loadInitialData = function () {
    return Promise.all([
        $.getJSON(base_url + 'tracker_saved_locations.php'),
        $.getJSON(base_url + 'tracker_today_flights.php'),
        $.getJSON(base_url + 'tracker_get_location_types.php')
    ]).then(([locations, scheduledFlights, types]) => {
        FlightTracker.state.savedLocations = locations;
        FlightTracker.state.locationTypes = types;

        FlightTrackerMap.displayLocationMarkers(locations);
        FlightTrackerLocations.populateLocationDropdowns();
        FlightTrackerLocations.populateLocationTypeDropdown();
        FlightTrackerLocations.populateManageTypesList();

        // RETURN THE DATA so it can be used by buildFlightList
        return { locations, scheduledFlights, types };
    }).catch(error => {
        console.error("Failed to load initial data:", error);
        $('#todays-flights-list').html('<p class="text-danger p-3">Could not load initial data.</p>');
        throw error; // Re-throw to propagate the error
    });
};

FlightTrackerLocations.populateLocationDropdowns = function () {
    const $depSelect = $('#departure-select');
    const $destSelect = $('#destination-select');
    $depSelect.empty().append('<option value="">Select Departure</option>');
    $destSelect.empty().append('<option value="">Select Destination</option>');

    FlightTracker.state.savedLocations.forEach(loc => {
        const option = `<option value="${loc.id}" data-lat="${loc.latitude}" data-lon="${loc.longitude}">${FlightTrackerUtils.escapeHtml(loc.location_name)}</option>`;
        $depSelect.append(option);
        $destSelect.append(option);
    });
};

FlightTrackerLocations.populateLocationTypeDropdown = function () {
    const $select = $('#new-location-type');
    $select.empty().append('<option value="">Select a Type</option>');
    FlightTracker.state.locationTypes.forEach(type => {
        $select.append(`<option value="${type.id}">${FlightTrackerUtils.escapeHtml(type.type_name)}</option>`);
    });
};

FlightTrackerLocations.populateManageTypesList = function () {
    const $list = $('#manage-types-list');
    $list.empty();
    if (FlightTracker.state.locationTypes.length === 0) {
        $list.append('<li>No types created yet.</li>');
        return;
    }
    FlightTracker.state.locationTypes.forEach(type => {
        const itemHtml = `
            <li data-type-id="${type.id}">
                <span>
                    <span class="color-box" style="background-color: ${type.color_hex};"></span>
                    ${FlightTrackerUtils.escapeHtml(type.type_name)}
                </span>
                <button class="btn btn-xs btn-danger delete-type-btn">&times;</button>
            </li>`;
        $list.append(itemHtml);
    });

};
