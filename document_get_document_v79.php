<?php
// document_get_document.php

session_start();

// Error reporting (Set to 0 for production)
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Set header early

// Default response in case of failure
$response = ['success' => false, 'message' => 'Failed to retrieve document details.', 'data' => null];

require_once "db_connect.php"; // Use require_once

if (!isset($_SESSION['HeliUser'])) {
    // Adding auth check for security
    $response['message'] = 'Authentication required.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}
$userId = (int)$_SESSION['HeliUser']; // Cast to integer after validation

// Get Document ID from GET parameter safely
$docId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if (!$docId) {
    $response['message'] = 'Invalid or missing document ID.';
    http_response_code(400); // Send Bad Request status
    echo json_encode($response);
    exit;
}

try {
    // --- Track View using Prepared Statement ---
    $sqlTrack = "INSERT IGNORE INTO document_views (pilot_id, doc_id) VALUES (?, ?)";
    $stmtTrack = $mysqli->prepare($sqlTrack);
    if ($stmtTrack) {
        $stmtTrack->bind_param("ii", $userId, $docId);
        if (!$stmtTrack->execute()) {
            error_log("get_document: Failed to track view for doc $docId, user $userId - " . $stmtTrack->error);
            // Log the error, but continue execution
        }
        $stmtTrack->close();
    } else {
        error_log("get_document: Prepare failed (track view) - " . $mysqli->error);
    }
    // --- End Track View ---


    // --- Get document details using Prepared Statement ---
    // LEFT JOINs are safer if creator or category might be NULL/deleted
    $sqlDetails = "SELECT
                       d.id, d.category_id, d.original_filename, d.stored_filename,
                       d.filepath,
                       d.preview_filepath, -- <<< --- ADD THIS LINE --- <<<
                       d.mime_type, d.filesize, d.upload_date,
                       d.creator, d.last_updated, d.is_active, d.description,
                       dc.category AS category_name, -- Get category name
                       CONCAT(u.lastname, ', ', u.firstname) AS creator_name
                   FROM documents d
                   LEFT JOIN document_categories dc ON d.category_id = dc.id
                   LEFT JOIN users u ON u.id = d.creator
                   WHERE d.id = ?"; // Use placeholder

    $stmtDetails = $mysqli->prepare($sqlDetails);
    if (!$stmtDetails) {
        throw new Exception("Prepare failed (get details): " . $mysqli->error);
    }

    $stmtDetails->bind_param("i", $docId); // Bind integer ID
    $stmtDetails->execute();
    $result = $stmtDetails->get_result();

    if ($result && $result->num_rows > 0) {
        $documentData = $result->fetch_assoc();
        $response['success'] = true;
        $response['message'] = 'Document details retrieved.';
        $response['data'] = $documentData; // Put data inside 'data' key as expected by JS
    } else {
        // Document not found or query failed after execute
        $response['message'] = 'Document not found.';
        http_response_code(404); // Send Not Found status
        error_log("get_document: Document not found for ID: " . $docId);
    }
    $stmtDetails->close();
    // --- End Get Details ---

} catch (Exception $e) {
    error_log("get_document: Error processing request for doc $docId - " . $e->getMessage());
    $response['message'] = 'An error occurred while fetching document details.';
    http_response_code(500); // Send Internal Server Error status
    $response['data'] = null; // Ensure data is null on error
}

// Close connection
if (isset($mysqli) && $mysqli) {
    $mysqli->close();
}

// Output final JSON
echo json_encode($response);
exit;
?>