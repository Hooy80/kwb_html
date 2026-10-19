<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_connect.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';

    if (empty($email)) {
        echo json_encode(['success' => false, 'error' => 'Email is verplicht']);
        exit;
    }

    // Check of gebruiker bestaat
    $stmt = $pdo->prepare("SELECT id, voornaam, naam, login FROM bestuur WHERE email = :email AND actief = 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Toon altijd success om email enumeration te voorkomen
        echo json_encode(['success' => true, 'message' => 'Als dit emailadres bestaat, ontvangt u een reset link']);
        exit;
    }

    // Genereer reset token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Sla token op (je moet een password_resets tabel maken)
    $stmt = $pdo->prepare("
        INSERT INTO password_resets (user_id, token, expires_at) 
        VALUES (:user_id, :token, :expires_at)
        ON DUPLICATE KEY UPDATE token = :token, expires_at = :expires_at
    ");
    $stmt->execute([
        'user_id' => $user['id'],
        'token' => $token,
        'expires_at' => $expires
    ]);

    // Stuur email
    $resetLink = "https://raakachterbos.be/bestuur/reset-password?token=$token";
    $to = $email;
    $subject = "Wachtwoord reset - RAAK Bestuur";
    $message = "Hallo {$user['voornaam']} {$user['naam']},\n\n";
    $message .= "Je hebt een wachtwoord reset aangevraagd.\n\n";
    $message .= "Klik op deze link om je wachtwoord te resetten:\n";
    $message .= "$resetLink\n\n";
    $message .= "Deze link is 1 uur geldig.\n\n";
    $message .= "Als je deze reset niet hebt aangevraagd, negeer dan deze email.\n\n";
    $message .= "Groeten,\nRAAK Achterbos";

    $headers = "From: noreply@raakachterbos.be\r\n";
    $headers .= "Reply-To: raakmolachterbos@gmail.com\r\n";

    mail($to, $subject, $message, $headers);

    echo json_encode(['success' => true, 'message' => 'Als dit emailadres bestaat, ontvangt u een reset link']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}
