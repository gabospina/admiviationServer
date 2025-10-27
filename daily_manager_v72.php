<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
file_put_contents('ajax_session_debug.log', print_r($_SESSION, true) . "\n", FILE_APPEND);

// --- Check for essential session variables needed for this page ---
// Redirect to login if user info or company info is missing
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) { 
    error_log("Session missing HeliUser or company_id in daily_manager.php. Redirecting to login.");
    header("Location: index.php"); 
    exit; // Stop script execution
}

require_once 'db_connect.php'; // Ensures $mysqli is available
require_once 'permissions.php'; // Include our new permission firewall

// --- Determine User Capabilities for this Page ---
// Determine user capabilities for this page
$canManageDispatch = canManageDispatch();
$canManagePilots = canManagePilotAdmin();
$canManageAssets = canManageAssets();
$isSuperAdmin = isSuperAdmin();

// Any logged-in user can view the page, but what they can DO is controlled by the variables above.

$page = "daily_manager"; // Define page context BEFORE header include
include_once "header.php";

?>

<style>
    /* Container for the PIC/SIC groups inside a schedule cell */
    .schedule-cell-container {
        display: flex;
        flex-direction: column; /* THIS IS THE FIX for stacking PIC under SIC */
        gap: 5px;               /* Vertical space between PIC and SIC */
        padding: 4px;
    }

    /* A group containing one label and one select dropdown */
    .pilot-select-group {
        display: flex;
        flex-direction: column;
        width: 100%; /* Make group take full width of its container */
    }

    /* The "PIC" or "SIC" label */
    .pilot-select-group label {
        font-size: 10px;
        font-weight: bold;
        color: #333;
        margin-bottom: 2px;
        text-align: left;
    }

    /* The pilot dropdown itself */
    .pilot-select-group .pilot-select {
        width: 100%;
        box-sizing: border-box;
        padding: 2px;
        font-size: 12px;
    }

    /* Cell containing the aircraft registration */
    .registration-cell {
        white-space: nowrap; /* THIS IS THE FIX for preventing text wrap */
        vertical-align: middle !important; /* Good for alignment */
    }
</style>

<style>
    /* Add any specific styles for daily_manager if needed */
    /* You can include the input sizing styles here or link statistics-styles.css if appropriate */
    #max-times-limits-fields .form-control.limit-input {
            max-width: 12em; /* Example width for limit inputs */
    }
    .tab-container .tabs {
        display: flex;
        flex-wrap: wrap; /* Allow tabs to wrap on smaller screens */
        border-bottom: 1px solid #ddd;
        margin-bottom: 10px;
    }
    .tab-container .tabs .tab.active {
        background-color: #fff;
        border-color: #ddd;
        border-bottom-color: #fff; /* Creates the effect of the tab merging with content */
        font-weight: bold;
        position: relative;
        top: 1px; /* Slightly raise active tab */
    }
    .tab-content > .tab-pane {
        display: none; /* Hide inactive tabs */
        padding: 15px;
        border: 1px solid #ddd;
        border-top: none; /* Connected to the active tab button */
        background-color: #fff;
    }
    .tab-content > .tab-pane.active {
    display: block !important;
    visibility: visible !important;
    }
    #newPilot_username_status {
        font-size: 0.9em;
        margin-top: 5px;
    }
    #newPilot_username_status.taken { color: red; }
    #newPilot_username_status.available { color: green; }

    #managePilotsTable th, #managePilotsTable td { vertical-align: middle; }
    .action-btn { margin-right: 5px; }

    /* ------------------------------------------------ */
    /* --- CSS FOR SCHEDULE TABLE LAYOUT & SPACING --- */
    /* ------------------------------------------------ */

    /* This rule ensures the table uses a modern, clean border model */
    table.fullsched {
        border-collapse: collapse !important;
    }

    /*
      PART A: This styles the empty spacer row that creates the
      large gap between the last aircraft and the green bar.
    */
    tr.spacer-above-type > td {
        height: 15px; /* <-- ADJUST THIS to control the size of the white gap */
        padding: 0;
        border: none;
    }

    /*
  ================================================================
  === THE FINAL, GUARANTEED FIX FOR THE HEADER BAR =============
  ================================================================
    */

    /*
    PART 1: Style the <th> element itself.
    Its only job is to be the full-width green container.
    */
    tr.type-header > th {
        background-color: rgb(236, 241, 237); /* The green color */
        height: 24px;                        /* The total height of the bar */
        padding: 0;                          /* CRITICAL: Remove all padding from the container */
        border: none;
    }

    /*
    PART 2: Style the new <div class="title-wrapper"> inside the <th>.
    Its only job is to align the text.
    */
    tr.type-header > th .title-wrapper {
        color: white;
        font-weight: bold;
        height: 100%; /* Make the wrapper fill the full 24px height of the <th> */

        /* --- The Flexbox properties for perfect alignment --- */
        display: flex;
        justify-content: center; /* Horizontally centers the text */
        align-items: flex-end;   /* Vertically aligns the text to the BOTTOM */
        padding-bottom: 3px;     /* Adds a tiny space so the text isn't touching the edge */
        box-sizing: border-box;  /* Ensures padding doesn't add to the height */
    }


    /*
      ===============================================================
      === Default Spacing for Normal Aircraft Rows ==================
      ===============================================================
    */
    table.fullsched tbody tr[data-craft-id] td {
        padding-top: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #dee2e6; /* The light gray line between aircraft */
    }

</style>
<!-- CSS block for alignment -->
<style>
    .legend-colors-container {
    display: flex;
    flex-direction: column; /* This is the key change: stacks items vertically */
    align-items: flex-start; /* Aligns items to the left within the container */
    gap: 5px; /* Sets the space between each vertical item */
    /* We move the centering to the parent container if needed */
    margin: 0 auto; /* Centers the whole block within its column */
    width: fit-content; /* Makes the container only as wide as its content */
    }
    .legend-item {
        display: flex;
        align-items: center;
    }
    .color-box {
        width: 15px;
        height: 15px;
        margin-right: 5px;
        border: 1px solid #999;
        flex-shrink: 0;
    }
</style>

<style>
    #ai-assistant-btn.listening {
        background-color: #d9534f !important; /* A red color */
        border-color: #d43f3a !important;
        animation: pulse 1.5s infinite;
    }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(217, 83, 79, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(217, 83, 79, 0); }
        100% { box-shadow: 0 0 0 0 rgba(217, 83, 79, 0); }
    }
</style>

<style>
    /* Add to your existing CSS section */
    .read-only-mode {
        opacity: 0.9;
    }

    .read-only-mode button:disabled,
    .read-only-mode input:disabled,
    .read-only-mode select:disabled,
    .read-only-mode textarea:disabled {
        cursor: not-allowed;
        background-color: #f8f9fa !important;
        border-color: #dee2e6 !important;
        color: #6c757d !important;
        opacity: 0.8;
    }

    .read-only-badge {
        position: fixed;
        top: 70px;
        right: 20px;
        background: linear-gradient(45deg, #ffc107, #ff9800);
        color: #000;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
        z-index: 10000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        border: 2px solid #ff9800;
        animation: pulse-badge 2s infinite;
    }

    @keyframes pulse-badge {
        0% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(255, 152, 0, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 152, 0, 0); }
    }

    /* Special styling for disabled tabs */
    .read-only-mode .tab:not(.active) {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .read-only-mode .tab.active {
        background-color: #f8f9fa;
        border-color: #dee2e6;
        color: #6c757d;
}
</style>

<!-- ========================================================= -->
<!-- === CSS FOR COMPACT PILOT DUTY SCHEDULE ACCORDION     === -->
<!-- ========================================================= -->
<style>
    /* Reduces the space BETWEEN each pilot's collapsible panel */
    .pilot-duty-panel {
        margin-bottom: 4px !important; /* Default is 20px, this makes it much tighter */
    }

    /* Reduces the padding AROUND the pilot's name (makes the clickable bar shorter) */
    .pilot-duty-panel .panel-heading {
        padding: 6px 15px; /* Reduced vertical padding from 10px to 6px */
    }

    /* Reduces the FONT SIZE of the pilot's name itself */
    .pilot-duty-panel .panel-title a {
        font-size: 14px; /* Smaller, more standard font size */
        font-weight: normal; /* Optional: Makes it less bold */
    }

    /* ========================================================= */
    /* === ADD THESE NEW STYLES FOR ON/OFF DUTY COLORS       === */
    /* ========================================================= */
    .pilot-duty-panel .panel-heading.duty-status-on {
        background-color: #d9edf7 !important; /* Light Blue */
        border-color: #bce8f1 !important;
    }
    .pilot-duty-panel .panel-heading.duty-status-off {
        background-color: #dff0d8 !important; /* Light Green */
        border-color: #d6e9c6 !important;
    }
</style>

<style>
    /* ALERT MESSAGES - Add to your CSS in daily_manager.php */
    .read-only-mode .btn:not([data-always-enabled]):hover {
        cursor: not-allowed;
        background-color: #f8f9fa !important;
        border-color: #dee2e6 !important;
        opacity: 0.65;
        position: relative;
    }

    .read-only-mode .btn:not([data-always-enabled]):hover::after {
        content: "🚫 Read-Only";
        position: absolute;
        top: -30px;
        left: 50%;
        transform: translateX(-50%);
        background: #ffc107;
        color: #000;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        white-space: nowrap;
        z-index: 1000;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    /* Pulse animation for disabled elements */
    @keyframes pulse-warning {
        0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
    }

    .read-only-mode .btn:not([data-always-enabled]):hover {
        animation: pulse-warning 2s infinite;
    }
</style>
<!-- ========================================================= -->

<?php $page_context = "daily_manager"; ?>

<script>
// UPDATE in daily_manager.php - Pass the page context
const userPermissions = {
    isReadOnly: <?php echo isReadOnlyUser('daily_manager') ? 'true' : 'false'; ?>,
    canManageDispatch: <?php echo canManageDispatch() ? 'true' : 'false'; ?>,
    canManagePilots: <?php echo canManagePilotAdmin() ? 'true' : 'false'; ?>,
    canManageAssets: <?php echo canManageAssets() ? 'true' : 'false'; ?>,
    isSuperAdmin: <?php echo isSuperAdmin() ? 'true' : 'false'; ?>,
    userId: <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'null'; ?>,
    userRole: '<?php echo isset($_SESSION['user_role']) ? addslashes($_SESSION['user_role']) : ''; ?>',
    currentPage: 'daily_manager' // Add page context
};

var currentPageContext = '<?php echo $page_context; ?>';
</script>

<script>
  var currentPageContext = '<?php echo $page_context; ?>';
</script>

    <!-- JS variable output -->

    <div class="light-bg">
        <!-- ========================================================= -->
        <!-- === START: INSERT THE CSRF TOKEN INPUT FIELD HERE       === -->
        <!-- ========================================================= -->
        <input type="hidden" name="csrf_token" id="csrf_token_manager" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
        
        <!-- === DEBUGGING LINE: Add this right below the input === -->
        <!-- Page Load Token: <?php echo htmlspecialchars($_SESSION['csrf_token'] ?? 'NOT SET'); ?> -->
        <!-- ========================================================= -->
        
        <!-- ========================================================= -->
        <!-- === END: CSRF TOKEN INPUT FIELD                         === -->
        <!-- ========================================================= -->

      <div class="inner-sm">
        <h1 class="page-header text-center">Daily Management
            <!-- AI Assistant Button is now here, specific to this page -->
            <button id="ai-assistant-btn" class="btn btn-info btn-sm" title="Activate Voice Assistant" style="margin-left: 15px; vertical-align: middle;">
                <i class="fa fa-microphone"></i> AI Assistant
            </button>
        </h1>

        <div class="row" style="width: 100%;">
          <div class="col-md-12 outer-left-xxs">
              <div class="tab-container">
                <!-- ============================================ -->
                <!-- === START: Tab Buttons                   === -->
                <!-- ============================================ -->
                <div class="tabs">
                   <div class="tab active" data-tab-toggle="schedule">Schedule</div>
                   <div class="tab" data-tab-toggle="queue">Prepare Notifications</div>
                   <div class="tab" data-tab-toggle="history">Notification History</div>
                   <div class="tab" data-tab-toggle="direct-sms">Direct SMS</div>

                   <div class="tab" data-tab-toggle="max-times">Pilot Max. Time Limits</div>
                   
                   <div class="tab" data-tab-toggle="create-pilot">Create New Pilot</div>
                
                   <div class="tab" data-tab-toggle="manage-pilots">Manage Pilots</div>
                   
                   <div class="tab" data-tab-toggle="manage-duty">Manage Duty Schedules</div>
                   
                   <div class="tab" data-tab-toggle="manage-crafts">Manage Crafts</div>
                   <div class="tab" data-tab-toggle="manage-contracts">Manage Contracts</div>
                   
                   <div class="tab" data-tab-toggle="licences-validity">Licences Validity</div>
                   
                   <div class="tab" data-tab-toggle="check-validities">Check Validities</div>
                </div>
                <!-- ============================================ -->
                <!-- === END: Tab Buttons                     === -->
                <!-- ============================================ -->

                <!-- Tab Content Panes -->
                <div class="tab-content">
                    <!-- ============================================ -->
                    <!-- === START: Weekly Schedule Selections    === -->
                    <!-- ============================================ -->
                    <div class="tab-pane active" data-tab="schedule">
                         <h3 class="page-header text-center">Manage Weekly Schedule Assignments</h3>
                         <!-- Filter Controls Row for Schedule Tab -->
                         <div class="row filter-row" style="margin-bottom: 20px;">
                            <!-- Column 1: Week Selector -->
                            <div class="col-md-4 outer-bottom-xs">
                                <h4 class="text-center">Select Week:</h4>
                                <input type="text" class="form-control datepicker" id="sched_week">
                            </div>

                            <!-- Column 2: Contract and Aircraft Dropdowns -->
                            <div class="col-md-4 outer-bottom-xs">
                                <h4 class="text-center">Contract:</h4>
                                <select class="form-control" id="contract-select"><option value="any" selected>Any</option></select>
                                
                                <h4 class="text-center" style="margin-top: 10px;">Aircraft:</h4>
                                <select class="form-control" id="craft-select"><option value="any" selected>Any Craft</option></select>
                            </div>

                            <!-- Column 3: Contract Colors Legend (Now on the right) -->
                            <div class="col-md-4">
                                <div id="contractLegendManager">
                                    <h4 class="text-center" style="margin: 0; margin-bottom: 10px;">Contract Colors</h4>
                                    <div id="legendColorsManager" class="legend-colors-container">
                                        <!-- The contract legend will be loaded here by JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- ============================================ -->
                        <!-- === START: Weekly Schedule  Selections   === -->
                        <!-- ============================================ -->

                        <!-- ======================================= -->
                        <!-- === START: Schedule Tab Pane        === -->
                        <!-- ======================================= -->
                         <div class="col-md-12 outer-top-xs"> <!-- Added class back -->
                            <div class="schedule-container table-responsive">
                                <table class="fullsched table table-bordered table-striped no-shadow" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <!-- CORRECT Weekly header -->
                                            <th scope="rowgroup" style="width: 15%;">Aircraft</th>
                                            <th scope="col" class="day0">Monday<br><span class="date"></span></th>
                                            <th scope="col" class="day1">Tuesday<br><span class="date"></span></th>
                                            <th scope="col" class="day2">Wednesday<br><span class="date"></span></th>
                                            <th scope="col" class="day3">Thursday<br><span class="date"></span></th>
                                            <th scope="col" class="day4">Friday<br><span class="date"></span></th>
                                            <th scope="col" class="day5">Saturday<br><span class="date"></span></th>
                                            <th scope="col" class="day6">Sunday<br><span class="date"></span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                       <!-- CORRECT Colspan -->
                                       <tr><td colspan="8" class="text-center">Select a week to load the schedule...</td></tr>
                                    </tbody>
                                </table>
                            </div><!-- End schedule-container -->
                         </div>
                        <!-- ======================================= -->
                        <!-- === END: Schedule Tab Pane          === -->
                        <!-- ======================================= -->

                    </div>
                    <!-- =========================================== -->
                    <!-- === END: Schedule Tab Pane              === -->
                    <!-- =========================================== -->

                    <!-- =========================================== -->
                    <!-- === START: Prepare QUEUE Tab Pane (Corrected for Control Panel Workflow) === -->
                    <!-- =========================================== -->
                    <div class="tab-pane" data-tab="queue">
                        <h3 class="page-header text-center">Notification Control Panel</h3>
                        <p class="text-center text-muted">Use the checkboxes to select which notifications to send. Uncheck items to exclude them from this batch.</p>
                        
                        <!-- Button Row - "Delete" button is now REMOVED -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-10 col-sm-8"></div> <!-- Spacer -->
                            <div class="col-md-2 col-sm-4">
                                <label class="lbl hidden-xs">&nbsp;</label>
                                <!-- Button is renamed for clarity, ID remains the same -->
                                <button class="btn btn-success btn-block" id="sendPreparedNotiBtn" title="Send SMS to all CHECKED items below" disabled>
                                    <i class="fa fa-paper-plane"></i> Send to Selected
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-12 table-responsive">
                            <table class="table table-bordered table-striped no-shadow" id="messageQueueTable">
                                <thead>
                                    <tr>
                                        <th style="width: 4%; text-align: center;"><div class="checkbox" style="margin: 0;"><label title="Select/Deselect All"><input type="checkbox" id="queue-check-all"/> All</label></div></th>
                                        <th style="width: 15%;">Schedule Date</th>
                                        <th style="width: 15%;">Craft</th>
                                        <th>Pilot Name</th>
                                        <th style="width: 10%;">Assigned Pos</th>
                                        <th style="width: 15%;">Routing</th>
                                        <th style="width: 15%;">Recipient Phone</th>
                                        <!-- "Action" column is now "Status" -->
                                        <th style="width: 10%;" title="Status of the notification">Status</th> 
                                    </tr>
                                </thead>
                                <tbody id="messageQueueBody">
                                    <tr><td colspan="8" class="text-center text-muted">Select pilots on the 'Schedule' tab to add notifications...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- =========================================== -->
                    <!-- === END: Prepare Queue Tab Pane         === -->
                    <!-- =========================================== -->

                    <!-- ============================================= -->
                    <!-- === START: Notification HISTORY Tab Pane  === -->
                    <!-- ============================================= -->
                    <div class="tab-pane" data-tab="history">
                        <!-- History Content (Title, Filters, History Table) -->
                         <h3 class="page-header text-center">Notification Sending History</h3>
                         <div class="row" style="margin-bottom: 15px;"> <div class="col-md-2 col-sm-4"><label class="lbl hidden-xs"> </label><button class="btn btn-danger form-control" id="deleteHistoryBtn" title="Delete selected history entries PERMANENTLY" disabled>Delete History</button></div> <div class="col-md-3 col-sm-4"><label class="lbl" for="history_date_filter">Filter History by Date Sent</label><input type="text" class="form-control" id="history_date_filter" placeholder="Select Date..."/></div> <div class="col-md-4 col-sm-4"><label class="lbl" for="history_search_filter">Filter History (Pilot, Craft...)</label><input type="text" class="form-control" id="history_search_filter" placeholder="Search history..."/></div> <div class="col-md-1 col-sm-2"><label class="lbl hidden-xs"> </label><button class="btn btn-primary form-control" id="refreshHistoryBtn" title="Refresh History Log"><i class="fa fa-refresh"></i></button></div> </div>
                         <div class="col-md-12 table-responsive"> <table class="table table-bordered table-striped no-shadow" id="historyLogTable"> <thead><tr> <th style="width: 4%; text-align: center;"><div class="checkbox" style="margin: 0;"><label title="Select/Deselect All History"><input type="checkbox" class="history-checkbox" id="history-check-all"/> All</label></div></th> <th>Schedule Date</th><th>Craft</th><th>Pilot</th><th>Recipient Phone</th><th>Date Sent</th><th>Message Status</th> </tr></thead> <tbody id="historyLogBody"> <tr><td colspan="7" class="text-center">Loading notification history...</td></tr> </tbody> </table> </div>
                    </div>
                    <!-- ============================================= -->
                    <!-- === END: Notification HISTORY Tab Pane =======-->
                    <!-- ============================================= -->

                    <!-- =============================================== -->
                    <!-- = START: Direct SMS Tab Pane BULK MESSAGE FOCUS -->
                    <!-- =============================================== -->
                    <div class="tab-pane" data-tab="direct-sms">
                         <h3 class="page-header text-center">Send Direct Bulk SMS to Pilots</h3>
                         <div class="row">
                             <div class="col-md-10 col-md-offset-1"> <!-- Wider content -->

                                 <!-- Recipient Selection -->
                                 <div class="form-group">
                                     <label>Select Recipient(s):</label>
                                     <!-- Optional: Add a search filter input here -->
                                     <!-- <input type="text" id="directSmsPilotSearch" class="form-control input-sm" placeholder="Filter pilots..."> -->
                                     <div id="directSmsPilotListBulk" class="well well-sm" style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; margin-top: 5px;">
                                         <!-- Pilot checkboxes populated by JS -->
                                         <p class="text-muted">Loading pilots...</p>
                                     </div>
                                     <div class="checkbox">
                                         <label><input type="checkbox" id="directSmsSelectAllBulk"> Select/Deselect All Pilots</label>
                                     </div>
                                 </div>

                                 <!-- *** NEW: Custom Phone Number Input *** -->
                                 <div class="form-group">
                                     <label for="directSmsCustomPhones">Additional Recipient Phone(s) <small>(Optional, E.164 format)</small>:</label>
                                     <input type="text" id="directSmsCustomPhones" class="form-control" placeholder="Enter numbers separated by comma, e.g., +15551112222,+44...">
                                     <small class="text-muted">Use comma (,) to separate multiple numbers. Ensure +CountryCode format.</small>
                                 </div>
                                 <!-- *** END NEW *** -->

                                 <hr>

                                 <!-- Bulk Message Body -->
                                 <div class="form-group">
                                     <label for="directSmsBulkMessage">Message Body:</label>
                                     <textarea class="form-control" id="directSmsBulkMessage" rows="4" placeholder="Enter the message to send..."></textarea>
                                     <small class="text-muted"><span id="directSmsBulkCharCount">0</span> / 1600 characters</small>
                                 </div>

                                 <!-- Send Button -->
                                 <div class="form-group text-right">
                                     <button class="btn btn-primary" id="sendDirectBulkSmsBtn" disabled>
                                         <i class="fa fa-paper-plane"></i> Send SMS to Selected Recipients
                                     </button>
                                 </div>

                             </div>
                         </div>
                    </div>
                    <!-- =============================================== -->
                    <!-- = END: Direct SMS Tab Pane  BULK MESSAGE FOCUS  -->
                    <!-- =============================================== -->

                    <!-- ============================================== -->
                    <!-- === START: Pilot Max. Time Limits Tab Pane === -->
                    <!-- ============================================== -->
                    <div class="tab-pane" data-tab="max-times">
                        <h3 class="page-header text-center">Maximum Time Limit Settings</h3>

                        <div id="max-times-alert-container" style="display: none; margin-top: 15px;"></div> <!-- Unique ID for alerts -->

                        <!-- Form to display and edit limits -->
                        <form id="editMaxTimesForm"> <!-- Keep the same ID for JS compatibility -->
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                     <!-- Assuming limits are per company/account -->
                                    <h3 class="panel-title">Edit Limits for Company: <span id="max-times-company-id-display">Loading...</span></h3>
                                     <!-- Store the company ID fetched by JS -->
                                     <input type="hidden" id="max_times_company_id_hidden" name="company_id" 
                                        value="<?php echo isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : ''; ?>">
                                </div>
                                <div class="panel-body">
                                    <div id="max-times-loading-indicator" class="text-center" style="display: none; padding: 20px;">
                                        <i class="fa fa-spinner fa-spin fa-2x"></i> Loading current limits...
                                    </div>

                                    <div id="max-times-limits-fields" style="display: none;">
                                        <!-- Row 1 --> 
                                        <div class="row">
                                            <div class="col-md-6 col-sm-12">
                                                <div class="form-group">
                                                    <!-- FIX: Changed class to 'text-danger' -->
                                                    <label for="max_in_day">Max Flying Hours / Day <small class="text-danger">(Current: <span class="current-limit" data-limit-name="max_in_day">...</span>)</small></label>
                                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_in_day" data-limit-name="max_in_day" placeholder="Enter new value..">
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-sm-12">
                                                <div class="form-group">
                                                    <!-- FIX: Changed class to 'text-danger' -->
                                                    <label for="max_duty_in_day">Max Duty Hours / Day <small class="text-danger">(Current: <span class="current-limit" data-limit-name="max_duty_in_day">...</span>)</small></label>
                                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_duty_in_day" data-limit-name="max_duty_in_day" placeholder="Enter new value..">
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        <!-- Row 2 -->
                                        <div class="row">
                                            <div class="col-md-6 col-sm-12">
                                                <div class="form-group">
                                                    <!-- FIX: Changed class to 'text-danger' -->
                                                    <label for="max_last_7">Max Flying Hours / 7 Days <small class="text-danger">(Current: <span class="current-limit" data-limit-name="max_last_7">...</span>)</small></label>
                                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_last_7" data-limit-name="max_last_7" placeholder="Enter new value..">
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-sm-12">
                                                <div class="form-group">
                                                    <!-- FIX: Changed class to 'text-danger' -->
                                                    <label for="max_duty_7">Max Duty Hours / 7 Days <small class="text-danger">(Current: <span class="current-limit" data-limit-name="max_duty_7">...</span>)</small></label>
                                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_duty_7" data-limit-name="max_duty_7" placeholder="Enter new value..">
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        <!-- Row 3 -->
                                        <div class="row">
                                            <div class="col-md-6 col-sm-12">
                                                <div class="form-group">
                                                    <!-- FIX: Changed class to 'text-danger' -->
                                                    <label for="max_last_28">Max Flying Hours / 28 Days <small class="text-danger">(Current: <span class="current-limit" data-limit-name="max_last_28">...</span>)</small></label>
                                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_last_28" data-limit-name="max_last_28" placeholder="Enter new value..">
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-sm-12">
                                                <div class="form-group">
                                                    <!-- FIX: Changed class to 'text-danger' -->
                                                    <label for="max_duty_28">Max Duty Hours / 28 Days <small class="text-danger">(Current: <span class="current-limit" data-limit-name="max_duty_28">...</span>)</small></label>
                                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_duty_28" data-limit-name="max_duty_28" placeholder="Enter new value..">
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        <!-- Row 4 -->
                                        <div class="row">
                                            <div class="col-md-6 col-sm-12">
                                                <div class="form-group">
                                                    <!-- FIX: Changed class to 'text-danger' -->
                                                    <label for="max_last_365">Max Flying Hours / 365 Days <small class="text-danger">(Current: <span class="current-limit" data-limit-name="max_last_365">...</span>)</small></label>
                                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_last_365" data-limit-name="max_last_365" placeholder="Enter new value..">
                                                </div>
                                            </div>
                                            <div class="col-md-6 col-sm-12">
                                                <div class="form-group">
                                                    <!-- FIX: Changed class to 'text-danger' -->
                                                    <label for="max_duty_365">Max Duty Hours / 365 Days <small class="text-danger">(Current: <span class="current-limit" data-limit-name="max_duty_365">...</span>)</small></label>
                                                    <input type="number" step="0.1" min="0" class="form-control limit-input" id="max_duty_365" data-limit-name="max_duty_365" placeholder="Enter new value..">
                                                </div>
                                            </div>
                                        </div>
                                    </div><!-- /#max-times-limits-fields -->
                                </div><!-- /panel-body -->
                                <div class="panel-footer text-right">
                                    <button type="submit" class="btn btn-success" id="saveMaxTimesBtn">
                                        <i class="fa fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </div><!-- /panel -->
                        </form>
                    </div>
                    <!-- =============================================== -->
                    <!-- === END: Pilot Max. Time Limits Tab Pane    === -->
                    <!-- =============================================== -->

                    <!-- =============================================== -->
                    <!-- === START: Create New Pilot Tab Pane        === -->
                    <!-- =============================================== -->
                    
                    <div class="tab-pane" data-tab="create-pilot">
                        <?php
                        // Fetch available roles from database
                        $roles_query = "SELECT id, role_name FROM users_roles ORDER BY id ASC";
                        $roles_result = $mysqli->query($roles_query);
                        $available_roles = [];

                        if ($roles_result) {
                            while ($row = $roles_result->fetch_assoc()) {
                                $available_roles[] = $row;
                            }
                            $roles_result->free();
                        } else {
                            error_log("Failed to fetch user roles: " . $mysqli->error);
                            $available_roles = []; // Ensure empty array on error
                        }
                        ?>
                        
                        <div class="row">
                            <div class="col-md-8 col-md-offset-2">
                                <h3 class="page-header text-center">Create New Pilot</h3>
                                
                                <!-- Alert container for form feedback -->
                                <div id="create-pilot-alert-container" class="alert-container"></div>
                                
                                <form id="createNewPilotForm" class="form-vertical">
                                    <!-- ======================================================= -->
                                    <!-- === START: CORRECTED BASIC INFORMATION FIELDSET       === -->
                                    <!-- ======================================================= -->
                                    <fieldset>
                                        <legend>Basic Information</legend>
                                        
                                        <!-- First Name / Last Name Row -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="newPilot_firstname" class="required">First Name</label>
                                                    <input type="text" class="form-control" id="newPilot_firstname" name="firstname" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="newPilot_lastname" class="required">Last Name</label>
                                                    <input type="text" class="form-control" id="newPilot_lastname" name="lastname" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- THE FIX: Nationality and Hire Date are now in their own 2-column row -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="newPilot_user_nationality">Nationality</label>
                                                    <input type="text" class="form-control" id="newPilot_user_nationality" name="user_nationality" placeholder="e.g., Canadian">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="newPilot_hire_date">Hire Date</label>
                                                    <input type="text" class="form-control datepicker" id="newPilot_hire_date" name="hire_date" placeholder="DD-MM-YYYY">
                                                </div>
                                            </div>
                                        </div>

                                    </fieldset>
                                    <!-- ======================================================= -->
                                    <!-- === END: CORRECTED BASIC INFORMATION FIELDSET         === -->
                                    <!-- ======================================================= -->

                                    <!-- Contact Information Section -->
                                    <fieldset>
                                        <legend>Contact Information</legend>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="newPilot_email" class="required">Email Address</label>
                                                    <input type="email" class="form-control" id="newPilot_email" 
                                                        name="email" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="newPilot_phone">Phone Number</label>
                                                    <input type="tel" class="form-control" id="newPilot_phone" 
                                                        name="phone" placeholder="+1-555-123-4567">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="newPilot_phonetwo">Secondary Phone</label>
                                            <input type="tel" class="form-control" id="newPilot_phonetwo" 
                                                name="phonetwo">
                                        </div>
                                    </fieldset>

                                    <!-- Account Information Section -->
                                    <fieldset>
                                        <legend>Account Information</legend>
                                        
                                        <div class="form-group">
                                            <label for="newPilot_username" class="required">Username</label>
                                            <input type="text" class="form-control" id="newPilot_username" 
                                                name="username" required minlength="4" maxlength="20" 
                                                pattern="[a-zA-Z0-9]+" title="Letters and numbers only, no spaces">
                                            <small class="form-text text-muted">4-20 characters, letters and numbers only</small>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="newPilot_password" class="required">Password</label>
                                                    <input type="password" class="form-control" id="newPilot_password" 
                                                        name="password" required minlength="8">
                                                    <small class="form-text text-muted">Minimum 8 characters</small>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="newPilot_confpassword" class="required">Confirm Password</label>
                                                    <input type="password" class="form-control" id="newPilot_confpassword" 
                                                        name="confpassword" required>
                                                </div>
                                            </div>
                                        </div>
                                    </fieldset>

                                    <!-- Pilot Qualifications Section -->
                                    <fieldset>
                                        <legend>Pilot Qualifications</legend>
                                        <!-- THE FIX: The row now only contains the two license fields -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="newPilot_nal_license">National License</label>
                                                    <input type="text" class="form-control" id="newPilot_nal_license" name="nal_license">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="newPilot_for_license">Foreign License</label>
                                                    <input type="text" class="form-control" id="newPilot_for_license" name="for_license">
                                                </div>
                                            </div>
                                        </div>
                                    </fieldset>

                                    <!-- Assignments Section -->
                                    <fieldset>
                                        <legend>Assignments</legend>
                                        
                                        <!-- Craft Type Assignment -->
                                        <div class="panel panel-default">
                                            <div class="panel-heading clearfix">
                                                <h4 class="panel-title pull-left">Assign Craft Types</h4>
                                                <button type="button" class="btn btn-success btn-xs pull-right" 
                                                        data-toggle="modal" data-target="#createCraftTypeModalManager">
                                                    <i class="fa fa-plus"></i> New Craft Type
                                                </button>
                                            </div>
                                            <div class="panel-body">
                                                <table class="table table-condensed" id="manager_crafts_checkbox_list">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 35%;">Craft Type</th>
                                                            <th style="width: 55%;">Position (PIC/SIC)</th>
                                                            <th style="width: 10%;" class="text-center">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="3" class="text-center text-muted">
                                                                <i class="fa fa-spinner fa-spin"></i> Loading craft types...
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <!-- Contract Assignment -->
                                        <div class="panel panel-default">
                                            <div class="panel-heading clearfix">
                                                <h4 class="panel-title pull-left">Assign Contracts</h4>
                                                <button type="button" class="btn btn-success btn-xs pull-right" 
                                                        data-toggle="modal" data-target="#createContractModal">
                                                    <i class="fa fa-plus"></i> New Contract
                                                </button>
                                            </div>
                                            <div class="panel-body" id="manager_contracts_checkbox_list">
                                                <p class="text-muted">
                                                    <i class="fa fa-spinner fa-spin"></i> Loading available contracts...
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <!-- Hidden assignments container -->
                                        <div id="hidden-assignments-container"></div>
                                    </fieldset>

                                    <!-- Role Assignment Section -->
                                    <fieldset>
                                        <legend>System Roles</legend>
                                        
                                        <div class="form-group">
                                            <label class="required">Assign Roles</label>
                                            <div class="panel panel-default">
                                                <div class="panel-body">
                                                    <?php if (!empty($available_roles)): ?>
                                                        <div class="row">
                                                            <?php
                                                            // Split roles into 2 columns
                                                            $columns = array_chunk($available_roles, ceil(count($available_roles) / 2));
                                                            
                                                            foreach ($columns as $column): ?>
                                                                <div class="col-md-6">
                                                                    <?php foreach ($column as $role): ?>
                                                                        <div class="checkbox">
                                                                            <label>
                                                                                <input type="checkbox" name="role_ids[]" 
                                                                                    value="<?= htmlspecialchars($role['id']) ?>"
                                                                                    <?= strtolower($role['role_name']) === 'pilot' ? 'checked' : '' ?>>
                                                                                <?= htmlspecialchars(ucwords($role['role_name'])) ?>
                                                                            </label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <p class="text-danger">No roles available in the system</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </fieldset>

                                    <!-- Hidden Fields -->
                                    <input type="hidden" name="company_id" value="<?= htmlspecialchars($_SESSION['company_id']) ?>">
                                    <input type="hidden" name="is_active" value="1">
                                    <input type="hidden" name="access_level" value="1">
                                    <input type="hidden" name="admin" value="1">

                                    <!-- Form Submission -->
                                    <div class="form-group text-center">
                                        <button type="submit" class="btn btn-primary btn-lg" id="submitNewPilotBtn">
                                            <i class="fa fa-user-plus"></i> Create Pilot Account
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Create Craft Modal (placeholder) -->
                        <div class="modal fade" id="createCraftModal" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <!-- Modal content will be loaded dynamically -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Create Contract Modal (placeholder) -->
                        <div class="modal fade" id="createContractModal" tabindex="-1" role="dialog">
                            <div class="modal-dialog" role="document">
                                <div class="modal-content">
                                    <!-- Modal content will be loaded dynamically -->
                                </div>
                            </div>
                        </div>
                    </div>
                   
                    <!-- =============================================== -->
                    <!-- === END: Create New Pilot Tab Pane          === -->
                    <!-- =============================================== -->

                    <!-- =============================================== -->
                    <!-- === START: Manage Pilots Tab Pane           === -->
                    <!-- =============================================== -->
                    
                    <div class="tab-pane" data-tab="manage-pilots">
                        <h3 class="page-header text-center">Manage Pilot Accounts</h3>
                        <div id="manage-pilots-alert-container" style="display: none; margin-bottom: 15px;"></div>
                        
                        <!-- Optional: Filters for pilot list -->
                        <div class="row" style="margin-bottom:15px;">
                            <div class="col-md-4">
                                <input type="text" id="pilot-search-filter" class="form-control" placeholder="Search by name, username, email...">
                            </div>
                            <div class="col-md-3">
                                <select id="pilot-status-filter" class="form-control">
                                    <option value="all">All Statuses</option>
                                    <option value="1" selected>Active Pilots</option>
                                    <option value="0">Inactive Pilots</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-default" id="applyPilotFiltersBtn"><i class="fa fa-filter"></i> Apply</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="managePilotsTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th> <!-- Assuming you have a roles table or a way to display role name -->
                                        <th>Status</th>
                                        <th style="width: 15%; text-align:center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="managePilotsTableBody">
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <i class="fa fa-spinner fa-spin"></i> Loading pilots...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Optional: Pagination controls here if needed -->
                    </div>
                    <!-- End Manage Pilots Tab Pane -->
                    
                    <!-- ================================================ -->
                    <!-- === END: Manage Pilots Tab Pane              === -->
                    <!-- ================================================ -->

                    <!-- ===================================================================== -->
                    <!-- === START: NEW "MANAGE DUTY SCHEDULES" TAB PANE (ACCORDION VERSION) === -->
                    <!-- ===================================================================== -->
                    <div class="tab-pane" data-tab="manage-duty">
                        <h3 class="page-header text-center">Manage Pilot Duty Schedules</h3>

                        <!-- ========================================================= -->
                        <!-- === START: NEW EXPORT TO EXCEL SECTION                === -->
                        <!-- ========================================================= -->
                        <div class="panel panel-default">
                            <div class="panel-body">
                                <div class="row">
                                    <!-- Use columns to push the controls to the right -->
                                    <div class="col-md-4">
                                        <!-- This column is an empty spacer -->
                                    </div>
                                    <div class="col-md-3">
                                        <label for="duty-export-start">Export Start Date:</label>
                                        <input type="text" id="duty-export-start" class="form-control datepicker">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="duty-export-end">Export End Date:</label>
                                        <input type="text" id="duty-export-end" class="form-control datepicker">
                                    </div>
                                    <div class="col-md-2"> <!-- Reduced padding to bring button closer -->
                                        <label>&nbsp;</label> <!-- Empty label for alignment -->
                                        <button id="duty-export-btn" class="btn btn-success btn-block"> <!-- Custom padding -->
                                            <i class="fa fa-file-excel-o"></i>Export to Excel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- ========================================================= -->
                        <!-- === END: NEW EXPORT TO EXCEL SECTION                  === -->
                        <!-- ========================================================= -->

                        <div class="row">
                            <div class="col-md-10 col-md-offset-1"> <!-- A wider container for the list -->

                                <!-- 1. Pilot Filter (The dropdown now acts as a filter/jump-to tool) -->
                                <div class="form-group">
                                    <label for="duty-pilot-selector">Filter / Jump to Pilot:</label>
                                    <select id="duty-pilot-selector" class="form-controlr">
                                        <option value="">-- Show All Pilots --</option>
                                        <!-- Pilot list will be loaded here by JavaScript -->
                                    </select>
                                </div>
                                <hr>

                                <!-- 2. Master Pilot List Container (The Accordion) -->
                                <!-- JavaScript will populate this div with a list of all pilots -->
                                <div id="duty-schedule-accordion-container">
                                    
                                    <!-- Loading message will be shown initially -->
                                    <div class="text-center text-muted">
                                        <p><i class="fa fa-spinner fa-spin fa-2x"></i></p>
                                        <p>Loading pilot list...</p>
                                    </div>
                                </div> <!-- /#duty-schedule-accordion-container -->
                            </div>
                        </div>
                    </div>
                    <!-- ===================================================================== -->
                    <!-- === END: NEW "MANAGE DUTY SCHEDULES" TAB PANE                     === -->
                    <!-- ===================================================================== -->

                    <!-- ================================================ -->
                    <!-- === START: NEW "MANAGE CRAFTS" TAB PANE      === -->
                    <!-- ================================================ -->
                    <div class="tab-pane" data-tab="manage-crafts">
                        <h3 class="page-header text-center">Manage Company Fleet</h3>
                        <div id="manager-crafts-alert-container"></div>
                        
                        <div class="row">
                            <div class="col-md-10 col-md-offset-1">
                                <!-- The craft table will be loaded here by JavaScript -->
                                <div id="manager-crafts-table-container">
                                    <p class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading crafts...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ================================================ -->
                    <!-- === END: NEW "MANAGE CRAFTS" TAB PANE        === -->
                    <!-- ================================================ -->

                    <!-- ================================================ -->
                    <!-- === START: NEW "MANAGE CONTRACTS" TAB PANE   === -->
                    <!-- ================================================ -->
                    <div class="tab-pane" data-tab="manage-contracts">
                        <h3 class="page-header text-center">Manage Customers & Contracts</h3>
                        <div id="manager-contracts-alert-container"></div>
                        
                        <!-- Buttons to open the modals -->
                        <div class="row">
                            <div class="col-sm-6 outer-xs">
                                <button class="btn btn-primary form-control" data-toggle="modal" data-target="#managerAddNewCustomerModal">Add New Customer</button>
                            </div>
                            <div class="col-sm-6 outer-xs">
                                <button class="btn btn-primary form-control" data-toggle="modal" data-target="#managerAddNewContractModal">Add New Contract</button>
                            </div>
                        </div>

                        <!-- Display Area -->
                        <div class="row outer-top-md">
                            <div class="col-md-6">
                                <h4>Existing Customers</h4>
                                <!-- Customer list will be loaded here by JavaScript -->
                                <div id="manager-customerList">
                                    <p class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading customers...</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h4>Existing Contracts</h4>
                                <!-- Contract list will be loaded here by JavaScript -->
                                <div id="manager-contractsList">
                                    <p class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading contracts...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ================================================ -->
                    <!-- === END: NEW "MANAGE CONTRACTS" TAB PANE     === -->
                    <!-- ================================================ -->

                    <!-- ====================================================== -->
                    <!-- === START: "LICENCES VALIDITIES" TAB FUNCTIONALITY === -->
                    <!-- ====================================================== -->
                    <div class="tab-pane" data-tab="licences-validity">
                        <h3 class="page-header text-center">Manage Licences & Validity</h3>
                        <p class="text-center text-muted" style="margin-bottom: 30px;">
                            Add or remove the standard certification fields that all pilots in your company must complete.
                        </p>

                        <div id="licence-fields-alert-container"></div>
                        
                        <div class="row">
                            <div class="col-md-8 col-md-offset-2">
                                <!-- Table of existing fields will be loaded here by JavaScript -->
                                <div id="licence-fields-table-container">
                                    <p class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading fields...</p>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6 col-md-offset-3">
                                <div class="panel panel-info">
                                    <div class="panel-heading">
                                        <h3 class="panel-title">Add New Field</h3>
                                    </div>
                                    <div class="panel-body">
                                        <form id="addNewLicenceFieldForm">
                                            <div class="form-group">
                                                <label for="newFieldLabel">Field Label (e.g., "Passport", "CRM Training")</label>
                                                <input type="text" class="form-control" id="newFieldLabel" required placeholder="The name pilots will see">
                                            </div>
                                            <div class="form-group">
                                                <label for="newFieldKey">Field Key (e.g., "passport", "crm_training")</label>
                                                <input type="text" class="form-control" id="newFieldKey" required placeholder="Internal name, no spaces, use underscores">
                                                <small class="help-block">This is the unique internal name. It cannot be changed later. Use lowercase letters and underscores only (e.g., `my_new_field`).</small>
                                            </div>
                                            <button type="submit" class="btn btn-success btn-block" id="addNewFieldBtn">
                                                <i class="fa fa-plus"></i> Add New Field
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- ====================================================== -->
                    <!-- === END: "LICENCES VALIDITIES" TAB FUNCTIONALITY === -->
                    <!-- ====================================================== -->

                    <!-- ================================================ -->
                    <!-- === START: "CHECK VALIDITIES" TAB FUNCTIONALITY = -->
                    <!-- ================================================ -->
                    <div class="tab-pane" data-tab="check-validities">
                        
                        <!-- Page Header (Title) -->
                        <div class="page-header" style="margin-top: 0; padding-top: 0;">
                            <h3 class="text-center">Upcoming Validity Expirations</h3>
                        </div>

                        <!-- Controls Row (Search Filter and Action Buttons) -->
                        <div class="row" style="margin-bottom: 20px; padding: 0 15px;">
                            
                            <!-- Pilot Search Input Box -->
                            <div class="col-md-4">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label for="validityPilotSearch" style="font-weight: normal;">Filter by Pilot Name:</label>
                                    <input type="text" id="validityPilotSearch" class="form-control" placeholder="Type a name to filter...">
                                </div>
                            </div>
                            
                            <!-- Spacer Column -->
                            <div class="col-md-5"></div>

                            <!-- Action Buttons -->
                            <div class="col-md-3 text-right">
                                <label> </label> <!-- Empty label for vertical alignment with the search input -->
                                <div class="btn-group" role="group" style="display: block;">
                                    <button id="printValiditiesBtn" class="btn btn-primary">
                                        <i class="fa fa-print"></i> Print List
                                    </button>
                                    <button id="downloadValiditiesPdfBtn" class="btn btn-success">
                                        <i class="fa fa-download"></i> Download PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- A simple horizontal rule to separate controls from the report -->
                        <hr style="margin-top: 0;">

                        <!-- This container will be filled by JavaScript with the report -->
                        <div id="validities-report-container">
                            <p class="text-center" style="padding: 20px;">
                                <i class="fa fa-spinner fa-spin fa-2x"></i>
                                <br>Checking all pilot validities...
                            </p>
                        </div>

                    </div>
                    <!-- ========================================================================= -->
                    <!-- === END: "CHECK VALIDITIES" TAB PANE                                  === -->
                    <!-- ========================================================================= -->
                    
                </div> <!-- End tab-content -->
                </div> <!-- End tab-container -->
            </div> <!-- End col-md-12 -->
        </div> <!-- End row -->
    </div> <!-- End inner-sm -->
</div> <!-- End light-bg -->

<!-- ===================================================================== -->
<!-- === START: MODALS FOR CREATING NEW CRAFTS TYPES                   === -->
<!-- ===================================================================== -->
<div class="modal fade" id="createCraftTypeModalManager" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title">Create New Craft Type</h4>
            </div>
            <div class="modal-body">
                <div id="create-craft-type-alert-container"></div>
                <p class="text-muted">This will create a new craft type available for assignment. A placeholder registration will be created, which can be updated later in the "Manage Crafts" tab.</p>
                <form id="createCraftTypeFormManager">
                    <div class="form-group">
                        <label for="new_craft_type_name_manager">Craft Type Name *</label>
                        <input type="text" class="form-control" id="new_craft_type_name_manager" placeholder="e.g., AW139" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveNewCraftTypeManagerBtn">Save Craft Type</button>
            </div>
        </div>
    </div>
</div>
<!-- ===================================================================== -->
<!-- === END: MODALS FOR CREATING NEW CRAFTS TYPES                     === -->
<!-- ===================================================================== -->

<!-- ===================================================================== -->
<!-- === START: MODALS FOR CREATING NEW CONTRACTS                      === -->
<!-- ===================================================================== -->
<div class="modal fade" id="createContractModal" tabindex="-1" role="dialog" aria-labelledby="createContractModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="createContractModalLabel">Create New Contract</h4>
            </div>
            <div class="modal-body">
                <div id="create-contract-alert-container"></div>
                <form id="createContractForm">
                    <div class="form-group">
                        <label for="new_contract_name">Contract Name / Code *</label>
                        <input type="text" class="form-control" id="new_contract_name" placeholder="e.g., OFF-2024-01, Shell Ops" required>
                    </div>
                    <div class="form-group">
                        <label for="new_customer_name">Customer Name *</label>
                        <input type="text" class="form-control" id="new_customer_name" placeholder="e.g., Shell Offshore Inc." required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveNewContractBtn">Save Contract</button>
            </div>
        </div>
    </div>
</div>
<!-- ===================================================================== -->
<!-- === END: MODALS FOR CREATING NEW CONTRACTS                        === -->
<!-- ===================================================================== -->

<!-- ===================================================================== -->
<!-- === START: "EDIT PILOT" MODAL                                     === -->
<!-- ===================================================================== -->
<div class="modal fade" id="editPilotModal" tabindex="-1" role="dialog" aria-labelledby="editPilotModalLabel">
    <div class="modal-dialog modal-lg" role="document"> <!-- modal-lg for more space -->
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                <h4 class="modal-title" id="editPilotModalLabel">Edit Pilot Information</h4>
            </div>
            <div class="modal-body">
                
                <!-- This container will show loading spinners or error messages -->
                <div id="edit-pilot-modal-feedback" class="text-center" style="padding: 20px;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p>Loading pilot data...</p>
                </div>
                
                <!-- Inside the #editPilotModal div -->
<form id="editPilotForm" class="form-vertical" style="display: none;">

    <!-- Hidden input to store the ID of the pilot being edited -->
    <input type="hidden" id="editPilot_id" name="pilot_id">

    <!-- Basic Information -->
    <!-- ======================================================= -->
    <!-- === START: CORRECTED BASIC INFORMATION FIELDSET       === -->
    <!-- ======================================================= -->
    <fieldset>
        <legend>Basic Information</legend>
        
        <!-- First Name / Last Name Row -->
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="editPilot_firstname" class="required">First Name</label>
                    <input type="text" class="form-control" id="editPilot_firstname" name="firstname" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="editPilot_lastname" class="required">Last Name</label>
                    <input type="text" class="form-control" id="editPilot_lastname" name="lastname" required>
                </div>
            </div>
        </div>
        
        <!-- THE FIX: Nationality and Hire Date are now in their own 2-column row -->
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="editPilot_user_nationality">Nationality</label>
                    <input type="text" class="form-control" id="editPilot_user_nationality" name="user_nationality" placeholder="e.g., Canadian">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="editPilot_hire_date">Hire Date</label>
                    <input type="text" class="form-control datepicker" id="editPilot_hire_date" name="hire_date" placeholder="DD-MM-YYYY">
                </div>
            </div>
        </div>

    </fieldset>
    <!-- ======================================================= -->
    <!-- === END: CORRECTED BASIC INFORMATION FIELDSET         === -->
    <!-- ======================================================= -->

    <!-- Contact & Account Info -->
    <fieldset>
        <legend>Contact & Account</legend>
        <div class="row">
            <div class="col-md-6"><div class="form-group">
                <label for="editPilot_email" class="required">Email Address</label>
                <input type="email" class="form-control" id="editPilot_email" name="email" required>
            </div></div>
            <div class="col-md-6"><div class="form-group">
                <label for="editPilot_username">Username</label>
                <input type="text" class="form-control" id="editPilot_username" name="username" readonly>
                <small class="text-muted">Username cannot be changed.</small>
            </div></div>
        </div>
        <div class="row">
            <div class="col-md-6"><div class="form-group">
                <label for="editPilot_phone">Phone Number</label>
                <input type="tel" class="form-control" id="editPilot_phone" name="phone">
            </div></div>
            <div class="col-md-6"><div class="form-group">
                <label for="editPilot_phonetwo">Secondary Phone</label>
                <input type="tel" class="form-control" id="editPilot_phonetwo" name="phonetwo">
            </div></div>
        </div>
    </fieldset>

    <!-- NEW: Password Reset Section -->
    <fieldset>
        <legend>Reset Password</legend>
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> Only fill out these fields if you want to reset the pilot's password.
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="editPilot_new_password">New Password</label>
                    <input type="password" class="form-control" id="editPilot_new_password" name="new_password" minlength="8" autocomplete="new-password">
                    <small class="text-muted">Minimum 8 characters. Leave blank to keep current password.</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="editPilot_confirm_password">Confirm New Password</label>
                    <input type="password" class="form-control" id="editPilot_confirm_password" name="confirm_new_password">
                </div>
            </div>
        </div>
    </fieldset>
    
    <!-- Pilot Qualifications & Hire Date -->
    <fieldset>
        <legend>Qualifications</legend>
        <div class="row">
            <div class="col-md-6"><div class="form-group">
                <label for="editPilot_nal_license">National License</label>
                <input type="text" class="form-control" id="editPilot_nal_license" name="nal_license">
            </div></div>
            <div class="col-md-6"><div class="form-group">
                <label for="editPilot_for_license">Foreign License</label>
                <input type="text" class="form-control" id="editPilot_for_license" name="for_license">
            </div></div>
        </div>
    </fieldset>

    <!-- Assignments Section -->
    <fieldset>
        <legend>Assignments</legend>
        <div class="panel panel-default">
            <div class="panel-heading">Craft Endorsements</div>
            <div class="panel-body">
                <table class="table table-condensed" id="edit_manager_crafts_checkbox_list">
                    <thead><tr><th>Craft Type</th><th>Position (PIC/SIC)</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="panel panel-default">
            <div class="panel-heading">Assigned Contracts</div>
            <div class="panel-body" id="edit_manager_contracts_checkbox_list"></div>
        </div>
    </fieldset>

    <!-- Role Assignment Section -->
    <fieldset>
        <legend>System Roles</legend>
        <div class="row"><div id="edit_manager_roles_checkbox_list"></div></div>
    </fieldset>

</form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePilotChangesBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>
<!-- ===================================================================== -->
<!-- === END: "EDIT PILOT" MODAL                                       === -->
<!-- ===================================================================== -->

<!-- ===================================================================== -->
<!-- === START: MODALS FOR CONTRACT MANAGEMENT (MANAGER)               === -->
<!-- ===================================================================== -->

<!-- ===================================================================== -->
<!-- === START: "ADD NEW CUSTOMER" MODAL   (Oct 01 2025)               === -->
<!-- ===================================================================== -->
<div class="modal fade" id="managerAddNewCustomerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Add a New Customer</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="managerNewCustomerName">Customer Name:</label>
                    <input type="text" class="form-control" id="managerNewCustomerName" placeholder="Enter customer name" required/>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="managerSubmitNewCustomerBtn">Add Customer</button>
            </div>
        </div>
    </div>
</div>
<!-- ===================================================================== -->
<!-- === END: "ADD NEW CONTRACT" FOR CONTRACT MANAGER TAB              === -->
<!-- ===================================================================== -->
 
<!-- ===================================================================== -->
<!-- === START: "ADD NEW CONTRACT" MODAL   (Oct 01 2025)               === -->
<!-- ===================================================================== -->
<div class="modal fade" id="managerAddNewContractModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Add a New Contract</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Contract Name</label>
                    <input class="form-control" type="text" id="managerNewContractName" placeholder="Enter name of contract"/>
                </div>
                <div class="form-group">
                    <label>Select Customer</label>
                    <select class="form-control" id="managerNewContractCustomerSelect"></select>
                </div>
                <div class="form-group">
                    <label>Assign Crafts to this Contract</label>
                    <select size='6' multiple class="form-control" id="managerNewContractCraftSelect"></select>
                </div>
                <!-- <div class="form-group">
                    <label>Assign Pilots to this Contract</label>
                    <select size='6' multiple class="form-control" id="managerNewContractPilotSelect"></select>
                </div> -->
                <div class="form-group">
                    <label>Assign Pilots to this Contract</label>
                    <div id="managerNewContractPilotsCheckboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background: white;">
                        <div class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading pilots...</div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Contract Color</label>
                    <input type="text" class="form-control" id="managerNewContractColor" value="#3f51b5">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button class="btn btn-success" id="managerSubmitNewContractBtn">Submit Contract</button>
            </div>
        </div>
    </div>
</div>
<!-- ===================================================================== -->
<!-- === END: "ADD NEW CONTRACT" FOR CONTRACT MANAGER TAB              === -->
<!-- ===================================================================== -->

<!-- ===================================================================== -->
<!-- === START: "EDIT CONTRACT" MODAL       (Oct 01 2025)              === -->
<!-- ===================================================================== -->
<div class="modal fade" id="editContractModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Edit Contract</h4>
            </div>
            <div class="modal-body">
                <form id="editContractForm">
                    <input type="hidden" id="editContractId">

                    <div class="form-group">
                        <label for="editContractName">Contract Name</label>
                        <input type="text" class="form-control" id="editContractName">
                    </div>

                    <!-- --- Customer is now an editable dropdown --- -->
                    <div class="form-group">
                        <label for="editContractCustomerSelect">Customer</label>
                        <select class="form-control" id="editContractCustomerSelect"></select>
                    </div>
                    
                    <!-- --- THIS IS THE NEW PART --- -->
                    <div class="form-group">
                        <label>Assign Crafts to this Contract</label>
                        <select size='6' multiple class="form-control" id="editContractCraftSelect"></select>
                    </div>
                    <div class="form-group">
                        <label>Assign Pilots to this Contract</label>
                        <div id="editContractPilotsCheckboxes" style="height: 150px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;"></div>
                    </div>
                    <!-- --- END OF NEW PART --- -->

                    <!-- --- Color is an editable input --- -->
                    <div class="form-group">
                        <label for="editContractColor">Contract Color</label>
                        <!-- This input will be turned into a color picker -->
                        <input type="text" class="form-control" id="editContractColor" />
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveEditedContract">Save Changes</button>
            </div>
        </div>
    </div>
</div>
<!-- ===================================================================== -->
<!-- === END: "EDIT NEW CONTRACT" MODAL FOR CONTRACT MANAGER TAB       === -->
<!-- ===================================================================== -->

<style>
    #managerNewContractPilotsCheckboxes {
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        max-height: 200px;
        overflow-y: auto;
    }

    #managerNewContractPilotsCheckboxes .checkbox {
        margin: 5px 0;
        padding: 5px;
        border-bottom: 1px solid #f0f0f0;
    }

    #managerNewContractPilotsCheckboxes .checkbox:last-child {
        border-bottom: none;
    }

    #managerNewContractPilotsCheckboxes .checkbox label {
        font-weight: normal;
        margin: 0;
    }

    #managerNewContractPilotsCheckboxes input[type="checkbox"] {
        margin-right: 8px;
    }
</style>

<?php
// =========================================================================
// === DEFINE PAGE-SPECIFIC STYLESHEETS & SCRIPTS (DAILY MANAGER)        ===
// =========================================================================
$page_stylesheets = [
    // 'https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/themes/mint.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/themes/base/jquery-ui.min.css'
];

$page_scripts = [
    // Third-party libraries (non-core)
    'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js',

    // === THE FIX IS HERE: Load the Flatpickr weekSelect plugin ===
    'https://npmcdn.com/flatpickr/dist/plugins/weekSelect/weekSelect.js',

    'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/jquery-ui.min.js',
    // 'https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.js',
    'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',

    // Your custom feature scripts for this page

    'daily_manager-utils.js',
    'daily_manager-maxtimes.js',
    'daily_manager-pilots.js',
    'daily_manager-crafts.js',
    'daily_manager-contracts.js',
    'daily_manager-validity.js',
    'daily_manager-reports.js',
    'notificationManagerFunctions.js', 
    'scheduleManagerFunctions.js',
    'scheduleManagerDutyFunctions.js',
    'daily_manager_ai_assistant_functions.js'
];

include_once "footer.php"; 
?>