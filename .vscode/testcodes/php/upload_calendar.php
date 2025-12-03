<?php
// Upload alleen calendar.php naar raak/php

$ftp_server = "ftp.raakachterbos.be";
$ftp_username = "raakachterbos.be";
$ftp_password = "Raakachterbos.1";

$conn = ftp_connect($ftp_server);
if (!$conn) die("Kon niet verbinden\n");

$login = ftp_login($conn, $ftp_username, $ftp_password);
if (!$login) die("Login mislukt\n");

echo "✓ Verbonden met FTP\n\n";
ftp_pasv($conn, true);

// Ga naar root
ftp_chdir($conn, '/');

// Maak raak/php aan als het niet bestaat
if (!@ftp_chdir($conn, 'raak/php')) {
    echo "Aanmaken raak/php...\n";
    @ftp_mkdir($conn, 'raak');
    @ftp_mkdir($conn, 'raak/php');
}

// Ga terug naar root
ftp_chdir($conn, '/');

// Upload calendar.php
echo "Uploaden: calendar.php naar raak/php/\n";
if (ftp_put($conn, 'raak/php/calendar.php', __DIR__ . '/php/calendar.php', FTP_BINARY)) {
    echo "✓ calendar.php geüpload!\n\n";
    echo "Test op: https://raakachterbos.be/raak/php/calendar.php\n";
} else {
    echo "✗ Upload mislukt\n";
    echo "FTP error: " . error_get_last()['message'] . "\n";
}

ftp_close($conn);
