<?php
// contract_edit_contract.php (FINAL - WITH CRAFT/PILOT UPDATES)
header('Content-Type: application/json');
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['HeliUser']) || $_SESSION['HeliUser'] == 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;
$contract_name = isset($_POST['contract_name']) ? trim($_POST['contract_name']) : '';
$customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
$color = isset($_POST['color']) ? trim($_POST['color']) : '';
// --- NEW: Get the assigned crafts and pilots ---
$craft_ids = $_POST['craft_ids'] ?? [];
$pilot_ids = $_POST['pilot_ids'] ?? [];

// --- Validation (No changes needed here) ---
if ($contract_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid contract ID']);
    exit;
}
if (empty($contract_name)) {
    echo json_encode(['success' => false, 'message' => 'Contract name cannot be empty']);
    exit;
}
if ($customer_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
    exit;
}
if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
    echo json_encode(['success' => false, 'message' => 'Invalid color format. Use hex format like #FFFFFF']);
    exit;
}

try {
    $mysqli->begin_transaction();

    // 1. Update the main contracts table
    $stmt = $mysqli->prepare("UPDATE contracts SET contract_name = ?, customer_id = ?, color = ? WHERE id = ?");
    $stmt->bind_param("sisi", $contract_name, $customer_id, $color, $contract_id);
    $stmt->execute();
    $stmt->close();

    // 2. --- FIX: Update Craft Assignments (Delete then Insert) ---
    $stmt_del_crafts = $mysqli->prepare("DELETE FROM contract_crafts WHERE contract_id = ?");
    $stmt_del_crafts->bind_param("i", $contract_id);
    $stmt_del_crafts->execute();
    $stmt_del_crafts->close();
    
    if (!empty($craft_ids)) {
        $stmt_ins_crafts = $mysqli->prepare("INSERT INTO contract_crafts (contract_id, craft_id) VALUES (?, ?)");
        foreach ($craft_ids as $craft_id) {
            // --- THIS IS THE FIX ---
            // Cast to an integer and store in a new variable first.
            $craft_id_int = (int)$craft_id;
            $stmt_ins_crafts->bind_param("ii", $contract_id, $craft_id_int);
            // --- END OF FIX ---
            $stmt_ins_crafts->execute();
        }
        $stmt_ins_crafts->close();
    }

    // 3. --- FIX: Update Pilot Assignments (Delete then Insert) ---
    $stmt_del_pilots = $mysqli->prepare("DELETE FROM contract_pilots WHERE contract_id = ?");
    $stmt_del_pilots->bind_param("i", $contract_id);
    $stmt_del_pilots->execute();
    $stmt_del_pilots->close();

    if (!empty($pilot_ids)) {
        $stmt_ins_pilots = $mysqli->prepare("INSERT INTO contract_pilots (contract_id, user_id) VALUES (?, ?)");
        foreach ($pilot_ids as $pilot_id) {

            $pilot_id_int = (int)$pilot_id;
            $stmt_ins_pilots->bind_param("ii", $contract_id, $pilot_id_int);
            $stmt_ins_pilots->execute();
        }
        $stmt_ins_pilots->close();
    }
    
    $mysqli->commit();
    echo json_encode(['success' => true, 'message' => 'Contract updated successfully']);

} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$mysqli->close();
?>