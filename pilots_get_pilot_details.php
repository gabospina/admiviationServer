<?php
// pilots_get_pilot_details.php - FINAL UNIFIED & SECURE VERSION

if (session_status() == PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'login_permissions.php'; // <-- FIX: Include the permissions file

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // --- Determine Pilot ID to fetch ---
    $pilotId = null;
    if (isset($_GET['pilot_id'])) {
        $pilotId = (int)$_GET['pilot_id'];
    } elseif (isset($_SESSION['HeliUser'])) {
        $pilotId = (int)$_SESSION['HeliUser'];
    }

    if (!$pilotId) {
        throw new Exception('User ID not specified or user not logged in.', 401);
    }
    
    if (!isset($_SESSION['company_id'])) {
        throw new Exception("Company context not set.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];

    // --- FIX: CHECK IF THE CURRENTLY LOGGED-IN USER IS A MANAGER ---
    // EXPLANATION: This is the core security check. We determine if the person *making the request*
    // has management privileges. We will use this to decide whether to send sensitive data.
    $managerRoles = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    $isManager = userHasRole($managerRoles, $mysqli);
    // --- END OF SECURITY CHECK ---
    
    // --- 1. Get Basic User Info ---
    $userQuery = "SELECT id, firstname, lastname, user_nationality, email, phone, phonetwo, 
                         username, nal_license, for_license, hire_date, profile_picture
                  FROM users WHERE id = ? AND company_id = ?";
    $stmtUser = $mysqli->prepare($userQuery);
    $stmtUser->bind_param("ii", $pilotId, $company_id);
    $stmtUser->execute();
    $userResult = $stmtUser->get_result();
    if ($userResult->num_rows === 0) throw new Exception('Pilot not found in your company.', 404);
    $userData = $userResult->fetch_assoc();
    $stmtUser->close();

    // --- 2. Fetch the STANDARD list of fields for the company ---
    $standardFields = [];
    $stmtFields = $mysqli->prepare("SELECT field_key, field_label FROM user_company_licence_fields WHERE company_id = ? ORDER BY display_order, field_label ASC");
    $stmtFields->bind_param("i", $company_id);
    $stmtFields->execute();
    $resultFields = $stmtFields->get_result();
    while ($row = $resultFields->fetch_assoc()) {
        $standardFields[] = $row;
    }
    $stmtFields->close();

    // --- 3. REFACTORED: Fetch the SPECIFIC validity data ---
    $pilotValidityData = [];
    $sqlData = "SELECT field_key, expiry_date, document_path FROM user_licence_data WHERE user_id = ?";
    $stmtData = $mysqli->prepare($sqlData);
    if ($stmtData) {
        $stmtData->bind_param("i", $pilotId);
        $stmtData->execute();
        $resultData = $stmtData->get_result();
        while ($row = $resultData->fetch_assoc()) {
            $key = $row['field_key'];
            $pilotValidityData[$key] = $row['expiry_date'];

            // --- FIX: CONDITIONALLY ADD THE DOCUMENT PATH ---
            // EXPLANATION: We only add the sensitive document path to the response array
            // if our security check from above confirms the user is a manager.
            if ($isManager) {
                $pilotValidityData[$key . '_doc'] = $row['document_path'];
            }
            // --- END OF CONDITIONAL FIX ---
        }
        $stmtData->close();
    }
    
    // --- 4 & 5. Get Assigned Crafts & Contracts (No changes needed here) ---
    $craftQuery = "SELECT craft_type, position FROM pilot_craft_type WHERE user_id = ?";
    $stmtCrafts = $mysqli->prepare($craftQuery);
    $stmtCrafts->bind_param("i", $pilotId);
    $stmtCrafts->execute();
    $assignedCrafts = $stmtCrafts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtCrafts->close();

    $contractQuery = "SELECT c.contract_name FROM contract_pilots cp JOIN contracts c ON cp.contract_id = c.id WHERE cp.user_id = ?";
    $stmtContracts = $mysqli->prepare($contractQuery);
    $stmtContracts->bind_param("i", $pilotId);
    $stmtContracts->execute();
    $assignedContracts = $stmtContracts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtContracts->close();

    // Combine all data into the final response
    $response = [
        'success' => true,
        'data' => [
            'details' => $userData,
            'standard_validity_fields' => $standardFields,
            'pilot_validity_data' => $pilotValidityData,
            'assigned_crafts' => $assignedCrafts,
            'assigned_contracts' => $assignedContracts
        ]
    ];

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>