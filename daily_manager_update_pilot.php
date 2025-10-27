<?php
// daily_manager_update_pilot.php - v83 - SESSION-BASED CSRF VERSION

// LINE 1: Headers first
header('Content-Type: application/json');

// LINE 2: Session start
if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}

// Error handling
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

// LINE 3: Include dependencies (REMOVE login_csrf_handler.php)
require_once 'db_connect.php';
// REMOVED: require_once 'login_csrf_handler.php'; 
require_once 'login_permissions.php';
require_once 'permissions.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // --- SESSION-BASED CSRF VALIDATION ---
    $submitted_token = $_POST['form_token'] ?? ''; // Changed from csrf_token to form_token
    
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
    
    // --- HANDLE JSON INPUT ---
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($input)) {
        // Use JSON input as primary data source
        $_POST = $input;
    }

    // --- SECURITY & VALIDATION ---
    if (!canManagePilotAdmin()) {
        throw new Exception("You do not have permission to edit users.", 403);
    }

    if (!isset($_SESSION['company_id'])) {
        throw new Exception("Authentication required.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];
    
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($user_id <= 0) {
        throw new Exception("Invalid or missing User ID.", 400);
    }
    
    // --- SANITIZE INPUTS ---
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    
    if (empty($firstname) || empty($lastname) || !$email) {
        throw new Exception("First Name, Last Name, and valid Email are required.", 400);
    }

    $nationality = empty(trim($_POST['user_nationality'] ?? '')) ? null : trim($_POST['user_nationality']);
    $phone = empty(trim($_POST['phone'] ?? '')) ? null : trim($_POST['phone']);
    $phonetwo = empty(trim($_POST['phonetwo'] ?? '')) ? null : trim($_POST['phonetwo']);
    $nal_license = empty(trim($_POST['nal_license'] ?? '')) ? null : trim($_POST['nal_license']);
    $for_license = empty(trim($_POST['for_license'] ?? '')) ? null : trim($_POST['for_license']);
    
    // --- DATE HANDLING ---
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
    
    // --- HANDLE CRAFT ASSIGNMENTS ---
    $crafts = $_POST['edit_crafts'] ?? [];
    if (!is_array($crafts)) {
        $crafts = [];
    }
    
    $contracts = $_POST['edit_contract_ids'] ?? [];
    $roles = $_POST['edit_role_ids'] ?? [];
    if (empty($roles)) $roles = [1];

    // --- PASSWORD RESET LOGIC ---
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_new_password'] ?? '';
    $passwordUpdateSqlFragment = '';
    $passwordParam = null;

    if (!empty($newPassword)) {
        if (strlen($newPassword) < 8) {
            throw new Exception("New password must be at least 8 characters long.", 400);
        }
        if ($newPassword !== $confirmPassword) {
            throw new Exception("Passwords do not match.", 400);
        }
        $passwordParam = password_hash($newPassword, PASSWORD_DEFAULT);
        $passwordUpdateSqlFragment = ", password = ?";
    }

    // --- DATABASE TRANSACTION ---
    $mysqli->begin_transaction();

    // --- UPDATE USER DETAILS ---
    $sql_user_update = "UPDATE users SET firstname = ?, lastname = ?, email = ?, user_nationality = ?, 
            phone = ?, phonetwo = ?, nal_license = ?, for_license = ?, hire_date = ? 
            $passwordUpdateSqlFragment 
        WHERE id = ? AND company_id = ?";
        
    $stmt_user = $mysqli->prepare($sql_user_update);

    if (!$stmt_user) throw new Exception("Prepare failed (user): " . $mysqli->error);
    
    $bindTypes = "sssssssss" . ($passwordParam ? 's' : '') . "ii";
    $bindParams = [
        $firstname, $lastname, $email, $nationality, $phone, $phonetwo, 
        $nal_license, $for_license, $hire_date_for_db
    ];

    if ($passwordParam !== null) {
        $bindParams[] = $passwordParam;
    }

    $bindParams[] = $user_id;
    $bindParams[] = $company_id;

    $stmt_user->bind_param($bindTypes, ...$bindParams);

    if (!$stmt_user->execute()) throw new Exception("Failed to update user details: " . $stmt_user->error);
    $stmt_user->close();

    // --- UPDATE CRAFT ASSIGNMENTS ---
    $stmt_delete_crafts = $mysqli->prepare("DELETE FROM pilot_craft_type WHERE user_id = ?");
    if (!$stmt_delete_crafts) throw new Exception("DB Prepare Error (craft delete): " . $mysqli->error);
    $stmt_delete_crafts->bind_param("i", $user_id);
    $stmt_delete_crafts->execute();
    $stmt_delete_crafts->close();

    if (!empty($crafts) && is_array($crafts)) {
        $stmt_insert_craft = $mysqli->prepare(
            "INSERT INTO pilot_craft_type (user_id, craft_type, position, is_tri, is_tre) VALUES (?, ?, ?, ?, ?)"
        );
        
        if (!$stmt_insert_craft) {
            throw new Exception("DB Prepare Error (craft insert): " . $mysqli->error);
        }

        foreach ($crafts as $assignment) {
            if (!isset($assignment['craft_type']) || empty($assignment['craft_type'])) {
                continue;
            }
            
            $craft_type = $assignment['craft_type'];
            $position = in_array($assignment['position'] ?? '', ['PIC', 'SIC']) ? $assignment['position'] : 'PIC';
            $is_tri = isset($assignment['is_tri']) ? (int)$assignment['is_tri'] : 0;
            $is_tre = isset($assignment['is_tre']) ? (int)$assignment['is_tre'] : 0;

            // Enforce business rule: TRE must also be TRI
            if ($is_tre === 1) {
                $is_tri = 1;
            }
            
            $stmt_insert_craft->bind_param("issii", $user_id, $craft_type, $position, $is_tri, $is_tre);
            if (!$stmt_insert_craft->execute()) {
                throw new Exception("Failed to insert craft assignment: " . $stmt_insert_craft->error);
            }
        }
        $stmt_insert_craft->close();
    }

    // --- UPDATE CONTRACT ASSIGNMENTS ---
    $stmt_contract = $mysqli->prepare("DELETE FROM contract_pilots WHERE user_id = ?");
    if (!$stmt_contract) throw new Exception("DB Prepare Error (contract delete): " . $mysqli->error);
    $stmt_contract->bind_param("i", $user_id);
    $stmt_contract->execute();
    $stmt_contract->close();
    
    if (!empty($contracts)) {
        $stmt_contract_insert = $mysqli->prepare("INSERT INTO contract_pilots (contract_id, user_id) VALUES (?, ?)");
        if (!$stmt_contract_insert) throw new Exception("DB Prepare Error (contract insert): " . $mysqli->error);
        
        foreach ($contracts as $contract_id) {
            $contract_id_int = (int)$contract_id;
            $stmt_contract_insert->bind_param("ii", $contract_id_int, $user_id);
            if (!$stmt_contract_insert->execute()) {
                throw new Exception("Failed to assign contract: " . $stmt_contract_insert->error);
            }
        }
        $stmt_contract_insert->close();
    }

    // --- UPDATE ROLES ---
    $stmt_role = $mysqli->prepare("DELETE FROM user_has_roles WHERE user_id = ? AND company_id = ?");
    if (!$stmt_role) throw new Exception("DB Prepare Error (role delete): " . $mysqli->error);
    $stmt_role->bind_param("ii", $user_id, $company_id);
    $stmt_role->execute();
    $stmt_role->close();
    
    if (!empty($roles)) {
        $stmt_role_insert = $mysqli->prepare("INSERT INTO user_has_roles (user_id, role_id, company_id) VALUES (?, ?, ?)");
        if (!$stmt_role_insert) throw new Exception("DB Prepare Error (role insert): " . $mysqli->error);
        
        foreach ($roles as $role_id) {
            $role_id_int = (int)$role_id;
            $stmt_role_insert->bind_param("iii", $user_id, $role_id_int, $company_id);
            if (!$stmt_role_insert->execute()) {
                throw new Exception("Failed to assign role: " . $stmt_role_insert->error);
            }
        }
        $stmt_role_insert->close();
    }

    // --- COMMIT ---
    $mysqli->commit();
    
    // ✅ Regenerate CSRF token on success (session-based)
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    $response = [
        'success' => true,
        'message' => "Details for " . htmlspecialchars($firstname) . " " . htmlspecialchars($lastname) . " updated successfully.",
        'new_csrf_token' => $_SESSION['csrf_token'] // ✅ Session-based token
    ];

} catch (Exception $e) {
    // ✅ FIXED: Remove invalid transaction check
    if (isset($mysqli)) {
        $mysqli->rollback();
    }
    
    // Clean any output buffer
    if (ob_get_length()) ob_clean();
    
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'new_csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)) // ✅ Session-based token
    ];
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

// Ensure no extra output
if (ob_get_length()) ob_clean();
echo json_encode($response);
exit;
?>