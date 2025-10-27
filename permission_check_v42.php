<?php
// check_permission.php

session_start();
include_once "db_connect.php";

/**
 * Check if the current user has a specific permission.
 *
 * @param string $permission_name The name of the permission to check.
 * @return bool Returns true if the user has the permission, false otherwise.
 */
function hasPermission($permission_name) {
    global $mysqli;

    // Fetch the user's role
    $user_id = $_SESSION["uid"];
    $query = "SELECT role_id FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($role_id);
    $stmt->fetch();
    $stmt->close();

    // Check if the user has the specified permission
    $query = "
        SELECT p.permission_n<?php

/**
 * Check if the current user has a specific permission.
 *
 * @param string $permission_name The name of the permission to check.
 * @return bool Returns true if the user has the permission, false otherwise.
 */
function hasPermission($permission_name) {
    global $mysqli;

    // Fetch the user's role
    $user_id = $_SESSION["HeliUser"];
    $query = "SELECT role_id FROM users WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($role_id);
    $stmt->fetch();
    $stmt->close();

    // Check if the user has the specified permission
    $query = "
        SELECT p.permission_name
        FROM role_permissions rp
        INNER JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ? AND p.permission_name = ?
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("is", $role_id, $permission_name);
    $stmt->execute();
    $stmt->store_result();

    // Return true if the user has the permission, false otherwise
    return $stmt->num_rows > 0;
}
?>ame
        FROM role_permissions rp
        INNER JOIN permissions p ON rp.permission_id = p.id
        WHERE rp.role_id = ? AND p.permission_name = ?
    ";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("is", $role_id, $permission_name);
    $stmt->execute();
    $stmt->store_result();

    // Return true if the user has the permission, false otherwise
    return $stmt->num_rows > 0;
}
?>