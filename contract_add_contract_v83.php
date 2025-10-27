<?php
// contract_add_contract.php - v83 CORRECTED VERSION

// This will catch fatal errors and allow us to send a clean JSON response
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        error_log("Fatal error in contract_add_contract.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        echo json_encode(['success' => false, 'message' => 'A critical server error occurred. Please contact support.']);
        exit;
    }
});

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

header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'login_permissions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.', 'new_csrf_token' => $_SESSION['csrf_token']];

try {
    // --- Security Checks ---
    $rolesThatCanManage = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    if (!userHasRole($rolesThatCanManage, $mysqli)) {
        throw new Exception("You do not have permission to manage contracts.", 403);
    }
    
    if (!isset($_SESSION['company_id'])) {
        throw new Exception("Company context is not set.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];

    // --- Input Validation ---
    $contract_name = trim($_POST['contract_name'] ?? '');
    $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
    $color = trim($_POST['color'] ?? '#000000');
    $craft_ids = $_POST['craft_ids'] ?? [];
    $pilot_ids = $_POST['pilot_ids'] ?? [];

    if (empty($contract_name) || !$customer_id || empty($craft_ids)) {
        throw new Exception("Contract Name, Customer, and at least one Craft are required.", 400);
    }
    $craft_ids = array_map('intval', (array)$craft_ids);
    $pilot_ids = array_map('intval', (array)$pilot_ids);

    // --- Database Operations ---
    $mysqli->begin_transaction();

    // 1. Insert the main contract record
    $stmtContract = $mysqli->prepare(
        "INSERT INTO contracts (company_id, contract_name, customer_id, color) VALUES (?, ?, ?, ?)"
    );
    if (!$stmtContract) throw new Exception("DB Prepare Error (Contract): " . $mysqli->error);

    // ✅ CORRECTED: Parameter binding
    $stmtContract->bind_param("isis", 
        $company_id,      // i - integer
        $contract_name,   // s - string  
        $customer_id,     // i - integer
        $color            // s - string
    );

    if (!$stmtContract->execute()) throw new Exception("DB Execute Error (Contract): " . $stmtContract->error);
    $new_contract_id = $stmtContract->insert_id;
    $stmtContract->close();

    // 2. Link the selected crafts
    $stmtCrafts = $mysqli->prepare("INSERT INTO contract_crafts (contract_id, craft_id) VALUES (?, ?)");
    if (!$stmtCrafts) throw new Exception("DB Prepare Error (Crafts): " . $mysqli->error);
    foreach ($craft_ids as $craft_id) {
        if ($craft_id > 0) {
            $stmtCrafts->bind_param("ii", $new_contract_id, $craft_id);
            $stmtCrafts->execute();
        }
    }
    $stmtCrafts->close();

    // 3. Link the selected pilots
    if (!empty($pilot_ids)) {
        $stmtPilots = $mysqli->prepare("INSERT INTO contract_pilots (contract_id, user_id) VALUES (?, ?)");
        if (!$stmtPilots) throw new Exception("DB Prepare Error (Pilots): " . $mysqli->error);
        foreach ($pilot_ids as $pilot_id) {
            if ($pilot_id > 0) {
                $stmtPilots->bind_param("ii", $new_contract_id, $pilot_id);
                $stmtPilots->execute();
            }
        }
        $stmtPilots->close();
    }
    
    $mysqli->commit();

    $response['success'] = true;
    $response['message'] = "Contract '{$contract_name}' created successfully!";

} catch (Exception $e) {
    if (method_exists($mysqli, 'rollback')) {
        $mysqli->rollback();
    }
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>