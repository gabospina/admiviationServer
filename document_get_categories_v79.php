<?php
// document_get_categories/php
ini_set('display_errors', 0); // Keep 0 for production, set to 1 if you need to debug this file directly
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$response = ['success' => false, 'data' => []];

try {
    require_once 'db_connect.php';

    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }

    // Get active categories only
    // Corrected Query (uses correct table name and column name)
    $query = "SELECT id, category as name FROM document_categories WHERE is_active = 1 ORDER BY category ASC";
    $result = $mysqli->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = [
                'id' => $row['id'],
                'name' => htmlspecialchars($row['name'])
            ];
        }
        $response['success'] = true;
    }
    // *** REMOVED THE INVALID JSON LINE THAT WAS HERE ***

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