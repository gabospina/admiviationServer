<?php
// stats_update_max_times.php (FINAL INTEGRATED VERSION)

// --- Error Logging & Setup ---
error_log("--- RAW POST in stats_update_max_times ---");
error_log(file_get_contents('php://input'));
error_log("--- \$_POST array ---");
error_log(print_r($_POST, true));
error_log("--- End Initial POST Log ---");

error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 0 in production

require_once 'stats_api_response.php';
require_once 'db_connect.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = new ApiResponse();
header('Content-Type: application/json');
global $mysqli;

$stmt = null;

try {
    // --- 1. Check Database Connection (This section is unchanged) ---
    if (!$mysqli || !($mysqli instanceof mysqli) || $mysqli->connect_error) {
        throw new Exception("Database connection failed.", 500);
    }

    // --- 2. Authentication & Authorization (This section is unchanged) ---
    if (!isset($_SESSION["company_id"]) || !isset($_SESSION["admin"]) || $_SESSION["admin"] < 7) {
        throw new Exception("Permission Denied or Session Invalid.", 403);
    }
    $company_id = (int)$_SESSION['company_id'];

    // --- 3. Whitelist of Allowed Columns (This section is unchanged) ---
    $allowed_columns = [
        'max_in_day', 'max_duty_in_day', 'max_last_7', 'max_duty_7',
        'max_last_28', 'max_duty_28', 'max_last_365', 'max_duty_365'
    ];

    // =========================================================================
    // === START: THIS IS THE NEW, SIMPLIFIED LOGIC TO REPLACE THE OLD CODE  ===
    // =========================================================================

    // --- 4. Collect and Validate Submitted Data ---
    $values_to_set = []; // Will hold validated key-value pairs for the query

    foreach ($allowed_columns as $col) {
        if (isset($_POST[$col])) {
            $value = filter_var($_POST[$col], FILTER_VALIDATE_FLOAT);
            if ($value === false || $value < 0) {
                 throw new Exception("Invalid value for '$col'. Must be a non-negative number.", 400);
            }
            $values_to_set[$col] = $value;
        }
    }

    // --- 5. Check if Any Valid Data Was Submitted ---
    if (empty($values_to_set)) {
        throw new Exception('No valid values were provided to update.', 400);
    }
    
    // Always include the company_id for the query
    $values_to_set['company_id'] = $company_id;

    // --- 6. Build the single, powerful INSERT ... ON DUPLICATE KEY UPDATE query ---
    $columns = array_keys($values_to_set);
    $placeholders = array_fill(0, count($columns), '?');
    $updates_on_duplicate = [];
    
    $final_params = [];
    $final_types = '';

    foreach ($columns as $col) {
        // Add the value to our parameter array
        $final_params[] = $values_to_set[$col];
        // Determine the bind type ('i' for company_id, 'd' for all others)
        $final_types .= ($col === 'company_id') ? 'i' : 'd';
        
        // Build the "UPDATE" part for the ON DUPLICATE KEY clause
        // We do not want to update the company_id itself, only the other values
        if ($col !== 'company_id') {
            $updates_on_duplicate[] = "`$col` = VALUES(`$col`)";
        }
    }
    
    // Construct the final SQL string using sprintf for clarity
    $sql = sprintf(
        "INSERT INTO pilot_max_times (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s",
        implode(', ', array_map(function($c) { return "`$c`"; }, $columns)),
        implode(', ', $placeholders),
        implode(', ', $updates_on_duplicate)
    );

    error_log("Executing SQL: " . $sql);
    error_log("With Bind Types: " . $final_types);
    error_log("With Bind Values: " . print_r($final_params, true));

    // --- 7. Prepare and Execute the Final Query ---
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception('Database prepare failed: ' . $mysqli->error, 500);
    
    if (!$stmt->bind_param($final_types, ...$final_params)) {
        throw new Exception("Database bind_param failed: " . $stmt->error, 500);
    }
    
    if (!$stmt->execute()) throw new Exception('Database execute failed: ' . $stmt->error, 500);
    
    error_log("SQL Execute successful. Affected rows: " . $stmt->affected_rows);

    // =========================================================================
    // === END: END OF THE NEW, SIMPLIFIED LOGIC                             ===
    // =========================================================================

    // --- 8. Set Success Response (This section is unchanged) ---
    $response->setSuccess(true)->setMessage('Limits updated successfully!');

} catch (Exception $e) {
    // --- Handle ALL Exceptions (This section is unchanged) ---
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $response->setSuccess(false)->setError($e->getMessage());
    error_log("Error in " . basename(__FILE__) . " on line " . $e->getLine() . ": [" . $e->getCode() . "] " . $e->getMessage());

} finally {
    if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
}

// --- 9. Send JSON Response (This section is unchanged) ---
$response->send();
exit;