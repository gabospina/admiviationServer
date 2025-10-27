<?php
// stats_delete_experience.php

require_once 'stats_api_response.php'; // Use require_once
require_once 'db_connect.php';      // Use require_once

// Initialize session and check authentication
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = new ApiResponse(); // Create response object instance
$stmt = null; // Initialize statement variable

// --- Authentication Check ---
if (!isset($_SESSION["HeliUser"])) {
    http_response_code(401);
    $response->setError("Authentication required")->setSuccess(false)->send();
}
$user_id = (int)$_SESSION["HeliUser"];

try {
    // --- Validate Input ---
    // vvv Expect 'craft_type' from JS based on previous standardization vvv
    if (!isset($_POST['craft_type']) || empty($_POST['craft_type'])) {
         throw new Exception("Aircraft type ('craft_type') is required");
    }
    // vvv Sanitize the craft_type input vvv
    // Use filter_input if possible, otherwise sanitize manually
    // $craft_type = filter_input(INPUT_POST, 'craft_type', FILTER_SANITIZE_STRING);
    $craft_type = trim($_POST['craft_type']); // Basic trim for now

    // --- Database Connection Check ---
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection failed: " . ($mysqli->connect_error ?? 'Unknown error'));
    }

    // --- Start transaction (Good practice for DELETE) ---
    $mysqli->begin_transaction();

    // --- Prepare DELETE Query ---
    // vvv Use correct table and column name vvv
    $query = "DELETE FROM pilot_experience WHERE user_id = ? AND craft_type = ?";
    $stmt = $mysqli->prepare($query);

    // vvv Check if prepare failed vvv
    if (!$stmt) {
        // Rollback before throwing exception if transaction started
        $mysqli->rollback();
        throw new Exception("Prepare failed (delete experience): (" . $mysqli->errno . ") " . $mysqli->error . " SQL: " . $query);
    }

    // --- Bind Parameters (integer, string) ---
    $stmt->bind_param("is", $user_id, $craft_type);

    // --- Execute Query ---
    if (!$stmt->execute()) {
         // Rollback before throwing exception
        $mysqli->rollback();
        throw new Exception("Execute failed (delete experience): (" . $stmt->errno . ") " . $stmt->error);
    }

    // --- Check if rows were affected ---
    $affected_rows = $stmt->affected_rows;
    // $stmt->close(); // Close statement here after getting affected rows

    if ($affected_rows > 0) {
        $mysqli->commit(); // Commit transaction if successful
        $response->setSuccess(true)->setMessage("Experience entries for " . htmlspecialchars($craft_type) . " deleted successfully.");
    } else {
        $mysqli->rollback(); // Rollback if no rows were deleted (optional, depends on desired behavior)
        // It's not strictly an error if nothing matched, but maybe the user expects deletion
        $response->setSuccess(false)->setError("No experience entries found for " . htmlspecialchars($craft_type) . " for this user.");
        // Alternatively, send success but indicate no change:
        // $response->setSuccess(true)->setMessage("No matching experience entries found to delete.");
         http_response_code(404); // Not Found might be appropriate if nothing was deleted
    }

    $response->send(); // Send JSON response

} catch (Exception $e) {
    // Rollback transaction on any error during the try block
    if (isset($mysqli) && $mysqli->ping()) { // Check if connection still exists
        $mysqli->rollback();
    }

    // Log the error
    if (function_exists('logError')) {
        logError("Error in stats_delete_experience.php: " . $e->getMessage(), [
            'user_id' => $user_id ?? null,
            'craft_type' => $_POST['craft_type'] ?? null // Log input
        ]);
    } else {
        error_log("Error in stats_delete_experience.php: " . $e->getMessage());
    }

    // Send error response using ApiResponse instance
    http_response_code(500);
    $response->setError("An error occurred while deleting experience: " . $e->getMessage())->setSuccess(false);
    $response->send();

} finally {
    // Cleanup: Close statement ONLY if it's a valid object
     if ($stmt instanceof mysqli_stmt) {
         $stmt->close();
     }
    // Close DB connection if it was successfully opened and still exists
    if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) {
         $mysqli->close();
    }
}
?>