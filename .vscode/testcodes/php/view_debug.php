<?php
// View debug log
$logFile = __DIR__ . '/debug.log';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Debug Log</title>";
echo "<style>body{font-family:monospace;margin:20px;} pre{background:#f0f0f0;padding:15px;border-radius:5px;overflow:auto;} .clear{background:#ff5555;color:white;padding:10px;border:none;border-radius:5px;cursor:pointer;margin-bottom:10px;}</style>";
echo "</head><body>";

echo "<h1>Debug Log Viewer</h1>";

if (isset($_GET['clear'])) {
    if (file_exists($logFile)) {
        unlink($logFile);
        echo "<p style='color:green;'>✓ Log cleared!</p>";
    }
    echo "<script>setTimeout(function(){ window.location.href='view_debug.php'; }, 1000);</script>";
} else {
    echo "<button class='clear' onclick='if(confirm(\"Clear log?\")) window.location.href=\"?clear=1\"'>Clear Log</button>";
    echo "<button onclick='window.location.reload()'>Refresh</button>";
    
    if (file_exists($logFile)) {
        echo "<h2>Log Contents</h2>";
        echo "<p>File: $logFile</p>";
        echo "<p>Size: " . filesize($logFile) . " bytes</p>";
        echo "<p>Last modified: " . date('Y-m-d H:i:s', filemtime($logFile)) . "</p>";
        echo "<pre>";
        echo htmlspecialchars(file_get_contents($logFile));
        echo "</pre>";
    } else {
        echo "<p>No log file found yet. Try sending an email from the mailinglijst page.</p>";
    }
}

echo "</body></html>";
?>
