<?php
// hangar_change_password.php (Refined and Integrated Version)

session_start();
header('Content-Type: application/json');

include_once "db_connect.php";
global $mysqli; 

// --- 1. Authentication Check ---
if (!(session_status() === PHP_SESSION_ACTIVE && isset($_SESSION["HeliUser"]))) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Authentication required. Please log in."]);
    exit;
}

$uid = (int)$_SESSION["HeliUser"];

// --- 2. Get and Validate Inputs ---
// Using clearer variable names to match the form/AJAX
$old_password = $_POST["old_password"] ?? null;
$new_password = $_POST["new_password"] ?? null;
$confirm_password = $_POST["confirm_password"] ?? null;

if ($old_password === null || $new_password === null || $confirm_password === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "All password fields are required."]);
    exit;
}

if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "New password must be at least 8 characters long."]);
    exit;
}

if ($new_password !== $confirm_password) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "New passwords do not match."]);
    exit;
}

// --- 3. Database Interaction ---
try {
    // Get the current hashed password from the database
    $stmt = $mysqli->prepare("SELECT password FROM users WHERE id = ?");
    if (!$stmt) throw new Exception("Database error [C1]: Prepare failed.", 500);
    
    $stmt->bind_param("i", $uid);
    if (!$stmt->execute()) throw new Exception("Database error [C2]: Execute failed.", 500);
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        throw new Exception("User account not found [C6].", 404);
    }
    
    $hashed_password_from_db = $user["password"];

    // Verify the old password
    if (!password_verify($old_password, $hashed_password_from_db)) {
        throw new Exception("Your current password is incorrect. Please try again.", 400);
    }
    
    // Hash the new password
    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
    if ($hashed_new_password === false) {
        throw new Exception("Error processing new password [C3].", 500);
    }

    // Update the password in the database
    $stmt_update = $mysqli->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    if (!$stmt_update) throw new Exception("Database error [C4]: Prepare failed.", 500);
    
    $stmt_update->bind_param("si", $hashed_new_password, $uid);
    if (!$stmt_update->execute()) {
        throw new Exception("Failed to update password [C5].", 500);
    }

    if ($stmt_update->affected_rows > 0) {
        $_SESSION['password_changed_time'] = time(); 
        echo json_encode(["success" => true, "message" => "Password changed successfully."]);
    } else {
        // This can happen if the new password hashes to the same value as the old one.
        // It's not an error, so we still report success.
        echo json_encode(["success" => true, "message" => "Password updated."]);
    }
    $stmt_update->close();

} catch (Exception $e) {
    error_log("[ChangePassword] UID: $uid | Error: " . $e->getMessage());
    $httpStatusCode = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
    exit;
}
?>