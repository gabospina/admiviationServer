<?php
// training_update_drop_date.php v84 Handles updating an event's start date after a drag-and-drop action.

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

header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'login_permissions.php'; // For role checking
require_once 'permissions.php'; // Include the firewall

$response = ['success' => false, 'message' => 'An unknown error occurred.', 'new_csrf_token' => $_SESSION['csrf_token']];

try {
    // --- SECURITY GUARD CLAUSE ---
    if (!canEditTrainingSchedule()) {
        throw new Exception("Permission denied to modify events.", 403);
    }
    // --- END GUARD CLAUSE ---
    // 1. --- Security and Permission Check ---
    $editorRoles = ['admin', 'manager', 'training manager', 'admin pilot', 'manager pilot', 'training manager pilot'];
    if (!isset($_SESSION["HeliUser"]) || !userHasRole($editorRoles, $mysqli)) {
        throw new Exception("Permission denied.", 403);
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.", 405);
    }

    $company_id = (int)$_SESSION['company_id'];

    // 2. --- Input Validation ---
    $eventId = isset($_POST['eventId']) ? (int)$_POST['eventId'] : 0;
    $newStartDate = $_POST['newStartDate'] ?? null; // Expects YYYY-MM-DD format

    if ($eventId <= 0 || empty($newStartDate) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $newStartDate)) {
        throw new Exception("Invalid event ID or start date provided.", 400);
    }

    // 3. --- Database Update ---
    global $mysqli;
    $sql = "UPDATE training_sim_schedule SET start_date = ?, updated_at = NOW() WHERE id = ? AND company_id = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("Database prepare statement failed.", 500);

    $stmt->bind_param("sii", $newStartDate, $eventId, $company_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = "Event rescheduled successfully.";
        } else {
            throw new Exception("Event not found for this company.", 404);
        }
    } else {
        throw new Exception("Failed to execute database update.", 500);
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>