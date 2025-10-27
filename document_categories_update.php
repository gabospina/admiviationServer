<?php
// document_categories_update.php

// Start session at the VERY TOP
if (session_status() == PHP_SESSION_NONE) {
    session_start();

// --- SESSION-BASED CSRF VALIDATION ---
$submitted_token = $_POST['form_token'] ?? '';

if (empty($submitted_token)) {
    throw new Exception("Security token missing. Please refresh the page.", 403);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    throw new Exception("Invalid security token. Please refresh the page.", 403);
}

}
include_once 'login_csrf_handler.php';

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Initialization failed.', 'new_category_id' => null, 'new_csrf_token' => $_SESSION['csrf_token']];

// --- CSRF Protection ---
if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        // Token validation failed - regenerate for security
        $response['new_csrf_token'] = CSRFHandler::generateToken();
    $response['message'] = 'Invalid security token. Please refresh the page.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

// --- Security: Check Auth & Company ---
if (!isset($_SESSION['HeliUser']) || !isset($_SESSION['company_id'])) {
    $response['message'] = 'Authentication required.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

$adminUserId = (int)$_SESSION['HeliUser'];
$company_id = (int)$_SESSION['company_id']; // === ADDED: Get company_id ===

// --- Validate Inputs ---
$docId = isset($_POST['docid']) ? filter_var($_POST['docid'], FILTER_VALIDATE_INT) : null;
$categoryValue = isset($_POST['category']) ? trim($_POST['category']) : null;
$isNew = isset($_POST['isNew']) && ($_POST['isNew'] === 'true' || $_POST['isNew'] === true);

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

$finalCategoryId = null;

try {
    $mysqli->begin_transaction();

    // === FIX: VERIFY DOCUMENT BELONGS TO USER'S COMPANY ===
    $sqlVerifyDoc = "SELECT id FROM documents WHERE id = ? AND company_id = ?";
    $stmtVerifyDoc = $mysqli->prepare($sqlVerifyDoc);
    if (!$stmtVerifyDoc) { throw new Exception("Prepare failed (verify document): " . $mysqli->error); }
    $stmtVerifyDoc->bind_param("ii", $docId, $company_id);
    $stmtVerifyDoc->execute();
    $resultVerifyDoc = $stmtVerifyDoc->get_result();
    
    if ($resultVerifyDoc->num_rows === 0) {
        throw new Exception("Document not found or access denied.");
    }
    $stmtVerifyDoc->close();

    if ($isNew) {
        // --- Handle NEW Category Name ---
        $newCategoryName = $categoryValue;
        error_log("update_doc_category: Processing NEW category name: '$newCategoryName' for doc ID $docId, company $company_id");

        // === FIX: ADD COMPANY_ID TO CATEGORY CHECK ===
        $sqlCheck = "SELECT id FROM document_categories WHERE LOWER(category) = LOWER(?) AND company_id = ?";
        $stmtCheck = $mysqli->prepare($sqlCheck);
        if (!$stmtCheck) { throw new Exception("Prepare failed (check category): " . $mysqli->error); }
        $lowerNewName = strtolower($newCategoryName);
        $stmtCheck->bind_param("si", $lowerNewName, $company_id);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            $existing = $resultCheck->fetch_assoc();
            $finalCategoryId = (int)$existing['id'];
            error_log("update_doc_category: New category name '$newCategoryName' already exists. Using existing ID $finalCategoryId.");
        } else {
            // === FIX: ADD COMPANY_ID TO CATEGORY INSERT ===
            error_log("update_doc_category: Creating new category '$newCategoryName' for company $company_id.");
            $sqlInsert = "INSERT INTO document_categories (category, created_by, company_id) VALUES (?, ?, ?)";
            $stmtInsert = $mysqli->prepare($sqlInsert);
            if (!$stmtInsert) { throw new Exception("Prepare failed (insert category): " . $mysqli->error); }
            $stmtInsert->bind_param("sii", $newCategoryName, $adminUserId, $company_id);
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
        $existingCategoryId = filter_var($categoryValue, FILTER_VALIDATE_INT);
        if ($existingCategoryId === false || $existingCategoryId < 0) {
            if ($categoryValue === null || $categoryValue === '0' || $categoryValue === '') {
                $finalCategoryId = null;
                error_log("update_doc_category: Assigning NULL category to doc ID $docId");
            } else {
                throw new Exception("Invalid existing category ID provided: '$categoryValue'");
            }
        } else {
            // === FIX: VERIFY CATEGORY BELONGS TO USER'S COMPANY ===
            $sqlVerifyCat = "SELECT id FROM document_categories WHERE id = ? AND company_id = ?";
            $stmtVerifyCat = $mysqli->prepare($sqlVerifyCat);
            if (!$stmtVerifyCat) { throw new Exception("Prepare failed (verify category): " . $mysqli->error); }
            $stmtVerifyCat->bind_param("ii", $existingCategoryId, $company_id);
            $stmtVerifyCat->execute();
            $resultVerifyCat = $stmtVerifyCat->get_result();
            
            if ($resultVerifyCat->num_rows === 0) {
                throw new Exception("Category not found or access denied.");
            }
            $stmtVerifyCat->close();
            
            $finalCategoryId = $existingCategoryId;
            error_log("update_doc_category: Using existing category ID $finalCategoryId for doc ID $docId");
        }
    }

    // --- Update the Document ---
    if ($finalCategoryId !== false) {
        error_log("update_doc_category: Updating document $docId SET category_id = " . ($finalCategoryId === null ? 'NULL' : $finalCategoryId));
        $sqlUpdate = "UPDATE documents SET category_id = ? WHERE id = ? AND company_id = ?"; // === FIX: ADD COMPANY_ID TO UPDATE ===
        $stmtUpdate = $mysqli->prepare($sqlUpdate);
        if (!$stmtUpdate) { throw new Exception("Prepare failed (update document): " . $mysqli->error); }

        if ($finalCategoryId === null) {
            $stmtUpdate->bind_param("iii", $finalCategoryId, $docId, $company_id);
        } else {
            $stmtUpdate->bind_param("iii", $finalCategoryId, $docId, $company_id);
        }

        if ($stmtUpdate->execute()) {
            if ($stmtUpdate->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Document category updated successfully.';
                $response['new_category_id'] = $finalCategoryId;
                error_log("update_doc_category: Successfully updated category for doc ID $docId.");
            } else {
                $response['success'] = true;
                $response['message'] = 'Document category updated (no change detected or document not found).';
                $response['new_category_id'] = $finalCategoryId;
                error_log("update_doc_category: Update executed but 0 rows affected for doc ID $docId");
            }
        } else {
            throw new Exception("Execute failed (update document): " . $stmtUpdate->error);
        }
        $stmtUpdate->close();
    } else {
        throw new Exception("Invalid final category ID determined.");
    }

    $mysqli->commit();

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("update_doc_category: Exception for doc ID $docId - " . $e->getMessage());
    $response['message'] = 'An error occurred: ' . $e->getMessage();
    http_response_code(500);
}

if (isset($mysqli) && $mysqli) {
    $mysqli->close();
}

echo json_encode($response);
exit;
?>