<?php
// Script om wachtwoord correct te encrypten op de server

$password = '15_Kapellekens';
$server_name = $_SERVER['SERVER_NAME'] ?? 'raakachterbos.be';
$encryption_key = 'raak_smtp_' . substr(md5($server_name), 0, 16);

echo "Server Name: $server_name\n";
echo "Encryption Key: $encryption_key\n\n";

// XOR encrypt
$encrypted = '';
for ($i = 0; $i < strlen($password); $i++) {
    $encrypted .= chr(ord($password[$i]) ^ ord($encryption_key[$i % strlen($encryption_key)]));
}

$encrypted_base64 = base64_encode($encrypted);

echo "Encrypted password: $encrypted_base64\n\n";

// Test decrypt
$decrypted = '';
$dec_data = base64_decode($encrypted_base64);
for ($i = 0; $i < strlen($dec_data); $i++) {
    $decrypted .= chr(ord($dec_data[$i]) ^ ord($encryption_key[$i % strlen($encryption_key)]));
}

echo "Test decrypt: $decrypted\n";
echo "Match: " . ($decrypted === $password ? '✓ OK' : '✗ FOUT') . "\n";
