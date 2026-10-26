<?php
header('Content-Type: application/json; charset=utf-8');
// Return JSON array of activity images in ../public/activities
$dir = __DIR__ . '/../public/activities';
$extPattern = '/\.(jpe?g|png|gif|bmp|tiff?)$/i';
$out = [];
if (!is_dir($dir)) {
  echo json_encode([]);
  exit;
}
$files = scandir($dir);
foreach ($files as $f) {
  if ($f === '.' || $f === '..') continue;
  if (!preg_match($extPattern, $f)) continue;
  // extract leading date yyyymmdd
  if (!preg_match('/^(\d{8})/', $f, $m)) continue;
  $ymd = $m[1];
  $year = substr($ymd, 0, 4);
  $month = substr($ymd, 4, 2);
  $day = substr($ymd, 6, 2);
  $date = "$year-$month-$day";
  $label = preg_replace('/^\d+_?/', '', $f);
  $label = preg_replace($extPattern, '', $label);
  $label = preg_replace('/[-_]+/', ' ', $label);
  $label = trim($label);
  $out[] = [
    'file' => $f,
    'date' => $date,
    'label' => $label
  ];
}
// sort by date asc
usort($out, function($a, $b) {
  return strcmp($a['date'], $b['date']);
});

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

