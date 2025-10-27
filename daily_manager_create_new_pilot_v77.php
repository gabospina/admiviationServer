<?php
// daily_manager_create_new_pilot.php (FINAL COMPLETE VERSION)

if (session_status() == PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'login_csrf_handler.php'; // For security
require_once 'login_permissions.php'; // For security
require_once 'permissions.php'; // Include the firewall

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // --- SECURITY GUARD CLAUSE ---
    if (!canManagePilotAdmin()) {
        throw new Exception("You do not have permission to create users.", 403);
    }
    // --- END GUARD CLAUSE ---
    
    // --- 1. Security & Validation ---
    CSRFHandler::validateToken($_POST['csrf_token'] ?? ''); // Add CSRF validation
    
    // $rolesThatCanCreate = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    // if (!userHasRole($rolesThatCanCreate, $mysqli)) {
    //     throw new Exception("You do not have permission to create users.", 403);
    // }
    
    if (!isset($_SESSION['company_id'])) {
        throw new Exception("Authentication required.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Method not allowed.", 405);
    }
    
    // --- 2. Validate and Sanitize Inputs ---
    $required = ['firstname', 'lastname', 'email', 'username', 'password', 'confpassword'];
    foreach ($required as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            throw new Exception("Missing required field: $field", 400);
        }
    }

    if ($_POST['password'] !== $_POST['confpassword']) {
        throw new Exception("Passwords do not match.", 400);
    }

    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $username = trim($_POST['username']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    if (!$email) throw new Exception("Invalid email format.", 400);

    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Sanitize all other fields
    $nationality = empty(trim($_POST['user_nationality'] ?? '')) ? null : trim($_POST['user_nationality']);
    $phone = empty(trim($_POST['phone'] ?? '')) ? null : trim($_POST['phone']);
    $phonetwo = empty(trim($_POST['phonetwo'] ?? '')) ? null : trim($_POST['phonetwo']);
    $nal_license = empty(trim($_POST['nal_license'] ?? '')) ? null : trim($_POST['nal_license']);
    $for_license = empty(trim($_POST['for_license'] ?? '')) ? null : trim($_POST['for_license']);
    
    // --- CRITICAL: Date Handling ---
    $hire_date_input = trim($_POST['hire_date'] ?? '');
    $hire_date_for_db = null;
    if (!empty($hire_date_input)) {
        // --- THIS IS THE FIX ---
        // Change the expected format to match what Flatpickr sends.
        $dateObject = DateTime::createFromFormat('Y-m-d', $hire_date_input);
        if ($dateObject) {
            $hire_date_for_db = $dateObject->format('Y-m-d');
        } else {
            // Update the error message to be helpful.
            throw new Exception("Invalid Hire Date format. Please use YYYY-MM-DD.", 400);
        }
    }

    $role_ids = $_POST['role_ids'] ?? [1];
    if (empty($role_ids)) $role_ids = [1];

    $assignments = $_POST['assignments'] ?? [];
    $crafts = $assignments['crafts'] ?? [];
    $contracts = $assignments['contracts'] ?? [];
    
    // --- 3. Check for Duplicates ---
    $stmt_check = $mysqli->prepare("SELECT id, username, email FROM users WHERE company_id = ? AND (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?))");
    $stmt_check->bind_param("iss", $company_id, $username, $email);
    $stmt_check->execute();
    $duplicate_result = $stmt_check->get_result();
    if ($duplicate_result->num_rows > 0) {
        $duplicate = $duplicate_result->fetch_assoc();
        error_log("Duplicate user found - ID: " . $duplicate['id'] . ", Username: " . $duplicate['username'] . ", Email: " . $duplicate['email']);
        throw new Exception("Username or email already exists in this company.", 409);
    }
    $stmt_check->close();
    
    // --- 4. Database Transaction ---
    $mysqli->begin_transaction();

    $stmt_user = $mysqli->prepare(
        "INSERT INTO users (company_id, firstname, lastname, user_nationality, email, phone, phonetwo, username, password, nal_license, for_license, hire_date, is_active) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $stmt_user->bind_param("isssssssssss", $company_id, $firstname, $lastname, $nationality, $email, $phone, $phonetwo, $username, $password_hash, $nal_license, $for_license, $hire_date_for_db);
    if (!$stmt_user->execute()) throw new Exception("Failed to create user: " . $stmt_user->error);
    $new_pilot_id = $stmt_user->insert_id;
    $stmt_user->close();
    
    // --- 7. Insert roles ---
    $stmt_role = $mysqli->prepare("INSERT INTO user_has_roles (user_id, role_id, company_id) VALUES (?, ?, ?)");
    foreach ($role_ids as $role_id) {
        if ($role_id > 0) {
            $stmt_role->bind_param("iii", $new_pilot_id, $role_id, $company_id);
            if (!$stmt_role->execute()) {
            error_log("Failed to insert role $role_id for user $new_pilot_id: " . $stmt_role->error);
            } else {
                error_log("Successfully assigned role $role_id to user $new_pilot_id");
            }
        }
    }
    $stmt_role->close();
    
    // --- 8. FIX: Insert craft assignments with correct column name 'user_id' ---
    $craft_assignments = $_POST['assignments']['crafts'] ?? []; // Or 'edit_crafts' for the update script

    if (!empty($craft_assignments)) {
        // This loop now expects an array of assignment objects
        $stmt = $mysqli->prepare(
            "INSERT INTO pilot_craft_type (user_id, craft_type, position, is_tri, is_tre) VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($craft_assignments as $assignment) {
            $craft_type = $assignment['craft_type'];
            $position = in_array($assignment['position'], ['PIC', 'SIC']) ? $assignment['position'] : 'PIC';
            $is_tri = (int)($assignment['is_tri'] ?? 0);
            $is_tre = (int)($assignment['is_tre'] ?? 0);

            // Enforce the hierarchy: if a user is a TRE, they must also be a TRI
            if ($is_tre === 1) {
                $is_tri = 1;
            }
            
            $stmt->bind_param("issii", $new_pilot_id, $craft_type, $position, $is_tri, $is_tre);
            $stmt->execute();
        }
        $stmt->close();
    }

    // --- 9. FIX: Insert contract assignments with correct column name 'user_id' ---
    if (!empty($contracts)) {
        $stmt_contract = $mysqli->prepare("INSERT INTO contract_pilots (user_id, contract_id) VALUES (?, ?)");
        if (!$stmt_contract) throw new Exception("DB Prepare Error (contracts): " . $mysqli->error);
        foreach ($contracts as $contract_id) {
            $contract_id_int = (int)$contract_id;
            if ($contract_id_int > 0) {
                $stmt_contract->bind_param("ii", $new_pilot_id, $contract_id_int);
                $stmt_contract->execute();
            }
        }
        $stmt_contract->close();
    }

    // --- 10. Commit ---
    $mysqli->commit();
    $response = [
        'success' => true,
        'message' => "Pilot account for " . htmlspecialchars($firstname) . " " . htmlspecialchars($lastname) . " created successfully.",
        'pilot_id' => $new_pilot_id
    ];

    } catch (Exception $e) {
        if (isset($mysqli) && $mysqli->in_transaction) {
            $mysqli->rollback();
        }
        $response = [
            'success' => false,
            'error' => $e->getMessage()
        ];
        http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    }

echo json_encode($response);