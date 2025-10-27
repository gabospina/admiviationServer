<?php
// stats_pilot_max_times.php

/**
 * Fetches company limits from the database.
 * Returns an array of limits or false on failure.
 */
function getPilotLimits($dbConnection, $companyId) {
    if (!$dbConnection || $dbConnection->connect_error || empty($companyId)) {
        error_log("getPilotLimits: Invalid DB connection or Company ID.");
        return false;
    }

    $limits = [ // Initialize with defaults in case query fails or returns nulls
        'max_in_day' => 8.0,
        'max_last_7' => 40.0,
        'max_last_28' => 100.0,
        'max_last_365' => 1000.0,
        'max_duty_in_day' => 12.0, // Example duty limits
        'max_duty_7' => 60.0,
        'max_duty_28' => 190.0,
        'max_duty_365' => 2000.0,
        'max_days_in_row' => 6 // Example
    ];

    $sql = "SELECT max_in_day, max_last_7, max_last_28, max_last_365,
                   max_duty_in_day, max_duty_7, max_duty_28, max_duty_365,
                   max_days_in_row
            FROM pilot_max_times WHERE id = ?";

    $stmt = $dbConnection->prepare($sql);
    if (!$stmt) {
        error_log("getPilotLimits: Prepare failed: " . $dbConnection->error);
        return $limits; // Return defaults on prepare error
    }

    $stmt->bind_param("i", $companyId);
    if (!$stmt->execute()) {
        error_log("getPilotLimits: Execute failed: " . $stmt->error);
        $stmt->close();
        return $limits; // Return defaults on execute error
    }

    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if ($data) {
        // Override defaults only if DB value is not null
        foreach ($limits as $key => $default) {
             if (isset($data[$key]) && $data[$key] !== null) {
                 // Cast to float for hour/duty limits, int for days
                 if (strpos($key, 'days') !== false) {
                     $limits[$key] = (int)$data[$key];
                 } else {
                     $limits[$key] = (float)$data[$key];
                 }
             }
        }
    } else {
         error_log("getACompanyLimits: No limits found for company ID: " . $companyId . ". Using defaults.");
         // Defaults are already set, so no action needed here
    }

    return $limits;
}
?>