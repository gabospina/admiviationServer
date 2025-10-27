<?php
// login.php - FINAL VERSION WITH LOGIN TRACKING

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// 1. Validate CSRF Token
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    empty($_POST['csrf_token']) || 
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    
    echo json_encode(['success' => false, 'error' => 'Invalid session or form submission. Please refresh the page.']);
    exit();
}

require_once 'db_connect.php';
$response = ['success' => false, 'error' => 'Login failed. Please try again.'];

try {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        throw new Exception("Username and password are required.");
    }

    $stmt = $mysqli->prepare("SELECT id, password, company_id, firstname FROM users WHERE username = ? AND is_active = 1");
    if (!$stmt) throw new Exception("Database query failed to prepare.");
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            // --- SESSION SETUP (SUCCESSFUL LOGIN) ---
            session_regenerate_id(true); 

            $_SESSION["HeliUser"] = (int)$user['id'];
            $_SESSION["username"] = $username;
            $_SESSION["name"] = $user['firstname'];
            $_SESSION["company_id"] = (int)$user['company_id'];

            // --- START: NEW LOGIN TRACKING IMPLEMENTATION ---
            // EXPLANATION: This powerful query handles both inserting a new tracking record
            // and updating an existing one in a single, efficient operation.
            $sql_track = "
                INSERT INTO user_tracking (id, company_id, logins, last_login)
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE 
                    logins = logins + 1,
                    last_login = NOW()
            ";
            $stmt_track = $mysqli->prepare($sql_track);
            if ($stmt_track) {
                $stmt_track->bind_param("ii", $_SESSION["HeliUser"], $_SESSION["company_id"]);
                $stmt_track->execute();
                $stmt_track->close();
            } else {
                // If tracking fails, log the error but don't stop the user from logging in.
                error_log("Failed to prepare user_tracking statement: " . $mysqli->error);
            }
            // --- END: NEW LOGIN TRACKING IMPLEMENTATION ---

            // --- ROLE ASSIGNMENT ---
            $user_assigned_roles = [];
            $stmt_roles = $mysqli->prepare("SELECT role_id FROM user_has_roles WHERE user_id = ?");
            if ($stmt_roles) {
                $stmt_roles->bind_param("i", $_SESSION["HeliUser"]);
                $stmt_roles->execute();
                $result_roles = $stmt_roles->get_result();
                while($row_role = $result_roles->fetch_assoc()) {
                    $user_assigned_roles[] = (int)$row_role['role_id'];
                }
                $stmt_roles->close();
            }
            $_SESSION['user_role_ids'] = $user_assigned_roles;
            $_SESSION['admin'] = in_array(8, $user_assigned_roles) ? 8 : 0;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Determine redirect URL based on roles
            $redirect_url = "home.php"; // Default
            if (in_array(8, $user_assigned_roles)) { // Admin role
                 $redirect_url = "daily_manager.php";
            } else if (in_array(3, $user_assigned_roles) || in_array(6, $user_assigned_roles)) { // Training roles
                 $redirect_url = "training.php";
            }
            
            $response = [
                'success' => true,
                'redirect' => $redirect_url
            ];

        } else {
            throw new Exception("The username or password you entered is incorrect.");
        }
    } else {
        throw new Exception("The username or password you entered is incorrect, or the account is inactive.");
    }
    $stmt->close();

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>