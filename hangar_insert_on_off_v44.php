
<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the Content-Type header to application/json
header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION["HeliUser"])) {
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

include_once "db_connect.php";
// include_once "check_login.php";

// Check if the database connection was successful
if (!$mysqli) {
    error_log("Database connection error: " . mysqli_connect_error());
    echo json_encode(["error" => "Database connection error: " . mysqli_connect_error()]);
    exit();
}

// Function to sanitize input
function sanitizeInput($input) {
    global $mysqli;
    return htmlspecialchars(mysqli_real_escape_string($mysqli, $input));
}

// Sanitize inputs
$id = intval($_POST["id"]);
$on = sanitizeInput($_POST["on"]);
$off = sanitizeInput($_POST["off"]);

// Check if the id is valid, must be a number
if ($id <= 0) {
    echo json_encode(["error" => "Invalid ID"]);
    exit;
}

// Validate date format (YYYY-MM-DD)
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $on) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $off)) {
    echo json_encode(["error" => "Invalid date format. Use YYYY-MM-DD."]);
    exit;
}

// Validate that off_date is after on_date
if ($off <= $on) {
    echo json_encode(["error" => "Off date must be after on date."]);
    exit;
}

try {
    $sql = "INSERT INTO user_availability (user_id, on_date, off_date) VALUES (?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    
    // Check if the prepare statement was valid, because is possible the column not found or SQL is invalid.
    if(!$stmt){
        error_log("SQL prepare error: " . $mysqli->error);
        echo json_encode(["error" => "Database error: " .  $mysqli->error]);
        exit(); // IMPORTANT: Stop further execution
    }

    $stmt->bind_param("iss", $id, $on, $off);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["error" => "Failed to insert new entry: ". $stmt->error]);
    }

    $stmt->close();
} catch (Exception $e) {
	error_log("Exception: " . $e->getMessage()); // Log the full exception message
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
    exit();
}
?>