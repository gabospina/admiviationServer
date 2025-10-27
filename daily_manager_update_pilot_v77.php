<?php
// daily_manager_update_pilot.php - UPDATED TO HANDLE JSON INPUT

if (session_status() == PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'login_csrf_handler.php';
require_once 'login_permissions.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // --- HANDLE JSON INPUT ---
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($input)) {
        // Merge JSON input with $_POST for backward compatibility
        $_POST = array_merge($_POST, $input);
    }

    // --- SECURITY & VALIDATION ---
    CSRFHandler::validateToken($_POST['csrf_token'] ?? '');
    
    $rolesThatCanEdit = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    if (!userHasRole($rolesThatCanEdit, $mysqli)) {
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
    
    // --- HANDLE CRAFT ASSIGNMENTS (FIXED FOR JSON) ---
    $crafts = $_POST['edit_crafts'] ?? [];
    // Ensure crafts is an array even if empty
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

    // --- UPDATE CRAFT ASSIGNMENTS (NOW WORKS WITH JSON) ---
    $stmt_delete_crafts = $mysqli->prepare("DELETE FROM pilot_craft_type WHERE user_id = ?");
    if (!$stmt_delete_crafts) throw new Exception("DB Prepare Error (craft delete): " . $mysqli->error);
    $stmt_delete_crafts->bind_param("i", $user_id);
    $stmt_delete_crafts->execute();
    $stmt_delete_crafts->close();

    // --- DEBUG: Check if crafts are being processed ---
    error_log("=== DEBUG CRAFT PROCESSING ===");
    error_log("Number of crafts received: " . count($crafts));
    error_log("Crafts data: " . print_r($crafts, true));

    if (!empty($crafts) && is_array($crafts)) {
        $stmt_insert_craft = $mysqli->prepare(
            "INSERT INTO pilot_craft_type (user_id, craft_type, position, is_tri, is_tre) VALUES (?, ?, ?, ?, ?)"
        );
        
        if (!$stmt_insert_craft) {
            error_log("DEBUG: Craft insert prepare failed: " . $mysqli->error);
            throw new Exception("DB Prepare Error (craft insert): " . $mysqli->error);
        } else {
            error_log("DEBUG: Craft insert prepared successfully");
        }

        $insert_count = 0;
        foreach ($crafts as $index => $assignment) {
            error_log("DEBUG: Processing craft assignment $index: " . print_r($assignment, true));
            
            if (!isset($assignment['craft_type']) || empty($assignment['craft_type'])) {
                error_log("DEBUG: Skipping invalid craft assignment at index $index");
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
            
            error_log("DEBUG: Inserting craft - user_id: $user_id, craft_type: $craft_type, position: $position, is_tri: $is_tri, is_tre: $is_tre");
            
            $stmt_insert_craft->bind_param("issii", $user_id, $craft_type, $position, $is_tri, $is_tre);
            if ($stmt_insert_craft->execute()) {
                $insert_count++;
                error_log("DEBUG: Successfully inserted craft assignment");
            } else {
                error_log("DEBUG: Craft insert execute failed: " . $stmt_insert_craft->error);
                throw new Exception("Failed to insert craft assignment: " . $stmt_insert_craft->error);
            }
        }
        $stmt_insert_craft->close();
        error_log("DEBUG: Total crafts inserted: $insert_count");
    } else {
        error_log("DEBUG: No crafts to process or crafts is not an array");
    }

    error_log("=== END DEBUG CRAFT PROCESSING ===");

    // --- UPDATE CONTRACT ASSIGNMENTS ---
    $stmt_contract = $mysqli->prepare("DELETE FROM contract_pilots WHERE user_id = ?");
    $stmt_contract->bind_param("i", $user_id);
    $stmt_contract->execute();
    $stmt_contract->close();
    if (!empty($contracts)) {
        $stmt_contract_insert = $mysqli->prepare("INSERT INTO contract_pilots (contract_id, user_id) VALUES (?, ?)");
        foreach ($contracts as $contract_id) {
            $contract_id_int = (int)$contract_id;
            $stmt_contract_insert->bind_param("ii", $contract_id_int, $user_id);
            $stmt_contract_insert->execute();
        }
        $stmt_contract_insert->close();
    }

    // --- UPDATE ROLES ---
    $stmt_role = $mysqli->prepare("DELETE FROM user_has_roles WHERE user_id = ? AND company_id = ?");
    $stmt_role->bind_param("ii", $user_id, $company_id);
    $stmt_role->execute();
    $stmt_role->close();
    if (!empty($roles)) {
        $stmt_role_insert = $mysqli->prepare("INSERT INTO user_has_roles (user_id, role_id, company_id) VALUES (?, ?, ?)");
        foreach ($roles as $role_id) {
            $role_id_int = (int)$role_id;
            $stmt_role_insert->bind_param("iii", $user_id, $role_id_int, $company_id);
            $stmt_role_insert->execute();
        }
        $stmt_role_insert->close();
    }

    // --- COMMIT ---
    $mysqli->commit();
    $response['success'] = true;
    $response['message'] = "Details for " . htmlspecialchars($firstname) . " " . htmlspecialchars($lastname) . " updated successfully.";
    $response['new_csrf_token'] = generate_csrf_token(); // Return new token

} catch (Exception $e) {
    if (isset($mysqli) && method_exists($mysqli, 'rollback')) {
        $mysqli->rollback();
    }
    $response['error'] = $e->getMessage();
    $httpStatusCode = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $response['new_csrf_token'] = generate_csrf_token(); // Return new token even on error
}

echo json_encode($response);
?>