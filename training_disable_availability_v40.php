<?php
// training_disable_availability.php
// (Works with training_availability table: date_on, date_off columns)

error_reporting(0); // Suppress notices and warnings from being outputted
ini_set('display_errors', 0); // Ensure errors are not displayed to the browser

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once 'db_connect.php';          // VERIFY PATH
require_once 'stats_api_response.php';  // VERIFY PATH

$apiResponse = new ApiResponse();

// --- Session & Permission Checks ---
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id']) || !isset($_SESSION['admin'])) {
    http_response_code(401);
    $apiResponse->setError("Authentication or required session data missing.")->send();
    exit;
}

$company_id = (int)$_SESSION['company_id'];
$user_id = (int)$_SESSION['HeliUser']; // For created_by if splitting records
$admin_level = (int)$_SESSION['admin'];

// Access Control
if (!($admin_level > 0 && $admin_level != 2 && $admin_level != 4)) {
    http_response_code(403);
    $apiResponse->setError("Permission denied to disable training availability.")->send();
    exit;
}

$disable_start_str = $_POST["start"] ?? null; // Expects YYYY-MM-DD
$disable_end_str = $_POST["end"] ?? null;     // Expects YYYY-MM-DD
// 'isSingleDay' is NOT needed by this script version

if (empty($disable_start_str) || empty($disable_end_str)) {
    http_response_code(400);
    $apiResponse->setError("Start and end dates for disabling are required.")->send();
    exit;
}
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $disable_start_str) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $disable_end_str)) {
    http_response_code(400);
    $apiResponse->setError("Invalid date format. Please use YYYY-MM-DD.")->send();
    exit;
}
if (strtotime($disable_start_str) > strtotime($disable_end_str)) {
    http_response_code(400);
    $apiResponse->setError("Disable start date cannot be after disable end date.")->send();
    exit;
}

try {
    global $mysqli;
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection error.", 500);
    }

    $mysqli->begin_transaction();

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
    
    $processed_ids = []; // To avoid processing a split record multiple times if logic is complex
    $changes_made = false;

    while ($existing_period = $result_overlap->fetch_assoc()) {
        if (in_array($existing_period['id'], $processed_ids)) {
            continue;
        }

        $existing_id = (int)$existing_period['id'];
        $existing_on_dt = new DateTime($existing_period['date_on']);
        $existing_off_dt = new DateTime($existing_period['date_off']);
        $disable_start_dt = new DateTime($disable_start_str);
        $disable_end_dt = new DateTime($disable_end_str);

        // Case 1: Existing period is completely within or same as the disable range -> DELETE
        if ($existing_on_dt >= $disable_start_dt && $existing_off_dt <= $disable_end_dt) {
            $delete_sql = "DELETE FROM training_availability WHERE id = ? AND company_id = ?";
            $delete_stmt = $mysqli->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $existing_id, $company_id);
            $delete_stmt->execute();
            if($delete_stmt->affected_rows > 0) $changes_made = true;
            $delete_stmt->close();
            $processed_ids[] = $existing_id;
        }
        // Case 2: Disable range is completely within the existing period -> SPLIT existing
        else if ($existing_on_dt < $disable_start_dt && $existing_off_dt > $disable_end_dt) {
            // Part 1: Shorten original to end before disable_start
            $new_off_part1_dt = clone $disable_start_dt;
            $new_off_part1_dt->modify('-1 day');
            
            $update_sql1 = "UPDATE training_availability SET date_off = ?, updated_at = NOW() WHERE id = ? AND company_id = ?";
            $update_stmt1 = $mysqli->prepare($update_sql1);
            $update_stmt1->bind_param("sii", $new_off_part1_dt->format('Y-m-d'), $existing_id, $company_id);
            $update_stmt1->execute();
            if($update_stmt1->affected_rows > 0) $changes_made = true;
            $update_stmt1->close();
            $processed_ids[] = $existing_id;

            // Part 2: Insert new period for after disable_end
            $new_on_part2_dt = clone $disable_end_dt;
            $new_on_part2_dt->modify('+1 day');
            // Only insert if the new start is not after the original end
            if ($new_on_part2_dt <= $existing_off_dt) {
                $insert_sql2 = "INSERT INTO training_availability (company_id, date_on, date_off, created_by) VALUES (?, ?, ?, ?)";
                $insert_stmt2 = $mysqli->prepare($insert_sql2);
                $insert_stmt2->bind_param("issi", $company_id, $new_on_part2_dt->format('Y-m-d'), $existing_off_dt->format('Y-m-d'), $user_id);
                $insert_stmt2->execute();
                 if($insert_stmt2->affected_rows > 0) $changes_made = true;
                $insert_stmt2->close();
            }
        }
        // Case 3: Disable range overlaps the start of the existing period -> Shorten existing START (change date_on)
        else if ($disable_start_dt <= $existing_on_dt && $disable_end_dt >= $existing_on_dt && $disable_end_dt < $existing_off_dt) {
            $new_on_dt = clone $disable_end_dt;
            $new_on_dt->modify('+1 day');
            
            $update_sql = "UPDATE training_availability SET date_on = ?, updated_at = NOW() WHERE id = ? AND company_id = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            $update_stmt->bind_param("sii", $new_on_dt->format('Y-m-d'), $existing_id, $company_id);
            $update_stmt->execute();
            if($update_stmt->affected_rows > 0) $changes_made = true;
            $update_stmt->close();
            $processed_ids[] = $existing_id;
        }
        // Case 4: Disable range overlaps the end of the existing period -> Shorten existing END (change date_off)
        else if ($disable_start_dt > $existing_on_dt && $disable_start_dt <= $existing_off_dt && $disable_end_dt >= $existing_off_dt) {
            $new_off_dt = clone $disable_start_dt;
            $new_off_dt->modify('-1 day');

            $update_sql = "UPDATE training_availability SET date_off = ?, updated_at = NOW() WHERE id = ? AND company_id = ?";
            $update_stmt = $mysqli->prepare($update_sql);
            $update_stmt->bind_param("sii", $new_off_dt->format('Y-m-d'), $existing_id, $company_id);
            $update_stmt->execute();
            if($update_stmt->affected_rows > 0) $changes_made = true;
            $update_stmt->close();
            $processed_ids[] = $existing_id;
        }
    }
    $stmt_overlap->close();

    if ($changes_made) {
        $apiResponse->setSuccess(true)->setMessage("Training availability updated for the selected range.");
    } else {
        $apiResponse->setSuccess(true)->setMessage("No existing availability periods strictly matched the disable criteria for modification, or no changes were needed.");
    }
    
    $mysqli->commit();

} catch (Exception $e) {
    // Log the error to the server's error log
    error_log("PHP Exception in ".__FILE__." at line ".__LINE__.": " . $e->getMessage() . "\n" . $e->getTraceAsString());

    // Send a JSON error response
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $apiResponse->setError("Server error during availability update. Please contact support if the issue persists."); // User-friendly message
    // $apiResponse->setError("Server error: " . $e->getMessage()); // More detailed for debugging if needed by client
}

$apiResponse->send();
?>