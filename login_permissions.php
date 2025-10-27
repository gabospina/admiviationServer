<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function userHasRole(array $allowedRoles, mysqli $mysqli) {
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        return false;
    }

    $userId = (int)$_SESSION['HeliUser'];
    $companyId = (int)$_SESSION['company_id'];

    $sql = "SELECT r.role_name
            FROM user_has_roles uhr
            JOIN users_roles r ON uhr.role_id = r.id
            WHERE uhr.user_id = ? AND uhr.company_id = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("Permission check failed: " . $mysqli->error);
        return false;
    }

    $stmt->bind_param("ii", $userId, $companyId);
    if (!$stmt->execute()) {
        error_log("Permission query failed: " . $stmt->error);
        return false;
    }

    $result = $stmt->get_result();
    $userRoles = [];
    while ($row = $result->fetch_assoc()) {
        $userRoles[] = strtolower($row['role_name']);
    }
    $stmt->close();

    $allowedRoles = array_map('strtolower', $allowedRoles);
    
    return count(array_intersect($userRoles, $allowedRoles)) > 0;
}

// Single additional function just for delete permission
function canDeletePilots(mysqli $mysqli): bool {
    return userHasRole(['admin', 'manager', 'admin pilot'], $mysqli);
}
?>