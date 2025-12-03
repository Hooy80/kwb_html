<?php
$ftp_server = "ftp.raakachterbos.be";
$ftp_username = "raakachterbos.be";
$ftp_password = "Raakachterbos.1";

$conn = ftp_connect($ftp_server);
ftp_login($conn, $ftp_username, $ftp_password);
ftp_pasv($conn, true);

echo "Current dir: " . ftp_pwd($conn) . "\n\n";

// Lijst van folders
echo "Files:\n";
$files = ftp_nlist($conn, ".");
foreach ($files as $file) {
    echo "  $file\n";
}

// Probeer raak folder aan te maken
echo "\nMaking raak folder...\n";
if (@ftp_mkdir($conn, 'raak')) {
    echo "✓ Created raak\n";
} else {
    echo "✗ Could not create (may exist)\n";
}

// Probeer bestand te uploaden
echo "\nTrying to upload test file...\n";
file_put_contents('/tmp/test.txt', 'test content');
if (ftp_put($conn, 'raak/test.txt', '/tmp/test.txt', FTP_BINARY)) {
    echo "✓ Upload successful!\n";
} else {
    echo "✗ Upload failed\n";
}

ftp_close($conn);
