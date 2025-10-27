// flight-tracker-main.js - Complete main initialization

// Initialize the application
$(document).ready(function () {
    console.log("Flight Tracker Initializing...");

    // Cache jQuery elements into the global object
    FlightTracker.elements.weatherWrapper = $('#weather-map-wrapper');
    FlightTracker.elements.overlayControls = $('.overlay-controls');
    FlightTracker.elements.opacitySlider = $('#overlay-opacity');
    FlightTracker.elements.weatherIframe = $('#weather-iframe');
    FlightTracker.elements.advisoryMessage = $('.advisory-message');
    FlightTracker.elements.dispatchDate = $('#dispatch-date');

    // Set up date
    FlightTracker.elements.dispatchDate.text(`- ${moment().format('dddd, MMMM D, YYYY')}`);

    // Initialize modules
    if (typeof FlightTrackerMap !== 'undefined' && FlightTrackerMap.init) {
        FlightTrackerMap.init();
    }
    if (typeof FlightTrackerUI !== 'undefined' && FlightTrackerUI.init) {
        FlightTrackerUI.init();
    }
    if (typeof FlightTrackerOverlay !== 'undefined' && FlightTrackerOverlay.init) {
        FlightTrackerOverlay.init();
    }

    // INITIALIZE OPACITY CONTROLS - ADD THIS LINE
    if (typeof FlightTrackerOverlay !== 'undefined' && FlightTrackerOverlay.setupOpacityControls) {
        FlightTrackerOverlay.setupOpacityControls();
    }

    // Load data and start animation - FIXED: Add proper error handling
    if (typeof FlightTrackerLocations !== 'undefined' && FlightTrackerLocations.loadInitialData) {
        FlightTrackerLocations.loadInitialData()
            .then((data) => {
                console.log("Initial data loaded successfully", data);

                // BUILD THE FLIGHT LIST HERE - This was missing!
                if (typeof FlightTrackerFlights !== 'undefined' && FlightTrackerFlights.buildFlightList) {
                    FlightTrackerFlights.buildFlightList(data.scheduledFlights);
                }

                if (typeof FlightTrackerFlights !== 'undefined' && FlightTrackerFlights.startAnimationLoop) {
                    FlightTrackerFlights.startAnimationLoop();
                }
            })
            .catch((error) => {
                console.error("Failed to load initial data:", error);
                // Ensure the spinner is removed even on error
                $('#todays-flights-list').html('<p class="text-danger p-3">Could not load flight data.</p>');
            });
    } else {
        console.error("FlightTrackerLocations.loadInitialData is not available");
        $('#todays-flights-list').html('<p class="text-danger p-3">Module loading error.</p>');
    }

});
