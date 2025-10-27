<?php
// stats_update_max_times.php - DEBUG VERSION

error_reporting(E_ALL);
ini_set('display_errors', 1); // Enable for debugging
ini_set('log_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'stats_api_response.php';
require_once 'db_connect.php';
require_once 'login_csrf_handler.php';

$response = new ApiResponse();
header('Content-Type: application/json');
global $mysqli;

// DEBUG: Log the start
error_log("=== DEBUG stats_update_max_times.php STARTED ===");
error_log("POST data: " . print_r($_POST, true));
error_log("Session company_id: " . ($_SESSION['company_id'] ?? 'NOT SET'));
error_log("Session admin: " . ($_SESSION['admin'] ?? 'NOT SET'));

try {
    // --- 1. Basic Checks ---
    error_log("DEBUG: Step 1 - Basic checks");
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.", 405);
    }
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection failed: " . ($mysqli->connect_error ?? 'Unknown'), 500);
    }

    // --- 2. Authentication & Authorization ---
    error_log("DEBUG: Step 2 - Authentication");
    if (!isset($_SESSION["company_id"]) || !isset($_SESSION["admin"]) || $_SESSION["admin"] < 7) {
        throw new Exception("Permission Denied or Session Invalid. Admin level: " . ($_SESSION['admin'] ?? 'NOT SET'), 403);
    }
    $company_id = (int)$_SESSION['company_id'];
    error_log("DEBUG: Company ID: $company_id, Admin level: " . $_SESSION['admin']);

    // --- 3. CSRF PROTECTION ---
    error_log("DEBUG: Step 3 - CSRF validation");
    
    // ✅ FIXED: Changed from 'csrf_token' to 'form_token'
    $csrf_token = $_POST['form_token'] ?? '';
    error_log("DEBUG: CSRF token received: " . ($csrf_token ? substr($csrf_token, 0, 10) . '...' : 'EMPTY'));
    error_log("DEBUG: Session CSRF token: " . (isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'], 0, 10) . '...' : 'NOT SET'));
    
    if (!CSRFHandler::validateToken($csrf_token)) {
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }
    error_log("DEBUG: CSRF validation passed");

    // --- 4. Whitelist and Input Validation ---
    error_log("DEBUG: Step 4 - Input validation");
    $allowed_columns = [
        'max_in_day', 'max_duty_in_day', 'max_last_7', 'max_duty_7',
        'max_last_28', 'max_duty_28', 'max_last_365', 'max_duty_365'
    ];
    $values_to_set = [];
    
    foreach ($allowed_columns as $col) {
        if (isset($_POST[$col])) {
            $value = filter_var($_POST[$col], FILTER_VALIDATE_FLOAT);
            if ($value === false || $value < 0) {
                 throw new Exception("Invalid value for '$col'. Must be a non-negative number.", 400);
            }
            $values_to_set[$col] = $value;
            error_log("DEBUG: Validated $col = $value");
        }
    }
    
    if (empty($values_to_set)) {
        throw new Exception('No valid values were provided to update.', 400);
    }
    error_log("DEBUG: Values to set: " . print_r($values_to_set, true));

    // --- 5. Check if record exists and handle duplicates ---
    error_log("DEBUG: Step 5 - Check existing record");

    // First, get ALL records for this company to handle duplicates
    $stmt_check_all = $mysqli->prepare("SELECT id FROM pilot_max_times WHERE company_id = ? ORDER BY id ASC");
    if (!$stmt_check_all) {
        throw new Exception('Check all records prepare failed: ' . $mysqli->error, 500);
    }

    $stmt_check_all->bind_param("i", $company_id);
    if (!$stmt_check_all->execute()) {
        throw new Exception('Check all records execute failed: ' . $stmt_check_all->error, 500);
    }

    $result_all = $stmt_check_all->get_result();
    $all_record_ids = [];
    while ($row = $result_all->fetch_assoc()) {
        $all_record_ids[] = $row['id'];
    }
    $stmt_check_all->close();

    error_log("DEBUG: Found records for company $company_id: " . implode(', ', $all_record_ids));

    // Determine which record to use
    if (count($all_record_ids) > 0) {
        // Use the first record (lowest ID) and delete duplicates if any exist
        $rule_set_id = $all_record_ids[0];
        $record_exists = true;
        
        // If there are duplicates, clean them up
        if (count($all_record_ids) > 1) {
            error_log("DEBUG: Found duplicate records. Keeping ID: $rule_set_id, deleting others");
            
            // Delete all except the first record
            $placeholders = implode(',', array_fill(0, count($all_record_ids) - 1, '?'));
            $delete_sql = "DELETE FROM pilot_max_times WHERE company_id = ? AND id IN (" . $placeholders . ")";
            $stmt_delete = $mysqli->prepare($delete_sql);
            
            if ($stmt_delete) {
                $delete_params = array_slice($all_record_ids, 1);
                array_unshift($delete_params, $company_id);
                $delete_types = str_repeat('i', count($delete_params));
                $stmt_delete->bind_param($delete_types, ...$delete_params);
                $stmt_delete->execute();
                $stmt_delete->close();
                error_log("DEBUG: Deleted duplicate records: " . implode(', ', array_slice($all_record_ids, 1)));
            }
        }
    } else {
        // No records exist, we'll create a new one
        $rule_set_id = 1; // Default for new records
        $record_exists = false;
    }

    error_log("DEBUG: Using record ID: $rule_set_id, Record exists: " . ($record_exists ? 'YES' : 'NO'));

    // --- 6. Build and execute query ---
    error_log("DEBUG: Step 6 - Build query");
    $columns = array_keys($values_to_set);
    $types = str_repeat('d', count($columns)); // 'd' for double/float
    $params = array_values($values_to_set);

    if ($record_exists) {
        // UPDATE
        $set_clause = implode(' = ?, ', $columns) . ' = ?';
        $sql = "UPDATE pilot_max_times SET $set_clause WHERE company_id = ? AND id = ?";
        $types .= 'ii';
        $params[] = $company_id;
        $params[] = $rule_set_id;
        error_log("DEBUG: UPDATE SQL: $sql");
    } else {
        // INSERT
        $columns[] = 'company_id';
        $columns[] = 'id';
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO pilot_max_times (" . implode(', ', $columns) . ") VALUES ($placeholders)";
        $types .= 'ii';
        $params[] = $company_id;
        $params[] = $rule_set_id;
        error_log("DEBUG: INSERT SQL: $sql");
    }

    error_log("DEBUG: Types: $types");
    error_log("DEBUG: Params: " . print_r($params, true));

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error, 500);
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error, 500);
    }
    
    $stmt->close();
    error_log("DEBUG: Query executed successfully");

    // --- 7. Success response ---
    error_log("DEBUG: Step 7 - Success response");
    $new_token = CSRFHandler::generateToken();
    $response->setSuccess(true)
             ->setMessage('Limits updated successfully!')
             ->setData(['new_csrf_token' => $new_token]);

} catch (Exception $e) {
    error_log("DEBUG: Exception caught: " . $e->getMessage());
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $response->setSuccess(false)->setError($e->getMessage());
    
    // Send new token on failure
    $new_token = CSRFHandler::generateToken();
    $response->setData(['new_csrf_token' => $new_token]);
}

// --- 8. Send response ---
error_log("DEBUG: Final response: " . json_encode($response));
$response->send();
exit;
?>