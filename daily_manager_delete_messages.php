<?php
/**
 * File: daily_manager_delete_messages.php (UPDATED)
 * Now handles pre-formatted composite IDs directly
 */
if (session_status() == PHP_SESSION_NONE) { session_start();

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
header('Content-Type: application/json');
include_once "db_connect.php";
require_once 'login_permissions.php';

$response = ['success' => false, 'new_csrf_token' => $_SESSION['csrf_token']];

$rolesThatCanDelete = ['manager', 'admin', 'manager pilot', 'admin pilot'];
if (!isset($_SESSION["HeliUser"]) || !userHasRole($rolesThatCanDelete, $mysqli)) {
    $response['error'] = 'Permission Denied.';
    http_response_code(403);
    echo json_encode($response);
    exit();
}

$composite_ids = $_POST['log_ids'] ?? [];

if (empty($composite_ids) || !is_array($composite_ids)) {
    $response['error'] = 'No message log entries selected.';
    http_response_code(400);
    echo json_encode($response);
    exit();
}

$assignment_ids_to_delete = [];
$direct_ids_to_delete = [];

// Process the composite IDs that are already formatted correctly
foreach ($composite_ids as $cid) {
    // Skip invalid or undefined values
    if (empty($cid) || $cid === 'undefined' || strpos($cid, 'undefined') !== false) {
        continue;
    }
    
    $parts = explode('-', $cid, 2);
    if (count($parts) === 2) {
        $type = $parts[0];
        $id = (int)$parts[1];
        if ($id >= 0) { // Changed to >= 0 to allow ID 0
            if ($type === 'assignment') {
                $assignment_ids_to_delete[] = $id;
            } elseif ($type === 'direct') {
                $direct_ids_to_delete[] = $id;
            }
        }
    }
}

$total_deleted = 0;
$mysqli->begin_transaction();

try {
    // Delete from the assignment log table
    if (!empty($assignment_ids_to_delete)) {
        $placeholders = implode(',', array_fill(0, count($assignment_ids_to_delete), '?'));
        $sql = "DELETE FROM sms_notifications_log WHERE id IN ($placeholders)";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed (assignments): " . $mysqli->error);
        $stmt->bind_param(str_repeat('i', count($assignment_ids_to_delete)), ...$assignment_ids_to_delete);
        $stmt->execute();
        $total_deleted += $stmt->affected_rows;
        $stmt->close();
    }

    // Delete from the direct sms log table
    if (!empty($direct_ids_to_delete)) {
        $placeholders = implode(',', array_fill(0, count($direct_ids_to_delete), '?'));
        $sql = "DELETE FROM sms_direct_log WHERE id IN ($placeholders)";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed (direct): " . $mysqli->error);
        $stmt->bind_param(str_repeat('i', count($direct_ids_to_delete)), ...$direct_ids_to_delete);
        $stmt->execute();
        $total_deleted += $stmt->affected_rows;
        $stmt->close();
    }

    $mysqli->commit();
    $response['success'] = true;
    $response['message'] = "Successfully deleted " . $total_deleted . " message log(s).";

} catch (Exception $e) {
    $mysqli->rollback();
    error_log("Error deleting message logs: " . $e->getMessage());
    $response['error'] = 'An error occurred during deletion.';
    http_response_code(500);
}

echo json_encode($response);
?>