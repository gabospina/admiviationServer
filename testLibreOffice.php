<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
echo "Testing shell_exec...<br>";

// *** USE YOUR EXACT PATH ***
$sofficeCmd = '"C:\\Program Files\\LibreOffice\\program\\soffice.exe" --version';

echo "Attempting command: " . htmlspecialchars($sofficeCmd) . "<br>";

// Use output buffering to potentially capture fleeting output/errors
ob_start();
$output = shell_exec($sofficeCmd);
$captured_output = ob_get_clean();

echo "Result from shell_exec:<br><pre>";
if ($output === null && empty($captured_output)) {
    echo "ERROR: shell_exec returned NULL and captured no output. Command likely failed to execute or produced no output/error stream.";
    // Check PHP error log for lower-level errors if any
} elseif ($output !== null) {
    echo "Direct output:\n" . htmlspecialchars($output);
} elseif (!empty($captured_output)) {
     echo "Captured output (ob_get_clean):\n" . htmlspecialchars($captured_output);
}
// Try adding a simple check if the command seemed to run at all
if ($output !== null || !empty($captured_output)) {
     echo "\n\nSUCCESS (Potentially): Command ran via shell_exec. Check output above for version.";
}
echo "</pre>";
?>