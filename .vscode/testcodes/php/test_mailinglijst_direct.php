<?php
// Direct test met exact dezelfde parameters als mailinglijst
require_once __DIR__ . '/smtp_mail.php';

echo "<h1>Mailinglijst Direct Test</h1>";
echo "<pre>";

// Exact dezelfde parameters als mailinglijst.php zou gebruiken
$fromEmail = 'info@raakachterbos.be';
$fromName = 'RAAK Achterbos';
$to = 'raakmolachterbos@gmail.com';
$replyTo = 'raakmolachterbos@gmail.com';
$onderwerp = 'TEST Smakelijk wandelen mail';
$bericht = 'Mail mag weg';

$fullMessage = $bericht . "\n\n---\n";
$fullMessage .= "Deze mail werd verzonden vanaf info@raakachterbos.be (NOREPLY).\n";
$fullMessage .= "Voor vragen kun je terecht via raakmolachterbos@gmail.com of het contactformulier op de website.\n";

// Test met array zoals JavaScript doorstuurt
$emails = ['wim.hooyberghs80@gmail.com'];

echo "From: $fromEmail ($fromName)\n";
echo "To: $to\n";
echo "Reply-To: $replyTo\n";
echo "Onderwerp: $onderwerp\n";
echo "BCC emails array: " . print_r($emails, true) . "\n";
echo "Email count: " . count($emails) . "\n";
echo "\n";

echo "Calling sendEmail()...\n\n";

$result = sendEmail($fromEmail, $fromName, $to, $onderwerp, $fullMessage, $replyTo, $emails);

if ($result) {
    echo "\n✓ Email succesvol verzonden!\n";
} else {
    echo "\n✗ Email verzenden mislukt!\n";
}

echo "</pre>";
?>
