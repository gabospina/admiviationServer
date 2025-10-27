<?php
// hangar_update_validity.php - FINAL DYNAMIC & SECURE VERSION

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'login_csrf_handler.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // --- 1. Security & Authentication ---
    CSRFHandler::validateToken($_POST['csrf_token'] ?? '');
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
    // The date can be empty (to clear it), so we only validate if it's not.
    if (!empty($date_value) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_value)) {
        throw new Exception("Invalid date format. Please use YYYY-MM-DD.", 400);
    }
    // If the date is empty, we should store NULL in the database.
    $dateToStore = !empty($date_value) ? $date_value : null;

    // --- 3. Dynamic Whitelist Security Check ---
    // Get the list of fields the manager has approved for this company.
    $allowedFields = [];
    $stmt_get_fields = $mysqli->prepare("SELECT field_key FROM user_company_licence_fields WHERE company_id = ?");
    if (!$stmt_get_fields) throw new Exception("DB Error preparing to get fields.");
    $stmt_get_fields->bind_param("i", $company_id);
    $stmt_get_fields->execute();
    $result_fields = $stmt_get_fields->get_result();
    while ($row = $result_fields->fetch_assoc()) {
        $allowedFields[] = $row['field_key'];
    }
    $stmt_get_fields->close();

    // Check if the submitted field is in the approved list.
    if (empty($allowedFields) || !in_array($field_key, $allowedFields)) {
        throw new Exception("Invalid or unauthorized validity field specified.", 400);
    }
    // Now that we've validated the field_key, it is SAFE to use in our SQL query.

    // --- 4. Secure Database Update ---
    // The `ON DUPLICATE KEY UPDATE` is perfect for this. It will create a row for the user
    // if one doesn't exist, or update the existing one if it does.
    // The user_id must be a PRIMARY or UNIQUE key in the table for this to work.
    $sqlUpdate = "
        INSERT INTO user_licence_data (user_id, company_id, `{$field_key}`)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE `{$field_key}` = VALUES(`{$field_key}`)
    ";
    $stmt_update = $mysqli->prepare($sqlUpdate);
    if (!$stmt_update) {
        throw new Exception("DB Prepare Error (Update): " . $mysqli->error);
    }
    
    $stmt_update->bind_param("iis", $user_id, $company_id, $dateToStore);
    if (!$stmt_update->execute()) {
        throw new Exception("Failed to save the date to the database.");
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