<?php
require_once 'smtp_config.php';

echo "=== SMTP CONNECTION TEST ===\n\n";

// Test 1: Check configuratie
echo "1. SMTP Configuratie:\n";
echo "   Host: " . SMTP_HOST . "\n";
echo "   Port: " . SMTP_PORT . "\n";
echo "   User: " . SMTP_USER . "\n";
echo "   Pass: " . (get_smtp_password() === '15Chapels@bos' ? '✓ Correct gedecrypt' : '✗ Decrypt FOUT') . "\n\n";

// Test 2: DNS lookup
echo "2. DNS Lookup:\n";
$ip = gethostbyname(SMTP_HOST);
echo "   " . SMTP_HOST . " -> " . $ip . "\n";
if ($ip === SMTP_HOST) {
    echo "   ✗ DNS lookup FAILED\n\n";
    exit;
} else {
    echo "   ✓ DNS OK\n\n";
}

// Test 3: SSL Socket verbinding
echo "3. SSL Socket Connection:\n";
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

$conn = @stream_socket_client(
    'ssl://' . SMTP_HOST . ':' . SMTP_PORT,
    $errno,
    $errstr,
    30,
    STREAM_CLIENT_CONNECT,
    $context
);

if (!$conn) {
    echo "   ✗ Connection FAILED: $errstr ($errno)\n\n";
    exit;
}
echo "   ✓ Connected to " . SMTP_HOST . ":" . SMTP_PORT . "\n\n";

// Test 4: SMTP Handshake
echo "4. SMTP Handshake:\n";

// Lees greeting
$response = fgets($conn, 515);
echo "   < " . trim($response) . "\n";
if (substr($response, 0, 3) !== '220') {
    echo "   ✗ Invalid greeting\n";
    fclose($conn);
    exit;
}

// EHLO
fwrite($conn, "EHLO " . SMTP_HOST . "\r\n");
$response = '';
while ($line = fgets($conn, 515)) {
    $response .= $line;
    if (substr($line, 3, 1) === ' ') break;
}
echo "   > EHLO\n";
echo "   < " . trim($response) . "\n";

// AUTH LOGIN
echo "\n5. SMTP Authentication:\n";
fwrite($conn, "AUTH LOGIN\r\n");
$response = fgets($conn, 515);
echo "   > AUTH LOGIN\n";
echo "   < " . trim($response) . "\n";

if (substr($response, 0, 3) !== '334') {
    echo "   ✗ AUTH LOGIN not supported\n";
    fclose($conn);
    exit;
}

// Username
$username_b64 = base64_encode(SMTP_USER);
fwrite($conn, $username_b64 . "\r\n");
$response = fgets($conn, 515);
echo "   > [username base64]\n";
echo "   < " . trim($response) . "\n";

if (substr($response, 0, 3) !== '334') {
    echo "   ✗ Username rejected\n";
    fclose($conn);
    exit;
}

// Password
$password_b64 = base64_encode(get_smtp_password());
fwrite($conn, $password_b64 . "\r\n");
$response = fgets($conn, 515);
echo "   > [password base64]\n";
echo "   < " . trim($response) . "\n";

if (substr($response, 0, 3) === '235') {
    echo "   ✓ Authentication SUCCESS!\n\n";
} else {
    echo "   ✗ Authentication FAILED\n";
    echo "   Error: " . trim($response) . "\n\n";
    fclose($conn);
    exit;
}

// Test 6: Test email versturen
echo "6. Test Email:\n";
$test_email = SMTP_USER; // Stuur naar jezelf

fwrite($conn, "MAIL FROM: <" . SMTP_USER . ">\r\n");
$response = fgets($conn, 515);
echo "   > MAIL FROM\n";
echo "   < " . trim($response) . "\n";

fwrite($conn, "RCPT TO: <$test_email>\r\n");
$response = fgets($conn, 515);
echo "   > RCPT TO: $test_email\n";
echo "   < " . trim($response) . "\n";

fwrite($conn, "DATA\r\n");
$response = fgets($conn, 515);
echo "   > DATA\n";
echo "   < " . trim($response) . "\n";

$email_content = "Subject: SMTP Test\r\n";
$email_content .= "From: " . SMTP_FROM_NAME . " <" . SMTP_USER . ">\r\n";
$email_content .= "To: $test_email\r\n";
$email_content .= "Content-Type: text/plain; charset=UTF-8\r\n";
$email_content .= "\r\n";
$email_content .= "Dit is een test email van het SMTP systeem.\r\n";
$email_content .= "Tijd: " . date('Y-m-d H:i:s') . "\r\n";
$email_content .= ".\r\n";

fwrite($conn, $email_content);
$response = fgets($conn, 515);
echo "   < " . trim($response) . "\n";

if (substr($response, 0, 3) === '250') {
    echo "   ✓ Email SENT!\n\n";
} else {
    echo "   ✗ Email send FAILED\n\n";
}

// QUIT
fwrite($conn, "QUIT\r\n");
$response = fgets($conn, 515);
echo "   > QUIT\n";
echo "   < " . trim($response) . "\n";

fclose($conn);

echo "\n=== TEST COMPLETED ===\n";
