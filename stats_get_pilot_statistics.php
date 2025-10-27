<?php
/*
 * stats_get_pilot_statistics.php
 * Fetches paginated flight log entries for the logged-in user based on a start date.
 */

// --- Error Reporting (Enable for Debugging, Disable for Production) ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// --- Required Files ---
// Use require_once for files essential for the script to run
require_once 'stats_api_response.php'; // For sending JSON responses
require_once 'db_connect.php';      // For establishing $mysqli connection

// --- Session and Authentication ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Prepare Response Object ---
// Create ONE instance to use throughout for consistent responses
$response = new ApiResponse();
$stmtCount = null; // Initialize statement variables
$stmtSelect = null;

// --- Authentication Check ---
if (!isset($_SESSION["HeliUser"])) {
    http_response_code(401); // Unauthorized
    $response->setError("Authentication required")->setSuccess(false);
    $response->send(); // send() includes exit
}

try {
    // --- Get and Validate Input Parameters ---
    $user = (int)$_SESSION["HeliUser"];
    $page = isset($_GET["page"]) ? filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]) : 0;
    // Default to today if 'start' is not provided or empty
    $dateInput = isset($_GET["start"]) && !empty($_GET["start"]) ? $_GET["start"] : date('Y-m-d');
    $init = isset($_GET["init"]) ? filter_var($_GET["init"], FILTER_VALIDATE_BOOLEAN) : false;

    // Set page to 0 if validation fails or it's negative
    if ($page === false || $page < 0) {
        $page = 0;
    }

    // Validate date format
    $dateObject = DateTime::createFromFormat('Y-m-d', $dateInput);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $dateInput) {
        // Try another common format if needed (e.g., from datepicker)
        $dateObject = DateTime::createFromFormat('m/d/Y', $dateInput);
        if (!$dateObject) {
             throw new Exception("Invalid start date format. Please use YYYY-MM-DD.");
        }
        // Convert to YYYY-MM-DD for database
        $date = $dateObject->format('Y-m-d');
    } else {
        $date = $dateInput; // Assign validated date
    }


    // --- Database Connection Check (from db_connect.php) ---
    if (!$mysqli || $mysqli->connect_error) {
         throw new Exception("Database connection failed: " . ($mysqli->connect_error ?? 'Unknown error'));
    }

    // --- Get Total Count for Pagination ---
    $countQuery = "SELECT COUNT(id) AS total FROM pilot_log_book WHERE user_id = ? AND date >= ?";
    $stmtCount = $mysqli->prepare($countQuery);
    if (!$stmtCount) {
        throw new Exception("Prepare failed (count): (" . $mysqli->errno . ") " . $mysqli->error);
    }
    $stmtCount->bind_param("is", $user, $date);
    if (!$stmtCount->execute()) {
         throw new Exception("Execute failed (count): (" . $stmtCount->errno . ") " . $stmtCount->error);
    }
    $totalResult = $stmtCount->get_result();
    $totalRow = $totalResult->fetch_assoc();
    $total = $totalRow ? (int)$totalRow["total"] : 0; // Handle case where fetch fails
    $stmtCount->close(); // Close count statement


    // --- Calculate Pagination ---
    $perPage = 18; // Number of entries per page
    $totalPages = ($total > 0) ? ceil($total / $perPage) : 0;

    // Adjust page if initial load asks for the last page
    if ($init && $totalPages > 0) {
        $page = max(0, $totalPages - 1); // Calculate last page index (0-based)
    }

    // Ensure current page is not out of bounds after calculation
    if ($page >= $totalPages && $totalPages > 0) {
        $page = $totalPages - 1; // Set to last valid page index
    } elseif ($totalPages == 0) {
        $page = 0; // No pages, so page index is 0
    }

    $offset = $page * $perPage;


    // --- Get Paginated Results ---
    $entries = []; // Initialize entries array
    if ($total > 0) { // Only query for entries if there are any
        // Select all necessary columns, including the standardized names
        $query = "SELECT id, date, craft_type, registration, PIC, SIC, route, ifr, vfr, hours, night_time, hour_type
                  FROM pilot_log_book
                  WHERE user_id = ? AND date >= ?
                  ORDER BY date ASC, id ASC -- Add secondary sort for consistency
                  LIMIT ? OFFSET ?";

        $stmtSelect = $mysqli->prepare($query);
         if (!$stmtSelect) {
            // Log the specific SQL error for debugging
            throw new Exception("Prepare failed (select): (" . $mysqli->errno . ") " . $mysqli->error . " SQL: " . $query);
        }
        // Bind parameters: user (i), date (s), limit (i), offset (i)
        $stmtSelect->bind_param("isii", $user, $date, $perPage, $offset);
        if (!$stmtSelect->execute()) {
             throw new Exception("Execute failed (select): (" . $stmtSelect->errno . ") " . $stmtSelect->error);
        }
        $result = $stmtSelect->get_result();

        while ($row = $result->fetch_assoc()) {
            // Build the entry array using the correct keys expected by JavaScript
            $entries[] = [
                'id' => (int)$row['id'],
                'date' => $row['date'],
                'craft_type' => $row['craft_type'],
                'registration' => $row['registration'],
                'PIC' => $row['PIC'],
                'SIC' => $row['SIC'],
                'route' => $row['route'],
                'ifr' => (float)$row['ifr'],
                'vfr' => (float)$row['vfr'],
                'hours' => (float)$row['hours'],         // Total hours
                'night_time' => (float)$row['night_time'], // Night portion
                'hour_type' => $row['hour_type']       // 'day' or 'night'
            ];
        }
        $stmtSelect->close(); // Close select statement
    } // End if ($total > 0)


    // --- Success Case: Prepare and Send Response ---
    $response->setSuccess(true);
    $response->setData([
        'entries' => $entries,
        'total' => $total,               // Total number of matching records
        'page' => $page,                 // Current page index (0-based)
        'per_page' => $perPage,          // Items per page
        'total_pages' => $totalPages     // Total number of pages
    ]);
    $response->send(); // Outputs JSON and exits

} catch (Exception $e) {
    // --- Error Case: Log and Send Error Response ---
    $context = [
        'user_id' => $user ?? null, // Use null coalescing operator
        'page' => $_GET['page'] ?? null, // Log original request param
        'date' => $dateInput ?? null,  // Log original request param
        'init' => $_GET['init'] ?? null, // Log original request param
        'trace' => $e->getTraceAsString() // Include stack trace for detailed debugging
    ];
    // Use function from stats_api_response.php if available
    if (function_exists('logError')) {
         logError("Error in stats_get_pilot_statistics.php: " . $e->getMessage(), $context);
    } else {
         error_log("Error in stats_get_pilot_statistics.php: " . $e->getMessage() . " Context: " . json_encode($context));
    }

    http_response_code(500); // Internal Server Error status
    $response->setError("An error occurred while fetching statistics.");
    // Optionally include $e->getMessage() in setError FOR DEBUGGING ONLY
    // $response->setError("An error occurred: " . $e->getMessage());
    $response->setSuccess(false);
    $response->send(); // Outputs JSON and exits

} finally {
    // --- Cleanup: Close Database Connection ---
    // Ensure $mysqli is set and is a valid connection object before closing
    if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) {
         $mysqli->close();
    }
}

// No code should execute after $response->send() calls because they include exit.
?>