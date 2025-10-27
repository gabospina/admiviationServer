<?php
// Ensure we return JSON content type
header('Content-Type: application/json');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);

include_once "db_connect.php";

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Initial error state',
    'debug' => []
];

try {
    // Validate session and permissions
    session_start();
    if (!isset($_SESSION['HeliUser']) || !isset($_SESSION['company_id'])) {
        throw new Exception("Authentication required");
    }

    // Validate POST input
    if (!isset($_POST['craft'])) {
        throw new Exception("Craft ID not provided");
    }

    $craft_id = (int)$_POST['craft'];
    $company_id = (int)$_SESSION['company_id'];

    // Verify craft belongs to company
    $check_sql = "SELECT company_id FROM crafts WHERE id = ?";
    $check_stmt = $mysqli->prepare($check_sql);
    
    if (!$check_stmt) {
        throw new Exception("Database preparation error");
    }
    
    $check_stmt->bind_param("i", $craft_id);
    $check_stmt->execute();
    $check_stmt->bind_result($craft_company_id);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($craft_company_id !== $company_id) {
        throw new Exception("Craft does not belong to your company");
    }

    // Check if craft is attached to any contracts
    $contract_check = "SELECT COUNT(*) FROM contract_crafts WHERE craft_id = ?";
    $contract_stmt = $mysqli->prepare($contract_check);
    
    if (!$contract_stmt) {
        throw new Exception("Failed to prepare contract check");
    }
    
    $contract_stmt->bind_param("i", $craft_id);
    $contract_stmt->execute();
    $contract_stmt->bind_result($contract_count);
    $contract_stmt->fetch();
    $contract_stmt->close();
    
    if ($contract_count > 0) {
        throw new Exception("Cannot delete craft - it is assigned to one or more contracts");
    }

    // Begin transaction
    $mysqli->begin_transaction();

    try {
        // Delete from crafts table
        $delete_sql = "DELETE FROM crafts WHERE id = ?";
        $delete_stmt = $mysqli->prepare($delete_sql);
        
        if (!$delete_stmt) {
            throw new Exception("Failed to prepare delete statement");
        }
        
        $delete_stmt->bind_param("i", $craft_id);
        
        if (!$delete_stmt->execute()) {
            throw new Exception("Failed to execute delete");
        }
        
        $delete_stmt->close();

        // Commit transaction if all went well
        $mysqli->commit();

        $response['success'] = true;
        $response['message'] = "Craft successfully removed";
        
    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e; // Re-throw for outer catch
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    $response['debug']['error'] = $e->getMessage();
    error_log("Craft Removal Error: " . $e->getMessage());
}

// Ensure no output has been sent before this
if (ob_get_length()) {
    ob_clean();
}

echo json_encode($response);

// Close connection if it exists
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}

exit();
?>