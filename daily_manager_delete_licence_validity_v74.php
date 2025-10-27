<?php
// daily_manager_delete_licence_validity.php

session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'login_csrf_handler.php';
require_once 'login_permissions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request method.", 405);
    CSRFHandler::validateToken($_POST['csrf_token'] ?? '');

    $rolesThatCanManage = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    if (!userHasRole($rolesThatCanManage, $mysqli)) {
        throw new Exception("You do not have permission to manage licence fields.", 403);
    }
    $company_id = (int)$_SESSION['company_id'];

    $field_id = filter_input(INPUT_POST, 'field_id', FILTER_VALIDATE_INT);
    if (!$field_id) {
        throw new Exception("Invalid Field ID provided.", 400);
    }

    $mysqli->begin_transaction();

    // 1. Get the 'field_key' from our template table before we delete the row
    $stmtGet = $mysqli->prepare("SELECT field_key FROM user_company_licence_fields WHERE id = ? AND company_id = ?");
    if (!$stmtGet) throw new Exception("DB Prepare Error (Get Key): " . $mysqli->error);
    $stmtGet->bind_param("ii", $field_id, $company_id);
    $stmtGet->execute();
    $result = $stmtGet->get_result();
    if ($result->num_rows === 0) {
        throw new Exception("Field not found or you do not have permission to delete it.", 404);
    }
    $row = $result->fetch_assoc();
    $field_key = $row['field_key'];
    $stmtGet->close();

    // 2. Delete the row from our template table
    $stmtDelete = $mysqli->prepare("DELETE FROM user_company_licence_fields WHERE id = ?");
    $stmtDelete->bind_param("i", $field_id);
    $stmtDelete->execute();
    $stmtDelete->close();

    // 3. Drop the columns from the main data table
    $alterSql = "ALTER TABLE `user_licences_validity` DROP COLUMN `{$field_key}`, DROP COLUMN `{$field_key}_doc`";
    if (!$mysqli->query($alterSql)) {
        // This is a serious error, rollback everything
        throw new Exception("Database Error: Could not remove columns from the validity table.");
    }

    $mysqli->commit();

    $response['success'] = true;
    $response['message'] = "Field '{$field_key}' was deleted successfully.";

} catch (Exception $e) {
    if ($mysqli->in_transaction) $mysqli->rollback();
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>