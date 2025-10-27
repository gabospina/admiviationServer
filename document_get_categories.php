<?php
// document_get_categories.php
session_start(); // ADDED: Start session for company_id access
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$response = ['success' => false, 'data' => []];

// === FIX: ADD AUTHENTICATION AND COMPANY_ID CHECK ===
if (!isset($_SESSION['HeliUser']) || !isset($_SESSION['company_id'])) {
    $response['message'] = 'Authentication required.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

$company_id = (int)$_SESSION['company_id'];

try {
    require_once 'db_connect.php';

    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }

    // === FIX: ADD COMPANY_ID FILTER TO CATEGORIES QUERY ===
    $query = "SELECT id, category as name FROM document_categories WHERE company_id = ? AND is_active = 1 ORDER BY category ASC";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }
    
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = [
                'id' => $row['id'],
                'name' => htmlspecialchars($row['name'])
            ];
        }
        $response['success'] = true;
    }
    $stmt->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error in get_document_categories: " . $e->getMessage());
}

// Close connection if it exists
if (isset($mysqli) && $mysqli) {
    $mysqli->close();
}

echo json_encode($response);
exit;
?>