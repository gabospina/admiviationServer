<?php
// assets/php/auth_helpers.php

if (session_status() == PHP_SESSION_NONE) {
    // It's good practice for helper files not to start sessions themselves,
    // but to assume the calling script has already handled it.
    // However, if these functions might be called very early, you might need it.
    // For now, let's assume the calling script starts the session.
}

/**
 * Checks if a user has a specific role in a given company.
 *
 * @param mysqli $mysqli The mysqli connection object.
 * @param int $userId The ID of the user.
 * @param int $roleId The ID of the role to check for.
 * @param int $companyId The ID of the company context.
 * @return bool True if the user has the role, false otherwise.
 */
function userHasRole($mysqli, $userId, $roleId, $companyId) {
    if (!$mysqli || $userId === null || $roleId === null || $companyId === null) {
        // Basic validation or error logging
        error_log("userHasRole: Invalid parameters provided.");
        return false;
    }
    $sql = "SELECT 1 FROM user_has_roles WHERE user_id = ? AND role_id = ? AND company_id = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("userHasRole: Prepare failed: " . $mysqli->error);
        return false;
    }
    $stmt->bind_param("iii", $userId, $roleId, $companyId);
    $stmt->execute();
    $stmt->store_result(); // Important for num_rows
    $hasRole = $stmt->num_rows > 0;
    $stmt->close();
    return $hasRole;
}

/**
 * Checks if a user has a specific permission key in a given company,
 * based on their assigned roles.
 *
 * @param mysqli $mysqli The mysqli connection object.
 * @param int $userId The ID of the user.
 * @param string $permissionKey The key of the permission to check for (e.g., 'manage_training_schedule').
 * @param int $companyId The ID of the company context.
 * @return bool True if the user has the permission, false otherwise.
 */
function userHasPermission($mysqli, $userId, $permissionKey, $companyId) {
    if (!$mysqli || $userId === null || empty($permissionKey) || $companyId === null) {
        error_log("userHasPermission: Invalid parameters provided.");
        error_log("userHasPermission called with: UserID={$userId}, PermissionKey='{$permissionKey}', CompanyID={$companyId}");
        return false;
    }
    $sql = "SELECT 1
            FROM user_has_roles uhr
            JOIN role_permissions rp ON uhr.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE uhr.user_id = ? 
              AND uhr.company_id = ? 
              AND p.permission_key = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        error_log("userHasPermission: Prepare failed: " . $mysqli->error);
        return false;
    }
    $stmt->bind_param("iis", $userId, $companyId, $permissionKey);
    $stmt->execute();
    $stmt->store_result();
    $hasPermission = $stmt->num_rows > 0;
    $stmt->close();
    return $hasPermission;
}
?>