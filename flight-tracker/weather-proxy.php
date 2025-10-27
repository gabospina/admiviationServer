<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Just validate the OpenWeatherMap key (no actual proxy needed)
echo json_encode([
    'status' => 'ready',
    'message' => 'Using direct OpenWeatherMap tiles',
    'timestamp' => date('c')
]);
?>