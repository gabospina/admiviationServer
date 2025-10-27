<?php
// daily_manager_check_validities.php - REFACTORED FOR NEW DATABASE STRUCTURE

session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    // --- Authentication & Connection Check ---
    if (!isset($_SESSION["HeliUser"], $_SESSION["company_id"])) {
        throw new Exception("Authentication error.", 401);
    }
    if (!isset($mysqli) || $mysqli->connect_error) {
        throw new Exception("Database connection error.", 500);
    }

    $company_id = (int)$_SESSION['company_id'];
    
    // --- REFACTORED LOGIC ---
    // EXPLANATION: This new query is much simpler and more efficient.
    // 1. It joins `user_licence_data` with `users` to get the pilot's name.
    // 2. It joins with `user_company_licence_fields` to get the proper "label" for the license.
    // 3. It uses a WHERE clause to let the database do the efficient filtering for us,
    //    only selecting records that have an expiry date.

    $sql = "
        SELECT
            CONCAT(u.firstname, ' ', u.lastname) as pilot_name,
            lf.field_label as validity_name,
            ld.expiry_date
        FROM
            user_licence_data ld
        JOIN
            users u ON ld.user_id = u.id
        JOIN
            user_company_licence_fields lf ON ld.field_key = lf.field_key AND ld.company_id = lf.company_id
        WHERE
            ld.company_id = ?
            AND ld.expiry_date IS NOT NULL
            AND u.is_active = 1
        ORDER BY
            ld.expiry_date ASC
    ";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("SQL Prepare Error: " . $mysqli->error);

    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $all_validities = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // --- Process data in PHP (this part remains similar) ---
    $report_data = [];
    $today = new DateTime();
    $today->setTime(0, 0, 0);

    foreach ($all_validities as $validity) {
        $expiry_date = new DateTime($validity['expiry_date']);
        $expiry_date->setTime(0, 0, 0);
        
        $diff = $today->diff($expiry_date);
        $days_left = (int)$diff->format('%r%a'); // %r gives the sign (+ or -)

        $report_data[] = [
            'pilot_name' => $validity['pilot_name'],
            'validity_name' => $validity['validity_name'],
            'expiry_date' => $validity['expiry_date'],
            'days_left' => $days_left
        ];
    }
    
    // Sort by days_left, ascending
    usort($report_data, function($a, $b) {
        return $a['days_left'] <=> $b['days_left'];
    });

    $response['success'] = true;
    $response['data'] = $report_data;

} catch (Exception $e) {
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    $response['message'] = "A server error occurred: " . $e->getMessage();
    error_log("Error in daily_manager_check_validities.php: " . $e->getMessage());
}

$mysqli->close();
echo json_encode($response);
?>