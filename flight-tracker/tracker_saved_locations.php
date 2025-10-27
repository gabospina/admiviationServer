<?php
// flight-tracker/user_saved_locations.php
// Fetches all saved locations for the user's company.

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once '../db_connect.php';

if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$company_id = (int)$_SESSION['company_id'];
$locations = [];

try {
    $stmt = $mysqli->prepare(
        "SELECT id, location_name, latitude, longitude FROM user_locations WHERE company_id = ? ORDER BY location_name ASC"
    );
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
    $stmt->close();
    
    echo json_encode($locations);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error.']);
}
?>