<?php
// Simpele log viewer (VERWIJDER DIT NA DEBUGGING!)
// SECURITY: Verwijder dit bestand na debugging!
// Of voeg een wachtwoord toe:

$debug_password = 'raak2025'; // Wijzig dit!
if (!isset($_GET['pw']) || $_GET['pw'] !== $debug_password) {
    die('Access denied. Use: ?pw=xxxxx');
}

echo "<h1>Recent Error Logs</h1>";
echo "<pre>";

// Try verschillende log locaties
$logFiles = [
    __DIR__ . '/../error_log',
    __DIR__ . '/error_log',
    '/home/raakach1/public_html/error_log',
    ini_get('error_log')
];

$found = false;
foreach ($logFiles as $logFile) {
    if (file_exists($logFile) && is_readable($logFile)) {
        echo "=== Log file: $logFile ===\n\n";
        
        // Lees laatste 100 regels
        $lines = file($logFile);
        $lastLines = array_slice($lines, -100);
        
        // Filter alleen SMTP en Mailinglijst regels
        foreach ($lastLines as $line) {
            if (stripos($line, 'SMTP') !== false || stripos($line, 'Mailinglijst') !== false) {
                echo htmlspecialchars($line);
            }
        }
        
        $found = true;
        break;
    }
}

if (!$found) {
    echo "Geen error log gevonden in standaard locaties.\n";
    echo "Probeer via je hosting control panel.\n";
}

echo "</pre>";
?>
