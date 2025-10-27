<?php
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }

// /**
//  * Checks if the currently logged-in user has at least one of the specified roles.
//  *
//  * This function checks a user's assigned role names against an array of allowed roles.
//  * It's a much simpler approach than the granular permission system.
//  *
//  * @param array $allowedRoles An array of role names that are allowed to perform an action.
//  *                             Example: ['admin', 'manager', 'training manager pilot']
//  * @param mysqli $mysqli The database connection object.
//  * @return bool True if the user has at least one of the allowed roles, false otherwise.
//  */
// function userHasRole(array $allowedRoles, mysqli $mysqli) {
//     // 1. Ensure user is logged in.
//     if (!isset($_SESSION['HeliUser'])) {
//         return false;
//     }
//     $userId = (int)$_SESSION['HeliUser'];

//     // 2. Query the database to get all role names for the current user.
//     $sql = "SELECT r.role_name
//             FROM user_has_roles uhr
//             JOIN users_roles r ON uhr.role_id = r.id
//             WHERE uhr.user_id = ?";
    
//     $stmt = $mysqli->prepare($sql);
//     if (!$stmt) {
//         error_log("Role check query failed to prepare: " . $mysqli->error);
//         return false; 
//     }

//     $stmt->bind_param("i", $userId);
//     $stmt->execute();
//     $result = $stmt->get_result();
    
//     $userRoles = array();
//     while ($row = $result->fetch_assoc()) {
//         $userRoles[] = $row['role_name'];
//     }
//     $stmt->close();
    
//     // 3. If the user has no roles, they can't have permission.
//     if (empty($userRoles)) {
//         return false;
//     }

//     // 4. Check if any of the user's roles exist in the list of allowed roles.
//     // array_intersect finds the common values between two arrays.
//     $matchingRoles = array_intersect($userRoles, $allowedRoles);

//     // If the resulting array is not empty, it means the user has at least one matching role.
//     return !empty($matchingRoles);
// }

/**
 * NOTE: You need to have a user_roles instad of `permissions` table that maps IDs to names.
 * Example `permissions` table schema:
 * 
 * CREATE TABLE `user_roles` (
 *   `id` INT AUTO_INCREMENT PRIMARY KEY,
 *   `permission_name` VARCHAR(100) NOT NULL UNIQUE,
 *   `description` TEXT
 * );
 * 
 * Example Data:
 * (1, 'pilot', 'Update personal information, duty schedule availabilitly, update check validities, upload photo, update password, update clock settings in hangar.php'),
 * (2, 'schedule manager pilot', 'Can modify the daily schedule'),
 * (3, 'training_manager pilot', 'Can manage training records in training.php and upload new documents in document_docufile.php'),
 * (4, 'manager pilot', 'Can view records for all users, create, edit, and delete users, add or delete crafts and contracts, upload new documents'),
 * (5, 'schedule manager', 'Can modify the daily schedule, add or delete crafts and contracts, upload new documents'),
 * (6, 'training manager', 'Can manage training records in training.php and upload new documents in document_docufile.php'),
 * (7, 'manager', 'Can view records for all users, , create, edit, and delete users, manage training records, add or delete crafts and contracts, upload new documents'),
 * (8, 'admin', 'Can access billing and subscription info, view records for all users, create, edit, and delete users, manage training records, upload new documents')
 * (9, 'admin pilot', 'All of the above')
 */
?>

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