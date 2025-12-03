<?php
// Test script om te checken of PHP de foto kan vinden

$activitiesDir = __DIR__ . '/../activities/';

echo "PHP script locatie: " . __DIR__ . "\n";
echo "Activities folder pad: " . $activitiesDir . "\n";
echo "Activities folder bestaat: " . (is_dir($activitiesDir) ? 'JA' : 'NEE') . "\n\n";

if (is_dir($activitiesDir)) {
    echo "Bestanden in activities:\n";
    $files = scandir($activitiesDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "  - $file\n";
        }
    }
}

// Test specifiek bestand
$testFile = $activitiesDir . '20251128_Dropping.jpg';
echo "\nTest bestand: $testFile\n";
echo "Bestaat: " . (file_exists($testFile) ? 'JA' : 'NEE') . "\n";
