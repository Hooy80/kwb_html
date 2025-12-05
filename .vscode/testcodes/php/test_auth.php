<?php
// Simpele test om te zien of de API bereikbaar is
header('Content-Type: application/json');
echo json_encode([
    'test' => 'API is bereikbaar',
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
    'raw_input' => file_get_contents('php://input'),
    'post' => $_POST,
    'get' => $_GET
]);
