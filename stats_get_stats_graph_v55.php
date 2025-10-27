<?php
// stats_get_stats_graph.php

// --- Error Reporting ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- Required Files ---
require_once "db_connect.php";
require_once "stats_api_response.php";
require_once "stats_pilot_max_times.php";

// --- Session and Input ---
if (session_status() == PHP_SESSION_NONE) { session_start(); }
$response = new ApiResponse();

// --- Authentication ---
if (!isset($_SESSION["HeliUser"])) {
    http_response_code(401);
    $response->setError("Authentication required")->setSuccess(false)->send();
}
$user_id = (int)$_SESSION["HeliUser"];
if (!isset($_SESSION["company_id"]) || !is_numeric($_SESSION["company_id"])) {
    http_response_code(400);
    $response->setError("Company ID not found in session.")->setSuccess(false)->send();
}
$company_id = (int)$_SESSION["company_id"];

// --- Get Input Parameters ---
$type = $_GET["type"] ?? null;
$startInput = $_GET["start"] ?? null;

// --- Basic Input Validation ---
if (empty($type) || empty($startInput)) {
    http_response_code(400);
    $response->setError("Missing required parameters (type, start).")->setSuccess(false)->send();
}
$startDateObj = DateTime::createFromFormat('Y-m-d', $startInput);
if (!$startDateObj || $startDateObj->format('Y-m-d') !== $startInput) {
    http_response_code(400);
    $response->setError("Invalid start date format. Use YYYY-MM-DD.")->setSuccess(false)->send();
}
$start_date_sql = $startDateObj->format('Y-m-d');

// --- Database Connection ---
if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500);
    $response->setError("Database connection failed.")->setSuccess(false)->send();
}

// --- Fetch Limits ---
$limits = getPilotLimits($mysqli, $company_id);
if (!$limits) {
    http_response_code(500);
    $response->setError("Could not fetch maximum time limits.")->setSuccess(false)->send();
}
// NEW LOGIC: Get the specific daily limit for stacked bar calculation.
$daily_flight_limit = $limits['max_in_day'] ?? 12.0; // Default to 12 if not set

// --- Main Logic ---
$graphData = [];
$totalHoursPeriod = 0.0;
$stmt = null;

try {
    // Determine the full date range needed
    $endDateObj = null;
    $queryStartDateObj = clone $startDateObj;

    // --- Calculate date ranges (same logic as before) ---
    switch ($type) {
        case "week":
            $endDateObj = clone $startDateObj; $endDateObj->modify('+7 days');
            $queryStartDateObj->modify("-6 days");
            break;
        case "past7":
            $endDateObj = clone $startDateObj; $endDateObj->modify('+1 day');
            $queryStartDateObj = clone $startDateObj; $queryStartDateObj->modify('-6 days');
            $start_date_sql = $queryStartDateObj->format('Y-m-d');
            break;
        case "month":
            $endDateObj = clone $startDateObj; $endDateObj->modify('first day of next month');
            $queryStartDateObj->modify("-27 days"); // Need previous 28 days for rolling sum
            break;
        case "past28":
            $endDateObj = clone $startDateObj; $endDateObj->modify('+1 day');
            $queryStartDateObj = clone $startDateObj; $queryStartDateObj->modify('-27 days');
            $start_date_sql = $queryStartDateObj->format('Y-m-d');
            break;
        case "year":
            $endDateObj = clone $startDateObj; $endDateObj->modify('+1 year');
            $queryStartDateObj->modify("-364 days");
            break;
        default: throw new Exception("Invalid type parameter specified.");
    }
    $query_start_date_sql = $queryStartDateObj->format('Y-m-d');
    $end_date_sql = $endDateObj->format('Y-m-d');

    // --- Fetch ALL necessary data ---
    $sql = "SELECT date, hours FROM pilot_log_book WHERE user_id = ? AND date >= ? AND date < ? ORDER BY date ASC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) { throw new Exception("Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error); }
    $stmt->bind_param("iss", $user_id, $query_start_date_sql, $end_date_sql);
    if (!$stmt->execute()) { throw new Exception("Execute failed: (" . $stmt->errno . ") " . $stmt->error); }
    $result = $stmt->get_result();
    
    // NEW LOGIC: Aggregate multiple flights per day
    $flights_by_day = []; // Stores arrays of flights: ['YYYY-MM-DD' => [2.5, 3.0]]
    while ($row = $result->fetch_assoc()) {
        $flights_by_day[$row['date']][] = (float)$row['hours'];
    }

    // NEW LOGIC: Create a map of daily TOTALS for rolling sum calculations.
    $allDailyTotals = [];
    foreach ($flights_by_day as $date => $flights) {
        $allDailyTotals[$date] = array_sum($flights);
    }

    // --- Process data and calculate rolling sums ---
    $currentDateObj = DateTime::createFromFormat('Y-m-d', $start_date_sql);
    $displayEndDateObj = $endDateObj;

    if ($type == 'year') {
        // ... (The existing 'year' logic is preserved here) ...
        // It will now correctly use the summed daily totals from $allDailyTotals
        $monthlyData = array_fill(0, 12, ['monthName' => '', 'hours' => 0.0]);
        $loopDateObj = clone $currentDateObj;
        while ($loopDateObj < $displayEndDateObj) {
            $currentDateStr = $loopDateObj->format('Y-m-d');
            $hoursToday = $allDailyTotals[$currentDateStr] ?? 0.0;
            $totalHoursPeriod += $hoursToday;

            $monthIndex = (int)$loopDateObj->format('n') - 1;
            $monthlyData[$monthIndex]['monthName'] = $loopDateObj->format('F');
            $monthlyData[$monthIndex]['hours'] += $hoursToday;
            
            $loopDateObj->modify('+1 day');
        }
        // Format for Flot... (this part remains the same conceptually)
        for ($m = 0; $m < 12; $m++) {
            $monthName = !empty($monthlyData[$m]['monthName']) ? $monthlyData[$m]['monthName'] : date("F", mktime(0, 0, 0, $m + 1, 1, (int)$currentDateObj->format('Y')));
            $graphData[] = [ "color" => "#49BFF2", "data" => [[$monthName, round($monthlyData[$m]['hours'], 1)]]];
        }
    } else {
        // NEW LOGIC: Prepare arrays for stacked bar chart and detailed hover data
        $normal_hours_series = [];
        $overage_hours_series = [];
        $hover_data = [];

        // --- Daily/Weekly/Monthly Processing ---
        while ($currentDateObj < $displayEndDateObj) {
            $currentDateStr = $currentDateObj->format('Y-m-d');
            
            // NEW LOGIC: Get array of flights for today and calculate daily total
            $flights_today = $flights_by_day[$currentDateStr] ?? [];
            $daily_total = $allDailyTotals[$currentDateStr] ?? 0.0;

            // Add to the total for the displayed period
            $totalHoursPeriod += $daily_total;

            // --- Calculate Rolling Sums *ending on this day* using $allDailyTotals ---
            $temp7DaySum = 0.0;
            $temp28DaySum = 0.0;
            $dateIterator7 = clone $currentDateObj;
            for ($d = 0; $d < 7; $d++) {
                 $dStr = $dateIterator7->format('Y-m-d');
                 $temp7DaySum += $allDailyTotals[$dStr] ?? 0.0;
                 $dateIterator7->modify('-1 day');
            }
            $dateIterator28 = clone $currentDateObj;
            for ($d = 0; $d < 28; $d++) {
                 $dStr = $dateIterator28->format('Y-m-d');
                 $temp28DaySum += $allDailyTotals[$dStr] ?? 0.0;
                 $dateIterator28->modify('-1 day');
            }

            // Determine rolling total and limit based on view type
            $relevantRollingTotal = ($type == 'week' || $type == 'past7') ? $temp7DaySum : $temp28DaySum;
            $relevantLimit = ($type == 'week' || $type == 'past7') ? ($limits['max_last_7'] ?? 99999) : ($limits['max_last_28'] ?? 99999);
            $limitPeriod = ($type == 'week' || $type == 'past7') ? 7 : 28;

            // Format x-axis label
            $xLabel = ($type == 'week' || $type == 'past7') ? $currentDateObj->format('D M jS') : $currentDateObj->format("M<\b\\r/>jS");

            // NEW LOGIC: Split daily total into "normal" and "overage" for stacking
            $normal_hours = 0;
            $overage_hours = 0;
            if ($daily_total > $daily_flight_limit) {
                $normal_hours = $daily_flight_limit;
                $overage_hours = $daily_total - $daily_flight_limit;
            } else {
                $normal_hours = $daily_total;
            }
            
            // Populate the series data for the stacked chart
            $normal_hours_series[] = [$xLabel, round($normal_hours, 1)];
            $overage_hours_series[] = [$xLabel, round($overage_hours, 1)];
            
            // NEW LOGIC: Populate the detailed hover data
            $hover_data[$xLabel] = [
                'total' => round($daily_total, 1),
                'flights' => $flights_today, // Array of individual flights
                'rollingTotal' => round($relevantRollingTotal, 1),
                'limit' => $relevantLimit,
                'period' => $limitPeriod,
                'isOverDaily' => ($daily_total > $daily_flight_limit),
                'isOverRolling' => ($relevantRollingTotal > $relevantLimit)
            ];

            $currentDateObj->modify('+1 day');
        } // End while loop

        // NEW LOGIC: Assemble the final graph data structure for Flot
        $graphData = [
            [ 'label' => 'Overage Hours', 'data' => $overage_hours_series, 'color' => '#e74c3c' ], // Red for overage
            [ 'label' => 'Normal Hours',  'data' => $normal_hours_series,  'color' => '#49BFF2' ]  // Blue for normal
        ];
        
        // Final data package for the response
        $finalDataPackage = [
            'total' => round($totalHoursPeriod, 1), 
            'graphData' => $graphData,
            'hoverData' => $hover_data
        ];
    } // End else (daily/weekly/monthly)

    // --- Set Success Response ---
    $response->setSuccess(true);
    // For year view, 'data' is the old format. For others, it's the new package.
    $response->setData($type == 'year' ? ['total' => round($totalHoursPeriod, 1), 'data' => $graphData] : $finalDataPackage);

} catch (Exception $e) {
     http_response_code(500);
     $response->setError($e->getMessage())->setSuccess(false);
     error_log("Error in stats_get_stats_graph.php: " . $e->getMessage());
} finally {
     if ($stmt instanceof mysqli_stmt) {
         $stmt->close();
     }
     if ($mysqli instanceof mysqli && $mysqli->thread_id) $mysqli->close();
}

// --- Send Final JSON Response ---
$response->send();