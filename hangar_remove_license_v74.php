<?php
// hangar_remove_license.php

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'login_csrf_handler.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // --- Security Checks ---
    CSRFHandler::validateToken($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("Authentication required.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];

    // --- Input Validation ---
    $pilotId = filter_input(INPUT_POST, 'pilotId', FILTER_VALIDATE_INT);
    $validityField = trim($_POST['validityField'] ?? '');
    if (!$pilotId || empty($validityField)) {
        throw new Exception("Invalid pilot ID or validity field specified.", 400);
    }

    // --- Dynamic Whitelist Security Check ---
    $allowedFields = [];
    $stmt_get_fields = $mysqli->prepare("SELECT field_key FROM user_company_licence_fields WHERE company_id = ?");
    $stmt_get_fields->bind_param("i", $company_id);
    $stmt_get_fields->execute();
    $result_fields = $stmt_get_fields->get_result();
    while ($row = $result_fields->fetch_assoc()) {
        $allowedFields[] = $row['field_key'];
    }
    $stmt_get_fields->close();
    if (!in_array($validityField, $allowedFields)) {
        throw new Exception("Invalid or unauthorized validity field specified.", 400);
    }
    $documentColumn = $validityField . '_doc';

    // --- Database Operations ---
    $mysqli->begin_transaction();

    // 1. Get the path of the document to delete it from the server
    $sqlSelect = "SELECT `$documentColumn` FROM user_licence_data WHERE user_id = ?";
    $stmt_select = $mysqli->prepare($sqlSelect);
    if(!$stmt_select) throw new Exception("DB Prepare Error (Select): ".$mysqli->error);
    $stmt_select->bind_param("i", $pilotId);
    $stmt_select->execute();
    $oldPathResult = $stmt_select->get_result();
    $oldPath = null;
    if ($oldPathRow = $oldPathResult->fetch_assoc()) {
        $oldPath = $oldPathRow[$documentColumn];
    }
    $stmt_select->close();

    // 2. Update the database to set the document path to NULL
    $sqlUpdate = "UPDATE user_licence_data SET `$documentColumn` = NULL WHERE user_id = ?";
    $stmt_update = $mysqli->prepare($sqlUpdate);
    if(!$stmt_update) throw new Exception("DB Prepare Error (Update): ".$mysqli->error);
    $stmt_update->bind_param("i", $pilotId);
    if (!$stmt_update->execute()) {
        throw new Exception("Failed to update the database record.");
    }
    $stmt_update->close();
    
    // 3. If DB update was successful, commit and then delete the physical file
    $mysqli->commit();

    if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
        unlink(__DIR__ . '/' . $oldPath);
    }

    $response['success'] = true;
    $response['message'] = 'Document removed successfully.';

} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->in_transaction) {
        $mysqli->rollback();
    }
    $response['error'] = $e->getMessage();
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
}

echo json_encode($response);
?>