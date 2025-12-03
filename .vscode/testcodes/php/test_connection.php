<?php
// Test verschillende wachtwoorden
$host = 'raakachterbos.be.mysql';
$dbname = 'raakachterbos_beraak';
$username = 'raakachterbos_beraak';

$passwords = [
    // Achterbos variaties
    'Achterbos_76',
    'Raakachterbos.1',
    'Raakachterbos_76',
    'achterbos_76',
    'raakachterbos_76',
    
    // 15chapel variaties
    '15chapel@bos',
    '15Chapel@bos',
    '15chapel@Bos',
    '15Chapel@Bos',
    '15chapel_@bos',
    '15Chapel_@bos',
    '15chapel_@Bos',
    '15Chapel_@Bos',
    '15chapel@bos_',
    '15Chapel@bos_',
    '15chapel@Bos_',
    '15Chapel@Bos_',
    '15chapel@bos!',
    '15Chapel@bos!',
    '15chapel@Bos!',
    '15Chapel@Bos!',
    
    // 15chapels variaties (met s)
    '15chapels@bos',
    '15Chapels@bos',
    '15chapels@Bos',
    '15Chapels@Bos',
    '15chapels_@bos',
    '15Chapels_@bos',
    '15chapels_@Bos',
    '15Chapels_@Bos',
    '15chapels@bos_',
    '15Chapels@bos_',
    '15chapels@Bos_',
    '15Chapels@Bos_',
    '15chapels@bos!',
    '15Chapels@bos!',
    '15chapels@Bos!',
    '15Chapels@Bos!',
];

echo "Testing database connection...\n\n";
echo "Host: $host\n";
echo "Database: $dbname\n";
echo "Username: $username\n\n";

foreach ($passwords as $password) {
    echo "Testing password: " . str_repeat('*', strlen($password)) . " (" . substr($password, 0, 3) . "...)\n";
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "✓ SUCCESS! Connection established with this password!\n";
        echo "Password: $password\n\n";
        
        // Test query
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tables in database:\n";
        foreach ($tables as $table) {
            echo "  - $table\n";
        }
        
        exit(0);
        
    } catch (PDOException $e) {
        echo "✗ Failed: " . $e->getMessage() . "\n\n";
    }
}

echo "None of the passwords worked. Check your one.com control panel for the correct password.\n";
