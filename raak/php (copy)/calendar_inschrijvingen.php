<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/smtp_mail.php';
require_once __DIR__ . '/calendar_inschrijvingen_schema.php';

function escapeHtml($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function getPriceInCents($amount): int
{
    $unitAmount = trim((string)($amount ?? '0'));
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $unitAmount)) {
        throw new RuntimeException('Het inschrijvingsbedrag heeft geen geldig euroformaat.');
    }
    [$euros, $cents] = array_pad(explode('.', $unitAmount, 2), 2, '');
    $euros = ltrim($euros, '0') ?: '0';
    if (strlen($euros) > 9) {
        throw new RuntimeException('Het inschrijvingsbedrag valt buiten het toegestane SEPA-bedrag.');
    }

    return ((int)$euros * 100) + (int)str_pad($cents, 2, '0');
}

function createSepaPaymentDetails(array $activity, int $memberPeople, int $nonMemberPeople = 0): ?array
{
    if ($memberPeople < 0 || $nonMemberPeople < 0) {
        throw new RuntimeException('Het aantal deelnemers is ongeldig.');
    }
    $memberUnitCents = getPriceInCents($activity['inschrijving_bedrag'] ?? 0);
    $nonMemberUnitCents = getPriceInCents($activity['inschrijving_bedrag_nl'] ?? 0);
    if ($memberPeople === 0 && $nonMemberPeople === 0) {
        return null;
    }

    $maximumCents = 99999999999;
    if (($memberPeople > 0 && $memberUnitCents > intdiv($maximumCents, $memberPeople)) ||
        ($nonMemberPeople > 0 && $nonMemberUnitCents > intdiv($maximumCents, $nonMemberPeople))) {
        throw new RuntimeException('Het berekende inschrijvingsbedrag valt buiten het toegestane SEPA-bedrag.');
    }
    $amountCents = ($memberUnitCents * $memberPeople) + ($nonMemberUnitCents * $nonMemberPeople);
    if ($amountCents === 0) {
        return null;
    }
    if ($amountCents > $maximumCents) {
        throw new RuntimeException('Het berekende inschrijvingsbedrag valt buiten het toegestane SEPA-bedrag.');
    }

    $amountText = intdiv($amountCents, 100) . '.' . str_pad((string)($amountCents % 100), 2, '0', STR_PAD_LEFT);
    $reference = 'RAAK-' . (int)$activity['id'] . '-' . strtoupper(bin2hex(random_bytes(4)));
    $iban = 'BE38979540597472';
    $payload = implode("\n", [
        'BCD',
        '002',
        '1',
        'SCT',
        '',
        'RAAK Achterbos',
        $iban,
        'EUR' . $amountText,
        '',
        '',
        $reference,
        ''
    ]);
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
        'size' => '320x320',
        'format' => 'png',
        'data' => $payload
    ], '', '&', PHP_QUERY_RFC3986);

    return [
        'amount' => $amountText,
        'iban' => $iban,
        'reference' => $reference,
        'qr_url' => $qrUrl
    ];
}

function isDroppingActivity(array $activity): bool
{
    return stripos((string)$activity['name'], 'dropping') !== false;
}

function isBierproefavondActivity(array $activity): bool
{
    return stripos((string)$activity['name'], 'bierproefavond') !== false;
}

function isSmakelijkWandelenActivity(array $activity): bool
{
    return stripos((string)$activity['name'], 'smakelijk wandelen') !== false;
}

function getRegistrationActivity(PDO $pdo, $activityId, bool $forUpdate = false): ?array
{
    $id = filter_var($activityId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT id, name, date, start_hour, stop_hour, place, comment, info, inschrijving_aantal,
            inschrijving_bedrag, inschrijving_bedrag_nl, inschrijving_start, inschrijving_end
        FROM calendar
        WHERE id = :id
          AND inschrijving = 1
        LIMIT 1
    " . ($forUpdate ? 'FOR UPDATE' : ''));
    $stmt->execute(['id' => $id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    return $activity ?: null;
}

function getRegistrationAvailability(PDO $pdo, array $activity): array
{
    if (isDroppingActivity($activity)) {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(COALESCE(kort, 0) + COALESCE(lang, 0) + COALESCE(kort_nl, 0) + COALESCE(lang_nl, 0)), 0) FROM dropping WHERE id_act = :id');
    } elseif (isBierproefavondActivity($activity)) {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(COALESCE(leden, 0) + COALESCE(niet_leden, 0)), 0) FROM bierproefavond WHERE id_act = :id');
    } elseif (isSmakelijkWandelenActivity($activity)) {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(COALESCE(aantal_personen, 0) + COALESCE(aantal_vegi, 0)), 0) FROM smakelijk_wandelen WHERE id_act = :id');
    } else {
        $schema = getCalendarInschrijvingenSchema($pdo);
        $stmt = $pdo->prepare("SELECT COALESCE(SUM({$schema['people']}), 0) FROM calendar_inschrijvingen WHERE {$schema['activity']} = :id");
    }
    $stmt->execute(['id' => $activity['id']]);
    $registered = (int)$stmt->fetchColumn();
    $capacity = $activity['inschrijving_aantal'] === null ? null : (int)$activity['inschrijving_aantal'];
    $today = date('Y-m-d');
    $start = $activity['inschrijving_start'];
    $end = $activity['inschrijving_end'];

    $hasStart = $start && $start !== '0000-00-00';
    $hasEnd = $end && $end !== '0000-00-00';
    $open = (!$hasStart || $today >= $start) && (!$hasEnd || $today <= $end);
    $hasCapacityLimit = $capacity !== null && $capacity > 0;
    $full = $hasCapacityLimit && $registered >= $capacity;
    $message = '';

    if ($full) {
        $message = 'Het maximumaantal inschrijvingen is bereikt. Inschrijven is niet meer mogelijk.';
    } elseif ($hasStart && $today < $start) {
        $message = 'Inschrijven kan vanaf ' . date('d/m/Y', strtotime($start)) . '.';
    } elseif ($hasEnd && $today > $end) {
        $message = 'De inschrijvingsperiode is afgesloten.';
    }

    return [
        'registered' => $registered,
        'capacity' => $capacity,
        'open' => $open,
        'full' => $full,
        'available' => $open && !$full,
        'message' => $message
    ];
}

function insertActivityRegistration(PDO $pdo, string $tableName, array $values, ?string &$namedLockName, array $sqlExpressions = []): void
{
    $columns = $pdo->query("SHOW COLUMNS FROM `$tableName`")->fetchAll(PDO::FETCH_ASSOC);
    if (!$columns) {
        throw new PDOException("De tabel `$tableName` heeft geen kolommen.");
    }

    $columnNames = array_column($columns, 'Field');
    foreach (array_merge(array_keys($values), array_keys($sqlExpressions)) as $requiredColumn) {
        if (!in_array($requiredColumn, $columnNames, true)) {
            throw new PDOException("De kolom `$requiredColumn` ontbreekt in de tabel `$tableName`.");
        }
    }

    foreach ($columns as $column) {
        if ($column['Key'] !== 'PRI' || stripos((string)$column['Extra'], 'auto_increment') !== false ||
            array_key_exists($column['Field'], $values)) {
            continue;
        }

        if (!preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/i', (string)$column['Type'])) {
            throw new PDOException("De primaire sleutel `{$column['Field']}` van `$tableName` kan niet automatisch worden ingevuld.");
        }

        $namedLockName = 'raak_' . $tableName . '_registration_id';
        $lock = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
        $lock->execute(['lock_name' => $namedLockName]);
        if ((int)$lock->fetchColumn() !== 1) {
            $namedLockName = null;
            throw new PDOException("Kon geen unieke inschrijvings-ID reserveren voor `$tableName`.");
        }

        $primaryKey = '`' . str_replace('`', '``', $column['Field']) . '`';
        $values[$column['Field']] = (int)$pdo->query("SELECT COALESCE(MAX($primaryKey), 0) + 1 FROM `$tableName`")->fetchColumn();
        break;
    }

    $insertColumns = array_values(array_unique(array_merge(array_keys($values), array_keys($sqlExpressions))));
    $quotedColumns = array_map(static function ($columnName) {
        return '`' . str_replace('`', '``', $columnName) . '`';
    }, $insertColumns);
    $placeholders = array_map(static function ($columnName) use ($sqlExpressions) {
        return $sqlExpressions[$columnName] ?? ':' . $columnName;
    }, $insertColumns);
    $insert = $pdo->prepare("INSERT INTO `$tableName` (" . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')');
    $insert->execute($values);
}

function insertBierproefavondRegistration(PDO $pdo, array $values, ?string &$namedLockName): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM `bierproefavond`')->fetchAll(PDO::FETCH_COLUMN);
    $nameColumn = in_array('name', $columns, true) ? 'name' : (in_array('naam', $columns, true) ? 'naam' : null);
    foreach (['id_act', 'email', 'leden', 'niet_leden'] as $requiredColumn) {
        if (!in_array($requiredColumn, $columns, true)) {
            throw new PDOException("De kolom `$requiredColumn` ontbreekt in de tabel `bierproefavond`.");
        }
    }
    if ($nameColumn === null) {
        throw new PDOException('De naamkolom ontbreekt in de tabel `bierproefavond`.');
    }

    $registration = [
        'id_act' => $values['id_act'],
        $nameColumn => $values['name'],
        'email' => $values['email'],
        'leden' => $values['leden'],
        'niet_leden' => $values['niet_leden']
    ];
    foreach (['info', 'mededeling', 'betaling_ok'] as $optionalColumn) {
        if (in_array($optionalColumn, $columns, true)) {
            $registration[$optionalColumn] = $values[$optionalColumn];
        }
    }

    insertActivityRegistration($pdo, 'bierproefavond', $registration, $namedLockName);
}

function releaseRegistrationLock(PDO $pdo, ?string &$lockName): void
{
    if ($lockName === null) {
        return;
    }

    try {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $release->execute(['lock_name' => $lockName]);
        if ((int)$release->fetchColumn() !== 1) {
            error_log('Calendar registration lock was not released: ' . $lockName);
        }
    } catch (PDOException $e) {
        error_log('Calendar registration lock release failed: ' . $e->getMessage());
    }
    $lockName = null;
}

function renderRegistrationPage(?array $activity, array $availability, string $message = '', array $values = [], bool $showForm = false, bool $isError = false, ?array $payment = null): void
{
    $isDropping = $activity !== null && isDroppingActivity($activity);
    $isBierproefavond = $activity !== null && isBierproefavondActivity($activity);
    $isSmakelijkWandelen = $activity !== null && isSmakelijkWandelenActivity($activity);
    $title = $activity ? $activity['name'] : 'Inschrijven';
    $activityDate = $activity ? date('d/m/Y', strtotime($activity['date'])) : '';
    $comment = $activity ? trim((string)$activity['comment']) : '';
    $place = $activity ? trim((string)$activity['place']) : '';
    $info = $activity ? trim((string)$activity['info']) : '';
    $startHour = $activity ? trim((string)$activity['start_hour']) : '';
    $stopHour = $activity ? trim((string)$activity['stop_hour']) : '';
    $memberAmount = $activity ? (float)($activity['inschrijving_bedrag'] ?? 0) : 0;
    $nonMemberAmount = $activity ? (float)($activity['inschrijving_bedrag_nl'] ?? 0) : 0;
    $remainingSeats = isset($availability['capacity']) && $availability['capacity'] > 0
        ? max(0, $availability['capacity'] - $availability['registered'])
        : null;
    $seatLimitAttribute = $remainingSeats === null ? '' : ' max="' . (int)$remainingSeats . '"';
    $formMessage = $message !== '' ? $message : ($availability['message'] ?? '');
    $messageClass = $isError || empty($availability['available']) ? 'notice error' : 'notice';
    ?>
    <!doctype html>
    <html lang="nl">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Inschrijven - <?= escapeHtml($title) ?></title>
      <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px 14px; background: #f4f6f5; color: #27332e; font: 16px/1.5 Arial, sans-serif; }
        main { max-width: 620px; margin: 0 auto; padding: 28px; border-radius: 12px; background: #fff; box-shadow: 0 3px 18px #00000012; }
        h1 { margin: 0 0 6px; color: #275d46; font-size: 27px; }
        .date { margin: 0 0 18px; color: #59655e; font-size: 18px; }
        .info { margin: 0 0 20px; padding: 14px; border-left: 4px solid #70a987; background: #f4f8f5; white-space: pre-wrap; }
        .comment { margin: 18px 0; white-space: pre-wrap; }
        .activity-details { display: grid; gap: 8px; margin: 0 0 20px; }
        .notice { margin: 18px 0; padding: 12px 14px; border-radius: 6px; background: #edf6ef; color: #245a35; }
        .notice.error { background: #fff0ee; color: #9b2e22; }
        .payment { margin-top: 24px; padding: 18px; border: 1px solid #d6e2da; border-radius: 8px; background: #f7faf8; }
        .payment img { display: block; width: min(100%, 320px); height: auto; margin: 16px auto; }
        .payment-details { overflow-wrap: anywhere; }
        label { display: block; margin: 16px 0 6px; font-weight: 700; }
        input, textarea { width: 100%; padding: 11px; border: 1px solid #bbc5bf; border-radius: 5px; font: inherit; }
        textarea { min-height: 100px; resize: vertical; }
        button { margin-top: 22px; padding: 12px 20px; border: 0; border-radius: 5px; background: #347b54; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        button:hover { background: #286643; }
        small { color: #59655e; }
        @media (max-width: 480px) { main { padding: 22px 18px; } h1 { font-size: 23px; } }
      </style>
    </head>
    <body>
      <main>
        <h1><?= escapeHtml($title) ?></h1>
        <?php if ($activity): ?>
          <p class="date"><?= escapeHtml($activityDate) ?></p>
          <?php if ($comment !== ''): ?><p class="comment"><?= escapeHtml($comment) ?></p><?php endif; ?>
          <?php if ($place !== '' || ($startHour !== '' && $stopHour !== '') || $info !== '' || $activity !== null): ?>
            <div class="activity-details">
              <?php if ($place !== ''): ?><div><strong>📍 Locatie:</strong> <?= escapeHtml($place) ?></div><?php endif; ?>
              <?php if ($startHour !== '' && $stopHour !== ''): ?><div><strong>🕒 Tijd:</strong> <?= escapeHtml(substr($startHour, 0, 5)) ?> - <?= escapeHtml(substr($stopHour, 0, 5)) ?></div><?php endif; ?>
              <?php if ($info !== ''): ?><div class="info"><strong>ℹ️ Info:</strong><br><?= escapeHtml($info) ?></div><?php endif; ?>
              <div><strong>Inschrijvingsgeld leden:</strong> EUR <?= escapeHtml(number_format($memberAmount, 2, ',', '.')) ?> per persoon</div>
              <div><strong>Inschrijvingsgeld niet-leden:</strong> EUR <?= escapeHtml(number_format($nonMemberAmount, 2, ',', '.')) ?> per persoon</div>
            </div>
          <?php endif; ?>
          <?php if ($formMessage !== ''): ?><p class="<?= escapeHtml($messageClass) ?>"><?= escapeHtml($formMessage) ?></p><?php endif; ?>
          <?php if ($payment !== null): ?>
            <section class="payment" aria-labelledby="payment-title">
              <h2 id="payment-title">Betaal het inschrijvingsgeld</h2>
              <p>Scan de SEPA QR-code met je bankapp of voer de overschrijving handmatig uit.</p>
              <img src="<?= escapeHtml($payment['qr_url']) ?>" alt="SEPA-betaal QR-code voor <?= escapeHtml($payment['amount']) ?> euro">
              <div class="payment-details">
                <p><strong>Bedrag:</strong> EUR <?= escapeHtml($payment['amount']) ?></p>
                <p><strong>Begunstigde:</strong> RAAK Achterbos</p>
                <p><strong>IBAN:</strong> <?= escapeHtml($payment['iban']) ?></p>
                <p><strong>Mededeling:</strong> <?= escapeHtml($payment['reference']) ?></p>
              </div>
              <small>Deze inschrijving is opgeslagen; de betaling wordt niet automatisch gecontroleerd.</small>
            </section>
          <?php endif; ?>
          <?php if ($showForm): ?>
            <?php if ($remainingSeats !== null): ?><p>Nog <?= (int)$remainingSeats ?> plaatsen beschikbaar.</p><?php endif; ?>
            <form method="post" action="/php/calendar_inschrijvingen.php">
              <input type="hidden" name="id_act" value="<?= (int)$activity['id'] ?>">
              <label for="naam">Naam</label>
              <input id="naam" name="naam" type="text" maxlength="254" required autocomplete="name" value="<?= escapeHtml($values['naam'] ?? '') ?>">
              <label for="email">E-mailadres</label>
              <input id="email" name="email" type="email" maxlength="254" required autocomplete="email" value="<?= escapeHtml($values['email'] ?? '') ?>">
              <?php if ($isDropping): ?>
                <label for="telefoonnr">Telefoonnummer</label>
                <input id="telefoonnr" name="telefoonnr" type="tel" maxlength="13" pattern="[0-9]{4}/[0-9]{2}\.[0-9]{2}\.[0-9]{2}" placeholder="0000/00.00.00" autocomplete="tel" value="<?= escapeHtml($values['telefoonnr'] ?? '') ?>">
                <label for="kort">Aantal deelnemers korte toer</label>
                <input id="kort" name="kort" type="number" min="0"<?= $seatLimitAttribute ?> step="1" required value="<?= escapeHtml($values['kort'] ?? '0') ?>">
                <label for="lang">Aantal deelnemers lange toer</label>
                <input id="lang" name="lang" type="number" min="0"<?= $seatLimitAttribute ?> step="1" required value="<?= escapeHtml($values['lang'] ?? '0') ?>">
                <label for="kort_nl">Aantal niet-leden korte toer</label>
                <input id="kort_nl" name="kort_nl" type="number" min="0"<?= $seatLimitAttribute ?> step="1" required value="<?= escapeHtml($values['kort_nl'] ?? '0') ?>">
                <label for="lang_nl">Aantal niet-leden lange toer</label>
                <input id="lang_nl" name="lang_nl" type="number" min="0"<?= $seatLimitAttribute ?> step="1" required value="<?= escapeHtml($values['lang_nl'] ?? '0') ?>">
                <small>Schrijf minstens één deelnemer in.<?php if ($remainingSeats !== null): ?> Er zijn nog <?= (int)$remainingSeats ?> plaatsen beschikbaar voor beide toeren samen.<?php endif; ?></small>
              <?php elseif ($isBierproefavond): ?>
                <label for="leden">Aantal leden</label>
                <input id="leden" name="leden" type="number" min="0"<?= $seatLimitAttribute ?> step="1" required value="<?= escapeHtml($values['leden'] ?? '0') ?>">
                <label for="niet_leden">Aantal niet-leden</label>
                <input id="niet_leden" name="niet_leden" type="number" min="0"<?= $seatLimitAttribute ?> step="1" required value="<?= escapeHtml($values['niet_leden'] ?? '0') ?>">
                <small>Schrijf minstens één deelnemer in.<?php if ($remainingSeats !== null): ?> Beide aantallen tellen mee voor de <?= (int)$remainingSeats ?> beschikbare plaatsen.<?php endif; ?></small>
              <?php elseif ($isSmakelijkWandelen): ?>
                <label for="aantal_personen">Aantal personen (niet vegetarisch)</label>
                <input id="aantal_personen" name="aantal_personen" type="number" min="0"<?= $seatLimitAttribute ?> step="1" required value="<?= escapeHtml($values['aantal_personen'] ?? '0') ?>">
                <label for="aantal_vegi">Aantal vegetarisch</label>
                <input id="aantal_vegi" name="aantal_vegi" type="number" min="0"<?= $seatLimitAttribute ?> step="1" value="<?= escapeHtml($values['aantal_vegi'] ?? '0') ?>">
                <small>Schrijf minstens één persoon in.<?php if ($remainingSeats !== null): ?> Beide aantallen samen tellen mee voor de <?= (int)$remainingSeats ?> beschikbare plaatsen.<?php endif; ?></small>
                <label for="opmerking">Speciale diëten/allergieën? (optioneel)</label>
                <textarea id="opmerking" name="opmerking" maxlength="1000"><?= escapeHtml($values['opmerking'] ?? '') ?></textarea>
                <label for="mail" style="display:flex; align-items:center; gap:8px; font-weight:normal;">
                  <input id="mail" name="mail" type="checkbox" value="1" required style="width:auto;" <?= !empty($values['mail']) ? 'checked' : '' ?>>
                  Ik ga akkoord met de <a href="/alg_vw_smak.html" target="_blank" rel="noopener noreferrer">algemene voorwaarden</a>.
                </label>
              <?php else: ?>
                <label for="aantal_personen">Aantal personen</label>
                <input id="aantal_personen" name="aantal_personen" type="number" min="1"<?= $seatLimitAttribute ?> step="1" required value="<?= escapeHtml($values['aantal_personen'] ?? '1') ?>">
                <?php if ($remainingSeats !== null): ?><small>Je kan maximaal <?= (int)$remainingSeats ?> personen inschrijven.</small><?php endif; ?>
              <?php endif; ?>
              <?php if (!$isSmakelijkWandelen): ?>
                <label for="inschrijving_info">Extra info (optioneel)</label>
                <textarea id="inschrijving_info" name="info" maxlength="254"><?= escapeHtml($values['info'] ?? '') ?></textarea>
                <small>Maximaal 254 karakters.</small>
              <?php endif; ?>
              <button type="submit">Inschrijven</button>
            </form>
          <?php endif; ?>
        <?php else: ?>
          <p class="notice error"><?= escapeHtml($formMessage !== '' ? $formMessage : 'Deze activiteit heeft geen online inschrijvingen.') ?></p>
        <?php endif; ?>
      </main>
    </body>
    </html>
    <?php
}

$activityId = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['id_act'] ?? '') : ($_GET['id_act'] ?? '');
$activity = getRegistrationActivity($pdo, $activityId);

if (!$activity) {
    http_response_code(404);
    renderRegistrationPage(null, [], 'Deze activiteit bestaat niet of heeft geen online inschrijvingen.');
    exit;
}

$availability = getRegistrationAvailability($pdo, $activity);
$values = [
    'naam' => trim((string)($_POST['naam'] ?? '')),
    'email' => trim((string)($_POST['email'] ?? '')),
    'telefoonnr' => trim((string)($_POST['telefoonnr'] ?? '')),
    'aantal_personen' => trim((string)($_POST['aantal_personen'] ?? '1')),
    'kort' => trim((string)($_POST['kort'] ?? '0')),
    'lang' => trim((string)($_POST['lang'] ?? '0')),
    'kort_nl' => trim((string)($_POST['kort_nl'] ?? '0')),
    'lang_nl' => trim((string)($_POST['lang_nl'] ?? '0')),
    'leden' => trim((string)($_POST['leden'] ?? '0')),
    'niet_leden' => trim((string)($_POST['niet_leden'] ?? '0')),
    'aantal_vegi' => trim((string)($_POST['aantal_vegi'] ?? '0')),
    'opmerking' => trim((string)($_POST['opmerking'] ?? '')),
    'mail' => isset($_POST['mail']) ? '1' : '',
    'info' => trim((string)($_POST['info'] ?? ''))
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $naam = trim((string)($_POST['naam'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $telefoonnr = trim((string)($_POST['telefoonnr'] ?? ''));
    $info = trim((string)($_POST['info'] ?? ''));
    $isDropping = isDroppingActivity($activity);
    $isBierproefavond = isBierproefavondActivity($activity);
    $isSmakelijkWandelen = isSmakelijkWandelenActivity($activity);
    $kort = filter_var($_POST['kort'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $lang = filter_var($_POST['lang'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $kortNl = filter_var($_POST['kort_nl'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $langNl = filter_var($_POST['lang_nl'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $leden = filter_var($_POST['leden'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $nietLeden = filter_var($_POST['niet_leden'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $aantalPersonen = filter_var($_POST['aantal_personen'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => $isSmakelijkWandelen ? 0 : 1]]);
    $aantalVegi = filter_var($_POST['aantal_vegi'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $totalPeople = $isDropping
        ? (($kort !== false && $lang !== false && $kortNl !== false && $langNl !== false)
            ? $kort + $lang + $kortNl + $langNl
            : false)
        : ($isBierproefavond
            ? (($leden !== false && $nietLeden !== false) ? $leden + $nietLeden : false)
            : ($isSmakelijkWandelen
                ? (($aantalPersonen !== false && $aantalVegi !== false) ? $aantalPersonen + $aantalVegi : false)
                : $aantalPersonen));
    $opmerking = trim((string)($_POST['opmerking'] ?? ''));
    $extraInfo = $isSmakelijkWandelen ? $opmerking : $info;
    $extraInfoLength = function_exists('mb_strlen') ? mb_strlen($extraInfo, 'UTF-8') : strlen($extraInfo);
    $termsAccepted = !$isSmakelijkWandelen || (($_POST['mail'] ?? '') === '1');

    if (!$availability['available']) {
        renderRegistrationPage($activity, $availability, $availability['message']);
        exit;
    }
    if ($naam === '' || strlen($naam) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 ||
        ($isDropping && $telefoonnr !== '' && !preg_match('/^\d{4}\/\d{2}\.\d{2}\.\d{2}$/D', $telefoonnr)) ||
        $extraInfoLength > ($isSmakelijkWandelen ? 1000 : 254) ||
        $totalPeople === false || (($isDropping || $isBierproefavond || $isSmakelijkWandelen) && $totalPeople < 1) ||
        ($availability['capacity'] !== null && $availability['capacity'] > 0 &&
            $totalPeople > ($availability['capacity'] - $availability['registered'])) || !$termsAccepted) {
        http_response_code(400);
        $validationMessage = $isDropping
            ? 'Controleer je naam, e-mailadres, telefoonnummer (formaat 0000/00.00.00), de aantallen leden en niet-leden per toer (samen minstens één), je extra info (maximaal 254 tekens) en het beschikbare aantal plaatsen.'
            : ($isBierproefavond
                ? 'Controleer je naam, e-mailadres, de aantallen leden en niet-leden (samen minstens één) en het beschikbare aantal plaatsen.'
                : ($isSmakelijkWandelen
                    ? 'Controleer je naam, e-mailadres, de aantallen (samen minstens één en binnen de beschikbare plaatsen), je opmerking (maximaal 1000 tekens) en aanvaarding van de algemene voorwaarden.'
                    : 'Controleer je naam, e-mailadres, het aantal personen en de lengte van je extra info (maximaal 254 tekens).'));
        renderRegistrationPage($activity, $availability, $validationMessage, $values, true, true);
        exit;
    }

    $namedLockName = null;
    $registrationStage = 'start transaction';
    try {
        $pdo->beginTransaction();
        $registrationStage = 'reload activity';
        $lockedActivity = getRegistrationActivity($pdo, $activityId, true);
        if (!$lockedActivity) {
            $pdo->rollBack();
            http_response_code(404);
            renderRegistrationPage(null, [], 'Deze activiteit bestaat niet of heeft geen online inschrijvingen.');
            exit;
        }

        $registrationStage = 'check remaining capacity';
        $availability = getRegistrationAvailability($pdo, $lockedActivity);
        $exceedsCapacity = $availability['capacity'] !== null && $availability['capacity'] > 0 &&
            $totalPeople > ($availability['capacity'] - $availability['registered']);
        if (!$availability['available'] || $exceedsCapacity) {
            $pdo->rollBack();
            if ($availability['available']) {
                $remaining = $availability['capacity'] - $availability['registered'];
                if (isDroppingActivity($lockedActivity)) {
                    $availability['message'] = 'Er zijn nog maar ' . $remaining . ' plaatsen beschikbaar. Pas het aantal deelnemers per toer aan.';
                    $values['kort'] = (string)min((int)$kort, $remaining);
                    $values['lang'] = (string)min((int)$lang, $remaining - (int)$values['kort']);
                    $remaining -= (int)$values['kort'] + (int)$values['lang'];
                    $values['kort_nl'] = (string)min((int)$kortNl, $remaining);
                    $values['lang_nl'] = (string)min((int)$langNl, $remaining - (int)$values['kort_nl']);
                } elseif (isBierproefavondActivity($lockedActivity)) {
                    $availability['message'] = 'Er zijn nog maar ' . $remaining . ' plaatsen beschikbaar. Pas de aantallen leden en niet-leden aan.';
                    $values['leden'] = (string)min((int)$leden, $remaining);
                    $values['niet_leden'] = (string)min((int)$nietLeden, $remaining - (int)$values['leden']);
                } elseif (isSmakelijkWandelenActivity($lockedActivity)) {
                    $availability['message'] = 'Er zijn nog maar ' . $remaining . ' plaatsen beschikbaar. Pas het aantal personen aan.';
                    $values['aantal_personen'] = (string)min((int)$aantalPersonen, $remaining);
                    $values['aantal_vegi'] = (string)min((int)$aantalVegi, max(0, $remaining - (int)$values['aantal_personen']));
                } else {
                    $availability['message'] = 'Er zijn nog maar ' . $remaining . ' plaatsen beschikbaar. Pas het aantal personen aan.';
                    $values['aantal_personen'] = (string)min($aantalPersonen, max(1, $remaining));
                }
            }
            renderRegistrationPage($lockedActivity, $availability, $availability['message'], $values, $availability['available'], true);
            exit;
        }

        try {
            $payment = $isDropping
                ? createSepaPaymentDetails($lockedActivity, (int)$kort + (int)$lang, (int)$kortNl + (int)$langNl)
                : ($isBierproefavond
                    ? createSepaPaymentDetails($lockedActivity, (int)$leden, (int)$nietLeden)
                    : createSepaPaymentDetails($lockedActivity, (int)$totalPeople));
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            releaseRegistrationLock($pdo, $namedLockName);
            error_log('Calendar registration payment details could not be prepared: ' . $e->getMessage());
            http_response_code(500);
            renderRegistrationPage($lockedActivity, $availability, 'De betaalgegevens konden niet worden aangemaakt. Probeer het later opnieuw.', $values, true, true);
            exit;
        }
        if (isDroppingActivity($lockedActivity)) {
            $registrationStage = 'save dropping registration';
            $insert = $pdo->prepare('INSERT INTO dropping (id_act, name, email, telefoonnr, kort, lang, kort_nl, lang_nl, info, mededeling, betaling_ok) VALUES (:id_act, :name, :email, :telefoonnr, :kort, :lang, :kort_nl, :lang_nl, :info, :mededeling, :betaling_ok)');
            $insert->execute([
                'id_act' => $lockedActivity['id'],
                'name' => $naam,
                'email' => $email,
                'telefoonnr' => $telefoonnr,
                'kort' => $kort,
                'lang' => $lang,
                'kort_nl' => $kortNl,
                'lang_nl' => $langNl,
                'info' => $info,
                'mededeling' => $payment['reference'] ?? '',
                'betaling_ok' => $payment === null ? 1 : 0
            ]);
        } elseif (isBierproefavondActivity($lockedActivity)) {
            $registrationStage = 'save Bierproefavond registration';
            insertBierproefavondRegistration($pdo, [
                'id_act' => $lockedActivity['id'],
                'name' => $naam,
                'email' => $email,
                'leden' => $leden,
                'niet_leden' => $nietLeden,
                'info' => $info,
                'mededeling' => $payment['reference'] ?? '',
                'betaling_ok' => $payment === null ? 1 : 0
            ], $namedLockName);
        } elseif (isSmakelijkWandelenActivity($lockedActivity)) {
            $registrationStage = 'save Smakelijk Wandelen registration';
            insertActivityRegistration($pdo, 'smakelijk_wandelen', [
                'id_act' => $lockedActivity['id'],
                'naam' => $naam,
                'email' => $email,
                'aantal_personen' => $aantalPersonen,
                'aantal_vegi' => $aantalVegi,
                'opmerking' => $opmerking,
                'mail' => 1
            ], $namedLockName, ['inschrijfdatum' => 'NOW()']);
        } else {
            $registrationStage = 'save activity registration';
            $schema = getCalendarInschrijvingenSchema($pdo);
            $insertColumns = [$schema['activity'], 'naam', 'email', $schema['people'], 'info'];
            $insertValues = [':id_cal', ':naam', ':email', ':aantal', ':info'];
            $insertParams = [
                'id_cal' => $lockedActivity['id'],
                'naam' => $naam,
                'email' => $email,
                'aantal' => $aantalPersonen,
                'info' => $info
            ];
            if (!$schema['id_auto']) {
                $namedLockName = 'raak_calendar_inschrijvingen_id';
                $lock = $pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
                $lock->execute(['lock_name' => $namedLockName]);
                $lockResult = (int)$lock->fetchColumn();
                if ($lockResult !== 1) {
                    $namedLockName = null;
                    throw new PDOException('Kon geen unieke inschrijvings-ID reserveren.');
                }
                $lastId = $pdo->query('SELECT id FROM calendar_inschrijvingen ORDER BY id DESC LIMIT 1 FOR UPDATE')->fetchColumn();
                $insertColumns[] = 'id';
                $insertValues[] = ':id';
                $insertParams['id'] = $lastId === false ? 1 : (int)$lastId + 1;
            }
            $insert = $pdo->prepare('INSERT INTO calendar_inschrijvingen (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')');
            $insert->execute($insertParams);
        }
        $registrationStage = 'commit registration';
        $pdo->commit();
        releaseRegistrationLock($pdo, $namedLockName);

        $registrationStage = 'prepare confirmation email';
        $safeActivityName = trim(preg_replace('/[\r\n]+/', ' ', $lockedActivity['name']));
        $activityDate = date('d/m/Y', strtotime($lockedActivity['date']));
        $adminSubject = $payment !== null
            ? 'Inschrijving wacht op betaling: ' . $safeActivityName
            : 'Nieuwe inschrijving: ' . $safeActivityName;
        $adminBody = $payment !== null
            ? "Nieuwe inschrijving voor {$safeActivityName} ({$activityDate}) - BETALING IN AFWACHTING.\n\n"
            : "Nieuwe inschrijving voor {$safeActivityName} ({$activityDate}).\n\n";
        $adminBody .= "Naam: {$naam}\nE-mailadres: {$email}\n";
        if ($isDropping) {
            $adminBody .= "Telefoonnummer: {$telefoonnr}\n";
            $adminBody .= "Leden - korte toer: {$kort}\nLeden - lange toer: {$lang}\n";
            $adminBody .= "Niet-leden - korte toer: {$kortNl}\nNiet-leden - lange toer: {$langNl}\n";
        } elseif ($isBierproefavond) {
            $adminBody .= "Leden: {$leden}\nNiet-leden: {$nietLeden}\n";
        } elseif ($isSmakelijkWandelen) {
            $adminBody .= "Aantal personen: {$aantalPersonen}\nAantal vegetarisch: {$aantalVegi}\n";
            if ($opmerking !== '') {
                $adminBody .= "Opmerking: {$opmerking}\n";
            }
        } else {
            $adminBody .= "Aantal personen: {$aantalPersonen}\n";
        }
        if (!$isSmakelijkWandelen && $info !== '') {
            $adminBody .= "Extra info: {$info}\n";
        }
        if ($payment !== null) {
            $adminBody .= "Te betalen: EUR {$payment['amount']}\nMededeling: {$payment['reference']}\n";
        }
        $confirmationSubject = 'Bevestiging inschrijving: ' . $safeActivityName;
        $confirmationBody = "Beste {$naam},\n\n";
        $confirmationBody .= "Je inschrijving voor {$safeActivityName} op {$activityDate} is ontvangen.\n";
        if ($isDropping) {
            $confirmationBody .= "Telefoonnummer: {$telefoonnr}\n";
            $confirmationBody .= "Leden - korte toer: {$kort}\nLeden - lange toer: {$lang}\n";
            $confirmationBody .= "Niet-leden - korte toer: {$kortNl}\nNiet-leden - lange toer: {$langNl}\n";
        } elseif ($isBierproefavond) {
            $confirmationBody .= "Leden: {$leden}\nNiet-leden: {$nietLeden}\n";
        } elseif ($isSmakelijkWandelen) {
            $confirmationBody .= "Aantal personen: {$aantalPersonen}\nAantal vegetarisch: {$aantalVegi}\n";
            if ($opmerking !== '') {
                $confirmationBody .= "Opmerking: {$opmerking}\n";
            }
        } else {
            $confirmationBody .= "Aantal personen: {$aantalPersonen}\n";
        }
        if (!$isSmakelijkWandelen && $info !== '') {
            $confirmationBody .= "Extra info: {$info}\n";
        }
        if ($payment !== null) {
            $confirmationBody .= "\nTe betalen: EUR {$payment['amount']}\n";
            $confirmationBody .= "IBAN: {$payment['iban']}\nMededeling: {$payment['reference']}\n";
            $confirmationBody .= "Open de SEPA-betaal QR-code via deze link:\n{$payment['qr_url']}\n";
            $confirmationSubject = 'Betalingsverzoek inschrijving: ' . $safeActivityName;
        }
        $confirmationBody .= "\nMet vriendelijke groeten,\nRAAK Achterbos";
        $fromEmail = 'info@raakachterbos.be';
        $adminMailSent = sendEmail($fromEmail, 'RAAK Achterbos', 'raakmolachterbos@gmail.com', $adminSubject, $adminBody, $email);
        $confirmationSent = sendEmail(
            $fromEmail,
            'RAAK Achterbos',
            $email,
            $confirmationSubject,
            $confirmationBody,
            'raakmolachterbos@gmail.com'
        );
        $successMessage = 'Bedankt ' . $naam . ', je inschrijving is ontvangen.';
        if ($payment !== null) {
            $successMessage .= ' Dit is nog geen definitieve bevestiging. Voer de betaling uit met de SEPA QR-code hieronder of via de link in de e-mail; de betaling wordt door de organisatie gecontroleerd.';
            if (!$confirmationSent) {
                $successMessage .= ' Het betalingsverzoek kon niet per e-mail worden verstuurd.';
            }
            if (!$adminMailSent) {
                $successMessage .= ' De organisatie kon niet automatisch op de hoogte worden gebracht.';
            }
        } elseif (!$adminMailSent || !$confirmationSent) {
            $successMessage .= ' De inschrijving is opgeslagen, maar een e-mail kon niet worden verstuurd.';
        } else {
            $successMessage .= ' Je ontvangt een bevestiging per e-mail.';
        }

        renderRegistrationPage($lockedActivity, $availability, $successMessage, [], false, false, $payment);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        releaseRegistrationLock($pdo, $namedLockName);
        $driverCode = $e->errorInfo[1] ?? 'n/a';
        error_log("Calendar registration failed during {$registrationStage}; SQLSTATE {$e->getCode()}; driver code {$driverCode}: " . $e->getMessage());
        http_response_code(500);
        renderRegistrationPage($activity, $availability, 'Er is een fout opgetreden tijdens het onderdeel “' . $registrationStage . '” (databasecode ' . escapeHtml($driverCode) . '). Probeer het later opnieuw.', $values, true, true);
    }
    exit;
}

renderRegistrationPage($activity, $availability, '', $values, $availability['available']);
