<?php
require_once('../db_connect.php');
header('Content-Type: application/json');

// Mock API - replace with real NOAA/OpenWeatherMap calls
if ($_GET['type'] === 'sigmet') {
    // Sample SIGMET data
    echo json_encode([
        [
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[/* coordinates */]]
            ],
            'properties' => [
                'hazard' => 'Thunderstorm area'
            ]
        ]
    ]);
} 
elseif ($_GET['type'] === 'wind') {
    // Sample wind data
    echo json_encode([
        'speed' => rand(10, 50),
        'direction' => rand(0, 360)
    ]);
}

$type = $_GET['type'] ?? '';
$lat = $_GET['lat'] ?? '';
$lon = $_GET['lon'] ?? '';

if ($type === 'sigmet') {
    // Fetch real SIGMET data from NOAA for North America
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://aviationweather.gov/cgi-bin/data/sigmet.php?format=json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo $response;
}
elseif ($type === 'wind' && is_numeric($lat) && is_numeric($lon)) {
    // Get wind data from OpenWeatherMap
    $apiKey = 'b756cb0ed0290bceb8b22a58ed5beeda';
    $url = "https://api.openweathermap.org/data/2.5/weather?lat=$lat&lon=$lon&appid=$apiKey";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['wind'])) {
        echo json_encode([
            'speed' => $data['wind']['speed'] * 1.94384, // Convert m/s to knots
            'direction' => $data['wind']['deg']
        ]);
    } else {
        echo json_encode(['error' => 'Wind data unavailable']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>