// flight-tracker-map.js - Complete map functionality
var FlightTrackerMap = FlightTrackerMap || {};

FlightTrackerMap.init = function () {
    const OWM_API_KEY = 'b756cb0ed0290bceb8b22a58ed5beeda';

    // Fix Leaflet icon paths
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });

    // Define Base and Weather Layers
    const streetMap = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    });

    const owmPrecipitation = L.tileLayer(`https://tile.openweathermap.org/map/precipitation_new/{z}/{x}/{y}.png?appid=${OWM_API_KEY}`, {
        attribution: '&copy; <a href="https://openweathermap.org/">OpenWeatherMap</a>',
        opacity: 0.9
    });

    const owmClouds = L.tileLayer(`https://tile.openweathermap.org/map/clouds_new/{z}/{x}/{y}.png?appid=${OWM_API_KEY}`, {
        attribution: '&copy; <a href="https://openweathermap.org/">OpenWeatherMap</a>',
        opacity: 0.9
    });

    // Initialize the Map
    FlightTracker.state.map = L.map('map', {
        center: [57.5, -98.5],
        zoom: 4,
        layers: [streetMap, owmPrecipitation]
    });

    setTimeout(() => FlightTracker.state.map.invalidateSize(), 150);

    // Setup Layer Control
    const baseLayers = { "Street Map": streetMap };
    const overlayMaps = { "Precipitation": owmPrecipitation, "Clouds": owmClouds };

    L.control.layers(baseLayers, overlayMaps, {
        collapsed: false
    }).addTo(FlightTracker.state.map);

    // Create aircraft icon
    FlightTracker.state.aircraftIcon = L.icon({
        iconUrl: '/admviation_2/images/loading.gif',
        iconSize: [40, 40],
    });

    console.log("Map initialized successfully");

    // When map is ready, publish event
    // FlightTrackerEvents.publish(FlightTrackerEvents.EVENTS.MAP_READY, FlightTracker.state.map);

    // // Subscribe to location updates
    // FlightTrackerEvents.subscribe(FlightTrackerEvents.EVENTS.LOCATIONS_LOADED, function (locations) {
    //     FlightTrackerMap.displayLocationMarkers(locations);
    // });
};

FlightTrackerMap.displayLocationMarkers = function (locations) {
    FlightTracker.state.locationMarkers.forEach(marker => FlightTracker.state.map.removeLayer(marker));
    FlightTracker.state.locationMarkers = [];

    locations.forEach(loc => {
        const type = FlightTracker.state.locationTypes.find(t => t.id === loc.location_type_id);
        const color = type ? type.color_hex : '#30e351ff';

        const circleMarker = L.circleMarker([loc.latitude, loc.longitude], {
            radius: 8, color: 'rgba(220, 35, 35, 1)', weight: 1, fillColor: color, fillOpacity: 0.9
        });

        const latDMS = FlightTrackerUtils.decimalToDMS(loc.latitude, true);
        const lonDMS = FlightTrackerUtils.decimalToDMS(loc.longitude, false);
        const typeName = type ? FlightTrackerUtils.escapeHtml(type.type_name) : 'Uncategorized';

        circleMarker.bindPopup(`<b>${FlightTrackerUtils.escapeHtml(loc.location_name)}</b><br><small>${typeName}</small><br><small>${latDMS}, ${lonDMS}</small>`);
        circleMarker.addTo(FlightTracker.state.map);
        FlightTracker.state.locationMarkers.push(circleMarker);
    });
};