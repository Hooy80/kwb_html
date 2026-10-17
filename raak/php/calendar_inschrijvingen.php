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

function isDroppingActivity(array $activity): bool
{
    return stripos((string)$activity['name'], 'dropping') !== false;
}

function getRegistrationActivity(PDO $pdo, $activityId, bool $forUpdate = false): ?array
{
    $id = filter_var($activityId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT id, name, date, start_hour, stop_hour, place, comment, info, inschrijving_aantal, inschrijving_start, inschrijving_end
        FROM calendar
        WHERE id = :id
          AND inschrijving = 1
          AND LOWER(name) NOT LIKE '%smakelijk%wandelen%'
        LIMIT 1
    " . ($forUpdate ? 'FOR UPDATE' : ''));
    $stmt->execute(['id' => $id]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);

    return $activity ?: null;
}

function getRegistrationAvailability(PDO $pdo, array $activity): array
{
    if (isDroppingActivity($activity)) {
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(COALESCE(kort, 0) + COALESCE(lang, 0)), 0) FROM dropping WHERE id_act = :id');
    } else {
        $schema = getCalendarInschrijvingenSchema($pdo);
        $stmt = $pdo->prepare("SELECT COALESCE(SUM({$schema['people']}), 0) FROM calendar_inschrijvingen WHERE {$schema['activity']} = :id");
    }
    $stmt->execute(['id' => $activity['id']]);
    $registered = (int)$stmt->fetchColumn();
    $capacity = (int)$activity['inschrijving_aantal'];
    $today = date('Y-m-d');
    $start = $activity['inschrijving_start'];
    $end = $activity['inschrijving_end'];

    $hasStart = $start && $start !== '0000-00-00';
    $hasEnd = $end && $end !== '0000-00-00';
    $open = (!$hasStart || $today >= $start) && (!$hasEnd || $today <= $end);
    $full = $capacity < 1 || $registered >= $capacity;
    $message = '';

    if ($capacity < 1) {
        $message = 'Voor deze activiteit is geen geldig maximumaantal ingesteld.';
    } elseif ($full) {
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

function renderRegistrationPage(?array $activity, array $availability, string $message = '', array $values = [], bool $showForm = false, bool $isError = false): void
{
    $isDropping = $activity !== null && isDroppingActivity($activity);
    $title = $activity ? $activity['name'] : 'Inschrijven';
    $activityDate = $activity ? date('d/m/Y', strtotime($activity['date'])) : '';
    $comment = $activity ? trim((string)$activity['comment']) : '';
    $place = $activity ? trim((string)$activity['place']) : '';
    $info = $activity ? trim((string)$activity['info']) : '';
    $startHour = $activity ? trim((string)$activity['start_hour']) : '';
    $stopHour = $activity ? trim((string)$activity['stop_hour']) : '';
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
          <?php if ($place !== '' || ($startHour !== '' && $stopHour !== '') || $info !== ''): ?>
            <div class="activity-details">
              <?php if ($place !== ''): ?><div><strong>📍 Locatie:</strong> <?= escapeHtml($place) ?></div><?php endif; ?>
              <?php if ($startHour !== '' && $stopHour !== ''): ?><div><strong>🕒 Tijd:</strong> <?= escapeHtml(substr($startHour, 0, 5)) ?> - <?= escapeHtml(substr($stopHour, 0, 5)) ?></div><?php endif; ?>
              <?php if ($info !== ''): ?><div class="info"><strong>ℹ️ Info:</strong><br><?= escapeHtml($info) ?></div><?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if ($formMessage !== ''): ?><p class="<?= escapeHtml($messageClass) ?>"><?= escapeHtml($formMessage) ?></p><?php endif; ?>
          <?php if ($showForm): ?>
            <p>Nog <?= (int)($availability['capacity'] - $availability['registered']) ?> plaatsen beschikbaar.</p>
            <form method="post" action="/php/calendar_inschrijvingen.php">
              <input type="hidden" name="id_act" value="<?= (int)$activity['id'] ?>">
              <label for="naam">Naam</label>
              <input id="naam" name="naam" type="text" maxlength="254" required autocomplete="name" value="<?= escapeHtml($values['naam'] ?? '') ?>">
              <label for="email">E-mailadres</label>
              <input id="email" name="email" type="email" maxlength="254" required autocomplete="email" value="<?= escapeHtml($values['email'] ?? '') ?>">
              <?php if ($isDropping): ?>
                <label for="kort">Aantal deelnemers korte toer</label>
                <input id="kort" name="kort" type="number" min="0" max="<?= (int)($availability['capacity'] - $availability['registered']) ?>" step="1" required value="<?= escapeHtml($values['kort'] ?? '0') ?>">
                <label for="lang">Aantal deelnemers lange toer</label>
                <input id="lang" name="lang" type="number" min="0" max="<?= (int)($availability['capacity'] - $availability['registered']) ?>" step="1" required value="<?= escapeHtml($values['lang'] ?? '0') ?>">
                <small>Schrijf minstens één deelnemer in. Er zijn nog <?= (int)($availability['capacity'] - $availability['registered']) ?> plaatsen beschikbaar voor beide toeren samen.</small>
              <?php else: ?>
                <label for="aantal_personen">Aantal personen</label>
                <input id="aantal_personen" name="aantal_personen" type="number" min="1" max="<?= (int)($availability['capacity'] - $availability['registered']) ?>" step="1" required value="<?= escapeHtml($values['aantal_personen'] ?? '1') ?>">
                <small>Je kan maximaal <?= (int)($availability['capacity'] - $availability['registered']) ?> personen inschrijven.</small>
              <?php endif; ?>
              <label for="inschrijving_info">Extra info (optioneel)</label>
              <textarea id="inschrijving_info" name="info" maxlength="254"><?= escapeHtml($values['info'] ?? '') ?></textarea>
              <small>Maximaal 254 karakters.</small>
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
    'aantal_personen' => trim((string)($_POST['aantal_personen'] ?? '1')),
    'kort' => trim((string)($_POST['kort'] ?? '0')),
    'lang' => trim((string)($_POST['lang'] ?? '0')),
    'info' => trim((string)($_POST['info'] ?? ''))
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $naam = trim((string)($_POST['naam'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $info = trim((string)($_POST['info'] ?? ''));
    $isDropping = isDroppingActivity($activity);
    $kort = filter_var($_POST['kort'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $lang = filter_var($_POST['lang'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $aantalPersonen = $isDropping
        ? (($kort !== false && $lang !== false) ? $kort + $lang : false)
        : filter_var($_POST['aantal_personen'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $infoLength = function_exists('mb_strlen') ? mb_strlen($info, 'UTF-8') : strlen($info);

    if (!$availability['available']) {
        renderRegistrationPage($activity, $availability, $availability['message']);
        exit;
    }
    if ($naam === '' || strlen($naam) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 ||
        $infoLength > 254 ||
        $aantalPersonen === false || ($isDropping && $aantalPersonen < 1) ||
        $aantalPersonen > ($availability['capacity'] - $availability['registered'])) {
        http_response_code(400);
        $validationMessage = $isDropping
            ? 'Controleer je naam, e-mailadres, het aantal deelnemers per toer (samen minstens één), je extra info (maximaal 254 tekens) en het beschikbare aantal plaatsen.'
            : 'Controleer je naam, e-mailadres, het aantal personen en de lengte van je extra info (maximaal 254 tekens).';
        renderRegistrationPage($activity, $availability, $validationMessage, $values, true, true);
        exit;
    }

    $namedLockAcquired = false;
    try {
        $pdo->beginTransaction();
        $lockedActivity = getRegistrationActivity($pdo, $activityId, true);
        if (!$lockedActivity) {
            $pdo->rollBack();
            http_response_code(404);
            renderRegistrationPage(null, [], 'Deze activiteit bestaat niet of heeft geen online inschrijvingen.');
            exit;
        }

        $availability = getRegistrationAvailability($pdo, $lockedActivity);
        if (!$availability['available'] || $aantalPersonen > ($availability['capacity'] - $availability['registered'])) {
            $pdo->rollBack();
            if ($availability['available']) {
                $remaining = $availability['capacity'] - $availability['registered'];
                if (isDroppingActivity($lockedActivity)) {
                    $availability['message'] = 'Er zijn nog maar ' . $remaining . ' plaatsen beschikbaar. Pas het aantal deelnemers per toer aan.';
                    $values['kort'] = (string)min((int)$kort, $remaining);
                    $values['lang'] = (string)min((int)$lang, $remaining - (int)$values['kort']);
                } else {
                    $availability['message'] = 'Er zijn nog maar ' . $remaining . ' plaatsen beschikbaar. Pas het aantal personen aan.';
                    $values['aantal_personen'] = (string)min($aantalPersonen, max(1, $remaining));
                }
            }
            renderRegistrationPage($lockedActivity, $availability, $availability['message'], $values, $availability['available'], true);
            exit;
        }

        if (isDroppingActivity($lockedActivity)) {
            $insert = $pdo->prepare('INSERT INTO dropping (id_act, name, email, kort, lang, info) VALUES (:id_act, :name, :email, :kort, :lang, :info)');
            $insert->execute([
                'id_act' => $lockedActivity['id'],
                'name' => $naam,
                'email' => $email,
                'kort' => $kort,
                'lang' => $lang,
                'info' => $info
            ]);
        } else {
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
                $lockResult = (int)$pdo->query("SELECT GET_LOCK('raak_calendar_inschrijvingen_id', 10)")->fetchColumn();
                if ($lockResult !== 1) {
                    throw new PDOException('Kon geen unieke inschrijvings-ID reserveren.');
                }
                $namedLockAcquired = true;
                $lastId = $pdo->query('SELECT id FROM calendar_inschrijvingen ORDER BY id DESC LIMIT 1 FOR UPDATE')->fetchColumn();
                $insertColumns[] = 'id';
                $insertValues[] = ':id';
                $insertParams['id'] = $lastId === false ? 1 : (int)$lastId + 1;
            }
            $insert = $pdo->prepare('INSERT INTO calendar_inschrijvingen (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')');
            $insert->execute($insertParams);
        }
        $pdo->commit();
        if ($namedLockAcquired) {
            $pdo->query("SELECT RELEASE_LOCK('raak_calendar_inschrijvingen_id')");
            $namedLockAcquired = false;
        }

        $safeActivityName = trim(preg_replace('/[\r\n]+/', ' ', $lockedActivity['name']));
        $activityDate = date('d/m/Y', strtotime($lockedActivity['date']));
        $adminSubject = 'Nieuwe inschrijving: ' . $safeActivityName;
        $adminBody = "Nieuwe inschrijving voor {$safeActivityName} ({$activityDate}).\n\n";
        $adminBody .= "Naam: {$naam}\nE-mailadres: {$email}\n";
        if ($isDropping) {
            $adminBody .= "Korte toer: {$kort}\nLange toer: {$lang}\n";
        } else {
            $adminBody .= "Aantal personen: {$aantalPersonen}\n";
        }
        if ($info !== '') {
            $adminBody .= "Extra info: {$info}\n";
        }
        $confirmationSubject = 'Bevestiging inschrijving: ' . $safeActivityName;
        $confirmationBody = "Beste {$naam},\n\n";
        $confirmationBody .= "Je inschrijving voor {$safeActivityName} op {$activityDate} is ontvangen.\n";
        if ($isDropping) {
            $confirmationBody .= "Korte toer: {$kort}\nLange toer: {$lang}\n";
        } else {
            $confirmationBody .= "Aantal personen: {$aantalPersonen}\n";
        }
        if ($info !== '') {
            $confirmationBody .= "Extra info: {$info}\n";
        }
        $confirmationBody .= "\nMet vriendelijke groeten,\nRAAK Achterbos";
        $fromEmail = 'info@raakachterbos.be';
        $adminMailSent = sendEmail($fromEmail, 'RAAK Achterbos', 'raakmolachterbos@gmail.com', $adminSubject, $adminBody, $email);
        $confirmationSent = sendEmail($fromEmail, 'RAAK Achterbos', $email, $confirmationSubject, $confirmationBody, 'raakmolachterbos@gmail.com');
        $successMessage = 'Bedankt ' . $naam . ', je inschrijving is ontvangen.';
        if (!$adminMailSent || !$confirmationSent) {
            $successMessage .= ' De inschrijving is opgeslagen, maar een e-mail kon niet worden verstuurd.';
        } else {
            $successMessage .= ' Je ontvangt een bevestiging per e-mail.';
        }

        renderRegistrationPage($lockedActivity, $availability, $successMessage);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($namedLockAcquired) {
            $pdo->query("SELECT RELEASE_LOCK('raak_calendar_inschrijvingen_id')");
        }
        error_log('Calendar registration failed: ' . $e->getMessage());
        http_response_code(500);
        renderRegistrationPage($activity, $availability, 'Er is een fout opgetreden bij het verwerken van je inschrijving. Probeer het later opnieuw.', $values, true, true);
    }
    exit;
}

renderRegistrationPage($activity, $availability, '', $values, $availability['available']);
