<?php
// craft_save_order.php v83

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
require_once 'login_permissions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.', 'new_csrf_token' => $_SESSION['csrf_token']];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.", 405);
    }

    $rolesThatCanManage = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    if (!userHasRole($rolesThatCanManage, $mysqli)) {
        throw new Exception("You do not have permission to manage craft order.", 403);
    }
    $company_id = (int)$_SESSION['company_id'];

    $craft_order = $_POST['craft_order'] ?? [];
    if (empty($craft_order) || !is_array($craft_order)) {
        throw new Exception("Invalid or empty order data provided.", 400);
    }

    $mysqli->begin_transaction();

    $stmt = $mysqli->prepare("UPDATE crafts SET display_order = ? WHERE id = ? AND company_id = ?");
    if (!$stmt) {
        throw new Exception("DB Prepare Error: " . $mysqli->error);
    }

    foreach ($craft_order as $index => $craft_id) {
        $order = $index + 1; // 1-based ordering
        $id = (int)$craft_id;
        $stmt->bind_param("iii", $order, $id, $company_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update order for craft ID {$id}: " . $stmt->error);
        }
    }
    $stmt->close();

    $mysqli->commit();

    $response['success'] = true;
    $response['message'] = "Craft display order saved successfully.";

} catch (Exception $e) {
    if ($mysqli->in_transaction) $mysqli->rollback();
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>