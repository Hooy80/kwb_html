<?php
// Read-only script to list candidate files on the server that are often unnecessary to serve
// (e.g., local build zips, tests). This script does NOT delete anything.

header('Content-Type: application/json');

$root = __DIR__ . '/../';
$patterns = [
    '/\\.zip$/i',
    '/test_/',
    '/__tests__/',
    '/\\.test\\./i',
    '/test_console\\.js$/i',
    '/_notused\\//i'
];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$candidates = [];
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = str_replace($root, '', $file->getPathname());
    foreach ($patterns as $p) {
        if (preg_match($p, $path)) {
            $candidates[] = $path;
            break;
        }
    }
}

echo json_encode(['success' => true, 'candidates' => $candidates], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
