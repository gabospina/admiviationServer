<?php
// contract_edit_contract.php - v83 DEBUG VERSION

// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    session_start();
    
    error_log("DEBUG: Session ID: " . session_id());
    error_log("DEBUG: Session CSRF token: " . ($_SESSION['csrf_token'] ?? 'NOT SET'));
    error_log("DEBUG: Session HeliUser: " . ($_SESSION['HeliUser'] ?? 'NOT SET'));
    error_log("DEBUG: Session company_id: " . ($_SESSION['company_id'] ?? 'NOT SET'));

    // Log the start
    error_log("=== DEBUG contract_edit_contract.php STARTED ===");
    error_log("POST data: " . print_r($_POST, true));

    // --- SESSION-BASED CSRF VALIDATION ---
    $submitted_token = $_POST['form_token'] ?? '';
    error_log("DEBUG: CSRF token received: " . ($submitted_token ? substr($submitted_token, 0, 10) . '...' : 'EMPTY'));

    if (empty($submitted_token)) {
        throw new Exception("Security token missing. Please refresh the page.", 403);
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    error_log("DEBUG: CSRF validation passed");

    require_once 'db_connect.php';
    error_log("DEBUG: Database connected");

    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    // Check if user is logged in
    if (!isset($_SESSION['HeliUser']) || $_SESSION['HeliUser'] == 0) {
        throw new Exception('Unauthorized access', 401);
    }

    // Get POST data
    $contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
    $contract_name = isset($_POST['contract_name']) ? trim($_POST['contract_name']) : '';
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $color = isset($_POST['color']) ? trim($_POST['color']) : '';
    $craft_ids = $_POST['craft_ids'] ?? [];
    $pilot_ids = $_POST['pilot_ids'] ?? [];

    error_log("DEBUG: contract_id: $contract_id");
    error_log("DEBUG: contract_name: $contract_name");
    error_log("DEBUG: customer_id: $customer_id");
    error_log("DEBUG: color: $color");
    error_log("DEBUG: craft_ids: " . print_r($craft_ids, true));
    error_log("DEBUG: pilot_ids: " . print_r($pilot_ids, true));

    // --- Validation ---
    if ($contract_id <= 0) {
        throw new Exception('Invalid contract ID', 400);
    }
    if (empty($contract_name)) {
        throw new Exception('Contract name cannot be empty', 400);
    }
    if ($customer_id <= 0) {
        throw new Exception('Invalid customer ID', 400);
    }
    if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
        throw new Exception('Invalid color format. Use hex format like #FFFFFF', 400);
    }

    error_log("DEBUG: Starting database transaction");
    $mysqli->begin_transaction();

    // 1. Update the main contracts table
    error_log("DEBUG: Updating main contract record");
    $stmt = $mysqli->prepare("UPDATE contracts SET contract_name = ?, customer_id = ?, color = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed for contract update: " . $mysqli->error);
    }
    
    $stmt->bind_param("sisi", $contract_name, $customer_id, $color, $contract_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for contract update: " . $stmt->error);
    }
    $stmt->close();
    error_log("DEBUG: Main contract updated");

    // 2. Update Craft Assignments (Delete then Insert)
    error_log("DEBUG: Updating craft assignments");
    $stmt_del_crafts = $mysqli->prepare("DELETE FROM contract_crafts WHERE contract_id = ?");
    if (!$stmt_del_crafts) {
        throw new Exception("Prepare failed for craft deletion: " . $mysqli->error);
    }
    $stmt_del_crafts->bind_param("i", $contract_id);
    if (!$stmt_del_crafts->execute()) {
        throw new Exception("Execute failed for craft deletion: " . $stmt_del_crafts->error);
    }
    $stmt_del_crafts->close();
    error_log("DEBUG: Old craft assignments deleted");
    
    if (!empty($craft_ids)) {
        error_log("DEBUG: Inserting " . count($craft_ids) . " craft assignments");
        $stmt_ins_crafts = $mysqli->prepare("INSERT INTO contract_crafts (contract_id, craft_id) VALUES (?, ?)");
        if (!$stmt_ins_crafts) {
            throw new Exception("Prepare failed for craft insertion: " . $mysqli->error);
        }
        foreach ($craft_ids as $craft_id) {
            $craft_id_int = (int)$craft_id;
            $stmt_ins_crafts->bind_param("ii", $contract_id, $craft_id_int);
            if (!$stmt_ins_crafts->execute()) {
                throw new Exception("Execute failed for craft insertion: " . $stmt_ins_crafts->error);
            }
        }
        $stmt_ins_crafts->close();
        error_log("DEBUG: New craft assignments inserted");
    }

    // 3. Update Pilot Assignments (Delete then Insert)
    error_log("DEBUG: Updating pilot assignments");
    $stmt_del_pilots = $mysqli->prepare("DELETE FROM contract_pilots WHERE contract_id = ?");
    if (!$stmt_del_pilots) {
        throw new Exception("Prepare failed for pilot deletion: " . $mysqli->error);
    }
    $stmt_del_pilots->bind_param("i", $contract_id);
    if (!$stmt_del_pilots->execute()) {
        throw new Exception("Execute failed for pilot deletion: " . $stmt_del_pilots->error);
    }
    $stmt_del_pilots->close();
    error_log("DEBUG: Old pilot assignments deleted");

    if (!empty($pilot_ids)) {
        error_log("DEBUG: Inserting " . count($pilot_ids) . " pilot assignments");
        $stmt_ins_pilots = $mysqli->prepare("INSERT INTO contract_pilots (contract_id, user_id) VALUES (?, ?)");
        if (!$stmt_ins_pilots) {
            throw new Exception("Prepare failed for pilot insertion: " . $mysqli->error);
        }
        foreach ($pilot_ids as $pilot_id) {
            $pilot_id_int = (int)$pilot_id;
            $stmt_ins_pilots->bind_param("ii", $contract_id, $pilot_id_int);
            if (!$stmt_ins_pilots->execute()) {
                throw new Exception("Execute failed for pilot insertion: " . $stmt_ins_pilots->error);
            }
        }
        $stmt_ins_pilots->close();
        error_log("DEBUG: New pilot assignments inserted");
    }
    
    $mysqli->commit();
    error_log("DEBUG: Transaction committed successfully");

    $response['success'] = true;
    $response['message'] = 'Contract updated successfully';

} catch (Exception $e) {
    error_log("DEBUG: Exception caught: " . $e->getMessage());
    
    if (isset($mysqli) && $mysqli->in_transaction) {
        $mysqli->rollback();
        error_log("DEBUG: Transaction rolled back");
    }
    
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

if (isset($mysqli)) {
    $mysqli->close();
}

error_log("DEBUG: Final response: " . json_encode($response));
echo json_encode($response);
?>