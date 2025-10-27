<?php
// schedule_update.php (FINAL, CORRECTED VERSION)

if (session_status() == PHP_SESSION_NONE) { session_start(); }

date_default_timezone_set('UTC');
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        http_response_code(401);
        throw new Exception("Session expired or invalid.");
    }
    $loggedInUserId = (int)$_SESSION["HeliUser"];
    $loggedInCompanyId = (int)$_SESSION["company_id"];

    $requiredFields = ['pk', 'value', 'pos', 'registration', 'craftType'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field])) throw new Exception("Missing field: {$field}", 400);
    }
    
    $sched_date = $_POST['pk'];
    $pilotIdToAssign = (int)$_POST['value'];
    $positionRole = $_POST['pos'];
    $registration = $_POST['registration'];
    $craftType = $_POST['craftType'];
    
    $mysqli->begin_transaction();
    
    // Find if a record for this slot already exists
    $stmt_lookup = $mysqli->prepare("SELECT id FROM schedule WHERE sched_date = ? AND registration = ? AND pos = ? AND company_id = ?");
    $stmt_lookup->bind_param("sssi", $sched_date, $registration, $positionRole, $loggedInCompanyId);
    $stmt_lookup->execute();
    $currentAssignmentId = $stmt_lookup->get_result()->fetch_assoc()['id'] ?? null;
    $stmt_lookup->close();

    if ($pilotIdToAssign === 0) { // Un-assign
        if ($currentAssignmentId) {
            $stmt = $mysqli->prepare("DELETE FROM schedule WHERE id = ?");
            $stmt->bind_param("i", $currentAssignmentId);
            $stmt->execute();
            $response = ['success' => true, 'action' => 'removed', 'removed_schedule_id' => $currentAssignmentId];
        } else {
            $response = ['success' => true, 'action' => 'nothing_to_remove'];
        }
    } else { // Assign or Update
        if ($currentAssignmentId) { // Update existing
            $stmt = $mysqli->prepare("UPDATE schedule SET user_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $pilotIdToAssign, $currentAssignmentId);
            $stmt->execute();
            // --- THE FIX: Build the correct response for an UPDATE ---
            $response = [
                'success' => true,
                'action' => 'updated',
                'message' => 'Schedule updated successfully.',
                'schedule_id' => $currentAssignmentId // Return the ID of the record we just updated
            ];
        } else { // Insert new
            $stmt = $mysqli->prepare("INSERT INTO schedule (user_id, sched_date, craft_type, registration, pos, created_by, company_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssii", $pilotIdToAssign, $sched_date, $craftType, $registration, $positionRole, $loggedInUserId, $loggedInCompanyId);
            $stmt->execute();
            // --- THE FIX: Build the correct response for an INSERT ---
            $response = [
                'success' => true,
                'action' => 'added',
                'message' => 'Schedule added successfully.',
                'new_schedule_id' => $mysqli->insert_id
            ];
        }
    }
    
    $mysqli->commit();

} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->in_transaction) $mysqli->rollback();
    $response['error'] = $e->getMessage();
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

echo json_encode($response);
?>