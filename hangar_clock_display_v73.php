<?php
// clock_display.php
function displayClockTimes() {
    session_start();
    include_once "db_connect.php";
    
    $user = $_SESSION["HeliUser"];
    $query = "SELECT clock_name, clock_tz FROM pilot_info WHERE id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $user);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['clock_tz']) {
            try {
                $tz = new DateTimeZone($row['clock_tz']);
                $local_time = new DateTime('now', $tz);
                $utc_time = new DateTime('now', new DateTimeZone('UTC'));
                
                echo "<div class='clock-display'>";
                echo "<h3>{$row['clock_name']}</h3>";
                echo "<p>Local Time: " . $local_time->format('Y-m-d H:i:s') . "</p>";
                echo "<p>UTC Time: " . $utc_time->format('Y-m-d H:i:s') . "</p>";
                echo "</div>";
            } catch (Exception $e) {
                echo "<p>Invalid timezone setting: {$row['clock_tz']}</p>";
            }
        } else {
            echo "<p>Timezone not configured</p>";
        }
    } else {
        echo "<p>Clock settings not found</p>";
    }
}
?>