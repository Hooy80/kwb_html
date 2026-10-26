<?php
// SMTP Email functie voor one.com
require_once 'smtp_config.php';

function sendEmail($fromEmail, $fromName, $to, $subject, $message, $replyTo = null, $bccArray = []) {
    $smtp_host = SMTP_HOST;
    $smtp_port = SMTP_PORT;
    $smtp_user = SMTP_USER;
    $smtp_pass = get_smtp_password();
    $from = $fromEmail;
    
    if ($replyTo === null) {
        $replyTo = $fromEmail;
    }
    
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
        return false;
    }
    
    $read = function() use ($socket) {
        return fgets($socket, 515);
    };
    
    $send = function($cmd) use ($socket) {
        fputs($socket, $cmd . "\r\n");
    };
    
    try {
        $greeting = $read();
        
        $ehloHost = $_SERVER['SERVER_NAME'] ?? 'raakachterbos.be';
        $send("EHLO $ehloHost");
        
        while ($line = fgets($socket, 515)) {
            if (substr($line, 3, 1) === ' ') break;
        }
        
        $send("AUTH LOGIN");
        $authResp = $read();
        
        $send(base64_encode($smtp_user));
        $userResp = $read();
        
        $send(base64_encode($smtp_pass));
        $response = $read();
        
        if (strpos($response, '235') === false) {
            fclose($socket);
            return false;
        }
        
        $send("MAIL FROM: <{$from}>");
        $fromResp = $read();
        
        $send("RCPT TO: <{$to}>");
        $toResp = $read();
        
        foreach ($bccArray as $bccEmail) {
            $bccEmail = trim($bccEmail);
            if (!empty($bccEmail)) {
                $send("RCPT TO: <{$bccEmail}>");
                $bccResp = $read();
            }
        }
        
        $send("DATA");
        $dataResp = $read();
        
        $headers = "From: {$fromName} <{$from}>\r\n";
        $headers .= "Reply-To: {$replyTo}\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        
        $send($headers . "\r\n" . $message . "\r\n.");
        $sendResp = $read();
        
        $send("QUIT");
        $quitResp = $read();
        
        fclose($socket);
        return true;
        
    } catch (Exception $e) {
        if (is_resource($socket)) {
            fclose($socket);
        }
        return false;
    }
}
?>
