<?php
// stats_get_max_times.php - CORRECTED ORDER

// --- The Golden Rule: Start the session FIRST. ---
// This must be the very first piece of executable code.
// No blank lines or spaces before the opening <?php tag.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Now that the session is active, you can include other files.
require_once 'stats_api_response.php';
require_once 'db_connect.php';

// Enable error reporting for debugging (this is fine to have here).
error_reporting(E_ALL);
ini_set('display_errors', 1); 

$response = new ApiResponse();
// DO NOT set the header here yet. Let the ApiResponse class do it at the end.
// header('Content-Type: application/json'); // <-- REMOVE THIS LINE from here.

// --- Get $mysqli connection object ONCE ---
global $mysqli; // If db_connect.php makes it global
// OR call the function if it returns the connection:
// $mysqli = db_connect(); // <<< CALL HERE if needed

$stmt = null; // Initialize for finally block

try {
    // --- Check DB Connection ---
    if (!$mysqli || !($mysqli instanceof mysqli)) {
        throw new Exception("Database connection object not available.", 500);
    }
    if ($mysqli->connect_error) {
        throw new Exception("Database connection failed: " . $mysqli->connect_error, 500);
    }
    error_log("stats_get_max_times: DB Connection OK.");

    // --- Authentication (Optional - Uncomment if needed) ---
    /*
    if (!isset($_SESSION["HeliUser"])) {
        throw new Exception("Authentication required", 401);
    }
    */

    // --- Get company ID (Primarily from Session) ---
    // JS doesn't need to send it if PHP uses the session value
    if (!isset($_SESSION['company_id']) || !filter_var($_SESSION['company_id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        throw new Exception("Valid Company ID not found in session.", 400);
    }
    $company_id = (int)$_SESSION['company_id'];
    error_log("stats_get_max_times: Using company_id from session: " . $company_id);

    // --- Fetch Data ---
    // THE FIX: Select all columns from the table, including the company_id itself.
    // Using `*` is fine here as we want all columns from this specific table.
    $sql = "SELECT * 
            FROM pilot_max_times 
            WHERE company_id = ?
            LIMIT 1";
            
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) { throw new Exception("Prepare failed: ".$mysqli->error, 500); }

    $stmt->bind_param("i", $company_id);

    if (!$stmt->execute()) { throw new Exception("Execute failed: ".$stmt->error, 500); }

    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Row found, return the data
        $data = $result->fetch_assoc();
        error_log("stats_get_max_times: Limits found for company $company_id: " . print_r($data, true));
        $response->setSuccess(true)->setData($data);
    } else {
        // No row found for this company, return defaults
        error_log("stats_get_max_times: No limits found for company $company_id. Returning defaults.");
        // *** IMPORTANT: Keys here MUST match input IDs in daily_manager.php HTML ***
        $default_data = [
            'max_in_day' => 0.0, // Use floats for consistency
            'max_last_7' => 0.0,
            'max_last_28' => 0.0,
            'max_last_365' => 0.0,
            'max_duty_in_day' => 0.0,
            'max_duty_7' => 0.0,
            'max_duty_28' => 0.0,
            'max_duty_365' => 0.0
            // Add 'max_days_in_row' => 0 if needed
        ];
        $response->setSuccess(true)->setData($default_data)->setMessage("No specific limits found, showing defaults."); // Optional message
    }
    $result->free();

} catch (Exception $e) {
    // --- Handle Errors ---
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $response->setSuccess(false)->setError($e->getMessage());
    error_log("Error in " . basename(__FILE__) . " on line " . $e->getLine() . ": [" . $e->getCode() . "] " . $e->getMessage());

} finally {
    // --- Cleanup ---
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    // if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) { $mysqli->close(); } // Close connection?
}

// --- Send Response ---
$response->send();