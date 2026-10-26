<?php
// Sessie configuratie
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
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

// Alleen admin en bestuur mogen berichten zien
if ($userFunctie !== 'admin' && $userFunctie !== 'bestuur') {
    echo json_encode(['success' => false, 'error' => 'Geen toegang']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET - Haal alle berichten op
    if ($method === 'GET') {
        $stmt = $pdo->query("
            SELECT id, naam, email, onderwerp, bericht, datum_ontvangen, gelezen 
            FROM contact_berichten 
            ORDER BY datum_ontvangen DESC
        ");
        $berichten = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'berichten' => $berichten]);
    }
    
    // PUT - Markeer bericht als gelezen
    elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $berichtId = $input['id'] ?? null;
        
        if (!$berichtId) {
            echo json_encode(['success' => false, 'error' => 'Bericht ID ontbreekt']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE contact_berichten SET gelezen = 1 WHERE id = :id");
        $stmt->execute(['id' => $berichtId]);
        
        echo json_encode(['success' => true]);
    }
    
    // DELETE - Verwijder bericht (alleen admin)
    elseif ($method === 'DELETE') {
        if ($userFunctie !== 'admin') {
            echo json_encode(['success' => false, 'error' => 'Alleen admins kunnen berichten verwijderen']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $berichtId = $input['id'] ?? null;
        
        if (!$berichtId) {
            echo json_encode(['success' => false, 'error' => 'Bericht ID ontbreekt']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM contact_berichten WHERE id = :id");
        $stmt->execute(['id' => $berichtId]);
        
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server fout: ' . $e->getMessage()]);
}
