<?php
// Deletes a location type from the database.
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

$response = ['success' => false];
if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
    http_response_code(401); $response['message'] = 'Authentication required.'; echo json_encode($response); exit;
}
$company_id = (int)$_SESSION['company_id'];
$data = json_decode(file_get_contents('php://input'), true);
$type_id = isset($data['id']) ? (int)$data['id'] : 0;

if ($type_id <= 0) {
    http_response_code(400); $response['message'] = 'Invalid Type ID.'; echo json_encode($response); exit;
}

try {
    $stmt = $mysqli->prepare("DELETE FROM user_location_types WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $type_id, $company_id);
    if ($stmt->execute()) {
        $response['success'] = $stmt->affected_rows > 0;
    } else { throw new Exception('Failed to delete location type.'); }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500); $response['message'] = 'Database error: ' . $e->getMessage();
}
echo json_encode($response);
?>