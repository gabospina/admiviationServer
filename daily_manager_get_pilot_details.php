<?php
// daily_manager_get_pilot_details.php (FINAL CORRECTED VERSION)

if (session_status() == PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // --- 1. Security & Validation ---
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("Authentication required.", 401);
    }

    if (!isset($_GET['user_id'])) {
        throw new Exception("User ID is required.", 400);
    }
    $user_id = (int)$_GET['user_id'];
    if ($user_id <= 0) {
        throw new Exception("Invalid User ID provided.", 400);
    }
    
    $user_data = [];

    // --- 2. Get All User Info ---
    $stmt = $mysqli->prepare(
        "SELECT id, firstname, lastname, email, username, user_nationality, phone, phonetwo, nal_license, for_license, hire_date 
         FROM users WHERE id = ?"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) throw new Exception("Pilot not found.", 404);

    $user_details = $result->fetch_assoc();

    // --- CRITICAL: Date Formatting for Frontend ---
    if (!empty($user_details['hire_date'])) {
        $dateObject = new DateTime($user_details['hire_date']);
        $user_details['hire_date'] = $dateObject->format('d-m-Y'); // Format for dd-mm-yyyy datepicker
    }
    $user_data['details'] = $user_details;
    $stmt->close();

    // =========================================================================
    // === THE FIX: Use the correct 'user_id' column name in these queries   ===
    // =========================================================================
    // --- 3. Get Assigned Craft Types and Positions ---
    $stmt = $mysqli->prepare("SELECT craft_type, position, is_tri, is_tre FROM pilot_craft_type WHERE user_id = ?");
    
    if (!$stmt) throw new Exception("DB Prepare Error (crafts): " . $mysqli->error);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data['assigned_crafts'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // --- 4. Get Assigned Contract IDs ---
    $stmt = $mysqli->prepare("SELECT contract_id FROM contract_pilots WHERE user_id = ?");
    if (!$stmt) throw new Exception("DB Prepare Error (contracts): " . $mysqli->error);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assigned_contracts = [];
    while($row = $result->fetch_assoc()) {
        $assigned_contracts[] = $row['contract_id'];
    }
    $user_data['assigned_contracts'] = $assigned_contracts;
    $stmt->close();
    // =========================================================================

    // --- 5. Get Assigned Role IDs (This was already correct) ---
    $stmt = $mysqli->prepare("SELECT role_id FROM user_has_roles WHERE user_id = ?");
    if (!$stmt) throw new Exception("DB Prepare Error (roles): " . $mysqli->error);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assigned_roles = [];
    while($row = $result->fetch_assoc()) {
        $assigned_roles[] = $row['role_id'];
    }
    $user_data['assigned_roles'] = $assigned_roles;
    $stmt->close();
    
    $response['success'] = true;
    $response['data'] = $user_data;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>