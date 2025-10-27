<?php
// manager_add_licence_field.php

session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'login_csrf_handler.php';
require_once 'login_permissions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request method.", 405);
    CSRFHandler::validateToken($_POST['csrf_token'] ?? '');

    $rolesThatCanManage = ['manager', 'admin', 'manager pilot', 'admin pilot']; // Define who can do this
    if (!userHasRole($rolesThatCanManage, $mysqli)) {
        throw new Exception("You do not have permission to manage licence fields.", 403);
    }
    $company_id = (int)$_SESSION['company_id'];
    
    // Validate inputs
    $field_label = trim($_POST['field_label'] ?? '');
    $field_key = trim($_POST['field_key'] ?? '');

    if (empty($field_label) || empty($field_key)) {
        throw new Exception("Both Field Label and Field Key are required.", 400);
    }
    // Enforce naming convention for the key (security)
    if (!preg_match('/^[a-z0-9_]+$/', $field_key)) {
        throw new Exception("Field Key must contain only lowercase letters, numbers, and underscores.", 400);
    }

    $mysqli->begin_transaction();

    // 1. Add the new column to the main data table
    $alterSql = "ALTER TABLE `user_licences_validity` ADD `{$field_key}` DATE NULL DEFAULT NULL, ADD `{$field_key}_doc` VARCHAR(255) NULL DEFAULT NULL";
    if (!$mysqli->query($alterSql)) {
        throw new Exception("Database Error: Could not add new columns to the validity table. The field key might already exist.");
    }
    
    // 2. Add the new field definition to our template table
    $insertSql = "INSERT INTO user_company_licence_fields (company_id, field_key, field_label) VALUES (?, ?, ?)";
    $stmt = $mysqli->prepare($insertSql);
    if (!$stmt) throw new Exception("DB Prepare Error: " . $mysqli->error);
    $stmt->bind_param("iss", $company_id, $field_key, $field_label);
    if (!$stmt->execute()) {
        // Check for duplicate key error
        if ($mysqli->errno === 1062) {
            throw new Exception("This Field Key already exists for your company. Please choose a unique key.");
        }
        throw new Exception("DB Execute Error: " . $stmt->error);
    }
    $stmt->close();

    $mysqli->commit();

    $response['success'] = true;
    $response['message'] = "New field '{$field_label}' was added successfully.";

} catch (Exception $e) {
    if ($mysqli->in_transaction) $mysqli->rollback();
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>