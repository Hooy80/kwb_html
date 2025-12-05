<?php
// Test SMTP functionaliteit
require_once __DIR__ . '/smtp_mail.php';

echo "<h1>SMTP Test</h1>";
echo "<pre>";

$fromEmail = 'info@raakachterbos.be';
$fromName = 'RAAK Achterbos Test';
$to = 'raakmolachterbos@gmail.com';
$subject = 'Test email van mailinglijst';
$message = "Dit is een test email.\n\nVerzonden op: " . date('Y-m-d H:i:s');
$replyTo = 'raakmolachterbos@gmail.com';
$bcc = ['wim.hooyberghs80@gmail.com']; // Test BCC

echo "Verzenden test email...\n";
echo "From: $fromEmail ($fromName)\n";
echo "To: $to\n";
echo "Reply-To: $replyTo\n";
echo "BCC: " . implode(', ', $bcc) . "\n";
echo "Subject: $subject\n\n";

$result = sendEmail($fromEmail, $fromName, $to, $subject, $message, $replyTo, $bcc);

if ($result) {
    echo "\n✓ Email succesvol verzonden!\n";
} else {
    echo "\n✗ Email verzenden mislukt!\n";
    echo "\nCheck de PHP error logs voor details.\n";
}

echo "</pre>";
?>
