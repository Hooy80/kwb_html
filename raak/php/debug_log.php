<?php
// Custom debug logger that writes to a file we can read

function debugLog($message) {
    $logFile = __DIR__ . '/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function clearDebugLog() {
    $logFile = __DIR__ . '/debug.log';
    if (file_exists($logFile)) {
        unlink($logFile);
    }
}
?>
