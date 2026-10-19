<?php
require_once 'smtp_config.php';
require_once 'debug_log.php';

debugLog("=== CHECK_SERVER_NAME.PHP ===");
debugLog("SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'NOT SET'));
debugLog("SMTP_ENCRYPTION_KEY: " . SMTP_ENCRYPTION_KEY);
debugLog("HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NOT SET'));

$password = get_smtp_password();
debugLog("Password length: " . strlen($password));
debugLog("Password first 3: " . substr($password, 0, 3) . "***");

echo "<h1>Server Name Debug</h1>";
echo "<pre>";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'NOT SET') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NOT SET') . "\n";
echo "ENCRYPTION_KEY: " . SMTP_ENCRYPTION_KEY . "\n";
echo "Password length: " . strlen($password) . "\n";
echo "Password first 3: " . substr($password, 0, 3) . "***\n";
echo "</pre>";

echo "<p>Check view_debug.php for full log</p>";
?>
