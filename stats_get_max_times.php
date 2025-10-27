<?php
// stats_get_max_times.php v72

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'stats_api_response.php';
require_once 'db_connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1); 

$response = new ApiResponse();
global $mysqli;
$stmt = null;

try {
    if (!$mysqli) throw new Exception("Database connection not available.", 500);

    if (!isset($_SESSION['company_id'])) {
        throw new Exception("Company ID not found in session.", 400);
    }
    $company_id = (int)$_SESSION['company_id'];

    // --- THIS IS THE INTEGRATED FIX ---
    // The query now joins with the 'companies' table to fetch the company_name.
    // It assumes your limits are in 'pilot_max_times' and company names in 'companies'.
    $sql = "SELECT 
                pmt.*, 
                c.company_name 
            FROM 
                pilot_max_times pmt
            LEFT JOIN 
                companies c ON pmt.company_id = c.id
            WHERE 
                pmt.company_id = ?";
    // --- END OF FIX ---
            
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) { throw new Exception("Prepare failed: ".$mysqli->error, 500); }

    $stmt->bind_param("i", $company_id);
    if (!$stmt->execute()) { throw new Exception("Execute failed: ".$stmt->error, 500); }

    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $response->setSuccess(true)->setData($data);
    } else {
        // If no limits are found, we still need the company name.
        $company_name = 'N/A';
        $name_stmt = $mysqli->prepare("SELECT company_name FROM companies WHERE id = ?");
        if ($name_stmt) {
            $name_stmt->bind_param("i", $company_id);
            $name_stmt->execute();
            $name_res = $name_stmt->get_result();
            if ($name_row = $name_res->fetch_assoc()) {
                $company_name = $name_row['company_name'];
            }
            $name_stmt->close();
        }
        
        $default_data = [
            'company_id' => $company_id,
            'company_name' => $company_name, // Include the fetched name
            'max_in_day' => 0.0,
            'max_last_7' => 0.0,
            'max_last_28' => 0.0,
            'max_last_365' => 0.0,
            'max_duty_in_day' => 0.0,
            'max_duty_7' => 0.0,
            'max_duty_28' => 0.0,
            'max_duty_365' => 0.0
        ];
        $response->setSuccess(true)->setData($default_data)->setMessage("No specific limits found, showing defaults.");
    }

} catch (Exception $e) {
    http_response_code(500);
    $response->setError($e->getMessage());
} finally {
    if ($stmt) { $stmt->close(); }
}

$response->send();
?>