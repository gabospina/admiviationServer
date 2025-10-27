<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once "db_connect.php";

// Function to return a JSON response
function returnJson($status, $message) {
    header('Content-Type: application/json');
    echo json_encode(array("status" => $status, "message" => $message));
    exit();
}

// Check if the username is set
if (isset($_GET["username"])) {
    // Get the username and convert it to lowercase
    $username = strtolower($_GET["username"]);

    // Validate the username
    if (empty($username) || strlen($username) < 4) {
        returnJson("invalid", "Username must be at least 4 characters long.");
    }

    // Use prepared statements to prevent SQL injection
    $sql = "SELECT username FROM users WHERE username = ?";
    $stmt = $mysqli->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $username); // "s" indicates a string
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            returnJson("taken", "This username is already taken.");
        } else {
            returnJson("available", "This username is available.");
        }

        $stmt->close();
    } else {
        returnJson("error", "Database error: " . $mysqli->error);
    }

    $mysqli->close(); // Always close the connection
} else {
    returnJson("invalid", "Invalid request (username not set).");
}
?>