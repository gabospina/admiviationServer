<?php
// flight-tracker/tracker_today_flights.php (FINAL - WITH BULLETPROOF PILOT ORDERING)

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once '../db_connect.php';

if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}
$company_id = (int)$_SESSION['company_id'];

$todays_flights = [];

try {
    // --- THIS IS THE BULLETPROOF QUERY WITH GUARANTEED PILOT ORDERING ---
    $sql = "SELECT 
                s.sched_date,
                s.registration,
                s.craft_type,
                -- THE FIX IS HERE: Made the ORDER BY case-insensitive and whitespace-proof
                GROUP_CONCAT(
                    CONCAT(u.lastname, ', ', u.firstname, ' (', s.pos, ')')
                    ORDER BY 
                        CASE UPPER(TRIM(s.pos)) -- Force to uppercase and remove spaces
                            WHEN 'PIC' THEN 1
                            WHEN 'SIC' THEN 2
                            ELSE 3
                        END
                    SEPARATOR ' - '
                ) as pilots_list,
                MIN(s.id) as flight_id 
            FROM schedule s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.company_id = ?
              AND s.sched_date = CURDATE()
            GROUP BY 
                s.sched_date, s.registration, s.craft_type
            ORDER BY 
                registration ASC";
              
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $todays_flights[] = [
            'flight_id' => (int)$row['flight_id'],
            'craft' => "{$row['craft_type']} ({$row['registration']})",
            'pilot_name' => $row['pilots_list']
        ];
    }
    $stmt->close();
    
    echo json_encode($todays_flights);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
