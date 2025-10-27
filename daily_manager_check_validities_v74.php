<?php
// daily_manager_check_validities.php - FINAL VERSION for user_licence_data table
session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';

$response = array('success' => false, 'message' => 'An unknown error occurred.');

// --- Authentication & Connection Check ---
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
    $response['message'] = "Authentication error.";
    http_response_code(401);
    echo json_encode($response);
    exit();
}
if (!isset($mysqli) || $mysqli->connect_error) {
    $response['message'] = "Database connection error.";
    http_response_code(500);
    echo json_encode($response);
    exit();
}

$company_id = (int)$_SESSION['company_id'];
$today = new DateTime();
$today->setTime(0, 0, 0);
$thirty_days_from_now = (new DateTime())->modify('+30 days')->format('Y-m-d');

try {
    // --- STEP 1: DYNAMICALLY GET ALL VALIDITY COLUMN NAMES ---
    $validity_date_columns = array();
    $all_validity_columns = array();

    // CHANGE 1: Using the new table name
    $result = $mysqli->query("DESCRIBE user_licence_data");
    if (!$result) {
        throw new Exception("Could not describe user_licence_data table: " . $mysqli->error);
    }
    
    // CHANGE 2: Ignoring 'user_id' instead of 'pilot_id'
    $ignore_columns = array('id', 'user_id', 'company_id', 'created_at', 'updated_at');
    
    while ($row = $result->fetch_assoc()) {
        $field_name = $row['Field'];
        $all_validity_columns[] = $field_name;
        if ($row['Type'] == 'date' && !in_array($field_name, $ignore_columns)) {
            $validity_date_columns[] = $field_name;
        }
    }
    $result->free();

    if (empty($validity_date_columns)) {
        $response['success'] = true;
        $response['data'] = array();
        echo json_encode($response);
        exit();
    }
    
    $expiring_validities = array();
    
    // CHANGE 3: Building the query with the new table name and JOIN condition
    $validity_select_string = "v." . implode(", v.", $all_validity_columns);
    $sql = "SELECT u.firstname, u.lastname, $validity_select_string FROM users u JOIN user_licence_data v ON u.id = v.user_id WHERE u.company_id = ?";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("SQL Prepare Error: " . $mysqli->error);

    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    
    // This compatible fetching method does not need to change
    $result_row = array();
    $params = array();
    $fields = array_merge(array('firstname', 'lastname'), $all_validity_columns);
    foreach ($fields as $field) {
        $params[] = &$result_row[$field];
    }
    call_user_func_array(array($stmt, 'bind_result'), $params);
    
    while ($stmt->fetch()) {
        $pilot_data = array();
        foreach ($result_row as $key => $val) {
            $pilot_data[$key] = $val;
        }

        foreach ($validity_date_columns as $column_name) {
            if (isset($pilot_data[$column_name])) {
                $expiry_date_str = $pilot_data[$column_name];
                if ($expiry_date_str && $expiry_date_str <= $thirty_days_from_now) {
                    $expiry_date = new DateTime($expiry_date_str);
                    $expiry_date->setTime(0, 0, 0);
                    $diff = $today->diff($expiry_date);
                    $days_left = (int)$diff->format('%r%a');
                    $friendly_name = ucwords(str_replace('_', ' ', $column_name));
                    
                    $expiring_validities[] = array(
                        'pilot_name' => $pilot_data['firstname'] . ' ' . $pilot_data['lastname'],
                        'validity_name' => $friendly_name,
                        'expiry_date' => $expiry_date_str,
                        'days_left' => $days_left
                    );
                }
            }
        }
    }
    $stmt->close();

    // --- STEP 3: PREPARE AND SEND THE FINAL RESPONSE ---
    // CHANGE 4: Using a PHP 5.x compatible sorting function
    usort($expiring_validities, function($a, $b) {
        if ($a['days_left'] == $b['days_left']) {
            return 0;
        }
        return ($a['days_left'] < $b['days_left']) ? -1 : 1;
    });

    $response['success'] = true;
    $response['data'] = $expiring_validities;

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = "A server error occurred: " . $e->getMessage();
    error_log("Error in check_validities.php: " . $e->getMessage());
}

$mysqli->close();
echo json_encode($response);
?>