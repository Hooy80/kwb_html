<?php
// SMTP Email functie voor one.com
require_once 'smtp_config.php';

function sendEmail($to, $toName, $subject, $message) {
    // Gebruik configuratie uit smtp_config.php
    $smtp_host = SMTP_HOST;
    $smtp_port = SMTP_PORT;
    $smtp_user = SMTP_USER;
    $smtp_pass = get_smtp_password(); // Decrypt wachtwoord
    $from = SMTP_FROM;
    $fromName = SMTP_FROM_NAME;
    
    // Open SSL socket connectie
    $socket = @stream_socket_client(
        "ssl://{$smtp_host}:{$smtp_port}",
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ])
    );
    
    if (!$socket) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }
    
    // Helper functie om SMTP response te lezen
    $read = function() use ($socket) {
        return fgets($socket, 515);
    };
    
    // Helper functie om SMTP command te versturen
    $send = function($cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };
    
    try {
        // Lees server greeting
        $read();
        
        // EHLO
        $send("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'raakachterbos.be'));
        // Lees multi-line EHLO response
        while ($line = fgets($socket, 515)) {
            if (substr($line, 3, 1) === ' ') break;
        }
        
        // AUTH LOGIN
        $send("AUTH LOGIN");
        $read();
        
        $send(base64_encode($smtp_user));
        $read();
        
        $send(base64_encode($smtp_pass));
        $response = $read();
        if (strpos($response, '235') === false) {
            error_log("SMTP authentication failed: $response");
            fclose($socket);
            return false;
        }
        
        // MAIL FROM
        $send("MAIL FROM: <{$from}>");
        $read();
        
        // RCPT TO
        $send("RCPT TO: <{$to}>");
        $read();
        
        // DATA
        $send("DATA");
        $read();
        
        // Email headers en body
        $headers = "From: {$fromName} <{$from}>\r\n";
        $headers .= "Reply-To: {$from}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "To: {$toName} <{$to}>\r\n";
        
        $send($headers . "\r\n" . $message . "\r\n.");
        $read();
        
        // QUIT
        $send("QUIT");
        $read();
        
        fclose($socket);
        return true;
        
    } catch (Exception $e) {
        error_log("SMTP error: " . $e->getMessage());
        if (is_resource($socket)) {
            fclose($socket);
        }
        return false;
    }
}
