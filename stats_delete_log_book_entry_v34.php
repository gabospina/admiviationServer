<?php
// stats_delete_hour_entry.php

require_once 'stats_api_response.php'; // Use require_once for essential files
require_once 'db_connect.php';      // Use require_once for essential files

// Initialize session and check authentication
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication Check ---
if (!isset($_SESSION["HeliUser"])) {
    $response = new ApiResponse();
    $response->setError("Authentication required")->setSuccess(false);
    http_response_code(401);
    $response->send();
}

// --- Prepare Response Object ---
$response = new ApiResponse();
$stmt = null; // Initialize statement variable

try {
    // --- Validate Input ---
    if (!isset($_POST['pk']) || !filter_var($_POST['pk'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        throw new Exception("Invalid or missing entry ID (pk)");
    }
    $entry_id = (int)$_POST['pk'];
    $user_id = (int)$_SESSION["HeliUser"];

    // --- Database Connection Check ---
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection failed: " . ($mysqli->connect_error ?? 'Unknown error'));
    }

    // --- Prepare DELETE Query ---
    // Ensure user can only delete their own entries
    $query = "DELETE FROM flight_hours WHERE id = ? AND user_id = ?";
    $stmt = $mysqli->prepare($query);

    if (!$stmt) {
        throw new Exception("Prepare failed (delete): (" . $mysqli->errno . ") " . $mysqli->error);
    }

    // --- Bind Parameters ---
    $stmt->bind_param("ii", $entry_id, $user_id);

    // --- Execute Query ---
    if (!$stmt->execute()) {
        throw new Exception("Execute failed (delete): (" . $stmt->errno . ") " . $stmt->error);
    }

    // --- Check if a row was actually deleted ---
    if ($stmt->affected_rows > 0) {
        $response->setSuccess(true);
        $response->setMessage("Log entry deleted successfully.");
    } else {
        // Either ID didn't exist or didn't belong to the user
        throw new Exception("Log entry not found or you do not have permission to delete it.");
        // Or send a non-error response indicating nothing happened:
        // $response->setSuccess(true); // Or false depending on desired frontend behaviour
        // $response->setMessage("Log entry not found or no changes made.");
    }

    // --- Send Success Response ---
    $response->send();

} catch (Exception $e) {
    // Log the error
    if (function_exists('logError')) {
        logError("Error in stats_delete_hour_entry.php: " . $e->getMessage(), ['pk' => $_POST['pk'] ?? null, 'user_id' => $user_id ?? null]);
    } else {
        error_log("Error in stats_delete_hour_entry.php: " . $e->getMessage());
    }

    // Send error response
    http_response_code(500); // Or 400 for bad input, 404 if not found was the specific error
    $response->setError($e->getMessage());
    $response->setSuccess(false);
    $response->send();

} finally {
    // Close statement if prepared
    if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    // Close DB connection if open
    if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) {
        $mysqli->close();
    }
}
?>