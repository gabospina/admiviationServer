<?php
require_once 'api_response.php';
include_once "db_connect.php";

// Initialize session and check authentication
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["HeliUser"])) {
    echo ApiResponse::error("Authentication required", 401);
    exit();
}

try {
    $user_id = (int)$_SESSION["HeliUser"];
    
    // Validate required fields
    $required = ['date', 'craft', 'aircraft', 'hours', 'hour_type'];
    $errors = validateInput($required);
    
    if (!empty($errors)) {
        echo ApiResponse::error(implode(', ', $errors));
        exit();
    }

    // Sanitize and validate input
    $date = sanitizeInput($_POST['date']);
    $craft = sanitizeInput($_POST['craft']);
    $aircraft = sanitizeInput($_POST['aircraft']);
    $command = isset($_POST['command']) ? sanitizeInput($_POST['command']) : null;
    $copilot = isset($_POST['copilot']) ? sanitizeInput($_POST['copilot']) : null;
    $route = isset($_POST['route']) ? sanitizeInput($_POST['route']) : null;
    $ifr = isset($_POST['ifr']) ? (float)$_POST['ifr'] : 0.0;
    $actual = isset($_POST['actual']) ? (float)$_POST['actual'] : 0.0;
    $hours = (float)$_POST['hours'];
    $hour_type = sanitizeInput($_POST['hour_type']);

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo ApiResponse::error("Invalid date format");
        exit();
    }

    // Validate hour_type
    if (!in_array($hour_type, ['day', 'night'])) {
        echo ApiResponse::error("Invalid hour type");
        exit();
    }

    // Validate hours
    if ($hours <= 0) {
        echo ApiResponse::error("Hours must be greater than 0");
        exit();
    }

    // Insert flight hours
    $query = "INSERT INTO flight_hours (date, craft, aircraft, command, copilot, route, ifr, actual, hours, hour_type, user_id) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ssssssdddsi", $date, $craft, $aircraft, $command, $copilot, $route, $ifr, $actual, $hours, $hour_type, $user_id);
    
    if ($stmt->execute()) {
        echo ApiResponse::success([
            'id' => $mysqli->insert_id,
            'date' => $date,
            'craft' => $craft,
            'aircraft' => $aircraft
        ], "Flight hours added successfully");
    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    logError("Error in add_flight_hours.php: " . $e->getMessage(), [
        'user_id' => $user_id ?? null,
        'date' => $date ?? null,
        'craft' => $craft ?? null
    ]);
    echo ApiResponse::error("An error occurred while adding flight hours");
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $mysqli->close();
}
?> 