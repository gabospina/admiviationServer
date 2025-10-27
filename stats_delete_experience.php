<?php
// stats_delete_experience.php
// Deletes entries from pilot_initial_experience for a given user and craft_type.

require_once 'stats_api_response.php';
require_once 'db_connect.php'; // Ensures $mysqli is available

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

$apiResponse = new ApiResponse();
header('Content-Type: application/json');
$stmt = null;
$user_id = null; // Initialize for logging in catch block

// --- Authentication Check ---
if (!isset($_SESSION["HeliUser"])) {
    http_response_code(401); // Unauthorized
    $apiResponse->setError("Authentication required. Please log in.")->setSuccess(false)->send();
    // exit; // Critical to exit after send() if ApiResponse doesn't do it.
}
$user_id = (int)$_SESSION["HeliUser"];

error_log("--- stats_delete_experience.php ---");
error_log("Attempting to delete experience for User ID: $user_id, POST Data: " . print_r($_POST, true));


try {
    // --- Validate Input ---
    if (empty($_POST['craft_type'])) { // Check if set and not empty
         throw new Exception("Aircraft type ('craft_type') is required for deletion.", 400);
    }
    $craft_type = trim((string)$_POST['craft_type']);
    if (empty($craft_type)) { // Check again after trim
        throw new Exception("Aircraft type cannot be empty.", 400);
    }
    error_log("Target craft_type for deletion: '$craft_type'");


    // --- Database Connection Check ---
    global $mysqli; // Assuming $mysqli is made global by db_connect.php
    if (!$mysqli || !($mysqli instanceof mysqli) || $mysqli->connect_error) {
        throw new Exception("Database connection error: " . ($mysqli->connect_error ?? "Unknown error"), 500);
    }
    error_log("DB Connection OK for deletion.");

    // --- Start transaction (Good practice for DELETE, though simple here) ---
    if (!$mysqli->begin_transaction()) {
        throw new Exception("Failed to start database transaction.", 500);
    }
    error_log("Transaction started.");

    // --- Prepare DELETE Query ---
    // IMPORTANT: Deleting from pilot_initial_experience table
    $query = "DELETE FROM pilot_initial_experience WHERE user_id = ? AND craft_type = ?";
    $stmt = $mysqli->prepare($query);

    if (!$stmt) {
        $mysqli->rollback(); // Rollback before throwing
        error_log("SQL Prepare failed (delete experience): " . $mysqli->error . " SQL: " . $query);
        throw new Exception("Error preparing delete statement: " . $mysqli->error, 500);
    }
    error_log("SQL statement prepared: $query");

    // --- Bind Parameters (integer for user_id, string for craft_type) ---
    if (!$stmt->bind_param("is", $user_id, $craft_type)) {
        $mysqli->rollback();
        error_log("SQL Bind_param failed (delete experience): " . $stmt->error);
        throw new Exception("Error binding parameters for delete: " . $stmt->error, 500);
    }
    error_log("Parameters bound: user_id=$user_id, craft_type='$craft_type'");

    // --- Execute Query ---
    if (!$stmt->execute()) {
        $mysqli->rollback();
        error_log("SQL Execute failed (delete experience): " . $stmt->error);
        throw new Exception("Error executing delete statement: " . $stmt->error, 500);
    }
    error_log("Delete statement executed.");

    // --- Check if rows were affected ---
    $affected_rows = $stmt->affected_rows;
    error_log("Affected rows by delete: $affected_rows");

    // Statement can be closed after getting affected_rows
    if ($stmt instanceof mysqli_stmt) $stmt->close();
    $stmt = null;


    if ($affected_rows > 0) {
        if (!$mysqli->commit()) { // Commit transaction if successful
            throw new Exception("Failed to commit transaction.", 500);
        }
        error_log("Transaction committed. Deletion successful.");
        $apiResponse->setSuccess(true)->setMessage("Initial experience entries for '" . htmlspecialchars($craft_type) . "' deleted successfully.");
    } else {
        $mysqli->rollback(); // Rollback if no rows were deleted
        error_log("No rows affected. Transaction rolled back. No initial experience found for this craft/user combination.");
        // It's not strictly an error if nothing matched, but the user might expect something to be deleted.
        $apiResponse->setSuccess(false)->setError("No initial experience entries found for '" . htmlspecialchars($craft_type) . "' to delete for this user.");
        // http_response_code(404); // Not Found might be appropriate, but for an action, false success is also clear.
    }

} catch (Exception $e) {
    // Rollback transaction on any error during the try block
    if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->ping() && $mysqli->info === null) { // Check if in transaction
        // $mysqli->rollback(); // mysqli_rollback works on connection, not statement.
        // Check if transaction was started. A bit tricky without a flag.
        // Simpler: just ensure connection is usable if an error occurred very early.
        // If begin_transaction failed, rollback might also error.
        error_log("Exception occurred, attempting rollback if applicable.");
        // $mysqli->rollback(); // Might cause issues if transaction wasn't started or already handled
    }

    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    
    $errorMessage = $e->getMessage();
    error_log("ERROR in " . basename(__FILE__) . " (Line " . $e->getLine() . "): [" . $e->getCode() . "] " . $errorMessage);
    
    $apiResponse->setSuccess(false)->setError("An error occurred: " . $errorMessage);

} finally {
    // Ensure statement is closed if it was prepared and not already closed
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    // Do not close $mysqli here if it's managed globally for the application.
    // If db_connect.php opens/closes per script, it might be closed there.
}

$apiResponse->send();
// exit; // ApiResponse->send() should ideally handle exit if needed.
?>