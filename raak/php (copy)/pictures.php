<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Probeer verschillende paden
$possiblePaths = [
    __DIR__ . '/../pictures',           // Direct naast php folder
    __DIR__ . '/../public/pictures',    // In public folder
    __DIR__ . '/../build/pictures'      // In build folder
];

$picturesDir = null;
foreach ($possiblePaths as $path) {
    if (is_dir($path)) {
        $picturesDir = $path;
        break;
    }
}

$folders = [];

// Lees alle submappen
if ($picturesDir && is_dir($picturesDir)) {
    $items = scandir($picturesDir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $picturesDir . '/' . $item;
        
        if (is_dir($fullPath)) {
            // Haal alle foto's in deze map op
            $photos = [];
            $photoFiles = scandir($fullPath);
            
            foreach ($photoFiles as $photo) {
                if ($photo === '.' || $photo === '..') continue;
                
                $ext = strtolower(pathinfo($photo, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $photos[] = $photo;
                }
            }
            
            // Sorteer foto's alfabetisch
            sort($photos);
            
            if (!empty($photos)) {
                $folders[] = [
                    'name' => $item,
                    'photoCount' => count($photos),
                    'firstPhoto' => $photos[0],
                    'photos' => $photos
                ];
            }
        }
    }
}

// Sorteer folders alfabetisch
usort($folders, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

echo json_encode($folders);
?>
