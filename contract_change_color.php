<?php
// change_contract_color.php
// --- ADD SESSION START AND CSRF ---
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
require_once "db_connect.php";
// REMOVED: // REMOVED: require_once 'login_csrf_handler.php'; // Add this line
header('Content-Type: application/json');

// --- CSRF VALIDATION ---
if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        // Token validation failed - regenerate for security
        $response['new_csrf_token'] = CSRFHandler::generateToken();
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

if (!isset($_SESSION['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$contractId = (int)$_POST['id'];
$color = $_POST['color'];

// Validate color format
if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
    echo json_encode(['success' => false, 'message' => 'Invalid color format']);
    exit;
}

$stmt = $mysqli->prepare("UPDATE contracts SET color = ? WHERE id = ? AND company_id = ?");
$stmt->bind_param("sii", $color, $contractId, $_SESSION['company_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}