<?php
// print.php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
// Include DB connection, authentication checks, etc.

// Get parameters
$print_type = $_GET['print_type'] ?? null; // 'monthly_report' or 'logbook'
$start_date_str = $_GET['start'] ?? null;   // Expected YYYY-MM-DD
$end_date_str = $_GET['end'] ?? null;     // Expected YYYY-MM-DD
$output_format = $_GET['output'] ?? 'html'; // Default to html, expect 'pdf'

if (!$print_type || !$start_date_str || !$end_date_str) {
    die("Missing required parameters.");
}

// Validate dates (basic validation)
$start_date = DateTime::createFromFormat('Y-m-d', $start_date_str);
$end_date = DateTime::createFromFormat('Y-m-d', $end_date_str);

if (!$start_date || $start_date->format('Y-m-d') !== $start_date_str || 
    !$end_date || $end_date->format('Y-m-d') !== $end_date_str) {
    die("Invalid date format. Use YYYY-MM-DD.");
}

// --- Fetch Data from Database based on $print_type, $start_date, $end_date ---
$data_for_report = []; // Placeholder
if ($print_type === 'monthly_report') {
    // SQL to fetch monthly report data between $start_date_str and $end_date_str
    // Example:
    // $stmt = $mysqli->prepare("SELECT ... FROM ... WHERE date_column BETWEEN ? AND ? AND user_id = ?");
    // $stmt->bind_param("ssi", $start_date_str, $end_date_str, $_SESSION['HeliUser']);
    // $stmt->execute();
    // $result = $stmt->get_result();
    // while ($row = $result->fetch_assoc()) { $data_for_report[] = $row; }
    // $stmt->close();
} elseif ($print_type === 'logbook') {
    // SQL to fetch logbook entries between $start_date_str and $end_date_str
    // Example:
    // $stmt = $mysqli->prepare("SELECT * FROM pilot_log_book WHERE date BETWEEN ? AND ? AND user_id = ? ORDER BY date ASC");
    // $stmt->bind_param("ssi", $start_date_str, $end_date_str, $_SESSION['HeliUser']);
    // ... fetch data into $data_for_report ...
} else {
    die("Invalid print type.");
}

// --- Generate Report Content (e.g., HTML) ---
$html_content = "<html><head><title>" . ucfirst($print_type) . "</title>";
// Add CSS styles for PDF here or link to a CSS file accessible by the server
$html_content .= "<style> table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid black; padding: 5px; text-align: left; } </style>";
$html_content .= "</head><body>";
$html_content .= "<h1>" . ucfirst(str_replace('_', ' ', $print_type)) . "</h1>";
$html_content .= "<p>Period: " . $start_date->format('M d, Y') . " to " . $end_date->format('M d, Y') . "</p>";

// Example table generation (customize heavily based on your data)
if (!empty($data_for_report)) {
    $html_content .= "<table><thead><tr>";
    // Assuming $data_for_report is an array of associative arrays
    foreach (array_keys($data_for_report[0]) as $header) {
        $html_content .= "<th>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $header))) . "</th>";
    }
    $html_content .= "</tr></thead><tbody>";
    foreach ($data_for_report as $row) {
        $html_content .= "<tr>";
        foreach ($row as $value) {
            $html_content .= "<td>" . htmlspecialchars($value) . "</td>";
        }
        $html_content .= "</tr>";
    }
    $html_content .= "</tbody></table>";
} else {
    $html_content .= "<p>No data found for the selected period.</p>";
}
$html_content .= "</body></html>";


// --- Output as PDF if requested ---
if ($output_format === 'pdf') {
    // ** OPTION 1: Using a PHP PDF Library (e.g., Dompdf - install via Composer) **
    /*
    require_once 'vendor/autoload.php'; // If using Composer
    use Dompdf\Dompdf;
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html_content);
    $dompdf->setPaper('A4', 'landscape'); // or 'portrait'
    $dompdf->render();
    // Output the generated PDF to Browser
    // Use 'true' for $attachment to force download, 'false' to display inline
    $filename = str_replace(' ', '_', $print_type) . "_" . $start_date_str . "_to_" . $end_date_str . ".pdf";
    $dompdf->stream($filename, ["Attachment" => false]); 
    exit;
    */

    // ** OPTION 2: Using LibreOffice Command Line (Requires LibreOffice on server & shell_exec permissions) **
    // This is more complex and has security implications.
    // 1. Save $html_content to a temporary HTML file
    $tmp_html_file = tempnam(sys_get_temp_dir(), 'report_') . '.html';
    file_put_contents($tmp_html_file, $html_content);

    // 2. Define output PDF file path
    $tmp_pdf_file = str_replace('.html', '.pdf', $tmp_html_file);

    // 3. Construct LibreOffice command (paths may vary)
    // soffice might be libreoffice on some systems. Use --headless for no GUI.
    // Ensure the web server user (e.g., www-data, apache) has permission to execute this.
    $command = "soffice --headless --convert-to pdf --outdir " . escapeshellarg(sys_get_temp_dir()) . " " . escapeshellarg($tmp_html_file);
    
    // For debugging the command: error_log("LibreOffice Command: " . $command);
    
    shell_exec($command); // Execute the conversion

    if (file_exists($tmp_pdf_file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($tmp_pdf_file) . '"'); // Or attachment
        header('Content-Length: ' . filesize($tmp_pdf_file));
        readfile($tmp_pdf_file);
        unlink($tmp_html_file); // Clean up temp files
        unlink($tmp_pdf_file);
        exit;
    } else {
        unlink($tmp_html_file); // Clean up HTML file even if PDF failed
        // error_log("LibreOffice PDF conversion failed. Command: " . $command);
        die("Error: Could not generate PDF. HTML file: $tmp_html_file. PDF Output expected at: $tmp_pdf_file");
    }

} else {
    // Default to HTML output if not PDF
    echo $html_content;
}
?>