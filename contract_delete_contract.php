<?php
// contract_delete_contract.php v83

session_start();

// --- SESSION-BASED CSRF VALIDATION ---
$submitted_token = $_POST['form_token'] ?? '';

if (empty($submitted_token)) {
    throw new Exception("Security token missing. Please refresh the page.", 403);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    throw new Exception("Invalid security token. Please refresh the page.", 403);
}

include_once "db_connect.php";

// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Initialize debug log
error_log("=== Starting contract deletion process ===");

// Check if user is logged in
if (!isset($_SESSION["HeliUser"])) {
    error_log("Error: User not logged in");
    echo json_encode([
        "success" => false, 
        "message" => "Authentication required"
    ]);
    exit;
}

// Get contract ID
$contract_id = isset($_POST['contract_id']) ? (int)$_POST['contract_id'] : 0;
error_log("Received contract ID: " . $contract_id);

if ($contract_id <= 0) {
    error_log("Error: Invalid contract ID received");
    echo json_encode([
        "success" => false, 
        "message" => "Invalid contract ID"
    ]);
    exit;
}

// Start transaction
error_log("Starting database transaction");
$mysqli->begin_transaction();

// Start transaction
error_log("Starting database transaction");
$mysqli->begin_transaction();

try {
    // 1. First delete contract_pilots relationships
    error_log("Deleting contract_pilots relationships");
    $delete_pilots_sql = "DELETE FROM contract_pilots WHERE contract_id = ?";
    $delete_pilots_stmt = $mysqli->prepare($delete_pilots_sql);
    
    if (!$delete_pilots_stmt) {
        error_log("Prepare failed for contract_pilots: " . $mysqli->error);
        throw new Exception("Database error: " . $mysqli->error);
    }
    
    $delete_pilots_stmt->bind_param("i", $contract_id);
    if (!$delete_pilots_stmt->execute()) {
        error_log("Execute failed for contract_pilots: " . $delete_pilots_stmt->error);
        throw new Exception("Database error: " . $delete_pilots_stmt->error);
    }
    
    $pilots_deleted = $delete_pilots_stmt->affected_rows;
    error_log("Deleted $pilots_deleted contract_pilots records");
    $delete_pilots_stmt->close();

    // 2. Then delete contract_crafts relationships
    error_log("Deleting contract_crafts relationships");
    $delete_crafts_sql = "DELETE FROM contract_crafts WHERE contract_id = ?";
    $delete_crafts_stmt = $mysqli->prepare($delete_crafts_sql);
    
    if (!$delete_crafts_stmt) {
        error_log("Prepare failed for contract_crafts: " . $mysqli->error);
        throw new Exception("Database error: " . $mysqli->error);
    }
    
    $delete_crafts_stmt->bind_param("i", $contract_id);
    if (!$delete_crafts_stmt->execute()) {
        error_log("Execute failed for contract_crafts: " . $delete_crafts_stmt->error);
        throw new Exception("Database error: " . $delete_crafts_stmt->error);
    }
    
    $crafts_deleted = $delete_crafts_stmt->affected_rows;
    error_log("Deleted $crafts_deleted contract_crafts records");
    $delete_crafts_stmt->close();

    // 3. Verify contract exists before deletion
    error_log("Verifying contract exists");
    $check_sql = "SELECT id FROM contracts WHERE id = ? FOR UPDATE";
    $check_stmt = $mysqli->prepare($check_sql);
    
    if (!$check_stmt) {
        error_log("Prepare failed for contract check: " . $mysqli->error);
        throw new Exception("Database error: " . $mysqli->error);
    }
    
    $check_stmt->bind_param("i", $contract_id);
    if (!$check_stmt->execute()) {
        error_log("Execute failed for contract check: " . $check_stmt->error);
        throw new Exception("Database error: " . $check_stmt->error);
    }
    
    $check_stmt->store_result();
    $contract_exists = $check_stmt->num_rows > 0;
    $check_stmt->close();
    
    if (!$contract_exists) {
        error_log("Error: Contract $contract_id not found in database");
        throw new Exception("No contract found with that ID");
    }

    // 4. Delete the contract
    error_log("Deleting contract record");
    $delete_contract_sql = "DELETE FROM contracts WHERE id = ?";
    $delete_contract_stmt = $mysqli->prepare($delete_contract_sql);
    
    if (!$delete_contract_stmt) {
        error_log("Prepare failed for contract deletion: " . $mysqli->error);
        throw new Exception("Database error: " . $mysqli->error);
    }
    
    $delete_contract_stmt->bind_param("i", $contract_id);
    if (!$delete_contract_stmt->execute()) {
        error_log("Execute failed for contract deletion: " . $delete_contract_stmt->error);
        throw new Exception("Database error: " . $delete_contract_stmt->error);
    }
    
    $affected_rows = $delete_contract_stmt->affected_rows;
    $delete_contract_stmt->close();
    
    $mysqli->commit();
    error_log("Successfully deleted contract $contract_id");
    echo json_encode([
        "success" => true,
        "message" => "Contract deleted successfully"
    ]);

    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Exception during contract deletion: " . $e->getMessage());
        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
    }

$mysqli->close();
error_log("=== End of contract deletion process ===");
?>