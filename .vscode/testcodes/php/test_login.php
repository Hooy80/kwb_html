<?php
// Test script om login te debuggen
require_once 'db_connect.php';

$login = 'Hooy80';
$password = 'Sluis@bos_2026';

echo "=== LOGIN DEBUG TEST ===\n\n";

// Check database verbinding
echo "1. Database verbinding: ";
try {
    $pdo->query('SELECT 1');
    echo "✓ OK\n\n";
} catch (Exception $e) {
    echo "✗ FOUT: " . $e->getMessage() . "\n\n";
    exit;
}

// Zoek gebruiker
echo "2. Zoeken naar gebruiker '$login':\n";
$stmt = $pdo->prepare("
    SELECT id, naam, voornaam, login, email, paswoord, functie, actief 
    FROM bestuur 
    WHERE login = :login
");
$stmt->execute(['login' => $login]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "   ✗ Gebruiker niet gevonden\n\n";
    echo "3. Alle gebruikers in database:\n";
    $stmt = $pdo->query("SELECT id, login, naam, voornaam, actief FROM bestuur");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        echo "   - ID: {$u['id']}, Login: '{$u['login']}', Naam: {$u['voornaam']} {$u['naam']}, Actief: {$u['actief']}\n";
    }
    exit;
}

echo "   ✓ Gebruiker gevonden:\n";
echo "   - ID: {$user['id']}\n";
echo "   - Naam: {$user['voornaam']} {$user['naam']}\n";
echo "   - Email: {$user['email']}\n";
echo "   - Functie: " . ($user['functie'] ?? 'GEEN') . "\n";
echo "   - Actief: {$user['actief']}\n";
echo "   - Paswoord hash: " . substr($user['paswoord'], 0, 20) . "...\n\n";

// Check actief status
echo "3. Check actief status: ";
if ($user['actief'] != 1) {
    echo "✗ Account niet actief (actief = {$user['actief']})\n\n";
    exit;
}
echo "✓ Account is actief\n\n";

// Test password verify
echo "4. Test wachtwoord verificatie:\n";
echo "   Ingevoerd wachtwoord: '$password'\n";
echo "   Hash in database: " . $user['paswoord'] . "\n";

if (password_verify($password, $user['paswoord'])) {
    echo "   ✓ Wachtwoord is CORRECT!\n\n";
    echo "=== LOGIN ZOU MOETEN WERKEN ===\n";
} else {
    echo "   ✗ Wachtwoord is FOUT!\n\n";
    
    // Genereer nieuwe hash voor vergelijking
    echo "5. Nieuwe hash genereren:\n";
    $newHash = password_hash($password, PASSWORD_BCRYPT);
    echo "   Nieuwe hash: $newHash\n\n";
    
    echo "   UPDATE SQL om wachtwoord te resetten:\n";
    echo "   UPDATE bestuur SET paswoord = '$newHash' WHERE login = '$login';\n\n";
}
