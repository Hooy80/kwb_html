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
require_once __DIR__ . '/calendar_inschrijvingen_schema.php';

function getMailingRegistrationSources(PDO $pdo): array
{
    $availableTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $sources = [];
    $definitions = [
        'dropping' => ['activity' => 'id_act', 'email' => 'email'],
        'smakelijk_wandelen' => ['activity' => 'id_act', 'email' => 'email'],
        'calendar_inschrijvingen' => ['activity' => null, 'email' => 'email']
    ];

    foreach ($definitions as $table => $definition) {
        if (!in_array($table, $availableTables, true)) {
            continue;
        }

        if ($table === 'calendar_inschrijvingen') {
            $schema = getCalendarInschrijvingenSchema($pdo);
            $definition['activity'] = $schema['activity'];
        }

        $columns = array_column(
            $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC),
            'Field'
        );
        if (!in_array($definition['activity'], $columns, true) || !in_array($definition['email'], $columns, true)) {
            throw new PDOException("De mailinglijstkolommen ontbreken in tabel `$table`.");
        }

        $sources[] = [
            'table' => $table,
            'activity' => $definition['activity'],
            'email' => $definition['email'],
            'opt_in' => in_array('mail', $columns, true) ? 'mail' : null
        ];
    }

    return $sources;
}

function quoteMailingIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    try {
        $sources = getMailingRegistrationSources($pdo);
        if ($action === 'list') {
            $activiteiten = [];
            foreach ($sources as $source) {
                $table = quoteMailingIdentifier($source['table']);
                $activityColumn = quoteMailingIdentifier($source['activity']);
                $stmt = $pdo->query(
                    "SELECT DISTINCT c.name
                     FROM $table r
                     JOIN calendar c ON c.id = r.$activityColumn
                     WHERE c.inschrijving = 1
                     ORDER BY c.name"
                );
                $activiteiten = array_merge($activiteiten, $stmt->fetchAll(PDO::FETCH_COLUMN));
            }

            $activiteiten = array_values(array_unique($activiteiten));
            natcasesort($activiteiten);
            echo json_encode(['success' => true, 'activiteiten' => array_values($activiteiten)]);
        } elseif ($action === 'emails') {
            $requestedActivities = array_values(array_filter(array_map('trim', explode(',', $_GET['activiteiten'] ?? ''))));
            if (!$requestedActivities) {
                echo json_encode(['success' => true, 'emails' => [], 'count' => 0]);
                exit;
            }

            $activityIdsStmt = $pdo->prepare(
                'SELECT id FROM calendar WHERE inschrijving = 1 AND name = :name'
            );
            $emails = [];
            foreach ($requestedActivities as $activityName) {
                $activityIdsStmt->execute(['name' => $activityName]);
                $activityIds = $activityIdsStmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($sources as $source) {
                    $table = quoteMailingIdentifier($source['table']);
                    $activityColumn = quoteMailingIdentifier($source['activity']);
                    $emailColumn = quoteMailingIdentifier($source['email']);
                    $optInFilter = $source['opt_in'] !== null
                        ? ' AND ' . quoteMailingIdentifier($source['opt_in']) . ' = 1'
                        : '';
                    $emailStmt = $pdo->prepare(
                        "SELECT DISTINCT $emailColumn
                         FROM $table
                         WHERE $activityColumn = :activity_id
                           AND $emailColumn IS NOT NULL
                           AND $emailColumn != ''$optInFilter"
                    );
                    foreach ($activityIds as $activityId) {
                        $emailStmt->execute(['activity_id' => $activityId]);
                        $emails = array_merge($emails, $emailStmt->fetchAll(PDO::FETCH_COLUMN));
                    }
                }
            }

            $emails = array_values(array_unique(array_filter($emails)));
            echo json_encode(['success' => true, 'emails' => $emails, 'count' => count($emails)]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ongeldige actie']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        error_log('Mailinglijst GET fout: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
