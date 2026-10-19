<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database connectie
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/smtp_mail.php';

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
    
    $adminEmail = 'raakmolachterbos@gmail.com';
    $fromEmail = 'info@raakachterbos.be';
    $fromName = 'RAAK Achterbos';

    $adminSubject = $onderwerp;
    $adminEmailBody = "Nieuwe inschrijving Smakelijk Wandelen $currentYear\n\n";
    $adminEmailBody .= "Van: $naam\n";
    $adminEmailBody .= "Email: $email\n";
    $adminEmailBody .= "Aantal personen: $aantal_personen\n";
    $adminEmailBody .= "Aantal vegetarisch: $aantal_vegi\n\n";
    $adminEmailBody .= "Opmerking:\n$opmerking\n";

    $confirmationSubject = "Bevestiging inschrijving Smakelijk Wandelen $currentYear";
    $confirmationBody = "Beste $naam,\n\n";
    $confirmationBody .= "Bedankt voor uw inschrijving voor Smakelijk Wandelen $currentYear.\n\n";
    $confirmationBody .= "We ontvingen deze gegevens:\n";
    $confirmationBody .= "- Email: $email\n";
    $confirmationBody .= "- Aantal personen: $aantal_personen\n";
    $confirmationBody .= "- Aantal vegetarisch: $aantal_vegi\n";
    if ($opmerking !== '') {
        $confirmationBody .= "- Opmerking: $opmerking\n";
    }
    $confirmationBody .= "\nGelieve het juiste bedrag, EUR 10 per persoon, over te schrijven naar RAAK Achterbos op BE38 9795 4059 7472.\n\n";
    $confirmationBody .= "Met vriendelijke groeten,\n";
    $confirmationBody .= "RAAK Achterbos";

    $adminMailSent = sendEmail($fromEmail, $fromName, $adminEmail, $adminSubject, $adminEmailBody, $email);
    $confirmationMailSent = sendEmail($fromEmail, $fromName, $email, $confirmationSubject, $confirmationBody, $adminEmail);

    $message = "Bedankt $naam! Je inschrijving is ontvangen.";
    if ($confirmationMailSent) {
        $message .= " Er werd een bevestigingsmail gestuurd naar $email.";
    } else {
        $message .= " Je inschrijving is opgeslagen, maar de bevestigingsmail kon niet verstuurd worden.";
    }

    if (!$adminMailSent) {
        $message .= " De interne melding kon ook niet verstuurd worden.";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'confirmationMailSent' => $confirmationMailSent,
        'adminMailSent' => $adminMailSent
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database fout: ' . $e->getMessage()
    ]);
}
?>
