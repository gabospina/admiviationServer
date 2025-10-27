<?php
ob_start(); // START output buffering at the very beginning.

/**
 * File: daily_manager_user_availability_export_duty.php
 * Generates and downloads an Excel (.xlsx) file of the pilot duty schedule.
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// --- DEPENDENCIES ---
require_once 'db_connect.php';
require_once 'login_permissions.php';
require_once __DIR__ . '/vendor/autoload.php';

// Use the required classes from the PhpSpreadsheet library
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

try {
    // --- 1. Security & Validation ---
    $rolesThatCanExport = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    if (!isset($_SESSION["HeliUser"]) || !userHasRole($rolesThatCanExport, $mysqli)) {
        die("Permission Denied.");
    }
    $company_id = (int)$_SESSION['company_id'];

    $start_date_str = $_GET['start'] ?? '';
    $end_date_str = $_GET['end'] ?? '';

    $start_date = new DateTime($start_date_str);
    $end_date = new DateTime($end_date_str);

    // --- 2. Fetch Data ---
    $stmt_pilots = $mysqli->prepare("SELECT id, CONCAT(firstname, ' ', lastname) as name FROM users WHERE company_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt_pilots->bind_param("i", $company_id);
    $stmt_pilots->execute();
    $pilots = $stmt_pilots->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt_avail = $mysqli->prepare("SELECT user_id, on_date, off_date FROM user_availability WHERE company_id = ? AND on_date <= ? AND off_date >= ?");
    $stmt_avail->bind_param("iss", $company_id, $end_date_str, $start_date_str);
    $stmt_avail->execute();
    $availability_periods = $stmt_avail->get_result()->fetch_all(MYSQLI_ASSOC);

    $pilot_schedules = [];
    foreach ($availability_periods as $period) {
        $pilot_schedules[$period['user_id']][] = $period;
    }

    // --- 3. Create and Format the Spreadsheet ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Duty Schedule');

    $blue_fill = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFADD8E6']]];
    $green_fill = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF90EE90']]];
    $header_style = ['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];
    $all_borders = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

    // --- 4. Write Headers ---
    $sheet->setCellValue('A1', 'Pilot Name');
    $sheet->getColumnDimension('A')->setWidth(30);
    
    $date_period = new DatePeriod($start_date, new DateInterval('P1D'), (clone $end_date)->modify('+1 day'));
    $col_index = 2;
    $date_columns = [];
    foreach ($date_period as $date) {
        $col_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col_index);
        $sheet->setCellValue($col_letter . '1', $date->format('M d'));
        $sheet->getColumnDimension($col_letter)->setWidth(5);
        $date_columns[$date->format('Y-m-d')] = $col_letter;
        $col_index++;
    }
    $sheet->getStyle('A1:' . $col_letter . '1')->applyFromArray($header_style);

    // --- 5. Write Data and Apply Colors ---
    $row_index = 2;
    foreach ($pilots as $pilot) {
        $sheet->setCellValue('A' . $row_index, $pilot['name']);
        $pilot_id = $pilot['id'];
        $pilot_avail = $pilot_schedules[$pilot_id] ?? [];

        foreach ($date_columns as $date_str => $col_letter) {
            $current_day = new DateTime($date_str);
            $is_on_duty = false;
            foreach ($pilot_avail as $period) {
                if ($current_day >= new DateTime($period['on_date']) && $current_day <= new DateTime($period['off_date'])) {
                    $is_on_duty = true;
                    break;
                }
            }
            if ($is_on_duty) {
                $sheet->getStyle($col_letter . $row_index)->applyFromArray($blue_fill);
            } else {
                $sheet->getStyle($col_letter . $row_index)->applyFromArray($green_fill);
            }
        }
        $row_index++;
    }
    
    $sheet->getStyle('A1:' . $col_letter . ($row_index - 1))->applyFromArray($all_borders);

    // --- 6. Send File to Browser ---
    // Clean the buffer of any stray output before sending headers
    ob_clean(); 
    
    $filename = "Duty_Schedule_" . $start_date->format('Y-m-d') . "_to_" . $end_date->format('Y-m-d') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    
    ob_end_flush(); // FLUSH the clean buffer to the browser.
    
    exit;

} catch (Exception $e) {
    ob_clean(); // Clean the buffer on error too
    error_log("Excel Export Error: " . $e->getMessage());
    die("An error occurred while generating the Excel file. Please check the server logs for more details.");
}
?>