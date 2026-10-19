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
    $token = $input['token'] ?? '';
    $newPassword = $input['password'] ?? '';

    if (empty($token) || empty($newPassword)) {
        echo json_encode(['success' => false, 'error' => 'Token en wachtwoord zijn verplicht']);
        exit;
    }

    // Check token
    $stmt = $pdo->prepare("
        SELECT user_id FROM password_resets 
        WHERE token = :token AND expires_at > NOW()
    ");
    $stmt->execute(['token' => $token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        echo json_encode(['success' => false, 'error' => 'Ongeldige of verlopen reset link']);
        exit;
    }

    // Update wachtwoord
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE bestuur SET paswoord = :paswoord WHERE id = :id");
    $stmt->execute(['paswoord' => $hashedPassword, 'id' => $reset['user_id']]);

    // Verwijder gebruikte token
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
    $stmt->execute(['token' => $token]);

    echo json_encode(['success' => true, 'message' => 'Wachtwoord succesvol gewijzigd']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}
