<?php
// daily_manager_delete_licence_validity.php - REFACTORED

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
// REMOVED: // REMOVED: // REMOVED: require_once 'login_csrf_handler.php';
require_once 'login_permissions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.', 'new_csrf_token' => $_SESSION['csrf_token']];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.", 405);
    }
    // REMOVED: CSRFHandler::validateToken

    $rolesThatCanManage = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    if (!userHasRole($rolesThatCanManage, $mysqli)) {
        throw new Exception("You do not have permission to manage licence fields.", 403);
    }
    $company_id = (int)$_SESSION['company_id'];

    $field_id = filter_input(INPUT_POST, 'field_id', FILTER_VALIDATE_INT);
    if (!$field_id) {
        throw new Exception("Invalid Field ID provided.", 400);
    }

    $mysqli->begin_transaction();

    // 1. Get the 'field_key' from our definitions table before we delete the row.
    // This is important for cleaning up the associated data.
    $stmtGet = $mysqli->prepare("SELECT field_key FROM user_company_licence_fields WHERE id = ? AND company_id = ?");
    if (!$stmtGet) {
        throw new Exception("DB Prepare Error (Get Key): " . $mysqli->error);
    }
    $stmtGet->bind_param("ii", $field_id, $company_id);
    $stmtGet->execute();
    $result = $stmtGet->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Field not found or you do not have permission to delete it.", 404);
    }
    $row = $result->fetch_assoc();
    $field_key = $row['field_key'];
    $stmtGet->close();

    // --- REFACTORED LOGIC ---
    // EXPLANATION: The dangerous `ALTER TABLE` command has been removed.
    // We now perform two safe DELETE operations.

    // 2. Delete all pilot data associated with this field_key for this company.
    // This is a critical cleanup step to prevent orphaned data in our new flexible table.
    $stmtDeleteData = $mysqli->prepare("DELETE FROM user_licence_data WHERE company_id = ? AND field_key = ?");
    if (!$stmtDeleteData) {
        throw new Exception("DB Prepare Error (Delete Data): " . $mysqli->error);
    }
    $stmtDeleteData->bind_param("is", $company_id, $field_key);
    $stmtDeleteData->execute();
    $stmtDeleteData->close();

    // 3. Delete the field definition itself from the definitions table.
    $stmtDeleteDef = $mysqli->prepare("DELETE FROM user_company_licence_fields WHERE id = ? AND company_id = ?");
    if (!$stmtDeleteDef) {
        throw new Exception("DB Prepare Error (Delete Definition): " . $mysqli->error);
    }
    $stmtDeleteDef->bind_param("ii", $field_id, $company_id);
    $stmtDeleteDef->execute();
    $stmtDeleteDef->close();
    
    $mysqli->commit();

    $response['success'] = true;
    $response['message'] = "Field '{$field_key}' and all its associated data were deleted successfully.";

} catch (Exception $e) {
    if ($mysqli->in_transaction) {
        $mysqli->rollback();
    }
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>