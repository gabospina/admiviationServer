<?php
// stats_generate_logbook_report.php

// --- Basic Error Reporting Setup ---
ini_set('display_errors', 0); // Don't display errors directly to user for JSON API
ini_set('log_errors', 1);     // Log errors to server's error log
// ini_set('error_log', '/path/to/your/php-error.log'); // Optionally set a specific log file
error_reporting(E_ALL);

if (session_status() == PHP_SESSION_NONE) {
    session_start();

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

}

require_once 'db_connect.php'; // For database connection
// If you have a custom ApiResponse class, include it:
// require_once 'stats_api_response.php'; 

// --- Default JSON Response Structure ---
$response = [
    'success' => false,
    'message' => 'Report generation failed due to an unexpected error.',
    'pdf_url' => null,
    'report_id' => null, // Optional: If you store generated reports in a DB table
    'debug_info' => []   // For adding debug messages
];
header('Content-Type: application/json'); // Crucial: Always send JSON header

try {
    // --- 1. Authentication & Authorization ---
    if (!isset($_SESSION["HeliUser"])) { // Use your actual session variable name
        throw new Exception("Authentication required. Please log in.", 401);
    }
    $userId = (int)$_SESSION["HeliUser"];
    $response['debug_info']['user_id_from_session'] = $userId;

    // --- 2. Receive and Validate HTTP Request & Parameters ---
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method. Only POST requests are accepted for report generation.", 405);
    }

    $print_type = $_POST['print_type'] ?? null;
    $start_date_str = $_POST['start_date'] ?? null; // Expected format: YYYY-MM-DD
    $end_date_str = $_POST['end_date'] ?? null;   // Expected format: YYYY-MM-DD

    $response['debug_info']['received_post_params'] = $_POST;

    if (empty($print_type) || empty($start_date_str) || empty($end_date_str)) {
        throw new Exception("Missing required parameters: print_type, start_date, or end_date.", 400);
    }
    if (!in_array($print_type, ['logbook', 'monthly_report'])) {
        throw new Exception("Invalid print_type specified. Must be 'logbook' or 'monthly_report'.", 400);
    }

    // Validate date formats rigorously
    $startDateObj = DateTime::createFromFormat('Y-m-d', $start_date_str);
    $endDateObj = DateTime::createFromFormat('Y-m-d', $end_date_str);

    if (!$startDateObj || $startDateObj->format('Y-m-d') !== $start_date_str) {
        throw new Exception("Invalid start_date format. Expected YYYY-MM-DD. Received: " . htmlspecialchars($start_date_str), 400);
    }
    if (!$endDateObj || $endDateObj->format('Y-m-d') !== $end_date_str) {
        throw new Exception("Invalid end_date format. Expected YYYY-MM-DD. Received: " . htmlspecialchars($end_date_str), 400);
    }
    if ($endDateObj < $startDateObj) {
        throw new Exception("End date cannot be before start date.", 400);
    }

    // --- 3. Define File Paths and Filenames ---
    $baseReportDir = 'reports/'; // Relative to this script's location for file system, and web root for URL
    $subDir = ($print_type === 'logbook') ? 'logbook/' : 'monthly_report/';
    
    // __DIR__ is the directory of THIS PHP script.
    $fullReportSaveDirAbsolute = __DIR__ . '/' . $baseReportDir . $subDir; 
    // Assumes 'reports/' directory is in the same directory as this script.
    // If 'reports/' is at the web document root, use:
    // $fullReportSaveDirAbsolute = $_SERVER['DOCUMENT_ROOT'] . '/' . $baseReportDir . $subDir;
    
    $reportUrlBaseRelative = $baseReportDir . $subDir; // Path for browser URL relative to web root

    // Ensure report directory exists and is writable
    if (!is_dir($fullReportSaveDirAbsolute)) {
        if (!mkdir($fullReportSaveDirAbsolute, 0775, true)) { // Create recursively
            error_log("Failed to create report directory: " . $fullReportSaveDirAbsolute);
            throw new Exception("Server error: Could not create report directory.", 500);
        }
    }
    if (!is_writable($fullReportSaveDirAbsolute)) {
        error_log("Report directory is not writable: " . $fullReportSaveDirAbsolute);
        throw new Exception("Server error: Report directory is not writable.", 500);
    }

    $timestamp = date('Ymd_His');
    $safe_print_type = preg_replace('/[^a-zA-Z0-9_-]/', '_', $print_type); // Sanitize for filename
    $uniqueFilenameBase = $safe_print_type . "_user" . $userId . "_" . $start_date_str . "_to_" . $end_date_str . "_" . $timestamp;
    
    $tempHtmlFilename = $uniqueFilenameBase . '.html';
    $pdfFilename = $uniqueFilenameBase . '.pdf';

    $tempHtmlPathAbsolute = rtrim($fullReportSaveDirAbsolute, '/') . '/' . $tempHtmlFilename;
    $pdfPathAbsolute = rtrim($fullReportSaveDirAbsolute, '/') . '/' . $pdfFilename;
    $pdfUrlRelative = rtrim($reportUrlBaseRelative, '/') . '/' . $pdfFilename;

    $response['debug_info']['paths_generated'] = [
        'save_dir_abs' => $fullReportSaveDirAbsolute,
        'temp_html_abs' => $tempHtmlPathAbsolute,
        'pdf_abs' => $pdfPathAbsolute,
        'pdf_url_rel' => $pdfUrlRelative
    ];

    // --- 4. Fetch Data and Generate HTML Content ---
    if (!$mysqli || $mysqli->connect_error) { // Check DB connection from db_connect.php
        error_log("Database connection error in " . basename(__FILE__) . ": " . ($mysqli->connect_error ?? "mysqli object not available"));
        throw new Exception("Database connection failed.", 500);
    }

    $reportData = [];
    // Default to Portrait for the Monthly Report
    $page_orientation_style = '@page { size: A4 portrait; }'; 
    
    // If the request is specifically for a logbook, switch to Landscape
    if ($print_type === 'logbook') {
        $page_orientation_style = '@page { size: A4 landscape; }';
    }

    $html_content_for_pdf = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>" . htmlspecialchars(ucfirst($print_type)) . " Report</title>";
    $html_content_for_pdf .= "<style> 
                            /* Set page orientation to landscape */
                            " . $page_orientation_style . " /* <-- This injects the correct orientation rule */
                            body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; } /* Slightly smaller font for more space */
                            table { width: 100%; border-collapse: collapse; margin-top: 15px; page-break-inside: auto; } 
                            tr { page-break-inside: avoid; page-break-after: auto; }
                            th, td { border: 1px solid #888; padding: 5px; text-align: left; word-wrap: break-word; } 
                            th { background-color: #e9e9e9; font-weight: bold; } 
                            h1 { text-align: center; font-size: 16pt; margin-bottom: 5px; } 
                            .period { text-align: center; font-size: 10pt; margin-bottom: 20px; } 
                         </style>";
    $html_content_for_pdf .= "</head><body>";
    $html_content_for_pdf .= "<h1>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $print_type))) . "</h1>";
    $html_content_for_pdf .= "<p class='period'>For Period: " . $startDateObj->format('M d, Y') . " to " . $endDateObj->format('M d, Y') . "</p>";

    if ($print_type === 'logbook') {
    // The data fetching query is now simpler as it doesn't need 'hour_type'
    $stmt_log = $mysqli->prepare("SELECT date, craft_type, registration, PIC, SIC, route, ifr, vfr, night_time, hours FROM pilot_log_book WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC, id ASC");
    if (!$stmt_log) throw new Exception("Prepare failed (logbook query): " . $mysqli->error, 500);
    $stmt_log->bind_param("iss", $userId, $start_date_str, $end_date_str);
    if(!$stmt_log->execute()) throw new Exception("Execute failed (logbook query): " . $stmt_log->error, 500);
    $result = $stmt_log->get_result();
    while ($row = $result->fetch_assoc()) {
        $reportData[] = $row;
    }
    $stmt_log->close();

    if (empty($reportData)) {
        $html_content_for_pdf .= "<p>No logbook entries found for this period.</p>";
    } else {
        // =========================================================================
        // === THE FIX IS HERE: Widths are now HTML attributes, not CSS classes  ===
        // =========================================================================
        $html_content_for_pdf .= "<table><thead><tr>
                                    <th width='8%'>Date</th>
                                    <th width='8%'>Craft</th>
                                    <th width='8%'>Reg</th>
                                    <th width='12%'>PIC</th>
                                    <th width='12%'>SIC</th>
                                    <th width='36%'>Route</th>
                                    <th width='4%'>IFR</th>
                                    <th width='4%'>VFR</th>
                                    <th width='4%'>Night</th>
                                    <th width='4%'>Total</th>
                                </tr></thead><tbody>";

        foreach ($reportData as $entry) {
            // The data rows (<td>) will automatically inherit the widths from the header
            // No changes are needed here, but the centering and removal of 'Type' are kept.
            $html_content_for_pdf .= "<tr>";
            $html_content_for_pdf .= "<td>" . htmlspecialchars(DateTime::createFromFormat('Y-m-d', $entry['date'])->format('M d, Y')) . "</td>";
            $html_content_for_pdf .= "<td>" . htmlspecialchars($entry['craft_type']) . "</td>";
            $html_content_for_pdf .= "<td>" . htmlspecialchars($entry['registration']) . "</td>";
            $html_content_for_pdf .= "<td>" . htmlspecialchars($entry['PIC']) . "</td>";
            $html_content_for_pdf .= "<td>" . htmlspecialchars($entry['SIC']) . "</td>";
            $html_content_for_pdf .= "<td>" . htmlspecialchars($entry['route']) . "</td>";
            $html_content_for_pdf .= "<td style='text-align:center;'>" . htmlspecialchars(number_format((float)$entry['ifr'], 1)) . "</td>";
            $html_content_for_pdf .= "<td style='text-align:center;'>" . htmlspecialchars(number_format((float)$entry['vfr'], 1)) . "</td>";
            $html_content_for_pdf .= "<td style='text-align:center;'>" . htmlspecialchars(number_format((float)$entry['night_time'], 1)) . "</td>";
            $html_content_for_pdf .= "<td style='text-align:center;'>" . htmlspecialchars(number_format((float)$entry['hours'], 1)) . "</td>";
            
            $html_content_for_pdf .= "</tr>";
        }
        $html_content_for_pdf .= "</tbody></table>";
    }
} elseif ($print_type === 'monthly_report') {
        $response['debug_info']['report_type_processing'] = 'monthly_report';
        
        // 1. Daily Flight Summary
        $stmt_daily = $mysqli->prepare("SELECT date, SUM(hours) as total_daily_hours, SUM(night_time) as total_daily_night, COUNT(id) as number_of_flights FROM pilot_log_book WHERE user_id = ? AND date BETWEEN ? AND ? GROUP BY date ORDER BY date ASC");
        if (!$stmt_daily) throw new Exception("Prepare failed (monthly_report_daily): " . $mysqli->error, 500);
        $stmt_daily->bind_param("iss", $userId, $start_date_str, $end_date_str);
        if(!$stmt_daily->execute()) throw new Exception("Execute failed (monthly_report_daily): " . $stmt_daily->error, 500);
        $result_daily = $stmt_daily->get_result();
        $daily_summary = []; $grand_total_hours_period = 0; $grand_total_night_period = 0; $grand_total_flights_period = 0;
        while ($row = $result_daily->fetch_assoc()) {
            $daily_summary[] = $row;
            $grand_total_hours_period += (float)$row['total_daily_hours'];
            $grand_total_night_period += (float)$row['total_daily_night'];
            $grand_total_flights_period += (int)$row['number_of_flights'];
        }
        $stmt_daily->close();

        // 2. Hours by Craft Type Summary
        $stmt_craft = $mysqli->prepare("SELECT craft_type, SUM(hours) as total_craft_hours, SUM(night_time) as total_craft_night, COUNT(id) as number_of_flights_craft FROM pilot_log_book WHERE user_id = ? AND date BETWEEN ? AND ? GROUP BY craft_type ORDER BY craft_type ASC");
        if (!$stmt_craft) throw new Exception("Prepare failed (monthly_report_craft): " . $mysqli->error, 500);
        $stmt_craft->bind_param("iss", $userId, $start_date_str, $end_date_str);
        if(!$stmt_craft->execute()) throw new Exception("Execute failed (monthly_report_craft): " . $stmt_craft->error, 500);
        $result_craft = $stmt_craft->get_result();
        $craft_summary = [];
        while ($row = $result_craft->fetch_assoc()) { $craft_summary[] = $row; }
        $stmt_craft->close();

        // --- HTML Generation with Centered Columns ---
        if (empty($daily_summary) && empty($craft_summary)) {
        $html_content_for_pdf .= "<p>No data found for this monthly report period.</p>";
    } else {
        if (!empty($daily_summary)) {
            // === THE FIX IS HERE: Centered headers for numeric columns ===
            $html_content_for_pdf .= "<h3>Daily Flight Summary</h3><table><thead><tr><th>Date</th><th style='text-align:center;'>Flights</th><th style='text-align:center;'>Total Hours</th><th style='text-align:center;'>Night Hours</th></tr></thead><tbody>";
            foreach ($daily_summary as $day) {
                // === THE FIX IS HERE: Changed text-align from 'right' to 'center' for data cells ===
                $html_content_for_pdf .= "<tr><td>" . htmlspecialchars(DateTime::createFromFormat('Y-m-d', $day['date'])->format('M d, Y')) . "</td><td style='text-align:center;'>" . htmlspecialchars($day['number_of_flights']) . "</td><td style='text-align:center;'>" . htmlspecialchars(number_format((float)$day['total_daily_hours'], 1)) . "</td><td style='text-align:center;'>" . htmlspecialchars(number_format((float)$day['total_daily_night'], 1)) . "</td></tr>";
            }
            // === THE FIX IS HERE: Centered grand totals in the footer row ===
            $html_content_for_pdf .= "<tr style='font-weight:bold; background-color:#eee;'><td><strong>Total for Period</strong></td><td style='text-align:center;'><strong>" . htmlspecialchars($grand_total_flights_period) . "</strong></td><td style='text-align:center;'><strong>" . htmlspecialchars(number_format($grand_total_hours_period, 1)) . "</strong></td><td style='text-align:center;'><strong>" . htmlspecialchars(number_format($grand_total_night_period, 1)) . "</strong></td></tr>";
            $html_content_for_pdf .= "</tbody></table><br><br>";
        }
        if (!empty($craft_summary)) {
            // === THE FIX IS HERE: Centered headers for numeric columns ===
            $html_content_for_pdf .= "<h3>Hours by Craft Type</h3><table><thead><tr><th>Craft Type</th><th style='text-align:center;'>Flights</th><th style='text-align:center;'>Total Hours</th><th style='text-align:center;'>Night Hours</th></tr></thead><tbody>";
            foreach ($craft_summary as $craft) {
                // === THE FIX IS HERE: Changed text-align from 'right' to 'center' for data cells ===
                $html_content_for_pdf .= "<tr><td>" . htmlspecialchars($craft['craft_type']) . "</td><td style='text-align:center;'>" . htmlspecialchars($craft['number_of_flights_craft']) . "</td><td style='text-align:center;'>" . htmlspecialchars(number_format((float)$craft['total_craft_hours'], 1)) . "</td><td style='text-align:center;'>" . htmlspecialchars(number_format((float)$craft['total_craft_night'], 1)) . "</td></tr>";
            }
            $html_content_for_pdf .= "</tbody></table>";
        }
    }
}
    $html_content_for_pdf .= "</body></html>";
    $response['debug_info']['html_generation_complete'] = "HTML content assembled.";

    // --- 5. Save HTML to Temporary File (or directly in the reports folder if preferred) ---
    if (file_put_contents($tempHtmlPathAbsolute, $html_content_for_pdf) === false) {
        throw new Exception("Failed to write temporary HTML file for PDF conversion at " . $tempHtmlPathAbsolute, 500);
    }
    $response['debug_info']['temp_html_file_written_to'] = $tempHtmlPathAbsolute;

    // --- 6. Convert HTML to PDF using LibreOffice ---
    // *** CRITICAL: ADJUST THIS PATH TO YOUR SERVER'S LIBREOFFICE INSTALLATION ***
    $sofficePath = '"C:\\Program Files\\LibreOffice\\program\\soffice.exe"'; // WINDOWS EXAMPLE
    // $sofficePath = '/usr/bin/libreoffice'; // Typical LINUX EXAMPLE if in PATH
    // $sofficePath = '/opt/libreoffice7.6/program/soffice'; // Example specific Linux install path

    $outputDirForPdfAbsolute = dirname($pdfPathAbsolute);

    // The command needs absolute paths for input and output directory
    $command = $sofficePath .
               " --headless --nolockcheck --norestore" .
               " --convert-to pdf" .
            //    " --convert-to pdf:\"writer_pdf_Export\"" . // Specific filter if needed for better HTML interpretation
               " --outdir " . escapeshellarg($outputDirForPdfAbsolute) . // Directory where PDF will be created
               " " . escapeshellarg($tempHtmlPathAbsolute);              // Input HTML file

    $response['debug_info']['libreoffice_command_to_execute'] = $command;
    
    $cmd_shell_output = []; // To capture output from exec
    $return_var = -1;     // To capture return status of exec
    exec($command . " 2>&1", $cmd_shell_output, $return_var); // Execute and capture stderr

    $response['debug_info']['libreoffice_execution_return_var'] = $return_var;
    $response['debug_info']['libreoffice_execution_output'] = implode("\n", $cmd_shell_output);

    // --- 7. Check PDF Creation & Clean Up Temporary HTML File ---
    clearstatcache(); // Important before file_exists after exec
    
    if ($return_var === 0 && file_exists($pdfPathAbsolute) && filesize($pdfPathAbsolute) > 0) { // Check if file exists AND is not empty
        $response['success'] = true;
        $response['message'] = ucfirst(str_replace('_', ' ', $print_type)) . " PDF generated successfully.";
        $response['pdf_url'] = $pdfUrlRelative;
        error_log(ucfirst(str_replace('_', ' ', $print_type)) . " PDF generated and saved to: " . $pdfPathAbsolute);
    } else {
        error_log("LibreOffice PDF conversion FAILED. Return code: $return_var. Output: " . implode(" | ", $cmd_shell_output) . ". Expected PDF at: " . $pdfPathAbsolute);
        // Provide more specific error if PDF exists but is empty
        if (file_exists($pdfPathAbsolute) && filesize($pdfPathAbsolute) === 0) {
             throw new Exception("LibreOffice PDF conversion resulted in an empty file. Check HTML content and LibreOffice logs.", 500);
        }
        throw new Exception("LibreOffice PDF conversion failed. Check server logs and LibreOffice installation. Return code: $return_var.", 500);
    }

    // Always delete temporary HTML file
    if (file_exists($tempHtmlPathAbsolute)) {
        unlink($tempHtmlPathAbsolute);
    }

    // --- (Optional) 8. Store Record in Database ---
    // ... (your logic to insert into a 'generated_reports' table if needed) ...

} catch (Exception $e) {
    // Log detailed error
    error_log("Exception in " . basename(__FILE__) . " on line " . $e->getLine() . ": " . $e->getMessage());
    // error_log("POST data for error: " . print_r($_POST, true)); // Already logged or in debug
    // error_log("Exception Trace: " . $e->getTraceAsString()); // For very detailed debugging

    $response['success'] = false;
    $response['message'] = "Server Error: " . htmlspecialchars($e->getMessage());
    // Set appropriate HTTP status code based on exception code or default to 500 for server errors
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);

    // Clean up temporary HTML file if it was created and an exception occurred later
    if (isset($tempHtmlPathAbsolute) && file_exists($tempHtmlPathAbsolute)) {
        @unlink($tempHtmlPathAbsolute); // Suppress error if unlink fails
    }
} finally {
    // Close database connection if it was opened and is valid
    if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->thread_id) {
        $mysqli->close();
    }
}

// --- 9. Return JSON Response to JavaScript ---
echo json_encode($response);
exit;
?>