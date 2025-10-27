<?php
// delete_document.php

session_start();

ini_set('display_errors', 1); // 0 for Production: Hide errors from user
ini_set('log_errors', 1);    // Log errors to file
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- Database Connection ---
require_once 'db_connect.php';
require_once 'login_csrf_handler.php'; // For token validation
require_once 'login_permissions.php';  // For permission checks

// --- Response Structure ---
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!$mysqli || $mysqli->connect_error) {
    error_log("delete_document: Database connection failed - " . ($mysqli->connect_error ?? 'mysqli object not created'));
    $response['message'] = 'Internal Server Error: Database connection error.';
    http_response_code(500);
    echo json_encode($response);
    exit;
}

// This entire block is wrapped in a try/catch to handle all errors gracefully.
try {
    // 1. Validate the CSRF token to prevent cross-site request forgery.
    CSRFHandler::validateToken($_POST['csrf_token'] ?? '');

    // 2. Check if the user has the required role to perform this action.
    // This is a critical security step. Adjust the roles as needed.
    $rolesThatCanDelete = ['training_manager pilot', 'manager pilot', 'manager', 'admin', 'admin pilot'];
    if (!userHasRole($rolesThatCanDelete, $mysqli)) {
        throw new Exception("You do not have permission to delete documents.", 403);
    }
    
    // 3. Validate the incoming document ID to match the JavaScript.
    $docId = isset($_POST['documentId']) ? filter_var($_POST['documentId'], FILTER_VALIDATE_INT) : null;
    if (!$docId) {
        throw new Exception('Invalid or missing document ID.', 400);
    }
    
    // =========================================================================
    // === END: INTEGRATED SECURITY AND VALIDATION BLOCK                     ===
    // =========================================================================

    // Your existing robust logic starts here. It's already inside the try block.
    $originalFilepath = null;
    $previewFilepath = null;
    $deleteSuccess = false;

    $mysqli->begin_transaction();

    // 1. Get File Paths BEFORE deleting the record
    $sqlGetPaths = "SELECT filepath, preview_filepath FROM documents WHERE id = ?";
    $stmtGetPaths = $mysqli->prepare($sqlGetPaths);
    if (!$stmtGetPaths) { throw new Exception("Prepare failed (get paths): " . $mysqli->error); }
    $stmtGetPaths->bind_param("i", $docId);
    $stmtGetPaths->execute();
    $resultPaths = $stmtGetPaths->get_result();
    if ($row = $resultPaths->fetch_assoc()) {
        $originalFilepath = $row['filepath'];
        $previewFilepath = $row['preview_filepath'];
    } else {
        // Changed this to throw an exception for consistency.
        throw new Exception("Document with ID $docId not found.", 404);
    }
    $stmtGetPaths->close();

    // 2. Delete from document_views table first (foreign key constraint if applicable)
    $sqlDeleteViews = "DELETE FROM document_views WHERE doc_id = ?";
    $stmtDeleteViews = $mysqli->prepare($sqlDeleteViews);
    if (!$stmtDeleteViews) { throw new Exception("Prepare failed (delete views): " . $mysqli->error); }
    $stmtDeleteViews->bind_param("i", $docId);
    if (!$stmtDeleteViews->execute()) {
        // Log error but maybe continue to delete main record? Or rollback? Let's rollback.
        throw new Exception("Execute failed (delete views): " . $stmtDeleteViews->error);
    }
    $affectedViews = $stmtDeleteViews->affected_rows;
    $stmtDeleteViews->close();
    error_log("delete_document: Deleted $affectedViews rows from document_views for doc ID $docId.");


    // 3. Delete the main document record
    $sqlDeleteDoc = "DELETE FROM documents WHERE id = ?";
    $stmtDeleteDoc = $mysqli->prepare($sqlDeleteDoc);
    if (!$stmtDeleteDoc) { throw new Exception("Prepare failed (delete document): " . $mysqli->error); }
    $stmtDeleteDoc->bind_param("i", $docId);
    if ($stmtDeleteDoc->execute()) {
        if ($stmtDeleteDoc->affected_rows > 0) {
            $deleteSuccess = true; // DB record deleted
            error_log("delete_document: Successfully deleted record from documents for ID $docId.");
        } else {
             // Should have been caught by the path check earlier, but safety check
             throw new Exception("Document record not found during delete execute (ID: $docId).");
        }
    } else {
        throw new Exception("Document record not found during delete (ID: $docId). This might occur if another user deleted it just now.");
    }
    $stmtDeleteDoc->close();

    // 4. If DB deletion was successful, commit the transaction
    if ($deleteSuccess) {
        $mysqli->commit();
        error_log("delete_document: DB transaction committed for doc ID $docId.");
    } else {
        // Should not happen if exceptions are working, but safety rollback
        $mysqli->rollback();
        error_log("delete_document: deleteSuccess flag was false after DB operations. Rolling back.");
        $response['message'] = 'Failed to delete document record from database.';
        echo json_encode($response);
        exit;
    }

    // 5. Attempt to delete files AFTER successful DB commit
    $fileDeleteMessages = [];
    if ($deleteSuccess) {
        // Construct absolute paths for unlink (assuming paths stored are relative to web root/script location)
        // Adjust __DIR__ logic if uploads are outside the script's parent directory
        $basePath = __DIR__ . '/'; // Assuming uploads are relative to this script's directory

        if ($originalFilepath && file_exists($basePath . $originalFilepath)) {
            if (unlink($basePath . $originalFilepath)) {
                $fileDeleteMessages[] = "Original file deleted.";
                error_log("delete_document: Successfully deleted original file: " . $basePath . $originalFilepath);
            } else {
                $fileDeleteMessages[] = "Failed to delete original file (check permissions).";
                error_log("delete_document: Failed to delete original file (unlink error): " . $basePath . $originalFilepath);
            }
        } else if ($originalFilepath) {
            $fileDeleteMessages[] = "Original file not found at specified path.";
            error_log("delete_document: Original file path found in DB but file not found on disk: " . $basePath . $originalFilepath);
        }

        if ($previewFilepath && file_exists($basePath . $previewFilepath)) {
            if (unlink($basePath . $previewFilepath)) {
                $fileDeleteMessages[] = "Preview file deleted.";
                error_log("delete_document: Successfully deleted preview file: " . $basePath . $previewFilepath);
            } else {
                $fileDeleteMessages[] = "Failed to delete preview file (check permissions).";
                error_log("delete_document: Failed to delete preview file (unlink error): " . $basePath . $previewFilepath);
            }
        } else if ($previewFilepath) {
             $fileDeleteMessages[] = "Preview file not found at specified path.";
             error_log("delete_document: Preview file path found in DB but file not found on disk: " . $basePath . $previewFilepath);
        }

        $response['success'] = true;
        $response['message'] = 'Document record deleted successfully. ' . implode(' ', $fileDeleteMessages);
    }

} catch (Exception $e) {
    // This single catch block now handles all errors gracefully.
    if ($mysqli->in_transaction) {
        $mysqli->rollback();
    }
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $response['message'] = $e->getMessage();
    error_log("delete_document: Exception caught for doc ID " . ($docId ?? 'N/A') . " - " . $e->getMessage());
}

// Close connection
if (isset($mysqli) && $mysqli) {
    $mysqli->close();
}

// Output final JSON
echo json_encode($response);
exit;
?>