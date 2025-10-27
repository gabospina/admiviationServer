// This file defines the global namespace and state for the entire application.
// It MUST be loaded FIRST.
var FlightTracker = FlightTracker || {};

FlightTracker.state = {
    locationTypes: [],
    savedLocations: [],
    activeFlights: [],
    aircraftMarkers: {},
    aircraftPaths: {},
    flightColors: ['#007bff', '#dc3545', '#28a745', '#6f42c1', '#fd7e14'],
    locationMarkers: [],
    isCalculating: false,
    map: null,
    aircraftIcon: null
};

FlightTracker.elements = {
    weatherWrapper: null,
    overlayControls: null,
    opacitySlider: null,
    weatherIframe: null,
    advisoryMessage: null,
    dispatchDate: null
};
