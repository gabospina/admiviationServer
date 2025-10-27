<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// include_once "db_connect.php";

// $response = array();

// if (isset($_POST['customer_id']) && !empty($_POST['customer_id'])) {
//     $customerId = intval($_POST['customer_id']);

//     // Check if there are any contracts associated with the customer
//     $query = "SELECT COUNT(*) AS contract_count FROM contracts WHERE customer_id = ?";
//     $stmt = $mysqli->prepare($query);

//     if ($stmt) {
//         $stmt->bind_param("i", $customerId);
//         $stmt->execute();
//         $result = $stmt->get_result();
//         $row = $result->fetch_assoc();
//         $contractCount = intval($row['contract_count']);
//         $stmt->close();

//         $response['has_contracts'] = ($contractCount > 0);  // True if customer has contracts
//     } else {
//         $response['has_contracts'] = true; // Assume true to prevent deletion on error
//         $response['message'] = "Error preparing statement: " . $mysqli->error;
//         error_log("Error preparing statement: " . $mysqli->error);
//     }
// } else {
//     $response['has_contracts'] = true; // Assume true to prevent deletion on error
//     $response['message'] = "Invalid customer ID.";
// }

// header('Content-Type: application/json');
// echo json_encode($response);
// $mysqli->close();
?>

<?php
session_start();
include_once "db_connect.php";

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

$customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;

if ($customer_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid customer ID"]);
    exit;
}

try {
    $sql = "SELECT COUNT(*) as contract_count FROM contracts WHERE customer_id = ?";
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $mysqli->error);
    }
    
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    echo json_encode([
        "success" => true,
        "has_contracts" => ($row['contract_count'] > 0),
        "contract_count" => $row['contract_count']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Error checking contracts: " . $e->getMessage()
    ]);
}
?>