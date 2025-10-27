<?php
// hangar_update_validity.php - REFACTORED FOR NEW DATABASE STRUCTURE

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
    // --- 1. Security & Authentication ---
    // REMOVED: CSRFHandler::validateToken
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("Authentication required.", 401);
    }
    // The user being updated is the one logged in.
    $user_id = (int)$_SESSION['HeliUser'];
    $company_id = (int)$_SESSION['company_id'];

    // --- 2. Input Validation ---
    $field_key = trim($_POST['field'] ?? '');
    $date_value = trim($_POST['value'] ?? '');
    
    if (empty($field_key)) {
        throw new Exception("The 'field' identifier is missing.", 400);
    }
    // Date format validation.
    if (!empty($date_value) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_value)) {
        throw new Exception("Invalid date format. Please use YYYY-MM-DD.", 400);
    }
    // If the date is empty, we store NULL in the database to clear it.
    $dateToStore = !empty($date_value) ? $date_value : null;

    // --- 3. Whitelist Security Check (This logic is still excellent and remains) ---
    // We must ensure the field_key being submitted is one that the company actually uses.
    $stmt_check = $mysqli->prepare("SELECT id FROM user_company_licence_fields WHERE company_id = ? AND field_key = ?");
    if (!$stmt_check) throw new Exception("DB Error preparing to validate field.");
    $stmt_check->bind_param("is", $company_id, $field_key);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows === 0) {
        throw new Exception("Invalid or unauthorized validity field specified.", 400);
    }
    $stmt_check->close();

    // --- 4. REFACTORED Database Update ---
    // EXPLANATION: This is the new, correct query. It uses the powerful `ON DUPLICATE KEY UPDATE`
    // with our new table structure. The `UNIQUE KEY` on `(user_id, company_id, field_key)`
    // is what makes this command work its magic.
    
    $sqlUpdate = "
        INSERT INTO user_licence_data (user_id, company_id, field_key, expiry_date)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE expiry_date = VALUES(expiry_date)
    ";
    
    $stmt_update = $mysqli->prepare($sqlUpdate);
    if (!$stmt_update) {
        throw new Exception("DB Prepare Error (Update): ". $mysqli->error);
    }
    
    // The bind parameters now match the new query structure.
    $stmt_update->bind_param("iiss", $user_id, $company_id, $field_key, $dateToStore);
    
    if (!$stmt_update->execute()) {
        throw new Exception("Failed to save the date to the database: " . $stmt_update->error);
    }
    $stmt_update->close();
    
    $response['success'] = true;
    $response['message'] = 'Date saved successfully.';

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
}

echo json_encode($response);
?>