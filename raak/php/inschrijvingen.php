<?php
// Sessie configuratie
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', 1);
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE');

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Include database connectie
require_once __DIR__ . '/db_connect.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Check welke actie wordt gevraagd
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            // Haal alle unieke inschrijvingen op (zonder duplicaten)
            $stmt = $pdo->prepare("
                SELECT DISTINCT
                    id_act,
                    tabel,
                    kolommen
                FROM inschrijvingen
                ORDER BY id_act ASC
            ");
            $stmt->execute();
            $inschrijvingen = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Voor elke inschrijving, haal de activiteitnaam op
            $result = [];
            foreach ($inschrijvingen as $inschrijving) {
                // Haal activiteitnaam op
                $actStmt = $pdo->prepare("SELECT name, date FROM calendar WHERE id = :id");
                $actStmt->execute(['id' => $inschrijving['id_act']]);
                $activity = $actStmt->fetch(PDO::FETCH_ASSOC);

                if ($activity) {
                    // Haal alle jaren op voor deze activiteit
                    $yearStmt = $pdo->prepare("
                        SELECT DISTINCT YEAR(c.date) as jaar
                        FROM inschrijvingen i
                        JOIN calendar c ON i.id_act = c.id
                        WHERE c.name = :name
                        ORDER BY jaar DESC
                    ");
                    $yearStmt->execute(['name' => $activity['name']]);
                    $jaren = $yearStmt->fetchAll(PDO::FETCH_COLUMN);

                    $result[] = [
                        'id_act' => $inschrijving['id_act'],
                        'name' => $activity['name'],
                        'date' => $activity['date'],
                        'tabel' => $inschrijving['tabel'],
                        'kolommen' => $inschrijving['kolommen'],
                        'jaren' => $jaren
                    ];
                }
            }

            echo json_encode(['success' => true, 'inschrijvingen' => $result]);

        } elseif ($action === 'data') {
            // Haal inschrijvingsdata op voor specifieke activiteit en jaar
            $activityName = $_GET['name'] ?? '';
            $jaar = $_GET['jaar'] ?? '';

            if (empty($activityName)) {
                echo json_encode(['success' => false, 'error' => 'Activity name required']);
                exit;
            }

            // Haal de inschrijving info op
            $stmt = $pdo->prepare("
                SELECT i.id_act, i.tabel, i.kolommen, c.name, c.date
                FROM inschrijvingen i
                JOIN calendar c ON i.id_act = c.id
                WHERE c.name = :name
                " . (!empty($jaar) ? "AND YEAR(c.date) = :jaar" : "") . "
                LIMIT 1
            ");
            
            $params = ['name' => $activityName];
            if (!empty($jaar)) {
                $params['jaar'] = $jaar;
            }
            $stmt->execute($params);
            $inschrijving = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inschrijving) {
                echo json_encode(['success' => false, 'error' => 'Inschrijving not found']);
                exit;
            }

            // Parse kolommen
            $kolommen = array_map('trim', explode(',', $inschrijving['kolommen']));
            $tabelNaam = $inschrijving['tabel'];

            // Haal data op uit de inschrijvingstabel
            // Escape table name en kolommen (basic sanitization)
            $tabelNaam = preg_replace('/[^a-zA-Z0-9_]/', '', $tabelNaam);
            $kolommenStr = implode(', ', array_map(function($col) {
                return preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            }, $kolommen));

            $dataStmt = $pdo->prepare("SELECT $kolommenStr FROM $tabelNaam");
            $dataStmt->execute();
            $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'id_act' => $inschrijving['id_act'],
                'activity' => $inschrijving['name'],
                'date' => $inschrijving['date'],
                'tabel' => $tabelNaam,
                'kolommen' => $kolommen,
                'data' => $data
            ]);

        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }

    } elseif ($method === 'PUT') {
        // Update inschrijving - alleen admin en bestuur
        if ($_SESSION['user_functie'] !== 'admin' && $_SESSION['user_functie'] !== 'bestuur') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $tabel = $input['tabel'] ?? null;
        $data = $input['data'] ?? null;

        if (!$id || !$tabel || !$data) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }

        // Escape table name
        $tabel = preg_replace('/[^a-zA-Z0-9_]/', '', $tabel);

        // Build UPDATE query
        $setClauses = [];
        $params = [];
        foreach ($data as $key => $value) {
            $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            $setClauses[] = "$cleanKey = :$cleanKey";
            $params[$cleanKey] = $value;
        }
        $params['id'] = $id;

        $sql = "UPDATE $tabel SET " . implode(', ', $setClauses) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Inschrijving updated']);

    } elseif ($method === 'DELETE') {
        // Delete inschrijving - alleen admin en bestuur
        if ($_SESSION['user_functie'] !== 'admin' && $_SESSION['user_functie'] !== 'bestuur') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $tabel = $input['tabel'] ?? null;

        if (!$id || !$tabel) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }

        // Escape table name
        $tabel = preg_replace('/[^a-zA-Z0-9_]/', '', $tabel);

        $stmt = $pdo->prepare("DELETE FROM $tabel WHERE id = :id");
        $stmt->execute(['id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Inschrijving deleted']);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
