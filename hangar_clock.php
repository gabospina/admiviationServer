<?php
// File: hangar-clock.php
// Purpose: Securely handles saving the user's primary timezone.

// We need the session to identify the user
session_start();
header('Content-Type: application/json');

// Security Check 1: Ensure the user is logged in.
if (!isset($_SESSION['HeliUser'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

// Security Check 2: Ensure this is a POST request.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

// Include the database connection
include_once "db_connect.php";

$user_id = (int)$_SESSION['HeliUser'];
$timezone = $_POST['timezone'] ?? null;

// Security Check 3: Validate the received timezone.
// It must not be empty and must be a real, valid timezone identifier.
if (empty($timezone) || !in_array($timezone, DateTimeZone::listIdentifiers())) {
    echo json_encode(['success' => false, 'error' => 'Invalid timezone selected.']);
    exit();
}

// Prepare the statement to update the user's record
$stmt = $mysqli->prepare("UPDATE users SET timezone = ? WHERE id = ?");
$stmt->bind_param("si", $timezone, $user_id);

if ($stmt->execute()) {
    // IMPORTANT: Update the session variable immediately.
    // This ensures the change is reflected on the next page load without needing to log out.
    $_SESSION['user_timezone'] = $timezone;
    echo json_encode(['success' => true, 'message' => 'Timezone updated successfully!']);
} else {
    // Provide a generic error for security. Log detailed errors if needed.
    error_log("Failed to update timezone for user ID {$user_id}: " . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Database error. Could not save timezone.']);
}

$stmt->close();
$mysqli->close();
?>