<?php
require_once('../db_connect.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$data = json_decode(file_get_contents('php://input'), true);

$stmt = $mysqli->prepare("
    INSERT INTO flight_tracker_logs 
    (timestamp, latitude, longitude, altitude, airspeed, wind_speed, wind_direction) 
    VALUES (NOW(), ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "ddiddi", 
    $data['lat'], 
    $data['lon'], 
    $data['alt'], 
    $data['speed'], 
    $data['wind'], 
    $data['heading']
);
$stmt->execute();
?>