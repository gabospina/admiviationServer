<?php
// csrf_audit.php - Audits both PHP and JavaScript files for CSRF implementation.
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Include your existing CSRF handler
require_once 'login_csrf_handler.php';

$phpFiles = [
    // ✅ FIXED - Pilot Management
    'daily_manager_pilot_activate_deactivate.php',
    'daily_manager_create_new_pilot.php',
    'daily_manager_update_pilot.php', 
    'daily_manager_delete_pilot.php',
    'daily_manager_get_pilot_details.php', // READ ONLY - No CSRF needed

    'daily_manager_licences_validity.php', // READ ONLY - No CSRF needed
    'daily_manager_licence_validity_order.php',
    'daily_manager_delete_licence_validity.php',
    'daily_manager_create_licence_validity.php',
    'daily_manager_check_validities.php', // READ ONLY - No CSRF needed

    // Hangar
    'hangar_remove_license.php',
    'hangar_update_validity.php',
    'hangar_upload_license.php',

    // Contracts
    'contracts.php', // READ ONLY - No CSRF needed
    'contracts_add_craft.php',
    'contract_add_customer.php', // ✅ FIXED
    'contract_add_contract.php',
    'contracts_add_craft_to_contract.php',
    'contracts_add_pilot_to_contract.php',
    'contract_edit_contract.php',
    'contract_remove_contract_item.php',
    'contract_delete_customer.php', // ✅ FIXED
    'contract_delete_contract.php',
    'contract_change_color.php',
    'contract_get_all_crafts.php', // READ ONLY - No CSRF needed
    'contract_get_all_contracts.php', // READ ONLY - No CSRF needed
    'contract_get_all_pilots.php', // READ ONLY - No CSRF needed
    'contract_get_all_customers.php', // READ ONLY - No CSRF needed
    'contract_get_pilots.php', // READ ONLY - No CSRF needed
    'contract_get_details.php', // READ ONLY - No CSRF needed
    'contracts_contract_details.php', // READ ONLY - No CSRF needed

    // ✅ FIXED - Crafts
    'craft_add_craft.php',
    'craft_remove_craft.php',
    'craft_save_order.php',
    "craft_get_fleet.php", // READ ONLY - No CSRF needed
    
    // ✅ FIXED - Notification/SMS System
    'daily_manager_send_direct_sms.php',
    'daily_manager_prepare_send_notifications.php',
    'daily_manager_prepare_notifications_save_item.php',
    'daily_manager_prepare_notifications_delete_item.php',
    'daily_manager_delete_messages.php',
    'daily_manager_get_message_log.php', // READ ONLY - No CSRF needed

    'daily_manager_send_update_sms.php',
    'daily_manager_send_notifications.php',
    'daily_manager_prepare_notifications_load.php', // READ ONLY - No CSRF needed
    
    // ✅ FIXED - Schedule/Duty System
    'daily_manager_user_availability_insert.php',
    'daily_manager_user_availability_delete.php',
    'daily_manager_user_availability_get.php', // READ ONLY - No CSRF needed
    'daily_manager_user_availability_export_duty.php', // READ ONLY - No CSRF needed
    
    // Other management files
    'daily_manager_get_pilots.php', // READ ONLY - No CSRF needed
    'daily_manager_get_all_roles.php', // READ ONLY - No CSRF needed
    'daily_manager_create_contract.php',
    'daily_manager_delete_contract.php',

    // Max Times
    'stats_get_max_times.php', // READ ONLY - No CSRF needed
    'stats_update_max_times.php', // ✅ FIXED

    // Schedule
    'schedule_qualified_and_available_pilots.php', // READ ONLY - No CSRF needed
    'schedule_get_existing_assignments.php', // READ ONLY - No CSRF needed
    'schedule_update.php', // ✅ FIXED (JavaScript updated)
    'schedule_get_aircraft.php', // READ ONLY - No CSRF needed
    'schedule_save_entire.php',

    // Pilots
    'pilots_get_all_pilots.php', // READ ONLY - No CSRF needed

    // Document Management
    'document_delete_category.php',
    'document_categories_update.php',
    'document_delete.php',
    'document_get_documents.php', // READ ONLY - No CSRF needed
    'document_get_document.php', // READ ONLY - No CSRF needed
    'document_get_categories.php', // READ ONLY - No CSRF needed
    'document_changeCategoryModal.php', // READ ONLY - No CSRF needed

    // Stats/Logbook
    'stats_generate_logbook_report.php',
    'stats_update_hour_entry.php',   
    'stats_log_book_entry_delete.php', 
    'stats_log_book_entry_add.php',
    'stats_delete_experience.php',
    'stats_add_experience.php',
    'stats_print_experience.php',  // READ ONLY - No CSRF needed
    'stats_get_pilot_statistics.php', // READ ONLY - No CSRF needed
    'stats_get_all_crafts.php',   // READ ONLY - No CSRF needed
    'stats_get_stats_graph.php',     // READ ONLY - No CSRF needed
    'stats_get_craft_experience.php', // READ ONLY - No CSRF needed

    // Training System
    'training_update_drop_date.php',
    'checkAdmin.php',                
    'training_update_event.php',     
    'training_remove.php',           
    'training_enable_availability.php',   
    'training_disable_availability.php',  
    'training_add_sim_pilot.php',         
    'training_get_schedule.php',  // READ ONLY - No CSRF needed
    'trainers_get_all.php',       // READ ONLY - No CSRF needed
    'training_get_pilots.php',    // READ ONLY - No CSRF needed
    'trainer_get_all_crafts.php', // READ ONLY - No CSRF needed
    'training_get_dates.php',     // READ ONLY - No CSRF needed
    'trainers_get_tri.php',       // READ ONLY - No CSRF needed
    'trainers_get_tre.php',       // READ ONLY - No CSRF needed
];

function checkCsrfProtection($filename) {
    if (!file_exists($filename)) {
        return ['exists' => false, 'has_csrf' => false, 'uses_handler' => false, 'uses_session_csrf' => false];
    }
    
    $content = file_get_contents($filename);
    
    $hasCsrf = (strpos($content, 'csrf_token') !== false) || 
               (strpos($content, 'form_token') !== false) ||
               (strpos($content, 'CSRFHandler') !== false);
    
    $usesHandler = (strpos($content, 'login_csrf_handler.php') !== false) ||
                   (strpos($content, 'CSRFHandler') !== false);
    
    $usesSessionCsrf = (strpos($content, '$_SESSION[\'csrf_token\']') !== false) ||
                       (strpos($content, 'hash_equals($_SESSION[\'csrf_token\']') !== false);
    
    return [
        'exists' => true,
        'has_csrf' => $hasCsrf,
        'uses_handler' => $usesHandler,
        'uses_session_csrf' => $usesSessionCsrf
    ];
}

echo "<h3>PHP File CSRF Logic Audit</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th>File</th><th>Exists</th><th>Uses CSRF Handler</th><th>Has CSRF Logic</th><th>Uses Session CSRF</th><th>Action Needed</th></tr>";

// Define files that are already fixed
$fixedFiles = [
    'daily_manager_pilot_activate_deactivate.php',
    'daily_manager_create_new_pilot.php',
    'daily_manager_update_pilot.php',
    'daily_manager_delete_pilot.php',
    'contract_add_customer.php',
    'contract_delete_customer.php',
    'craft_add_craft.php',
    'craft_remove_craft.php',
    'daily_manager_prepare_send_notifications.php',
    'daily_manager_prepare_notifications_save_item.php',
    'daily_manager_prepare_notifications_delete_item.php',
    'daily_manager_user_availability_insert.php',
    'daily_manager_user_availability_delete.php',
    'stats_update_max_times.php'
];

// Define read-only files (no CSRF needed)
$readOnlyFiles = [
    'daily_manager_get_pilot_details.php',
    'daily_manager_licences_validity.php',
    'daily_manager_check_validities.php',
    'contracts.php',
    'contract_get_all_crafts.php',
    'contract_get_all_contracts.php',
    'contract_get_all_pilots.php',
    'contract_get_all_customers.php',
    'contract_get_pilots.php',
    'contract_get_details.php',
    'contracts_contract_details.php',
    'craft_get_fleet.php',
    'daily_manager_get_message_log.php',
    'daily_manager_prepare_notifications_load.php',
    'daily_manager_user_availability_get.php',
    'daily_manager_user_availability_export_duty.php',
    'daily_manager_get_pilots.php',
    'daily_manager_get_all_roles.php',
    'stats_get_max_times.php',
    'schedule_qualified_and_available_pilots.php',
    'schedule_get_existing_assignments.php',
    'schedule_get_aircraft.php',
    'pilots_get_all_pilots.php',
    'document_get_documents.php',
    'document_get_document.php',
    'document_get_categories.php',
    'document_changeCategoryModal.php',
    'stats_print_experience.php',
    'stats_get_pilot_statistics.php',
    'stats_get_all_crafts.php',
    'stats_get_stats_graph.php',
    'stats_get_craft_experience.php',
    'training_get_schedule.php',
    'trainers_get_all.php',
    'training_get_pilots.php',
    'trainer_get_all_crafts.php',
    'training_get_dates.php',
    'trainers_get_tri.php',
    'trainers_get_tre.php'
];

foreach ($phpFiles as $file) {
    $result = checkCsrfProtection($file);
    
    $action = '';
    $rowStyle = '';
    
    if (!$result['exists']) {
        $action = '<span style="color: red;">FILE NOT FOUND</span>';
    } elseif (in_array($file, $fixedFiles)) {
        $action = '<span style="color: green;">✅ FIXED (Session CSRF)</span>';
        $rowStyle = 'style="background-color: #e8f5e8;"';
    } elseif (in_array($file, $readOnlyFiles)) {
        $action = '<span style="color: blue;">READ ONLY - No CSRF needed</span>';
        $rowStyle = 'style="background-color: #e8f0ff;"';
    } elseif ($result['uses_session_csrf']) {
        $action = '<span style="color: green;">✅ PROTECTED (Session CSRF)</span>';
        $rowStyle = 'style="background-color: #e8f5e8;"';
    } elseif ($result['uses_handler'] && $result['has_csrf']) {
        $action = '<span style="color: orange;">⚠️ Uses Class Handler (Update to Session)</span>';
        $rowStyle = 'style="background-color: #fff3cd;"';
    } else {
        $action = '<span style="color: red;">❌ ADD CSRF PROTECTION</span>';
        $rowStyle = 'style="background-color: #ffe0e0;"';
    }
    
    echo "<tr $rowStyle>";
    echo "<td><code>$file</code></td>";
    echo "<td align='center'>" . ($result['exists'] ? '✓' : '✗') . "</td>";
    echo "<td align='center'>" . ($result['uses_handler'] ? '✓' : '✗') . "</td>";
    echo "<td align='center'>" . ($result['has_csrf'] ? '✓' : '✗') . "</td>";
    echo "<td align='center'>" . ($result['uses_session_csrf'] ? '✓' : '✗') . "</td>";
    echo "<td>$action</td>";
    echo "</tr>";
}

echo "</table>";

// ======================================================================
// === NEW JAVASCRIPT AUDIT SECTION ===
// ======================================================================
$jsFiles = [
    // Add all JS files that make POST requests
    'daily_manager-contracts.js',
    'daily_manager-crafts.js',
    'daily_manager-maxtimes.js', // ✅ FIXED
    'daily_manager-pilots.js',
    'daily_manager-utils.js',
    'daily_manager-validity.js',
    
    'contractfunctions.js',
    'craftfunctions.js',
    'loginfunctions.js',
    'documentfunctions.js',

    'hangar-clock.js',
    'hangar-main.js',
    'hangar-validity.js',

    'notificationManagerFunctions.js', // ✅ FIXED
    'permission-controls.js',
    'pilotfunctions.js',

    'scheduleHomeReadOnlyFunctions.js',
    'scheduleManagerDutyFunctions.js',
    'scheduleManagerFunctions.js', // ❌ NEEDS UPDATE

    'stats-experience.js',
    'stats-graphs.js',
    'stats-logbook.js',
    'stats-main.js',
    'stats-reports.js',
    
    'training-ajax-operations.js',
    'training-calendar.js',
    'training-date-utils.js',
    'training-event-handlers.js',
    'training-main.js',
    'training-modal-handlers.js', // ❌ NEEDS UPDATE
    'training-modal-utilities.js',
    'training-utilities.js',
    'training-year-view.js',
];

function auditJavaScriptFiles($filename) {
    if (!file_exists($filename)) {
        return ['exists' => false, 'uses_old_id' => false, 'uses_old_key' => false];
    }
    
    $content = file_get_contents($filename);
    
    // Check for the old ID '#csrf_token_manager'
    $usesOldId = (strpos($content, '#csrf_token_manager') !== false);
    
    // Check for the old data key 'csrf_token:'
    $usesOldKey = (preg_match("/csrf_token\s*:/", $content) === 1);
    
    // Check for correct implementation
    $usesCorrectId = (strpos($content, '#form_token_manager') !== false);
    $usesCorrectKey = (preg_match("/form_token\s*:/", $content) === 1);
    
    return [
        'exists' => true,
        'uses_old_id' => $usesOldId,
        'uses_old_key' => $usesOldKey,
        'uses_correct_id' => $usesCorrectId,
        'uses_correct_key' => $usesCorrectKey
    ];
}

// Define fixed JavaScript files
$fixedJsFiles = [
    'daily_manager-maxtimes.js',
    'notificationManagerFunctions.js'
];

echo "<h3>JavaScript CSRF Implementation Audit</h3>";
echo "<p>This checks for the old ID <code>#csrf_token_manager</code> and the old data key <code>csrf_token:</code>. Files that need updating will be highlighted.</p>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th>JavaScript File</th><th>Exists</th><th>Uses Old ID?</th><th>Uses Old Key?</th><th>Uses Correct ID?</th><th>Action Needed</th></tr>";

foreach ($jsFiles as $file) {
    // We need to adjust the path if the JS files are in a subfolder
    $pathToFile = 'js/' . $file; // Assuming they are in a /js/ folder
    if (!file_exists($pathToFile)) {
       $pathToFile = $file; // Fallback to root if not in /js/
    }

    $result = auditJavaScriptFiles($pathToFile);
    
    $action = '';
    $rowStyle = '';
    
    if (!$result['exists']) {
        $action = '<span style="color: grey;">FILE NOT FOUND</span>';
    } elseif (in_array($file, $fixedJsFiles)) {
        $action = '<span style="color: green;">✅ FIXED</span>';
        $rowStyle = 'style="background-color: #e8f5e8;"';
    } elseif ($result['uses_correct_id'] && $result['uses_correct_key']) {
        $action = '<span style="color: green;">✅ CORRECT</span>';
        $rowStyle = 'style="background-color: #e8f5e8;"';
    } elseif ($result['uses_old_id'] || $result['uses_old_key']) {
        $action = '<strong style="color: red;">❌ UPDATE TO #form_token_manager and form_token:</strong>';
        $rowStyle = 'style="background-color: #ffe0e0;"';
    } else {
        $action = '<span style="color: orange;">⚠️ CHECK MANUALLY</span>';
    }
    
    echo "<tr $rowStyle>";
    echo "<td><code>$file</code></td>";
    echo "<td align='center'>" . ($result['exists'] ? '✓' : '✗') . "</td>";
    echo "<td align='center'>" . ($result['uses_old_id'] ? '<strong style="color:red;">YES</strong>' : 'No') . "</td>";
    echo "<td align='center'>" . ($result['uses_old_key'] ? '<strong style="color:red;">YES</strong>' : 'No') . "</td>";
    echo "<td align='center'>" . ($result['uses_correct_id'] ? '✓' : '✗') . "</td>";
    echo "<td>$action</td>";
    echo "</tr>";
}

echo "</table>";
?>