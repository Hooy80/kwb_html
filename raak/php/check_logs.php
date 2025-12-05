<?php
// Check if we can see error logs

// Show last 100 lines of error_log calls
$logFile = ini_get('error_log');
echo "<h2>Error Log Location</h2>";
echo "<p>error_log setting: " . ($logFile ? $logFile : 'default (usually stderr)') . "</p>";

echo "<h2>PHP Info</h2>";
echo "<p>display_errors: " . ini_get('display_errors') . "</p>";
echo "<p>log_errors: " . ini_get('log_errors') . "</p>";
echo "<p>error_reporting: " . error_reporting() . "</p>";

// Try to read from possible locations
$possibleLogs = [
    $logFile,
    '../error_log',
    '../../error_log',
    '../../../error_log',
    '/tmp/error_log',
    '/var/log/php_errors.log',
    '/var/log/apache2/error.log',
    __DIR__ . '/../error_log',
    __DIR__ . '/../../error_log'
];

echo "<h2>Checking Possible Log Locations</h2>";
foreach ($possibleLogs as $path) {
    if (!$path) continue;
    
    echo "<p>Checking: $path</p>";
    if (file_exists($path)) {
        echo "<p><strong>✓ Found!</strong></p>";
        if (is_readable($path)) {
            echo "<pre style='background: #f0f0f0; padding: 10px; max-height: 400px; overflow: auto;'>";
            echo "Last 200 lines:\n\n";
            $lines = file($path);
            $last200 = array_slice($lines, -200);
            echo htmlspecialchars(implode('', $last200));
            echo "</pre>";
        } else {
            echo "<p>File exists but not readable</p>";
        }
    } else {
        echo "<p>Not found</p>";
    }
    echo "<hr>";
}

// Test error_log function
error_log("=== TEST LOG FROM check_logs.php at " . date('Y-m-d H:i:s') . " ===");
echo "<p>Test log written with error_log()</p>";
?>
