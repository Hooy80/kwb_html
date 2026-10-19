<?php
// Sessie configuratie
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db_connect.php';

function getRegistrationFields($input) {
    if (!is_array($input)) {
        throw new InvalidArgumentException('Ongeldige invoer');
    }

    $amount = $input['inschrijving_bedrag'] ?? '0';
    if (!is_string($amount) && !is_int($amount) && !is_float($amount)) {
        throw new InvalidArgumentException('Vul een geldig inschrijfbedrag in (minimaal 0 euro, maximaal 2 decimalen)');
    }
    $amount = (string)$amount;
    if (!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D', $amount) || (float)$amount > 999999999.99) {
        throw new InvalidArgumentException('Vul een geldig inschrijfbedrag in (minimaal 0 euro, maximaal 2 decimalen)');
    }
    $nonMemberAmount = $input['inschrijving_bedrag_nl'] ?? '0';
    if (!is_string($nonMemberAmount) && !is_int($nonMemberAmount) && !is_float($nonMemberAmount)) {
        throw new InvalidArgumentException('Vul een geldig inschrijfbedrag voor niet-leden in (minimaal 0 euro, maximaal 2 decimalen)');
    }
    $nonMemberAmount = (string)$nonMemberAmount;
    if (!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D', $nonMemberAmount) || (float)$nonMemberAmount > 999999999.99) {
        throw new InvalidArgumentException('Vul een geldig inschrijfbedrag voor niet-leden in (minimaal 0 euro, maximaal 2 decimalen)');
    }

    $enabled = $input['inschrijvingen'] ?? 0;
    if (!in_array($enabled, [0, 1, '0', '1', false, true], true)) {
        throw new InvalidArgumentException('Ongeldige instelling voor inschrijvingen');
    }

    if ((int)$enabled === 0) {
        return [
            'inschrijvingen' => 0,
            'inschrijvingen_aantal' => 0,
            'inschrijvingen_start' => '0000-00-00',
            'inschrijvingen_end' => '0000-00-00',
            'inschrijving_bedrag' => $amount,
            'inschrijving_bedrag_nl' => $nonMemberAmount
        ];
    }

    $aantalInput = $input['inschrijvingen_aantal'] ?? null;
    $aantal = $aantalInput === null || $aantalInput === ''
        ? 0
        : filter_var($aantalInput, FILTER_VALIDATE_INT);
    if ($aantal === false || $aantal < 0) {
        throw new InvalidArgumentException('Vul een geldig maximumaantal inschrijvingen in (0 of leeg voor onbeperkt)');
    }

    $start = $input['inschrijvingen_start'] ?? '';
    $end = $input['inschrijvingen_end'] ?? '';
    $parsedStart = is_string($start) ? DateTime::createFromFormat('!Y-m-d', $start) : false;
    $parsedEnd = is_string($end) ? DateTime::createFromFormat('!Y-m-d', $end) : false;
    if (!$parsedStart || $parsedStart->format('Y-m-d') !== $start ||
        !$parsedEnd || $parsedEnd->format('Y-m-d') !== $end) {
        throw new InvalidArgumentException('Vul een geldige start- en einddatum voor de inschrijvingen in');
    }
    if ($end < $start) {
        throw new InvalidArgumentException('De einddatum van de inschrijvingen mag niet voor de startdatum liggen');
    }

    return [
        'inschrijvingen' => 1,
        'inschrijvingen_aantal' => $aantal,
        'inschrijvingen_start' => $start,
        'inschrijvingen_end' => $end,
        'inschrijving_bedrag' => $amount,
        'inschrijving_bedrag_nl' => $nonMemberAmount
    ];
}

// Check of gebruiker ingelogd is
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Niet ingelogd']);
    exit;
}

$userFunctie = $_SESSION['user_functie'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    // POST - Nieuwe activiteit aanmaken (admin & bestuur)
    if ($method === 'POST') {
        if ($userFunctie !== 'admin' && $userFunctie !== 'bestuur') {
            echo json_encode(['success' => false, 'error' => 'Geen toegang']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $registration = getRegistrationFields($input);
        
        $stmt = $pdo->prepare("
            INSERT INTO calendar (date, name, start_hour, stop_hour, place, comment, info, inschrijving, verantwoordelijke, inschrijving_aantal, inschrijving_start, inschrijving_end, inschrijving_bedrag, inschrijving_bedrag_nl)
            VALUES (:date, :name, :start_hour, :stop_hour, :place, :comment, :info, :inschrijving, :verantwoordelijke, :inschrijving_aantal, :inschrijving_start, :inschrijving_end, :inschrijving_bedrag, :inschrijving_bedrag_nl)
        ");
        
        $stmt->execute([
            'date' => $input['date'] ?? null,
            'name' => $input['name'] ?? '',
            'start_hour' => $input['start_hour'] ?? null,
            'stop_hour' => $input['stop_hour'] ?? null,
            'place' => $input['place'] ?? null,
            'comment' => $input['comment'] ?? null,
            'info' => $input['info'] ?? '',
            'inschrijving' => $registration['inschrijvingen'],
            'verantwoordelijke' => $input['verantwoordelijke'] ?? null,
            'inschrijving_aantal' => $registration['inschrijvingen_aantal'],
            'inschrijving_start' => $registration['inschrijvingen_start'],
            'inschrijving_end' => $registration['inschrijvingen_end'],
            'inschrijving_bedrag' => $registration['inschrijving_bedrag'],
            'inschrijving_bedrag_nl' => $registration['inschrijving_bedrag_nl']
        ]);

        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    }
    
    // PUT - Activiteit bijwerken (admin & bestuur)
    elseif ($method === 'PUT') {
        if ($userFunctie !== 'admin' && $userFunctie !== 'bestuur') {
            echo json_encode(['success' => false, 'error' => 'Geen toegang']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID ontbreekt']);
            exit;
        }
        $registration = getRegistrationFields($input);

        $stmt = $pdo->prepare("
            UPDATE calendar 
            SET date = :date, 
                name = :name, 
                start_hour = :start_hour, 
                stop_hour = :stop_hour, 
                place = :place, 
                comment = :comment, 
                info = :info,
                inschrijving = :inschrijving,
                verantwoordelijke = :verantwoordelijke,
                inschrijving_aantal = :inschrijving_aantal,
                inschrijving_start = :inschrijving_start,
                inschrijving_end = :inschrijving_end,
                inschrijving_bedrag = :inschrijving_bedrag,
                inschrijving_bedrag_nl = :inschrijving_bedrag_nl
            WHERE id = :id
        ");
        
        $stmt->execute([
            'id' => $id,
            'date' => $input['date'] ?? null,
            'name' => $input['name'] ?? '',
            'start_hour' => $input['start_hour'] ?? null,
            'stop_hour' => $input['stop_hour'] ?? null,
            'place' => $input['place'] ?? null,
            'comment' => $input['comment'] ?? null,
            'info' => $input['info'] ?? '',
            'inschrijving' => $registration['inschrijvingen'],
            'verantwoordelijke' => $input['verantwoordelijke'] ?? null,
            'inschrijving_aantal' => $registration['inschrijvingen_aantal'],
            'inschrijving_start' => $registration['inschrijvingen_start'],
            'inschrijving_end' => $registration['inschrijvingen_end'],
            'inschrijving_bedrag' => $registration['inschrijving_bedrag'],
            'inschrijving_bedrag_nl' => $registration['inschrijving_bedrag_nl']
        ]);

        echo json_encode(['success' => true]);
    }
    
    // DELETE - Activiteit verwijderen (admin & bestuur)
    elseif ($method === 'DELETE') {
        if ($userFunctie !== 'admin' && $userFunctie !== 'bestuur') {
            echo json_encode(['success' => false, 'error' => 'Geen toegang']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        
        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID ontbreekt']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM calendar WHERE id = :id");
        $stmt->execute(['id' => $id]);

        echo json_encode(['success' => true]);
    }
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}

?>
