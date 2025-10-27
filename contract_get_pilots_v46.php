<?php
session_start();
header('Content-Type: application/json');
include_once "db_connect.php";

try {
    if (!isset($_SESSION['company_id'])) {
        throw new Exception('Company ID not set in session');
    }

    $company_id = (int)$_SESSION['company_id'];

    // *** SELECT id, firstname, lastname, AND phone ***
    $query = "SELECT id, firstname, lastname, phone 
              FROM users 
              WHERE is_active = 1
              AND company_id = ?
              ORDER BY lastname, firstname";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $mysqli->error);
    }

    $stmt->bind_param('i', $company_id);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $pilots = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure phone is null if empty/null in DB
        $row['phone'] = !empty($row['phone']) ? $row['phone'] : null;
        $pilots[] = $row;
    }
    $stmt->close();

    echo json_encode($pilots);

} catch (Exception $e) {
    error_log("Get Pilots Error: " . $e->getMessage()); // Log error
    http_response_code(500);
    echo json_encode([ "error" => "Failed to load pilot list." ]); // Generic error to client
} finally {
     if (isset($mysqli) && $mysqli->ping()) { $mysqli->close(); }
}
?>

<?php
// get_pilots.php (CORRECTED - NOW FILTERS BY CRAFT TYPE)

// session_start();
// header('Content-Type: application/json');
// include_once "db_connect.php";

// try {
//     if (!isset($_SESSION['company_id'])) {
//         throw new Exception('Company ID not set in session');
//     }
//     // THE FIX: The craft_type is now also required.
//     if (!isset($_GET['date'], $_GET['craft_type'])) {
//         throw new Exception('Date and Craft Type parameters are required.', 400);
//     }

//     $company_id = (int)$_SESSION['company_id'];
//     $target_date = $_GET['date'];
//     $craft_type = $_GET['craft_type'];

//     // --- NEW, SMARTER SQL QUERY ---
//     // This query now joins across THREE tables to find pilots who are:
//     // 1. Active and in the correct company.
//     // 2. Qualified for the specific craft_type.
//     // 3. Available (On Duty) for the target_date.
//     $query = "SELECT DISTINCT
//                   u.id, u.firstname, u.lastname, u.phone 
//               FROM 
//                   users u
//               INNER JOIN 
//                   pilot_craft_type pct ON u.id = pct.pilot_id
//               INNER JOIN 
//                   user_availability ua ON u.id = ua.user_id
//               WHERE 
//                   u.is_active = 1
//               AND 
//                   u.company_id = ?
//               AND
//                   pct.craft_type = ?
//               AND 
//                   ? BETWEEN ua.on_date AND ua.off_date
//               ORDER BY 
//                   u.lastname, u.firstname";
    
//     $stmt = $mysqli->prepare($query);
//     if (!$stmt) throw new Exception('Prepare failed: ' . $mysqli->error);

//     // Bind all three parameters
//     $stmt->bind_param('iss', $company_id, $craft_type, $target_date);
    
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $pilots = $result->fetch_all(MYSQLI_ASSOC);
//     $stmt->close();

//     echo json_encode($pilots);

// } catch (Exception $e) {
//     error_log("Get Pilots Error: " . $e->getMessage()); // Log error
//     http_response_code(500);
//     echo json_encode([ "error" => "Failed to load pilot list." ]); // Generic error to client
// } finally {
//      if (isset($mysqli) && $mysqli->ping()) { $mysqli->close(); }
// }
?>
