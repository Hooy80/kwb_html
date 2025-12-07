<?php
// FTP upload script met selectieve upload ondersteuning
// Gebruik: php upload.php              -> volledige upload
//          php upload.php file1 file2  -> alleen specifieke bestanden

$ftp_server = "ftp.raakachterbos.be";
$ftp_username = "raakachterbos.be";
$ftp_password = "Raakachterbos.1";

// Check of er specifieke bestanden opgegeven zijn
$specificFiles = array_slice($argv, 1);
$selectiveMode = !empty($specificFiles);

$conn = ftp_connect($ftp_server);
if (!$conn) die("Kon niet verbinden\n");

$login = ftp_login($conn, $ftp_username, $ftp_password);
if (!$login) die("Login mislukt\n");

echo "✓ Verbonden met FTP server\n\n";
ftp_pasv($conn, true);

$file_count = 0;
$dir_count = 0;

function upload_dir($conn, $local_dir, $remote_dir, &$file_count, &$dir_count) {
    // Maak folder aan - probeer eerst, fail is OK als het al bestaat
    if ($remote_dir !== '' && $remote_dir !== '.') {
        $parts = explode('/', $remote_dir);
        $current = '';
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') continue;
            $current .= ($current ? '/' : '') . $part;
            @ftp_mkdir($conn, $current);
        }
        
        if (@ftp_mkdir($conn, $remote_dir)) {
            echo "✓ Folder: $remote_dir\n";
            $dir_count++;
        }
    }
    
    $items = scandir($local_dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $local_path = $local_dir . '/' . $item;
        // Fix: handle root upload properly
        $remote_path = ($remote_dir === '' || $remote_dir === '.') ? $item : $remote_dir . '/' . $item;
        
        if (is_dir($local_path)) {
            upload_dir($conn, $local_path, $remote_path, $file_count, $dir_count);
        } else {
            echo "Upload: " . basename($item) . " ... ";
            // Gebruik ASCII voor text bestanden, BINARY voor de rest
            $mode = (preg_match('/\.(html|css|js|json|txt|map)$/i', $item)) ? FTP_ASCII : FTP_BINARY;
            $result = @ftp_put($conn, $remote_path, $local_path, $mode);
            if ($result) {
                echo "✓\n";
                $file_count++;
            } else {
                echo "✗\n";
            }
        }
    }
}

$start = microtime(true);

if ($selectiveMode) {
    // Selectieve upload: alleen opgegeven bestanden
    echo "Selectieve upload modus: " . count($specificFiles) . " bestand(en)\n\n";
    
    foreach ($specificFiles as $file) {
        $uploaded = false;
        
        // Check in build/static/js/ (gebouwde React bestanden)
        $buildJsPath = __DIR__ . '/build/static/js/' . basename($file);
        if (file_exists($buildJsPath)) {
            echo "Upload: static/js/" . basename($file) . " ... ";
            if (@ftp_put($conn, 'static/js/' . basename($file), $buildJsPath, FTP_ASCII)) {
                echo "✓\n";
                $file_count++;
                $uploaded = true;
            } else {
                echo "✗\n";
            }
        }
        
        // Check in build/static/css/
        $buildCssPath = __DIR__ . '/build/static/css/' . basename($file);
        if (file_exists($buildCssPath)) {
            echo "Upload: static/css/" . basename($file) . " ... ";
            if (@ftp_put($conn, 'static/css/' . basename($file), $buildCssPath, FTP_ASCII)) {
                echo "✓\n";
                $file_count++;
                $uploaded = true;
            } else {
                echo "✗\n";
            }
        }
        
        // Check in php/
        $phpPath = __DIR__ . '/php/' . basename($file);
        if (file_exists($phpPath)) {
            echo "Upload: php/" . basename($file) . " ... ";
            @ftp_mkdir($conn, 'php');
            if (@ftp_put($conn, 'php/' . basename($file), $phpPath, FTP_ASCII)) {
                echo "✓\n";
                $file_count++;
                $uploaded = true;
            } else {
                echo "✗\n";
            }
        }
        
        // Check in build/ root
        $buildRootPath = __DIR__ . '/build/' . basename($file);
        if (file_exists($buildRootPath)) {
            echo "Upload: " . basename($file) . " ... ";
            $mode = (preg_match('/\.(html|css|js|json|txt|map)$/i', $file)) ? FTP_ASCII : FTP_BINARY;
            if (@ftp_put($conn, basename($file), $buildRootPath, $mode)) {
                echo "✓\n";
                $file_count++;
                $uploaded = true;
            } else {
                echo "✗\n";
            }
        }
        
        if (!$uploaded) {
            // Check of het een source file is
            if (strpos($file, 'src/') !== false || strpos($file, './src/') !== false) {
                echo "⚠ Source bestand: $file\n";
                echo "   React source files moeten eerst gebouwd worden.\n";
                echo "   Run eerst: PUBLIC_URL=/ npm run build\n";
                echo "   Dan upload: php upload.php (volledige upload van build)\n";
            } else {
                echo "⚠ Bestand niet gevonden in build/, php/ of public/: $file\n";
            }
        }
    }
} else {
    // Volledige upload
    echo "Volledige upload modus\n\n";
    echo "Uploaden build naar site root (/)...\n\n";
    upload_dir($conn, __DIR__ . '/build', '', $file_count, $dir_count);

    // Nieuw: upload de statische pages naar root/pages (wijziging van pad)
    echo "\nUploaden public/pages naar /pages...\n\n";
    if (is_dir(__DIR__ . '/public/pages')) {
        upload_dir($conn, __DIR__ . '/public/pages', 'pages', $file_count, $dir_count);
    } else {
        echo "(overslaan) lokale map public/pages niet gevonden\n\n";
    }

    echo "\nUploaden PHP naar /php...\n";
    @ftp_mkdir($conn, 'php');

    // Upload alle PHP bestanden in php folder
    $phpFiles = glob(__DIR__ . '/php/*.php');
    foreach ($phpFiles as $phpFile) {
        $filename = basename($phpFile);
        echo "Upload: $filename ... ";
        if (@ftp_put($conn, 'php/' . $filename, $phpFile, FTP_ASCII)) {
            echo "✓\n";
            $file_count++;
        } else {
            echo "✗\n";
        }
    }
    
    // Upload bestuur directory (direct vanuit src_bestuur)
    echo "\nUploaden bestuur naar /bestuur...\n";
    if (is_dir(__DIR__ . '/src_bestuur')) {
        @ftp_mkdir($conn, 'bestuur');
        $bestuurFiles = glob(__DIR__ . '/src_bestuur/*');
        foreach ($bestuurFiles as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                echo "Upload: $filename ... ";
                $mode = (preg_match('/\.(html|css|js|json|txt)$/i', $filename)) ? FTP_ASCII : FTP_BINARY;
                if (@ftp_put($conn, 'bestuur/' . $filename, $file, $mode)) {
                    echo "✓\n";
                    $file_count++;
                } else {
                    echo "✗\n";
                }
            }
        }
    } else {
        echo "(overslaan) lokale map src_bestuur niet gevonden\n";
    }
}

$duration = round(microtime(true) - $start, 2);

echo "\n========================================\n";
echo "✓ Upload voltooid!\n";
echo "Bestanden: $file_count\n";
echo "Folders: $dir_count\n";
echo "Tijd: {$duration}s\n";
echo "========================================\n\n";
echo "Website: https://raakachterbos.be/\n";
echo "API: https://raakachterbos.be/php/calendar.php\n";

ftp_close($conn);
