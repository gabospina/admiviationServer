<?php
// daily_manager_create_new_pilot.php - FIXED VERSION

// ADD THESE AT THE VERY TOP - BEFORE ANY OUTPUT
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}

require_once 'db_connect.php';
require_once 'permissions.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // --- SESSION-BASED CSRF VALIDATION ---
    $submitted_token = $_POST['form_token'] ?? '';
    
    // Check if token is missing
    if (empty($submitted_token)) {
        throw new Exception("Security token missing. Please refresh the page.", 403);
    }
    
    // Check if session token exists
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Validate token using session-based approach
    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    // --- 2. SECURITY CHECKS ---
    if (!canManagePilotAdmin()) {
        throw new Exception("You do not have permission to create users.", 403);
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Method not allowed.", 405);
    }
    
    $company_id = (int)$_SESSION['company_id'];
    
    // --- 4. Validate Required Fields ---
    $required = ['firstname', 'lastname', 'email', 'username', 'password', 'confpassword'];
    foreach ($required as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            throw new Exception("Missing required field: $field", 400);
        }
    }

    if ($_POST['password'] !== $_POST['confpassword']) {
        throw new Exception("Passwords do not match.", 400);
    }

    // --- 5. Sanitize Inputs ---
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $username = trim($_POST['username']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    if (!$email) throw new Exception("Invalid email format.", 400);

    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $nationality = empty(trim($_POST['user_nationality'] ?? '')) ? null : trim($_POST['user_nationality']);
    $phone = empty(trim($_POST['phone'] ?? '')) ? null : trim($_POST['phone']);
    $phonetwo = empty(trim($_POST['phonetwo'] ?? '')) ? null : trim($_POST['phonetwo']);
    $nal_license = empty(trim($_POST['nal_license'] ?? '')) ? null : trim($_POST['nal_license']);
    $for_license = empty(trim($_POST['for_license'] ?? '')) ? null : trim($_POST['for_license']);
    
    // --- 6. Date Handling ---
    $hire_date_input = trim($_POST['hire_date'] ?? '');
    $hire_date_for_db = null;
    if (!empty($hire_date_input)) {
        $dateObject = DateTime::createFromFormat('Y-m-d', $hire_date_input);
        if ($dateObject) {
            $hire_date_for_db = $dateObject->format('Y-m-d');
        } else {
            throw new Exception("Invalid Hire Date format. Please use YYYY-MM-DD.", 400);
        }
    }

    $role_ids = $_POST['role_ids'] ?? [1];
    if (empty($role_ids)) $role_ids = [1];

    // --- 7. Check for Duplicates ---
    $stmt_check = $mysqli->prepare("SELECT id FROM users WHERE company_id = ? AND (username = ? OR email = ?)");
    $stmt_check->bind_param("iss", $company_id, $username, $email);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        throw new Exception("Username or email already exists.", 409);
    }
    $stmt_check->close();
    
    // --- 8. Database Transaction ---
    $mysqli->begin_transaction();

    // Insert user
    $stmt_user = $mysqli->prepare(
        "INSERT INTO users (company_id, firstname, lastname, user_nationality, email, phone, phonetwo, username, password, nal_license, for_license, hire_date, is_active) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    
    if (!$stmt_user) {
        throw new Exception("Failed to prepare user statement: " . $mysqli->error);
    }
    
    $stmt_user->bind_param("isssssssssss", $company_id, $firstname, $lastname, $nationality, $email, $phone, $phonetwo, $username, $password_hash, $nal_license, $for_license, $hire_date_for_db);
    
    if (!$stmt_user->execute()) {
        throw new Exception("Failed to create user: " . $stmt_user->error);
    }
    
    $new_pilot_id = $stmt_user->insert_id;
    $stmt_user->close();
    
    // --- 9. Insert roles ---
    $stmt_role = $mysqli->prepare("INSERT INTO user_has_roles (user_id, role_id, company_id) VALUES (?, ?, ?)");
    if (!$stmt_role) {
        throw new Exception("Failed to prepare role statement: " . $mysqli->error);
    }
    
    foreach ($role_ids as $role_id) {
        $role_id_int = (int)$role_id;
        if ($role_id_int > 0) {
            $stmt_role->bind_param("iii", $new_pilot_id, $role_id_int, $company_id);
            if (!$stmt_role->execute()) {
                throw new Exception("Failed to assign role: " . $stmt_role->error);
            }
        }
    }
    $stmt_role->close();
    
    // --- 10. Insert craft assignments ---
    $craft_assignments = $_POST['assignments']['crafts'] ?? [];
    if (!empty($craft_assignments)) {
        $stmt_craft = $mysqli->prepare(
            "INSERT INTO pilot_craft_type (user_id, craft_type, position, is_tri, is_tre) VALUES (?, ?, ?, ?, ?)"
        );
        
        if (!$stmt_craft) {
            throw new Exception("Failed to prepare craft statement: " . $mysqli->error);
        }
        
        foreach ($craft_assignments as $assignment) {
            $craft_type = $assignment['craft_type'];
            $position = in_array($assignment['position'], ['PIC', 'SIC']) ? $assignment['position'] : 'PIC';
            $is_tri = (int)($assignment['is_tri'] ?? 0);
            $is_tre = (int)($assignment['is_tre'] ?? 0);

            if ($is_tre === 1) $is_tri = 1;
            
            $stmt_craft->bind_param("issii", $new_pilot_id, $craft_type, $position, $is_tri, $is_tre);
            if (!$stmt_craft->execute()) {
                throw new Exception("Failed to assign craft: " . $stmt_craft->error);
            }
        }
        $stmt_craft->close();
    }

    // --- 11. Insert contract assignments ---
    $contracts = $_POST['assignments']['contracts'] ?? [];
    if (!empty($contracts)) {
        $stmt_contract = $mysqli->prepare("INSERT INTO contract_pilots (user_id, contract_id) VALUES (?, ?)");
        if (!$stmt_contract) {
            throw new Exception("Failed to prepare contract statement: " . $mysqli->error);
        }
        
        foreach ($contracts as $contract_id) {
            $contract_id_int = (int)$contract_id;
            if ($contract_id_int > 0) {
                $stmt_contract->bind_param("ii", $new_pilot_id, $contract_id_int);
                if (!$stmt_contract->execute()) {
                    throw new Exception("Failed to assign contract: " . $stmt_contract->error);
                }
            }
        }
        $stmt_contract->close();
    }

    // --- 12. Commit and Regenerate CSRF Token ---
    $mysqli->commit();
    
    // Regenerate CSRF token for security
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    $response = [
        'success' => true,
        'message' => "Pilot {$firstname} {$lastname} created successfully.",
        'pilot_id' => $new_pilot_id,
        'new_csrf_token' => $_SESSION['csrf_token'] // Session-based token
    ];

} catch (Exception $e) {
    if (isset($mysqli)) {
        $mysqli->rollback();
    }
    
    error_log("Pilot creation error: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'new_csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32))
    ];
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

echo json_encode($response);
?>