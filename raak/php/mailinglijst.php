<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Sessie configuratie
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

// Only admin and bestuur can access
$userFunctie = $_SESSION['user_functie'] ?? '';
if ($userFunctie !== 'admin' && $userFunctie !== 'bestuur') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit;
}

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        // Haal lijst van activiteiten op met inschrijvingen
        try {
            $stmt = $pdo->query("
                SELECT DISTINCT c.name 
                FROM inschrijvingen i
                JOIN calendar c ON i.id_act = c.id
                ORDER BY c.name
            ");
            $activiteiten = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode([
                'success' => true,
                'activiteiten' => $activiteiten
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } elseif ($action === 'emails') {
        // Haal email adressen op voor geselecteerde activiteiten
        $activiteiten = $_GET['activiteiten'] ?? '';
        
        if (empty($activiteiten)) {
            echo json_encode(['success' => true, 'emails' => []]);
            exit;
        }
        
        $activiteitenArray = explode(',', $activiteiten);
        $emails = [];
        
        try {
            foreach ($activiteitenArray as $activiteit) {
                $activiteit = trim($activiteit);
                
                // Haal tabel naam uit inschrijvingen via calendar join
                $stmtTabel = $pdo->prepare("
                    SELECT i.tabel 
                    FROM inschrijvingen i
                    JOIN calendar c ON i.id_act = c.id
                    WHERE c.name = :name 
                    LIMIT 1
                ");
                $stmtTabel->execute([':name' => $activiteit]);
                $inschrijving = $stmtTabel->fetch();
                
                if ($inschrijving && !empty($inschrijving['tabel'])) {
                    $tabel = $inschrijving['tabel'];
                    
                    // Check of tabel bestaat
                    $tableCheckStmt = $pdo->query("SHOW TABLES LIKE '$tabel'");
                    $tableExists = $tableCheckStmt->fetch();
                    
                    if ($tableExists) {
                        // Check eerst of kolom mail bestaat
                        $columnsStmt = $pdo->query("SHOW COLUMNS FROM `$tabel` LIKE 'mail'");
                        $hasMailColumn = $columnsStmt->fetch();
                        
                        if ($hasMailColumn) {
                            // Haal email adressen op waar mail = 1
                            $stmtEmails = $pdo->query("SELECT DISTINCT email FROM `$tabel` WHERE mail = 1 AND email IS NOT NULL AND email != ''");
                            $results = $stmtEmails->fetchAll(PDO::FETCH_COLUMN);
                            $emails = array_merge($emails, $results);
                        } else {
                            // Als mail kolom niet bestaat, haal alle emails op
                            $emailCheckStmt = $pdo->query("SHOW COLUMNS FROM `$tabel` LIKE 'email'");
                            $hasEmailColumn = $emailCheckStmt->fetch();
                            
                            if ($hasEmailColumn) {
                                $stmtEmails = $pdo->query("SELECT DISTINCT email FROM `$tabel` WHERE email IS NOT NULL AND email != ''");
                                $results = $stmtEmails->fetchAll(PDO::FETCH_COLUMN);
                                $emails = array_merge($emails, $results);
                            }
                        }
                    }
                }
            }
            
            // Verwijder duplicaten en filter lege waarden
            $emails = array_unique(array_filter($emails));
            $emails = array_values($emails); // Re-index array
            
            echo json_encode([
                'success' => true,
                'emails' => $emails,
                'count' => count($emails)
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
} elseif ($method === 'POST') {
    // Send email to mailinglist
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
    
    $action = $data['action'] ?? '';
    
    if ($action === 'send') {
        $onderwerp = $data['onderwerp'] ?? '';
        $bericht = $data['bericht'] ?? '';
        $emails = $data['emails'] ?? [];
        
        // Debug: log received data
        error_log("POST data received - onderwerp: '$onderwerp', bericht length: " . strlen($bericht) . ", emails count: " . count($emails));
        
        if (empty($onderwerp)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Onderwerp is verplicht']);
            exit;
        }
        
        if (empty($bericht)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Bericht is verplicht']);
            exit;
        }
        
        if (empty($emails)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email adressen zijn verplicht']);
            exit;
        }
        
        require_once __DIR__ . '/smtp_mail.php';
        
        $fromEmail = 'info@raakachterbos.be';
        $fromName = 'RAAK Achterbos';
        $replyTo = 'raakmolachterbos@gmail.com';
        
        // Format message with unsubscribe link
        $unsubscribeUrl = 'https://raakachterbos.be/php/unsubscribe.php';
        $fullMessage = $bericht . "\n\n---\n";
        $fullMessage .= "Deze mail werd verzonden vanaf info@raakachterbos.be (NOREPLY).\n";
        $fullMessage .= "Voor vragen kun je terecht via raakmolachterbos@gmail.com of het contactformulier op de website.\n\n";
        $fullMessage .= "Wil je geen emails meer ontvangen? Klik hier om uit te schrijven:\n";
        $fullMessage .= $unsubscribeUrl . "\n";
        
        try {
            // Ensure emails is an array
            if (!is_array($emails)) {
                $emails = [$emails];
            }
            
            // Send email with all recipients in BCC for privacy
            // Use RAAK email as "To" address, all real recipients go in BCC
            $success = sendEmail($fromEmail, $fromName, $replyTo, $onderwerp, $fullMessage, $replyTo, $emails);
            
            if ($success) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'Email succesvol verzonden naar ' . count($emails) . ' ontvangers'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Fout bij verzenden email'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Fout bij verzenden: ' . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ongeldige actie']);
    }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Mailinglijst POST Exception: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'success' => false, 
            'message' => 'Server error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>
