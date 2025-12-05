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

if (!$data || !isset($data['naam']) || !isset($data['email']) || !isset($data['aantal_personen']) || !isset($data['aantal_vegi']) || !isset($data['opmerking'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Ontbrekende velden'
    ]);
    exit;
}

$naam = htmlspecialchars($data['naam']);
$email = htmlspecialchars($data['email']);
$aantal_personen = htmlspecialchars($data['aantal_personen']);
$aantal_vegi = htmlspecialchars($data['aantal_vegi']);
$opmerking = htmlspecialchars($data['opmerking']);
$mail = isset($data['mail']) ? (int)$data['mail'] : 0;

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
    // Zoek id van "Smakelijk Wandelen" activiteit dit jaar (case insensitive)
    $currentYear = date('Y');
    $stmtActivity = $pdo->prepare("
        SELECT id 
        FROM calendar 
        WHERE LOWER(name) LIKE LOWER('%smakelijk%wandelen%') 
        AND YEAR(date) = :year
        ORDER BY date DESC
        LIMIT 1
    ");
    $stmtActivity->execute([':year' => $currentYear]);
    $activity = $stmtActivity->fetch();
    
    if (!$activity) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Geen Smakelijk Wandelen activiteit gevonden voor dit jaar'
        ]);
        exit;
    }
    
    $id_act = $activity['id'];
    $onderwerp = "Inschrijving smakelijk wandelen " . $currentYear;
    
    // Check of email al bestaat, update mail kolom indien nodig
    $stmtCheck = $pdo->prepare("SELECT id FROM smakelijk_wandelen WHERE email = :email");
    $stmtCheck->execute([':email' => $email]);
    $existing = $stmtCheck->fetch();
    
    if ($existing) {
        // Update mail kolom voor bestaand email adres
        $stmtUpdateMail = $pdo->prepare("UPDATE smakelijk_wandelen SET mail = :mail WHERE email = :email");
        $stmtUpdateMail->execute([':mail' => $mail, ':email' => $email]);
    }
    
    // Sla inschrijving op in database
    $stmt = $pdo->prepare("
        INSERT INTO smakelijk_wandelen (id_act, naam, email, aantal_personen, aantal_vegi, opmerking, mail, inschrijfdatum)
        VALUES (:id_act, :naam, :email, :aantal_personen, :aantal_vegi, :opmerking, :mail, NOW())
    ");
    
    $stmt->execute([
        ':id_act' => $id_act,
        ':naam' => $naam,
        ':email' => $email,
        ':aantal_personen' => $aantal_personen,
        ':aantal_vegi' => $aantal_vegi,
        ':opmerking' => $opmerking,
        ':mail' => $mail
    ]);
    
    // Probeer ook email te versturen (optioneel)
    $to = 'raakmolachterbos@gmail.com';
    $subject = $onderwerp;
    $emailBody = "Nieuwe inschrijving Smakelijk Wandelen $currentYear\n\n";
    $emailBody .= "Van: $naam\n";
    $emailBody .= "Email: $email\n";
    $emailBody .= "Aantal personen: $aantal_personen\n";
    $emailBody .= "Aantal vegetarisch: $aantal_vegi\n\n";
    $emailBody .= "Opmerking:\n$opmerking\n";
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
