<?php
// training_disable_availability.php v84

// Turn off all error reporting to prevent notices from breaking JSON output
error_reporting(0);

if (session_status() == PHP_SESSION_NONE) {
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

}

header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'stats_api_response.php';

$apiResponse = new ApiResponse();

// --- Session & Permission Checks ---
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id']) || !isset($_SESSION['admin'])) {
    http_response_code(401);
    $apiResponse->setError("Authentication or required session data missing.")->send();
    exit;
}

$company_id = (int)$_SESSION['company_id'];
$user_id = (int)$_SESSION['HeliUser'];
$admin_level = (int)$_SESSION['admin'];

// Access Control
if (!($admin_level > 0 && $admin_level != 2 && $admin_level != 4)) {
    http_response_code(403);
    $apiResponse->setError("Permission denied to disable training availability.")->send();
    exit;
}

$disable_start_str = $_POST["start"] ?? null;
$disable_end_str = $_POST["end"] ?? null;

// Validate dates
if (empty($disable_start_str) || empty($disable_end_str)) {
    http_response_code(400);
    $apiResponse->setError("Start and end dates for disabling are required.")->send();
    exit;
}

try {
    $disable_start_dt = new DateTime($disable_start_str);
    $disable_end_dt = new DateTime($disable_end_str);
    
    if ($disable_start_dt > $disable_end_dt) {
        throw new Exception("Disable start date cannot be after disable end date.", 400);
    }
    
    global $mysqli;
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection error.", 500);
    }

    $mysqli->begin_transaction();

    // Find overlapping periods
    $sql_overlap = "SELECT id, date_on, date_off FROM training_availability 
                    WHERE company_id = ? 
                    AND date_on <= ?
                    AND date_off >= ?";
    
    $stmt_overlap = $mysqli->prepare($sql_overlap);
    if (!$stmt_overlap) {
        throw new Exception("SQL prepare error (overlap select): " . $mysqli->error, 500);
    }
    
    $stmt_overlap->bind_param("iss", $company_id, $disable_end_str, $disable_start_str);
    $stmt_overlap->execute();
    $result_overlap = $stmt_overlap->get_result();
    
    $changes_made = false;

    while ($existing_period = $result_overlap->fetch_assoc()) {
        $existing_id = (int)$existing_period['id'];
        $existing_on_dt = new DateTime($existing_period['date_on']);
        $existing_off_dt = new DateTime($existing_period['date_off']);

        // Case 1: Existing period is completely within the disable range
        if ($existing_on_dt >= $disable_start_dt && $existing_off_dt <= $disable_end_dt) {
            $delete_sql = "DELETE FROM training_availability WHERE id = ? AND company_id = ?";
            $delete_stmt = $mysqli->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $existing_id, $company_id);
            $delete_stmt->execute();
            $changes_made = $changes_made || ($delete_stmt->affected_rows > 0);
            $delete_stmt->close();
        }
        // Case 2: Disable range is completely within the existing period
        elseif ($existing_on_dt < $disable_start_dt && $existing_off_dt > $disable_end_dt) {
            // Part 1: Update existing record to end before disable range
            $new_off_part1 = $disable_start_dt->format('Y-m-d');
            $new_off_part1_dt = new DateTime($new_off_part1);
            $new_off_part1_dt->modify('-1 day');
            
            $update_sql1 = "UPDATE training_availability SET date_off = ?, updated_at = NOW() WHERE id = ? AND company_id = ?";
            $update_stmt1 = $mysqli->prepare($update_sql1);
            $update_stmt1->bind_param("sii", $new_off_part1_dt->format('Y-m-d'), $existing_id, $company_id);
            $update_stmt1->execute();
            $changes_made = $changes_made || ($update_stmt1->affected_rows > 0);
            $update_stmt1->close();

            // Part 2: Insert new record for after disable range
            $new_on_part2 = $disable_end_dt->format('Y-m-d');
            $new_on_part2_dt = new DateTime($new_on_part2);
            $new_on_part2_dt->modify('+1 day');
            
            if ($new_on_part2_dt <= $existing_off_dt) {
                $insert_sql2 = "INSERT INTO training_availability (company_id, date_on, date_off, created_by) VALUES (?, ?, ?, ?)";
                $insert_stmt2 = $mysqli->prepare($insert_sql2);
                $insert_stmt2->bind_param("issi", $company_id, $new_on_part2_dt->format('Y-m-d'), $existing_off_dt->format('Y-m-d'), $user_id);
                $insert_stmt2->execute();
                $changes_made = $changes_made || ($insert_stmt2->affected_rows > 0);
                $insert_stmt2->close();
            }
        }
        // Case 3: Disable range overlaps start of existing period
        elseif ($disable_start_dt <= $existing_on_dt && $disable_end_dt >= $existing_on_dt && $disable_end_dt < $existing_off_dt) {
            $new_on_dt = clone $disable_end_dt;
            $new_on_dt->modify('+1 day');
            
            $update_sql = "UPDATE training_availability SET date_on = ?, updated_at = NOW() WHERE id = ? AND company_id = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            $update_stmt->bind_param("sii", $new_on_dt->format('Y-m-d'), $existing_id, $company_id);
            $update_stmt->execute();
            $changes_made = $changes_made || ($update_stmt->affected_rows > 0);
            $update_stmt->close();
        }
        // Case 4: Disable range overlaps end of existing period
        elseif ($disable_start_dt > $existing_on_dt && $disable_start_dt <= $existing_off_dt && $disable_end_dt >= $existing_off_dt) {
            $new_off_dt = clone $disable_start_dt;
            $new_off_dt->modify('-1 day');

            $update_sql = "UPDATE training_availability SET date_off = ?, updated_at = NOW() WHERE id = ? AND company_id = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            $update_stmt->bind_param("sii", $new_off_dt->format('Y-m-d'), $existing_id, $company_id);
            $update_stmt->execute();
            $changes_made = $changes_made || ($update_stmt->affected_rows > 0);
            $update_stmt->close();
        }
    }
    $stmt_overlap->close();

    if ($changes_made) {
        $mysqli->commit();
        $apiResponse->setSuccess(true)->setMessage("Training availability updated for the selected range.");
    } else {
        $mysqli->rollback();
        $apiResponse->setSuccess(true)->setMessage("No existing availability periods matched the disable criteria.");
    }

} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->ping()) {
        $mysqli->rollback();
    }
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $apiResponse->setError($e->getMessage());
}

$apiResponse->send();
?>