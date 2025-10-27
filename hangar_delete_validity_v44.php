<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION["HeliUser"])) {
        throw new Exception('Authentication required');
    }

    $userId = $_SESSION["HeliUser"];
    $field = $_POST['field'];

    // Validate field name
    $validFields = [
        'for_lic', 'passport', 'nal_visa', 'us_visa', 'instruments', 'booklet',
        'train_rec', 'flight_train', 'base_check', 'night_cur', 'night_check',
        'ifr_cur', 'ifr_check', 'line_check', 'hoist_check', 'hoist_cur',
        'crm', 'hook', 'herds', 'dang_good', 'huet', 'english', 'faids',
        'fire', 'avsec'
    ];
    if (!in_array($field, $validFields)) {
        throw new Exception('Invalid certification type');
    }

    $query = "UPDATE validity SET $field = NULL WHERE pilot_id = ?";

    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception('Database preparation failed: ' . $mysqli->error);
    }

    $stmt->bind_param('i', $userId);
    if (!$stmt->execute()) {
        throw new Exception('Database update failed: ' . $stmt->error);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
