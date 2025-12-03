<?php
// FTP upload script voor calendar.php en test_connection.php

$ftp_server = "ftp.raakachterbos.be";
$ftp_username = "raakachterbos.be";
$ftp_password = "Raakachterbos.1";

// Verbind met FTP server
$conn = ftp_connect($ftp_server);
if (!$conn) {
    die("Kon niet verbinden met FTP server\n");
}

// Login
$login = ftp_login($conn, $ftp_username, $ftp_password);
if (!$login) {
    die("FTP login mislukt\n");
}

echo "✓ Verbonden met FTP server\n\n";

// Passieve mode
ftp_pasv($conn, true);

// Toon huidige directory
echo "Huidige directory: " . ftp_pwd($conn) . "\n\n";

// Toon bestanden in root
echo "Bestanden op server:\n";
$files = ftp_nlist($conn, ".");
foreach ($files as $file) {
    echo "  - $file\n";
}
echo "\n";

// Maak php folder aan als die niet bestaat
echo "Controleren of 'php' folder bestaat...\n";
if (!@ftp_chdir($conn, "php")) {
    echo "Folder 'php' bestaat niet, aanmaken...\n";
    if (ftp_mkdir($conn, "php")) {
        echo "✓ Folder 'php' aangemaakt\n";
    } else {
        echo "✗ Kon folder 'php' niet aanmaken\n";
    }
} else {
    echo "✓ Folder 'php' bestaat al\n";
    ftp_chdir($conn, "..");
}

echo "\n";

// Upload calendar.php
$local_file = __DIR__ . '/php/calendar.php';
$remote_file = 'php/calendar.php';

echo "Uploaden: calendar.php\n";
if (ftp_put($conn, $remote_file, $local_file, FTP_BINARY)) {
    echo "✓ calendar.php succesvol geüpload\n";
} else {
    echo "✗ Upload van calendar.php mislukt\n";
}

// Upload test_connection.php
$local_file = __DIR__ . '/php/test_connection.php';
$remote_file = 'php/test_connection.php';

echo "Uploaden: test_connection.php\n";
if (ftp_put($conn, $remote_file, $local_file, FTP_BINARY)) {
    echo "✓ test_connection.php succesvol geüpload\n";
} else {
    echo "✗ Upload van test_connection.php mislukt\n";
}

echo "\n";
echo "Je kunt nu testen op:\n";
echo "https://raakachterbos.be/php/test_connection.php\n";

ftp_close($conn);
