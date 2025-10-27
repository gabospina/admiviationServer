<?php
// craft_remove_craft.php - UPDATED WITH CONSISTENT CSRF

// --- SESSION AND SECURITY ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json'); // Add this for proper JSON response

include_once "db_connect.php";
// REMOVE: require_once 'login_csrf_handler.php'; // Remove conflicting handler

// Initialize response array
$response = array();

// --- CONSISTENT CSRF VALIDATION (same as your other files) ---
$submitted_token = $_POST['form_token'] ?? ''; // Changed from csrf_token to form_token

// Check if token is missing
if (empty($submitted_token)) {
    $response["success"] = false;
    $response["message"] = "Security token missing. Please refresh the page.";
    echo json_encode($response);
    exit;
}

// Check if session token exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate token using session-based approach
if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    $response["success"] = false;
    $response["message"] = "Invalid security token. Please refresh the page.";
    $response["new_csrf_token"] = $_SESSION['csrf_token']; // Send current token back
    echo json_encode($response);
    exit;
}

// --- ORIGINAL CRAFT REMOVAL LOGIC (unchanged) ---
$craft_id = $_POST["craft"]; // Get the craft ID

// Use prepared statements to prevent SQL injection
$sql = "DELETE FROM crafts WHERE id = ?";  //delete the craft
$stmt = $mysqli->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $craft_id);  // "i" - integer
    if ($stmt->execute()) {
        $stmt->close();
        $response["success"] = true;
        $response["message"] = "success";
        
        // ✅ Regenerate token only on success
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $response["new_csrf_token"] = $_SESSION['csrf_token'];
    } else {
        error_log("Error deleting craft: " . $stmt->error); // Log the error
        $stmt->close();
        $response["success"] = false;
        $response["message"] = "failed_delete_craft";
        $response["new_csrf_token"] = $_SESSION['csrf_token']; // Send current token on error
    }
} else {
    error_log("Error preparing statement: " . $mysqli->error);
    $response["success"] = false;
    $response["message"] = "failed_prepare_craft";
    $response["new_csrf_token"] = $_SESSION['csrf_token']; // Send current token on error
}

echo json_encode($response);
$mysqli->close();
?>