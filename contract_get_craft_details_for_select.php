<?php
// get_craft_details_for_select.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_connect.php';
// No ApiResponse needed for direct echo

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode([]); // Return empty on auth failure
    exit;
}
$company_id = (int)$_SESSION['company_id'];

global $mysqli;
if (!$mysqli || $mysqli->connect_error) { /* ... error handling ... */ echo json_encode([]); exit; }

try {
    $craft_details = [];
    // Fetch id, craft_type, registration for active crafts in the company
    $sql = "SELECT id, craft_type, registration FROM crafts WHERE company_id = ? AND alive = 1 ORDER BY craft_type ASC, registration ASC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("SQL prepare error: " . $mysqli->error);
    $stmt->bind_param("i", $company_id);
    if (!$stmt->execute()) throw new Exception("SQL execute error: " . $stmt->error);

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $craft_details[] = [
            'id' => (int)$row['id'],
            'craft_type' => htmlspecialchars($row['craft_type']),
            'registration' => htmlspecialchars($row['registration'])
        ];
    }
    $stmt->close();
    echo json_encode($craft_details); // Returns an array of objects
    exit;
} catch (Exception $e) { /* ... error handling ... */ echo json_encode([]); exit; }
?>