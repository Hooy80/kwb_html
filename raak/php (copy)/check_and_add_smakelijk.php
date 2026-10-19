<?php
// Script to check for 'Smakelijk Wandelen' in calendar and insert a 2026 entry if missing.
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

try {
    $currentYear = date('Y');
    $stmt = $pdo->prepare("SELECT id, date, name, place, inschrijving FROM calendar WHERE LOWER(name) LIKE LOWER('%smakelijk%wandelen%') ORDER BY date ASC");
    $stmt->execute();
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Find entries for current year
    $stmtYear = $pdo->prepare("SELECT id, date, name, place, inschrijving FROM calendar WHERE LOWER(name) LIKE LOWER('%smakelijk%wandelen%') AND YEAR(date) = :year ORDER BY date ASC");
    $stmtYear->execute([':year' => $currentYear]);
    $yearEntries = $stmtYear->fetchAll(PDO::FETCH_ASSOC);

    $result = [
        'success' => true,
        'currentYear' => $currentYear,
        'foundAllCount' => count($all),
        'foundYearCount' => count($yearEntries),
        'foundAll' => $all,
        'foundYear' => $yearEntries
    ];

    if (count($yearEntries) === 0) {
        // Insert a new 2026 entry: 2026-05-10
        $date = "$currentYear-05-10"; // will be 2026 when run in 2026
        $name = 'Smakelijk Wandelen';
        $start_hour = null;
        $stop_hour = null;
        $place = 'Ten Aerenkorf';
        $comment = '';
        $info = '';
        $inschrijving = 1;
        $verantwoordelijke = null;

        $insert = $pdo->prepare("INSERT INTO calendar (date, name, start_hour, stop_hour, place, comment, info, inschrijving, verantwoordelijke) VALUES (:date, :name, :start_hour, :stop_hour, :place, :comment, :info, :inschrijving, :verantwoordelijke)");
        $insert->execute([
            ':date' => $date,
            ':name' => $name,
            ':start_hour' => $start_hour,
            ':stop_hour' => $stop_hour,
            ':place' => $place,
            ':comment' => $comment,
            ':info' => $info,
            ':inschrijving' => $inschrijving,
            ':verantwoordelijke' => $verantwoordelijke
        ]);

        $newId = $pdo->lastInsertId();
        $result['inserted'] = [
            'id' => $newId,
            'date' => $date,
            'name' => $name,
            'place' => $place,
            'inschrijving' => $inschrijving
        ];

        // Re-query year entries
        $stmtYear->execute([':year' => $currentYear]);
        $result['foundYear'] = $stmtYear->fetchAll(PDO::FETCH_ASSOC);
        $result['foundYearCount'] = count($result['foundYear']);
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
