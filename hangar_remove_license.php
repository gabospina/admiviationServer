<?php
// hangar_remove_license.php - REFACTORED FOR NEW DATABASE STRUCTURE

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

header('Content-Type: application/json');
require_once 'db_connect.php';
// REMOVED: // REMOVED: require_once 'login_csrf_handler.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.', 'new_csrf_token' => $_SESSION['csrf_token']];

try {
    // --- Security Checks ---
    // REMOVED: CSRFHandler::validateToken
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("Authentication required.", 401);
    }
    // FIX: Get the user ID from the session. No need to trust the client to send it.
    $user_id = (int)$_SESSION['HeliUser'];
    $company_id = (int)$_SESSION['company_id'];

    // --- Input Validation ---
    $field_key = trim($_POST['validityField'] ?? ''); // This is the field key, e.g., 'passport'
    if (empty($field_key)) {
        throw new Exception("Invalid validity field specified.", 400);
    }

    // --- Whitelist Security Check (Still essential) ---
    $stmt_check = $mysqli->prepare("SELECT id FROM user_company_licence_fields WHERE company_id = ? AND field_key = ?");
    if (!$stmt_check) throw new Exception("DB Error preparing to validate field.");
    $stmt_check->bind_param("is", $company_id, $field_key);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows === 0) {
        throw new Exception("Invalid or unauthorized validity field specified.", 400);
    }
    $stmt_check->close();

    // --- REFACTORED Database Operations ---
    $mysqli->begin_transaction();

    // 1. Get the path of the document to delete it from the server.
    $sqlSelect = "SELECT document_path FROM user_licence_data WHERE user_id = ? AND field_key = ?";
    $stmt_select = $mysqli->prepare($sqlSelect);
    if(!$stmt_select) throw new Exception("DB Prepare Error (Select): ".$mysqli->error);
    $stmt_select->bind_param("is", $user_id, $field_key);
    $stmt_select->execute();
    $oldPathResult = $stmt_select->get_result();
    $oldPath = null;
    if ($oldPathRow = $oldPathResult->fetch_assoc()) {
        $oldPath = $oldPathRow['document_path'];
    }
    $stmt_select->close();

    // 2. Update the database to set the document path to NULL for this specific user and license.
    $sqlUpdate = "UPDATE user_licence_data SET document_path = NULL WHERE user_id = ? AND field_key = ?";
    $stmt_update = $mysqli->prepare($sqlUpdate);
    if(!$stmt_update) throw new Exception("DB Prepare Error (Update): ".$mysqli->error);
    $stmt_update->bind_param("is", $user_id, $field_key);
    if (!$stmt_update->execute()) {
        throw new Exception("Failed to update the database record.");
    }
    $stmt_update->close();
    
    // 3. If DB update was successful, commit and then delete the physical file.
    $mysqli->commit();

    if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
        unlink(__DIR__ . '/' . $oldPath);
    }

    $response['success'] = true;
    $response['message'] = 'Document removed successfully.';

} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->in_transaction) {
        $mysqli->rollback();
    }
    $response['error'] = $e->getMessage();
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
}

echo json_encode($response);
?>