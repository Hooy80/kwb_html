<?php
require_once __DIR__ . '/db_connect.php';
// Enable verbose error reporting for CLI runs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
try {
    $yr = date('Y');
    // write debug start
    @file_put_contents('/tmp/insert_smakelijk_2026.log', "Running insert_smakelijk_2026.php for year: $yr\n", FILE_APPEND);

    $search = $pdo->prepare("SELECT id, date, name, inschrijving FROM calendar WHERE LOWER(name) LIKE LOWER('%smakelijk%wandelen%') AND YEAR(date) = :yr");
    $search->execute([':yr' => $yr]);
    $found = $search->fetchAll(PDO::FETCH_ASSOC);

    @file_put_contents('/tmp/insert_smakelijk_2026.log', "Search returned: " . json_encode($found) . "\n", FILE_APPEND);

    if (count($found) > 0) {
        echo json_encode(['success' => true, 'message' => 'Already exists for this year', 'found' => $found], JSON_PRETTY_PRINT);
        exit;
    }

    // Insert new row for 10 May this year
    $date = "$yr-05-10";
    $stmt = $pdo->prepare("INSERT INTO calendar (date, name, start_hour, stop_hour, place, comment, info, inschrijving, verantwoordelijke) VALUES (:date, :name, :start_hour, :stop_hour, :place, :comment, :info, :inschrijving, :verantwoordelijke)");
    $stmt->execute([
        ':date' => $date,
        ':name' => 'Smakelijk Wandelen',
        ':start_hour' => null,
        ':stop_hour' => null,
        ':place' => 'Ten Aerenkorf',
        ':comment' => '',
        ':info' => '',
        ':inschrijving' => 1,
        ':verantwoordelijke' => null
    ]);

    $id = $pdo->lastInsertId();
    @file_put_contents('/tmp/insert_smakelijk_2026.log', "Inserted id: $id for date $date\n", FILE_APPEND);
    echo json_encode(['success' => true, 'inserted_id' => $id, 'date' => $date], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    @file_put_contents('/tmp/insert_smakelijk_2026.log', "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
