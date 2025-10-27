<?php
// print_experience.php

// --- Setup & Security ---
if (session_status() == PHP_SESSION_NONE) { session_start(); }

require_once 'db_connect.php'; // Ensure path is correct and $mysqli is available

// Authentication check
if (!isset($_SESSION["HeliUser"])) {
    // For a print page, dying is okay. For an API, JSON is better.
    header("Content-Type: text/html; charset=utf-8"); // Set content type before die
    die("<h1>Access Denied</h1><p>Authentication required. Please log in.</p><script>setTimeout(function(){ window.close(); }, 3000);</script>");
}
$userId = (int)$_SESSION["HeliUser"];
$company_id_from_session = isset($_SESSION['company_id']) && filter_var($_SESSION['company_id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])
    ? (int)$_SESSION['company_id']
    : null;

// --- Fetch User and Company Info (for display) ---
$userName = $_SESSION["username"] ?? ($_SESSION["name"] ?? 'N/A'); // Use a display name from session
$companyName = 'N/A';

// Fetch company name if company_id is available
global $mysqli; // Assuming $mysqli is made global by db_connect.php
if ($company_id_from_session && $mysqli && !$mysqli->connect_error) {
    $stmt_comp = $mysqli->prepare("SELECT company_name FROM companies WHERE id = ? LIMIT 1");
    if ($stmt_comp) {
        $stmt_comp->bind_param("i", $company_id_from_session);
        if ($stmt_comp->execute()) {
            $result_comp = $stmt_comp->get_result();
            if ($row_comp = $result_comp->fetch_assoc()) {
                if (!empty($row_comp['company_name'])) {
                    $companyName = $row_comp['company_name'];
                }
            }
            if ($result_comp) $result_comp->free();
        }
        $stmt_comp->close();
    }
}

// --- Fetch Combined Craft Experience Data ---
// This logic should mirror stats_get_craft_experience.php
$finalCombinedExperience = [];

if ($mysqli && !$mysqli->connect_error) {
    error_log("[Print Experience] Starting data fetch for User ID: $userId, Company ID: " . ($company_id_from_session ?? 'N/A'));

    // 1. Fetch Detailed Initial Experience
    if ($company_id_from_session) { // Only if company_id is valid for initial experience
        $sql_initial = "SELECT 
                            craft_type, 
                            initial_pic_ifr_hours, initial_pic_vfr_hours, initial_pic_night_hours,
                            initial_sic_ifr_hours, initial_sic_vfr_hours, initial_sic_night_hours
                        FROM pilot_initial_experience 
                        WHERE user_id = ? AND company_id = ?";
        $stmt_initial = $mysqli->prepare($sql_initial);
        if ($stmt_initial) {
            $stmt_initial->bind_param("ii", $userId, $company_id_from_session);
            if ($stmt_initial->execute()) {
                $result_initial = $stmt_initial->get_result();
                while ($row = $result_initial->fetch_assoc()) {
                    $craft = trim($row['craft_type']); if (empty($craft)) continue;
                    if (!isset($finalCombinedExperience[$craft])) {
                         $finalCombinedExperience[$craft] = ['PIC' => 0.0, 'SIC' => 0.0, 'IFR' => 0.0, 'VFR' => 0.0, 'Night' => 0.0, 'Total' => 0.0];
                    }
                    $init_pic_ifr   = (float)($row['initial_pic_ifr_hours'] ?? 0.0);
                    $init_pic_vfr   = (float)($row['initial_pic_vfr_hours'] ?? 0.0);
                    $init_pic_night = (float)($row['initial_pic_night_hours'] ?? 0.0);
                    $init_sic_ifr   = (float)($row['initial_sic_ifr_hours'] ?? 0.0);
                    $init_sic_vfr   = (float)($row['initial_sic_vfr_hours'] ?? 0.0);
                    $init_sic_night = (float)($row['initial_sic_night_hours'] ?? 0.0);

                    $finalCombinedExperience[$craft]['PIC']   += $init_pic_ifr + $init_pic_vfr;
                    $finalCombinedExperience[$craft]['SIC']   += $init_sic_ifr + $init_sic_vfr;
                    $finalCombinedExperience[$craft]['IFR']   += $init_pic_ifr + $init_sic_ifr;
                    $finalCombinedExperience[$craft]['VFR']   += $init_pic_vfr + $init_sic_vfr;
                    $finalCombinedExperience[$craft]['Night'] += $init_pic_night + $init_sic_night;
                }
                if ($result_initial) $result_initial->free();
            } else { error_log("[Print Experience] Execute failed (initial_experience): " . $stmt_initial->error); }
            $stmt_initial->close();
        } else { error_log("[Print Experience] Prepare failed (initial_experience): " . $mysqli->error); }
    } else {
        error_log("[Print Experience] Skipping initial experience fetch due to missing/invalid company_id.");
    }
    error_log("[Print Experience] After Initial Experience: " . print_r($finalCombinedExperience, true));


    // 2. Fetch and Add Logged Experience (using pic_user_id, sic_user_id)
    $sql_logbook = "SELECT 
                        craft_type, 
                        SUM(CASE WHEN pic_user_id = ? THEN hours ELSE 0 END) AS logged_pic_hours, 
                        SUM(CASE WHEN sic_user_id = ? THEN hours ELSE 0 END) AS logged_sic_hours,  
                        SUM(CASE WHEN pic_user_id = ? OR sic_user_id = ? THEN ifr ELSE 0 END) as logged_ifr_hours,         
                        SUM(CASE WHEN pic_user_id = ? OR sic_user_id = ? THEN vfr ELSE 0 END) as logged_vfr_hours,         
                        SUM(CASE WHEN pic_user_id = ? OR sic_user_id = ? THEN night_time ELSE 0 END) as logged_night_hours
                    FROM pilot_log_book 
                    WHERE (pic_user_id = ? OR sic_user_id = ?)
                      AND craft_type IS NOT NULL AND craft_type != ''
                    GROUP BY craft_type";
    $stmt_logbook = $mysqli->prepare($sql_logbook);
    if ($stmt_logbook) {
        $stmt_logbook->bind_param("iiiiiiiiii", 
            $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId
        );
        if ($stmt_logbook->execute()) {
            $result_logbook = $stmt_logbook->get_result();
            while ($row = $result_logbook->fetch_assoc()) {
                $craft = trim($row['craft_type']); if (empty($craft)) continue;
                if (!isset($finalCombinedExperience[$craft])) {
                     $finalCombinedExperience[$craft] = ['PIC' => 0.0, 'SIC' => 0.0, 'IFR' => 0.0, 'VFR' => 0.0, 'Night' => 0.0, 'Total' => 0.0];
                }
                $finalCombinedExperience[$craft]['PIC']   += (float)($row['logged_pic_hours'] ?? 0.0);
                $finalCombinedExperience[$craft]['SIC']   += (float)($row['logged_sic_hours'] ?? 0.0);
                $finalCombinedExperience[$craft]['IFR']   += (float)($row['logged_ifr_hours'] ?? 0.0);
                $finalCombinedExperience[$craft]['VFR']   += (float)($row['logged_vfr_hours'] ?? 0.0);
                $finalCombinedExperience[$craft]['Night'] += (float)($row['logged_night_hours'] ?? 0.0);
            }
            if ($result_logbook) $result_logbook->free();
        } else { error_log("[Print Experience] Execute failed (logbook_sum): ".$stmt_logbook->error); }
        $stmt_logbook->close();
    } else { error_log("[Print Experience] Prepare failed (logbook_sum): ".$mysqli->error); }
    error_log("[Print Experience] After Logged Experience: " . print_r($finalCombinedExperience, true));


    // 3. Calculate Final 'Total' (PIC + SIC) for each craft
    foreach ($finalCombinedExperience as $craft => &$data) {
        $data['Total'] = $data['PIC'] + $data['SIC'];
    }
    unset($data); // Break reference

    ksort($finalCombinedExperience); // Sort by craft type

    error_log("[Print Experience] Final Combined Data: " . print_r($finalCombinedExperience, true));

    // Don't close $mysqli here if it's managed globally
} else {
    error_log("[Print Experience] Main DB connection error at start of data fetch.");
    // $finalCombinedExperience remains empty, will show "No data" message in HTML
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Craft Experience Report - <?php echo htmlspecialchars($userName); ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; margin: 20px; color: #333; }
        .report-header { text-align: center; margin-bottom: 30px; }
        .report-header h1 { margin: 0; font-size: 18pt; color: #0056b3; }
        .report-header h2 { margin: 5px 0 0 0; font-size: 12pt; font-weight: normal; color: #555; }
        .info-bar { font-size: 9pt; color: #777; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .info-bar span { margin-right: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; box-shadow: 0 0 5px rgba(0,0,0,0.1); }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 9pt; }
        th { background-color: #007bff; color: white; font-weight: bold; text-align: center; }
        td.text-right { text-align: right; }
        .totals-row td { font-weight: bold; background-color: #f0f8ff; border-top: 2px solid #007bff; }
        .no-data { text-align: center; font-style: italic; color: #888; margin-top: 30px; }
        @media print {
            body { margin: 0.5in; font-size: 9pt; color: #000; } /* Ensure black text for print */
            .report-header h1 { font-size: 16pt; }
            .report-header h2 { font-size: 11pt; }
            .info-bar { display: none; } /* Optional: hide generated on date for formal print */
            table { box-shadow: none; }
            th { background-color: #e9ecef !important; color: #212529 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } /* Lighter for print, ensure colors print */
            .totals-row td { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            /* Optional: Remove Bootstrap button if it was part of a print preview page */
            .no-print-on-page { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="report-header">
        <h1>Pilot Experience Summary by Aircraft</h1>
        <h2><?php echo htmlspecialchars($userName); ?></h2>
    </div>

    <div class="info-bar">
        <span><strong>Company:</strong> <?php echo htmlspecialchars($companyName); ?></span>
        <span><strong>Report Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></span>
    </div>


    <?php if (!empty($finalCombinedExperience)): ?>
        <table>
            <thead>
                <tr>
                    <th>Aircraft Type</th>
                    <th class="text-right">PIC Hours</th>
                    <th class="text-right">SIC Hours</th>
                    <th class="text-right">IFR Hours</th>
                    <th class="text-right">VFR Hours</th>
                    <th class="text-right">Night Hours</th>
                    <th class="text-right">Total Hours</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grandTotalPIC = 0; $grandTotalSIC = 0; $grandTotalIFR = 0;
                $grandTotalVFR = 0; $grandTotalNight = 0; $grandTotalOverall = 0;

                foreach ($finalCombinedExperience as $craft => $data):
                    $pic   = (float)($data['PIC'] ?? 0.0);
                    $sic   = (float)($data['SIC'] ?? 0.0);
                    $ifr   = (float)($data['IFR'] ?? 0.0);
                    $vfr   = (float)($data['VFR'] ?? 0.0);
                    $night = (float)($data['Night'] ?? 0.0);
                    $total = (float)($data['Total'] ?? 0.0); // This is PIC + SIC

                    $grandTotalPIC += $pic;
                    $grandTotalSIC += $sic;
                    $grandTotalIFR += $ifr;
                    $grandTotalVFR += $vfr;
                    $grandTotalNight += $night;
                    $grandTotalOverall += $total;
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($craft); ?></td>
                        <td class="text-right"><?php echo number_format($pic, 1); ?></td>
                        <td class="text-right"><?php echo number_format($sic, 1); ?></td>
                        <td class="text-right"><?php echo number_format($ifr, 1); ?></td>
                        <td class="text-right"><?php echo number_format($vfr, 1); ?></td>
                        <td class="text-right"><?php echo number_format($night, 1); ?></td>
                        <td class="text-right"><?php echo number_format($total, 1); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if (count($finalCombinedExperience) > 0): // Show tfoot only if there's data ?>
            <tfoot>
                <tr class="totals-row">
                    <td><strong>Grand Totals</strong></td>
                    <td class="text-right"><?php echo number_format($grandTotalPIC, 1); ?></td>
                    <td class="text-right"><?php echo number_format($grandTotalSIC, 1); ?></td>
                    <td class="text-right"><?php echo number_format($grandTotalIFR, 1); ?></td>
                    <td class="text-right"><?php echo number_format($grandTotalVFR, 1); ?></td>
                    <td class="text-right"><?php echo number_format($grandTotalNight, 1); ?></td>
                    <td class="text-right"><?php echo number_format($grandTotalOverall, 1); ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    <?php else: ?>
        <p class="no-data">No craft experience data found for <?php echo htmlspecialchars($userName); ?>.</p>
    <?php endif; ?>

    <script type="text/javascript">
        // Automatically trigger print dialog once the page is fully loaded
        window.onload = function() {
            // Optional: small delay to ensure content is rendered, especially complex tables
            // setTimeout(function() {
                 window.print();
                 // Optional: close the window after print dialog is actioned (or cancelled)
                 // This can be problematic as it might close before printing starts for some browsers.
                 // setTimeout(function() { window.close(); }, 1000);
            // }, 500);
        };
    </script>

</body>
</html>