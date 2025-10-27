<?php
// Fetches all location types for the user's company.
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
    http_response_code(401); exit(json_encode(['error' => 'Authentication required.']));
}
$company_id = (int)$_SESSION['company_id'];
$types = [];

try {
    $stmt = $mysqli->prepare("SELECT id, type_name, color_hex FROM user_location_types WHERE company_id = ? ORDER BY type_name ASC");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $types[] = $row;
    }
    $stmt->close();
    echo json_encode($types);
} catch (Exception $e) {
    http_response_code(500); exit(json_encode(['error' => 'Database error.']));
}
?>