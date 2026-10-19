<?php
// Sessie configuratie
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_connect.php';

// Log request voor debugging
error_log("Login attempt - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Raw input: " . file_get_contents('php://input'));

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Check if JSON decode worked
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit;
    }
    
    $login = $input['login'] ?? '';
    $password = $input['password'] ?? '';

    error_log("Login: $login, Password length: " . strlen($password));

    if (empty($login) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Login en wachtwoord zijn verplicht']);
        exit;
    }

    // Haal gebruiker op uit database
    $stmt = $pdo->prepare("
        SELECT id, naam, voornaam, login, email, paswoord, functie, actief 
        FROM bestuur 
        WHERE login = :login
    ");
    $stmt->execute(['login' => $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Ongeldige login of wachtwoord']);
        exit;
    }

    // Check of account actief is
    if ($user['actief'] != 1) {
        echo json_encode(['success' => false, 'error' => 'Account is gedeactiveerd']);
        exit;
    }

    // Verifieer wachtwoord
    if (!password_verify($password, $user['paswoord'])) {
        error_log("Password verification failed for user: $login");
        echo json_encode(['success' => false, 'error' => 'Ongeldige login of wachtwoord']);
        exit;
    }

    error_log("Login successful for user: $login");

    // Login succesvol - sla sessie op
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_login'] = $user['login'];
    $_SESSION['user_functie'] = $user['functie'];
    $_SESSION['user_voornaam'] = $user['voornaam'];
    $_SESSION['user_naam'] = $user['naam'];
    $_SESSION['user_email'] = $user['email'];

    // Verwijder gevoelige data voor response
    unset($user['paswoord']);

    echo json_encode([
        'success' => true,
        'user' => $user,
        'functie' => $user['functie']
    ]);

} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}
