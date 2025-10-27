<?php
// date_default_timezone_set('UTC');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'date_utils.php';

// Verify session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Add console.log equivalent for PHP
error_log("update_schedule.php: Current user ID: " . $_SESSION['user_id']);

try {
    // Validate inputs
    $required = ['pk', 'value', 'pos', 'registration']; // Removed craftType
    foreach ($required as $field) {
        if (!isset($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $date = $mysqli->real_escape_string($_POST['pk']);
    $pilotId = (int)$_POST['value'];
    $position = $mysqli->real_escape_string($_POST['pos']);
    $otherPil = $mysqli->real_escape_string($_POST['otherPil'] ?? '');
    $registration = $mysqli->real_escape_string($_POST['registration']); // registration

    // Fetch craft_type from crafts table based on registration
    $craftTypeStmt = $mysqli->prepare("SELECT craft_type FROM crafts WHERE registration = ?");
    $craftTypeStmt->bind_param("s", $registration);
    $craftTypeStmt->execute();
    $craftTypeResult = $craftTypeStmt->get_result();

    if ($craftTypeResult->num_rows === 0) {
        throw new Exception("Invalid registration: $registration");
    }

    $craftType = $craftTypeResult->fetch_assoc()['craft_type'];

    // Validate date is not in the past
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $scheduleDate = new DateTime($date);
    if ($scheduleDate < $today) {
        throw new Exception("Cannot modify past schedules");
    }

    $mysqli->begin_transaction();

    // Check existing assignment - INCLUDE REGISTRATION!
    $stmt = $mysqli->prepare("SELECT id, user_id FROM schedule
                        WHERE sched_date = ? AND craft_type = ? AND registration = ? AND pos = ?");
    $stmt->bind_param("ssss", $date, $craftType, $registration, $position);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($pilotId === 0) {
        // Remove assignment
        if ($existing) {
            $stmt = $mysqli->prepare("DELETE FROM schedule
                                    WHERE sched_date = ? AND craft_type = ? AND registration = ? AND pos = ?");
            $stmt->bind_param("ssss", $date, $craftType, $registration, $position);
            $action = 'removed';
        } else {
            // Nothing to delete
            $action = 'none';
        }
    } elseif ($existing) {
        // Update existing
        $stmt = $mysqli->prepare("UPDATE schedule
                                SET user_id = ?, registration = ?, otherPil = ?, updated_at = NOW()
                                WHERE id = ?");
        $stmt->bind_param("isss", $pilotId, $registration, $otherPil, $existing['id']);
        $action = 'updated';
    } else {
        // Insert new
        $stmt = $mysqli->prepare("INSERT INTO schedule
                                (user_id, sched_date, craft_type, registration, pos, otherPil, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $pilotId, $date, $craftType, $registration, $position, $otherPil, $_SESSION['user_id']);
        $action = 'added';
    }

    // Only execute if there's an action to perform
    if ($action !== 'none' && !$stmt->execute()) {
        throw new Exception("Failed to $action schedule");
    }

    // Get schedule ID for logging
    $scheduleId = $existing['id'] ?? $mysqli->insert_id;

    // Log to schedule_details table
    if ($action !== 'none') {
        $logValue = json_encode([
            'pilot_id' => $pilotId,
            'action' => $action,
            'by_user' => $_SESSION['user_id'],
            'craft_type' => $craftType, //added to correctly display the craft_type
            'registration' => $registration,
            'position' => $position,
            'other_pilot' => $otherPil,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        $detailsStmt = $mysqli->prepare("INSERT INTO schedule_details
                                       (date, craft_type, registration, value)
                                       VALUES (?, ?, ?, ?)");
        $detailsStmt->bind_param("ssss", $date, $craftType, $registration, $logValue);

        if (!$detailsStmt->execute()) {
            throw new Exception("Failed to log schedule details");
        }
    }

    $mysqli->commit();

    // Return success with pilot name
    $pilotName = '';
    if ($pilotId > 0) {
        $nameStmt = $mysqli->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
        $nameStmt->bind_param("i", $pilotId);
        $nameStmt->execute();
        $pilot = $nameStmt->get_result()->fetch_assoc();
        $pilotName = $pilot ? $pilot['firstname'][0] . '. ' . $pilot['lastname'] : '';
    }

    echo json_encode([
        'success' => true,
        'name' => $pilotName,
        'id' => $pilotId,
        'action' => $action
    ]);

} catch (Exception $e) {
    if ($mysqli->inTransaction()) {
        $mysqli->rollback();
    }
    error_log("Schedule Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    // error_log("Processing date: " . json_encode([
    //     'received' => $_POST['date'] ?? $_POST['pk'] ?? null,
    //     'processed' => $date ?? null,
    //     'timezone' => date_default_timezone_get()
    // ]));
}
?>