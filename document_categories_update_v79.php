<?php
// document_categories_update.php

// Start session at the VERY TOP
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
include_once 'login_csrf_handler.php';

ini_set('display_errors', 0); // Production
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Initialization failed.', 'new_category_id' => null];

// --- CSRF Protection ---
if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
    $response['message'] = 'Invalid security token. Please refresh the page.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

// --- Security: Check Auth & Admin ---
if (!isset($_SESSION['HeliUser'])) {
    $response['message'] = 'Authentication required.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

$adminUserId = (int)$_SESSION['HeliUser']; // ID of the admin making the change

// --- Validate Inputs ---
$docId = isset($_POST['docid']) ? filter_var($_POST['docid'], FILTER_VALIDATE_INT) : null;
$categoryValue = isset($_POST['category']) ? trim($_POST['category']) : null; // This is EITHER an existing ID (string/number) OR a new category name (string)
$isNew = isset($_POST['isNew']) && ($_POST['isNew'] === 'true' || $_POST['isNew'] === true); // Check if JS sent the 'isNew' flag

if (!$docId || $categoryValue === null || $categoryValue === '') {
    $response['message'] = 'Missing document ID or category value.';
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// --- Database Connection ---
require_once 'db_connect.php';
if (!$mysqli || $mysqli->connect_error) {
    error_log("update_doc_category: DB connection failed - " . ($mysqli->connect_error ?? 'mysqli object not created'));
    $response['message'] = 'Internal Server Error: Database connection error.';
    http_response_code(500);
    echo json_encode($response);
    exit;
}

$finalCategoryId = null; // The ID to actually update the document with

try {
    $mysqli->begin_transaction();

    if ($isNew) {
        // --- Handle NEW Category Name ---
        $newCategoryName = $categoryValue; // The value IS the name
        error_log("update_doc_category: Processing NEW category name: '$newCategoryName' for doc ID $docId");

        // 1. Check if category name already exists (case-insensitive)
        $sqlCheck = "SELECT id FROM document_categories WHERE LOWER(category) = LOWER(?)";
        $stmtCheck = $mysqli->prepare($sqlCheck);
        if (!$stmtCheck) { throw new Exception("Prepare failed (check category): " . $mysqli->error); }
        $lowerNewName = strtolower($newCategoryName);
        $stmtCheck->bind_param("s", $lowerNewName);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            // Category already exists, use its ID
            $existing = $resultCheck->fetch_assoc();
            $finalCategoryId = (int)$existing['id'];
            error_log("update_doc_category: New category name '$newCategoryName' already exists. Using existing ID $finalCategoryId.");
        } else {
            // Category does not exist, create it
            error_log("update_doc_category: Creating new category '$newCategoryName'.");
            $sqlInsert = "INSERT INTO document_categories (category, created_by) VALUES (?, ?)";
            $stmtInsert = $mysqli->prepare($sqlInsert);
            if (!$stmtInsert) { throw new Exception("Prepare failed (insert category): " . $mysqli->error); }
            // Use $adminUserId for created_by (or null if preferred)
            $stmtInsert->bind_param("si", $newCategoryName, $adminUserId);
            if ($stmtInsert->execute()) {
                $finalCategoryId = $stmtInsert->insert_id;
                error_log("update_doc_category: New category created with ID $finalCategoryId.");
            } else {
                throw new Exception("Execute failed (insert category): " . $stmtInsert->error);
            }
            $stmtInsert->close();
        }
        $stmtCheck->close();

    } else {
        // --- Handle EXISTING Category ID ---
        $existingCategoryId = filter_var($categoryValue, FILTER_VALIDATE_INT); // The value IS the ID
        if ($existingCategoryId === false || $existingCategoryId < 0) { // Allow 0 or potential NULL category ID? Check your logic. Assuming >= 1 is valid. Let's allow NULL assignment too.
             if ($categoryValue === null || $categoryValue === '0' || $categoryValue === '') { // Allow unsetting category?
                 $finalCategoryId = null; // Assign NULL to category_id
                 error_log("update_doc_category: Assigning NULL category to doc ID $docId");
             } else {
                throw new Exception("Invalid existing category ID provided: '$categoryValue'");
             }
        } else {
             $finalCategoryId = $existingCategoryId;
             error_log("update_doc_category: Using existing category ID $finalCategoryId for doc ID $docId");
             // Optional: Verify the ID actually exists in document_categories table? Might be overkill.
        }
    }

    // --- Update the Document ---
    if ($finalCategoryId !== false) { // Proceed if we have a valid ID or NULL
        error_log("update_doc_category: Updating document $docId SET category_id = " . ($finalCategoryId === null ? 'NULL' : $finalCategoryId));
        $sqlUpdate = "UPDATE documents SET category_id = ? WHERE id = ?";
        $stmtUpdate = $mysqli->prepare($sqlUpdate);
        if (!$stmtUpdate) { throw new Exception("Prepare failed (update document): " . $mysqli->error); }

        // Bind parameters: 'i' for integer ID, use 's' and send NULL if binding NULL doesn't work directly with 'i'
        // Binding NULL to integer needs care depending on driver/version. Sending it as integer often works.
        if ($finalCategoryId === null) {
            // Option A: Bind as NULL directly if driver supports it (often does)
             $stmtUpdate->bind_param("ii", $finalCategoryId, $docId); // Try binding null to integer first
             // Option B: If above fails, bind as string and send null
             // $nullVar = null;
             // $stmtUpdate->bind_param("si", $nullVar, $docId);
        } else {
            $stmtUpdate->bind_param("ii", $finalCategoryId, $docId); // Bind the integer ID
        }


        if ($stmtUpdate->execute()) {
            if ($stmtUpdate->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Document category updated successfully.';
                $response['new_category_id'] = $finalCategoryId; // Return the ID used/created
                error_log("update_doc_category: Successfully updated category for doc ID $docId.");
            } else {
                // Document ID might not exist OR category was already set to this value
                $response['success'] = true; // Treat as success if no rows affected? Or check if doc exists?
                $response['message'] = 'Document category updated (no change detected or document not found).';
                $response['new_category_id'] = $finalCategoryId;
                error_log("update_doc_category: Update executed but 0 rows affected for doc ID $docId (possibly no change or doc not found).");
            }
        } else {
             throw new Exception("Execute failed (update document): " . $stmtUpdate->error);
        }
        $stmtUpdate->close();

    } else {
         // Should be caught by earlier validation, but safety check
         throw new Exception("Invalid final category ID determined.");
    }

    // --- Commit Transaction ---
    $mysqli->commit();

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("update_doc_category: Exception for doc ID $docId - " . $e->getMessage());
    $response['message'] = 'An error occurred: ' . $e->getMessage();
    http_response_code(500);
}

// Close connection
if (isset($mysqli) && $mysqli) {
    $mysqli->close();
}

// Output final JSON
echo json_encode($response);
exit;
?>