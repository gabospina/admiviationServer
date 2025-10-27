<?php
// hangar_change_profile_picture.php (FINAL, CORRECTED PATH)

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Wrap the entire script in a try/catch to guarantee a JSON response
try {
    require_once 'db_connect.php';
    
    $response = ['success' => false, 'error' => 'An unknown error occurred.'];

    if (!isset($_SESSION['HeliUser'])) {
        throw new Exception("Authentication required.", 401);
    }
    if (empty($_FILES['file'])) {
        throw new Exception("No file was uploaded.", 400);
    }

    $pilot_id = (int)$_SESSION['HeliUser'];
    $file = $_FILES['file'];

    // --- File Validation ---
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) throw new Exception("Invalid file type.", 415);
    if ($file['size'] > 2 * 1024 * 1024) throw new Exception("File is too large (Max 2MB).", 413);
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("File upload error code: " . $file['error']);

    // --- File Processing ---
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = 'pilot_' . $pilot_id . '_' . time() . '.' . $extension;
    
    // =================================================================
    // === THE FIX: Use the correct path relative to the web root    ===
    // =================================================================
    // This assumes your script is in the main directory like /Admviation1/
    $destination_folder = 'uploads/pictures/';
    $destination_path = $destination_folder . $new_filename;
    // =================================================================

    // Check if the destination directory exists and is writable
    if (!is_dir($destination_folder) || !is_writable($destination_folder)) {
        throw new Exception("Server configuration error: Upload directory is not writable.");
    }

    // --- Database Interaction ---
    $mysqli->begin_transaction();
    
    // Get old filename to delete it after successful upload
    $stmt_old = $mysqli->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt_old->bind_param('i', $pilot_id);
    $stmt_old->execute();
    $old_filename = $stmt_old->get_result()->fetch_object()->profile_picture ?? null;
    $stmt_old->close();

    // Move the new file BEFORE updating the database
    if (move_uploaded_file($file['tmp_name'], $destination_path)) {
        // Update the database with the new filename
        $stmt_update = $mysqli->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt_update->bind_param('si', $new_filename, $pilot_id);
        
        if ($stmt_update->execute()) {
            $mysqli->commit();
            // Success! Now delete the old file
            if ($old_filename && file_exists($destination_folder . $old_filename)) {
                unlink($destination_folder . $old_filename);
            }
            $response['success'] = true;
            $response['filename'] = $new_filename;
        } else {
            $mysqli->rollback();
            unlink($destination_path); // Delete the newly uploaded file if DB update fails
            throw new Exception("Failed to update database record.");
        }
        $stmt_update->close();
    } else {
        $mysqli->rollback();
        throw new Exception("Failed to move uploaded file to destination.");
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $code = $e->getCode();
    http_response_code($code >= 400 ? $code : 500);
}

echo json_encode($response);
?>