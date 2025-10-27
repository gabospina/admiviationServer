<?php
// daily_manager_delete_contract.php (CORRECTED)
require_once 'db_connect.php';
header('Content-Type: application/json');
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


// --- ADD CSRF PROTECTION ---
// REMOVED: // REMOVED: require_once 'login_csrf_handler.php';

// --- IMPORTANT: Add your security checks here ---
if (!isset($_SESSION['HeliUser']) /* && !is_manager() */) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied.']);
    exit;
}

// --- CSRF VALIDATION ---
if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        // Token validation failed - regenerate for security
        $response['new_csrf_token'] = CSRFHandler::generateToken();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$contract_id = filter_input(INPUT_POST, 'contract_id', FILTER_VALIDATE_INT);

if (!$contract_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Contract ID.']);
    exit;
}

// Using 'contracts' table and correct primary key 'id'
$stmt = $mysqli->prepare("DELETE FROM contracts WHERE id = ?");
$stmt->bind_param("i", $contract_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Contract not found or already deleted.']);
    }
} else {
    // Foreign key constraint on contract_craft or contract_pilots will trigger this
    if ($mysqli->errno === 1451) {
         echo json_encode(['success' => false, 'message' => 'Cannot delete. This contract has pilots or crafts assigned to it.']);
    } else {
         echo json_encode(['success' => false, 'message' => 'Database error: ' . $mysqli->error]);
    }
}
$stmt->close();
?>