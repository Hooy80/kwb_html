<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Probeer verschillende paden
$possiblePaths = [
    __DIR__ . '/../folders',
    __DIR__ . '/../public/folders',
    __DIR__ . '/../build/folders'
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
    
    $monthNames = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maart',
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Augustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'December'
    ];
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            // Parse filename: yyyy_mm.pdf of yyyy_mm_extra.pdf
            if (preg_match('/^(\d{4})_(\d{2})/', $file, $matches)) {
                $year = $matches[1];
                $month = $matches[2];
                
                $monthName = isset($monthNames[$month]) ? $monthNames[$month] : $month;
                $displayName = $monthName . ' ' . $year;
                
                // Check voor duplicaten
                $baseKey = $year . '_' . $month;
                $counter = 1;
                $uniqueKey = $baseKey;
                
                // Tel hoeveel files met dezelfde jaar_maand er zijn
                foreach ($folders as $existing) {
                    if (strpos($existing['file'], $baseKey) === 0) {
                        $counter++;
                    }
                }
                
                if ($counter > 1) {
                    $displayName .= ' (' . $counter . ')';
                }
                
                $folders[] = [
                    'file' => $file,
                    'year' => intval($year),
                    'month' => intval($month),
                    'displayName' => $displayName,
                    'sortKey' => $year . $month . sprintf('%02d', $counter)
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
