<?php
// document_get_documents.php
session_start(); // ADDED: Start session for company_id access
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once "db_connect.php";

$response = ['success' => false, 'data' => [], 'message' => 'Initialization failed'];

// === FIX: ADD AUTHENTICATION AND COMPANY_ID CHECK ===
if (!isset($_SESSION['HeliUser']) || !isset($_SESSION['company_id'])) {
    $response['message'] = 'Authentication required.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

$company_id = (int)$_SESSION['company_id'];

try {
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }

    // === FIX: ADD COMPANY_ID TO BASE WHERE CLAUSE ===
    $whereClause = " WHERE d.company_id = ? AND d.is_active = 1";
    $params = [$company_id];
    $types = "i";

    // Check for category ID filter
    if (isset($_GET["category"]) && is_numeric($_GET["category"])) {
        $categoryId = (int)$_GET["category"];
        $whereClause .= " AND d.category_id = ?";
        $params[] = $categoryId;
        $types .= "i";
        error_log("Filtering documents by Category ID: " . $categoryId . " for company: " . $company_id);
    } else {
        error_log("Showing all documents for company: " . $company_id);
    }

    // === FIX: USE PREPARED STATEMENT WITH COMPANY_ID ===
    $query = "SELECT
                  d.id, d.category_id, d.original_filename, d.stored_filename,
                  d.filepath, d.preview_filepath, d.mime_type, d.filesize, d.upload_date,
                  d.creator, d.last_updated, d.is_active, d.description,
                  dc.category AS category_name,
                  CONCAT(u.lastname, ', ', u.firstname) AS creator_name
              FROM documents d
              LEFT JOIN document_categories dc ON d.category_id = dc.id
              LEFT JOIN users u ON u.id = d.creator
              {$whereClause}
              ORDER BY d.upload_date DESC";

    error_log("Executing document list query for company: " . $company_id);

    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    $stmt->close();

    $response['success'] = true;
    $response['data'] = $documents;
    $response['message'] = 'Documents retrieved successfully.';

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error in get_documents.php: " . $e->getMessage());
}

if (isset($mysqli) && $mysqli) {
    $mysqli->close();
}

echo json_encode($response);
exit;
?>