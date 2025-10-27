<?php
// hangar_get_assigned_crafts.php
require_once 'db_connect.php';
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['HeliUser'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}
$pilot_id = (int)$_SESSION['HeliUser'];

// Using 'pilot_craft_type' table
$stmt = $mysqli->prepare("SELECT craft_type, position FROM pilot_craft_type WHERE pilot_id = ? ORDER BY craft_type");
$stmt->bind_param("i", $pilot_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'data' => $data]);
?>