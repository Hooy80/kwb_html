<?php
// Sessie configuratie
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check of gebruiker ingelogd is
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

// Haal sessie data op
echo json_encode([
    'success' => true,
    'user' => [
        'id' => $_SESSION['user_id'],
        'login' => $_SESSION['user_login'],
        'functie' => $_SESSION['user_functie'],
        'voornaam' => $_SESSION['user_voornaam'] ?? '',
        'naam' => $_SESSION['user_naam'] ?? '',
        'email' => $_SESSION['user_email'] ?? ''
    ]
]);
