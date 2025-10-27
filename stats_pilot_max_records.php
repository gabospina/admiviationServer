<?php
// stats_get_stats_graph.php
require_once "db_connect.php";
require_once "stats_api_response.php";
require_once "stats_pilot_max_times.php"; // *** NEW: Include file fetching limits ***

if (session_status() == PHP_SESSION_NONE) { session_start(); }
$response = new ApiResponse();

if (!isset($_SESSION["HeliUser"])) { /* ... auth check ... */ }
$user_id = (int)$_SESSION["HeliUser"];
$company_id = (int)$_SESSION["company_id"]; // Assuming company ID is also in session

$type = $_GET["type"] ?? null;
$startInput = $_GET["start"] ?? null;

// --- Fetch Limits (Using the new included file) ---
$limits = getCompanyLimits($mysqli, $company_id);
if (!$limits) {
    http_response_code(500);
    $response->setError("Could not fetch account limits.")->setSuccess(false)->send();
}


// --- Input Validation ---
// ... (validate $type, $startInput as before) ...
$start_date_sql = $startDateObj->format('Y-m-d');

// --- Database Connection ---
// ... (check $mysqli as before) ...

$graphData = [];
$totalHoursPeriod = 0.0; // Total hours just for the specific period being graphed
$stmt = null;

try {
    // Determine the full date range needed (including lookback for rolling sums)
    $lookbackDays = 0;
    $endDateObj = null;
    $queryStartDateObj = clone $startDateObj; // Start date for the main query range

    switch ($type) {
        case "week":
             $lookbackDays = 6; // Need 6 previous days for 7-day rolling sum
             $endDateObj = clone $startDateObj;
             $endDateObj->modify('+7 days');
             $queryStartDateObj->modify("-$lookbackDays days"); // Start query earlier
            break;
        case "past7":
             $lookbackDays = 6;
             $endDateObj = clone $startDateObj; // End date is the input start date
             $endDateObj->modify('+1 day'); // Query up to the day after startInput
             $queryStartDateObj = clone $startDateObj;
             $queryStartDateObj->modify('-'. (6 + $lookbackDays) . ' days'); // Query range starts 6 days before, plus lookback
            break;
        case "month":
             $lookbackDays = 27; // Need 27 previous days for 28-day rolling sum
             $endDateObj = clone $startDateObj;
             $endDateObj->modify('first day of next month');
             $queryStartDateObj->modify("-$lookbackDays days"); // Start query earlier
            break;
         case "past28":
             $lookbackDays = 27;
             $endDateObj = clone $startDateObj; // End date is the input start date
             $endDateObj->modify('+1 day');
             $queryStartDateObj = clone $startDateObj;
             $queryStartDateObj->modify('-'. (27 + $lookbackDays) . ' days');
             break;
         case "year":
             $lookbackDays = 364; // Need 364 previous days
             $endDateObj = clone $startDateObj;
             $endDateObj->modify('+1 year');
             $queryStartDateObj->modify("-$lookbackDays days");
             break;
        default: throw new Exception("Invalid type parameter.");
    }

    $query_start_date_sql = $queryStartDateObj->format('Y-m-d');
    $end_date_sql = $endDateObj->format('Y-m-d');

    // --- Fetch ALL necessary data in one go ---
    $sql = "SELECT date, registration, hours
            FROM flight_hours
            WHERE user_id = ? AND date >= ? AND date < ?
            ORDER BY date ASC"; // Fetch necessary columns

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) { throw new Exception("Prepare failed: (" . $mysqli->errno . ") " . $mysqli->error); }
    $stmt->bind_param("iss", $user_id, $query_start_date_sql, $end_date_sql);
    if (!$stmt->execute()) { throw new Exception("Execute failed: (" . $stmt->errno . ") " . $stmt->error); }

    $result = $stmt->get_result();
    $allDailyHours = []; // ALL fetched data { 'YYYY-MM-DD': hours }
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        $hours = (float)$row['hours'];
        $allDailyHours[$date] = ($allDailyHours[$date] ?? 0.0) + $hours; // Sum hours if multiple entries on same day
    }
    $stmt->close();


    // --- Process data and calculate rolling sums ---
    $currentDateObj = clone $startDateObj; // Iterator for the display period
    $displayEndDateObj = clone $endDateObj;
    if ($type == 'past7') $displayEndDateObj = (clone $startDateObj)->modify('+1 day'); // Adjust end date for past7 display loop
    if ($type == 'past28') $displayEndDateObj = (clone $startDateObj)->modify('+1 day'); // Adjust end date for past28 display loop

     // Adjust start date for loops involving 'past' types
     if ($type == 'past7') $currentDateObj->modify('-6 days');
     if ($type == 'past28') $currentDateObj->modify('-27 days');


    if ($type == 'year') {
       // --- Yearly Processing (Aggregate by Month) ---
       $monthlyData = array_fill(0, 12, ['hours' => 0.0, 'rolling365' => 0.0]); // 0-indexed months
       $rolling365Sum = 0.0;
       $rollingWindow = []; // Store last 365 days of hours

       $loopStartDate = clone $queryStartDateObj; // Start from beginning of fetched data
       $loopEndDate = clone $endDateObj; // End one year after display start

       while($loopStartDate < $loopEndDate){
            $currentDateStr = $loopStartDate->format('Y-m-d');
            $hoursToday = $allDailyHours[$currentDateStr] ?? 0.0;

            // Add to rolling sum and window
            $rolling365Sum += $hoursToday;
            $rollingWindow[$currentDateStr] = $hoursToday;

            // Remove day falling out of the 365-day window
            $dateToRemove = (clone $loopStartDate)->modify('-365 days')->format('Y-m-d');
            if (isset($rollingWindow[$dateToRemove])) {
                 $rolling365Sum -= $rollingWindow[$dateToRemove];
                 unset($rollingWindow[$dateToRemove]);
            }

             // If this date is within the display year, add to monthly total
             if($loopStartDate >= $startDateObj && $loopStartDate < $endDateObj){
                 $monthIndex = (int)$loopStartDate->format('n') - 1; // 0-11
                 $monthlyData[$monthIndex]['hours'] += $hoursToday;
                 // Store the rolling sum at the *end* of each day within the display month
                 $monthlyData[$monthIndex]['rolling365'] = $rolling365Sum; // Use the latest rolling sum for the month's data point
             }

            $loopStartDate->modify('+1 day');
       }


        // Format for Flot
        for ($m = 0; $m < 12; $m++) {
            $monthName = date("F", mktime(0, 0, 0, $m + 1, 1, (int)$startDateObj->format('Y')));
            $isOverLimit = $monthlyData[$m]['rolling365'] > $limits['max_last_365']; // Compare rolling sum
            $graphData[] = [
                 "color" => $isOverLimit ? "#EDABAB" : "#49BFF2",
                 "data" => [[$monthName, round($monthlyData[$m]['hours'], 1)]],
                 // Add custom data for hover tooltip
                 "hoverData" => [
                     "rollingTotal" => round($monthlyData[$m]['rolling365'], 1),
                     "limit" => $limits['max_last_365'],
                     "period" => 365 // Indicate the period for the rolling total
                 ]
            ];
            $totalHoursPeriod += $monthlyData[$m]['hours']; // Sum displayed monthly hours
        }


    } else {
        // --- Daily/Weekly/Monthly Processing ---
        $rolling7Sum = 0.0;
        $rolling28Sum = 0.0;
        $window7 = []; // Store last 7 days of hours
        $window28 = []; // Store last 28 days of hours

        // Loop through the *display* period
        while ($currentDateObj < $displayEndDateObj) {
            $currentDateStr = $currentDateObj->format('Y-m-d');
            $hoursToday = $allDailyHours[$currentDateStr] ?? 0.0; // Hours flown *on this specific day*

            // --- Calculate Rolling Sums *ending on this day* ---
            // To do this accurately, we need data *prior* to this day from $allDailyHours
            $temp7DaySum = 0.0;
            $temp28DaySum = 0.0;
            $dateIterator = clone $currentDateObj;
            for ($d = 0; $d < 7; $d++) { // Sum last 7 days ending today
                 $dStr = $dateIterator->format('Y-m-d');
                 $temp7DaySum += $allDailyHours[$dStr] ?? 0.0;
                 if ($d < 28) $temp28DaySum += $allDailyHours[$dStr] ?? 0.0; // Start summing 28 day
                 $dateIterator->modify('-1 day');
            }
             $dateIterator = clone $currentDateObj; // Reset iterator
             $dateIterator->modify('-7 days'); // Start from day 8
             for ($d = 7; $d < 28; $d++) { // Sum remaining days for 28 day total
                 $dStr = $dateIterator->format('Y-m-d');
                 $temp28DaySum += $allDailyHours[$dStr] ?? 0.0;
                 $dateIterator->modify('-1 day');
             }
             // --- End Rolling Sum Calculation ---


            $totalHoursPeriod += $hoursToday; // Sum hours for the specific period being graphed

            // Determine rolling total and limit based on view type for hover/color
            $relevantRollingTotal = 0.0;
            $relevantLimit = 0.0;
            $limitPeriod = 0;
             if ($type == 'week' || $type == 'past7') {
                 $relevantRollingTotal = $temp7DaySum;
                 $relevantLimit = $limits['max_last_7'];
                 $limitPeriod = 7;
            } else { // month or past28
                 $relevantRollingTotal = $temp28DaySum;
                 $relevantLimit = $limits['max_last_28'];
                 $limitPeriod = 28;
            }

            // Determine color based on daily limit AND rolling limit
            $isOverDaily = $hoursToday > $limits['max_in_day'];
            $isOverRolling = $relevantRollingTotal > $relevantLimit;
            $color = $isOverRolling ? "#EDABAB" : ($isOverDaily ? "#FAD46B" : "#49BFF2"); // Red > Yellow > Blue

            // Format x-axis label
            $xLabel = ($type == 'week' || $type == 'past7') ?
                      $currentDateObj->format('D M jS') :
                      $currentDateObj->format("M<\b\\r/>jS");

             $graphData[] = [
                 "color" => $color,
                 "label" => '', // Label now used for hover data below
                 "data" => [[$xLabel, round($hoursToday, 1)]],
                 // Add custom data for hover tooltip
                 "hoverData" => [
                     "rollingTotal" => round($relevantRollingTotal, 1),
                     "limit" => $relevantLimit,
                     "period" => $limitPeriod,
                     "isOverDaily" => $isOverDaily,
                     "isOverRolling" => $isOverRolling
                 ]
            ];

            $currentDateObj->modify('+1 day');
        }
    }

    // --- Set Success Response ---
    $response->setSuccess(true);
    $response->setData(['total' => round($totalHoursPeriod, 1), 'data' => $graphData]);

} catch (Exception $e) {
    // --- Set Error Response ---
    http_response_code(500);
    $response->setError($e->getMessage())->setSuccess(false);
     if (function_exists('logError')) { logError("Error in stats_get_stats_graph.php: " . $e->getMessage()); }
     else { error_log("Error in stats_get_stats_graph.php: " . $e->getMessage()); }
} finally {
     // --- Cleanup ---
     if ($stmt instanceof mysqli_stmt) $stmt->close();
     if ($mysqli instanceof mysqli && $mysqli->thread_id) $mysqli->close();
}

$response->send();

?>