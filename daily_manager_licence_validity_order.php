<?php
// daily_manager_licence_validity_order.php v83

session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';
// REMOVED: // REMOVED: // REMOVED: // REMOVED: require_once 'login_csrf_handler.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request method.", 405);

    $rolesThatCanManage = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    if (!userHasRole($rolesThatCanManage, $mysqli)) {
        throw new Exception("You do not have permission to manage licence fields.", 403);
    }
    $company_id = (int)$_SESSION['company_id'];
    
    // Get the ordered array of field IDs from the JavaScript
    $fieldOrder = $_POST['field_order'] ?? [];
    if (empty($fieldOrder) || !is_array($fieldOrder)) {
        throw new Exception("No field order data received.", 400);
    }

    $mysqli->begin_transaction();

    // Loop through the received array and update the display_order for each field
    // We multiply by 10 to leave space for future manual insertions if ever needed
    $order = 10;
    $stmt = $mysqli->prepare(
        "UPDATE user_company_licence_fields SET display_order = ? WHERE id = ? AND company_id = ?"
    );
    if (!$stmt) throw new Exception("DB Prepare Error: " . $mysqli->error);

    foreach ($fieldOrder as $field_id) {
        $stmt->bind_param("iii", $order, $field_id, $company_id);
        $stmt->execute();
        $order += 10;
    }
    $stmt->close();

    $mysqli->commit();

    // Regenerate CSRF token on success
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    $response['success'] = true;
    $response['message'] = "Operation completed successfully.";
    $response['new_csrf_token'] = $_SESSION['csrf_token'];

} catch (Exception $e) {
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
    $response['new_csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
}

echo json_encode($response);
?>