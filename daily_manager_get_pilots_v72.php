<?php
// daily_manager_get_pilots.php (FINAL, CORRECTED SQL)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = [
    'success' => false,
    'data' => ['pilots' => []],
    'error' => 'An unknown error occurred.'
];

try {
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("Session expired.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];
    
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? 'all';
    
    // --- THIS IS THE CORRECTED SQL ---
    // Start with the base query, joining the tables.
    $sql = "SELECT
                u.id,
                u.firstname,
                u.lastname,
                u.username,
                u.email,
                u.is_active,
                GROUP_CONCAT(r.role_name SEPARATOR ', ') AS role_name
            FROM
                users u
            LEFT JOIN
                user_has_roles uhr ON u.id = uhr.user_id
            LEFT JOIN
                users_roles r ON uhr.role_id = r.id
            WHERE
                u.company_id = ?";
    
    $params = [$company_id];
    $types = "i";

    // Add status filter if specified.
    if ($status === '1' || $status === '0') {
        $sql .= " AND u.is_active = ?";
        $params[] = (int)$status;
        $types .= "i";
    }

    // Add search filter if specified.
    // This needs to come BEFORE the GROUP BY clause.
    if (!empty($search)) {
        $sql .= " AND (CONCAT(u.firstname, ' ', u.lastname) LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "sss";
    }

    // Add the GROUP BY and a SINGLE ORDER BY at the end.
    $sql .= " GROUP BY u.id ORDER BY u.lastname, u.firstname";
    // --- END OF CORRECTED SQL ---

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        // For debugging, it's helpful to see the actual error.
        throw new Exception("Database query failed to prepare: " . $mysqli->error);
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // fetch_all is cleaner for getting all rows at once.
    $pilots = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $response['success'] = true;
    $response['data']['pilots'] = $pilots;
    unset($response['error']);

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>