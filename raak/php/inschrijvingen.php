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

function getActivityRegistrationTableName(string $activityName): string
{
    $tableName = strtolower(trim($activityName));
    $tableName = preg_replace('/\s+/', '_', $tableName);
    $tableName = preg_replace('/[^a-z0-9_]/', '_', $tableName);
    $tableName = trim(preg_replace('/_+/', '_', $tableName), '_');

    if ($tableName === '') {
        throw new InvalidArgumentException('De activiteit heeft geen geldige tabelnaam.');
    }

    return $tableName;
}

function getActivityRegistrationColumns(PDO $pdo, string $tableName): array
{
    $columns = $pdo->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(PDO::FETCH_ASSOC);
    if (!$columns) {
        throw new PDOException("De tabel `$tableName` heeft geen kolommen.");
    }

    return $columns;
}

function getActivityRegistrationPrimaryKey(PDO $pdo, string $tableName): string
{
    $columns = getActivityRegistrationColumns($pdo, $tableName);
    foreach ($columns as $column) {
        if ($column['Key'] === 'PRI') {
            return $column['Field'];
        }
    }

    foreach ($columns as $column) {
        if ($column['Field'] === 'id') {
            return 'id';
        }
    }

    throw new PDOException("De tabel `$tableName` heeft geen primaire sleutel.");
}

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // Check welke actie wordt gevraagd
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            // De activiteitenkeuzelijst volgt de inschrijvingsinstelling op calendar.
            $stmt = $pdo->prepare("
                SELECT
                    c.id AS id_act,
                    c.name,
                    c.date,
                    NULL AS tabel,
                    NULL AS kolommen
                FROM calendar c
                WHERE c.inschrijving = 1
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
                        c.id AS id_act, c.name, c.date, c.info, c.inschrijving,
                        c.inschrijving_aantal, c.inschrijving_start, c.inschrijving_end
                    FROM calendar c
                    WHERE c.id = :id AND c.inschrijving = 1
                    LIMIT 1
                ");
                $stmt->execute(['id' => $activityId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT c.id AS id_act, c.name, c.date, c.info, c.inschrijving,
                        c.inschrijving_aantal, c.inschrijving_start, c.inschrijving_end
                    FROM calendar c
                    WHERE c.name = :name AND c.inschrijving = 1
                    " . (!empty($jaar) ? "AND YEAR(c.date) = :jaar" : "") . "
                    ORDER BY c.date DESC
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

            $isDroppingRegistration = stripos($inschrijving['name'], 'dropping') !== false;
            $isSmakelijkRegistration = stripos($inschrijving['name'], 'smakelijk wandelen') !== false;
            $isGenericRegistration = !$isSmakelijkRegistration;
            $tabelNaam = getActivityRegistrationTableName($inschrijving['name']);
            $tableColumns = getActivityRegistrationColumns($pdo, $tabelNaam);
            $columnNames = array_column($tableColumns, 'Field');
            $primaryKeyIndex = array_search('PRI', array_column($tableColumns, 'Key'), true);
            $primaryKey = $primaryKeyIndex === false ? null : $columnNames[$primaryKeyIndex];
            if ($primaryKey === null) {
                $primaryKey = in_array('id', $columnNames, true) ? 'id' : null;
            }
            if ($primaryKey === null) {
                throw new PDOException("De tabel `$tabelNaam` heeft geen primaire sleutel.");
            }

            $quotedColumns = array_map(static function ($column) {
                return '`' . str_replace('`', '``', $column) . '`';
            }, $columnNames);
            if ($primaryKey !== 'id') {
                $quotedColumns[] = "`$primaryKey` AS id";
                $kolommen = ['id', ...array_values(array_diff($columnNames, [$primaryKey]))];
            } else {
                $kolommen = $columnNames;
            }

            $where = in_array('id_act', $columnNames, true) ? ' WHERE `id_act` = :id_act' : '';
            $orderBy = $primaryKey !== 'id' ? "`$primaryKey`" : '`id`';
            $dataStmt = $pdo->prepare('SELECT ' . implode(', ', $quotedColumns) . " FROM `$tabelNaam`$where ORDER BY $orderBy ASC");
            $dataStmt->execute($where !== '' ? ['id_act' => $inschrijving['id_act']] : []);
            $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

            $peopleColumns = in_array('aantal_personen', $columnNames, true)
                ? ['aantal_personen']
                : array_values(array_intersect(['kort', 'lang'], $columnNames));
            if (!$peopleColumns) {
                $peopleColumns = array_values(array_filter($columnNames, static function ($column) {
                    return strpos(strtolower($column), 'aantal') === 0;
                }));
            }
            $registeredCount = 0;
            if ($peopleColumns) {
                $sumExpression = implode(' + ', array_map(static function ($column) {
                    return "COALESCE(`$column`, 0)";
                }, $peopleColumns));
                $countSql = "SELECT COALESCE(SUM($sumExpression), 0) FROM `$tabelNaam`" . ($where !== '' ? ' WHERE `id_act` = :id_act' : '');
                $registeredStmt = $pdo->prepare($countSql);
                $registeredStmt->execute($where !== '' ? ['id_act' => $inschrijving['id_act']] : []);
                $registeredCount = (int)$registeredStmt->fetchColumn();
            }
            $maxRegistrations = $inschrijving['inschrijving_aantal'] === null ? null : (int)$inschrijving['inschrijving_aantal'];
            $today = date('Y-m-d');
            $startDate = $inschrijving['inschrijving_start'];
            $endDate = $inschrijving['inschrijving_end'];
            $hasStartDate = $startDate && $startDate !== '0000-00-00';
            $hasEndDate = $endDate && $endDate !== '0000-00-00';
            $isOpen = (!$hasStartDate || $today >= $startDate) && (!$hasEndDate || $today <= $endDate);
            $isFull = $maxRegistrations !== null && $maxRegistrations > 0 && $registeredCount >= $maxRegistrations;

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
                'is_dropping_registration' => $isDroppingRegistration,
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
        $primaryKey = getActivityRegistrationPrimaryKey($pdo, $tabel);

        $sql = "UPDATE $tabel SET " . implode(', ', $setClauses) . " WHERE $primaryKey = :id";
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

        $primaryKey = getActivityRegistrationPrimaryKey($pdo, $tabel);
        $stmt = $pdo->prepare("DELETE FROM $tabel WHERE $primaryKey = :id");
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
