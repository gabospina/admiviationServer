<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://aviationweather.gov/cgi-bin/data/sigmet.php?format=json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for testing!

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    die(json_encode([
        'error' => 'CURL error',
        'details' => curl_error($ch),
        'http_code' => $http_code
    ]));
}

curl_close($ch);

if ($http_code !== 200) {
    die(json_encode([
        'error' => 'API response error',
        'http_code' => $http_code,
        'response' => substr($response, 0, 200) // First 200 chars
    ]));
}

echo $response;
?>