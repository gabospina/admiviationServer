<?php
// daily_manager_ai_pilot_creation.php

/**
 * Creates a new pilot in the database within a transaction.
 *
 * @param array $pilotData An associative array of pilot data (firstname, lastname, etc.)
 * @param mysqli $mysqli The active database connection object.
 * @param int $company_id The ID of the company the pilot belongs to.
 * @return array An array with the result ['success' => bool, 'message' => string, ...].
 */
function create_new_pilot(array $pilotData, mysqli $mysqli, int $company_id) {
    try {
        // --- 1. Validate and Sanitize Inputs ---
        $required = ['firstname', 'lastname', 'email', 'username', 'password', 'confpassword'];
        foreach ($required as $field) {
            if (empty($pilotData[$field])) {
                throw new Exception("Missing required field for pilot creation: $field", 400);
            }
        }
        if ($pilotData['password'] !== $pilotData['confpassword']) {
            throw new Exception("Passwords do not match.", 400);
        }

        $email = filter_var($pilotData['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) throw new Exception("Invalid email format.", 400);
        $username = trim($pilotData['username']);
        $firstname = trim($pilotData['firstname']);
        $lastname = trim($pilotData['lastname']);
        
        // --- 2. Check for Duplicates ---
        $stmt_check = $mysqli->prepare("SELECT id FROM users WHERE company_id = ? AND (LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?))");
        $stmt_check->bind_param("iss", $company_id, $username, $email);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            throw new Exception("Username or email already exists in this company.", 400);
        }
        $stmt_check->close();

        // --- 3. Database Transaction ---
        $mysqli->begin_transaction();

        // --- 4. Insert into users table ---
        $stmt_user = $mysqli->prepare(
            "INSERT INTO users (company_id, firstname, lastname, email, username, password, is_active, access_level, admin) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt_user) throw new Exception("DB Prepare Error (users): " . $mysqli->error);
        
        $password_hash = password_hash($pilotData['password'], PASSWORD_DEFAULT);
        
        $stmt_user->bind_param(
            "issssssii",
            $company_id, $firstname, $lastname, $email, $username, $password_hash,
            $pilotData['is_active'], $pilotData['access_level'], $pilotData['admin']
        );
        
        if (!$stmt_user->execute()) {
            throw new Exception("Failed to create user: " . $stmt_user->error);
        }
        
        $new_pilot_id = $stmt_user->insert_id;
        $stmt_user->close();
        
        // (In the future, you could add logic here to insert into 'validity' or 'user_has_roles' tables)

        // --- 5. Commit ---
        $mysqli->commit();
        
        return ['success' => true, 'message' => "Pilot account for {$firstname} {$lastname} created successfully", 'pilot_id' => $new_pilot_id];

    } catch (Exception $e) {
        if ($mysqli->thread_id && $mysqli->in_transaction) {
            $mysqli->rollback();
        }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>