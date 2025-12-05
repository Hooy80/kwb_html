<?php
// SMTP configuratie voor one.com
// Wachtwoord wordt encrypted opgeslagen

define('SMTP_HOST', 'send.one.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'info@raakachterbos.be');

// Encrypted wachtwoord - gebruik base64 + XOR encryption met server-specific key
// Om wachtwoord te encrypten: base64_encode(encrypt_string('jouw_wachtwoord', $_SERVER['SERVER_NAME']))
define('SMTP_PASS_ENCRYPTED', 'Q1QiAz4DCBgDH1JaEQ==');
define('SMTP_ENCRYPTION_KEY', 'raak_smtp_' . substr(md5($_SERVER['SERVER_NAME'] ?? 'raakachterbos.be'), 0, 16));

define('SMTP_FROM', 'info@raakachterbos.be');
define('SMTP_FROM_NAME', 'RAAK Achterbos');

// Decrypt functie
function get_smtp_password() {
    $encrypted = base64_decode(SMTP_PASS_ENCRYPTED);
    $key = SMTP_ENCRYPTION_KEY;
    $decrypted = '';
    for ($i = 0; $i < strlen($encrypted); $i++) {
        $decrypted .= chr(ord($encrypted[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return $decrypted;
}
