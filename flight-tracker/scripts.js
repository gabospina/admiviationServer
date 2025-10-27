// Initialize map centered on Canada (latitude: 56.1304° N, longitude: 106.3468° W)
const map = L.map('map').setView([56.1304, -106.3468], 4);

// Base map layer
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenWeatherMap</a>'
}).addTo(map);

// OpenWeatherMap Precipitation Layer
const owmPrecipitation = L.tileLayer('https://tile.openweathermap.org/map/precipitation_new/{z}/{x}/{y}.png?appid=b756cb0ed0290bceb8b22a58ed5beeda', {
    attribution: '© OpenWeatherMap',
    maxZoom: 10,
    opacity: 0.7
});

// Cloud Layer
const owmClouds = L.tileLayer('https://tile.openweathermap.org/map/clouds_new/{z}/{x}/{y}.png?appid=b756cb0ed0290bceb8b22a58ed5beeda', {
    attribution: '© OpenWeatherMap',
    maxZoom: 10,
    opacity: 0.5
});

// Layer control
const baseLayers = {
    "Street Map": L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png')
};

const overlayMaps = {
    "Precipitation": owmPrecipitation,
    "Clouds": owmClouds
};

L.control.layers(baseLayers, overlayMaps, {
    collapsed: false
}).addTo(map);

// Add precipitation by default
owmPrecipitation.addTo(map);

// Error handling
owmPrecipitation.on('load', () => console.log('Precipitation layer loaded'));
owmPrecipitation.on('tileerror', (e) => console.warn('Tile load error:', e));

// Initialize with Canadian airports as reference points
const canadianAirports = [
    { name: "Vancouver", code: "CYVR", coords: [49.1939, -123.1845] },
    { name: "Calgary", code: "CYYC", coords: [51.1139, -114.0203] },
    { name: "Toronto", code: "CYYZ", coords: [43.6777, -79.6248] },
    { name: "Montreal", code: "CYUL", coords: [45.4706, -73.7408] }
];

canadianAirports.forEach(airport => {
    L.marker(airport.coords)
        .bindPopup(`<b>${airport.name} (${airport.code})</b>`)
        .addTo(map);
});

// Test the API key
fetch('https://api.openweathermap.org/data/2.5/weather?q=London&appid=b756cb0ed0290bceb8b22a58ed5beeda')
    .then(response => response.json())
    .then(data => {
        if (data.cod === 401) {
            alert('Invalid OpenWeatherMap API Key');
        }
    });