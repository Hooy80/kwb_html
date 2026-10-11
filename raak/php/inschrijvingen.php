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
require_once __DIR__ . '/calendar_inschrijvingen_schema.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Check welke actie wordt gevraagd
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            // Toon open inschrijvingen en activiteiten waarvoor al inschrijvingen bestaan.
            $stmt = $pdo->prepare("
                SELECT
                    c.id AS id_act,
                    c.name,
                    c.date,
                    MAX(i.tabel) AS tabel,
                    MAX(i.kolommen) AS kolommen
                FROM calendar c
                LEFT JOIN inschrijvingen i ON i.id_act = c.id
                WHERE c.inschrijving = 1 OR i.id_act IS NOT NULL
                GROUP BY c.id, c.name, c.date
                ORDER BY c.date DESC, c.id DESC
            ");
            $stmt->execute();
            $result = array_map(static function ($activity) {
                return [
                    'id_act' => (int)$activity['id_act'],
                    'name' => $activity['name'],
                    'date' => $activity['date'],
                    'tabel' => $activity['tabel'],
                    'kolommen' => $activity['kolommen'],
                    'jaren' => [(int)substr($activity['date'], 0, 4)]
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

            echo json_encode(['success' => true, 'inschrijvingen' => $result]);

        } elseif ($action === 'data') {
            // Haal inschrijvingsdata op voor specifieke activiteit en jaar
            $activityId = $_GET['id_act'] ?? '';
            $activityName = $_GET['name'] ?? '';
            $jaar = $_GET['jaar'] ?? '';

            if (empty($activityId) && empty($activityName)) {
                echo json_encode(['success' => false, 'error' => 'Activity name required']);
                exit;
            }

            // Haal de inschrijving info op
            if (!empty($activityId)) {
                $stmt = $pdo->prepare("
                    SELECT
                        c.id AS id_act,
                        c.name,
                        c.date,
                        c.info,
                        c.inschrijving,
                        c.inschrijving_aantal,
                        c.inschrijving_start,
                        c.inschrijving_end,
                        MAX(i.tabel) AS tabel,
                        MAX(i.kolommen) AS kolommen
                    FROM calendar c
                    LEFT JOIN inschrijvingen i ON i.id_act = c.id
                    WHERE c.id = :id AND (c.inschrijving = 1 OR i.id_act IS NOT NULL)
                    GROUP BY c.id, c.name, c.date, c.info, c.inschrijving, c.inschrijving_aantal, c.inschrijving_start, c.inschrijving_end
                    LIMIT 1
                ");
                $stmt->execute(['id' => $activityId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT i.id_act, i.tabel, i.kolommen, c.name, c.date, c.info,
                        c.inschrijving, c.inschrijving_aantal, c.inschrijving_start, c.inschrijving_end
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
            }
            $inschrijving = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inschrijving) {
                echo json_encode(['success' => false, 'error' => 'Activity not found']);
                exit;
            }

            $isGenericRegistration = (int)$inschrijving['inschrijving'] === 1
                && stripos($inschrijving['name'], 'smakelijk wandelen') === false;
            if ($isGenericRegistration) {
                $registrationSchema = getCalendarInschrijvingenSchema($pdo);
                $tabelNaam = 'calendar_inschrijvingen';
                $kolommen = ['id', $registrationSchema['activity'], 'naam', 'email', $registrationSchema['people'], 'info'];
                $dataStmt = $pdo->prepare("
                    SELECT id, {$registrationSchema['activity']}, naam, email, {$registrationSchema['people']}, info
                    FROM calendar_inschrijvingen
                    WHERE {$registrationSchema['activity']} = :id_act
                    ORDER BY id ASC
                ");
                $dataStmt->execute(['id_act' => $inschrijving['id_act']]);
                $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

                $registeredStmt = $pdo->prepare("SELECT COALESCE(SUM({$registrationSchema['people']}), 0) FROM calendar_inschrijvingen WHERE {$registrationSchema['activity']} = :id_act");
                $registeredStmt->execute(['id_act' => $inschrijving['id_act']]);
                $registeredCount = (int)$registeredStmt->fetchColumn();
                $maxRegistrations = (int)$inschrijving['inschrijving_aantal'];
                $today = date('Y-m-d');
                $startDate = $inschrijving['inschrijving_start'];
                $endDate = $inschrijving['inschrijving_end'];
                $hasStartDate = $startDate && $startDate !== '0000-00-00';
                $hasEndDate = $endDate && $endDate !== '0000-00-00';
                $isOpen = (!$hasStartDate || $today >= $startDate) && (!$hasEndDate || $today <= $endDate);
                $isFull = $maxRegistrations < 1 || $registeredCount >= $maxRegistrations;
            } else {
                $kolommen = $inschrijving['kolommen']
                    ? array_map('trim', explode(',', $inschrijving['kolommen']))
                    : [];
                $tabelNaam = $inschrijving['tabel'] ?? null;

                if ($tabelNaam && $kolommen) {
                    // Haal data op uit de bestaande activiteit-specifieke inschrijvingstabel.
                    $tabelNaam = preg_replace('/[^a-zA-Z0-9_]/', '', $tabelNaam);
                    $kolommenStr = implode(', ', array_map(function($col) {
                        return preg_replace('/[^a-zA-Z0-9_]/', '', $col);
                    }, $kolommen));

                    $dataStmt = $pdo->prepare("SELECT $kolommenStr FROM $tabelNaam");
                    $dataStmt->execute();
                    $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $data = [];
                }

                $registeredCount = null;
                $maxRegistrations = null;
                $isFull = false;
                $today = date('Y-m-d');
                $startDate = $inschrijving['inschrijving_start'];
                $endDate = $inschrijving['inschrijving_end'];
                $hasStartDate = $startDate && $startDate !== '0000-00-00';
                $hasEndDate = $endDate && $endDate !== '0000-00-00';
                $isOpen = (!$hasStartDate || $today >= $startDate) && (!$hasEndDate || $today <= $endDate);
            }

            echo json_encode([
                'success' => true,
                'id_act' => $inschrijving['id_act'],
                'activity' => $inschrijving['name'],
                'date' => $inschrijving['date'],
                'info' => $inschrijving['info'],
                'inschrijving' => (int)$inschrijving['inschrijving'],
                'inschrijvingen_aantal' => $inschrijving['inschrijving_aantal'] === null ? null : (int)$inschrijving['inschrijving_aantal'],
                'inschrijvingen_start' => $startDate,
                'inschrijvingen_end' => $endDate,
                'registered_count' => $registeredCount,
                'is_generic_registration' => $isGenericRegistration,
                'is_open' => $isOpen,
                'is_full' => $isFull,
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
