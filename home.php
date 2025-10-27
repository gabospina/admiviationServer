<?php
// home.php - CORRECTED AND STANDARDIZED VERSION

// session_start() MUST be the very first command.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 1. DEFINE A CONSTANT FOR THIS PAGE ---
// This is used by header.php to highlight the active sidebar item.
$page = "home";

// --- 2. INCLUDE THE HEADER ---
// The header file will handle the security check (if user is logged in) and database connection.
// It also defines BASE_PATH.
include_once "header.php";

// --- 3. GATHER SESSION DATA (AFTER header.php) ---
// We can be sure the user is logged in at this point.
$firstName = $_SESSION['firstName'] ?? '';
$lastName = $_SESSION['lastName'] ?? '';
$userId = $_SESSION['HeliUser'] ?? 0;

$lastHeliSelected = $_COOKIE["lastHeliSelected"] ?? $_SESSION["lastHeliSelected"] ?? "";
if ($lastHeliSelected) {
    $_SESSION["lastHeliSelected"] = $lastHeliSelected;
}

// --- 4. OUTPUT PAGE-SPECIFIC JAVASCRIPT VARIABLES ---
// This script block is now correctly placed AFTER the header and before the main content.
echo '<script>
  var lastHeliSelected = "' . htmlspecialchars($lastHeliSelected, ENT_QUOTES, 'UTF-8') . '";
  var pilotData = {
    user_id: ' . $userId . ',
    firstName: "' . htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') . '",
    lastName: "' . htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8') . '"
  };
</script>';

?>

<!-- In home.php -->
<div class="container-fluid"> 
    <div class="row outer-xs ">
        <div id="notifications"></div>
        <div id="onOffHeader" class="page-header outer-top-sm">
            <div class="on-duty-title-container">
                <span class="on-duty-text">On Duty: <small id="onOffHeaderText">Checking schedule...</small></span>
                <button class="btn btn-primary" data-toggle="modal" data-target="#futureScheduleModal">
                    View future dates
                </button>
            </div>
            <span class="divider"></span>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="futureScheduleModal" tabindex="-1" role="dialog" aria-labelledby="futureScheduleModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="futureScheduleModalLabel">Your Future On-Duty Periods</h4>
            </div>
            <div class="modal-body">
                <ul id="userOnOff" class="list-unstyled">
                    <li><i class="fa fa-spinner fa-spin"></i> Loading schedule...</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- My Schedule Section with Contract Colors -->
<div class="container">
    <div class="row outer-bottom-sm" style="margin-top: 50px;" id="myScheduleSection">
        <h3 style="margin-top: 0px;">My Schedule</h3>
        <table class="mysched table table-bordered text-center">
            <thead>
                <tr>
                    <th id="0"> <span class="date"></span><br><span class="day-name">Monday</span></th>
                    <th id="1"><span class="date"></span><br><span class="day-name">Tuesday</span></th>
                    <th id="2"><span class="date"></span><br><span class="day-name">Wednesday</span></th>
                    <th id="3"><span class="date"></span><br><span class="day-name">Thursday</span></th>
                    <th id="4"><span class="date"></span><br><span class="day-name">Friday</span></th>
                    <th id="5"><span class="date"></span><br><span class="day-name">Saturday</span></th>
                    <th id="6"><span class="date"></span><br><span class="day-name">Sunday</span></th>
                </tr>
            </thead>
            <tbody>
                <tr id="userSched"></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Full Schedule Section -->
<div class="container">
    <div class="row outer-top-xs" id="advancedSelect">
        <h2 class="page-header text-center">Full Schedule</h2>
        
        <!-- Filters Row -->
        <div class="filter-row row">
            <!-- Week Selector -->
            <div class="col-md-4 outer-bottom-xs">    
                <h4 class="text-center">Select Week</h4>
                <input type="text" class="form-control" id="sched_week">
                
                <h4 class="text-center">Validity Check</h4>
                <select class="form-control" id="strict-search">
                    <option value="2">Check ALL Validity</option>
                    <option value="1">Check REQUIRED Validity</option>
                    <option value="0" selected>No Validity Check</option>
                </select>
            </div>
            
            <!-- Contract and Aircraft Selectors -->
            <div class="col-md-4 outer-bottom-xs">
                <h4 class="text-center">Contract</h4>
                <select class="form-control" id="contract-select">
                    <option value="any" selected>Any Contract</option>
                </select>
                
                <h4 class="text-center">Aircraft</h4>
                <select class="form-control" id="craft-select">
                    <option value="any" selected>Any Craft</option>
                </select>
            </div>

            <!-- Legend and Actions -->
            <div class="col-md-4 outer-bottom-xs">
                <div id="contractLegend">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div class="legend-details" style="flex: 1; min-width: 0;">
                            <h4 style="margin: 0;">Contract Colors</h4>
                            <div id="legendColors" class="legend-colors" style="margin-top: 10px;">
                                <!-- Legend items will be loaded here by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="action-buttons-container" style="margin-left: 10px; display: flex; flex-direction: column; gap: 5px;">
                            <button class="btn btn-primary" id="printScheduleBtn" style="white-space: nowrap; padding: 5px 10px;">
                                <i class="fa fa-print"></i> Print Schedule
                            </button>
                            <button class="btn btn-success" id="downloadScheduleBtn" style="white-space: nowrap; padding: 5px 10px;">
                                <i class="fa fa-download"></i> Download Schedule
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="printable-area">
            <!-- Schedule Table -->
            <div class="schedule-container">
                <table class="fullsched">
                    <thead>
                        <tr>
                            <th scope="rowgroup">Aircraft</th>
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
                        <!-- Aircraft rows will be dynamically inserted here -->
                    </tbody>    
                </table>
            </div>
        </div>
    </div>

    <!-- Thoughts Form -->
    <div class="row inner-left-md inner-right-md" style="margin-top: 40px;">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Let Us Know Your Thoughts</h3>
                </div>
                <div class="panel-body" style="padding: 15px;">
                    <form id="thoughtsForm">
                        <!-- CSRF token is NOT needed for logged-in users, so it's removed -->
                        <div id="thoughtsFeedback" style="margin-bottom:15px;"></div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="thoughtsName">Name (Optional)</label>
                                    <input type="text" class="form-control" id="thoughtsName" placeholder="Name (Optional)" value="<?php echo htmlspecialchars($firstName . ' ' . $lastName); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="thoughtsEmail">Email (Optional)</label>
                                    <input type="email" class="form-control" id="thoughtsEmail" placeholder="Email (Optional)">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="thoughtsMessage">Message</label>
                            <textarea id="thoughtsMessage" class="form-control" rows="5" style="resize: vertical;" required></textarea>
                        </div>
                        <button type="button" class="btn btn-default" id="submitThoughts" style="width: 100%;">Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div><!-- End main container -->
        <!-- ABOVE - Thoughts Container Section -->

<style>
    /* ========== ON DUTY HEADER (FIXES #1 & #5) ========== */
    #onOffHeader {
        margin-bottom: 5px !important;
    }

    .on-duty-title-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px; /* Space between "On Duty:" text and button */
    }

    /* Add this new rule to your CSS */
    .future_sched .modal-body {
        text-align: center;
        font-size: 1.1em; /* Optional: Makes the text a bit larger and clearer */
    }

    .on-duty-text {
        font-size: 2rem;
        font-weight: 500;
        line-height: 1.2;
    }

    /* ========== MY SCHEDULE SECTION ========== */
    #myScheduleSection {
        /* margin-top: 1px !important; */
        margin-bottom: 1px !important;
        background-color: rgb(163, 220, 236);
        padding: 5px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .mysched {
        width: 100% !important;
        table-layout: fixed;
        border-collapse: collapse;
        margin: 5px 0;
    }

    /* FIX #2: Reduced vertical padding */
    .mysched th {
        background-color: #343a40; /* THIS LINE SETS THE DARK BACKGROUND COLOR */
        color: white;
        /* REDUCED vertical padding to tighten the gap */
        padding: 10px 8px !important;
        text-align: center;
        min-width: 20px;
    }

    /* FIX #3: Assigned craft type space */
    .mysched td {
        padding: 20px 10px !important;
        border: 1px solid #dee2e6;
        /* vertical-align: bottom !important; */
        /* height: 100px; */
    }

    /* FIX #2: Tightened line-height */
    .mysched .day-name {
        font-weight: bold;
        /* display: block; */
        line-height: 1.4; /* Tightens line spacing */
    }

    .mysched .date {
        font-size: 0.8em;
        /* display: block; */
        line-height: 1.4; /* Tightens line spacing */
    }

    /* ========== GLOBAL STYLES (Unchanged) ========== */
    .container {
        width: 100%;
        max-width: 1200px;
        padding-right: 15px;
        padding-left: 15px;
        margin-right: auto;
        margin-left: auto;
    }
</style>

<!-- Print Styles (will only be used when printing) -->
<style>
    /* ========== FULL SCHEDULE SECTION ========== */
    #advancedSelect {
        margin-top: 15px !important;
    }

    .fullsched {
        width: 100% !important;
        table-layout: fixed;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .fullsched th, .fullsched td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: center;
        width: calc(100% / 7);
    }

    .fullsched th {
        background-color: #f2f2f2;
        position: sticky;
        top: 0;
    }

    .schedule-container {
        width: 100%;
        overflow-x: auto;
    }

    /* ========== THOUGHTS PANEL SECTION ========== */
    .thoughts-container {
        margin-top: 40px;
        width: 100%;
        max-width: 1200px;
        margin-left: auto;
        margin-right: auto;
        padding: 0 15px;
    }

    .thoughts-panel {
        width: 100%;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,0.05);
    }

    .thoughts-panel .panel-heading {
        padding: 10px 15px;
        background-color: #f5f5f5;
        border-bottom: 1px solid #ddd;
        border-top-left-radius: 3px;
        border-top-right-radius: 3px;
    }

    .thoughts-panel .panel-title {
        margin-top: 0;
        margin-bottom: 0;
        font-size: 16px;
    }

    .thoughts-panel .panel-body {
        padding: 15px;
    }

    /* ========================================================= */
    /* === ADD THESE NEW STYLES FOR THE CONTRACT COLOR LEGEND  === */
    /* ========================================================= */
    .legend-item {
        display: flex;         /* Use flexbox for easy vertical alignment */
        align-items: center;   /* Vertically centers the box and the text */
        margin-bottom: 5px;    /* Adds a little space between each contract line */
    }

    .color-box {
        width: 18px;           /* Sets the width of the colored square */
        height: 18px;          /* Sets the height of the colored square */
        margin-right: 8px;     /* Adds space between the square and the contract name */
        border: 1px solid #aaa;/* A subtle border so white/light colors are visible */
        display: inline-block; /* Makes the <span> behave like a block with dimensions */
        flex-shrink: 0;        /* Prevents the box from shrinking if space is tight */
    }

    @media print {

    }
</style>

<?php
// =========================================================================
// === DEFINE PAGE-SPECIFIC STYLESHEETS & SCRIPTS (HOME)                 ===
// =========================================================================
$page_stylesheets = [
    'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/base/jquery-ui.min.css',
    'css/print.css', // This seems to be a print-specific stylesheet
];

$page_scripts = [
    // Third-party libraries (non-core)
    'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/jquery-ui.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.js',
    'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
    'https://npmcdn.com/flatpickr/dist/plugins/weekSelect/weekSelect.js',
    
    // Your custom scripts for the home page
    'scheduleHomeReadOnlyFunctions.js',
    'home-duty-schedule.js',
];

include_once "footer.php"; 
?>
