<?php
// Ultra simple debug - no session, no auth, just echo what we receive
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$input = file_get_contents('php://input');

echo json_encode([
    'success' => true,
    'method' => $method,
    'input_raw' => $input,
    'input_decoded' => json_decode($input, true),
    'post' => $_POST,
    'get' => $_GET,
    'server_request_uri' => $_SERVER['REQUEST_URI'] ?? 'not set'
]);
?>
