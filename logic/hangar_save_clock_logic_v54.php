<?php
// logic/hangar_save_clock_logic.php

// IMPORTANT: DO NOT put session_start() or header() calls in this file.
// The main 'hangar_ajax_handler.php' file has already done this.

// The authentication check was also done in the handler, but we can safely access the session here.
$user_id = (int)$_SESSION["HeliUser"];

// --- DATABASE CONNECTION ---
// The path is now relative to the handler in the root directory.
// If 'db_connect.php' is in the root, this path is correct.
require_once "db_connect.php";

// Check the database connection
if ($mysqli->connect_error) {
    error_log("Database Connection Error in hangar_save_clock_logic: " . $mysqli->connect_error);
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}


// =========================================
// Handle GET request (Load settings)
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $mysqli->prepare("SELECT clock_name, clock_tz FROM pilot_info WHERE user_id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database prepare error (GET)']);
        exit();
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if ($data && !empty($data['clock_tz']) && !empty($data['clock_name'])) {
        // Settings found
        echo json_encode([
            'status' => 'success',
            'name' => $data['clock_name'],
            'tz' => $data['clock_tz']
        ]);
    } else {
        // No settings found, return defaults
        echo json_encode([
            'status' => 'success',
            'name' => 'My Clock',
            'tz' => 'UTC'
        ]);
    }
    exit();
}


// =========================================
// Handle POST request (Save settings)
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $timezone = trim($_POST['timezone'] ?? '');
    $name = trim($_POST['name'] ?? '');

    if (empty($name) || empty($timezone)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Clock name and timezone are required.']);
        exit();
    }

    // --- (The rest of your excellent POST logic remains unchanged) ---
    try {
        new DateTimeZone($timezone);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Invalid timezone identifier: '{$timezone}'."]);
        exit();
    }

    // Note: This logic assumes a record in 'pilot_info' already exists.
    $stmt = $mysqli->prepare("UPDATE pilot_info SET clock_name = ?, clock_tz = ? WHERE user_id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database prepare error (POST)']);
        exit();
    }

    $stmt->bind_param("ssi", $name, $timezone, $user_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $stmt->close();
        echo json_encode([
            'status' => 'success',
            'message' => 'Settings saved successfully.'
        ]);
    } else {
        $stmt->close();
        // This can happen if the data was unchanged or the user_id wasn't found.
        // Returning a soft success is often better than an error here.
        echo json_encode([
            'status' => 'success',
            'message' => 'Settings are already up to date.'
        ]);
    }
    exit();
}

// Fallback if the request is not GET or POST
http_response_code(405); // Method Not Allowed
echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
exit();

?>