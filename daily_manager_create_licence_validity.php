<?php
// daily_manager_create_licence_validity.php - REFACTORED

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
    
    // Validate inputs
    $field_label = trim($_POST['field_label'] ?? '');
    $field_key = trim($_POST['field_key'] ?? '');

    if (empty($field_label) || empty($field_key)) {
        throw new Exception("Both Field Label and Field Key are required.", 400);
    }
    // Enforce naming convention for the key (security)
    if (!preg_match('/^[a-z0-9_]+$/', $field_key)) {
        throw new Exception("Field Key must contain only lowercase letters, numbers, and underscores.", 400);
    }

    // --- REFACTORED LOGIC ---
    // EXPLANATION: The dangerous and error-prone `ALTER TABLE` command has been completely removed.
    // The only action required is to insert a new definition into our fields table.
    // The database is now flexible and no longer needs structural changes to add new fields.
    
    $insertSql = "INSERT INTO user_company_licence_fields (company_id, field_key, field_label) VALUES (?, ?, ?)";
    $stmt = $mysqli->prepare($insertSql);
    if (!$stmt) {
        throw new Exception("DB Prepare Error: " . $mysqli->error);
    }
    
    $stmt->bind_param("iss", $company_id, $field_key, $field_label);
    
    if (!$stmt->execute()) {
        // We have a UNIQUE key on (company_id, field_key), so we can check for duplicate errors.
        if ($mysqli->errno === 1062) {
            throw new Exception("This Field Key already exists for your company. Please choose a unique key.");
        }
        throw new Exception("DB Execute Error: " . $stmt->error);
    }
    $stmt->close();

    $response['success'] = true;
    $response['message'] = "New field '{$field_label}' was added successfully.";

} catch (Exception $e) {
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>