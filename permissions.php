<?php
// permissions.php
// This file is the single source of truth for all role-based access control.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// We require the base function that talks to the database.
require_once 'login_permissions.php';

// This makes the $mysqli connection object available to our functions.
// It assumes db_connect.php is included before this file is used.
global $mysqli;

// Add this function to permissions.php
// In permissions.php - UPDATE the getUserRoleInfo function with better error handling
function getUserRoleInfo($userId, $mysqli) {
    // Return empty result if no user ID
    if (!$userId) {
        return [
            'highest_role_id' => null,
            'highest_role_name' => null,
            'all_roles' => [],
            'can_upload' => false
        ];
    }
    
    $rolePriority = [
        8 => 100, // Admin
        7 => 90,  // Manager
        4 => 80,  // Manager Pilot
        3 => 70,  // Schedule Manager Pilot
        6 => 60,  // Training Manager TRE
        5 => 50,  // Training Manager Pilot TRI
        2 => 40,  // Schedule Manager
        1 => 10   // Pilot
    ];
    
    try {
        $stmt = $mysqli->prepare("
            SELECT ur.id, ur.role_name 
            FROM user_has_roles uhr 
            JOIN users_roles ur ON uhr.role_id = ur.id 
            WHERE uhr.user_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . $mysqli->error);
        }
        
        if (!$stmt->bind_param("i", $userId)) {
            throw new Exception("Failed to bind parameters: " . $stmt->error);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        $highestRoleId = null;
        $highestRoleName = null;
        $highestPriority = -1;
        $allRoles = [];
        
        while ($row = $result->fetch_assoc()) {
            $roleId = $row['id'];
            $allRoles[] = $row['role_name'];
            
            if (isset($rolePriority[$roleId]) && $rolePriority[$roleId] > $highestPriority) {
                $highestPriority = $rolePriority[$roleId];
                $highestRoleId = $roleId;
                $highestRoleName = $row['role_name'];
            }
        }
        
        $stmt->close();
        
        return [
            'highest_role_id' => $highestRoleId,
            'highest_role_name' => $highestRoleName,
            'all_roles' => $allRoles,
            'can_upload' => in_array($highestRoleId, [3, 4, 5, 6, 7, 8])
        ];
        
    } catch (Exception $e) {
        error_log("Error in getUserRoleInfo: " . $e->getMessage());
        return [
            'highest_role_id' => null,
            'highest_role_name' => null,
            'all_roles' => [],
            'can_upload' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Usage in document_docufile.php
$userRoleInfo = [];
$canUpload = false;

if (isset($_SESSION["HeliUser"])) {
    $userRoleInfo = getUserRoleInfo($_SESSION["HeliUser"], $mysqli);
    $canUpload = $userRoleInfo['can_upload'];
    
    // Debug
    error_log("User Roles: " . implode(', ', $userRoleInfo['all_roles']));
    error_log("Highest Role: " . $userRoleInfo['highest_role_name'] . " (ID: " . $userRoleInfo['highest_role_id'] . ")");
    error_log("Can Upload: " . ($canUpload ? 'YES' : 'NO'));
}

// ===============================================================
// === CAPABILITY-BASED PERMISSION FUNCTIONS                   ===
// ===============================================================

// In permissions.php - UPDATE the isReadOnlyUser() function
function isReadOnlyUser($pageContext = '') {
    global $mysqli;
    
    // If not on daily_manager page, never treat as read-only
    if ($pageContext !== 'daily_manager') {
        return false;
    }
    
    // Only apply read-only restrictions on daily_manager.php
    $readOnlyRoleIds = [1, 2, 5, 6]; // Pilot, Schedule Manager, Training Manager Pilot TRI, Training Manager TRE
    
    if (!isset($_SESSION['user_id'])) return false;
    
    $stmt = $mysqli->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return in_array($row['role_id'], $readOnlyRoleIds);
    }
    return false;
}

function userHasSpecificRole($roleIds) {
    global $mysqli;
    if (!isset($_SESSION['user_id'])) return false;
    
    $stmt = $mysqli->prepare("SELECT role_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return in_array($row['role_id'], $roleIds);
    }
    return false;
}

// ============== BELOW pilot view all taining schedule

function canViewTrainingSchedule($userId = null) {
    global $mysqli;
    
    // Pilots can now view ALL training (read-only)
    $viewRoles = [
        'Pilot',                      // ID: 1 - CAN VIEW ALL TRAINING
        'Schedule Manager',           // ID: 2
        'Schedule Manager Pilot',     // ID: 3
        'Manager Pilot',              // ID: 4  
        'Training Manager Pilot TRI', // ID: 5
        'Training Manager TRE',       // ID: 6
        'Manager',                    // ID: 7
        'Admin'                       // ID: 8
    ];
    
    return userHasRole($viewRoles, $mysqli);
}

// ============== ABOVE pilot view all taining schedule

/**
 * Checks if the current user can manage the live flight dispatch schedule.
 * (Schedule, Prepare Notifications, History, Direct SMS tabs)
 * @return bool
 */
function canManageDispatch() {
    global $mysqli;
    $allowedRoles = [
        'Schedule Manager',
        'Schedule Manager Pilot',
        'Manager Pilot',
        'Manager',
        'Admin'
    ];
    return userHasRole($allowedRoles, $mysqli);
}

/**
 * Checks if the current user can manage administrative pilot data.
 * (Max Times, Create New Pilot, Manage Pilot, Manage Duty, Licenses Validity, Check Validity tabs)
 * @return bool
 */
function canManagePilotAdmin() {
    global $mysqli;
    $allowedRoles = [
        'Schedule Manager Pilot', // As per your description, this role can create users
        'Manager Pilot',
        'Manager',
        'Admin'
    ];
    return userHasRole($allowedRoles, $mysqli);
}

/**
 * Checks if the current user can manage company assets.
 * (Manage Crafts, Manage Contracts tabs)
 * @return bool
 */
function canManageAssets() {
    global $mysqli;
    // This typically has the same permissions as managing pilots.
    $allowedRoles = [
        'Schedule Manager Pilot',
        'Manager Pilot',
        'Manager',
        'Admin'
    ];
    return userHasRole($allowedRoles, $mysqli);
}

/**
 * Checks if the current user can manage the training schedule.
 * @return bool
 */
function canEditTrainingSchedule() {
    global $mysqli;
    $allowedRoles = [
        'Schedule Manager Pilot',
        'Training Manager Pilot TRI',
        'Training Manager TRE',
        'Manager Pilot',
        'Manager',
        'Admin'
    ];
    return userHasRole($allowedRoles, $mysqli);
}

/**
 * Checks if the user is a top-level administrator with access to special tabs like "Max Times".
 * @return bool
 */
function isSuperAdmin() {
    global $mysqli;
    $allowedRoles = [
        'Manager',
        'Admin'
    ];
    return userHasRole($allowedRoles, $mysqli);
}
?>