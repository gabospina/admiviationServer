<?php
// update_csrf_files.php - Batch update PHP files to session-based CSRF

if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}

// Files that need CSRF updates (from your audit)
$filesToUpdate = [
    // Licence Management
    'daily_manager_licence_validity_order.php',
    'daily_manager_delete_licence_validity.php',
    'daily_manager_create_licence_validity.php',
    
    // Hangar Management
    'hangar_remove_license.php',
    'hangar_update_validity.php',
    'hangar_upload_license.php',
    
    // Contract Management
    'contracts_add_craft.php',
    'contract_add_contract.php',
    'contracts_add_craft_to_contract.php',
    'contracts_add_pilot_to_contract.php',
    'contract_edit_contract.php',
    'contract_remove_contract_item.php',
    'contract_delete_contract.php',
    'contract_change_color.php',
    
    // Craft Management
    'craft_save_order.php',
    
    // SMS/Notification
    'daily_manager_send_direct_sms.php',
    'daily_manager_send_update_sms.php',
    'daily_manager_send_notifications.php',
    
    // Contract Operations
    'daily_manager_create_contract.php',
    'daily_manager_delete_contract.php',
    
    // Schedule
    'schedule_save_entire.php',
    
    // Document Management
    'document_delete_category.php',
    'document_categories_update.php',
    'document_delete.php',
    
    // Admin
    'checkAdmin.php',
    
    // Stats/Logbook (missing CSRF)
    'daily_manager_delete_messages.php',
    'stats_generate_logbook_report.php',
    'stats_update_hour_entry.php',
    'stats_log_book_entry_delete.php',
    'stats_log_book_entry_add.php',
    'stats_delete_experience.php',
    'stats_add_experience.php',
    
    // Training System (missing CSRF)
    'training_update_drop_date.php',
    'training_update_event.php',
    'training_remove.php',
    'training_enable_availability.php',
    'training_disable_availability.php',
    'training_add_sim_pilot.php'
];

function updateFileCsrf($filename) {
    if (!file_exists($filename)) {
        return ['success' => false, 'message' => "File not found: $filename"];
    }
    
    $content = file_get_contents($filename);
    $originalContent = $content;
    
    // Check current state
    $hasClassHandler = (strpos($content, 'require_once \'login_csrf_handler.php\'') !== false) ||
                      (strpos($content, 'CSRFHandler::validateToken') !== false);
    
    $hasSessionCsrf = (strpos($content, 'hash_equals($_SESSION[\'csrf_token\']') !== false) ||
                      (strpos($content, '$_SESSION[\'csrf_token\']') !== false);
    
    // Apply updates based on current state
    $changes = [];
    
    if ($hasClassHandler) {
        // Remove class-based CSRF handler
        $content = str_replace("require_once 'login_csrf_handler.php';", "// REMOVED: require_once 'login_csrf_handler.php';", $content);
        $changes[] = "Removed class-based CSRF handler";
    }
    
    // Add session-based CSRF validation after session_start()
    if (strpos($content, 'session_start()') !== false && !$hasSessionCsrf) {
        $csrfValidationCode = <<<'PHP'

// --- SESSION-BASED CSRF VALIDATION ---
$submitted_token = $_POST['form_token'] ?? '';

if (empty($submitted_token)) {
    throw new Exception("Security token missing. Please refresh the page.", 403);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    throw new Exception("Invalid security token. Please refresh the page.", 403);
}

PHP;

        // Insert after session_start()
        $content = preg_replace(
            '/(session_start\(\)[^;]*;)/',
            "$1\n$csrfValidationCode",
            $content
        );
        $changes[] = "Added session-based CSRF validation";
    }
    
    // Replace old CSRFHandler calls
    if (preg_match('/CSRFHandler::validateToken\([^)]+\)/', $content)) {
        $content = preg_replace('/CSRFHandler::validateToken\([^)]+\);/', '// REMOVED: CSRFHandler::validateToken', $content);
        $changes[] = "Removed CSRFHandler validation calls";
    }
    
    // Add token regeneration on success
    if (strpos($content, 'success') !== false && !strpos($content, '$_SESSION[\'csrf_token\'] = bin2hex')) {
        // Look for success response patterns and add token regeneration
        $successPattern = '/(\$response\[[\'"]success[\'"]\]\s*=\s*true)/';
        if (preg_match($successPattern, $content)) {
            $tokenRegenCode = "\n    \n    // Regenerate CSRF token on success\n    \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));\n    ";
            $content = preg_replace($successPattern, "$1;$tokenRegenCode", $content);
            $changes[] = "Added token regeneration on success";
        }
    }
    
    // Update response to include new token
    if (strpos($content, '$_SESSION[\'csrf_token\'] = bin2hex') !== false) {
        $tokenResponseCode = "\$response['new_csrf_token'] = \$_SESSION['csrf_token'];";
        if (strpos($content, $tokenResponseCode) === false) {
            // Add to JSON response
            $content = preg_replace(
                '/(\$response\s*=\s*\[[^]]*)(\];)/',
                "$1, 'new_csrf_token' => \$_SESSION['csrf_token']$2",
                $content
            );
            $changes[] = "Added new_csrf_token to response";
        }
    }
    
    // Write changes if any were made
    if ($content !== $originalContent) {
        if (file_put_contents($filename, $content)) {
            return [
                'success' => true, 
                'message' => "Updated: " . implode(', ', $changes),
                'changes' => $changes
            ];
        } else {
            return ['success' => false, 'message' => "Failed to write changes to: $filename"];
        }
    } else {
        return ['success' => true, 'message' => "No changes needed - already up to date"];
    }
}

// Process files
echo "<h3>CSRF File Update Report</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th>File</th><th>Status</th><th>Changes Made</th></tr>";

foreach ($filesToUpdate as $file) {
    $result = updateFileCsrf($file);
    
    $statusColor = $result['success'] ? 'green' : 'red';
    $statusText = $result['success'] ? '✓ UPDATED' : '✗ FAILED';
    
    $changes = isset($result['changes']) ? implode(', ', $result['changes']) : 'No changes';
    
    echo "<tr>";
    echo "<td><code>$file</code></td>";
    echo "<td style='color: $statusColor;'><strong>$statusText</strong></td>";
    echo "<td>{$result['message']}</td>";
    echo "</tr>";
}

echo "</table>";

// Also provide manual update templates for complex files
echo "<h3>Manual Update Required For:</h3>";
echo "<ul>";
$complexFiles = [
    'stats_generate_logbook_report.php' => "Complex PDF generation - needs manual CSRF integration",
    'training_update_drop_date.php' => "Drag-drop functionality - needs AJAX CSRF integration",
    'training_update_event.php' => "Calendar event updates - needs manual integration",
];
foreach ($complexFiles as $file => $reason) {
    echo "<li><code>$file</code> - $reason</li>";
}
echo "</ul>";

?>