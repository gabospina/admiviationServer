<?php
// pilots_get_pilot_details.php - FINAL UNIFIED VERSION (Serves both hangar.php and pilots.php)

if (session_status() == PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // --- THIS IS THE CORE FIX: Smartly determine which pilot's data to fetch ---
    $pilotId = null;
    if (isset($_GET['pilot_id'])) {
        // Use Case 1: A manager on pilots.php is requesting a specific pilot's details.
        $pilotId = (int)$_GET['pilot_id'];
    } elseif (isset($_SESSION['HeliUser'])) {
        // Use Case 2: A pilot on hangar.php is requesting their own details.
        // The AJAX call from hangar.php has no pilot_id, so we fall back to the session.
        $pilotId = (int)$_SESSION['HeliUser'];
    }

    // Now, validate that we have a valid ID from one of the sources.
    if (!$pilotId) {
        throw new Exception('User ID not specified or user not logged in.', 401);
    }
    // --- END OF CORE FIX ---
    
    // We also need the company_id for our queries
    if (!isset($_SESSION['company_id'])) {
        throw new Exception("Company context not set.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];
    
    // --- 1. Get Basic User Info ---
    // This query is now secure because it checks both the determined pilotId AND the session company_id.
    $userQuery = "SELECT id, firstname, lastname, user_nationality, email, phone, phonetwo, 
                         username, nal_license, for_license, hire_date, profile_picture
                  FROM users WHERE id = ? AND company_id = ?";
                  
    $stmtUser = $mysqli->prepare($userQuery);
    if (!$stmtUser) throw new Exception('DB Error (user details): ' . $mysqli->error);
    $stmtUser->bind_param("ii", $pilotId, $company_id);
    $stmtUser->execute();
    $userResult = $stmtUser->get_result();
    if ($userResult->num_rows === 0) throw new Exception('Pilot not found in your company.', 404);
    $userData = $userResult->fetch_assoc();
    $stmtUser->close();

    // --- 2. Fetch the STANDARD list of fields for the company ---
    $standardFields = [];
    $stmtFields = $mysqli->prepare(
        "SELECT field_key, field_label FROM user_company_licence_fields WHERE company_id = ? ORDER BY display_order, field_label ASC"
    );
    $stmtFields->bind_param("i", $company_id);
    $stmtFields->execute();
    $resultFields = $stmtFields->get_result();
    while ($row = $resultFields->fetch_assoc()) {
        $standardFields[] = $row;
    }
    $stmtFields->close();

    // --- 3. REFACTORED: Fetch the SPECIFIC validity data for THIS pilot from the NEW table ---
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
            $pilotValidityData[$key . '_doc'] = $row['document_path'];
        }
        $stmtData->close();
    }
    
    // --- 4. Get Assigned Craft Types ---
    $craftQuery = "SELECT craft_type, position FROM pilot_craft_type WHERE user_id = ?";
    $stmtCrafts = $mysqli->prepare($craftQuery);
    $stmtCrafts->bind_param("i", $pilotId);
    $stmtCrafts->execute();
    $assignedCrafts = $stmtCrafts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtCrafts->close();

    // --- 5. Get Assigned Contracts ---
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