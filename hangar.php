<?php
// hangar.php - REFACTORED FOR NEW DATABASE STRUCTURE

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

include_once "db_connect.php";
$page = "hangar";

// =========================================================================
// === START: REFACTORED DYNAMIC DATA FETCHING LOGIC                     ===
// =========================================================================

// Define user_id and company_id from the session
$user_id = isset($_SESSION["HeliUser"]) ? (int)$_SESSION["HeliUser"] : 0;
$company_id = isset($_SESSION["company_id"]) ? (int)$_SESSION["company_id"] : 0;

// --- 1. Fetch the STANDARD list of fields for the user's company ---
// This part of the logic remains the same, as it queries the correct table.
$standardFields = [];
$sqlFields = "SELECT field_key, field_label FROM user_company_licence_fields WHERE company_id = ? ORDER BY display_order, field_label ASC";
$stmtFields = $mysqli->prepare($sqlFields);
if ($stmtFields) {
    $stmtFields->bind_param("i", $company_id);
    $stmtFields->execute();
    $result = $stmtFields->get_result();
    while ($row = $result->fetch_assoc()) {
        $standardFields[$row['field_key']] = $row['field_label'];
    }
    $stmtFields->close();
} else {
    error_log("Hangar.php - Failed to prepare statement for user_company_licence_fields: " . $mysqli->error);
}

// --- 2. Fetch the SPECIFIC validity data for THIS pilot from the NEW table ---
// EXPLANATION: This is the core of the refactoring. Instead of building a complex query
// to select from a wide table, we run a single, simple query on our new `user_licence_data` table.
$pilotValidityData = [];
$sqlData = "SELECT field_key, expiry_date, document_path FROM user_licence_data WHERE user_id = ?";
$stmtData = $mysqli->prepare($sqlData);
if ($stmtData) {
    $stmtData->bind_param("i", $user_id);
    $stmtData->execute();
    $resultData = $stmtData->get_result();

    // Now, we loop through the results and build an associative array.
    // This makes the data compatible with the existing HTML/PHP code in the table below.
    while ($row = $resultData->fetch_assoc()) {
        $key = $row['field_key'];
        $pilotValidityData[$key] = $row['expiry_date'];
        $pilotValidityData[$key . '_doc'] = $row['document_path'];
    }
    $stmtData->close();
} else {
    error_log("Hangar.php - Failed to prepare statement for user_licence_data: " . $mysqli->error);
}

// =========================================================================
// === END: REFACTORED DYNAMIC DATA FETCHING LOGIC                       ===
// =========================================================================

// The rest of the file (header include, HTML) remains the same.
include_once "header.php";

if ($user_id === 0) {
    // A basic guard against non-logged-in users
    echo "<div class='container'><p class='alert alert-danger'>Error: User not authenticated. Please log in.</p></div>";
    include_once "footer.php";
    exit();
}

?>

<!-- === FINAL CSS FIX & REDESIGN STYLES === -->
<style>
    /* 
     * THE FIX for the white space: This targets the main container of this page
     * and pushes it down from the top navbar.
    */
    .content > .light-bg {
        padding-top: 40px !important;
        margin-top: 0 !important;
    }

    /* New styles for a cleaner card layout */
    .card {
        margin-bottom: 20px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 1px 1px rgba(0,0,0,.05);
    }
    .card-header {
        padding: 10px 15px;
        background-color: #f5f5f5;
        border-bottom: 1px solid #ddd;
    }
    .card-title {
        margin-top: 0;
        margin-bottom: 0;
        font-size: 16px;
    }
    .card-body {
        padding: 15px;
    }
    .equal-height {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .equal-height .panel-body, .equal-height .card-body {
        flex-grow: 1;
    }

    /* THE FIX FOR A SQUARE SHAPE */
    #profile-picture-container {
        position: relative;
        cursor: pointer;
        max-width: 200px;
        margin: auto;
        border: 2px dashed #ccc;
        padding: 4px;
        border-radius: 8px; /* Use a small value for rounded corners */
        overflow: hidden;
    }
    #profile-picture {
        border-radius: 6px; /* A slightly smaller radius for the inner image */
        width: 100%;
        height: auto;
        display: block; /* Prevents extra space under the image */
    }
    #profile-picture-overlay {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.5); color: white; opacity: 0;
        display: flex; align-items: center; justify-content: center;
        transition: opacity 0.3s;
        border-radius: 6px; /* Match the inner image's radius */
    }
    #profile-picture-container:hover #profile-picture-overlay {
        opacity: 1;
    }
</style>

<!-- ========================================================= -->
<!-- === GLOBAL CSRF TOKEN FOR AJAX REQUESTS ON THIS PAGE  === -->
<!-- ========================================================= -->
<input type="hidden" id="csrf_token_hangar" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
<!-- ========================================================= -->

<div class="light-bg" id="personalSection">
    <div class="container inner-sm">

    <!-- BELOW - Personal Information and Duty Schedule - SECTION - Feb-19-24 -->

    <div class="row inner-left-md inner-right-md">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <a href="#" data-toggle="modal" data-target="#personalInfoModal">
                        <h3 class="panel-title">Personal Information - <small>(Click to edit)</small></h3>
                    </a>
                </div>
                <div class="panel-body">
                    <!-- Inside the Personal Information panel-body -->
                    <ul class="list-unstyled">
                        <li><strong>Name:</strong> <span id="displayName"></span></li>
                        <li><strong>Username:</strong> <span id="displayUsername"></span></li>
                        <li><strong>Nationality:</strong> <span id="nationality"></span></li>
                        <li id="hireDateDisplay"></li> 
                        <li><strong>License:</strong> <span id="nalLic"></span></li>
                        <li><strong>Foreign License:</strong> <span id="forLic"></span></li>
                        <li><strong>E-mail:</strong> <span id="persEmail"></span></li>
                        <li><strong>Phone:</strong> <span id="persPhone"></span></li>
                        <li><strong>Secondary Phone:</strong> <span id="persPhoneTwo"></span></li>
                        
                        <!-- THE FIX: The "Current Position" block is now at the bottom -->
                        <li style="margin-top: 15px;"> 
                            <strong>Current Position:</strong>
                            <div id="pilotContainer" data-pilot-id="<?php echo htmlspecialchars($_SESSION["HeliUser"], ENT_QUOTES, 'UTF-8'); ?>">
                                
                                <p style="margin-top: 5px; margin-bottom: 2px;">
                                    <strong>Assigned Craft Qualifications:</strong>
                                </p>
                                <ul id="assignedCraftsList" class="list-unstyled" style="margin-bottom: 10px;">
                                    <li><i class="fa fa-spinner fa-spin"></i> Loading...</li>
                                </ul>

                                <!-- This paragraph no longer has the class that adds top margin -->
                                <p style="margin-bottom: 2px;">
                                    <strong>Assigned Contracts:</strong>
                                </p>
                                <ul id="assignedContractsList" class="list-unstyled" style="margin-bottom: 0;">
                                    <li><i class="fa fa-spinner fa-spin"></i> Loading...</li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

            <!-- ========================================================= -->
            <!-- === NEW: READ-ONLY DUTY SCHEDULE PANEL FOR HANGAR.PHP === -->
            <!-- ========================================================= -->
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">My On Duty Periods</h3>
                    </div>
                    <div class="panel-body">
                        <!-- The availability data will be loaded into this list by JavaScript -->
                        <ul id="hangarDutyScheduleList" class="list-unstyled">
                            <li><i class="fa fa-spinner fa-spin"></i> Loading schedule...</li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- ========================================================= -->


            
        </div>
        <!-- ABOVE - Personal Information and ON DUTY OFF DUTY user_availibility SECTION -->

    <!-- =========== BELOW - VALIFITY DATES for EXPIRE CHECKS & LICENCES - SECTION ================== -->

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">My Certifications & Validities</h5>
        </div>

        <div class="card-body" style="padding: 0;">
            <div id="notification-container" style="padding: 15px 15px 0 15px;"></div> <!-- Notification container -->
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="validityTable">
                    <thead class="table-dark">
                        <tr>
                            <th width="30%">Certification</th>
                            <th width="25%">Expiry Date</th>
                            <th width="15%">Status</th>
                            <th width="30%">Document</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                    // === THE FIX: Loop through the DYNAMIC standardFields array instead of a hard-coded one ===
                    if (empty($standardFields)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                Your company manager has not set up any standard validity fields yet.
                            </td>
                        </tr>
                    <?php else:
                        foreach ($standardFields as $field_key => $field_label): 
                            // Get the specific data for this pilot for this field
                            $expiryDate = $pilotValidityData[$field_key] ?? '';
                            $documentPath = $pilotValidityData[$field_key . '_doc'] ?? '';
                        ?>
                            <tr data-field="<?= htmlspecialchars($field_key) ?>">
                                <td><strong><?= htmlspecialchars($field_label) ?></strong></td>
                                <td>
                                    <div class="input-group" style="width: 170px; position: relative; z-index: 1;">
                                        <input type="text" class="form-control datepicker validity-date" value="<?= htmlspecialchars($expiryDate) ?>" placeholder="DD-MM-YYYY">
                                        <span class="input-group-btn">
                                            <button class="btn btn-primary save-validity" style="margin-left: 5px !important;" type="button">
                                                <i class="fa fa-save"></i> Save
                                            </button>
                                        </span>
                                    </div>
                                </td>
                                <td class="status-cell">
                                    <!-- Status will be populated by JavaScript -->
                                </td>
                                <td class="document-cell">
                                    <?php if (!empty($documentPath)): ?>
                                        <a href="<?= htmlspecialchars($documentPath) ?>" target="_blank" class="btn btn-xs btn-info view-license-btn">
                                            <i class="fa fa-eye"></i> View
                                        </a>
                                        <button class="btn btn-xs btn-warning remove-license-btn" data-field="<?= htmlspecialchars($field_key) ?>">
                                            <i class="fa fa-trash"></i> Remove
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-xs btn-success upload-license-btn" data-field="<?= htmlspecialchars($field_key) ?>">
                                            <i class="fa fa-upload"></i> Upload
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach;
                    endif; ?>
                </tbody>
            </table>
        </div>
    </div>

        <!-- START: MODIFICATION 4 - Add a hidden form for the file upload -->
        <form id="licenseUploadForm" style="display: none;" enctype="multipart/form-data">
            <input type="file" name="licenseFile" id="licenseUploadInput" accept=".pdf,.jpg,.jpeg,.png">
            <input type="hidden" name="pilotId" value="<?= htmlspecialchars($user_id) ?>"> <!-- CHANGED to $user_id -->
            <input type="hidden" name="validityField" id="validityFieldInput">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
        </form>
        <!-- END: MODIFICATION 4 -->

    </div>

<!-- ABOVE - Validity dates for trainng schedule - SECTION -->

<!-- ===================================================================== -->
<!-- === START: NEW, REDESIGNED 3-COLUMN BOTTOM SECTION                === -->
<!-- ===================================================================== -->

<div class="row inner-left-md inner-right-md">

    <!-- === COLUMN 1: Profile Picture & Password === -->
    <div class="col-md-4">
        <!-- Profile Picture Card -->
        <div class="card">
            <div class="card-header"><h5 class="card-title">Profile Picture</h5></div>
            <div class="card-body text-center">
                <div id="profile-picture-container" data-toggle="modal" data-target="#change-profile-picture">
                    <img src="uploads/pictures/default_picture.jpg" id="profile-picture" alt="Click to Change Picture"/>
                    <div id="profile-picture-overlay">
                        <div><i class="fa fa-pencil"></i><br>
                            Change Picture</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="card text-center">
            <div class="card-header"><h5 class="card-title">Security</h5></div>
            <div class="card-body">
                <a class="btn btn-default" data-toggle="modal" data-target=".changepass">
                    <i class="fa fa-key"></i> Change Password
                </a>
            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- === START: FINAL CLOCK SETTINGS & DISPLAY CARD        === -->
    <!-- ======================================================= -->

    <!-- === COLUMN 2: Clock Settings & Display === -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Clock Settings</h5></div>
            <div class="card-body">
                
                <!-- Input Row for settings -->
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="clock-name">Clock Name:</label>
                            <input type="text" id="clock-name" class="form-control" placeholder="e.g., My Local Time">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label for="clock-timezone">Clock Timezone:</label>
                            <select id="clock-timezone" class="form-control">
                                <?php
                                // Get the user's currently saved timezone from the session
                                $currentUserTimezone = $_SESSION['user_timezone'] ?? 'UTC';
                                
                                $timezones = DateTimeZone::listIdentifiers();
                                foreach ($timezones as $tz) {
                                    $dtz = new DateTimeZone($tz);
                                    $time = new DateTime('now', $dtz);
                                    $offset = $dtz->getOffset($time) / 3600;
                                    $sign = $offset >= 0 ? '+' : '';
                            
                                    // Add 'selected' if the timezone matches the user's current setting
                                    $selected = ($tz === $currentUserTimezone) ? 'selected' : '';
                            
                                    echo "<option value='$tz' data-offset='$offset' $selected>(UTC$sign$offset) $tz</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="form-group text-right" style="margin-top: 15px;">
                    <button class="btn btn-primary" id="saveClockSettings"><i class="fa fa-save"></i> Save Clock Settings</button>
                </div>

                <hr> <!-- Add a separator for visual clarity -->

                <!-- === THE NEWLY ADDED CLOCK DISPLAY === -->
                <div id="clock-display">
                    <div class="well" style="padding: 15px; margin-bottom: 0; background-color: #f9f9f9; border-radius: 4px;">
                        <div class="row">
                            <div class="col-xs-12 text-center">
                                <h4 id="display-clock-name" style="color: #337ab7; margin-top: 0; margin-bottom: 15px;">
                                    <!-- Name will be loaded by JavaScript -->
                                </h4>
                            </div>
                            <div class="col-xs-6">
                                <p style="margin-bottom: 5px;">
                                    <i class="fa fa-clock-o text-success"></i> 
                                    <strong>Local Time:</strong> 
                                    <span id="display-local-time" class="text-success" style="font-weight: bold;">--:--:--</span>
                                </p>
                            </div>
                            <div class="col-xs-6">
                                <p style="margin-bottom: 5px;">
                                    <i class="fa fa-globe text-info"></i> 
                                    <strong>UTC Time:</strong> 
                                    <span id="display-utc-time" class="text-info" style="font-weight: bold;">--:--:--</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- === END OF CLOCK DISPLAY === -->

            </div> <!-- End card-body -->
        </div>
    </div> <!-- End Column -->

    <!-- ======================================================= -->
    <!-- === END: FINAL CLOCK SETTINGS & DISPLAY CARD          === -->
    <!-- ======================================================= -->

    <!-- "Let us know your thoughts" can be removed or kept, as you prefer. It's omitted here for a cleaner layout. -->

    <!-- ===================================================================== -->
    <!-- === END: NEW, REDESIGNED 3-COLUMN BOTTOM SECTION                  === -->
    <!-- ===================================================================== -->

        <!-- ================================== -->
        <!-- Thoughts Section - Wider Layout == -->
        <!-- ================================== -->
        <div class="row inner-left-md inner-right-md">
            <div class="col-md-12"> <!-- Changed from col-md-6 to col-md-12 -->
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Let Us Know Your Thoughts-hangar</h3>
                    </div>
                    <div class="panel-body" style="padding: 15px;"> <!-- Removed reduce-body class -->
                        <form id="thoughtsForm">
                            <!-- Add the CSRF token from header.php -->
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <!-- Div for feedback messages -->
                            <div id="thoughtsFeedback" style="margin-bottom:15px;"></div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="thoughtsName">Name (Optional)</label>
                                        <input type="text" class="form-control" id="thoughtsName" placeholder="Name (Optional)">
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
                            <!-- Change to type="button" -->
                            <button type="button" class="btn btn-default" id="submitThoughts" style="width: 100%;">Send</button>
                        </form>
                    </div>   
                </div>
            </div>
        </div>
        <!-- ABOVE - Thoughts Container Section -->

        <!-- ========================================= -->
        <!-- The Modal for Changing Profile Picture == -->
        <!-- ========================================= -->
        <div class="modal" id="change-profile-picture" tabindex="-1" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Change Your Profile Picture</h4>
                    </div>
                    <div class="modal-body">
                        <!-- THE FIX: The Dropzone library needs a <form> element to work. -->
                        <!-- The action should point to your PHP upload script. -->
                        <form action="hangar_change_profile_picture.php" 
                            class="dropzone" 
                            id="profilePictureUpload">
                            <div class="dz-message" data-dz-message>
                                <span>Drop image here or click to upload.</span><br>
                                <small class="text-muted">(Max file size: 2MB. Allowed types: JPG, PNG, GIF)</small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- ABOVE - Modal for Change Profile Picture -->
    </div>

<!-- ====================== ABOVE - ENDS of WHOLE hangar.php DESIGN PAGE - COMPLETED - Feb-20-25 ====================== -->

        <!-- BELOW - PERSONAL INFORMATION MODAL - DO NOT DELETE - IT IS WORKING FINE - Feb-19-25 -->

        <!-- <div class="modal" id="personalInfoModal" tabindex="-1" role="dialog" aria-labelledby="personalInfoModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                        <h3 class="modal-title">Edit Personal Information</h3>
                    </div>
                    <div class="modal-body">
                        <form>
                            <div class="form-group">
                                <label for="firstname">First Name:</label>
                                <input type="text" class="form-control" id="firstname">
                            </div>
                            <div class="form-group">
                                <label for="lastname">Last Name:</label>
                                <input type="text" class="form-control" id="lastname">
                            </div>
                            <div class="form-group">
                                <label for="usernameInput">Username:</label>
                                <input type="text" class="form-control" id="usernameInput">
                            </div>
                            <div class="form-group">
                                <label for="user_nationality">Nationality:</label>
                                <input type="text" class="form-control" id="user_nationality">
                            </div>
                            <div class="form-group">
                                <label for="nal_license">National License:</label>
                                <input type="text" class="form-control" id="nal_license">
                            </div>
                            <div class="form-group">
                                <label for="for_license">Foreign License:</label>
                                <input type="text" class="form-control" id="for_license">
                            </div>
                            <div class="form-group">
                                <label for="email">E-mail:</label>
                                <input type="email" class="form-control" id="email">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone:</label>
                                <input type="text" class="form-control" id="phone">
                            </div>
                            <div class="form-group">
                                <label for="phonetwo">Secondary Phone:</label>
                                <input type="text" class="form-control" id="phonetwo">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary savePersonalInfo">Save changes</button>
                    </div>
                </div>
            </div>
        </div> -->

        <!-- ABOVE - END PERSONAL INFORMATION MODAL  DO NOT DELETE - IT IS WORKING FINE - Feb-19-24-->

        <!-- CHANGE PASSWORD MODAL -->
        <div class="modal changepass" tabindex="-1" role="dialog" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                        <h3 class="modal-title" id="changePasswordModalLabel">Change Your Password</h3>
                    </div>
                    <div class="modal-body">
                        <!-- Container for success/error messages -->
                        <div id="changePassAlertContainer" style="display: none;"></div>
                        
                        <p id="passwordRules">Your password must be a minimum of 8 characters long.</p>
                        
                        <!-- The form now has an ID for easy targeting -->
                        <form id="changePasswordForm">
                            <div class="form-group">
                                <label for="oldpass">Current Password:</label>
                                <input type="password" class="form-control" id="oldpass" name="oldpass" required>
                            </div>
                            <div class="form-group">
                                <label for="newpass">New Password:</label>
                                <input type="password" class="form-control" id="newpass" name="newpass" required minlength="8">
                            </div>
                            <div class="form-group">
                                <label for="confpass">Confirm New Password:</label>
                                <input type="password" class="form-control" id="confpass" name="confpass" required>
                            </div>
                            
                            <!-- The footer is the standard place for action buttons -->
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                <!-- Button is now type="submit" to trigger the form's submit event -->
                                <button type="submit" class="btn btn-success" id="submitChangePassBtn">
                                    Save New Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- ABOVE - ENDS CHANGE PASSWORD MODAL in progress -->

<?php
// =========================================================================
// === DEFINE PAGE-SPECIFIC STYLESHEETS & SCRIPTS                        ===
// =========================================================================

// This array tells header.php which CSS files to load for THIS page.
$page_stylesheets = [
    'https://unpkg.com/dropzone@5/dist/min/dropzone.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.css',
    'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.1/themes/base/jquery-ui.min.css',
];

// This array tells footer.php which JS files to load for THIS page,
// in the correct order. Libraries FIRST, your custom script LAST.
$page_scripts = [
    // Libraries
    'https://unpkg.com/dropzone@5/dist/min/dropzone.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.js',
    
    // 1. Utilities (must be loaded first)
    'hangar-utils.js',
    // 2. Feature Modules (the order among these doesn't matter)
    'hangar-personal-info.js',
    'hangar-assignments.js',
    'hangar-schedule.js',
    'hangar-validity.js',
    'hangar-clock.js',
    'hangar-profile.js',
    'hangar-password.js',
    
    // 3. Main Initializer (must be loaded last to run everything)
    'hangar-main.js',
    
];

// Now, include the footer. It will handle loading everything.
include_once "footer.php"; 
?>
