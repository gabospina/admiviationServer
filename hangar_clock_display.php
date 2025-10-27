<?php
// hangar_clock_display.php - CORRECTED
function displayClockTimes() {
    // This function assumes a session is already started where it is called.
    // If not, uncomment the next line:
    // if (session_status() == PHP_SESSION_NONE) { session_start(); }

    include_once "db_connect.php";

    // Ensure the session variable is set before using it.
    if (!isset($_SESSION["HeliUser"])) {
        echo "<p>Error: Not logged in.</p>";
        return;
    }
    
    $user_id = $_SESSION["HeliUser"];

    // FIX: The query now selects from the 'users' table, not 'pilot_info'.
    // EXPLANATION: This gets the clock data directly from the user's own record,
    // creating a single source of truth for all user information.
    $query = "SELECT clock_name, clock_tz FROM users WHERE id = ?";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        echo "<p>Database error.</p>";
        return;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        if (!empty($row['clock_tz'])) {
            try {
                $tz = new DateTimeZone($row['clock_tz']);
                $local_time = new DateTime('now', $tz);
                $utc_time = new DateTime('now', new DateTimeZone('UTC'));
                
                $clock_name = htmlspecialchars($row['clock_name'] ?: 'My Clock');

                echo "<div class='clock-display'>";
                echo "<h3>{$clock_name}</h3>";
                echo "<p>Local Time: " . $local_time->format('Y-m-d H:i:s') . "</p>";
                echo "<p>UTC Time: " . $utc_time->format('Y-m-d H:i:s') . "</p>";
                echo "</div>";
            } catch (Exception $e) {
                echo "<p>Invalid timezone setting: " . htmlspecialchars($row['clock_tz']) . "</p>";
            }
        } else {
            echo "<p>Timezone not configured</p>";
        }
    } else {
        echo "<p>Clock settings not found</p>";
    }
    $stmt->close();
}
?>