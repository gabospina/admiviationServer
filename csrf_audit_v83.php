<?php
// csrf_audit.php - Audits both PHP and JavaScript files for CSRF implementation.
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Include your existing CSRF handler
require_once 'login_csrf_handler.php';

$phpFiles = [
    // Pilot Management
    'daily_manager_pilot_activate_deactivate.php',
    'daily_manager_create_new_pilot.php',
    'daily_manager_update_pilot.php', 
    'daily_manager_delete_pilot.php',
    'daily_manager_get_pilot_details.php', // READ ONLY - probably doesn't need CSRF

    'daily_manager_licences_validity.php', // NO CSRF as they don't modify data
    'daily_manager_licence_validity_order.php',
    'daily_manager_delete_licence_validity.php',
    'daily_manager_create_licence_validity.php',
    'daily_manager_check_validities.php',

    // hangar.php
    'hangar_remove_license.php',
    'hangar_update_validity.php',
    'hangar_upload_license.php',

    // contract
    'contracts.php', // NO CSRF as they don't modify data
    'contracts_add_craft.php',
    'contract_add_customer.php',
    'contract_add_contract.php',
    'contracts_add_craft_to_contract.php',
    'contracts_add_pilot_to_contract.php',
    'contract_edit_contract.php',
    'contract_remove_contract_item.php',
    'contract_delete_customer.php',
    'contract_delete_contract.php',
    'contract_change_color.php',
    'contract_get_all_crafts.php',
    'contract_get_all_contracts.php',
    'contract_get_all_pilots.php',
    'contract_get_all_customers.php',
    'contract_get_pilots.php',
    'contract_get_details.php',
    'contracts_contract_details.php',

    // crafts
    'craft_add_craft.php',
    'craft_remove_craft.php',
    'craft_save_order.php',
    "craft_get_fleet.php",
    
    // Notification/SMS System
    'daily_manager_send_direct_sms.php',
    'daily_manager_prepare_send_notifications.php',
    'daily_manager_prepare_notifications_save_item.php',
    'daily_manager_prepare_notifications_delete_item.php',
    'daily_manager_delete_messages.php',
    'daily_manager_get_message_log.php', //  PROBABLY DOESN'T need CSRF

    'daily_manager_send_update_sms.php',
    'daily_manager_send_notifications.php',
    'daily_manager_prepare_notifications_load.php',
    
    // Schedule/Duty System
    'daily_manager_user_availability_insert.php',
    'daily_manager_user_availability_delete.php',
    'daily_manager_user_availability_get.php', // READ ONLY
    'daily_manager_user_availability_export_duty.php', // NO CSRF as they don't modify data
    
    // Data Export (might need CSRF if it modifies state)
    'daily_manager_user_availability_export_duty.php',
    
    // Other management files
    'daily_manager_get_pilots.php', // READ ONLY
    'daily_manager_get_all_roles.php', // READ ONLY
    'daily_manager_create_contract.php',
    'daily_manager_delete_contract.php',

    // maxi times
    'stats_get_max_times.php', // NO CSRF as they don't modify data
    'stats_update_max_times.php',

    // schedule
    'schedule_qualified_and_available_pilots.php',
    'schedule_get_existing_assignments.php',
    'schedule_update.php',
    'schedule_get_aircraft.php',
    'schedule_save_entire.php',

    // pilots
    'pilots_get_all_pilots.php',

    // document
    'document_delete_category.php',
    'document_categories_update.php',
    'document_delete.php',
    'document_get_documents.php', // NO CSRF as they don't modify data
    'document_get_document.php', // NO CSRF as they don't modify data
    'document_get_categories.php', // NO CSRF as they don't modify data
    'document_changeCategoryModal.php', // NO CSRF as they don't modify data

    // stats
    'stats_generate_logbook_report.php',
    'stats_update_hour_entry.php',   
    'stats_log_book_entry_delete.php', 
    'stats_log_book_entry_add.php',
    'stats_delete_experience.php',
    'stats_add_experience.php',

    'stats_print_experience.php',  // NO CSRF - Read-only PDF generation
    'stats_get_pilot_statistics.php', // NO CSRF - Reads logbook data
    'stats_get_all_crafts.php',   // NO CSRF - Reads craft data
    'stats_get_stats_graph.php',     // NO CSRF - Reads graph data
    'stats_get_craft_experience.php', // NO CSRF - Reads experience data


    // training
    'training_update_drop_date.php', //  Updates event dates via drag-drop
    'checkAdmin.php',                //  Verifies admin privileges  
    'training_update_event.php',     //  Updates training events
    'training_remove.php',           //  Removes training events
    'training_enable_availability.php',   //  Enables training dates
    'training_disable_availability.php',  //  Disables training dates
    'training_add_sim_pilot.php',         //  Adds new training events
    'training_update_event.php',          //  Updates existing events

    'training_get_schedule.php',  //  NO CSRF - Reads calendar events
    'trainers_get_all.php',       //  NO CSRF - Reads trainer data
    'training_get_pilots.php',    //  NO CSRF - Reads pilot availability
    'trainer_get_all_crafts.php', //  NO CSRF - Reads aircraft types
    'training_get_dates.php',     //  NO CSRF - Reads training dates
    'trainers_get_tri.php',       //  NO CSRF - Reads TRI availability
    'trainers_get_tre.php',       //  NO CSRF - Reads TRE availability
];

function checkCsrfProtection1($filename) {
    if (!file_exists($filename)) {
        return ['exists' => false, 'has_csrf' => false, 'uses_handler' => false];
    }
    
    $content = file_get_contents($filename);
    
    $hasCsrf = (strpos($content, 'csrf_token') !== false) || 
               (strpos($content, 'CSRFHandler') !== false);
    
    $usesHandler = (strpos($content, 'login_csrf_handler.php') !== false) ||
                   (strpos($content, 'CSRFHandler') !== false);
    
    return [
        'exists' => true,
        'has_csrf' => $hasCsrf,
        'uses_handler' => $usesHandler
    ];
}

function checkCsrfProtection($filename) {
    if (!file_exists($filename)) return ['exists' => false, 'has_csrf' => false];
    $content = file_get_contents($filename);
    $hasCsrf = (strpos($content, 'csrf_token') !== false) || (strpos($content, 'form_token') !== false);
    return ['exists' => true, 'has_csrf' => $hasCsrf];
}

echo "<h3>PHP File CSRF Logic Audit</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th>File</th><th>Exists</th><th>Uses CSRF Handler</th><th>Has CSRF Logic</th><th>Action Needed</th></tr>";

foreach ($phpFiles as $file) {
    $result = checkCsrfProtection($file);
    
    $action = '';
    if (!$result['exists']) {
        $action = '<span style="color: red;">FILE NOT FOUND</span>';
    } elseif ($result['uses_handler'] && $result['has_csrf']) {
        $action = '<span style="color: green;">PROTECTED ✓</span>';
    } elseif (!$result['uses_handler'] && strpos($file, 'get_') === 0) {
        $action = '<span style="color: blue;">READ ONLY - OK</span>';
    } elseif (!$result['uses_handler'] && strpos($file, 'load') !== false) {
        $action = '<span style="color: blue;">READ ONLY - OK</span>';
    } else {
        $action = '<span style="color: orange;">ADD CSRF PROTECTION</span>';
    }
    
    echo "<tr>";
    echo "<td><code>$file</code></td>";
    echo "<td align='center'>" . ($result['exists'] ? '✓' : '✗') . "</td>";
    echo "<td align='center'>" . ($result['uses_handler'] ? '✓' : '✗') . "</td>";
    echo "<td align='center'>" . ($result['has_csrf'] ? '✓' : '✗') . "</td>";
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
    'daily_manager-maxtimes.js',
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

    'notificationManagerFunctions.js',
    'permission-controls.js',
    'pilotfunctions.js',

    'scheduleHomeReadOnlyFunctions.js',
    'scheduleManagerDutyFunctions.js',
    'scheduleManagerFunctions.js',

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
    'training-modal-handlers.js',
    'training-modal-utilities.js',
    'training-utilities.js',
    'training-year-view.js',

    // Add any other relevant JS files here
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
    
    return [
        'exists' => true,
        'uses_old_id' => $usesOldId,
        'uses_old_key' => $usesOldKey
    ];
}

echo "<h3>JavaScript CSRF Implementation Audit</h3>";
echo "<p>This checks for the old ID <code>#csrf_token_manager</code> and the old data key <code>csrf_token:</code>. Files that need updating will be highlighted.</p>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'><th>JavaScript File</th><th>Exists</th><th>Uses Old ID?</th><th>Uses Old Key?</th><th>Action Needed</th></tr>";

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
    } elseif ($result['uses_old_id'] || $result['uses_old_key']) {
        $action = '<strong style="color: red;">UPDATE TO #form_token_manager and form_token:</strong>';
        $rowStyle = 'style="background-color: #ffe0e0;"';
    } else {
        $action = '<span style="color: green;">LOOKS OK ✓</span>';
    }
    
    echo "<tr $rowStyle>";
    echo "<td><code>$file</code></td>";
    echo "<td align='center'>" . ($result['exists'] ? '✓' : '✗') . "</td>";
    echo "<td align='center'>" . ($result['uses_old_id'] ? '<strong style="color:red;">YES</strong>' : 'No') . "</td>";
    echo "<td align='center'>" . ($result['uses_old_key'] ? '<strong style="color:red;">YES</strong>' : 'No') . "</td>";
    echo "<td>$action</td>";
    echo "</tr>";
}

echo "</table>";
?>