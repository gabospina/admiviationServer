<?php
// stats_get_stats_graph.php - FINAL VERSION WITH LEGEND LABELS

ini_set('display_errors', 1); error_reporting(E_ALL);
require_once "db_connect.php";
require_once "stats_api_response.php";
require_once "stats_pilot_max_times.php";

// Helper function to get the correct ordinal suffix (1st, 2nd, 3rd, 4th)
function getOrdinalSuffix($number) {
    if (!in_array(($number % 100), [11, 12, 13])) {
        switch ($number % 10) {
            case 1: return $number . 'st';
            case 2: return $number . 'nd';
            case 3: return $number . 'rd';
        }
    }
    return $number . 'th';
}

if (session_status() == PHP_SESSION_NONE) { session_start(); }
$response = new ApiResponse();

// Standard session and parameter validation
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
    http_response_code(401); $response->setError("Authentication required")->send();
}
$user_id = (int)$_SESSION["HeliUser"];
$company_id = (int)$_SESSION["company_id"];

$type = $_GET["type"] ?? null;
$startInput = $_GET["start"] ?? null;
$startDateObj = $startInput ? DateTime::createFromFormat('Y-m-d', $startInput) : false;
if (empty($type) || !$startDateObj) {
    http_response_code(400); $response->setError("Invalid or missing parameters.")->send();
}
$start_date_sql = $startDateObj->format('Y-m-d');

if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500); $response->setError("Database connection failed.")->send();
}

$limits = getPilotLimits($mysqli, $company_id);
$daily_flight_limit = (float)($limits['max_in_day'] ?? 12.0);

$stmt = null;
try {
    // ... (Date range logic remains the same) ...
    $endDateObj = null;
    $queryStartDateObj = clone $startDateObj;
    switch ($type) {
        case "past7": $endDateObj = (clone $startDateObj)->modify('+1 day'); $queryStartDateObj->modify('-13 days'); $start_date_sql = (clone $startDateObj)->modify('-6 days')->format('Y-m-d'); break;
        case "past28": $endDateObj = (clone $startDateObj)->modify('+1 day'); $queryStartDateObj->modify('-55 days'); $start_date_sql = (clone $startDateObj)->modify('-27 days')->format('Y-m-d'); break;
        case "month": $endDateObj = (clone $startDateObj)->modify('first day of next month'); $queryStartDateObj->modify("first day of this month")->modify("-27 days"); break;
        case "year": $endDateObj = (clone $startDateObj)->modify('+1 year'); $queryStartDateObj->modify("-364 days"); break;
        default: throw new Exception("Invalid type parameter specified.");
    }
    $query_start_date_sql = $queryStartDateObj->format('Y-m-d');
    $end_date_sql = $endDateObj->format('Y-m-d');

    // ... (Database query logic remains the same) ...
    $sql = "SELECT id, date, hours, registration FROM pilot_log_book WHERE user_id = ? AND date >= ? AND date < ? ORDER BY date ASC, id ASC";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("iss", $user_id, $query_start_date_sql, $end_date_sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $flights_by_day = [];
    while ($row = $result->fetch_assoc()) {
        $flights_by_day[$row['date']][] = ['h' => (float)$row['hours'], 'r' => $row['registration']];
    }
    
    $allDailyTotals = [];
    foreach ($flights_by_day as $date => $flights) {
        $allDailyTotals[$date] = array_sum(array_column($flights, 'h'));
    }

    $currentDateObj = DateTime::createFromFormat('Y-m-d', $start_date_sql);
    $displayEndDateObj = $endDateObj;
    $totalHoursPeriod = 0.0;
    $finalDataPackage = null;
    $graphData = [];
    $hover_data = [];

    if ($type == 'year') {
        // Correct logic for the Year view (non-stacked)
        $monthlyTotals = array_fill(0, 12, 0.0);
        foreach ($allDailyTotals as $date => $total) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $date);
            if ($dateObj >= $startDateObj && $dateObj < $endDateObj) {
                $monthIndex = (int)$dateObj->format('n') - 1;
                $monthlyTotals[$monthIndex] += $total;
            }
        }
        
        for ($m = 0; $m < 12; $m++) {
            $monthName = date("F", mktime(0, 0, 0, $m + 1, 1));
            $hours = $monthlyTotals[$m];
            $totalHoursPeriod += $hours;
            $graphData[] = ["data" => [[$monthName, round($hours, 1)]], "color" => "#3498db"];
            $hover_data[$monthName] = ['total' => round($hours, 1)];
        }
    } else {
        $colors = ["#3498db", "#5dade2", "#85c1e9", "#aed6f1"];
        $max_flights_per_day = 0;
        if (!empty($flights_by_day)) {
            $max_flights_per_day = max(array_map('count', $flights_by_day));
        }

        $series_data = [];
        for ($i = 0; $i < $max_flights_per_day; $i++) {
            $series_data[$i] = [];
        }

        while ($currentDateObj < $displayEndDateObj) {
            $currentDateStr = $currentDateObj->format('Y-m-d');
            $daily_total = $allDailyTotals[$currentDateStr] ?? 0.0;
            $totalHoursPeriod += $daily_total;
            $flights_today = $flights_by_day[$currentDateStr] ?? [];
            $xLabel = $currentDateObj->format('M jS');

            for ($i = 0; $i < $max_flights_per_day; $i++) {
                $flight_hours = isset($flights_today[$i]) ? $flights_today[$i]['h'] : 0;
                $series_data[$i][] = [$xLabel, round($flight_hours, 1)];
            }
            
            $hover_data[$xLabel] = [
                'total' => round($daily_total, 1),
                'flights' => $flights_today,
                'isOverDaily' => ($daily_total > $daily_flight_limit),
            ];
            $currentDateObj->modify('+1 day');
        }

        // === FIX 3: Add a 'label' to each series for the legend ===
        for ($i = 0; $i < $max_flights_per_day; $i++) {
            $flightNumber = $i + 1;
            $flightLabel = getOrdinalSuffix($flightNumber) . " Flight";
            $graphData[] = [
                'data' => $series_data[$i],
                'color' => $colors[$i % count($colors)],
                'label' => $flightLabel // This is the new line for the legend
            ];
        }
    }

    $finalDataPackage = [
        'total' => round($totalHoursPeriod, 1),
        'graphData' => $graphData,
        'hoverData' => $hover_data,
        'dailyLimit' => $daily_flight_limit
    ];
    $response->setSuccess(true)->setData($finalDataPackage);

} catch (Exception $e) {
     http_response_code(500);
     $response->setError($e->getMessage());
     error_log("Error in stats_get_stats_graph.php: " . $e->getMessage());
} finally {
     if ($stmt) { $stmt->close(); }
     if ($mysqli) { $mysqli->close(); }
}
$response->send();