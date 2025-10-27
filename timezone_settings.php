<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['timezone_type']) && isset($data['timezone'])) {
        $_SESSION[$data['timezone_type']] = $data['timezone'];
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
    }
} else {
    echo json_encode([
        'home_timezone' => $_SESSION['home_timezone'] ?? 'America/Toronto',
        'current_timezone' => $_SESSION['current_timezone'] ?? 'auto'
    ]);
}