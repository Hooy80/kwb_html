<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Probeer verschillende paden
$possiblePaths = [
    __DIR__ . '/../kookles',
    __DIR__ . '/../public/kookles',
    __DIR__ . '/../build/kookles'
];

$foldersDir = null;
foreach ($possiblePaths as $path) {
    if (is_dir($path)) {
        $foldersDir = $path;
        break;
    }
}

$folders = [];

if ($foldersDir && is_dir($foldersDir)) {
    $files = scandir($foldersDir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            // Parse filename: yyyy - tekst.pdf
            $year = null;
            $displayName = null;
            
            // Format: yyyy - tekst.pdf
            if (preg_match('/^(\d{4})\s*-\s*(.+)\.pdf$/i', $file, $matches)) {
                $year = $matches[1];
                $displayName = $year . ' ' . trim($matches[2]);
            }
            
            if ($year && $displayName) {
                // Check voor duplicaten
                $counter = 1;
                
                // Tel hoeveel files met dezelfde displayName er zijn
                foreach ($folders as $existing) {
                    if ($existing['displayName'] === $displayName) {
                        $counter++;
                    }
                }
                
                $finalDisplayName = $displayName;
                if ($counter > 1) {
                    $finalDisplayName .= ' (' . $counter . ')';
                }
                
                $folders[] = [
                    'file' => $file,
                    'year' => intval($year),
                    'displayName' => $finalDisplayName,
                    'sortKey' => $year . sprintf('%03d', $counter)
                ];
            }
        }
    }
    
    // Sorteer van nieuw naar oud (desc)
    usort($folders, function($a, $b) {
        return strcmp($b['sortKey'], $a['sortKey']);
    });
}

echo json_encode($folders);
?>
