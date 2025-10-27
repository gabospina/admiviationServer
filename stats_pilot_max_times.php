<?php
// In stats_pilot_max_times.php

if (!function_exists('getPilotLimits')) {
    function getPilotLimits($mysqli, $company_id) {
        if (!$mysqli || !$company_id) {
            error_log("getPilotLimits: Invalid mysqli object or company_id provided.");
            return false;
        }

        // --- THIS IS THE FIX ---
        // The column `max_days_in_row` has been removed from the SELECT statement.
        $sql = "SELECT 
                    max_in_day, max_last_7, max_last_28, max_last_365,
                    max_duty_in_day, max_duty_7, max_duty_28, max_duty_365
                FROM pilot_max_times 
                WHERE company_id = ? 
                LIMIT 1";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            // Log the error if prepare fails. This is what you saw in your log.
            error_log("getPilotLimits: Prepare failed: " . $mysqli->error);
            return false;
        }

        $stmt->bind_param("i", $company_id);
        if (!$stmt->execute()) {
            error_log("getPilotLimits: Execute failed: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $limits = $result->fetch_assoc();
            $stmt->close();
            return $limits;
        } else {
            // No limits found for this company, return false or an array of defaults.
            // Returning false is cleaner as the calling script can handle the fallback.
            $stmt->close();
            return false;
        }
    }
}