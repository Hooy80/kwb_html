<?php
// Read-only checker for Smakelijk Wandelen 2026-05-10
// Usage (browser): https://raakachterbos.be/php/check_smakelijk_2026.php
// Does not modify DB. Returns JSON.

require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');
try {
    // Exact date match
    $stmt = $pdo->prepare("SELECT id, date, name, inschrijving, place, comment, info, verantwoordelijke FROM calendar WHERE date = :date AND LOWER(name) LIKE '%smakelijk%'");
    $date = '2026-05-10';
    $stmt->execute([':date' => $date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also list any Smakelijk entries in 2026
    $stmt2 = $pdo->prepare("SELECT id, date, name, inschrijving, place FROM calendar WHERE YEAR(date) = :yr AND LOWER(name) LIKE '%smakelijk%' ORDER BY date");
    $stmt2->execute([':yr' => 2026]);
    $rows2026 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'checked_date' => $date,
        'exact_matches' => $rows,
        'matches_2026' => $rows2026
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
