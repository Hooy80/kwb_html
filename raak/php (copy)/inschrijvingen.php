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
require_once __DIR__ . '/smtp_mail.php';

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

function getDroppingPaymentDetails(PDO $pdo, string $quotedPrimaryKey, $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT d.email, d.name, d.kort, d.lang, d.kort_nl, d.lang_nl,
                d.mededeling, d.betaling_ok, c.name AS activity_name, c.date,
                c.inschrijving_bedrag, c.inschrijving_bedrag_nl
         FROM dropping d
         JOIN calendar c ON c.id = d.id_act
         WHERE d.$quotedPrimaryKey = :id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(['id' => $id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    return $registration ?: null;
}

function getDroppingPaymentTotalCents(array $registration): ?int
{
    $totalCents = 0;
    foreach ([
        ['inschrijving_bedrag', (int)($registration['kort'] ?? 0) + (int)($registration['lang'] ?? 0)],
        ['inschrijving_bedrag_nl', (int)($registration['kort_nl'] ?? 0) + (int)($registration['lang_nl'] ?? 0)]
    ] as [$priceColumn, $people]) {
        $amount = trim((string)($registration[$priceColumn] ?? '0'));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) {
            return null;
        }
        [$euros, $cents] = array_pad(explode('.', $amount, 2), 2, '');
        $totalCents += (((int)$euros * 100) + (int)str_pad($cents, 2, '0')) * $people;
    }

    return $totalCents;
}

function sendDroppingPaymentReceipt(array $registration): bool
{
    $totalCents = getDroppingPaymentTotalCents($registration);
    if ($totalCents === null) {
        error_log('Dropping payment receipt not sent: invalid registration amount.');
        return false;
    }
    $totalAmount = intdiv($totalCents, 100) . ',' . str_pad((string)($totalCents % 100), 2, '0', STR_PAD_LEFT);
    $activityDate = date('d/m/Y', strtotime($registration['date']));
    $subject = 'Betaling ontvangen: ' . trim(preg_replace('/[\r\n]+/', ' ', $registration['activity_name']));
    $message = "Beste {$registration['name']},\n\n";
    $message .= "We hebben je betaling voor {$registration['activity_name']} op {$activityDate} ontvangen.\n";
    $message .= "Je inschrijving is hiermee bevestigd.\n";
    $message .= "Leden - korte toer: " . (int)($registration['kort'] ?? 0) . "\n";
    $message .= "Leden - lange toer: " . (int)($registration['lang'] ?? 0) . "\n";
    $message .= "Niet-leden - korte toer: " . (int)($registration['kort_nl'] ?? 0) . "\n";
    $message .= "Niet-leden - lange toer: " . (int)($registration['lang_nl'] ?? 0) . "\n";
    $message .= "Ontvangen bedrag: EUR {$totalAmount}\n";
    $message .= "Mededeling: {$registration['mededeling']}\n\n";
    $message .= "Met vriendelijke groeten,\nRAAK Achterbos";

    return sendEmail(
        'info@raakachterbos.be',
        'RAAK Achterbos',
        $registration['email'],
        $subject,
        $message,
        'raakmolachterbos@gmail.com'
    );
}

function getBierproefavondPaymentDetails(PDO $pdo, string $quotedPrimaryKey, $id): ?array
{
    $columns = array_column(getActivityRegistrationColumns($pdo, 'bierproefavond'), 'Field');
    foreach (['id_act', 'email', 'leden', 'niet_leden', 'betaling_ok'] as $requiredColumn) {
        if (!in_array($requiredColumn, $columns, true)) {
            return null;
        }
    }
    $nameColumn = in_array('name', $columns, true) ? 'name' : (in_array('naam', $columns, true) ? 'naam' : null);
    if ($nameColumn === null) {
        return null;
    }
    $mededeling = in_array('mededeling', $columns, true) ? 'd.`mededeling`' : "''";
    $stmt = $pdo->prepare(
        "SELECT d.`email`, d.`$nameColumn` AS name, d.`leden`, d.`niet_leden`,
                $mededeling AS mededeling, d.`betaling_ok`, c.name AS activity_name, c.date,
                c.inschrijving_bedrag, c.inschrijving_bedrag_nl
         FROM `bierproefavond` d
         JOIN calendar c ON c.id = d.id_act
         WHERE d.$quotedPrimaryKey = :id
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute(['id' => $id]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    return $registration ?: null;
}

function getBierproefavondPaymentTotalCents(array $registration): ?int
{
    $totalCents = 0;
    foreach ([
        ['inschrijving_bedrag', (int)($registration['leden'] ?? 0)],
        ['inschrijving_bedrag_nl', (int)($registration['niet_leden'] ?? 0)]
    ] as [$priceColumn, $people]) {
        $amount = trim((string)($registration[$priceColumn] ?? '0'));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) {
            return null;
        }
        [$euros, $cents] = array_pad(explode('.', $amount, 2), 2, '');
        $totalCents += (((int)$euros * 100) + (int)str_pad($cents, 2, '0')) * $people;
    }

    return $totalCents;
}

function sendBierproefavondPaymentReceipt(array $registration): bool
{
    $totalCents = getBierproefavondPaymentTotalCents($registration);
    if ($totalCents === null) {
        error_log('Bierproefavond payment receipt not sent: invalid registration amount.');
        return false;
    }
    $totalAmount = intdiv($totalCents, 100) . ',' . str_pad((string)($totalCents % 100), 2, '0', STR_PAD_LEFT);
    $activityDate = date('d/m/Y', strtotime($registration['date']));
    $subject = 'Betaling ontvangen: ' . trim(preg_replace('/[\r\n]+/', ' ', $registration['activity_name']));
    $message = "Beste {$registration['name']},\n\n";
    $message .= "We hebben je betaling voor {$registration['activity_name']} op {$activityDate} ontvangen.\n";
    $message .= "Je inschrijving is hiermee bevestigd.\n";
    $message .= "Leden: " . (int)$registration['leden'] . "\n";
    $message .= "Niet-leden: " . (int)$registration['niet_leden'] . "\n";
    $message .= "Ontvangen bedrag: EUR {$totalAmount}\n";
    if ($registration['mededeling'] !== '') {
        $message .= "Mededeling: {$registration['mededeling']}\n";
    }
    $message .= "\nMet vriendelijke groeten,\nRAAK Achterbos";

    return sendEmail(
        'info@raakachterbos.be',
        'RAAK Achterbos',
        $registration['email'],
        $subject,
        $message,
        'raakmolachterbos@gmail.com'
    );
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
                        c.inschrijving_aantal, c.inschrijving_bedrag, c.inschrijving_bedrag_nl,
                        c.inschrijving_start, c.inschrijving_end
                    FROM calendar c
                    WHERE c.id = :id AND c.inschrijving = 1
                    LIMIT 1
                ");
                $stmt->execute(['id' => $activityId]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT c.id AS id_act, c.name, c.date, c.info, c.inschrijving,
                        c.inschrijving_aantal, c.inschrijving_bedrag, c.inschrijving_bedrag_nl,
                        c.inschrijving_start, c.inschrijving_end
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
            $isBierproefavondRegistration = stripos($inschrijving['name'], 'bierproefavond') !== false;
            $isGenericRegistration = true;
            $tabelNaam = $isDroppingRegistration
                ? 'dropping'
                : ($isBierproefavondRegistration ? 'bierproefavond' : getActivityRegistrationTableName($inschrijving['name']));
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

            $peopleColumns = array_values(array_intersect(['kort', 'lang', 'kort_nl', 'lang_nl', 'leden', 'niet_leden'], $columnNames));
            if (!$peopleColumns && in_array('aantal_personen', $columnNames, true)) {
                $peopleColumns = ['aantal_personen'];
            }
            if (in_array('aantal_vegi', $columnNames, true)) {
                $peopleColumns[] = 'aantal_vegi';
            }
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
                'inschrijving_bedrag' => $inschrijving['inschrijving_bedrag'],
                'payment_required' => (is_numeric($inschrijving['inschrijving_bedrag']) && (float)$inschrijving['inschrijving_bedrag'] > 0) ||
                    (is_numeric($inschrijving['inschrijving_bedrag_nl']) && (float)$inschrijving['inschrijving_bedrag_nl'] > 0),
                'inschrijvingen_start' => $startDate,
                'inschrijvingen_end' => $endDate,
                'registered_count' => $registeredCount,
                'is_generic_registration' => $isGenericRegistration,
                'is_dropping_registration' => $isDroppingRegistration,
                'is_bierproefavond_registration' => $isBierproefavondRegistration,
                'is_tiered_registration' => $isDroppingRegistration || $isBierproefavondRegistration,
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
        // Inschrijving users may only update payment status for their own paid tiered activity.
        if (!in_array($_SESSION['user_functie'], ['admin', 'bestuur', 'inschrijving'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;
        $tabel = $input['tabel'] ?? null;
        $data = $input['data'] ?? null;

        if (!$id || !$tabel || !is_array($data) || !$data) {
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }

        // Escape table name
        $tabel = preg_replace('/[^a-zA-Z0-9_]/', '', $tabel);

        $primaryKey = getActivityRegistrationPrimaryKey($pdo, $tabel);
        $columns = array_column(getActivityRegistrationColumns($pdo, $tabel), 'Field');

        if ($_SESSION['user_functie'] === 'inschrijving') {
            $paymentValue = $data['betaling_ok'] ?? null;
            $validPaymentValue = $paymentValue === true || $paymentValue === 1 || $paymentValue === '1'
                ? 1
                : ($paymentValue === false || $paymentValue === 0 || $paymentValue === '0' ? 0 : null);

            if (!in_array($tabel, ['dropping', 'bierproefavond'], true) || array_keys($data) !== ['betaling_ok'] ||
                !in_array('betaling_ok', $columns, true) || $validPaymentValue === null) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Je mag alleen de betaalstatus van je eigen activiteit aanpassen.']);
                exit;
            }

            $quotedPrimaryKey = '`' . str_replace('`', '``', $primaryKey) . '`';
            $pdo->beginTransaction();
            $registration = $tabel === 'dropping'
                ? getDroppingPaymentDetails($pdo, $quotedPrimaryKey, $id)
                : getBierproefavondPaymentDetails($pdo, $quotedPrimaryKey, $id);
            $normalizeName = static function ($value): string {
                return strtolower(preg_replace('/\s+/', '', trim((string)$value)));
            };
            $registrationAmountCents = $registration
                ? ($tabel === 'dropping'
                    ? getDroppingPaymentTotalCents($registration)
                    : getBierproefavondPaymentTotalCents($registration))
                : null;
            $paymentRequired = $registrationAmountCents !== null && $registrationAmountCents > 0;

            if (!$registration || !$paymentRequired ||
                $normalizeName($registration['name']) !== $normalizeName($_SESSION['user_login'] ?? '')) {
                $pdo->rollBack();
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Je mag deze betaalstatus niet aanpassen.']);
                exit;
            }

            $update = $pdo->prepare("UPDATE `$tabel` SET betaling_ok = :betaling_ok WHERE $quotedPrimaryKey = :id");
            $update->execute(['betaling_ok' => $validPaymentValue, 'id' => $id]);
            $pdo->commit();
            $emailSent = null;
            if ((int)$registration['betaling_ok'] !== 1 && $validPaymentValue === 1) {
                $emailSent = $tabel === 'dropping'
                    ? sendDroppingPaymentReceipt($registration)
                    : sendBierproefavondPaymentReceipt($registration);
            }
            echo json_encode([
                'success' => true,
                'message' => 'Betaalstatus bijgewerkt',
                'email_sent' => $emailSent,
                'email_error' => $emailSent === false ? 'De betaalstatus is opgeslagen, maar de bevestigingsmail kon niet worden verstuurd.' : null
            ]);
            exit;
        }

        // Build UPDATE query from actual table columns.
        $setClauses = [];
        $params = [];
        $quotedPrimaryKey = '`' . str_replace('`', '``', $primaryKey) . '`';
        foreach ($data as $key => $value) {
            if (!in_array($key, $columns, true) || $key === $primaryKey || in_array($key, ['id_act', 'id_cal'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ongeldige kolom voor deze wijziging']);
                exit;
            }
            $quotedColumn = '`' . str_replace('`', '``', $key) . '`';
            $setClauses[] = "$quotedColumn = :$key";
            $params[$key] = $value;
        }
        $params['id'] = $id;

        $paymentRegistration = null;
        $paymentTarget = null;
        if (in_array($tabel, ['dropping', 'bierproefavond'], true) && array_key_exists('betaling_ok', $data)) {
            $paymentValue = $data['betaling_ok'];
            $paymentTarget = $paymentValue === true || $paymentValue === 1 || $paymentValue === '1'
                ? 1
                : ($paymentValue === false || $paymentValue === 0 || $paymentValue === '0' ? 0 : null);
            if ($paymentTarget === null) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ongeldige betaalstatus']);
                exit;
            }

            $pdo->beginTransaction();
            $paymentRegistration = $tabel === 'dropping'
                ? getDroppingPaymentDetails($pdo, $quotedPrimaryKey, $id)
                : getBierproefavondPaymentDetails($pdo, $quotedPrimaryKey, $id);
            if ($paymentRegistration === null) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Inschrijving niet gevonden']);
                exit;
            }
        }
        $sql = "UPDATE `$tabel` SET " . implode(', ', $setClauses) . " WHERE $quotedPrimaryKey = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($paymentRegistration !== null) {
            $pdo->commit();
        }

        $emailSent = null;
        $paymentAmountCents = $paymentRegistration !== null
            ? ($tabel === 'dropping'
                ? getDroppingPaymentTotalCents($paymentRegistration)
                : getBierproefavondPaymentTotalCents($paymentRegistration))
            : null;
        if ($paymentRegistration !== null && (int)$paymentRegistration['betaling_ok'] !== 1 &&
            $paymentTarget === 1 && $paymentAmountCents !== null && $paymentAmountCents > 0) {
            $emailSent = $tabel === 'dropping'
                ? sendDroppingPaymentReceipt($paymentRegistration)
                : sendBierproefavondPaymentReceipt($paymentRegistration);
        }
        echo json_encode([
            'success' => true,
            'message' => 'Inschrijving updated',
            'email_sent' => $emailSent,
            'email_error' => $emailSent === false ? 'De betaalstatus is opgeslagen, maar de bevestigingsmail kon niet worden verstuurd.' : null
        ]);

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
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
