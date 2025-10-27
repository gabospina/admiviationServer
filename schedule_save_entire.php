<?php
// schedule_save_entire.php
date_default_timezone_set('UTC');
session_start();

// --- SESSION-BASED CSRF VALIDATION ---
$submitted_token = $_POST['form_token'] ?? '';

if (empty($submitted_token)) {
    throw new Exception("Security token missing. Please refresh the page.", 403);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    throw new Exception("Invalid security token. Please refresh the page.", 403);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- ADD CSRF PROTECTION ---
// REMOVED: // REMOVED: require_once 'login_csrf_handler.php';

header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'date_utils.php';

// --- CSRF VALIDATION ---
if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        // Token validation failed - regenerate for security
        $response['new_csrf_token'] = CSRFHandler::generateToken();
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Add console.log equivalent for PHP
error_log("save_entire_schedule.php: Current user ID: " . $_SESSION['user_id']);

try {
    // Validate input data
    if (!isset($_POST['changes']) || empty($_POST['changes'])) {
        throw new Exception('No schedule changes provided');
    }

    $changes = json_decode($_POST['changes'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid data format: ' . json_last_error_msg());
    }

    if (empty($changes)) {
        // No changes to save, return success
        echo json_encode(['success' => true, 'message' => 'No changes to save']);
        exit;
    }

    $mysqli->begin_transaction();
    $updated = [];
    $detailsInserts = []; // For batch logging

    foreach ($changes as $change) {
        // Validate each change
        $required = ['date', 'pilotId', 'position', 'registration'];
        $date = frenchToDbDate($mysqli->real_escape_string($change['date']));
        foreach ($required as $field) {
            if (!isset($change[$field])) {
                throw new Exception("Missing field: $field");
            }
        }

        // $date = $mysqli->real_escape_string($change['date']);
        // error_log("Received date: $date, dayIndex: " . date('N', strtotime($date)));

        // Convert received date to proper timezone
        $dateStr = $mysqli->real_escape_string($change['date']);
        $dateObj = new DateTime($dateStr, new DateTimeZone('America/Toronto'));
        $date = $dateObj->format('Y-m-d');

        error_log("Date processing: " . json_encode([
            'received' => $change['date'],
            'processed' => $date,
            'timezone' => $dateObj->getTimezone()->getName()
        ]));

        $pilotId = (int)$change['pilotId'];
        $position = $mysqli->real_escape_string($change['position']);
        $otherPil = $mysqli->real_escape_string($change['otherPil'] ?? '');
        $registration = $mysqli->real_escape_string($change['registration']);

        // Fetch craft_type from crafts table based on registration
        $craftTypeStmt = $mysqli->prepare("SELECT craft_type FROM crafts WHERE registration = ?");
        $craftTypeStmt->bind_param("s", $registration);
        $craftTypeStmt->execute();
        $craftTypeResult = $craftTypeStmt->get_result();

        if ($craftTypeResult->num_rows === 0) {
            throw new Exception("Invalid registration: $registration");
        }

        $craftType = $craftTypeResult->fetch_assoc()['craft_type'];

        // Check existing
        $stmt = $mysqli->prepare("SELECT id, user_id FROM schedule
                                WHERE sched_date = ? AND craft_type = ? AND registration = ? AND pos = ?");
        $stmt->bind_param("ssss", $date, $craftType, $registration, $position);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        $action = '';
        $scheduleId = 0;

        if ($pilotId === 0) {
            // Remove
            if ($existing) {
                $stmt = $mysqli->prepare("DELETE FROM schedule
                                        WHERE sched_date = ? AND craft_type = ? AND registration = ? AND pos = ?");
                $stmt->bind_param("ssss", $date, $craftType, $registration, $position);
                $action = 'removed';
                $scheduleId = $existing['id'];
            } else {
                // Nothing to delete, skip
                continue;
            }
        } elseif ($existing) {
            // Only update if the pilot ID has changed
            if ($existing['user_id'] != $pilotId) {
                $stmt = $mysqli->prepare("UPDATE schedule
                                        SET user_id = ?, craft_type = ?, registration = ?, pos = ?, otherPil = ?, updated_at = NOW()
                                        WHERE id = ?");
                $stmt->bind_param("issssi", $pilotId, $craftType, $registration, $position, $otherPil, $existing['id']);
                $action = 'updated';
                $scheduleId = $existing['id'];
            } else {
                // No change needed
                $action = 'unchanged';
                $scheduleId = $existing['id'];
            }
        } else {
            $stmt = $mysqli->prepare("INSERT INTO schedule
                                    (user_id, sched_date, craft_type, registration, pos, otherPil, created_by)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE
                                    user_id = VALUES(user_id),
                                    sched_date = VALUES(sched_date), 
                                    craft_type = VALUES(craft_type), 
                                    registration = VALUES(registration),
                                    pos = VALUES(pos),
                                    otherPil = VALUES(otherPil),
                                    updated_at = NOW()");
            $stmt->bind_param("isssssi", $pilotId, $date, $craftType, $registration, $position, $otherPil, $_SESSION['user_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to save schedule for $craftType on $date: " . $mysqli->error);
            }
            $action = ($mysqli->affected_rows == 1) ? 'added' : 'updated';
        
            // Get schedule ID for logging
            if ($action === 'added') {
                $scheduleId = $mysqli->insert_id;
            }
        }

        // Execute the query if there's an action to perform
        if ($action !== 'unchanged' && !$stmt->execute()) {
            throw new Exception("Failed to save schedule for $craftType on $date: " . $mysqli->error);
        }

        // Prepare schedule_details entry if there was a change
        if ($action !== 'unchanged') {
            $logValue = json_encode([
                'pilot_id' => $pilotId,
                'action' => $action,
                'by_user' => $_SESSION['user_id'],
                'position' => $position,
                'other_pilot' => $otherPil,
                'timestamp' => date('Y-m-d H:i:s'),
                'registration' => $registration,
                'craft_type' => $craftType
            ]);

            // Add to batch inserts
            $detailsInserts[] = [
                'date' => $date,
                'craft_type' => $craftType,
                'registration' => $registration,
                'value' => $logValue,
                'schedule_id' => $scheduleId
            ];
        }

        // Get pilot name for response
        if ($pilotId > 0) {
            $nameStmt = $mysqli->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
            $nameStmt->bind_param("i", $pilotId);
            $nameStmt->execute();
            $pilot = $nameStmt->get_result()->fetch_assoc();
            $pilotName = $pilot ? $pilot['firstname'][0] . '. ' . $pilot['lastname'] : '';
        } else {
            $pilotName = '';
        }

        $updated[] = [
            'day_index' => date('N', strtotime($date)) - 1, // 0-6 (Monday-Sunday)
            'position' => $position,
            'pilot_id' => $pilotId,
            'pilot_name' => $pilotName,
            'craft_type' => $craftType,
            'action' => $action
        ];
    }

    // Insert schedule_details entries in batch
    if (!empty($detailsInserts)) {
        foreach ($detailsInserts as $insert) {
            $detailsStmt = $mysqli->prepare("INSERT INTO schedule_details
                                           (date, craft_type, registration, value)
                                           VALUES (?, ?, ?, ?)");
            $detailsStmt->bind_param("ssss",
                $insert['date'],
                $insert['craft_type'],
                $insert['registration'],
                $insert['value']
            );

            if (!$detailsStmt->execute()) {
                throw new Exception("Failed to log schedule details");
            }
        }
    }

    $mysqli->commit();

    // Return success with updated data
    echo json_encode([
        'success' => true,
        'updated' => $updated,
        'message' => count($updated) . ' schedule entries processed successfully'
    ]);

} catch (Exception $e) {
    if ($mysqli->inTransaction()) {
        $mysqli->rollback();
    }
    error_log("Schedule Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>

<?php
// session_start();

// --- SESSION-BASED CSRF VALIDATION ---
$submitted_token = $_POST['form_token'] ?? '';

if (empty($submitted_token)) {
    throw new Exception("Security token missing. Please refresh the page.", 403);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    throw new Exception("Invalid security token. Please refresh the page.", 403);
}

// header('Content-Type: application/json');
// require_once 'db_connect.php';
// require_once 'date_utils.php';

// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'error' => 'Unauthorized']);
//     exit;
// }

// // Add console.log equivalent for PHP
// error_log("save_entire_schedule.php: Current user ID: " . $_SESSION['user_id']);

// try {
//     // Validate input data
//     if (!isset($_POST['changes']) || empty($_POST['changes'])) {
//         throw new Exception('No schedule changes provided');
//     }

//     $changes = json_decode($_POST['changes'], true);
//     if (json_last_error() !== JSON_ERROR_NONE) {
//         throw new Exception('Invalid data format: ' . json_last_error_msg());
//     }

//     if (empty($changes)) {
//         // No changes to save, return success
//         echo json_encode(['success' => true, 'message' => 'No changes to save']);
//         exit;
//     }

//     $mysqli->begin_transaction();
//     $updated = [];
//     $detailsInserts = []; // For batch logging

//     foreach ($changes as $change) {
//         // Validate each change
//         $required = ['date', 'pilotId', 'position', 'registration'];
//         $date = frenchToDbDate($mysqli->real_escape_string($change['date']));
//         foreach ($required as $field) {
//             if (!isset($change[$field])) {
//                 throw new Exception("Missing field: $field");
//             }
//         }

//         $date = $mysqli->real_escape_string($change['date']);
//         error_log("Received date: $date, dayIndex: " . date('N', strtotime($date)));
//         $pilotId = (int)$change['pilotId'];
//         $position = $mysqli->real_escape_string($change['position']);
//         $otherPil = $mysqli->real_escape_string($change['otherPil'] ?? '');
//         $registration = $mysqli->real_escape_string($change['registration']);

//         // Fetch craft_type from crafts table based on registration
//         $craftTypeStmt = $mysqli->prepare("SELECT craft_type FROM crafts WHERE registration = ?");
//         $craftTypeStmt->bind_param("s", $registration);
//         $craftTypeStmt->execute();
//         $craftTypeResult = $craftTypeStmt->get_result();

//         if ($craftTypeResult->num_rows === 0) {
//             throw new Exception("Invalid registration: $registration");
//         }

//         $craftType = $craftTypeResult->fetch_assoc()['craft_type'];

//         // Check existing
//         $stmt = $mysqli->prepare("SELECT id, user_id FROM schedule
//                                 WHERE sched_date = ? AND craft_type = ? AND registration = ? AND pos = ?");
//         $stmt->bind_param("ssss", $date, $craftType, $registration, $position);
//         $stmt->execute();
//         $existing = $stmt->get_result()->fetch_assoc();

//         $action = '';
//         $scheduleId = 0;

//         if ($pilotId === 0) {
//             // Remove
//             if ($existing) {
//                 $stmt = $mysqli->prepare("DELETE FROM schedule
//                                         WHERE sched_date = ? AND craft_type = ? AND registration = ? AND pos = ?");
//                 $stmt->bind_param("ssss", $date, $craftType, $registration, $position);
//                 $action = 'removed';
//                 $scheduleId = $existing['id'];
//             } else {
//                 // Nothing to delete, skip
//                 continue;
//             }
//         } elseif ($existing) {
//             // Only update if the pilot ID has changed
//             if ($existing['user_id'] != $pilotId) {
//                 $stmt = $mysqli->prepare("UPDATE schedule
//                                         SET user_id = ?, craft_type = ?, registration = ?, pos = ?, otherPil = ?, updated_at = NOW()
//                                         WHERE id = ?");
//                 $stmt->bind_param("issssi", $pilotId, $craftType, $registration, $position, $otherPil, $existing['id']);
//                 $action = 'updated';
//                 $scheduleId = $existing['id'];
//             } else {
//                 // No change needed
//                 $action = 'unchanged';
//                 $scheduleId = $existing['id'];
//             }
//         } else {
//             $stmt = $mysqli->prepare("INSERT INTO schedule
//                                     (user_id, sched_date, craft_type, registration, pos, otherPil, created_by)
//                                     VALUES (?, ?, ?, ?, ?, ?, ?)
//                                     ON DUPLICATE KEY UPDATE
//                                     user_id = VALUES(user_id),
//                                     sched_date = VALUES(sched_date), 
//                                     craft_type = VALUES(craft_type), 
//                                     registration = VALUES(registration),
//                                     pos = VALUES(pos),
//                                     otherPil = VALUES(otherPil),
//                                     updated_at = NOW()");
//             $stmt->bind_param("isssssi", $pilotId, $date, $craftType, $registration, $position, $otherPil, $_SESSION['user_id']);
            
//             if (!$stmt->execute()) {
//                 throw new Exception("Failed to save schedule for $craftType on $date: " . $mysqli->error);
//             }
//             $action = ($mysqli->affected_rows == 1) ? 'added' : 'updated';
        
//             // Get schedule ID for logging
//             if ($action === 'added') {
//                 $scheduleId = $mysqli->insert_id;
//             }
//         }

//         // Execute the query if there's an action to perform
//         if ($action !== 'unchanged' && !$stmt->execute()) {
//             throw new Exception("Failed to save schedule for $craftType on $date: " . $mysqli->error);
//         }

//         // Prepare schedule_details entry if there was a change
//         if ($action !== 'unchanged') {
//             $logValue = json_encode([
//                 'pilot_id' => $pilotId,
//                 'action' => $action,
//                 'by_user' => $_SESSION['user_id'],
//                 'position' => $position,
//                 'other_pilot' => $otherPil,
//                 'timestamp' => date('Y-m-d H:i:s'),
//                 'registration' => $registration,
//                 'craft_type' => $craftType
//             ]);

//             // Add to batch inserts
//             $detailsInserts[] = [
//                 'date' => $date,
//                 'craft_type' => $craftType,
//                 'registration' => $registration,
//                 'value' => $logValue,
//                 'schedule_id' => $scheduleId
//             ];
//         }

//         // Get pilot name for response
//         if ($pilotId > 0) {
//             $nameStmt = $mysqli->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
//             $nameStmt->bind_param("i", $pilotId);
//             $nameStmt->execute();
//             $pilot = $nameStmt->get_result()->fetch_assoc();
//             $pilotName = $pilot ? $pilot['firstname'][0] . '. ' . $pilot['lastname'] : '';
//         } else {
//             $pilotName = '';
//         }

//         $updated[] = [
//             'day_index' => date('N', strtotime($date)) - 1, // 0-6 (Monday-Sunday)
//             'position' => $position,
//             'pilot_id' => $pilotId,
//             'pilot_name' => $pilotName,
//             'craft_type' => $craftType,
//             'action' => $action
//         ];
//     }

//     // Insert schedule_details entries in batch
//     if (!empty($detailsInserts)) {
//         foreach ($detailsInserts as $insert) {
//             $detailsStmt = $mysqli->prepare("INSERT INTO schedule_details
//                                            (date, craft_type, registration, value)
//                                            VALUES (?, ?, ?, ?)");
//             $detailsStmt->bind_param("ssss",
//                 $insert['date'],
//                 $insert['craft_type'],
//                 $insert['registration'],
//                 $insert['value']
//             );

//             if (!$detailsStmt->execute()) {
//                 throw new Exception("Failed to log schedule details");
//             }
//         }
//     }

//     $mysqli->commit();

//     // Return success with updated data
//     echo json_encode([
//         'success' => true,
//         'updated' => $updated,
//         'message' => count($updated) . ' schedule entries processed successfully'
//     ]);

// } catch (Exception $e) {
//     if ($mysqli->inTransaction()) {
//         $mysqli->rollback();
//     }
//     error_log("Schedule Save Error: " . $e->getMessage());
//     echo json_encode(['success' => false, 'error' => $e->getMessage()]);
// }

// date_default_timezone_set('UTC');
?>