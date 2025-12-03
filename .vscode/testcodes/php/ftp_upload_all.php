<?php
// FTP upload script voor volledige raak folder

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

// Functie om recursief folders te uploaden
function ftp_upload_recursive($conn, $local_dir, $remote_dir) {
    static $file_count = 0;
    static $dir_count = 0;
    
    // Maak remote directory aan als die niet bestaat
    if (!@ftp_chdir($conn, $remote_dir)) {
        if (ftp_mkdir($conn, $remote_dir)) {
            echo "✓ Folder aangemaakt: $remote_dir\n";
            $dir_count++;
        }
    }
    
    // Scan lokale directory
    $items = scandir($local_dir);
    
    foreach ($items as $item) {
        // Skip . en ..
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        $local_path = $local_dir . '/' . $item;
        $remote_path = $remote_dir . '/' . $item;
        
        // Skip node_modules en andere onnodige folders
        if (in_array($item, ['node_modules', '.git', '.vscode', 'build'])) {
            echo "⊗ Overgeslagen: $item\n";
            continue;
        }
        
        // Skip FTP upload scripts
        if (in_array($item, ['ftp_upload.php', 'ftp_upload_all.php', 'test_upload.txt'])) {
            echo "⊗ Overgeslagen: $item\n";
            continue;
        }
        
        if (is_dir($local_path)) {
            // Recursief uploaden van subfolder
            ftp_upload_recursive($conn, $local_path, $remote_path);
        } else {
            // Upload bestand
            echo "Uploaden: $remote_path ... ";
            if (ftp_put($conn, $remote_path, $local_path, FTP_BINARY)) {
                echo "✓\n";
                $file_count++;
            } else {
                echo "✗ MISLUKT\n";
            }
        }
    }
    
    return ['files' => $file_count, 'dirs' => $dir_count];
}

echo "Start uploaden van raak folder naar /raak op server...\n\n";

$start_time = microtime(true);
$stats = ftp_upload_recursive($conn, __DIR__, 'raak');
$end_time = microtime(true);

$duration = round($end_time - $start_time, 2);

echo "\n";
echo "========================================\n";
echo "Upload voltooid!\n";
echo "Bestanden: {$stats['files']}\n";
echo "Folders: {$stats['dirs']}\n";
echo "Tijd: {$duration} seconden\n";
echo "========================================\n";
echo "\n";
echo "Je React app staat nu op:\n";
echo "https://raakachterbos.be/raak/\n";
echo "\n";
echo "PHP API bereikbaar op:\n";
echo "https://raakachterbos.be/raak/php/calendar.php\n";

ftp_close($conn);
