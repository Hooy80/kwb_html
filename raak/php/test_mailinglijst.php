<?php
// Test script om mailinglijst POST te debuggen
session_start();

echo "<h1>Mailinglijst POST Debug</h1>";

echo "<h2>Session Info</h2>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n";
echo "Session cookie name: " . session_name() . "\n";
echo "Cookie path: " . ini_get('session.cookie_path') . "\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "User Functie: " . ($_SESSION['user_functie'] ?? 'NOT SET') . "\n";
echo "\nAll SESSION data:\n";
print_r($_SESSION);
echo "\nAll COOKIES:\n";
print_r($_COOKIE);
echo "</pre>";

echo "<h2>Test POST Request</h2>";

if ($_SESSION['user_functie'] ?? '' === 'admin' || $_SESSION['user_functie'] ?? '' === 'bestuur') {
    echo "<p style='color:green'>✓ Authentication OK</p>";
    
    // Simulate POST
    $testData = [
        'action' => 'send',
        'onderwerp' => 'TEST',
        'bericht' => 'Test bericht',
        'emails' => ['wim.hooyberghs80@gmail.com']
    ];
    
    echo "<h3>Test Data:</h3>";
    echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";
    
    // Test if we can load smtp_mail.php
    require_once __DIR__ . '/smtp_mail.php';
    echo "<p style='color:green'>✓ smtp_mail.php loaded</p>";
    
    echo "<h3>Simulating Email Send</h3>";
    $fromEmail = 'info@raakachterbos.be';
    $fromName = 'RAAK Achterbos';
    $replyTo = 'raakmolachterbos@gmail.com';
    $onderwerp = 'TEST';
    $bericht = 'Test bericht';
    $emails = ['wim.hooyberghs80@gmail.com'];
    
    $unsubscribeUrl = 'https://raakachterbos.be/php/unsubscribe.php';
    $fullMessage = $bericht . "\n\n---\n";
    $fullMessage .= "Deze mail werd verzonden vanaf info@raakachterbos.be (NOREPLY).\n";
    $fullMessage .= "Voor vragen kun je terecht via raakmolachterbos@gmail.com of het contactformulier op de website.\n\n";
    $fullMessage .= "Wil je geen emails meer ontvangen? Klik hier om uit te schrijven:\n";
    $fullMessage .= $unsubscribeUrl . "\n";
    
    echo "<p>Calling sendEmail()...</p>";
    $success = sendEmail($fromEmail, $fromName, $replyTo, $onderwerp, $fullMessage, $replyTo, $emails);
    
    if ($success) {
        echo "<p style='color:green'>✓ Email sent successfully!</p>";
    } else {
        echo "<p style='color:red'>✗ Email send failed!</p>";
    }
    
} else {
    echo "<p style='color:red'>✗ Not authenticated as admin/bestuur</p>";
    echo "<p>Please login first at: <a href='/bestuur/'>Bestuur Login</a></p>";
}
?>
