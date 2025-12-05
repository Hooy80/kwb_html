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
        
        $stmt = $pdo->prepare("
            INSERT INTO calendar (date, name, start_hour, stop_hour, place, comment, info, inschrijving)
            VALUES (:date, :name, :start_hour, :stop_hour, :place, :comment, :info, :inschrijving)
        ");
        
        $stmt->execute([
            'date' => $input['date'] ?? null,
            'name' => $input['name'] ?? '',
            'start_hour' => $input['start_hour'] ?? null,
            'stop_hour' => $input['stop_hour'] ?? null,
            'place' => $input['place'] ?? null,
            'comment' => $input['comment'] ?? null,
            'info' => $input['info'] ?? null,
            'inschrijving' => $input['inschrijving'] ?? 0
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

        $stmt = $pdo->prepare("
            UPDATE calendar 
            SET date = :date, 
                name = :name, 
                start_hour = :start_hour, 
                stop_hour = :stop_hour, 
                place = :place, 
                comment = :comment, 
                info = :info,
                inschrijving = :inschrijving
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
            'info' => $input['info'] ?? null,
            'inschrijving' => $input['inschrijving'] ?? 0
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
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}
