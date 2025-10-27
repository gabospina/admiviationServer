<?php
// schedule_update.php v83 (FINAL, COMPLETE, AND ROBUST VERSION)

// Suppress any stray PHP warnings/notices to guarantee a clean JSON output.
@ini_set('display_errors', 0);
@error_reporting(0);

// Set the content type to JSON at the very beginning.
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}

// ✅ PLACE DEBUG BLOCK HERE - RIGHT AFTER SESSION_START()
error_log("=== SCHEDULE UPDATE DEBUG ===");
error_log("DEBUG: POST data received: " . print_r($_POST, true));
error_log("DEBUG: CSRF token in POST: " . ($_POST['form_token'] ?? 'NOT FOUND'));
error_log("DEBUG: CSRF token in session: " . ($_SESSION['csrf_token'] ?? 'NOT FOUND'));
error_log("Session ID: " . session_id());
// ✅ END DEBUG BLOCK

// --- ADD CSRF PROTECTION ---
require_once 'login_csrf_handler.php';

// Set a consistent timezone for all date operations.
date_default_timezone_set('UTC');

// Include the database connection file.
require_once 'db_connect.php';

// Initialize a default response object.
$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // --- 1. CSRF VALIDATION ---
    if (!CSRFHandler::validateToken($_POST['form_token'] ?? '')) {
        // Token validation failed - regenerate for security
        $response['new_csrf_token'] = CSRFHandler::generateToken();
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    // --- 2. SESSION & PERMISSION VALIDATION ---
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        http_response_code(401);
        throw new Exception("Session expired or invalid. Please log in again.");
    }
    
    $loggedInUserId = (int)$_SESSION["HeliUser"];
    $loggedInCompanyId = (int)$_SESSION["company_id"];
    
    // Add a high-level permission check here if needed in the future
    // require_once 'permissions.php';
    // if (!canManageDispatch()) { throw new Exception("Permission Denied.", 403); }

    // --- 2. INPUT VALIDATION ---
    // Check for the existence of all required POST parameters.
    $requiredFields = ['pk', 'value', 'pos', 'registration', 'craftType'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field])) {
            throw new Exception("Missing required parameter: {$field}", 400);
        }
    }
    
    // Sanitize and assign variables from the POST data.
    $sched_date = $_POST['pk']; // The date string, e.g., "2025-09-10"
    $pilotIdToAssign = (int)$_POST['value']; // The user_id to assign, or 0 to un-assign
    $positionRole = $_POST['pos']; // e.g., "PIC" or "SIC"
    $registration = $_POST['registration'];
    $craftType = $_POST['craftType'];

    // --- 3. DATABASE CONNECTION CHECK ---
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection could not be established.");
    }

    // --- 4. START DATABASE TRANSACTION ---
    $mysqli->begin_transaction();
    
    // --- 5. LOGIC: Check if a schedule entry for this exact slot already exists ---
    $stmt_lookup = $mysqli->prepare("SELECT id FROM schedule WHERE sched_date = ? AND registration = ? AND pos = ? AND company_id = ?");
    if (!$stmt_lookup) throw new Exception("DB Error (Lookup Prepare): " . $mysqli->error);
    
    $stmt_lookup->bind_param("sssi", $sched_date, $registration, $positionRole, $loggedInCompanyId);
    $stmt_lookup->execute();
    $currentAssignmentId = $stmt_lookup->get_result()->fetch_assoc()['id'] ?? null;
    $stmt_lookup->close();

    // --- 6. CORE LOGIC: Decide whether to INSERT, UPDATE, or DELETE ---

    if ($pilotIdToAssign === 0) { // Un-assign Action
        if ($currentAssignmentId) {
            $stmt = $mysqli->prepare("DELETE FROM schedule WHERE id = ?");
            if (!$stmt) throw new Exception("DB Error (Delete Prepare): " . $mysqli->error);
            
            $stmt->bind_param("i", $currentAssignmentId);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $response = ['success' => true, 'action' => 'removed', 'schedule_id' => $currentAssignmentId];
            } else {
                // This case is unlikely but possible if the row was deleted by another user.
                throw new Exception("Delete failed: The schedule entry was not found.");
            }
        } else {
            // Nothing to delete, but the action is technically successful.
            $response = ['success' => true, 'action' => 'nothing_to_remove'];
        }
    } else { // Assign or Update Action
        if ($currentAssignmentId) { // Update an existing entry
            $stmt = $mysqli->prepare("UPDATE schedule SET user_id = ? WHERE id = ?");
            if (!$stmt) throw new Exception("DB Error (Update Prepare): " . $mysqli->error);
            
            $stmt->bind_param("ii", $pilotIdToAssign, $currentAssignmentId);
            $stmt->execute();
            
            // Note: affected_rows can be 0 if the same pilot is assigned again. This is a success.
            $response = ['success' => true, 'action' => 'updated', 'schedule_id' => $currentAssignmentId];

        } else { // Insert a new entry
            $stmt = $mysqli->prepare("INSERT INTO schedule (user_id, sched_date, craft_type, registration, pos, created_by, company_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) throw new Exception("DB Error (Insert Prepare): " . $mysqli->error);

            $stmt->bind_param("issssii", $pilotIdToAssign, $sched_date, $craftType, $registration, $positionRole, $loggedInUserId, $loggedInCompanyId);
            
            if (!$stmt->execute()) {
                // Provide the specific MySQL error for better debugging.
                throw new Exception("DB Error (Insert Execute): " . $stmt->error);
            }
            
            if ($stmt->affected_rows > 0) {
                $newId = $mysqli->insert_id;
                if ($newId > 0) {
                    $response = ['success' => true, 'action' => 'added', 'new_schedule_id' => $newId];
                } else {
                    // This error will trigger if the AUTO_INCREMENT is corrupted.
                    throw new Exception("INSERT successful, but a valid new ID was not generated.");
                }
            } else {
                throw new Exception("INSERT failed: No rows were affected.");
            }
        }
    }
    
    // Close the statement if it was created
    if (isset($stmt)) {
        $stmt->close();
    }
    
    // --- 7. COMMIT TRANSACTION ---
    $mysqli->commit();

} catch (Exception $e) {
    // If anything fails, roll back the transaction.
    if (isset($mysqli) && $mysqli->ping()) { // Check if connection is still alive
       @$mysqli->rollback(); // Use '@' to suppress warnings if no transaction is active
    }
    
    // Set the response error message and HTTP code.
    $response['error'] = $e->getMessage();
    $httpStatusCode = $e->getCode() >= 400 ? $e->getCode() : 500;
    if ($httpStatusCode) {
        http_response_code($httpStatusCode);
    }
    
    // Log the error to the server's error log for the developer to see.
    error_log("schedule_update.php ERROR: " . $e->getMessage());
}

// --- 8. SEND FINAL JSON RESPONSE ---
echo json_encode($response);
exit; // Ensure no other output is sent.
?>