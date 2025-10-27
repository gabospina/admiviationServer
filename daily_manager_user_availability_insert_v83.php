<?php
/**
 * File: daily_manager_user_availability_insert.php v83 (SESSION-BASED CSRF)
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'login_permissions.php';
// REMOVE: require_once 'login_csrf_handler.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    // --- SESSION-BASED CSRF VALIDATION ---
    $submitted_token = $_POST['form_token'] ?? ''; // Changed from csrf_token
    
    if (empty($submitted_token)) {
        throw new Exception("Security token missing. Please refresh the page.", 403);
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    // --- Security & Role Validation ---
    $rolesThatCanInsert = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    if (!isset($_SESSION["HeliUser"], $_SESSION['company_id']) || !userHasRole($rolesThatCanInsert, $mysqli)) {
        throw new Exception("Permission Denied.", 403);
    }
    
    $company_id = (int)$_SESSION['company_id'];
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $on_date = $_POST['on_date'] ?? '';
    $off_date = $_POST['off_date'] ?? '';
    
    if ($user_id <= 0) throw new Exception("Invalid User ID provided.", 400);
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $on_date) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $off_date)) {
        throw new Exception("Invalid date format. Use YYYY-MM-DD.", 400);
    }
    if (new DateTime($on_date) >= new DateTime($off_date)) {
        throw new Exception("The 'Off Duty' date must be after the 'On Duty' date.", 400);
    }

    // --- Overlap Check ---
    $stmt_check = $mysqli->prepare("SELECT id FROM user_availability WHERE user_id = ? AND on_date <= ? AND off_date >= ?");
    $stmt_check->bind_param("iss", $user_id, $off_date, $on_date);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        throw new Exception("This date range overlaps with an existing duty period for this pilot.", 409);
    }
    $stmt_check->close();

    // --- Database Insert ---
    $sql = "INSERT INTO user_availability (user_id, company_id, on_date, off_date) VALUES (?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("Database prepare failed: " . $mysqli->error);
    
    $stmt->bind_param("iiss", $user_id, $company_id, $on_date, $off_date);

    if ($stmt->execute()) {
        // Regenerate CSRF token on success
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $response['success'] = true;
        $response['message'] = 'Duty period added successfully.';
        $response['new_csrf_token'] = $_SESSION['csrf_token'];
    } else {
        throw new Exception("Failed to insert new duty period: " . $stmt->error);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage(); // Use 'message' not 'error'
    $response['new_csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    error_log("Error in " . basename(__FILE__) . ": " . $e->getMessage());
}

echo json_encode($response);
?>