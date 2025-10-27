<?php
// flight_tracker.php (FINAL CLEANED & STYLED VERSION)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['HeliUser'])) { header("Location: ../index.php"); exit; }

// This flag tells the "smart" header.php which page this is
$page = "flight-tracker";

// Define page-specific assets BEFORE including the header
$page_stylesheets = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'];

// Now, include the header. It will use the variables we just defined.
include_once "../header.php";  
?>

<style>
    #map { height: 70vh; }
    .card-body { display: flex; flex-direction: column; }
    .flight-controls-wrapper { flex-shrink: 0; }
    
    #todays-flights-list {display: flex; flex-wrap: wrap; gap: 15px;}

    .flight-item {
        flex: 1 1 32%;  /* Flex-grow, flex-shrink, and a base width of ~32% to fit THREE per row */
        min-width: 380px; /* Adjust min-width to allow for smaller size */
        padding: 15px; 
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #fff;
    }
    .flight-item h5 { margin-top: 0; }

    .flight-item .controls-row {display: flex; gap: 10px; align-items: flex-end;
    }

    #manage-types-list li { display: flex; justify-content: space-between; align-items: center; padding: 5px; border-bottom: 1px solid #eee; }
    .color-box { width: 20px; height: 20px; border-radius: 4px; border: 1px solid #777; margin-right: 10px; }

    /* ===== MAP LAYOUT MANAGEMENT STYLES ===== */
    
    /* Set the maps side by side */
    .map-container { display: flex; gap: 15px; margin-top: 15px; transition: all 0.3s ease; }
    #map-wrapper { flex: 1; min-width: 60%; transition: all 0.3s ease; }

    .map-container.overlay-active {gap: 0;}

    /* Ensure proper panel sizing */
    #map-wrapper .panel,
    #map-wrapper .panel-body,
    .map-container.overlay-active #map-wrapper {
        flex: 1;
        min-width: 100%;
        width: 100%;
    }

    /* Ensure flight map panel expands properly */
    .map-container.overlay-active #map-wrapper .panel {
        width: 100%;
        height: 100%;
    }

    .map-container.overlay-active #map-wrapper .panel-body {
        height: calc(100% - 45px); /* Adjust based on panel header height */
    }

    /* Overlay mode styles */
    .map-container.overlay-active #weather-map-wrapper {
        position: fixed;
        z-index: 10000;
        width: 60% !important;
        height: 70vh !important;
        box-shadow: transparent;
        border: transparent;
        cursor: move;
        margin: 0 !important;
        background: transparent !important;
    }

    /* ===== OVERLAY CONTROL STYLES ===== */
    .overlay-mode {
        position: fixed !important;
        z-index: 10000;
        width: 95%;
        height: 75vh;
        box-shadow: transparent;
        border: transparent;
        cursor: move;
        margin: 0 !important;
        background: transparent !important; /* Ensure transparency */
    }

    .overlay-mode .panel.panel-default {
        background: transparent !important;
        border: none !important;
    }

    .overlay-mode .panel-heading {
        /* background: rgba(255, 255, 255, 0.8) !important; */
        background: rgba(176, 204, 224, 0.8) !important;
        cursor: move;
    }

    .overlay-mode .panel-body {
        background: transparent !important;
    }

    /* Update the overlay mode to target the wrapper */
    .overlay-mode .weather-iframe-wrapper {
        opacity: 3 !important; /* Default transparency */
        background: transparent !important;
    }
    
    .overlay-controls {
        position: absolute;
        top: 50px; /* Changed from 10px to 50px to move it down */
        right: 1%; /* Box changed to the right */
        z-index: 10001;
        background: rgba(255, 255, 255, 0.95);
        padding: 6px;
        border-radius: 4px;
        box-shadow: 0 0 50px rgba(0,0,0,0.2);
        border: 1px solid #ddd;
        min-width: 60px;

        /* MAKE OUTER BOX THE FLEX CONTAINER */
        display: flex;
        flex-direction: column;
        gap: 5px; /* Space between button row and slider */
    }

    /* Style the button container */
    .overlay-controls .button-row {
        display: flex;
        justify-content: center; /* Space buttons evenly */
        gap: 4px;
    }

    .overlay-controls .transparency-control {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    /* Square buttons */
    .overlay-controls .button-row button {
        width: 36px; /* Square size */
        height: 20px; /* Square size */
        padding: 0; /* Remove padding */
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 3px; /* Slightly rounded */
        border: 1px solid #ddd;
    }
    
    .overlay-controls button {
        margin: 2px;
        padding: 3px 8px;
        font-size: 12px;
        cursor: pointer;
        border: 1px solid #ddd;
        border-radius: 3px;
        background: #f8f9fa;
    }
    
    /* Specific button styles */
    #snap-to-fit {background: #17a2b8; color: white; border-color: #138496;
    }
    
    #close-overlay {background: #dc3545; color: white; border-color: #c82333;
    }
    
    /* Button hover effects */
    .overlay-controls .button-row button:hover {
        opacity: 0.9;
        transform: scale(1.05);
    }

    .overlay-controls .button-row button:active {
        transform: scale(0.95);
    }

    /* Style the opacity label */
    .overlay-controls .opacity-label {
        font-size: 12px;
        text-align: left;
        margin: 2px 0;
        color: #333;
    }

    /* Transparency control - compact */
    .overlay-controls .transparency-control {
        display: flex;
        flex-direction: column;
        gap: 3px; /* Tight gap */
    }

    /* Shorter slider */
    .overlay-controls .transparency-control input[type="range"] {
        width: 80px; /* Shorter slider */
        height: 4px; /* Thinner slider */
        margin: 0;
        display: block;
    }

    /* ========================= */

    /* Ensure the panel is resizable and properly styled */
    #weather-map-wrapper .panel.ui-resizable {position: relative; overflow: visible;}

    /* Resize handle styling */
    #weather-map-wrapper .panel.ui-resizable .ui-resizable-handle.ui-resizable-se {
        width: 20px;
        height: 20px;
        background: #007bff;
        border: 2px solid white;
        border-radius: 3px;
        position: absolute;
        bottom: 5px;
        right: 5px;
        cursor: se-resize;
        z-index: 10002;
        box-shadow: 0 0 5px rgba(0,0,0,0.7);
    }

    #weather-map-wrapper .panel.ui-resizable .ui-resizable-handle.ui-resizable-se:hover {
        background: #0056b3;
        transform: scale(1.1);
    }

    #weather-map-wrapper { flex: 1; min-width: 35%; display: flex; flex-direction: column; }
    #weather-map-wrapper .panel { flex-grow: 1; display: flex; flex-direction: column; }
    #weather-map-wrapper .panel-body { flex-grow: 1; position: relative; display: flex; padding: 10px; }
    .weather-iframe-wrapper {width: 100%; height: 100%; position: relative;}
    #weather-iframe {width: 100%; height: 100%; border: 0; border-radius: 4px;}
    #weather-map-wrapper.overlay-mode {width: 95% !important; height: 70vh !important;}

    .weather-iframe-wrapper { flex-grow: 1; }
    .weather-iframe-wrapper iframe { width: 100%; height: 100%; border: 0; border-radius: 4px; }
    #weather-map-instruction {color: #007bff; font-weight: bold;}

</style>

<head>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>

<!-- Main Page Content -->
<div class="container-fluid" style="padding-top: 40px;">
    <div class="card">
        <div class="card-header">
            <h2>Live Flight Tracker</h2>
        </div>
        <div class="card-body">
            <!-- Wrapper for all control panels -->
            <div class="flight-controls-wrapper">

                <!-- Panel for Today's Flights -->
                <div class="panel panel-default">
                    <div class="panel-heading"><h5>Today's Flight Dispatch <span id="dispatch-date" class="text-muted"></span></h5></div>
                    <div class="panel-body" id="todays-flights-list">
                        <p class="text-center p-3"><i class="fa fa-spinner fa-spin"></i> Loading schedule...</p>
                    </div>
                </div>

                <!-- Panel for the Ad-Hoc Planner -->
                <!-- Panel for the Ad-Hoc Planner -->
                <div class="panel panel-default">
                    <div class="panel-heading"><h5>Ad-Hoc Flight Planner</h5></div>
                    <div class="panel-body">
                        
                        <!-- Location Selection Boxes -->
                        <div style="display: flex; justify-content: left; align-items: flex-start; gap: 20px; margin-bottom: 20px;">
                            
                            <!-- Box 1: Saved Locations -->
                            <div style="width: 33%; border: 1px solid #ccc; padding: 15px; border-radius: 4px;">
                                <h5>Plan with Saved Locations</h5>
                                <div style="display: flex; gap: 10px;">
                                    <div class="form-group" style="flex: 1;">
                                        <label>Departure</label>
                                        <select id="departure-select" class="form-control"></select>
                                    </div>
                                    <div class="form-group" style="flex: 1;">
                                        <label>Destination</label>
                                        <select id="destination-select" class="form-control"></select>
                                    </div>
                                </div>
                            </div>

                            <!-- "OR" Separator -->
                            <div style="flex-shrink: 0; padding-top: 45px;">
                                <strong>OR</strong>
                            </div>

                            <!-- Box 2: Manual Coordinates -->
                            <div style="width: 33%; border: 1px solid #ccc; padding: 15px; border-radius: 4px;">
                                <h5>Manual Coordinate Entry</h5>
                                <div style="display: flex; gap: 10px;">
                                    <div class="form-group" style="flex: 1;">
                                        <label>Departure (DMS)</label>
                                        <input type="text" id="departure-coords" class="form-control" placeholder="e.g., 45 28 14 N, 73 44 27 W">
                                    </div>
                                    <div class="form-group" style="flex: 1;">
                                        <label>Destination (DMS)</label>
                                        <input type="text" id="destination-coords" class="form-control" placeholder="e.g., 43 40 38 N, 79° 37 50 W">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Flight Parameters -->
                        <div class="form-group">
                            <label>Flight Parameters</label>
                            <div style="display: flex; align-items: flex-end; gap: 8px; flex-wrap: nowrap;">
                                <!-- ATD Field -->
                                <div class="form-group" style="width: 120px; margin-bottom: 0;">
                                    <label style="font-size: 12px; margin-bottom: 2px;">ATD</label>
                                    <input type="text" id="atd-planner" class="form-control" placeholder="HH:MM" required style="height: 30px; padding: 5px 8px;">
                                </div>
                                
                                <!-- ETA Field -->
                                <div class="form-group" style="width: 120px; margin-bottom: 0;">
                                    <label style="font-size: 12px; margin-bottom: 2px;">ETA</label>
                                    <input type="text" id="eta-planner" class="form-control" placeholder="HH:MM" required style="height: 30px; padding: 5px 8px;">
                                </div>
                                
                                <!-- Speed Field -->
                                <div class="form-group" style="width: 120px; margin-bottom: 0;">
                                    <label style="font-size: 12px; margin-bottom: 2px;">Speed (knots)</label>
                                    <input type="number" id="speed-planner" class="form-control" placeholder="Knots" style="height: 30px; padding: 5px 8px;">
                                </div>
                                
                                <!-- Animate Flight Button -->
                                <div class="form-group" style="flex-shrink: 0; margin-bottom: 0; padding-bottom: 2px;">
                                    <button type="button" id="start-flight" class="btn btn-primary btn-sm" style="height: 30px; padding: 5px 12px;">
                                        Animate Flight
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NEW PANEL: Add New Saved Location -->
                <div class="panel panel-default">
                    <div class="panel-heading"><h5>Add New Saved Location</h5></div>
                    <div class="panel-body">
                        <div id="save-location-feedback"></div>
                        <form id="save-location-form">
                            <div style="display: flex; align-items: flex-end; gap: 8px; flex-wrap: nowrap;">
                                <!-- Location type -->
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label>Location Type</label>
                                    <div class="input-group">
                                        <select id="new-location-type" class="form-control" required>
                                            <option value="">Loading types...</option>
                                        </select>
                                        <span class="input-group-btn">
                                        <!-- Button to open ADD modal -->
                                        <button class="btn btn-default" type="button" data-toggle="modal" data-target="#addLocationTypeModal" title="Add New Location Type">
                                            <i class="fa fa-plus"></i>
                                        </button>
                                        <!-- Button to open DELETE modal -->
                                        <button class="btn btn-default" type="button" data-toggle="modal" data-target="#deleteLocationTypeModal" title="Delete a Location Type">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                                
                                <!-- Location Name -->
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label>Location Name</label>
                                    <input type="text" id="new-location-name" class="form-control" required>
                                </div>
                                
                                <!-- Latitude -->
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label>Latitude (DMS)</label>
                                    <input type="text" id="new-location-lat" class="form-control" placeholder="e.g., 49 11 38 N" required>
                                </div>
                                
                                <!-- Longitude -->
                                <div class="form-group" style="width: 150px; margin-bottom: 0;">
                                    <label>Longitude (DMS)</label>
                                    <input type="text" id="new-location-lon" class="form-control" placeholder="e.g., 123 11 04 W" required>
                                </div>
                                
                                <!-- Save Button -->
                                <div class="form-group" style="flex-shrink: 0; margin-bottom: 0;">
                                    <button type="submit" class="btn btn-success">Save Location</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Map Wrapper -->
                <!-- <div id="map-wrapper">
                    <div id="map"></div>
                </div> -->

                                <!-- START: Side-by-Side Map Layout Wrapper -->
                <div class="map-container"> <!-- Added wrapper div -->

                    <!-- Column 1: Your Flight Tracker Map -->
                    <div id="map-wrapper">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5>Flight Tracks</h5>
                            </div>
                            <div class="panel-body" style="padding: 0;">
                                <div id="map"></div> <!-- Your existing map goes here -->
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Live Weather Map Panel -->
                    <div id="weather-map-wrapper">
                        <div class="panel panel-default">
                            <div class="panel-heading" style="cursor: pointer;" title="Double-click or Ctrl+click to drag over flight map">
                                <h5>Live Weather Radar 
                                    <small id="weather-map-instruction" class="text-muted">(Double-click header to overlay flight tracker)</small>
                                </h5>
                            </div>
                            <div class="panel-body" style="padding: 10px; position: relative;">
                                <!-- Overlay controls -->
                                <div class="overlay-controls" style="display: none;">
                                    <div class="button-row">
                                        <button id="snap-to-fit" class="btn btn-xs btn-info" title="Snap to center">
                                            <i class="fa fa-crosshairs"></i>
                                        </button>
                                        <button id="close-overlay" class="btn btn-xs btn-danger" title="Close overlay">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </div>

                                    <div class="opacity-label">Opacity</div>

                                    <div class="transparency-control">
                                        <input type="range" id="overlay-opacity" min="3" max="10" value="8">
                                    </div>
                                </div>

                                <!-- Weather iframe wrapper - NO RESIZE HERE -->
                                <div class="weather-iframe-wrapper">
                                    <iframe src="https://www.rainviewer.com/map.html?loc=56.0,-106.0,3&oFa=0&oC=0&oU=0&oCS=1&oF=0&oAP=0&rmt=0&c=1&o=83&lm=1&layer=radar&sm=1&sn=1"
                                        width="100%" 
                                        height="100%"
                                        frameborder="0" 
                                        style="border:0; border-radius: 4px;" 
                                        allowfullscreen
                                        id="weather-iframe"
                                        loading="lazy">
                                    </iframe>
                                </div>
                            </div>
                        </div>
                    </div>

<!-- Preload fonts to fix the warning -->
<div class="preload-fonts">
    <span style="font-family: 'FontAwesome';">.</span>
</div>
                <!-- END: Side-by-Side Map Layout Wrapper -->


                <!-- --- NEW: Add Location Type Modal --- -->
        <div class="modal fade" id="addLocationTypeModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">Add New Location Type</h4>
                    </div>
                    <div class="modal-body">
                        <form id="add-location-type-form">
                            <div style="display: flex; align-items: flex-end; gap: 15px;">
                                <div style="flex: 1;">
                                    <label for="new-type-name">Type Name</label>
                                    <input type="text" id="new-type-name" class="form-control" required>
                                </div>
                                <div>
                                    <label for="new-type-color">Color</label>
                                    <input type="color" id="new-type-color" class="form-control" value="#007bff" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Add Type</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- --- NEW: Delete Location Type Modal --- -->
        <div class="modal fade" id="deleteLocationTypeModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">Delete Existing Location Types</h4>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">Click the delete button (<i class="fa fa-times text-danger"></i>) next to the type you wish to remove.</p>
                        <ul class="list-unstyled" id="manage-types-list">
                            <li><i class="fa fa-spinner fa-spin"></i> Loading...</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

<?php
// Define page-specific assets
// $page_stylesheets = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'];
$page_scripts = [
    
    'https://cdn.rainviewer.com/leaflet/rainviewer-js-leaflet/rainviewer.min.js',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    'https://code.jquery.com/ui/1.13.2/jquery-ui.min.js', // Add jQuery UI for better dragging
    // 'flight-tracker/flight-tracker-functions.js',

    // 1. CORE file FIRST to define the global object
    'flight-tracker/flight-tracker-core.js',

    // 2. Events and Utilities next
    'flight-tracker/flight-tracker-events.js',
    'flight-tracker/flight-tracker-utilities.js',
    
    // 3. All other modules that DEPEND on the core object
    'flight-tracker/flight-tracker-map.js',
    'flight-tracker/flight-tracker-locations.js',
    'flight-tracker/flight-tracker-flights.js',
    'flight-tracker/flight-tracker-overlay.js',
    'flight-tracker/flight-tracker-ui.js',
    
    // 4. MAIN file LAST to initialize everything
    'flight-tracker/flight-tracker-main.js'

];
include_once "../footer.php";
?>
