<?php
// schedule_get_existing_assignments.php (FINAL, CORRECTED VERSION)
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

include_once "db_connect.php";
require_once 'date_utils.php'; // Ensure this file exists

$response = ['success' => false, 'assignments' => []];

try {
    if (!isset($_SESSION['company_id'])) throw new Exception('Authentication required');
    $company_id = (int)$_SESSION['company_id'];
    if ($company_id <= 0) throw new Exception('Invalid company ID');

    $monday = new DateTime('Monday this week');
    $sunday = new DateTime('Sunday this week');

    if (!empty($_GET['start_date'])) { try { $monday = new DateTime($_GET['start_date']); } catch (Exception $e) {} }
    if (!empty($_GET['end_date'])) { try { $sunday = new DateTime($_GET['end_date']); } catch (Exception $e) {} }
    
    $sunday->setTime(23, 59, 59);

    // =========================================================================
    // === FINAL QUERY WITH CORRECT JOIN AND DISTINCT PLACEMENT            ===
    // =========================================================================
    $query = "SELECT DISTINCT
                s.id as schedule_id,
                u.id as user_id,
                u.company_id as pilot_company_id, -- Moved company_id inside DISTINCT block
                s.craft_type,
                CASE
                    WHEN s.pos = 'com' THEN 'PIC'
                    WHEN s.pos = 'pil' THEN 'SIC'
                    ELSE s.pos
                END as position,
                s.sched_date,
                s.registration,
                CASE 
                    WHEN DAYOFWEEK(s.sched_date) = 1 THEN 6
                    ELSE DAYOFWEEK(s.sched_date)-2
                END as day_index,
                c.id as craft_id,
                CONCAT(LEFT(u.firstname, 1), '. ', u.lastname) as pilot_display_name
                
                FROM schedule s
                -- More robust JOIN condition to help prevent duplicates
                JOIN crafts c ON s.registration = c.registration AND s.company_id = c.company_id
                JOIN users u ON s.user_id = u.id
                
                WHERE s.sched_date BETWEEN ? AND ?
                AND s.company_id = ?";
    
    $user_filter = false;
    $user_id_filter = 0;
    if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
        $user_id_filter = (int)$_GET['user_id'];
        if ($user_id_filter > 0) {
            $query .= " AND s.user_id = ?";
            $user_filter = true;
        }
    }

    // REMOVED the problematic str_replace
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) throw new Exception('Database preparation failed: ' . $mysqli->error);

    $start_date = $monday->format('Y-m-d');
    $end_date = $sunday->format('Y-m-d H:i:s'); 

    if ($user_filter) {
        $stmt->bind_param("ssii", $start_date, $end_date, $company_id, $user_id_filter);
    } else {
        $stmt->bind_param("ssi", $start_date, $end_date, $company_id);
    }

    if (!$stmt->execute()) throw new Exception('Database execution failed: ' . $mysqli->error);

    $result = $stmt->get_result();
    $response['assignments'] = $result->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;

} catch (Exception $e) {
    error_log("Schedule Error: " . $e->getMessage());
    $response['error'] = 'Failed to load assignments: ' . $e->getMessage();
}

header_remove('X-Powered-By');
exit(json_encode($response));
?>