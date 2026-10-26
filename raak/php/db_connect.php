<?php
// Database configuratie voor one.com
$host = 'localhost';
$dbname = 'cftvsokaf_raakachterbos_beraak';
$username = 'cftvsokaf_raakachterbos_beraak';
$password = '15chapels@bos';

try {
    // Database connectie
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch(PDOException $e) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'error' => 'Database connectie mislukt: ' . $e->getMessage()
    ]));
}
?>
