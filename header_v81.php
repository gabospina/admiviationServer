<?php
// =========================================================================
// === header.php - MODERNIZED AND SECURE VERSION (FINAL FIX)            ===
// =========================================================================

// --- 0. DEFINE GLOBAL BASE PATH ---
// This constant will be used for all links and assets.
define('BASE_PATH', '/');

// --- 1. SESSION AND SECURITY ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- ADD CSRF TOKEN GENERATION ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION["HeliUser"])) {
    // Use the constant for a reliable redirect
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

// --- 2. DATABASE AND INITIALIZATION ---
require_once "db_connect.php";
require_once "time_utc_helpers.php";
require_once 'hangar_clock_display.php';

// --- 3. GATHER USER AND COMPANY DATA ---
$user_id = (int)$_SESSION["HeliUser"];
$company_id = isset($_SESSION["company_id"]) ? (int)$_SESSION["company_id"] : 0;
$accountName = "N/A";
$firstName = "User";
$lastName = "";

// Get Company Name
if ($company_id > 0) {
    $stmt = $mysqli->prepare("SELECT company_name FROM companies WHERE id = ?");
    $stmt->bind_param("i", $company_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($company = $result->fetch_assoc()) {
            $accountName = $company['company_name'];
        }
    }
    $stmt->close();
}

// Get User's First Name, Last Name, and Timezone
$stmt = $mysqli->prepare("SELECT firstname, lastname, timezone FROM users WHERE id = ?"); // Added timezone
$stmt->bind_param("i", $user_id);
if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($user = $result->fetch_assoc()) {
        $firstName = $user['firstname'];
        $lastName = $user['lastname'];
        
        // Store the user's timezone in the session.
        // Use 'UTC' as a safe fallback if their preference is not set.
        $_SESSION['user_timezone'] = $user['timezone'] ?? 'UTC';
    }
}
$stmt->close();

// =========================================================================
// === THE FIX IS HERE: Corrected User Activity Tracking                 ===
// =========================================================================
if (isset($page) && !empty($page)) {
    // 1. Create a whitelist of all valid column names (your page names).
    $allowed_columns = [
        'home', 'pilots', 'contracts', 'daily_manager', 'hangar',
        'crafts', 'statistics', 'docufile', 'account', 'news', 'store',
        'posts', 'messaging', 'admin', 'tracker flight', 'training' // Added 'training'
    ];

    // 2. Check if the current page is in our whitelist.
    if (in_array($page, $allowed_columns)) {
        $time = time();
        
        // 3. Since $page is now guaranteed to be safe, we can build the query directly.
        // NOTE: We are NOT using a prepared statement for the SHOW COLUMNS query.
        $check_column_query = "SHOW COLUMNS FROM user_tracking LIKE '" . $mysqli->real_escape_string($page) . "'";
        $check_result = $mysqli->query($check_column_query);

        if ($check_result && $check_result->num_rows > 0) {
            // 4. The UPDATE statement CAN and SHOULD still be prepared.
            // We use backticks (`) around the column name.
            $stmt_update = $mysqli->prepare("UPDATE user_tracking SET `$page` = `$page` + 1, last_accessed=? WHERE id=?");
            if ($stmt_update) {
                $stmt_update->bind_param("ii", $time, $user_id);
                $stmt_update->execute();
                $stmt_update->close();
            }
        }
    }
}
// =========================================================================

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admviation</title>
	<link rel="icon" href="<?php echo BASE_PATH; ?>favicon.ico">


    <!-- CORE STYLESHEETS -->
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>css/font-awesome.min.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>css/stylesheet.css">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>css/style-clock-bell.css">
	<link rel="stylesheet" href="<?php echo BASE_PATH; ?>css/modern-theme.css">
	<link rel="stylesheet" href="<?php echo BASE_PATH; ?>css/contract-management.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css"/>

    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>../fonts/fontawesome-webfont.woff2?v=4.3.0" as="font" type="font/woff2" crossorigin>
    
    <!-- ====================================================================== -->
    <!-- === FIX: DYNAMIC BASE URL FOR JAVASCRIPT === -->
    <?php
        // Default base URL for JavaScript AJAX calls
        $js_base_url = BASE_PATH;
        // If the including page has set $page to 'flight-tracker', adjust the path
        if (isset($page) && $page === 'flight-tracker') {
            $js_base_url = BASE_PATH . 'flight-tracker/';
        }
    ?>
    <script>var base_url = '<?php echo $js_base_url; ?>';</script>
    <!-- ====================================================================== -->

    <!-- DYNAMIC PAGE-SPECIFIC STYLESHEET LOADER -->
    <?php
    if (isset($page_stylesheets) && is_array($page_stylesheets)) {
        foreach ($page_stylesheets as $sheet) {
            // This logic correctly handles local and external sheets
            if (strpos($sheet, 'http') === 0) {
                echo '<link rel="stylesheet" href="' . htmlspecialchars($sheet) . '">' . "\n";
            } else {
                echo '<link rel="stylesheet" href="' . BASE_PATH . htmlspecialchars($sheet) . '">' . "\n";
            }
        }
    }
    ?>
</head>

<body>
    <body>
    <!-- ========================================================= -->
    <!-- === ADD CSRF TOKEN INPUT FIELD TO HEADER === -->
    <!-- ========================================================= -->
    <input type="hidden" name="csrf_token" id="csrf_token_manager" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
    
    <!-- DEBUG: Remove this after testing -->
    <!-- Token: <?php echo htmlspecialchars($_SESSION['csrf_token'] ?? 'NOT SET'); ?> -->
    <!-- ========================================================= -->


    <!-- HEADER & NAVIGATION BAR -->
	<form class="form-inline" id="logoutform" action="<?php echo BASE_PATH; ?>logout.php" method="post"></form>
	<div class="dark-bg" id="navcontainer">

    <a href="<?php echo BASE_PATH; ?>home.php" id="brand">Admviation - <?php echo htmlspecialchars($accountName, ENT_QUOTES, 'UTF-8'); ?></a>
    <!-- This new wrapper groups everything on the right -->

    <!-- START: ADD THESE FOUR LINES -->
    <button id="sidebar-toggle" class="header-icon-btn">
        <i class="fa fa-bars"></i>
    </button>
    <!-- END: ADD THESE FOUR LINES -->

    <div class="header-right-items">

        <!-- This wrapper holds the icons -->
        <div id="header-icons-wrapper">
            <!-- Bell Icon (First) -->
            <div id="notification-dropdown" class="dropdown">
                <a href="#" class="header-icon-btn dropdown-toggle">
                    <i class="fa fa-bell-o"></i>
                    <span id="notification-number" class="badge"></span>
                </a>
                <div class="dropdown-menu notification-dropdown-menu">
                    <div class="notification-item">No new notifications</div>
                </div>
            </div>
            <!-- Clock Icon (Second) -->
            <div id="standalone-clock-container" class="dropdown">
                <a href="#" id="clock-toggle-icon" class="header-icon-btn dropdown-toggle">
                    <i class="fa fa-clock-o"></i>
                </a>
                <div class="dropdown-menu clock-dropdown-menu">
                    <div class="row">
                        <div class="col-xs-6 text-center"><div class="lbl">Local</div><span class="timeVal" id="header-local-time">--:--:--</span></div>
                        <div class="col-xs-6 text-center"><div class="lbl">UTC</div><span class="timeVal" id="header-utc-time">--:--:--</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Dropdown (Third) -->
        <div id="user-dropdown">
            <div class="dropdown-header">
                <b>Welcome, <br/><span id='username'><?php echo htmlspecialchars($firstName . ' ' . $lastName, ENT_QUOTES, 'UTF-8'); ?></span></b>
                <div class="fa fa-sort-down"></div>
            </div>
            <ul class="dropdown-content">
                <!-- --- THIS IS THE FIX --- -->
                <li><a href="<?php echo BASE_PATH; ?>hangar.php" style="color: white;"><i class="fa fa-cogs"></i> My Hangar</a></li>
                <li><a href="<?php echo BASE_PATH; ?>logout.php" style="color: white;"><i class="fa fa-power-off"></i> Log Out</a></li>
                <!-- --- END OF FIX --- -->
            </ul>
        </div>

    </div> <!-- End .header-right-items -->

</div> <!-- End #navcontainer -->

    <!-- SIDEBAR NAVIGATION -->
    <div class="sidebar">
        <ul class="sidebar-list">
            <a href="<?php echo BASE_PATH; ?>home.php"><li class="sidebar-item"><i class="fa fa-lg fa-calendar"></i> Dashboard Schedule</li></a>
            <a href="<?php echo BASE_PATH; ?>training.php"><li class="sidebar-item"><i class="fa fa-lg fa-calendar"></i> Training Schedule</li></a>
            <a href="<?php echo BASE_PATH; ?>daily_manager.php"><li class="sidebar-item"><i class="fa fa-lg fa-pencil"></i> Daily Management</li></a>
            <a href="<?php echo BASE_PATH; ?>contracts.php"><li class="sidebar-item"><i class="fa fa-lg fa-book"></i> Contracts</li></a>
            <a href="<?php echo BASE_PATH; ?>crafts.php"><li class="sidebar-item"><i class="fa fa-lg fa-book"></i> Crafts</li></a>
            <a href="<?php echo BASE_PATH; ?>document_docufile.php"><li class="sidebar-item"><i class="fa fa-lg fa-file-text-o"></i> Documents</li></a>
            <a href="<?php echo BASE_PATH; ?>statistics.php"><li class="sidebar-item"><i class="fa fa-lg fa-bar-chart-o"></i> My Statistics</li></a>
            <a href="<?php echo BASE_PATH; ?>pilots.php"><li class="sidebar-item"><i class="fa fa-lg fa-users"></i> Pilots</li></a>
            <a href="<?php echo BASE_PATH; ?>hangar.php"><li class="sidebar-item"><i class="fa fa-lg fa-cogs"></i> My Hangar</li></a>
            
            <!-- <a href="/admviation_2/flight-tracker/flight_tracker.php"><li class="sidebar-item"><i class="fa fa-lg fa-map-o"></i> Flight Tracker</li></a> -->
            <!-- --- CORRECTED FLIGHT TRACKER LINK --- -->
            <a href="<?php echo BASE_PATH; ?>flight-tracker/flight_tracker.php"><li class="sidebar-item"><i class="fa fa-lg fa-map-o"></i> Flight Tracker</li></a>

            <!-- Community Dropdown -->
            <li class="sidebar-item">
                <i class="fa fa-lg fa-users"></i> Community
                <ul class='sidebar-dropdown-list'>
                    <a href="<?php echo BASE_PATH; ?>messaging.php"><li class="sidebar-item"><i class="fa fa-lg fa-envelope-o"></i>Message Center</li></a>
                    <a href="<?php echo BASE_PATH; ?>news.php"><li class="sidebar-item"><i class="fa fa-lg fa-newspaper-o"></i>News</li></a>
                    <a href="<?php echo BASE_PATH; ?>store.php"><li class="sidebar-item"><i class="fa fa-lg fa-money"></i>Store</li></a>
                </ul>
            </li>
        </ul>
    </div>
    
    <!-- This div opens the main content area that footer.php will close -->
    <div class="content">