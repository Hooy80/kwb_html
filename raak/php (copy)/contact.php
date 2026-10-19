<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connectie
require_once __DIR__ . '/db_connect.php';

// Lees JSON data
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || !isset($data['naam']) || !isset($data['email']) || !isset($data['onderwerp']) || !isset($data['bericht'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Ontbrekende velden'
    ]);
    exit;
}

$naam = htmlspecialchars($data['naam']);
$email = htmlspecialchars($data['email']);
$onderwerp = htmlspecialchars($data['onderwerp']);
$bericht = htmlspecialchars($data['bericht']);

// Valideer email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Ongeldig email adres'
    ]);
    exit;
}

try {
    // Sla bericht op in database
    $stmt = $pdo->prepare("
        INSERT INTO contact_berichten (naam, email, onderwerp, bericht, datum_ontvangen)
        VALUES (:naam, :email, :onderwerp, :bericht, NOW())
    ");
    
    $stmt->execute([
        ':naam' => $naam,
        ':email' => $email,
        ':onderwerp' => $onderwerp,
        ':bericht' => $bericht
    ]);
    
    // Probeer ook email te versturen (optioneel)
    $to = 'raakmolachterbos@gmail.com';
    $subject = $onderwerp; // Onderwerp als email subject
    $emailBody = "Nieuw bericht via RAAK Achterbos website\n\n";
    $emailBody .= "Van: $naam\n";
    $emailBody .= "Email: $email\n";
    $emailBody .= "Onderwerp: $onderwerp\n\n";
    $emailBody .= "Bericht:\n$bericht\n";
    $headers = "From: noreply@raakachterbos.be\r\n";
    $headers .= "Reply-To: $email\r\n";
    
    // Email wordt verstuurd maar we reageren niet op het resultaat
    @mail($to, $subject, $emailBody, $headers);
    
    echo json_encode([
        'success' => true,
        'message' => 'Bericht ontvangen! We nemen zo snel mogelijk contact op.'
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>
