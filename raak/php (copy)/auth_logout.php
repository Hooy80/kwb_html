<?php
// Sessie configuratie
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Destroy session
session_destroy();

echo json_encode(['success' => true]);
