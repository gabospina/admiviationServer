<?php
// stats_update_hour_entry.php - v83 SECURE VERSION

require_once 'stats_api_response.php';
require_once 'db_connect.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

$response = new ApiResponse();
header('Content-Type: application/json');
$stmt = null;

try {
    // --- Authentication Check ---
    if (!isset($_SESSION["HeliUser"])) {
        throw new Exception("Authentication required.", 401);
    }
    $user_id = (int)$_SESSION["HeliUser"];

    // --- Validate Input ---
    if (!isset($_POST['pk']) || !filter_var($_POST['pk'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        throw new Exception("Invalid or missing entry ID.", 400);
    }
    
    if (!isset($_POST['name']) || !isset($_POST['value'])) {
        throw new Exception("Missing required parameters.", 400);
    }

    $pk = (int)$_POST['pk'];
    $field_name = $_POST['name'];
    $field_value = $_POST['value'];

    // --- Validate Allowed Fields (Prevent SQL Injection) ---
    $allowed_fields = [
        'date', 'craft_type', 'registration', 'PIC', 'SIC', 'route',
        'ifr', 'vfr', 'hours', 'night_time', 'hour_type'
    ];
    
    if (!in_array($field_name, $allowed_fields)) {
        throw new Exception("Invalid field name.", 400);
    }

    // --- Validate User Ownership ---
    $checkQuery = "SELECT id FROM pilot_log_book WHERE id = ? AND user_id = ?";
    $checkStmt = $mysqli->prepare($checkQuery);
    if (!$checkStmt) {
        throw new Exception("Database preparation failed.", 500);
    }
    
    $checkStmt->bind_param("ii", $pk, $user_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Unauthorized access or entry not found.", 403);
    }
    $checkStmt->close();

    // --- Additional Field-Specific Validation ---
    if (in_array($field_name, ['ifr', 'vfr', 'hours', 'night_time'])) {
        $numeric_value = filter_var($field_value, FILTER_VALIDATE_FLOAT);
        if ($numeric_value === false || $numeric_value < 0) {
            throw new Exception("Invalid value for numeric field.", 400);
        }
        $field_value = $numeric_value;
    }

    // Date validation
    if ($field_name === 'date') {
        $date_object = DateTime::createFromFormat('Y-m-d', $field_value);
        if (!$date_object || $date_object->format('Y-m-d') !== $field_value) {
            throw new Exception("Invalid date format. Expected YYYY-MM-DD.", 400);
        }
    }

    // --- Update the Entry ---
    $updateQuery = "UPDATE pilot_log_book SET $field_name = ? WHERE id = ? AND user_id = ?";
    $stmt = $mysqli->prepare($updateQuery);
    if (!$stmt) {
        throw new Exception("Update preparation failed: " . $mysqli->error, 500);
    }

    // Determine parameter type
    $param_type = in_array($field_name, ['ifr', 'vfr', 'hours', 'night_time']) ? 'd' : 's';
    $stmt->bind_param("{$param_type}ii", $field_value, $pk, $user_id);

    if (!$stmt->execute()) {
        throw new Exception("Update failed: " . $stmt->error, 500);
    }

    $response->setSuccess(true)->setMessage("Update successful");

} catch (Exception $e) {
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
    http_response_code($httpStatusCode);
    $response->setSuccess(false)->setError($e->getMessage());
    error_log("Error in stats_update_hour_entry.php: " . $e->getMessage());
} finally {
    if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($checkStmt) && $checkStmt instanceof mysqli_stmt) {
        $checkStmt->close();
    }
}

$response->send();
exit;
?>