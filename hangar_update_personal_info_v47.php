<?php
// hangar_update_personal_info.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    if (!isset($_SESSION['HeliUser'])) {
        http_response_code(401);
        throw new Exception("Authentication required.");
    }
    $pilot_id = (int)$_SESSION['HeliUser'];

    // Sanitize all possible inputs from the form
    $firstname = trim(filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_STRING));
    $lastname = trim(filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_STRING));
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $nationality = trim(filter_input(INPUT_POST, 'user_nationality', FILTER_SANITIZE_STRING));
    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
    $phonetwo = trim(filter_input(INPUT_POST, 'phonetwo', FILTER_SANITIZE_STRING));
    $nal_license = trim(filter_input(INPUT_POST, 'nal_license', FILTER_SANITIZE_STRING));
    $for_license = trim(filter_input(INPUT_POST, 'for_license', FILTER_SANITIZE_STRING));

    if (empty($firstname) || empty($lastname) || !$email) {
        throw new Exception("First Name, Last Name, and a valid Email are required.", 400);
    }
    
    // --- Update the User's Details ---
    $stmt = $mysqli->prepare(
        "UPDATE users SET firstname = ?, lastname = ?, email = ?, user_nationality = ?, 
         phone = ?, phonetwo = ?, nal_license = ?, for_license = ? 
         WHERE id = ?"
    );
    if (!$stmt) throw new Exception("DB Prepare Failed: " . $mysqli->error);

    $stmt->bind_param("ssssssssi", 
        $firstname, $lastname, $email, $nationality, $phone, $phonetwo, $nal_license, $for_license, $pilot_id
    );
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Personal information updated successfully.';
    } else {
        throw new Exception("Database error on update: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

echo json_encode($response);
?>