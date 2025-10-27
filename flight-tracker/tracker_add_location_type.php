<?php
// Saves a new location type to the database.
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

$response = ['success' => false];
if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
    http_response_code(401); $response['message'] = 'Authentication required.'; echo json_encode($response); exit;
}
$company_id = (int)$_SESSION['company_id'];
$data = json_decode(file_get_contents('php://input'), true);

$name = trim($data['name'] ?? '');
$color = trim($data['color'] ?? '#007bff');

if (empty($name)) {
    http_response_code(400); $response['message'] = 'Type name cannot be empty.'; echo json_encode($response); exit;
}

try {
    $stmt = $mysqli->prepare("INSERT INTO user_location_types (company_id, type_name, color_hex) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $company_id, $name, $color);
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['new_type'] = ['id' => $stmt->insert_id, 'type_name' => $name, 'color_hex' => $color];
    } else { throw new Exception('Failed to save location type.'); }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500); $response['message'] = 'Database error: ' . $e->getMessage();
}
echo json_encode($response);
?>