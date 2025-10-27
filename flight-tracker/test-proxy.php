<?php
// Test if your server can access external URLs
$test_urls = [
    'Google' => 'https://www.google.com',
    'NOAA' => 'https://aviationweather.gov/cgi-bin/data/sigmet.php?format=json'
];

foreach ($test_urls as $name => $url) {
    echo "<h2>Testing $name</h2>";
    echo "<p>URL: $url</p>";
    
    // Method 1: file_get_contents
    echo "<h3>file_get_contents</h3>";
    $data = @file_get_contents($url);
    echo $data === false ? "Failed: " . error_get_last()['message'] : "Success!";
    
    // Method 2: CURL
    echo "<h3>CURL</h3>";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    echo curl_errno($ch) ? "Failed: " . curl_error($ch) : "Success!";
    curl_close($ch);
    
    echo "<hr>";
}
?>