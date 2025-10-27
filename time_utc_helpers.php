<?php
// time_utc_helpers.php

/**
 * Converts a UTC datetime string from the database to the user's local timezone.
 *
 * @param string $utc_datetime_str The UTC datetime string (e.g., '2023-10-27 14:30:00').
 * @param string $format The desired output format (e.g., 'Y-m-d H:i:s').
 * @return string The formatted datetime string in the user's local time, or an empty string on error.
 */
function to_user_time($utc_datetime_str, $format = 'Y-m-d H:i') {
    // Get the user's timezone from the session, defaulting to UTC.
    $user_timezone = $_SESSION['user_timezone'] ?? 'UTC';

    if (empty($utc_datetime_str)) {
        return ''; // Return empty if the input is empty
    }

    try {
        // 1. Create a DateTime object from the UTC string, and tell it that it's UTC.
        $datetime = new DateTime($utc_datetime_str, new DateTimeZone('UTC'));

        // 2. Set the timezone of the object to the user's local timezone.
        $datetime->setTimezone(new DateTimeZone($user_timezone));
        
        // 3. Return the formatted string.
        return $datetime->format($format);
        
    } catch (Exception $e) {
        // In case of an invalid date string, log the error and return something safe.
        error_log('Time conversion error: ' . $e->getMessage());
        return 'Invalid Date';
    }
}
?>